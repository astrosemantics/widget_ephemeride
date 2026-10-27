<?php
/**
 * Choix du mot du jour : un mot du glossaire, à tour de rôle dans les 12 thèmes,
 * sauf les jours d'événement du ciel (phases de la Lune, équinoxes et solstices,
 * pluies d'étoiles filantes, périhélie et aphélie), où le mot colle à l'actualité.
 */

declare(strict_types=1);

require_once __DIR__ . '/Astro.php';

final class MotDuJour
{
    /** Pluies d'étoiles filantes : [mois-jour du maximum, nom, radiant, conseil]. */
    private const PLUIES = [
        ['01-03', 'Quadrantides', 'dans le Bouvier, au nord-est', 'Regarde après minuit, vers le nord-est.'],
        ['04-22', 'Lyrides', 'près de Véga', 'Regarde après 22 h, vers l’est.'],
        ['05-06', 'Êta Aquarides', 'bas sur l’horizon est avant l’aube', 'Elles sont discrètes chez nous : regarde à l’aube.'],
        ['08-12', 'Perséides', 'dans Persée, au nord-est', 'Allonge-toi après 23 h, loin des lumières.'],
        ['10-21', 'Orionides', 'près d’Orion', 'Regarde après minuit, vers l’est.'],
        ['11-17', 'Léonides', 'dans le Lion', 'Regarde en fin de nuit, vers l’est.'],
        ['12-14', 'Géminides', 'près de Castor, dans les Gémeaux', 'Dès 22 h, vers l’est : c’est la pluie la plus riche de l’année.'],
    ];

    private static ?array $g = null;

    public static function glossaire(): array
    {
        return self::$g ??= require __DIR__ . '/donnees/glossaire.php';
    }

