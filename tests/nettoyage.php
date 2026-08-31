<?php
/**
 * Efface ce que la suite de controle a cree.
 *
 * Sans ce nettoyage, chaque execution laisse un membre « testeur_… », une
 * discussion et une dizaine de messages dans la base. Au bout de quelques
 * jours, l'accueil du site de demonstration ne montre plus que des
 * discussions de test, et les captures d'ecran envoyees au client aussi.
 * Le contenu de la suite n'est PAS marque demo = 1 — il est cree par HTTP,
 * comme celui d'un vrai membre — donc purge-demo.php ne le voit pas.
 *
 *   php tests/nettoyage.php            compte
 *   php tests/nettoyage.php --supprimer
 */
require __DIR__ . '/_amorce.php';

$supprimer = in_array('--supprimer', $argv, true);
$membres = array_column(
    qtous("SELECT id FROM utilisateurs WHERE identifiant LIKE 'testeur\_%' ESCAPE '\'
           OR identifiant LIKE 'court\_%' ESCAPE '\' OR identifiant LIKE 'robot\_%' ESCAPE '\'"),
    'id');
$discussions = array_column(
    qtous("SELECT id FROM discussions WHERE titre LIKE 'Controle automatique%'"), 'id');

if (!$supprimer) {
    sortir(['membres' => count($membres), 'discussions' => count($discussions)]);
}

$dans = fn(array $i) => implode(',', array_fill(0, count($i), '?'));

if ($discussions) {
    $ph = $dans($discussions);
    $mids = array_column(qtous("SELECT id FROM messages WHERE discussion_id IN ($ph)", $discussions), 'id');
    if ($mids) {
        $pm = $dans($mids);
        foreach (['reactions', 'mentions', 'revisions_message'] as $tb) {
            q("DELETE FROM $tb WHERE message_id IN ($pm)", $mids);
        }
        q("DELETE FROM notifications WHERE message_id IN ($pm)", $mids);
        q("DELETE FROM signalements WHERE objet_type = 'message' AND objet_id IN ($pm)", $mids);
        q("DELETE FROM actions_moderation WHERE objet_type = 'message' AND objet_id IN ($pm)", $mids);
        q("DELETE FROM index_recherche WHERE objet_type = 'message' AND objet_id IN ($pm)", $mids);
        q("DELETE FROM messages WHERE id IN ($pm)", $mids);
    }
    q("DELETE FROM abonnements WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $discussions);
    q("DELETE FROM signets WHERE discussion_id IN ($ph)", $discussions);
    q("DELETE FROM vues_discussion WHERE discussion_id IN ($ph)", $discussions);
    q("DELETE FROM notifications WHERE discussion_id IN ($ph)", $discussions);
    q("DELETE FROM index_recherche WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $discussions);
    q("DELETE FROM discussions WHERE id IN ($ph)", $discussions);
}
if ($membres) {
    $ph = $dans($membres);
    foreach (qtous("SELECT id, nom_fichier FROM medias WHERE utilisateur_id IN ($ph)", $membres) as $md) {
        $f = cfg('chemin_medias') . '/' . $md['nom_fichier'];
        if (is_file($f)) @unlink($f);
    }
    q("DELETE FROM medias WHERE utilisateur_id IN ($ph)", $membres);
    foreach (['sessions', 'preferences_notif', 'abonnements', 'signets', 'reactions'] as $tb) {
        q("DELETE FROM $tb WHERE utilisateur_id IN ($ph)", $membres);
    }
    q("DELETE FROM blocages WHERE utilisateur_id IN ($ph) OR bloque_id IN ($ph)",
      array_merge($membres, $membres));
    q("DELETE FROM notifications WHERE utilisateur_id IN ($ph) OR acteur_id IN ($ph)",
      array_merge($membres, $membres));
    q("DELETE FROM signalements WHERE signaleur_id IN ($ph)", $membres);
    // Les messages ecrits par un compte de test dans une discussion de
    // demonstration : sinon ils restent sans auteur.
    $mids = array_column(qtous("SELECT id FROM messages WHERE auteur_id IN ($ph)", $membres), 'id');
    if ($mids) {
        $pm = $dans($mids);
        q("DELETE FROM index_recherche WHERE objet_type = 'message' AND objet_id IN ($pm)", $mids);
        q("DELETE FROM messages WHERE id IN ($pm)", $mids);
    }
    q("DELETE FROM utilisateurs WHERE id IN ($ph)", $membres);
}
q('DELETE FROM recherches_vides');
q('DELETE FROM limites_taux');

foreach (qtous('SELECT id FROM discussions') as $d) recompter_discussion((int) $d['id']);
foreach (qtous('SELECT id FROM forums') as $f) recompter_forum((int) $f['id']);
reindexer_tout();

$restant = (int) qval("SELECT COUNT(*) FROM utilisateurs WHERE identifiant LIKE 'testeur\_%' ESCAPE '\'")
         + (int) qval("SELECT COUNT(*) FROM discussions WHERE titre LIKE 'Controle automatique%'");
sortir(['membres' => count($membres), 'discussions' => count($discussions), 'restant' => $restant]);
