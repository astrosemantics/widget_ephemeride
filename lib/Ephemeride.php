<?php
/**
 * Éphéméride du jour en un lieu : Soleil (lever, coucher, durée du jour), crépuscules,
 * Lune (lever, coucher, phase), et phénomènes du jour (phases, saisons, étoiles filantes,
 * planètes visibles, rapprochements Lune-planète).
 */

declare(strict_types=1);

require_once __DIR__ . '/Astro.php';
require_once __DIR__ . '/Planetes.php';

final class Ephemeride
{
    private const PLUIES = [
        ['01-03', 'Quadrantides', 'après minuit'],
        ['04-22', 'Lyrides', 'après 22 h'],
        ['05-06', 'Êta Aquarides', 'à l’aube'],
        ['08-12', 'Perséides', 'après 23 h'],
        ['10-21', 'Orionides', 'après minuit'],
        ['11-17', 'Léonides', 'en fin de nuit'],
        ['12-14', 'Géminides', 'dès 22 h'],
    ];
    /** Ordre d'éclat habituel, pour choisir les planètes à citer. */
    private const PLANETES = ['venus', 'jupiter', 'mars', 'saturne', 'mercure'];

    public Astro $astro;
    private DateTimeZone $tz;
    private float $lat;
    private float $lon;

    public function __construct(float $lat, float $lon, DateTimeZone $tz)
    {
        [$this->lat, $this->lon, $this->tz] = [$lat, $lon, $tz];
        $this->astro = new Astro($lat, $lon);
    }

    public function hm(int $ts): string
    {
        $t = (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz);
        return $t->format('G') . ' h ' . $t->format('i');
    }

