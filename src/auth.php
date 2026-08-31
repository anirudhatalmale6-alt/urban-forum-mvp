<?php
/**
 * Comptes, sessions, roles et permissions.
 *
 * Le controle d'acces passe par des PERMISSIONS, jamais par « si le role
 * s'appelle moderateur ». C'est ce que demande la section 6 du cahier des
 * charges, et c'est ce qui evite la ligne `if ($role == 'admin')` qu'on
 * finit toujours par oublier quelque part.
 */

declare(strict_types=1);

const PERMISSIONS = [
    'forum.lire'            => 'Lire les forums et les discussions publics',
    'forum.publier'         => 'Creer une discussion et repondre',
    'forum.editer_sien'     => 'Modifier ses propres messages',
    'forum.reagir'          => 'Reagir, mettre en signet, s\'abonner',
    'forum.televerser'      => 'Televerser une image',
    'forum.signaler'        => 'Signaler un contenu',
    'projet.proposer'       => 'Proposer une fiche projet ou une mise a jour',
    'projet.publier'        => 'Publier directement une fiche projet',
    'portail.rediger'       => 'Ecrire un article et l\'enregistrer en brouillon',
    'portail.publier'       => 'Publier ou retirer un article du portail',
    'portail.une'           => 'Choisir ce qui remonte a la une du portail',
    'moderation.file'       => 'Voir et traiter la file des signalements',
    'moderation.contenu'    => 'Masquer, epingler, verrouiller, deplacer, fusionner',
    'moderation.sanction'   => 'Avertir, suspendre, bannir',
    'admin.taxonomie'       => 'Gerer la taxonomie geographique et thematique',
    'admin.utilisateurs'    => 'Gerer les comptes et les roles',
    'admin.configuration'   => 'Configurer la plateforme',
    'admin.statistiques'    => 'Voir le tableau de bord et les journaux',
];

/* Rang : sert a comparer deux comptes (un moderateur ne sanctionne pas un
   administrateur). Il ne sert JAMAIS a autoriser une action. */
const ROLES = [
    'visiteur' => [
        'rang' => 0, 'fr' => 'Visiteur', 'en' => 'Visitor', 'ar' => 'زائر',
        'perms' => ['forum.lire'],
    ],
    'membre' => [
        'rang' => 10, 'fr' => 'Membre', 'en' => 'Member', 'ar' => 'عضو',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler'],
    ],
    'contributeur' => [
        'rang' => 20, 'fr' => 'Contributeur', 'en' => 'Contributor', 'ar' => 'مساهم',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler',
                    'projet.proposer'],
    ],
    'contributeur_verifie' => [
        'rang' => 30, 'fr' => 'Contributeur verifie', 'en' => 'Verified contributor',
        'ar' => 'مساهم موثّق',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler',
                    'projet.proposer', 'projet.publier'],
    ],
    'pro' => [
        'rang' => 35, 'fr' => 'Compte professionnel', 'en' => 'Professional account',
        'ar' => 'حساب مؤسسي',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler',
                    'projet.proposer'],
    ],
    /* Le portail a son propre metier. Un redacteur ecrit et publie des
       articles ; il ne masque pas un message et ne suspend personne. C'est
       exactement pour cela que l'autorisation passe par des permissions et
       non par un rang : un redacteur (40) est « au-dessus » d'un
       contributeur verifie (30) sans hériter de la moindre capacite de
       moderation. */
    'redacteur' => [
        'rang' => 40, 'fr' => 'Redacteur', 'en' => 'Editor', 'ar' => 'محرّر',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler',
                    'projet.proposer',
                    'portail.rediger', 'portail.publier', 'portail.une'],
    ],
    'moderateur' => [
        'rang' => 50, 'fr' => 'Moderateur', 'en' => 'Moderator', 'ar' => 'مشرف',
        'perms' => ['forum.lire', 'forum.publier', 'forum.editer_sien',
                    'forum.reagir', 'forum.televerser', 'forum.signaler',
                    'projet.proposer', 'projet.publier',
                    'portail.rediger', 'portail.publier', 'portail.une',
                    'moderation.file', 'moderation.contenu', 'moderation.sanction'],
    ],
    'administrateur' => [
        'rang' => 100, 'fr' => 'Administrateur', 'en' => 'Administrator', 'ar' => 'مدير',
        'perms' => '*',
    ],
];

