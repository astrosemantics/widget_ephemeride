<?php
/**
 * Choix de la position d'observation, depuis l'adresse du widget :
 *
 *   &pos=lieu        → toujours le lieu de config.php (par défaut, comme avant)
 *   &pos=telephone   → toujours la position du téléphone
 *   &pos=auto        → le lieu de config.php à moins de `rayon_auto_km`, sinon le téléphone
 *   &lat=…&lon=…     → position du téléphone (KWGT : $li(lat)$, $li(lon)$)
 *   &loc=…           → nom du lieu (facultatif, KWGT : $li(loc)$)
 *   &z=+0200         → décalage horaire du téléphone (facultatif, KWGT : $df(Z)$)
 *
 * Les alertes du club (notifier.php) n'utilisent jamais ceci : elles restent sur le lieu de config.php.
 */

declare(strict_types=1);

final class Lieu
{
    public static function appliquer(array $cfg): array
    {
        $pos = strtolower(trim((string) ($_GET['pos'] ?? 'lieu')));
        if (!in_array($pos, ['telephone', 'auto'], true)) {
            return $cfg;
        }
        $lat = filter_var(str_replace(',', '.', (string) ($_GET['lat'] ?? '')), FILTER_VALIDATE_FLOAT);
        $lon = filter_var(str_replace(',', '.', (string) ($_GET['lon'] ?? '')), FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lon === false || abs($lat) > 90 || abs($lon) > 180 || ($lat == 0 && $lon == 0)) {
            return $cfg;                                   // position inconnue : on garde le lieu du club
        }
        $km = 6371 * acos(max(-1.0, min(1.0, sin(deg2rad($lat)) * sin(deg2rad((float) $cfg['latitude']))
            + cos(deg2rad($lat)) * cos(deg2rad((float) $cfg['latitude'])) * cos(deg2rad($lon - (float) $cfg['longitude'])))));
        if ($pos === 'auto' && $km <= (float) ($cfg['rayon_auto_km'] ?? 20)) {
            return $cfg;
        }
        $cfg['latitude'] = (float) $lat;
        $cfg['longitude'] = (float) $lon;
        if ($km > 2) {
            $loc = mb_substr(trim(explode(',', (string) ($_GET['loc'] ?? ''))[0]), 0, 24);
            $cfg['lieu'] = $loc !== '' ? $loc : 'Ma position';
        }
        $z = (string) ($_GET['z'] ?? '');
        $z = preg_match('/^ \d/', $z) ? '+' . substr($z, 1) : trim($z);   // « + » non encodé reçu en espace
        if (preg_match('/^[+-]\d{2}:?\d{2}$/', $z)) {
            $cfg['fuseau'] = $z;
        }
        $cfg['position_telephone'] = true;
        return $cfg;
    }

    /** Fichier de cache météo propre à la position (arrondie à 0,1°, soit ~10 km). */
    public static function fichierMeteo(string $dossier, array $cfg): string
    {
        return sprintf('%s/ciel-%.1f_%.1f.json', $dossier, (float) $cfg['latitude'], (float) $cfg['longitude']);
    }
}
