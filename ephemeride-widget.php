<?php
/**
 * Image PNG du widget « Éphéméride astro », tout-en-un (4×6), pour KWGT.
 *
 * Cinq sections : l'éphéméride (Soleil, Lune, nuit), le ciel ce soir (note sur 100 et résumé
 * météo), aujourd'hui dans le ciel, le mot du jour (avec bouton Wikipédia) et « Ce jour-là ».
 * Pour la position du téléphone. Rouge dès que le Soleil passe sous -6°.
 *
 * GET ephemeride-widget.php?lat=…&lon=…&loc=…&z=…   position, nom du lieu, décalage horaire
 * GET …&t=<unix>&theme=rouge|normal                    tests
 *
 * Image transparente de 1002 × 1620 px (334 × 540 dp à densité ×3).
 * L'ancienne version compacte (4×4, sans météo ni mot du jour) reste disponible : ephemeride-4x4.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/garde.php';
require_once __DIR__ . '/lib/Ephemeride.php';
require_once __DIR__ . '/lib/Pictos.php';
require_once __DIR__ . '/lib/CeJour.php';
require_once __DIR__ . '/lib/MotDuJour.php';
require_once __DIR__ . '/lib/Nuit.php';
require_once __DIR__ . '/lib/Theme.php';
$cfg = require __DIR__ . '/config.php';

const H = 540;
const L = 334.0;
const X0 = 18.0;
const X1 = 316.0;

/* ---------- Position du téléphone ---------- */

$maintenant = isset($_GET['t']) && ctype_digit((string) $_GET['t']) ? (int) $_GET['t'] : time();
$lat = filter_var(str_replace(',', '.', (string) ($_GET['lat'] ?? '')), FILTER_VALIDATE_FLOAT);
$lon = filter_var(str_replace(',', '.', (string) ($_GET['lon'] ?? '')), FILTER_VALIDATE_FLOAT);
if ($lat !== false && $lon !== false && abs($lat) <= 90 && abs($lon) <= 180 && ($lat != 0 || $lon != 0)) {
    $km = 6371 * acos(max(-1, min(1, sin(deg2rad($lat)) * sin(deg2rad($cfg['latitude']))
        + cos(deg2rad($lat)) * cos(deg2rad($cfg['latitude'])) * cos(deg2rad($lon - $cfg['longitude'])))));
    $loc = mb_substr(trim(explode(',', (string) ($_GET['loc'] ?? ''))[0]), 0, 24);
    $cfg['lieu'] = $loc !== '' ? $loc : ($km <= 2 ? $cfg['lieu'] : 'Ma position');
    $cfg['latitude'] = (float) $lat;
    $cfg['longitude'] = (float) $lon;
}
$z = (string) ($_GET['z'] ?? '');
$z = preg_match('/^ \d/', $z) ? '+' . substr($z, 1) : trim($z);
if (preg_match('/^[+-]\d{2}:?\d{2}$/', $z)) {
    $cfg['fuseau'] = $z;
}
$tz = new DateTimeZone($cfg['fuseau']);

/* ---------- Calculs ---------- */

$e = new Ephemeride((float) $cfg['latitude'], (float) $cfg['longitude'], $tz);
$r = $e->calculer($maintenant);
$nuit = Nuit::calculer($cfg, $maintenant);
$mot = MotDuJour::choisir($cfg, $maintenant, false);
$cj = CeJour::choisir($r['jour']);