function role_cle_i18n(string $cle): string
{
    return t('role_' . $cle);
}

/* ------------------------------------------------------------------ */
/* Session                                                             */
/* ------------------------------------------------------------------ */

function utilisateur(): ?array
{
    /* Couture de test, EN LIGNE DE COMMANDE UNIQUEMENT. Les scripts de
       controle et les outils doivent pouvoir agir « en tant que » quelqu'un
       alors qu'il n'existe aucune session. La condition sur PHP_SAPI est ce
       qui empeche cette porte d'exister sur le serveur web. */
    if (PHP_SAPI === 'cli' && isset($GLOBALS['uf_utilisateur_force'])) {
        return $GLOBALS['uf_utilisateur_force'];
    }

    static $u = false;
    if ($u !== false) return $u;
    $u = null;

    $jeton = $_COOKIE['uf_session'] ?? null;
    if ($jeton && preg_match('/^[a-f0-9]{64}$/', $jeton)) {
        $emp = hash('sha256', $jeton);
        $s = qun('SELECT * FROM sessions WHERE jeton = ?', [$emp]);
        if ($s && strtotime($s['expire_le'] . ' UTC') > time()) {
            $c = qun('SELECT * FROM utilisateurs WHERE id = ?', [(int) $s['utilisateur_id']]);
            if ($c && (int) $c['actif'] === 1 && (int) $c['banni'] === 0) {
                $u = $c;
                // La date de derniere vue ne s'ecrit qu'une fois par heure :
                // une ecriture a chaque page transforme chaque lecture en
                // ecriture, et c'est ce qui met une base a genoux.
                if (!$c['vu_le'] || time() - strtotime($c['vu_le'] . ' UTC') > 3600) {
                    maj('utilisateurs', (int) $c['id'], ['vu_le' => maintenant()]);
                }
            }
        }
    }
    return $u;
}

function connecte(): bool { return utilisateur() !== null; }

