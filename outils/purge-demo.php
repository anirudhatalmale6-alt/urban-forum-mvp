<?php
/**
 * Efface TOUT le contenu de demonstration.
 *
 *   php outils/purge-demo.php --compter     ce qui serait supprime
 *   php outils/purge-demo.php --supprimer   le fait
 *
 * Deux modes, et le premier est le defaut. Une commande destructive dont le
 * comportement par defaut est de detruire finit toujours par etre lancee par
 * accident.
 *
 * Ce qui est supprime : les lignes portant demo = 1 (membres, discussions,
 * messages, medias) et tout ce qui en depend — reactions, abonnements,
 * signets, mentions, notifications, signalements, revisions, index.
 * Ce qui est CONSERVE : la geographie, la taxonomie, les forums, les roles.
 * Ce sont des structures, pas du contenu de remplissage.
 *
 * Apres la purge, passe 'mode_demo' a false dans src/config.local.php pour
 * retirer le bandeau.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/messages.php';
require $racine . '/src/recherche.php';
require $racine . '/src/moderation.php';

$supprimer = in_array('--supprimer', $argv, true);

$discussions = array_column(qtous('SELECT id FROM discussions WHERE demo = 1'), 'id');
$messages    = array_column(qtous('SELECT id FROM messages WHERE demo = 1'), 'id');
$membres     = array_column(qtous('SELECT id FROM utilisateurs WHERE demo = 1'), 'id');
$medias      = qtous('SELECT id, nom_fichier FROM medias WHERE demo = 1');

// Les messages ecrits par un membre de demonstration dans une discussion
// reelle comptent aussi : sinon la purge laisse des orphelins qui affichent
// « — » a la place d'un auteur.
if ($membres) {
    $ph = implode(',', array_fill(0, count($membres), '?'));
    foreach (qtous("SELECT id FROM messages WHERE auteur_id IN ($ph)", $membres) as $r) {
        $messages[] = (int) $r['id'];
    }
    foreach (qtous("SELECT id FROM discussions WHERE auteur_id IN ($ph)", $membres) as $r) {
        $discussions[] = (int) $r['id'];
    }
}
$messages = array_values(array_unique(array_map('intval', $messages)));
$discussions = array_values(array_unique(array_map('intval', $discussions)));

echo "Contenu de demonstration :\n";
printf("  membres      %d\n", count($membres));
printf("  discussions  %d\n", count($discussions));
printf("  messages     %d\n", count($messages));
printf("  medias       %d\n", count($medias));

if (!$supprimer) {
    echo "\nRien n'a ete supprime. Relance avec --supprimer pour le faire.\n";
    exit(0);
}

$dans = function (array $ids): array {
    return [implode(',', array_fill(0, count($ids), '?')), $ids];
};

bd()->beginTransaction();

if ($messages) {
    [$ph, $p] = $dans($messages);
    q("DELETE FROM reactions WHERE message_id IN ($ph)", $p);
    q("DELETE FROM mentions WHERE message_id IN ($ph)", $p);
    q("DELETE FROM revisions_message WHERE message_id IN ($ph)", $p);
    q("DELETE FROM notifications WHERE message_id IN ($ph)", $p);
    q("DELETE FROM signalements WHERE objet_type = 'message' AND objet_id IN ($ph)", $p);
    q("DELETE FROM actions_moderation WHERE objet_type = 'message' AND objet_id IN ($ph)", $p);
    q("DELETE FROM index_recherche WHERE objet_type = 'message' AND objet_id IN ($ph)", $p);
    q("DELETE FROM messages WHERE id IN ($ph)", $p);
}
if ($discussions) {
    [$ph, $p] = $dans($discussions);
    q("DELETE FROM messages WHERE discussion_id IN ($ph)", $p);
    q("DELETE FROM signets WHERE discussion_id IN ($ph)", $p);
    q("DELETE FROM vues_discussion WHERE discussion_id IN ($ph)", $p);
    q("DELETE FROM abonnements WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $p);
    q("DELETE FROM notifications WHERE discussion_id IN ($ph)", $p);
    q("DELETE FROM signalements WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $p);
    q("DELETE FROM actions_moderation WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $p);
    q("DELETE FROM index_recherche WHERE objet_type = 'discussion' AND objet_id IN ($ph)", $p);
    q("DELETE FROM discussions WHERE id IN ($ph)", $p);
}
foreach ($medias as $m) {
    $chemin = cfg('chemin_medias') . '/' . $m['nom_fichier'];
    if (is_file($chemin)) @unlink($chemin);
}
if ($medias) {
    [$ph, $p] = $dans(array_map('intval', array_column($medias, 'id')));
    q("DELETE FROM medias WHERE id IN ($ph)", $p);
}
if ($membres) {
    [$ph, $p] = $dans($membres);
    q("DELETE FROM sessions WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM preferences_notif WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM abonnements WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM signets WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM reactions WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM blocages WHERE utilisateur_id IN ($ph) OR bloque_id IN ($ph)",
      array_merge($p, $p));
    q("DELETE FROM notifications WHERE utilisateur_id IN ($ph) OR acteur_id IN ($ph)",
      array_merge($p, $p));
    q("DELETE FROM signalements WHERE signaleur_id IN ($ph)", $p);
    q("DELETE FROM badges_utilisateur WHERE utilisateur_id IN ($ph)", $p);
    q("DELETE FROM utilisateurs WHERE id IN ($ph)", $p);
}

bd()->commit();

foreach (qtous('SELECT id FROM discussions') as $d) recompter_discussion((int) $d['id']);
foreach (qtous('SELECT id FROM forums') as $f) recompter_forum((int) $f['id']);
$n = reindexer_tout();

/* --- Verification : il ne doit plus rien rester ---------------------- */
$restant = (int) qval('SELECT COUNT(*) FROM discussions WHERE demo = 1')
         + (int) qval('SELECT COUNT(*) FROM messages WHERE demo = 1')
         + (int) qval('SELECT COUNT(*) FROM utilisateurs WHERE demo = 1')
         + (int) qval('SELECT COUNT(*) FROM medias WHERE demo = 1');

echo "\nPurge effectuee. Lignes marquees demo restantes : $restant\n";
echo 'Index reconstruit : ' . array_sum($n) . " objets.\n";
if ($restant !== 0) {
    fwrite(STDERR, "ATTENTION : il reste du contenu marque demo.\n");
    exit(1);
}
echo "Passe maintenant 'mode_demo' a false dans src/config.local.php.\n";
