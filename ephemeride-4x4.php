<?php
/**
 * Image PNG du widget « Éphéméride astro » (4×4) pour KWGT.
 *
 * Lever et coucher du Soleil et de la Lune, durée du jour, nuit tombée et nuit noire,
 * phase de la Lune, phénomènes du jour, et un fait d'histoire « Ce jour-là ».
 * Pour la position du téléphone. Rouge la nuit.
 *
 * GET ephemeride-widget.php?lat=…&lon=…&loc=…&z=…   position, nom du lieu, décalage horaire
 * GET …&t=<unix>&theme=rouge|normal                    tests
 *
 * Image transparente de 1002 × 900 px (334 × 300 dp à densité ×3), format 4×4.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/garde.php';
require_once __DIR__ . '/lib/Ephemeride.php';
require_once __DIR__ . '/lib/Pictos.php';
require_once __DIR__ . '/lib/CeJour.php';
require_once __DIR__ . '/lib/Theme.php';
$cfg = require __DIR__ . '/config.php';

const H = 300;
const L = 334.0;

/* ---------- Position du téléphone ---------- */

$maintenant = isset($_GET['t']) && ctype_digit((string) $_GET['t']) ? (int) $_GET['t'] : time();
$lat = filter_var(str_replace(',', '.', (string) ($_GET['lat'] ?? '')), FILTER_VALIDATE_FLOAT);
$lon = filter_var(str_replace(',', '.', (string) ($_GET['lon'] ?? '')), FILTER_VALIDATE_FLOAT);
$lieu = (string) $cfg['lieu'];
if ($lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && ($lat != 0 || $lon != 0)) {
    $km = 6371 * acos(max(-1, min(1, sin(deg2rad($lat)) * sin(deg2rad($cfg['latitude']))
        + cos(deg2rad($lat)) * cos(deg2rad($cfg['latitude'])) * cos(deg2rad($lon - $cfg['longitude'])))));
    $loc = mb_substr(trim(explode(',', (string) ($_GET['loc'] ?? ''))[0]), 0, 24);
    $lieu = $loc !== '' ? $loc : ($km <= 2 ? $lieu : 'Ma position');
    $cfg['latitude'] = (float) $lat;
    $cfg['longitude'] = (float) $lon;
}
$tz = new DateTimeZone($cfg['fuseau']);
$z = (string) ($_GET['z'] ?? '');
$z = preg_match('/^ \d/', $z) ? '+' . substr($z, 1) : trim($z);
if (preg_match('/^[+-]\d{2}:?\d{2}$/', $z)) {
    $tz = new DateTimeZone($z);
}

/* ---------- Calculs ---------- */

$e = new Ephemeride((float) $cfg['latitude'], (float) $cfg['longitude'], $tz);
$r = $e->calculer($maintenant);
$palette = Theme::choisir($cfg, $maintenant);
$rouge = $palette['rouge'];
$d = new Dessin($palette['couleurs'], H);
$cDoux = $rouge ? $d->nuance('texte', 0.8) : $d->couleur('discret');

$jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$j = $r['jour'];
$dateTxt = $jours[(int) $j->format('w')] . ' ' . $j->format('j') . ' ' . $mois[(int) $j->format('n') - 1];
$h = static fn(?int $ts): string => $ts === null ? '—' : $e->hm($ts);

/* ---------- Dessin ---------- */

$d->rectangle(0, 0, L, H, 24, $d->hex($d->hex['fond'], 0.95));
$x = $d->texteEspace('ÉPHÉMÉRIDE ASTRO', 11, 'gras', 'accent', 16, 24);
$d->texteDroite($d->raccourcir($dateTxt . ' · ' . $lieu, 11.5, 'normal', L - $x - 28), 11.5, 'normal', 'discret', L - 16, 24);

$pictos = new Pictos($d, ['a' => $d->couleur('accent'), 't' => $d->couleur('texte'), 'd' => $d->couleur('discret'),
    'f' => $d->hex($rouge ? '1c0d0c' : '161a26'), 'o' => $d->couleur('alerte')], 36 / 64);

/** Une colonne Soleil ou Lune. */
$colonne = static function (float $x0, string $picto, string $titre, string $lever, string $coucher, string $sous, bool $demain = false) use ($d, $pictos, $cDoux): void {
    $pictos->dessiner($picto, $x0, 38);
    $xt = $x0 + 46;
    $d->texteEspace($titre, 11, 'medium', 'discret', $xt, 47, 0.06);
    $d->texte('↑ ' . $lever, 18, 'gras', 'texte', $xt, 70);
    $d->texte('↓ ' . $coucher, 18, 'gras', 'texte', $xt, 93);
    if ($demain) {
        // Coucher le lendemain : petit « +1 » en exposant
        $d->texte('+1', 10.5, 'gras', 'accent', $xt + $d->largeur('↓ ' . $coucher, 18, 'gras') + 3, 84);
    }
    foreach ([12.5, 12, 11.5, 11] as $tt) {
        if ($d->largeur($sous, $tt, 'normal') <= 150) {
            break;
        }
    }
    $d->texteCouleur($d->raccourcir($sous, $tt, 'normal', 150), $tt, 'normal', $cDoux, $x0 + 2, 114);
};

$s = $r['soleil'];
$duree = $s['duree'] !== null ? intdiv($s['duree'], 3600) . ' h ' . str_pad((string) intdiv($s['duree'] % 3600, 60), 2, '0', STR_PAD_LEFT) : '—';
$ecart = $s['ecart'] === null ? '' : ' (' . ($s['ecart'] > 0 ? '+' : ($s['ecart'] < 0 ? '−' : '±')) . abs($s['ecart']) . ' min)';
$colonne(16, 'soleil', 'SOLEIL', $h($s['lever']), $h($s['coucher']), 'jour ' . $duree . $ecart);

