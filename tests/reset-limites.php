<?php
/**
 * Remet a zero les compteurs de limitation de debit.
 *
 * La suite s'inscrit, se connecte et publie a chaque execution. Sans cette
 * remise a zero, la troisieme execution de la journee bute sur la limite
 * « 3 inscriptions par heure et par adresse » — et TOUS les controles
 * suivants echouent pour une raison qui n'a rien a voir avec ce qu'ils
 * mesurent. La limite elle-meme est verifiee par un controle dedie, qui
 * l'epuise volontairement.
 */
require __DIR__ . '/_amorce.php';
$n = (int) qval('SELECT COUNT(*) FROM limites_taux');
q('DELETE FROM limites_taux');
sortir(['effacees' => $n]);
