<?php
/**
 * « Ce jour-là » : un fait d'histoire de l'astronomie pour la date du jour.
 * Quand plusieurs faits partagent la date, on privilégie les anniversaires ronds
 * (100, 50, 25, 10 ans), sinon on alterne d'une année sur l'autre.
 * Les rares dates sans fait empruntent celui du jour le plus proche (± 3 jours).
 */

declare(strict_types=1);

final class CeJour
{
    private const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** ['annee','type','texte','ilya','date' (null si c'est bien aujourd'hui)] ou null. */
    public static function choisir(DateTimeImmutable $jour): ?array
    {
        $faits = require __DIR__ . '/donnees/cejour.php';
        $an = (int) $jour->format('Y');
        foreach ([0, -1, 1, -2, 2, -3, 3] as $dec) {
            $j = $jour->modify(($dec >= 0 ? '+' : '') . $dec . ' day');
            $liste = $faits[$j->format('m-d')] ?? [];
            $liste = array_values(array_filter($liste, static fn($f) => $f[0] < $an));
            if (!$liste) {
                continue;
            }
            $f = null;
            foreach ([100, 50, 25, 10] as $rond) {
                foreach ($liste as $c) {
                    if (($an - $c[0]) % $rond === 0) {
                        $f = $c;
                        break 2;
                    }
                }
            }
            $f ??= $liste[$an % count($liste)];
            return ['annee' => $f[0], 'type' => $f[1], 'texte' => $f[2], 'ilya' => $an - $f[0],
                    'date' => $dec === 0 ? null : ($j->format('j') === '1' ? '1er' : $j->format('j')) . ' ' . self::MOIS[(int) $j->format('n') - 1]];
        }
        return null;
    }
}