$l = $r['lune'];
$f = $l['fraction'];
$picto = 'lune:' . round($f, 2) . ':' . ($l['croissante'] ? 'c' : 'd');
$demain = $l['coucher'] !== null && $l['coucherDemain'];
$colonne(172, $picto, 'LUNE', $l['lever'] === null ? 'pas de lever' : $h($l['lever']), $h($l['coucher']),
    $l['phase'] . ' · ' . (int) round($f * 100) . ' %', $demain);

// Ligne des repères
$d->trait(16, 121, L - 16, 121, 0.7, $d->nuance('discret', 0.3));
[$nd, $nf] = $r['nuitNoire'];
$rep = 'Nuit tombée ' . $h($r['nuitTombee']) . ' · ' . ($nd ? 'nuit noire ' . $h($nd) . ' → ' . $h($nf) : 'pas de nuit noire');
$d->texteCouleur($d->raccourcir($rep, 12.5, 'normal', L - 32), 12.5, 'normal', $cDoux, 16, 139);

// Aujourd'hui dans le ciel
$ph = $r['phenomenes'];
$d->rectangle(12, 150, L - 24, 63, 14, $d->hex($rouge ? '1c0d0c' : '161a26', 0.6));
$d->texteEspace('AUJOURD’HUI DANS LE CIEL', 10.5, 'gras', $ph['evenement'] ? 'alerte' : 'discret', 24, 167, 0.08);
$lignes = $ph['lignes'] ?: ['Pas de planète bien placée ce soir'];
foreach ($lignes as $i => $t) {
    $y = 186 + $i * 19;
    $d->disque(28, $y - 4.5, 3, $i === 0 && $ph['evenement'] ? $d->couleur('alerte') : $d->couleur('accent'));
    foreach ([14, 13.5, 13, 12.5, 12] as $tt) {
        if ($d->largeur($t, $tt, 'normal') <= L - 24 - 40) {
            break;
        }
    }
    $d->texte($d->raccourcir($t, $tt, 'normal', L - 24 - 40), $tt, 'normal', 'texte', 38, $y);
}

// Ce jour-là
$cj = CeJour::choisir($j);
if ($cj !== null) {
    $d->trait(16, 222, L - 16, 222, 0.7, $d->nuance('discret', 0.3));
    $ci = $d->couleur('discret');
    $ix = 17;
    $iy = 230;
    switch ($cj['type']) {
        case 'm':   // fusée
            $d->forme([$ix + 7, $iy, $ix + 10, $iy + 4, $ix + 10, $iy + 10, $ix + 4, $iy + 10, $ix + 4, $iy + 4], $ci);
            $d->trait($ix + 4, $iy + 9, $ix + 1.5, $iy + 13, 1.4, $ci);
            $d->trait($ix + 10, $iy + 9, $ix + 12.5, $iy + 13, 1.4, $ci);
            $d->trait($ix + 7, $iy + 11, $ix + 7, $iy + 14, 1.4, $d->couleur('alerte'));
            break;
        case 'd':   // lunette sur trépied
            $d->trait($ix + 1, $iy + 8, $ix + 13, $iy + 2, 3, $ci);
            $d->trait($ix + 7, $iy + 6, $ix + 3.5, $iy + 14, 1.3, $ci);
            $d->trait($ix + 7, $iy + 6, $ix + 10.5, $iy + 14, 1.3, $ci);
            break;
        case 'p':   // silhouette
            $d->cercle($ix + 7, $iy + 4, 3, 1.4, $ci);
            $d->trait($ix + 2, $iy + 14, $ix + 7, $iy + 9, 1.4, $ci);
            $d->trait($ix + 7, $iy + 9, $ix + 12, $iy + 14, 1.4, $ci);
            break;
        default:    // étoile
            foreach ([0, 45, 90, 135] as $a) {
                $u = deg2rad($a);
                $r = $a % 90 ? 4 : 7;
                $d->trait($ix + 7 - $r * cos($u), $iy + 7 - $r * sin($u), $ix + 7 + $r * cos($u), $iy + 7 + $r * sin($u), 1.4, $ci);
            }
    }
    $d->texteEspace('CE JOUR-LÀ', 10.5, 'gras', 'discret', 37, 241, 0.08);
    $ilya = $cj['ilya'] . ' an' . ($cj['ilya'] > 1 ? 's' : '');
    $d->texteDroite(($cj['date'] !== null ? 'le ' . $cj['date'] . ', ' : '') . 'il y a ' . $ilya, 11, 'normal', 'discret', L - 16, 241);
    $annee = (string) $cj['annee'];
    $d->texte($annee, 22, 'gras', 'accent', 16, 269);
    $xt = 16 + $d->largeur($annee, 22, 'gras') + 10;
    $lt = L - 16 - $xt;
    foreach ([13, 12.5, 12, 11.5, 11] as $tt) {
        $lignes = $d->couper($cj['texte'], $tt, 'normal', $lt);
        if (count($lignes) <= 2) {
            break;
        }
    }
    if (count($lignes) > 2) {
        $lignes = [$lignes[0], $d->raccourcir(implode(' ', array_slice($lignes, 1)), $tt, 'normal', $lt)];
    }
    $y0 = count($lignes) === 1 ? 266 : 259;
    foreach ($lignes as $i => $t) {
        $d->texte($t, $tt, 'normal', 'texte', $xt, $y0 + $i * ($tt + 4));
    }
}

$d->envoyer((int) $cfg['cache']);