    private static function mot(string $nom): ?array
    {
        foreach (self::glossaire()['mots'] as $m) {
            if ($m['mot'] === $nom) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Le mot du jour : ['mot','theme','definition','exemple','wikipedia','picto','evenement','rang','total'].
     */
    public static function choisir(array $cfg, int $ts, bool $evenements = true): array
    {
        $tz = new DateTimeZone($cfg['fuseau']);
        $jour = (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->setTime(0, 0);
        $g = self::glossaire();
        $total = count($g['ordre']);
        $debut = new DateTimeImmutable((string) ($cfg['debut'] ?? '2026-11-01'), $tz);
        $n = (int) $debut->diff($jour)->format('%r%a');
        $rang = (($n % $total) + $total) % $total;
        $m = $g['mots'][$g['ordre'][$rang]];
        $m['evenement'] = null;
        $m['rang'] = $rang + 1;
        $m['total'] = $total;

        // Dans l'éphéméride, les événements sont déjà annoncés : on garde le mot de la rotation
        $ev = $evenements ? self::evenement($jour, $tz) : null;
        if ($ev !== null && ($e = self::mot($ev[0])) !== null) {
            $e['exemple'] = $ev[1];
            if (isset($ev[2])) {
                $e['picto'] = $ev[2];
            }
            $e['evenement'] = true;
            $e['rang'] = $m['rang'];
            $e['total'] = $total;
            return $e;
        }
        return $m;
    }

    private static function hm(int $ts, DateTimeZone $tz): string
    {
        $t = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        return $t->format('G') . ' h ' . $t->format('i');
    }

    /** Événement du jour : [mot, exemple, picto?] ou null. */
    public static function evenement(DateTimeImmutable $jour, DateTimeZone $tz): ?array
    {
        $md = $jour->format('m-d');
        $t0 = $jour->getTimestamp();
        $t1 = $jour->modify('+1 day')->getTimestamp();

        // Pluies d'étoiles filantes : la veille du maximum, le radiant ; le jour même, la pluie
        foreach (self::PLUIES as [$pic, $nom, $radiant, $conseil]) {
            $veille = (new DateTimeImmutable($jour->format('Y') . '-' . $pic, $tz))->modify('-1 day')->format('m-d');
            if ($md === $pic || $md === $veille) {
                $astro = new Astro(48.4, -4.2);
                $l = $astro->lunePosition($t0 + 26 * 3600);          // vers 2 h du matin
                $gene = $l['fraction'] > 0.6 && $l['levee'] ? ' La Lune, très éclairée, gênera.' : '';
                if ($md === $pic) {
                    return ['Pluie d’étoiles filantes', 'Cette nuit, maximum des ' . $nom . ', dont le radiant est ' . $radiant . '. ' . $conseil . $gene];
                }
                return ['Radiant', 'Demain soir, maximum des ' . $nom . ' : leur radiant est ' . $radiant . '. ' . $conseil];
            }
        }

        // Équinoxes et solstices : la longitude du Soleil passe 0, 90, 180 ou 270°
        $a = Astro::longitudeSoleilA($t0);
        $b = Astro::longitudeSoleilA($t1);
        foreach ([0 => ['Équinoxe', 'équinoxe de printemps', 'jour et nuit durent à peu près 12 heures partout sur Terre.'],
                  90 => ['Solstice', 'solstice d’été', 'c’est le jour le plus long de l’année.'],
                  180 => ['Équinoxe', 'équinoxe d’automne', 'jour et nuit durent à peu près 12 heures partout sur Terre.'],
                  270 => ['Solstice', 'solstice d’hiver', 'c’est le jour le plus court de l’année ; les jours vont rallonger.']] as $l => [$mot, $nom, $suite]) {
            $da = fmod($a - $l + 540, 360) - 180;
            $db = fmod($b - $l + 540, 360) - 180;
            if ($da < 0 && $db >= 0) {
                [$g, $d] = [$t0, $t1];
                for ($i = 0; $i < 20; $i++) {
                    $m = intdiv($g + $d, 2);
                    if (fmod(Astro::longitudeSoleilA($m) - $l + 540, 360) - 180 < 0) {
                        $g = $m;
                    } else {
                        $d = $m;
                    }
                }
                return [$mot, 'Aujourd’hui à ' . self::hm($d, $tz) . ', ' . $nom . ' : ' . $suite];
            }
        }

        // Phases de la Lune
        $astro = new Astro(48.4, -4.2);
        $prec = $astro->lunePosition($t0);
        for ($t = $t0 + 1800; $t <= $t1; $t += 1800) {
            $l = $astro->lunePosition($t);
            if (!$prec['croissante'] && $l['croissante'] && $l['fraction'] < 0.5) {
                return ['Nouvelle Lune', 'Aujourd’hui, nouvelle Lune : les prochaines nuits seront les plus noires du mois, idéales pour les galaxies.', 'lune:0:c'];
            }
            if ($prec['croissante'] && !$l['croissante'] && $l['fraction'] > 0.5) {
                $km = Astro::luneDistance($t);
                if ($km < 362000) {
                    return ['Super Lune', 'Ce soir, pleine Lune à ' . number_format(round($km / 1000) * 1000, 0, ',', ' ') . ' km seulement : une super Lune, qui se lève à l’est au coucher du Soleil.', 'lune:1:c'];
                }
                return ['Pleine Lune', 'Ce soir, pleine Lune : elle se lève à l’est quand le Soleil se couche, et éclaire toute la nuit.', 'lune:1:c'];
            }
            if ($l['croissante'] && $prec['fraction'] < 0.5 && $l['fraction'] >= 0.5) {
                return ['Premier quartier', 'Ce soir, premier quartier : la Lune est au sud au coucher du Soleil. Aux jumelles, suis le terminateur.', 'lune:0.5:c'];
            }
            if (!$l['croissante'] && $prec['fraction'] > 0.5 && $l['fraction'] <= 0.5) {
                return ['Dernier quartier', 'Cette nuit, dernier quartier : la Lune se lève vers minuit et brille au petit matin.', 'lune:0.5:d'];
            }
            $prec = $l;
        }

        // Périhélie et aphélie (dates moyennes, à un ou deux jours près selon les années)
        if ($md === '01-03') {
            return ['Périhélie', 'Ces jours-ci, la Terre passe au plus près du Soleil : environ 147 millions de km, en plein hiver !'];
        }
        if ($md === '07-04') {
            return ['Aphélie', 'Ces jours-ci, la Terre passe au plus loin du Soleil : environ 152 millions de km, en plein été !'];
        }
        return null;
    }
}
