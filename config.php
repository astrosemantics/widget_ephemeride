<?php
/**
 * Réglages du widget « Éphéméride astro ».
 */
return [
    // Lieu par défaut, utilisé quand le téléphone n'envoie pas sa position
    'lieu' => 'La Martyre',
    'latitude' => 48.436833,
    'longitude' => -4.150056,

    // Fuseau horaire d'affichage
    'fuseau' => 'Europe/Paris',

    // Couleurs : 'auto' (rouge dès que le Soleil passe sous -6°), 'rouge' ou 'normal'
    'theme' => 'auto',
    'rouge_soleil_sous' => -6,

    // Météo du soir (Open-Meteo, 7Timer) : nuages gênants au-delà de ce pourcentage
    'seuil_nuages' => 30,
    'modele' => 'best_match',
    'seeing_7timer' => true,

    // Mot du jour : premier jour du cycle (le même que le widget « Mot astro du jour »)
    'debut' => '2026-11-01',

    // Durée de cache HTTP côté KWGT (secondes)
    'cache' => 600,
];
