<?php
/**
 * Petits dessins (64 × 64 dp) qui illustrent les mots du glossaire.
 * Couleurs : 'a' accent, 't' texte, 'd' discret, 'f' fond, 'o' orange (alerte).
 */

declare(strict_types=1);

require_once __DIR__ . '/Dessin.php';

final class Pictos
{
    private Dessin $d;
    private array $c;
    private float $x;
    private float $y;

    private float $k;

    public function __construct(Dessin $d, array $couleurs, float $echelle = 1.0)
    {
        $this->d = $d;
        $this->c = $couleurs;
        $this->k = $echelle;
    }

    /** Dessine le picto $cle dans le carré (x, y, 64). Renvoie false si la clé est inconnue. */
    public function dessiner(string $cle, float $x, float $y): bool
    {
        [$this->x, $this->y] = [$x, $y];
        $p = explode(':', $cle);
        $f = 'p' . ucfirst($p[0]);
        if (!method_exists($this, $f)) {
            return false;
        }
        $this->$f(...array_slice($p, 1));
        return true;
    }

    private function X(float $v): float { return $this->x + $v * $this->k; }
    private function Y(float $v): float { return $this->y + $v * $this->k; }
    private function t(float $x1, float $y1, float $x2, float $y2, float $ep, string $c): void
    {
        $this->d->trait($this->X($x1), $this->Y($y1), $this->X($x2), $this->Y($y2), $ep * $this->k, $this->c[$c]);
    }
    private function disque(float $x, float $y, float $r, string $c): void
    {
        $this->d->disque($this->X($x), $this->Y($y), $r * $this->k, $this->c[$c]);
    }
    private function cercle(float $x, float $y, float $r, float $ep, string $c, float $tiret = 0, float $espace = 0): void
    {
        $this->d->cercle($this->X($x), $this->Y($y), $r * $this->k, $ep * $this->k, $this->c[$c], $tiret, $espace);
    }
    private function arc(float $cx, float $cy, float $rx, float $ry, float $de, float $a, float $ep, string $c, float $rot = 0): void
    {
        $prec = null;
        for ($t = $de; $t <= $a + 0.01; $t += 4) {
            $u = deg2rad($t);
            $px = $rx * cos($u);
            $py = $ry * sin($u);
            $r = deg2rad($rot);
            $q = [$cx + $px * cos($r) - $py * sin($r), $cy + $px * sin($r) + $py * cos($r)];
            if ($prec) {
                $this->t($prec[0], $prec[1], $q[0], $q[1], $ep, $c);
            }
            $prec = $q;
        }
    }
    private function forme(array $pts, string $c): void
    {
        $abs = [];
        foreach ($pts as $i => $v) {
            $abs[] = $i % 2 ? $this->Y($v) : $this->X($v);
        }
        $this->d->forme($abs, $this->c[$c]);
    }
    private function texte(string $t, float $x, float $y, float $taille = 8.5): void
    {
        $this->d->texteCouleur($t, $taille * $this->k, 'normal', $this->c['d'], $this->X($x), $this->Y($y), true);
    }

    /* ---------- Les dessins ---------- */

    private function pHorizon(): void
    {
        $this->arc(32, 50, 28, 28, 180, 360, 1, 'd');
        $this->t(2, 50, 62, 50, 1.2, 'd');
        $this->disque(32, 45, 2.4, 't');
        $this->t(32, 47, 32, 50, 1.6, 't');
        $this->t(32, 41, 32, 26, 1.6, 'a');
        $this->forme([32, 21, 28.5, 27, 35.5, 27], 'a');
        $this->texte('zénith', 32, 13);
        $this->texte('horizon', 32, 61);
    }

    /** Lune : fraction éclairée, côté éclairé 'c' (droite, croissante) ou 'd' (gauche). */
    private function pLune(string $f = '0.5', string $sens = 'c'): void
    {
        $f = (float) $f;
        $cx = 32;
        $cy = 32;
        $r = 24;
        $this->disque($cx, $cy, $r, 'f');
        $this->cercle($cx, $cy, $r, 1, 'd');
        if ($f <= 0.01) {
            return;
        }
        $s = $sens === 'd' ? -1 : 1;
        $pts = [];
        for ($t = -90; $t <= 90; $t += 6) {
            $pts[] = $cx + $s * $r * cos(deg2rad($t));
            $pts[] = $cy + $r * sin(deg2rad($t));
        }
        for ($t = 90; $t >= -90; $t -= 6) {
            $pts[] = $cx + $s * $r * (1 - 2 * $f) * cos(deg2rad($t));
            $pts[] = $cy + $r * sin(deg2rad($t));
        }
        $this->forme($pts, 't');
    }

