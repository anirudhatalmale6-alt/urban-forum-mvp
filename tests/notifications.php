<?php
/**
 * Critere de recette n°3 : « Les mentions et abonnements declenchent des
 * notifications CONFORMEMENT AUX PREFERENCES. »
 *
 * Le point qui compte est la deuxieme moitie de la phrase. Verifier qu'une
 * mention notifie est facile ; ce qui doit etre prouve, c'est qu'une
 * preference desactivee EMPECHE la notification. Le test coupe donc la
 * preference et refait exactement le meme geste.
 *
 *   php tests/notifications.php <pseudo_auteur> <slug_discussion>
 */

require __DIR__ . '/_amorce.php';

$auteur = $argv[1] ?? '';
$slug   = $argv[2] ?? '';
$u = en_tant_que($auteur);

$d = qun('SELECT * FROM discussions WHERE slug = ?', [$slug]);
if (!$d) { fwrite(STDERR, "discussion inconnue : $slug\n"); exit(1); }
$did = (int) $d['id'];

$cible = qun('SELECT * FROM utilisateurs WHERE identifiant = ?', ['amina_b']);
$abonne = qun('SELECT * FROM utilisateurs WHERE identifiant = ?', ['lucas_v']);
if (!$cible || !$abonne) { fwrite(STDERR, "membres de demonstration absents\n"); exit(1); }

/** Compte les notifications d'un type pour un membre. */
$compte = fn(int $uid, string $type) => (int) qval(
    'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND type = ?', [$uid, $type]);

/** Ecrit un message et declenche les notifications, comme le fait le site. */
$publier = function (string $corps) use ($did, $u): int {
    $mid = ecrire_message($did, (int) $u['id'], $corps);
    recompter_discussion($did);
    notifier_nouveau_message($mid);
    return $mid;
};

/* --- 1. Mention : la preference « app » est active par defaut --------- */
$avant = $compte((int) $cible['id'], 'mention');
$publier("Premier controle : @amina_b est mentionnee ici.");
$mention_avant = $compte((int) $cible['id'], 'mention') - $avant;

/* --- 2. Abonnement -------------------------------------------------- */
try {
    insere('abonnements', ['utilisateur_id' => (int) $abonne['id'],
        'objet_type' => 'discussion', 'objet_id' => $did, 'cree_le' => maintenant()]);
} catch (Throwable) { /* deja abonne */ }
$avant_abo = $compte((int) $abonne['id'], 'abonnement');
$publier("Deuxieme controle : ce message doit atteindre les abonnes.");
$abonnement = $compte((int) $abonne['id'], 'abonnement') - $avant_abo;

/* --- 3. La preference desactivee doit EMPECHER la notification ------- */
$pref = qun('SELECT id FROM preferences_notif WHERE utilisateur_id = ? AND type = ? AND canal = ?',
            [(int) $cible['id'], 'mention', 'app']);
if ($pref) maj('preferences_notif', (int) $pref['id'], ['actif' => 0]);
else insere('preferences_notif', ['utilisateur_id' => (int) $cible['id'],
    'type' => 'mention', 'canal' => 'app', 'actif' => 0]);

$avant2 = $compte((int) $cible['id'], 'mention');
$publier("Troisieme controle : @amina_b est mentionnee, mais elle a coupe ce canal.");
$mention_apres = $compte((int) $cible['id'], 'mention') - $avant2;

// On remet la preference comme on l'a trouvee : un test qui laisse la base
// dans un autre etat fausse le suivant.
if ($pref) maj('preferences_notif', (int) $pref['id'], ['actif' => 1]);

/* --- 4. Un membre bloque ne fait pas sonner la cloche ---------------- */
$bloqueur = qun('SELECT * FROM utilisateurs WHERE identifiant = ?', ['sara_m']);
try {
    insere('blocages', ['utilisateur_id' => (int) $bloqueur['id'],
        'bloque_id' => (int) $u['id'], 'cree_le' => maintenant()]);
} catch (Throwable) {}
$avant3 = $compte((int) $bloqueur['id'], 'mention');
$publier("Quatrieme controle : @sara_m m'a bloque, elle ne doit rien recevoir.");
$bloque = $compte((int) $bloqueur['id'], 'mention') - $avant3;
q('DELETE FROM blocages WHERE utilisateur_id = ? AND bloque_id = ?',
  [(int) $bloqueur['id'], (int) $u['id']]);

/* --- 5. Aucun e-mail ne doit etre marque envoye ---------------------- */
// cfg('mail_expediteur') est vide : la colonne email_envoye doit rester a 0
// partout. « J'ai appele mail() » n'est pas « le message est parti ».
$emails = (int) qval('SELECT COUNT(*) FROM notifications WHERE email_envoye = 1');

sortir([
    'mention_avant'   => $mention_avant,
    'abonnement'      => $abonnement,
    'mention_apres'   => $mention_apres,
    'bloque'          => $bloque,
    'emails_envoyes'  => $emails,
    'expediteur_configure' => cfg('mail_expediteur') !== '',
]);
