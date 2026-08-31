<?php
/**
 * Notifications (section 4.6).
 *
 * Regle qui gouverne tout ce fichier : on ne pretend JAMAIS qu'un e-mail est
 * parti. Tant que cfg('mail_expediteur') est vide, aucun envoi n'a lieu, la
 * colonne email_envoye reste a 0 et le centre de notifications affiche la
 * phrase qui le dit. Un accuse d'envoi sans destinataire est un mensonge
 * poli, et c'est celui qu'on decouvre trois mois plus tard.
 */

declare(strict_types=1);

function pref_notif(int $uid, string $type, string $canal): bool
{
    $r = qval('SELECT actif FROM preferences_notif WHERE utilisateur_id = ? AND type = ? AND canal = ?',
              [$uid, $type, $canal]);
    if ($r === null) return $canal === 'app';   // par defaut : in-app oui, e-mail non
    return (int) $r === 1;
}

function notifier(int $destinataire, string $type, array $donnees = []): ?int
{
    if ($destinataire <= 0) return null;

    // On ne se notifie pas soi-meme.
    $acteur = (int) ($donnees['acteur_id'] ?? 0);
    if ($acteur === $destinataire) return null;

    // Un membre bloque ne peut pas faire sonner la cloche de celui qui l'a
    // bloque : sans cela, le blocage n'empeche que la lecture.
    if ($acteur && qval('SELECT id FROM blocages WHERE utilisateur_id = ? AND bloque_id = ?',
                        [$destinataire, $acteur]) !== null) {
        return null;
    }
    if (!pref_notif($destinataire, $type, 'app')) return null;

    $id = insere('notifications', [
        'utilisateur_id' => $destinataire,
        'type'           => $type,
        'acteur_id'      => $acteur ?: null,
        'discussion_id'  => $donnees['discussion_id'] ?? null,
        'message_id'     => $donnees['message_id'] ?? null,
        'lien'           => $donnees['lien'] ?? null,
        'lue'            => 0,
        'cree_le'        => maintenant(),
        'email_envoye'   => 0,
    ]);

    if (pref_notif($destinataire, $type, 'email')) {
        envoyer_email_notification($id, $destinataire, $type, $donnees);
    }
    return $id;
}

function envoyer_email_notification(int $notif_id, int $uid, string $type, array $d): void
{
    $exp = (string) cfg('mail_expediteur');
    if ($exp === '') {
        // Rien a envoyer depuis. On le note dans le journal pour que ce ne
        // soit pas un silence, et on ne touche pas email_envoye.
        journal('info', 'e-mail de notification non envoye : aucun expediteur configure',
                ['notification' => $notif_id]);
        return;
    }
    $u = qun('SELECT email, identifiant FROM utilisateurs WHERE id = ?', [$uid]);
    if (!$u || !$u['email']) return;

    $sujet = '[' . cfg('nom_site') . '] ' . t('notif_' . $type, ['n' => '']);
    $corps = trim(($d['lien'] ?? '') . "\n");
    $entetes = 'From: ' . (cfg('mail_nom') ?: cfg('nom_site')) . ' <' . $exp . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    $ok = @mail($u['email'], $sujet, $corps, $entetes);
    // On n'ecrit email_envoye = 1 que si la fonction a rendu true. « J'ai
    // appele mail() » n'est pas « le message est parti ».
    if ($ok) maj('notifications', $notif_id, ['email_envoye' => 1]);
    else journal('alerte', 'mail() a echoue', ['notification' => $notif_id]);
}

/** Les notifications declenchees par un nouveau message. */
function notifier_nouveau_message(int $message_id): int
{
    $m = qun('SELECT * FROM messages WHERE id = ?', [$message_id]);
    if (!$m) return 0;
    $d = qun('SELECT * FROM discussions WHERE id = ?', [(int) $m['discussion_id']]);
    if (!$d) return 0;

    $lien = '/d/' . $d['slug'] . '#m' . $message_id;
    $auteur = (int) $m['auteur_id'];
    $envoyees = 0;
    $deja = [$auteur => true];

    // 1. Mentions — prioritaires : si quelqu'un est a la fois mentionne et
    //    abonne, il recoit UNE notification, celle de la mention.
    foreach (mentions_du_corps((string) $m['corps']) as $pseudo) {
        $u = qun('SELECT id FROM utilisateurs WHERE identifiant = ?', [$pseudo]);
        if (!$u || isset($deja[(int) $u['id']])) continue;
        try {
            insere('mentions', ['message_id' => $message_id, 'utilisateur_id' => (int) $u['id']]);
        } catch (Throwable) {}
        if (notifier((int) $u['id'], 'mention', [
                'acteur_id' => $auteur, 'discussion_id' => (int) $d['id'],
                'message_id' => $message_id, 'lien' => $lien])) {
            $envoyees++;
        }
        $deja[(int) $u['id']] = true;
    }

    // 2. Abonnes a la discussion, puis abonnes au forum.
    $abos = qtous('SELECT utilisateur_id FROM abonnements
                   WHERE (objet_type = ? AND objet_id = ?) OR (objet_type = ? AND objet_id = ?)',
                  ['discussion', (int) $d['id'], 'forum', (int) $d['forum_id']]);
    foreach ($abos as $a) {
        $uid = (int) $a['utilisateur_id'];
        if (isset($deja[$uid])) continue;
        if (notifier($uid, 'abonnement', [
                'acteur_id' => $auteur, 'discussion_id' => (int) $d['id'],
                'message_id' => $message_id, 'lien' => $lien])) {
            $envoyees++;
        }
        $deja[$uid] = true;
    }

    // 3. L'auteur de la discussion, meme sans abonnement explicite.
    $prop = (int) $d['auteur_id'];
    if (!isset($deja[$prop])) {
        if (notifier($prop, 'reponse', [
                'acteur_id' => $auteur, 'discussion_id' => (int) $d['id'],
                'message_id' => $message_id, 'lien' => $lien])) {
            $envoyees++;
        }
    }
    return $envoyees;
}

function notifications_non_lues(int $uid): int
{
    return (int) qval('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lue = 0', [$uid]);
}

function texte_notification(array $n): string
{
    $acteur = $n['acteur_id']
        ? (string) qval('SELECT identifiant FROM utilisateurs WHERE id = ?', [(int) $n['acteur_id']])
        : '';
    if ($n['type'] === 'abonnement') {
        $titre = $n['discussion_id']
            ? (string) qval('SELECT titre FROM discussions WHERE id = ?', [(int) $n['discussion_id']])
            : '';
        return t('notif_abonnement', ['n' => $titre]);
    }
    return t('notif_' . $n['type'], ['n' => $acteur]);
}
