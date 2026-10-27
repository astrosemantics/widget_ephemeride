<?php
/**
 * Ouvre la page Wikipédia du mot du jour affiché dans l'éphéméride (zone tactile KWGT « Ouvrir un lien »).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/garde.php';
require_once __DIR__ . '/lib/MotDuJour.php';
$cfg = require __DIR__ . '/config.php';

$maintenant = isset($_GET['t']) && ctype_digit((string) $_GET['t']) ? (int) $_GET['t'] : time();
$mot = MotDuJour::choisir($cfg, $maintenant, false);
header('Cache-Control: no-store');
header('Location: ' . $mot['wikipedia'], true, 302);