    private function jourDe(int $ts): string
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz)->format('Y-m-d');
    }

    /** Premier passage de f(t) par 0 entre $de et $a (montant ou descendant). */
    private static function passage(callable $f, int $de, int $a, bool $montant, int $pas = 600): ?int
    {
        $prec = $f($de);
        for ($t = $de + $pas; $t <= $a; $t += $pas) {
            $v = $f($t);
            if ($montant ? ($prec < 0 && $v >= 0) : ($prec > 0 && $v <= 0)) {
                [$g, $d] = [$t - $pas, $t];
                for ($i = 0; $i < 12; $i++) {
                    $m = intdiv($g + $d, 2);
                    $vm = $f($m);
                    if ($montant ? $vm < 0 : $vm > 0) {
                        $g = $m;
                    } else {
                        $d = $m;
                    }
                }
                return $d;
            }
            $prec = $v;
        }
        return null;
    }

    private function soleil(float $h): callable
    {
        return fn(int $t) => $this->astro->hauteurSoleil($t) - $h;
    }

    private function lune(): callable
    {
        // Hauteur topocentrique du centre ; lever / coucher du bord supérieur, réfraction comprise
        return fn(int $t) => $this->astro->lunePosition($t)['hauteur'] + 0.83;
    }

    /** Toutes les données du jour qui contient $ts. */
    public function calculer(int $ts): array
    {
        $jour = (new DateTimeImmutable('@' . $ts))->setTimezone($this->tz)->setTime(0, 0);
        $t0 = $jour->getTimestamp();
        $t1 = $jour->modify('+1 day')->getTimestamp();
        $hier = $jour->modify('-1 day')->getTimestamp();

        $lever = self::passage($this->soleil(-0.833), $t0, $t1, true);
        $coucher = self::passage($this->soleil(-0.833), $t0, $t1, false);
        $leverH = self::passage($this->soleil(-0.833), $hier, $t0, true);
        $coucherH = self::passage($this->soleil(-0.833), $hier, $t0, false);
        $duree = ($lever && $coucher) ? $coucher - $lever : null;
        $dureeH = ($leverH && $coucherH) ? $coucherH - $leverH : null;

        $midi = $t0 + 12 * 3600;
        $nuitTombee = self::passage($this->soleil(-6), $midi, $midi + 12 * 3600, false);
        $noireDebut = self::passage($this->soleil(-18), $midi, $midi + 14 * 3600, false);
        $noireFin = $noireDebut ? self::passage($this->soleil(-18), $noireDebut, $noireDebut + 14 * 3600, true) : null;

        // Lune : le lever du jour, puis le coucher qui suit (éventuellement le lendemain)
        $lune = $this->lune();
        $lLever = self::passage($lune, $t0, $t1, true);
        $lCoucher = $lLever
            ? self::passage($lune, $lLever, $lLever + 30 * 3600, false)
            : self::passage($lune, $t0, $t1, false);
        $soir = $nuitTombee ? $nuitTombee + 3600 : $t0 + 21 * 3600;
        $lp = $this->astro->lunePosition($soir);

        return [
            'jour' => $jour,
            'soleil' => ['lever' => $lever, 'coucher' => $coucher, 'duree' => $duree,
                         'ecart' => ($duree !== null && $dureeH !== null) ? (int) round(($duree - $dureeH) / 60) : null],
            'nuitTombee' => $nuitTombee,
            'nuitNoire' => [$noireDebut, $noireFin],
            'lune' => ['lever' => $lLever, 'coucher' => $lCoucher,
                       'coucherDemain' => $lCoucher !== null && $this->jourDe($lCoucher) !== $jour->format('Y-m-d'),
                       'fraction' => $lp['fraction'], 'croissante' => $lp['croissante'],
                       'phase' => self::nomPhase($lp['fraction'], $lp['croissante'])],
            'phenomenes' => $this->phenomenes($jour, $t0, $t1, $soir, $nuitTombee),
        ];
    }

    public static function nomPhase(float $f, bool $croissante): string
    {
        if ($f < 0.03) {
            return 'nouvelle Lune';
        }
        if ($f > 0.97) {
            return 'pleine Lune';
        }
        if ($f < 0.4) {
            return $croissante ? 'premier croissant' : 'dernier croissant';
        }
        if ($f <= 0.6) {
            return $croissante ? 'premier quartier' : 'dernier quartier';
        }
        return $croissante ? 'gibbeuse croissante' : 'gibbeuse décroissante';
    }

    private static function direction(float $az): string
    {
        $noms = ['au nord', 'au nord-est', 'à l’est', 'au sud-est', 'au sud', 'au sud-ouest', 'à l’ouest', 'au nord-ouest'];
        return $noms[((int) round($az / 45)) % 8];
    }

    private function planete(string $p, int $ts): array
    {
        [$ra, $dec] = Planetes::equatoriales($p, $ts);
        [$ra, $dec] = Astro::precession($ra, $dec, $ts);
        return $this->astro->horizontales($ra, $dec, $ts) + ['ra' => $ra, 'dec' => $dec];
    }

    /** Phénomènes du jour, du plus marquant au plus ordinaire (textes courts, une ligne chacun). */
    private function phenomenes(DateTimeImmutable $jour, int $t0, int $t1, int $soir, ?int $nuitTombee): array
    {
        $p = [];
        $evenement = false;
        $md = $jour->format('m-d');

        // Étoiles filantes
        foreach (self::PLUIES as [$pic, $nom, $conseil]) {
            $veille = (new DateTimeImmutable($jour->format('Y') . '-' . $pic, $this->tz))->modify('-1 day')->format('m-d');
            if ($md === $pic) {
                $p[] = $nom . ' : maximum cette nuit, ' . $conseil;
                $evenement = true;
            } elseif ($md === $veille) {
                $p[] = $nom . ' : maximum demain soir';
            }
        }

        // Équinoxes et solstices
        $a = Astro::longitudeSoleilA($t0);
        $b = Astro::longitudeSoleilA($t1);
        foreach ([0 => 'Équinoxe de printemps', 90 => 'Solstice d’été', 180 => 'Équinoxe d’automne', 270 => 'Solstice d’hiver'] as $l => $nom) {
            if (fmod($a - $l + 540, 360) - 180 < 0 && fmod($b - $l + 540, 360) - 180 >= 0) {
                $t = self::passage(static fn($x) => fmod(Astro::longitudeSoleilA($x) - $l + 540, 360) - 180, $t0, $t1, true, 1800);
                $p[] = $nom . ($t ? ' à ' . $this->hm($t) : '');
                $evenement = true;
            }
        }

        // Phases principales de la Lune
        $prec = $this->astro->lunePosition($t0);
        for ($t = $t0 + 900; $t <= $t1; $t += 900) {
            $l = $this->astro->lunePosition($t);
            $nom = null;
            if (!$prec['croissante'] && $l['croissante'] && $l['fraction'] < 0.5) {
                $nom = 'Nouvelle Lune à ' . $this->hm($t) . ' : nuits noires';
            } elseif ($prec['croissante'] && !$l['croissante'] && $l['fraction'] > 0.5) {
                $km = Astro::luneDistance($t);
                $nom = ($km < 362000 ? 'Super Lune' : 'Pleine Lune') . ' (' . $this->hm($t) . '), visible toute la nuit';
            } elseif ($l['croissante'] && $prec['fraction'] < 0.5 && $l['fraction'] >= 0.5) {
                $nom = 'Premier quartier à ' . $this->hm($t);
            } elseif (!$l['croissante'] && $prec['fraction'] > 0.5 && $l['fraction'] <= 0.5) {
                $nom = 'Dernier quartier à ' . $this->hm($t);
            }
            if ($nom !== null) {
                $p[] = $nom;
                $evenement = true;
                break;
            }
            $prec = $l;
        }

        // Rapprochement Lune-planète ce soir (moins de 5°)
        $lq = Astro::luneEquatoriales($soir);
        $ref = $soir;
        foreach (self::PLANETES as $pl) {
            $x = $this->planete($pl, $ref);
            $sep = rad2deg(acos(max(-1, min(1, sin(deg2rad($lq[1])) * sin(deg2rad($x['dec'])) + cos(deg2rad($lq[1])) * cos(deg2rad($x['dec'])) * cos(deg2rad($lq[0] - $x['ra']))))));
            if ($sep < 5 && $x[0] > 5) {
                $p[] = 'La Lune passe près de ' . Planetes::NOMS[$pl] . ' ce soir (' . (int) round($sep) . '°)';
                $evenement = true;
            }
        }

        // Planètes bien visibles en soirée, sinon avant l'aube
        foreach (self::PLANETES as $pl) {
            if (count($p) >= 3) {
                break;
            }
            [$alt, $az] = $this->planete($pl, $ref);
            if ($pl === 'mercure') {
                if ($nuitTombee && ($m = $this->planete($pl, $nuitTombee)) && $m[0] > 8) {
                    $p[] = 'Mercure, bas ' . self::direction($m[1]) . ' au crépuscule';
                }
                continue;
            }
            if ($alt > 10) {
                $p[] = Planetes::NOMS[$pl] . ($pl === 'venus' ? ' brille ' : ' est visible ') . self::direction($az) . ' en soirée';
            }
        }
        if (count($p) < 2) {
            $aube = self::passage($this->soleil(-6), $t1, $t1 + 12 * 3600, true);
            if ($aube) {
                foreach (self::PLANETES as $pl) {
                    if ($pl === 'mercure' || count($p) >= 2) {
                        continue;
                    }
                    [$alt, $az] = $this->planete($pl, $aube - 1800);
                    if ($alt > 10) {
                        $p[] = Planetes::NOMS[$pl] . ' brille ' . self::direction($az) . ' avant l’aube';
                    }
                }
            }
        }
        return ['lignes' => array_slice($p, 0, 2), 'evenement' => $evenement];
    }
}
