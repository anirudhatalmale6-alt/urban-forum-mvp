<?php
/**
 * Compare les CLES des trois dictionnaires.
 * Une cle presente en francais et absente en arabe ne provoque aucune
 * erreur : t() retombe sur le francais et la page arabe affiche du
 * francais au milieu. C'est precisement ce qu'on ne veut pas decouvrir
 * par un utilisateur.
 */
require __DIR__ . '/_amorce.php';
$fr = array_keys(require dirname(__DIR__) . '/src/lang/fr.php');
$en = array_keys(require dirname(__DIR__) . '/src/lang/en.php');
$ar = array_keys(require dirname(__DIR__) . '/src/lang/ar.php');
$manquantes = [];
foreach (['en' => $en, 'ar' => $ar] as $code => $cles) {
    foreach (array_diff($fr, $cles) as $c) $manquantes[] = "$code:$c";
}
$en_trop = [];
foreach (['en' => $en, 'ar' => $ar] as $code => $cles) {
    foreach (array_diff($cles, $fr) as $c) $en_trop[] = "$code:$c";
}
sortir([
    'n_fr' => count($fr), 'n_en' => count($en), 'n_ar' => count($ar),
    'manquantes' => array_values($manquantes), 'en_trop' => array_values($en_trop),
]);
