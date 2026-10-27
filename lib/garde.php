<?php
/**
 * À inclure en tout premier : aucun message PHP ne doit s'afficher au milieu
 * d'une image ou d'un JSON (sinon le widget reste vide).
 * Pour diagnostiquer un problème, consulter le journal d’erreurs PHP de l’hébergement.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
