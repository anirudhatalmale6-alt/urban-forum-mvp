<?php
/** Inscription, connexion, notifications, preferences, signets. */

declare(strict_types=1);

function page_inscription(array $erreurs = [], array $vals = []): void
{
    if (connecte()) redirige('/');
    meta(['titre' => t('nav_inscription'), 'noindex' => true]);
    rendre('inscription', compact('erreurs', 'vals'));
}

function post_inscription(): void
{
    // Piege a robots : un champ que personne ne voit et qu'un formulaire
    // automatique remplit. On repond 200 et une page normale — un 403 ici
    // apprend au robot quel champ eviter au prochain passage.
    if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
        journal('info', 'inscription rejetee par le piege', ['ip' => ip_client()]);
        redirige('/');
    }
    $r = inscrire(
        (string) ($_POST['identifiant'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['mot_de_passe'] ?? ''),
        (string) ($_POST['mot_de_passe2'] ?? ''),
        langue()
    );
    if (!empty($r['erreurs'])) {
        http_response_code(422);
        page_inscription($r['erreurs'], ['identifiant' => $_POST['identifiant'] ?? '',
                                         'email' => $_POST['email'] ?? '']);
        return;
    }
    ouvrir_session((int) $r['id']);
    redirige('/');
}

function page_connexion(array $erreurs = [], array $vals = []): void
{
    if (connecte()) redirige('/');
    meta(['titre' => t('nav_connexion'), 'noindex' => true]);
    rendre('connexion', compact('erreurs', 'vals'));
}

function post_connexion(): void
{
    $r = connecter((string) ($_POST['identifiant'] ?? ''), (string) ($_POST['mot_de_passe'] ?? ''));
    if (!empty($r['erreurs'])) {
        http_response_code(422);
        page_connexion($r['erreurs'], ['identifiant' => $_POST['identifiant'] ?? '']);
        return;
    }
    $suite = (string) ($_POST['suite'] ?? '/');
    // On ne redirige que vers un chemin interne : une valeur controlee par
    // le formulaire enverrait l'utilisateur connecte sur un site tiers.
    if (!str_starts_with($suite, '/') || str_starts_with($suite, '//')) $suite = '/';
    redirige($suite);
}

function post_deconnexion(): void
{
    fermer_session();
    redirige('/');
}

function page_notifications(): void
{
    $u = utilisateur();
    $notifs = qtous('SELECT * FROM notifications WHERE utilisateur_id = ?
                     ORDER BY cree_le DESC LIMIT 100', [(int) $u['id']]);
    meta(['titre' => t('notif_titre'), 'noindex' => true]);
    rendre('notifications', ['notifs' => $notifs]);
}

function post_tout_lu(): void
{
    $u = utilisateur();
    q('UPDATE notifications SET lue = 1 WHERE utilisateur_id = ?', [(int) $u['id']]);
    redirige('/notifications');
}

function page_parametres(array $message = []): void
{
    $u = utilisateur();
    $prefs = [];
    foreach (qtous('SELECT * FROM preferences_notif WHERE utilisateur_id = ?', [(int) $u['id']]) as $p) {
        $prefs[$p['type']][$p['canal']] = (int) $p['actif'];
    }
    meta(['titre' => t('nav_parametres'), 'noindex' => true]);
    rendre('parametres', compact('u', 'prefs', 'message'));
}

function post_parametres(): void
{
    $u = utilisateur();
    $langue = (string) ($_POST['langue'] ?? '');
    maj('utilisateurs', (int) $u['id'], [
        'nom_public'    => mb_substr(trim((string) ($_POST['nom_public'] ?? '')), 0, 120) ?: $u['identifiant'],
        'bio'           => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 2000),
        'localisation'  => mb_substr(trim((string) ($_POST['localisation'] ?? '')), 0, 120),
        'lien'          => filter_var(trim((string) ($_POST['lien'] ?? '')), FILTER_VALIDATE_URL) ?: '',
        'langue'        => in_array($langue, cfg('langues'), true) ? $langue : $u['langue'],
        'profil_public' => isset($_POST['profil_public']) ? 1 : 0,
    ]);

    foreach (['reponse', 'mention', 'abonnement', 'moderation'] as $type) {
        foreach (['app', 'email'] as $canal) {
            $actif = isset($_POST['notif'][$type][$canal]) ? 1 : 0;
            $ex = qun('SELECT id FROM preferences_notif WHERE utilisateur_id = ? AND type = ? AND canal = ?',
                      [(int) $u['id'], $type, $canal]);
            if ($ex) maj('preferences_notif', (int) $ex['id'], ['actif' => $actif]);
            else insere('preferences_notif', ['utilisateur_id' => (int) $u['id'], 'type' => $type,
                                              'canal' => $canal, 'actif' => $actif]);
        }
    }
    audit('parametres');
    page_parametres(['type' => 'ok', 'texte' => t('enregistrer')]);
}

function page_signets(): void
{
    $u = utilisateur();
    $lignes = qtous('SELECT d.*, s.cree_le AS mis_le FROM signets s
                     JOIN discussions d ON d.id = s.discussion_id
                     WHERE s.utilisateur_id = ? AND d.masquee = 0
                     ORDER BY s.cree_le DESC', [(int) $u['id']]);
    meta(['titre' => t('nav_signets'), 'noindex' => true]);
    rendre('signets', ['lignes' => $lignes]);
}