$palette = Theme::choisir($cfg, $maintenant);
$rouge = $palette['rouge'];
$d = new Dessin($palette['couleurs'], H);
$cDoux = $rouge ? $d->nuance('texte', 0.8) : $d->couleur('discret');
$cSep = $d->nuance('discret', 0.3);
$h = static fn(?int $ts): string => $ts === null ? '—' : $e->hm($ts);
$hh = static fn(int $ts): string => (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('G') . ' h';

$jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$j = $r['jour'];
$dateTxt = $jours[(int) $j->format('w')] . ' ' . $j->format('j') . ' ' . $mois[(int) $j->format('n') - 1];

/** Lignes d'un texte, en réduisant la police jusqu'à tenir en $max lignes (sinon « … »). */
$ajuster = static function (string $txt, array $tailles, float $l, int $max, string $fonte = 'normal', float $retrait = 0) use ($d): array {
    foreach ($tailles as $tt) {
        $lignes = $d->couper($txt, $tt, $fonte, $l - $retrait);
        // La première ligne peut être raccourcie d'un retrait : on recoupe la suite à pleine largeur
        if ($retrait > 0 && count($lignes) > 1) {
            $suite = trim(mb_substr($txt, mb_strlen($lignes[0])));
            $lignes = array_merge([$lignes[0]], $d->couper($suite, $tt, $fonte, $l));
        }
        if (count($lignes) <= $max) {
            return [$lignes, $tt];
        }
    }
    $lignes[$max - 1] = $d->raccourcir(implode(' ', array_slice($lignes, $max - 1)), $tt, $fonte, $l);
    return [array_slice($lignes, 0, $max), $tt];
};

/* ---------- Le ciel ce soir : résumé en une phrase ---------- */

$note = $nuit['note'] ?? null;
$sombres = array_values(array_filter($nuit['tranches'] ?? [], static fn($t) => in_array($t['obscurite'], ['astronomique', 'noire'], true)));
$moy = static function (array $xs, string $k): ?float {
    $v = array_filter(array_column($xs, $k), static fn($x) => $x !== null);
    return $v ? array_sum($v) / count($v) : null;
};
if ($note === null) {
    $cielTxt = 'Prévisions météo indisponibles pour l’instant.';
} else {
    $gene = $moy($sombres, 'gene') ?? 50;
    $seeing = $moy($sombres, 'seeing');
    $luneHaute = $sombres ? count(array_filter($sombres, static fn($t) => $t['lune'])) / count($sombres) : 0;
    $fraction = $sombres ? $moy($sombres, 'fraction') : 0;
    $cr = $nuit['creneau'] ?? null;
    $nuages = $gene <= 15 ? 'Ciel dégagé' : ($gene <= 40 ? 'Quelques nuages' : ($gene <= 75 ? 'Ciel souvent nuageux' : 'Ciel couvert'));
    if ($cr) {
        $nuages = ($gene <= 15 ? 'Ciel dégagé' : 'Éclaircie') . ' de ' . $hh($cr['debut']) . ' à ' . $hh($cr['fin']);
    }
    $parts = [$nuages];
    if ($gene <= 75 || $cr) {
        if ($seeing !== null) {
            $parts[] = $seeing >= 4 ? 'air très stable' : ($seeing >= 3 ? 'air stable' : 'air agité');
        }
        if ($luneHaute > 0.5 && $fraction > 0.5) {
            $cielTxt = implode(', ', $parts) . ', mais la Lune éclaire le ciel.';
        } elseif ($luneHaute < 0.2 || $fraction < 0.15) {
            $cielTxt = implode(', ', $parts) . ', sans Lune gênante.';
        } else {
            $cielTxt = implode(', ', $parts) . ', Lune une partie de la nuit.';
        }
    } else {
        $cielTxt = $nuages . ' : pas de créneau pour observer.';
    }
}
[$cielL, $cielT] = $ajuster($cielTxt, [14, 13.5, 13], X1 - (X0 + 54), 2);

/* ---------- Aujourd'hui dans le ciel ---------- */

$ph = $r['phenomenes'];
$phen = $ph['lignes'] ?: ['Pas de planète bien placée ce soir'];

/* ---------- Mot du jour ---------- */

$motTitre = $mot['mot'];
[$defL, $defT] = $ajuster($mot['definition'], [13.5, 13, 12.5], X1 - X0, 4);

/* ---------- Ce jour-là ---------- */

$cjL = [];
if ($cj !== null) {
    $prefixe = $cj['annee'] . ' · ';
    $retrait = $d->largeur($prefixe, 13.5, 'gras');
    [$cjL, $cjT] = $ajuster($cj['texte'], [13.5, 13, 12.5], X1 - X0, 2, 'normal', $retrait);
}

/* ---------- Mise en page : hauteurs, puis espaces égaux entre sections ---------- */

// Hauteurs fixes (place pour 2 phénomènes, 4 lignes de définition, 2 lignes de fait) :
// la zone du bouton Wikipédia reste toujours au même endroit.
$hs = [128, 68, 60, 114, 60];
$espace = (H - 32 - array_sum($hs)) / 4;

$titre = static function (string $t, float $y) use ($d): void {
    $d->texteEspace($t, 14, 'gras', 'accent', X0, $y + 14, 0.1);
};

$d->rectangle(0, 0, L, H, 24, $d->hex($d->hex['fond'], 0.95));
$pictos = new Pictos($d, ['a' => $d->couleur('accent'), 't' => $d->couleur('texte'), 'd' => $d->couleur('discret'),
    'f' => $d->hex($rouge ? '1c0d0c' : '161a26'), 'o' => $d->couleur('alerte')], 36 / 64);
$y = 16.0;
$sep = static function (float $y) use ($d, $cSep): void {
    $d->trait(X0, $y, X1, $y, 0.7, $cSep);
};

// 1. Éphéméride
$titre('ÉPHÉMÉRIDE', $y);
$d->texteDroite($d->raccourcir($dateTxt . ' · ' . $cfg['lieu'], 12, 'normal', 170), 12, 'normal', 'discret', X1, $y + 14);
$colonne = static function (float $x0, string $picto, string $nom, string $lever, string $coucher, string $sous, bool $demain, float $y) use ($d, $pictos, $cDoux): void {
    $pictos->dessiner($picto, $x0 - 2, $y + 26);
    $xt = $x0 + 46;
    $d->texteEspace($nom, 11.5, 'medium', 'discret', $xt, $y + 40, 0.04);
    $d->texte('↑ ' . $lever, 18, 'gras', 'texte', $xt, $y + 62);
    $d->texte('↓ ' . $coucher, 18, 'gras', 'texte', $xt, $y + 84);
    if ($demain) {
        $d->texte('+1', 10.5, 'gras', 'accent', $xt + $d->largeur('↓ ' . $coucher, 18, 'gras') + 3, $y + 75);
    }
    foreach ([12.5, 12, 11.5, 11] as $tt) {
        if ($d->largeur($sous, $tt, 'normal') <= 144) {
            break;
        }
    }
    $d->texteCouleur($d->raccourcir($sous, $tt, 'normal', 144), $tt, 'normal', $cDoux, $x0 + 2, $y + 104);
};
$s = $r['soleil'];
$duree = $s['duree'] !== null ? intdiv($s['duree'], 3600) . ' h ' . str_pad((string) intdiv($s['duree'] % 3600, 60), 2, '0', STR_PAD_LEFT) : '—';
$ecart = $s['ecart'] === null ? '' : ' (' . ($s['ecart'] > 0 ? '+' : ($s['ecart'] < 0 ? '−' : '±')) . abs($s['ecart']) . ' min)';
$colonne(X0, 'soleil', 'SOLEIL', $h($s['lever']), $h($s['coucher']), 'jour ' . $duree . $ecart, false, $y);
$l = $r['lune'];
$colonne(170, 'lune:' . round($l['fraction'], 2) . ':' . ($l['croissante'] ? 'c' : 'd'), 'LUNE',
    $l['lever'] === null ? 'pas de lever' : $h($l['lever']), $h($l['coucher']),
    $l['phase'] . ' · ' . (int) round($l['fraction'] * 100) . ' %', $l['coucher'] !== null && $l['coucherDemain'], $y);
[$nd, $nf] = $r['nuitNoire'];
$rep = 'Nuit tombée ' . $h($r['nuitTombee']) . ' · ' . ($nd ? 'nuit noire ' . $h($nd) . ' → ' . $h($nf) : 'pas de nuit noire');
$d->texteCouleur($d->raccourcir($rep, 12.5, 'normal', X1 - X0), 12.5, 'normal', $cDoux, X0, $y + 124);
$y += $hs[0] + $espace;

// 2. Le ciel ce soir
$sep($y - $espace / 2);
$titre('LE CIEL CE SOIR', $y);
$cx = X0 + 21;
$cy = $y + 45;
if ($note === null) {
    $anneau = $d->couleur('discret');
} elseif ($rouge) {
    $anneau = $d->couleur('accent');
} else {
    $anneau = $d->hex($note >= 70 ? '7fcf9a' : ($note >= 45 ? 'f2b880' : 'e07a6f'));
}
$d->cercle($cx, $cy, 19.5, 3, $anneau);
$d->texte($note === null ? '?' : (string) $note, 15, 'gras', 'texte', $cx, $cy + 5.5, true);
$ty = count($cielL) === 1 ? $cy + 5 : $cy - 4;
foreach ($cielL as $i => $t) {
    $d->texte($t, $cielT, 'normal', 'texte', X0 + 54, $ty + $i * 18);
}
$y += $hs[1] + $espace;

// 3. Aujourd'hui dans le ciel
$sep($y - $espace / 2);
$titre('AUJOURD’HUI DANS LE CIEL', $y);
foreach ($phen as $i => $t) {
    $by = $y + 36 + $i * 20;
    $d->disque(X0 + 4, $by - 5, 3.2, $i === 0 && $ph['evenement'] ? $d->couleur('alerte') : $d->couleur('accent'));
    foreach ([14, 13.5, 13, 12.5] as $tt) {
        if ($d->largeur($t, $tt, 'normal') <= X1 - X0 - 16) {
            break;
        }
    }
    $d->texte($d->raccourcir($t, $tt, 'normal', X1 - X0 - 16), $tt, 'normal', 'texte', X0 + 16, $by);
}
$y += $hs[2] + $espace;

// 4. Mot du jour (bouton Wikipédia : zone KWGT x 210, y 324, 112 × 38)
$sep($y - $espace / 2);
$titre('MOT DU JOUR', $y);
//  $d->pilule(X1 - 96, $y - 1, 96, 25, $d->couleur('accent'), $d->hex($d->hex['fond'], 0.95), 1.5);
//  $d->texte('Wikipédia ↗', 12, 'normal', 'texte', X1 - 48, $y + 16, true);
$d->texte($d->raccourcir($motTitre, 13, 'gras', X1 - X0 - 104), 13, 'gras', 'texte', X0, $y + 36);
foreach ($defL as $i => $t) {
    $d->texte($t, $defT, 'normal', 'texte', X0, $y + 36 + 19 * ($i + 1));
}
$y += $hs[3] + $espace;

// 5. Ce jour-là
if ($cj !== null) {
    $sep($y - $espace / 2);
    $titre('CE JOUR-LÀ', $y);
    $ilya = $cj['ilya'] . ' an' . ($cj['ilya'] > 1 ? 's' : '');
    $d->texteDroite(($cj['date'] !== null ? 'le ' . $cj['date'] . ', ' : '') . 'il y a ' . $ilya, 12, 'normal', 'discret', X1, $y + 14);
    foreach ($cjL as $i => $t) {
        $by = $y + 36 + 18 * $i;
        if ($i === 0) {
            $d->texte($prefixe, $cjT, 'gras', 'texte', X0, $by);
            $d->texte($t, $cjT, 'normal', 'texte', X0 + $d->largeur($prefixe, $cjT, 'gras'), $by);
        } else {
            $d->texte($t, $cjT, 'normal', 'texte', X0, $by);
        }
    }
}

$d->envoyer((int) $cfg['cache']);