    private function pCratere(): void
    {
        $this->disque(32, 32, 24, 't');
        $this->disque(24, 24, 7, 'd');
        $this->disque(40, 38, 5, 'd');
        $this->disque(30, 44, 3, 'd');
        $this->disque(43, 22, 3.2, 'd');
        $this->cercle(24, 24, 7, 1, 'f');
        $this->cercle(40, 38, 5, 1, 'f');
    }

    private function pRadiant(): void
    {
        foreach ([[-0.9, 0.45, 'texte'], [0.95, 0.3, 'texte'], [0.2, 0.98, 'texte'], [-0.5, -0.85, 'texte'], [0.75, -0.65, 'texte'], [-0.98, -0.15, 'texte']] as [$x, $y]) {
            $this->t(32 + 7 * $x, 28 + 7 * $y, 32 + 28 * $x, 28 + 28 * $y, 1.4, 't');
        }
        $this->disque(32, 28, 3, 'o');
        $this->texte('radiant', 32, 63);
    }

    private function pOrbite(): void
    {
        $this->arc(32, 32, 28, 17, 0, 360, 1.1, 'd', -15);
        $this->disque(32, 32, 6, 'o');
        $u = deg2rad(30);
        $px = 28 * cos($u);
        $py = 17 * sin($u);
        $r = deg2rad(-15);
        $this->disque(32 + $px * cos($r) - $py * sin($r), 32 + $px * sin($r) + $py * cos($r), 3.6, 'a');
    }

    private function pSaturne(): void
    {
        $this->arc(32, 33, 29, 9, 180, 360, 2, 'd', -18);
        $this->disque(32, 32, 14, 't');
        $this->arc(32, 33, 29, 9, 0, 180, 2, 'a', -18);
        $this->t(20, 28, 44, 24, 1, 'd');
    }

    private function pEclipse(): void
    {
        for ($a = 0; $a < 360; $a += 30) {
            $this->t(32 + 20 * cos(deg2rad($a)), 32 + 20 * sin(deg2rad($a)), 32 + 29 * cos(deg2rad($a)), 32 + 29 * sin(deg2rad($a)), 1.2, 'd');
        }
        $this->disque(32, 32, 17, 'o');
        $this->disque(34, 31, 16, 'f');
        $this->cercle(34, 31, 16, 0.8, 'd');
    }

    private function pSoleil(): void
    {
        for ($a = 0; $a < 360; $a += 30) {
            $this->t(32 + 19 * cos(deg2rad($a)), 32 + 19 * sin(deg2rad($a)), 32 + 28 * cos(deg2rad($a)), 32 + 28 * sin(deg2rad($a)), 2, 'o');
        }
        $this->disque(32, 32, 15, 'o');
        $this->disque(27, 29, 2, 'f');
        $this->disque(37, 36, 1.5, 'f');
    }

