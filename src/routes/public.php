<?php
/** Pages publiques : navigables SANS compte (critere de recette n°1). */

declare(strict_types=1);

function page_accueil(): void
{
    $continents = qtous('SELECT * FROM continents ORDER BY rang, id');
    $actives = qtous(
        'SELECT d.*, f.slug AS forum_slug, f.titre_fr, f.titre_en, f.titre_ar,
                u.identifiant
         FROM discussions d
         JOIN forums f ON f.id = d.forum_id
         LEFT JOIN utilisateurs u ON u.id = d.auteur_id
         WHERE d.masquee = 0
         ORDER BY d.dernier_message_le DESC, d.id DESC LIMIT 12');
    $villes = qtous(
        'SELECT v.*, p.slug AS pays_slug, p.nom_fr AS pays_fr, p.nom_en AS pays_en,
                p.nom_ar AS pays_ar,
                (SELECT COUNT(*) FROM forums f WHERE f.ville_id = v.id) AS nb_forums
         FROM villes v JOIN pays p ON p.id = v.pays_id
         ORDER BY nb_forums DESC, v.id LIMIT 12');

    // Chaque chiffre est COMPTE ici. Aucun n'est ecrit en dur dans la vue.
    $stats = [
        'membres'     => (int) qval('SELECT COUNT(*) FROM utilisateurs WHERE actif = 1 AND banni = 0'),
        'discussions' => (int) qval('SELECT COUNT(*) FROM discussions WHERE masquee = 0'),
        'messages'    => (int) qval('SELECT COUNT(*) FROM messages WHERE masque = 0'),
        'villes'      => (int) qval('SELECT COUNT(*) FROM villes'),
        'pays'        => (int) qval('SELECT COUNT(*) FROM pays'),
        'projets'     => (int) qval('SELECT COUNT(*) FROM projets'),
    ];

    meta([
        'titre' => cfg('nom_site'),
        'description' => t('accueil_intro'),
        'canonique' => '/',
    ]);
    rendre('accueil', compact('continents', 'actives', 'villes', 'stats'));
}

function page_forums(): void
{
    $tous = qtous('SELECT * FROM forums ORDER BY rang, id');
    meta(['titre' => t('nav_forums'), 'canonique' => '/forums']);
    rendre('forums', ['tous' => $tous]);
}

function page_forum(string $slug): void
{
    $f = qun('SELECT * FROM forums WHERE slug = ?', [$slug]);
    if (!$f) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $enfants = qtous('SELECT * FROM forums WHERE parent_id = ? ORDER BY rang, id', [(int) $f['id']]);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = (int) cfg('discussions_par_page');
    $total = (int) qval('SELECT COUNT(*) FROM discussions WHERE forum_id = ? AND masquee = 0', [(int) $f['id']]);
    $debut = ($page - 1) * $pp;

    $discussions = qtous(
        "SELECT d.*, u.identifiant, du.identifiant AS dernier_auteur
         FROM discussions d
         LEFT JOIN utilisateurs u ON u.id = d.auteur_id
         LEFT JOIN messages dm ON dm.id = d.dernier_message_id
         LEFT JOIN utilisateurs du ON du.id = dm.auteur_id
         WHERE d.forum_id = ? AND d.masquee = 0
         ORDER BY d.epinglee DESC, d.dernier_message_le DESC, d.id DESC
         LIMIT $pp OFFSET $debut", [(int) $f['id']]);

    meta([
        'titre' => champ_langue($f, 'titre'),
        'description' => extrait(champ_langue($f, 'description') ?: champ_langue($f, 'titre')),
        'canonique' => '/f/' . $f['slug'],
    ]);
    rendre('forum', compact('f', 'enfants', 'discussions', 'page', 'total', 'pp'));
}

function page_discussion(string $slug): void
{
    $d = qun('SELECT * FROM discussions WHERE slug = ?', [$slug]);
    if (!$d) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    // Une discussion fusionnee redirige vers celle qui l'a absorbee : les
    // liens deja partages continuent de fonctionner.
    if (!empty($d['fusionnee_dans'])) {
        $cible = qval('SELECT slug FROM discussions WHERE id = ?', [(int) $d['fusionnee_dans']]);
        if ($cible) { header('Location: ' . lien('/d/' . $cible), true, 301); exit; }
    }
    if ((int) $d['masquee'] === 1 && !peut('moderation.contenu')) {
        http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return;
    }

    compter_vue((int) $d['id']);

    $f = qun('SELECT * FROM forums WHERE id = ?', [(int) $d['forum_id']]);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = (int) cfg('messages_par_page');
    $total = (int) qval('SELECT COUNT(*) FROM messages WHERE discussion_id = ?', [(int) $d['id']]);
    $debut = ($page - 1) * $pp;

    $messages = qtous(
        "SELECT m.*, u.identifiant, u.nom_public, u.nb_messages, u.cree_le AS inscrit_le,
                r.cle AS role_cle
         FROM messages m
         LEFT JOIN utilisateurs u ON u.id = m.auteur_id
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE m.discussion_id = ?
         ORDER BY m.position ASC LIMIT $pp OFFSET $debut", [(int) $d['id']]);

    $ids = array_column($messages, 'id');
    $reactions = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach (qtous("SELECT message_id, type, COUNT(*) n FROM reactions
                        WHERE message_id IN ($ph) GROUP BY message_id, type", $ids) as $r) {
            $reactions[(int) $r['message_id']][$r['type']] = (int) $r['n'];
        }
    }

    $u = utilisateur();
    $abonne = $u && qval('SELECT id FROM abonnements WHERE utilisateur_id = ? AND objet_type = ? AND objet_id = ?',
                         [(int) $u['id'], 'discussion', (int) $d['id']]) !== null;
    $en_signet = $u && qval('SELECT id FROM signets WHERE utilisateur_id = ? AND discussion_id = ?',
                            [(int) $u['id'], (int) $d['id']]) !== null;

    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'DiscussionForumPosting',
        'headline' => $d['titre'],
        'datePublished' => str_replace(' ', 'T', (string) $d['cree_le']) . 'Z',
        'dateModified' => str_replace(' ', 'T', (string) ($d['dernier_message_le'] ?: $d['cree_le'])) . 'Z',
        'interactionStatistic' => [
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/ReplyAction',
             'userInteractionCount' => (int) $d['nb_reponses']],
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/ViewAction',
             'userInteractionCount' => (int) $d['nb_vues']],
        ],
    ];
    if (cfg('domaine')) $ld['url'] = url('/d/' . $d['slug']);

    meta([
        'titre' => $d['titre'],
        'description' => extrait((string) ($messages[0]['corps'] ?? $d['titre'])),
        'canonique' => '/d/' . $d['slug'] . ($page > 1 ? '?page=' . $page : ''),
        'og_type' => 'article',
        'ld' => $ld,
    ]);
    rendre('discussion', compact('d', 'f', 'messages', 'reactions', 'page', 'total', 'pp',
                                 'abonne', 'en_signet'));
}

/**
 * Compteur de vues deduplique par empreinte et par jour.
 * Compter chaque chargement gonfle le chiffre a chaque rechargement de
 * page, et un compteur gonfle est un compteur qu'on ne peut plus citer.
 */
function compter_vue(int $discussion_id): void
{
    $emp = substr(hash('sha256', ip_client() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . sel()), 0, 64);
    try {
        insere('vues_discussion', [
            'discussion_id' => $discussion_id, 'empreinte' => $emp, 'jour' => gmdate('Y-m-d'),
        ]);
        q('UPDATE discussions SET nb_vues = nb_vues + 1 WHERE id = ?', [$discussion_id]);
    } catch (Throwable) {
        // Contrainte unique : deja compte aujourd'hui. Ce n'est pas une erreur.
    }
}

function aller_message(string $id): void
{
    $m = qun('SELECT m.id, m.discussion_id, m.position, d.slug FROM messages m
              JOIN discussions d ON d.id = m.discussion_id WHERE m.id = ?', [(int) $id]);
    if (!$m) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }
    $pp = (int) cfg('messages_par_page');
    $page = (int) ceil(max(1, (int) $m['position']) / $pp);
    $u = '/d/' . $m['slug'] . ($page > 1 ? '?page=' . $page : '') . '#m' . (int) $m['id'];
    header('Location: ' . lien($u), true, 302);
    exit;
}

function page_villes(): void
{
    $villes = qtous(
        'SELECT v.*, p.slug AS pays_slug, p.nom_fr AS pays_fr, p.nom_en AS pays_en, p.nom_ar AS pays_ar,
                c.slug AS continent_slug,
                (SELECT COUNT(*) FROM discussions d JOIN forums f ON f.id = d.forum_id
                  WHERE f.ville_id = v.id AND d.masquee = 0) AS nb_discussions
         FROM villes v
         JOIN pays p ON p.id = v.pays_id
         JOIN continents c ON c.id = p.continent_id
         ORDER BY p.nom_en, v.nom_en');
    meta(['titre' => t('nav_villes'), 'canonique' => '/villes']);
    rendre('villes', compact('villes'));
}

function page_ville(string $slug): void
{
    $v = qun('SELECT v.*, p.slug AS pays_slug, p.nom_fr AS pays_fr, p.nom_en AS pays_en,
                     p.nom_ar AS pays_ar, c.slug AS continent_slug, c.nom_fr AS cont_fr,
                     c.nom_en AS cont_en, c.nom_ar AS cont_ar
              FROM villes v JOIN pays p ON p.id = v.pays_id
              JOIN continents c ON c.id = p.continent_id WHERE v.slug = ?', [$slug]);
    if (!$v) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $forums = qtous('SELECT * FROM forums WHERE ville_id = ? ORDER BY rang, id', [(int) $v['id']]);
    $discussions = qtous(
        'SELECT d.*, u.identifiant FROM discussions d
         JOIN forums f ON f.id = d.forum_id
         LEFT JOIN utilisateurs u ON u.id = d.auteur_id
         WHERE f.ville_id = ? AND d.masquee = 0
         ORDER BY d.dernier_message_le DESC LIMIT 20', [(int) $v['id']]);

    meta(['titre' => champ_langue($v), 'canonique' => '/v/' . $v['slug']]);
    rendre('ville', compact('v', 'forums', 'discussions'));
}

function page_pays(string $slug): void
{
    $p = qun('SELECT p.*, c.slug AS continent_slug, c.nom_fr AS cont_fr, c.nom_en AS cont_en,
                     c.nom_ar AS cont_ar
              FROM pays p JOIN continents c ON c.id = p.continent_id WHERE p.slug = ?', [$slug]);
    if (!$p) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $villes = qtous('SELECT * FROM villes WHERE pays_id = ? ORDER BY nom_en', [(int) $p['id']]);
    $forums = qtous('SELECT * FROM forums WHERE pays_id = ? ORDER BY rang, id', [(int) $p['id']]);
    $discussions = qtous(
        'SELECT d.*, u.identifiant FROM discussions d
         JOIN forums f ON f.id = d.forum_id
         LEFT JOIN utilisateurs u ON u.id = d.auteur_id
         WHERE (f.pays_id = ? OR f.ville_id IN (SELECT id FROM villes WHERE pays_id = ?))
           AND d.masquee = 0
         ORDER BY d.dernier_message_le DESC LIMIT 20', [(int) $p['id'], (int) $p['id']]);

    meta(['titre' => champ_langue($p), 'canonique' => '/pays/' . $p['slug']]);
    rendre('pays', compact('p', 'villes', 'forums', 'discussions'));
}

function page_continent(string $slug): void
{
    $c = qun('SELECT * FROM continents WHERE slug = ?', [$slug]);
    if (!$c) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }
    $pays = qtous(
        'SELECT p.*, (SELECT COUNT(*) FROM villes v WHERE v.pays_id = p.id) AS nb_villes
         FROM pays p WHERE p.continent_id = ? ORDER BY p.nom_en', [(int) $c['id']]);
    meta(['titre' => champ_langue($c), 'canonique' => '/continent/' . $c['slug']]);
    rendre('continent', compact('c', 'pays'));
}

function page_recherche(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $espace = ($_GET['espace'] ?? 'forum') === 'projets' ? 'projets' : 'forum';
    $tri = in_array($_GET['tri'] ?? '', ['pertinence', 'date', 'activite'], true)
         ? $_GET['tri'] : 'pertinence';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = 20;

    $res = ['resultats' => [], 'total' => 0, 'suggestions' => []];
    if ($q !== '') {
        $res = recherche_executer($q, ['espace' => $espace, 'tri' => $tri,
                                       'limite' => $pp, 'depart' => ($page - 1) * $pp]);
    }
    meta([
        'titre' => $q !== '' ? $q . ' — ' . t('nav_recherche') : t('nav_recherche'),
        // Une page de resultats de recherche ne doit pas etre indexee : elle
        // duplique le contenu et fabrique une infinite d'URL.
        'noindex' => true,
    ]);
    rendre('recherche', compact('q', 'res', 'espace', 'tri', 'page', 'pp'));
}

function page_profil(string $pseudo): void
{
    $pseudo = rawurldecode($pseudo);
    $p = qun('SELECT u.*, r.cle AS role_cle FROM utilisateurs u
              LEFT JOIN roles r ON r.id = u.role_id WHERE u.identifiant = ?', [$pseudo]);
    if (!$p) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $moi = utilisateur();
    $prive = (int) $p['profil_public'] === 0 && (!$moi || (int) $moi['id'] !== (int) $p['id']);

    $messages = $prive ? [] : qtous(
        'SELECT m.*, d.titre, d.slug FROM messages m
         JOIN discussions d ON d.id = m.discussion_id
         WHERE m.auteur_id = ? AND m.masque = 0 AND d.masquee = 0
         ORDER BY m.cree_le DESC LIMIT 20', [(int) $p['id']]);

    $bloque = $moi && qval('SELECT id FROM blocages WHERE utilisateur_id = ? AND bloque_id = ?',
                           [(int) $moi['id'], (int) $p['id']]) !== null;

    meta(['titre' => (string) $p['identifiant'], 'canonique' => '/u/' . rawurlencode($pseudo),
          'noindex' => $prive]);
    rendre('profil', compact('p', 'messages', 'prive', 'bloque'));
}

function page_projets(): void
{
    $n = (int) qval('SELECT COUNT(*) FROM projets');
    meta(['titre' => t('nav_projets'), 'canonique' => '/projets']);
    rendre('projets', ['n' => $n]);
}

function page_aide(): void
{
    meta(['titre' => t('nav_aide'), 'canonique' => '/aide']);
    rendre('aide', []);
}

function page_a_renseigner(): void
{
    $champs = champs_vides();
    meta(['titre' => t('vide_titre'), 'canonique' => '/a-renseigner', 'noindex' => true]);
    rendre('a_renseigner', ['champs' => $champs]);
}

/**
 * La liste des valeurs absentes, calculee et non recopiee.
 * Elle est produite a partir de la configuration elle-meme : ajouter un
 * champ vide dans config.php le fait apparaitre ici sans toucher a cette
 * fonction, donc la page ne peut pas devenir fausse en silence.
 */
function champs_vides(): array
{
    $etiquettes = [
        'nom_site' => 'Nom definitif de la plateforme',
        'baseline' => 'Signature / baseline',
        'domaine' => 'Nom de domaine (URL absolue, sans / final)',
        'entite_juridique' => 'Raison sociale, forme juridique, immatriculation',
        'adresse_postale' => 'Adresse postale du siege',
        'contact_public' => 'Canal de contact public',
        'directeur_publication' => 'Directeur de la publication',
        'hebergeur' => 'Hebergeur : nom et adresse (mentions legales)',
        'mail_expediteur' => "Adresse d'expedition des notifications",
        'mail_nom' => "Nom affiche de l'expediteur",
    ];
    $out = [];
    $c = cfg();
    foreach ($etiquettes as $cle => $lib) {
        $v = $c[$cle] ?? '';
        // nom_site porte une valeur PROVISOIRE : elle compte comme a
        // renseigner tant que nom_provisoire est vrai.
        $manquant = ($v === '') || ($cle === 'nom_site' && cfg('nom_provisoire'));
        if ($manquant) $out[] = ['cle' => $cle, 'libelle' => $lib, 'valeur' => $v];
    }
    if ($c['bd']['pilote'] === 'sqlite') {
        $out[] = ['cle' => 'bd', 'libelle' => 'Base MySQL de production (hote, base, utilisateur)',
                  'valeur' => ''];
    }
    if (!qval('SELECT COUNT(*) FROM projets')) {
        $out[] = ['cle' => 'projets', 'libelle' => 'Fiches projets (phase 2) : aucune donnee saisie',
                  'valeur' => ''];
    }
    $sans_coord = (int) qval('SELECT COUNT(*) FROM villes WHERE latitude IS NULL');
    if ($sans_coord > 0) {
        $out[] = ['cle' => 'coordonnees',
                  'libelle' => 'Coordonnees des villes : ' . nombre($sans_coord)
                             . ' ville(s) sans latitude/longitude (necessaires a la carte, phase 2)',
                  'valeur' => ''];
    }
    return $out;
}

function route_media(string $id): void { servir_media((int) $id); }