function ouvrir_session(int $utilisateur_id): string
{
    $jeton = bin2hex(random_bytes(32));
    insere('sessions', [
        'jeton'          => hash('sha256', $jeton),
        'utilisateur_id' => $utilisateur_id,
        'cree_le'        => maintenant(),
        'expire_le'      => gmdate('Y-m-d H:i:s', time() + cfg('duree_session')),
        'ip'             => ip_client(),
        'agent'          => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    if (!headers_sent()) {
        setcookie('uf_session', $jeton, [
            'expires'  => time() + cfg('duree_session'),
            'path'     => '/',
            'httponly' => true,
            'secure'   => (bool) cfg('cookie_secure'),
            'samesite' => 'Lax',
        ]);
    }
    return $jeton;
}

function fermer_session(): void
{
    $jeton = $_COOKIE['uf_session'] ?? null;
    if ($jeton) {
        q('DELETE FROM sessions WHERE jeton = ?', [hash('sha256', $jeton)]);
        if (!headers_sent()) {
            setcookie('uf_session', '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Permissions                                                         */
/* ------------------------------------------------------------------ */

function role_de(?array $u): array
{
    if (!$u) return ROLES['visiteur'] + ['cle' => 'visiteur'];
    $r = qun('SELECT * FROM roles WHERE id = ?', [(int) $u['role_id']]);
    $cle = $r['cle'] ?? 'membre';
    return (ROLES[$cle] ?? ROLES['membre']) + ['cle' => $cle];
}

/**
 * Les permissions d'un role, LUES EN BASE.
 *
 * La constante ROLES declare la configuration livree ; c'est elle que
 * l'installeur ecrit dans `role_permissions`. Mais c'est la TABLE qui fait
 * foi a l'execution — sinon la table n'est qu'un decor, la page
 * d'administration des permissions ne peut rien changer, et le modele de la
 * section 9 (Role, Permission, et la liaison entre les deux) ne veut rien
 * dire.
 *
 * Deux garde-fous :
 *
 * - un role declare « * » reste tout-puissant sans passer par la table. Une
 *   permission ajoutee dans le code apres la derniere installation manquerait
 *   sinon a l'administrateur, qui se retrouverait enferme dehors de sa propre
 *   plateforme.
 * - un role SANS AUCUNE ligne en base retombe sur la declaration du code.
 *   Une table vide veut dire « pas encore installe », pas « plus aucun
 *   droit » : on retombe sur le defaut declare, jamais sur une ouverture.
 */
function permissions_de_role(string $cle)
{
    static $cache = [];
    if (array_key_exists($cle, $cache)) return $cache[$cle];

    $declare = ROLES[$cle]['perms'] ?? [];
    if ($declare === '*') return $cache[$cle] = '*';

    try {
        $lignes = qtous(
            'SELECT p.cle FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.cle = ?', [$cle]);
    } catch (Throwable) {
        return $cache[$cle] = $declare;      // avant installation
    }
    $en_base = array_column($lignes, 'cle');
    return $cache[$cle] = $en_base ?: $declare;
}

function peut(string $permission, ?array $u = null): bool
{
    $u = $u ?? utilisateur();

    // Un compte suspendu garde la lecture et perd tout le reste. C'est le
    // sens du mot : il n'est pas banni, il ne peut plus agir.
    if ($u && !empty($u['suspendu_jusqu'])
        && strtotime($u['suspendu_jusqu'] . ' UTC') > time()
        && $permission !== 'forum.lire') {
        return false;
    }
    if ($u && (int) $u['banni'] === 1) return $permission === 'forum.lire';

    $perms = permissions_de_role(role_de($u)['cle']);
    if ($perms === '*') return true;
    return in_array($permission, $perms, true);
}

function exige(string $permission): void
{
    if (peut($permission)) return;
    if (!connecte()) {
        reponse_refus(401, t('refuse_connexion'));
    }
    reponse_refus(403, t('refuse_droit'));
}

/**
 * Refus unique pour l'interface ET pour l'API. Le critere de recette n°8
 * demande que les permissions bloquent des deux cotes ; le seul moyen d'en
 * etre sur est qu'il n'y ait qu'un chemin de refus, appele par les deux.
 */
function reponse_refus(int $code, string $message): never
{
    http_response_code($code);
    if (est_api()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
    } else {
        rendre('erreur', ['code' => $code, 'message' => $message]);
    }
    exit;
}

function est_api(): bool
{
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
}

/* ------------------------------------------------------------------ */
/* Inscription et connexion                                            */
/* ------------------------------------------------------------------ */

function inscrire(string $identifiant, string $email, string $mdp, string $mdp2, string $langue = 'fr'): array
{
    $erreurs = [];
    $identifiant = trim($identifiant);
    $email = trim($email);

    if (!preg_match('/^[\p{L}\p{N}_.-]{3,30}$/u', $identifiant)) {
        $erreurs['identifiant'] = t('cpt_erreur_pris');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = t('cpt_erreur_email');
    }
    if (mb_strlen($mdp) < 10)  $erreurs['mot_de_passe'] = t('cpt_erreur_mdp_court');
    if ($mdp !== $mdp2)        $erreurs['mot_de_passe2'] = t('cpt_erreur_mdp_differents');
    if ($erreurs) return ['erreurs' => $erreurs];

    if (!limite_ok('inscription', ip_client())) {
        return ['erreurs' => ['global' => t('err_limite')]];
    }

    $pris = qval('SELECT id FROM utilisateurs WHERE identifiant = ? OR email = ?',
                 [$identifiant, $email]);
    if ($pris !== null) return ['erreurs' => ['identifiant' => t('cpt_erreur_pris')]];

    $role_id = (int) qval('SELECT id FROM roles WHERE cle = ?', ['membre']);
    $id = insere('utilisateurs', [
        'identifiant'   => $identifiant,
        'email'         => $email,
        'mot_de_passe'  => password_hash($mdp, PASSWORD_DEFAULT),
        'role_id'       => $role_id,
        'nom_public'    => $identifiant,
        'langue'        => in_array($langue, cfg('langues'), true) ? $langue : cfg('langue_defaut'),
        'cree_le'       => maintenant(),
        'actif'         => 1, 'banni' => 0, 'demo' => 0,
        'profil_public' => 1, 'nb_messages' => 0,
    ]);
    prefs_notif_par_defaut($id);
    audit('inscription', 'utilisateur#' . $id);
    return ['id' => $id];
}

function connecter(string $identifiant, string $mdp): array
{
    if (!limite_ok('connexion', ip_client() . '|' . mb_strtolower($identifiant))) {
        return ['erreurs' => ['global' => t('cpt_trop_essais')]];
    }
    $u = qun('SELECT * FROM utilisateurs WHERE identifiant = ? OR email = ?',
             [$identifiant, $identifiant]);

    // Meme message et meme cout dans les deux cas : sans le hachage a vide,
    // un identifiant inexistant repond plus vite qu'un mot de passe faux, et
    // ce delai suffit a enumerer les comptes.
    if (!$u) {
        password_verify($mdp, '$2y$10$usesomesillystringfore.Hxk9Fq2Xy8oGm4a9DHJvGKQ.f6a');
        return ['erreurs' => ['global' => t('cpt_erreur_identifiants')]];
    }
    if (!password_verify($mdp, (string) $u['mot_de_passe'])) {
        journal('alerte', 'echec de connexion', ['identifiant' => $identifiant]);
        return ['erreurs' => ['global' => t('cpt_erreur_identifiants')]];
    }
    if ((int) $u['banni'] === 1) {
        return ['erreurs' => ['global' => t('err_403')]];
    }
    ouvrir_session((int) $u['id']);
    audit('connexion', 'utilisateur#' . $u['id']);
    return ['id' => (int) $u['id']];
}

function prefs_notif_par_defaut(int $uid): void
{
    foreach (['reponse', 'mention', 'abonnement', 'moderation'] as $type) {
        foreach (['app' => 1, 'email' => 0] as $canal => $actif) {
            try {
                insere('preferences_notif', [
                    'utilisateur_id' => $uid, 'type' => $type,
                    'canal' => $canal, 'actif' => $actif,
                ]);
            } catch (Throwable) { /* deja presente */ }
        }
    }
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function sel(): string
{
    $s = cfg('sel_session');
    if ($s) return $s;
    // Pas de sel configure : on en derive un stable depuis la base plutot
    // que d'en tirer un nouveau a chaque requete, ce qui invaliderait tous
    // les formulaires ouverts.
    $r = reglage('sel_derive');
    if ($r) return $r;
    $r = bin2hex(random_bytes(32));
    try { insere('reglages', ['cle' => 'sel_derive', 'valeur' => $r]); } catch (Throwable) {}
    return $r;
}

function jeton_csrf(): string
{
    $base = $_COOKIE['uf_session'] ?? ($_COOKIE['uf_anon'] ?? '');
    if ($base === '') {
        $base = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie('uf_anon', $base, [
                'expires' => time() + 86400, 'path' => '/',
                'httponly' => true, 'secure' => (bool) cfg('cookie_secure'),
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['uf_anon'] = $base;
    }
    return hash_hmac('sha256', 'csrf|' . $base, sel());
}

function csrf_champ(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(jeton_csrf()) . '">';
}

function verifie_csrf(): void
{
    $envoye = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(jeton_csrf(), (string) $envoye)) {
        journal('alerte', 'jeton CSRF invalide', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        reponse_refus(419, t('err_csrf'));
    }
}