    private function etoile(float $cx, float $cy, float $r, string $c): void
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $rr = $i % 2 ? $r * 0.42 : $r;
            $a = deg2rad(-90 + $i * 36);
            $pts[] = $cx + $rr * cos($a);
            $pts[] = $cy + $rr * sin($a);
        }
        $this->forme($pts, $c);
    }

    private function pEtoile(): void
    {
        $this->etoile(30, 32, 22, 't');
        $this->etoile(52, 12, 6, 'a');
        $this->etoile(10, 54, 4, 'd');
    }

    private function pDouble(): void
    {
        $this->disque(24, 34, 9, 'o');
        $this->disque(43, 28, 6, 'a');
        $this->arc(33, 31, 24, 12, 0, 360, 0.8, 'd', -15);
    }

    private function pGalaxie(): void
    {
        for ($b = 0; $b < 2; $b++) {
            $prec = null;
            for ($t = 0; $t <= 540; $t += 10) {
                $r = 3 + $t / 540 * 25;
                $a = deg2rad($t + $b * 180);
                $q = [32 + $r * cos($a), 32 + $r * sin($a) * 0.55];
                if ($prec) {
                    $this->t($prec[0], $prec[1], $q[0], $q[1], 2.2 - $t / 540 * 1.4, 't');
                }
                $prec = $q;
            }
        }
        $this->disque(32, 32, 5, 't');
    }

    private function pAmas(): void
    {
        mt_srand(7);
        for ($i = 0; $i < 70; $i++) {
            $r = 26 * sqrt(mt_rand(0, 1000) / 1000) * (mt_rand(0, 1) ? 1 : 0.6);
            $a = deg2rad(mt_rand(0, 359));
            $this->disque(32 + $r * cos($a), 32 + $r * sin($a), $r < 10 ? 1.3 : 0.9, $r < 14 ? 't' : 'd');
        }
        mt_srand();
    }

    private function pNebuleuse(): void
    {
        $this->arc(32, 32, 26, 16, 0, 360, 6, 'f', 20);
        $this->arc(30, 30, 20, 12, 0, 360, 5, 'd', 25);
        $this->arc(33, 33, 12, 7, 0, 360, 3, 'a', 30);
        $this->disque(32, 32, 2.2, 't');
        $this->disque(14, 14, 1.2, 't');
        $this->disque(52, 50, 1.4, 't');
    }

    private function pConstellation(): void
    {
        $pts = [[6, 22], [20, 40], [32, 26], [44, 42], [58, 20]];   // le W de Cassiopée
        for ($i = 1; $i < count($pts); $i++) {
            $this->t($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], 1.4, 'a');
        }
        foreach ($pts as [$x, $y]) {
            $this->disque($x, $y, 3, 't');
        }
    }

    private function pComete(): void
    {
        $this->forme([44, 18, 6, 50, 14, 54, 48, 24], 'd');
        $this->forme([44, 18, 10, 40, 12, 44, 46, 22], 'a');
        $this->disque(47, 19, 5, 't');
    }

    private function pLunette(): void
    {
        $this->forme([6, 40, 50, 18, 54, 25, 10, 47], 't');
        $this->forme([50, 18, 58, 14, 61, 20, 54, 25], 'a');
        $this->t(30, 36, 22, 58, 1.6, 'd');
        $this->t(30, 36, 38, 58, 1.6, 'd');
        $this->t(30, 36, 30, 58, 1.6, 'd');
    }

    private function pTelescope(): void
    {
        $this->forme([10, 30, 46, 12, 54, 26, 18, 44], 't');
        $this->forme([46, 12, 50, 10, 58, 24, 54, 26], 'a');
        $this->disque(18, 31, 2.2, 'a');
        $this->t(32, 32, 20, 60, 1.8, 'd');
        $this->t(32, 32, 44, 60, 1.8, 'd');
    }

    private function pJumelles(): void
    {
        $this->d->rectangle($this->X(8), $this->Y(18), 20 * $this->k, 32 * $this->k, 6 * $this->k, $this->c['t']);
        $this->d->rectangle($this->X(36), $this->Y(18), 20 * $this->k, 32 * $this->k, 6 * $this->k, $this->c['t']);
        $this->d->rectangle($this->X(26), $this->Y(24), 12 * $this->k, 8 * $this->k, 2 * $this->k, $this->c['d']);
        $this->disque(18, 44, 7, 'a');
        $this->disque(46, 44, 7, 'a');
    }

    private function pOeil(): void
    {
        $this->arc(32, 40, 28, 16, 180, 360, 1.8, 't');
        $this->arc(32, 24, 28, 16, 0, 180, 1.8, 't');
        $this->disque(32, 32, 9, 'a');
        $this->disque(32, 32, 4, 'f');
        $this->disque(35, 29, 1.5, 't');
    }

    private function pSatellite(): void
    {
        $this->d->rectangle($this->X(26), $this->Y(26), 12 * $this->k, 12 * $this->k, 2 * $this->k, $this->c['t']);
        $this->d->rectangle($this->X(4), $this->Y(28), 18 * $this->k, 8 * $this->k, 1 * $this->k, $this->c['a']);
        $this->d->rectangle($this->X(42), $this->Y(28), 18 * $this->k, 8 * $this->k, 1 * $this->k, $this->c['a']);
        $this->t(22, 32, 26, 32, 1.2, 'd');
        $this->t(38, 32, 42, 32, 1.2, 'd');
        $this->arc(32, 32, 30, 10, 200, 340, 0.8, 'd');
    }

    private function pFusee(): void
    {
        $this->forme([32, 4, 40, 18, 40, 44, 24, 44, 24, 18], 't');
        $this->forme([24, 34, 16, 48, 24, 44], 'a');
        $this->forme([40, 34, 48, 48, 40, 44], 'a');
        $this->disque(32, 22, 3.5, 'a');
        $this->forme([27, 46, 37, 46, 32, 60], 'o');
    }

    private function pLampadaire(): void
    {
        $this->t(18, 60, 18, 12, 2.2, 'd');
        $this->t(18, 12, 34, 12, 2.2, 'd');
        $this->forme([28, 12, 40, 12, 38, 17, 30, 17], 't');
        $this->forme([30, 17, 38, 17, 62, 60, 6, 60], 'o');
        $this->etoile(52, 8, 4, 'd');
        $this->etoile(8, 26, 3, 'd');
    }

    private function pAppareil(): void
    {
        $this->d->rectangle($this->X(6), $this->Y(20), 52 * $this->k, 34 * $this->k, 6 * $this->k, $this->c['t']);
        $this->d->rectangle($this->X(20), $this->Y(14), 16 * $this->k, 8 * $this->k, 2 * $this->k, $this->c['t']);
        $this->disque(32, 37, 12, 'f');
        $this->cercle(32, 37, 12, 1.4, 'a');
        $this->disque(32, 37, 5, 'a');
        $this->etoile(52, 6, 4, 'a');
    }
}
