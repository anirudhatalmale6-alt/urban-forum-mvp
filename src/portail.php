<?php
/**
 * Le PORTAIL : la couche editoriale publique posee devant le forum.
 *
 * Le forum est une conversation ; le portail est ce que voit quelqu'un qui
 * n'a pas encore de compte. Il lit, il comprend de quoi parle le site, et
 * s'il veut participer il descend dans la discussion attachee a l'article.
 * C'est le seul lien entre les deux : un article peut OUVRIR une
 * discussion, il ne la remplace pas.
 *
 * ------------------------------------------------------------------------
 * CE QUE LE PORTAIL N'INVENTE PAS
 *
 * Un article de portail parle de projets reels. Un budget, une hauteur, une
 * date de livraison, un nom de maitre d'ouvrage sont des FAITS : ils
 * viennent d'une source ou ils ne sont pas ecrits. Le formulaire de
 * redaction propose donc un bloc « sources » (table `sources`, la meme que
 * pour les fiches projet) et la page d'article affiche ces sources sous le
 * texte. Un article publie sans source porte une mention visible plutot
 * qu'un silence.
 *
 * Aucun article de DEMONSTRATION ne parle d'un projet reel. Les demos
 * livrees expliquent le fonctionnement du portail lui-meme. La raison est
 * la meme que pour les discussions de demonstration : une phrase ecrite
 * pour meubler une maquette se retrouve indexee, citee, puis reprise
 * ailleurs comme si elle avait ete verifiee.
 * ------------------------------------------------------------------------
 *
 * UNE LANGUE PAR ARTICLE. Voir le commentaire de la table `articles` dans
 * src/schema.php : on ne fabrique pas de traduction pour pouvoir
 * enregistrer. Les versions d'un meme sujet partagent la colonne `groupe`.
 */

declare(strict_types=1);

const ARTICLE_STATUTS = ['brouillon', 'publie', 'retire'];

/* ------------------------------------------------------------------ */
/* Rubriques                                                            */
/* ------------------------------------------------------------------ */

function rubriques_toutes(): array
{
    static $c = null;
    if ($c === null) $c = qtous('SELECT * FROM rubriques ORDER BY rang, id');
    return $c;
}

function rubrique_par_slug(string $slug): ?array
{
    return qun('SELECT * FROM rubriques WHERE slug = ?', [$slug]);
}

/* ------------------------------------------------------------------ */
/* Lecture                                                              */
/* ------------------------------------------------------------------ */

/**
 * Condition de VISIBILITE publique d'un article, en un seul endroit.
 *
 * Elle contient `publie_le <= maintenant`, et c'est important : une date de
 * publication dans le futur est une programmation, pas une publication. La
 * date est donc RELUE a chaque affichage plutot que testee une seule fois
 * au moment de l'enregistrement. Un article programme pour demain n'est
 * visible de personne aujourd'hui, y compris par une URL devinee.
 *
 * @return array{0:string,1:array} fragment SQL et parametres
 */
function condition_visible(string $alias = 'a'): array
{
    return ["$alias.statut = ? AND $alias.publie_le IS NOT NULL AND $alias.publie_le <= ?",
            ['publie', maintenant()]];
}

/**
 * Liste d'articles publies.
 *
 * $opts : rubrique_id, ville_id, pays_id, continent_id, langue, une,
 *         limite, depart, exclure
 */
function articles_publies(array $opts = []): array
{
    [$cond, $params] = condition_visible();
    $where = [$cond];

    foreach (['rubrique_id', 'ville_id', 'pays_id', 'continent_id', 'projet_id'] as $col) {
        if (!empty($opts[$col])) { $where[] = "a.$col = ?"; $params[] = (int) $opts[$col]; }
    }
    if (!empty($opts['langue'])) { $where[] = 'a.langue = ?'; $params[] = $opts['langue']; }
    if (!empty($opts['une']))    { $where[] = 'a.une = 1'; }
    if (!empty($opts['exclure'])) { $where[] = 'a.id <> ?'; $params[] = (int) $opts['exclure']; }

    // Comme pour l'autocompletion : LIMIT et OFFSET ne sont pas des
    // parametres lies. PDO en preparation NATIVE les passe en chaine et
    // MySQL refuse « LIMIT '10' ». Les deux sont castes en entier juste ici.
    $limite = max(1, min(100, (int) ($opts['limite'] ?? 12)));
    $depart = max(0, (int) ($opts['depart'] ?? 0));

    $ordre = !empty($opts['une'])
        ? 'a.rang_une ASC, a.publie_le DESC'
        : 'a.publie_le DESC, a.id DESC';

    return qtous(
        "SELECT a.*, r.slug AS rubrique_slug,
                r.nom_fr AS rub_fr, r.nom_en AS rub_en, r.nom_ar AS rub_ar,
                u.identifiant AS auteur,
                v.slug AS ville_slug, v.nom_fr AS ville_fr, v.nom_en AS ville_en,
                v.nom_ar AS ville_ar,
                p.slug AS pays_slug, p.nom_fr AS pays_fr, p.nom_en AS pays_en,
                p.nom_ar AS pays_ar
         FROM articles a
         LEFT JOIN rubriques r ON r.id = a.rubrique_id
         LEFT JOIN utilisateurs u ON u.id = a.auteur_id
         LEFT JOIN villes v ON v.id = a.ville_id
         LEFT JOIN pays p ON p.id = a.pays_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY $ordre
         LIMIT $limite OFFSET $depart", $params);
}

function compter_articles(array $opts = []): int
{
    [$cond, $params] = condition_visible();
    $where = [$cond];
    foreach (['rubrique_id', 'ville_id', 'pays_id', 'continent_id'] as $col) {
        if (!empty($opts[$col])) { $where[] = "a.$col = ?"; $params[] = (int) $opts[$col]; }
    }
    if (!empty($opts['langue'])) { $where[] = 'a.langue = ?'; $params[] = $opts['langue']; }
    return (int) qval('SELECT COUNT(*) FROM articles a WHERE ' . implode(' AND ', $where), $params);
}

/**
 * Un article par son slug.
 *
 * $avec_brouillon sert l'apercu en redaction : il n'est jamais vrai sur une
 * requete publique, il est passe par le controleur APRES avoir verifie la
 * permission portail.rediger.
 */
function article_par_slug(string $slug, bool $avec_brouillon = false): ?array
{
    $a = qun(
        'SELECT a.*, r.slug AS rubrique_slug,
                r.nom_fr AS rub_fr, r.nom_en AS rub_en, r.nom_ar AS rub_ar,
                u.identifiant AS auteur, u.nom_public AS auteur_nom
         FROM articles a
         LEFT JOIN rubriques r ON r.id = a.rubrique_id
         LEFT JOIN utilisateurs u ON u.id = a.auteur_id
         WHERE a.slug = ?', [$slug]);
    if (!$a) return null;
    if ($avec_brouillon) return $a;

    $visible = $a['statut'] === 'publie'
        && !empty($a['publie_le'])
        && strtotime($a['publie_le'] . ' UTC') <= time();
    return $visible ? $a : null;
}

/**
 * Remplace chaque article par sa version dans la langue du lecteur, quand
 * elle existe VRAIMENT.
 *
 * Sans cela, un lecteur arabe voyait le titre francais d'un sujet dont la
 * version arabe etait publiee juste a cote : la mise a la une porte sur UN
 * article, pas sur le groupe de traductions. On ne fabrique aucune
 * traduction ici — on se contente de preferer celle qui existe. S'il n'y en
 * a pas, l'article d'origine reste, avec son etiquette de langue.
 */
function preferer_langue(array $articles): array
{
    $lang = langue();
    $out = [];
    foreach ($articles as $a) {
        if (($a['langue'] ?? '') === $lang || empty($a['groupe'])) { $out[] = $a; continue; }
        [$cond, $params] = condition_visible();
        $id = qval("SELECT a.id FROM articles a WHERE a.groupe = ? AND a.langue = ? AND $cond LIMIT 1",
                   [$a['groupe'], $lang, ...$params]);
        if ($id === null) { $out[] = $a; continue; }
        $ligne = qun(
            'SELECT a.*, r.slug AS rubrique_slug,
                    r.nom_fr AS rub_fr, r.nom_en AS rub_en, r.nom_ar AS rub_ar,
                    u.identifiant AS auteur
             FROM articles a
             LEFT JOIN rubriques r ON r.id = a.rubrique_id
             LEFT JOIN utilisateurs u ON u.id = a.auteur_id
             WHERE a.id = ?', [(int) $id]);
        $out[] = $ligne ?: $a;
    }
    return $out;
}

/** Les autres langues du MEME sujet — celles qui existent vraiment. */
function traductions_de(?string $groupe, int $sauf_id): array
{
    if (!$groupe) return [];
    [$cond, $params] = condition_visible();
    return qtous(
        "SELECT id, langue, slug, titre FROM articles a
         WHERE a.groupe = ? AND a.id <> ? AND $cond
         ORDER BY a.langue",
        [$groupe, $sauf_id, ...$params]);
}

/** Compteur de vues deduplique — meme mecanique que les discussions. */
function compter_vue_article(int $id): void
{
    $emp = substr(hash('sha256', ip_client() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')
                                . '|' . (string) cfg('sel_session')), 0, 64);
    try {
        insere('vues_article', ['article_id' => $id, 'empreinte' => $emp, 'jour' => gmdate('Y-m-d')]);
        q('UPDATE articles SET nb_vues = nb_vues + 1 WHERE id = ?', [$id]);
    } catch (PDOException) {
        // Doublon sur la cle unique : cette empreinte a deja vu cet article
        // aujourd'hui. Ce n'est pas une erreur, c'est le but.
    }
}

/* ------------------------------------------------------------------ */
/* Ecriture                                                             */
/* ------------------------------------------------------------------ */

/**
 * Cree ou met a jour un article. Renvoie l'identifiant.
 *
 * Le corps est rendu ICI, une fois, a l'ecriture — jamais a l'affichage.
 * C'est le meme choix que pour les messages du forum : le HTML stocke est
 * celui que NOUS avons fabrique, et la page d'article n'a donc jamais a
 * faire confiance a quoi que ce soit.
 */
function enregistrer_article(?int $id, array $d, int $auteur_id): int
{
    $titre = trim((string) ($d['titre'] ?? ''));
    $corps = (string) ($d['corps'] ?? '');
    if ($titre === '') throw new InvalidArgumentException('titre vide');

    $langue = in_array($d['langue'] ?? '', cfg('langues'), true)
        ? $d['langue'] : cfg('langue_defaut');
    $statut = in_array($d['statut'] ?? '', ARTICLE_STATUTS, true) ? $d['statut'] : 'brouillon';

    $chapeau = trim((string) ($d['chapeau'] ?? ''));
    if ($chapeau === '') $chapeau = extrait($corps, 220);

    $champs = [
        'langue'     => $langue,
        'rubrique_id' => !empty($d['rubrique_id']) ? (int) $d['rubrique_id'] : null,
        'titre'      => mb_substr($titre, 0, 255),
        'chapeau'    => $chapeau,
        'corps'      => $corps,
        'rendu'      => rendre_message($corps),
        'signature'  => trim((string) ($d['signature'] ?? '')) ?: null,
        'continent_id' => !empty($d['continent_id']) ? (int) $d['continent_id'] : null,
        'pays_id'    => !empty($d['pays_id']) ? (int) $d['pays_id'] : null,
        'ville_id'   => !empty($d['ville_id']) ? (int) $d['ville_id'] : null,
        'media_une_id' => !empty($d['media_une_id']) ? (int) $d['media_une_id'] : null,
        'statut'     => $statut,
        'une'        => !empty($d['une']) ? 1 : 0,
        'rang_une'   => (int) ($d['rang_une'] ?? 100),
        'maj_le'     => maintenant(),
    ];

    // La ville implique le pays, et le pays implique le continent. On les
    // deduit ici plutot que de faire confiance au formulaire : sans cela un
    // article rattache a une ville n'apparait pas sur la page de son pays.
    if ($champs['ville_id']) {
        $v = qun('SELECT pays_id FROM villes WHERE id = ?', [$champs['ville_id']]);
        if ($v) $champs['pays_id'] = (int) $v['pays_id'];
    }
    if ($champs['pays_id']) {
        $p = qun('SELECT continent_id FROM pays WHERE id = ?', [$champs['pays_id']]);
        if ($p) $champs['continent_id'] = (int) $p['continent_id'];
    }

    // La date de publication n'est posee qu'au PASSAGE a « publie », et une
    // date fournie est conservee telle quelle : c'est ce qui permet de
    // programmer. Repasser en brouillon ne l'efface pas — republier ne doit
    // pas donner a un vieil article la fraicheur d'un nouveau.
    $ancien = $id ? qun('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    if ($statut === 'publie') {
        $fournie = trim((string) ($d['publie_le'] ?? ''));
        if ($fournie !== '' && strtotime($fournie) !== false) {
            $champs['publie_le'] = gmdate('Y-m-d H:i:s', strtotime($fournie . ' UTC'));
        } elseif (empty($ancien['publie_le'])) {
            $champs['publie_le'] = maintenant();
        }
    }

    if ($id && $ancien) {
        maj('articles', $id, $champs);
    } else {
        $champs['groupe']    = (string) ($d['groupe'] ?? '') ?: bin2hex(random_bytes(8));
        $champs['auteur_id'] = $auteur_id;
        $champs['cree_le']   = maintenant();
        $champs['nb_vues']   = 0;
        $champs['demo']      = !empty($d['demo']) ? 1 : 0;
        $champs['slug']      = slug_unique('articles', slug($titre));
        $id = insere('articles', $champs);
    }

    indexer_article($id);
    audit($ancien ? 'article.modifie' : 'article.cree', 'article#' . $id,
          ['statut' => $statut, 'langue' => $langue]);
    return $id;
}

/**
 * Indexation.
 *
 * Espace « portail » et non « forum » : la section 4.4 demande que la
 * recherche puisse porter sur le forum ou sur les fiches structurees
 * separement. Un article n'est ni l'un ni l'autre, il a son propre espace,
 * et un article NON PUBLIE est desindexe — sinon un brouillon remonte dans
 * la recherche publique avec son titre.
 */
function indexer_article(int $id): void
{
    $a = qun('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$a) return;
    $visible = $a['statut'] === 'publie'
        && !empty($a['publie_le'])
        && strtotime($a['publie_le'] . ' UTC') <= time();
    if (!$visible) { desindexer('article', $id); return; }
    indexer('portail', 'article', $id,
            [5 => $a['titre'], 3 => (string) $a['chapeau'], 1 => (string) $a['corps']]);
}

/**
 * Ouvre (ou retrouve) la discussion attachee a un article.
 *
 * Le forum d'accueil est choisi du plus precis au plus general : le forum
 * de la ville, sinon celui du pays, sinon celui du continent, sinon le
 * premier forum racine. Si le site n'a AUCUN forum, on ne fabrique rien et
 * on renvoie null — l'article s'affiche alors sans bouton de discussion,
 * ce qui est honnete, plutot qu'avec un bouton qui mene a une erreur.
 */
function discussion_de_article(int $id, int $auteur_id): ?int
{
    $a = qun('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$a) return null;
    if (!empty($a['discussion_id'])) {
        $existe = qval('SELECT id FROM discussions WHERE id = ?', [(int) $a['discussion_id']]);
        if ($existe !== null) return (int) $a['discussion_id'];
    }

    $forum = null;
    foreach ([['ville_id', $a['ville_id']], ['pays_id', $a['pays_id']],
              ['continent_id', $a['continent_id']]] as [$col, $val]) {
        if (!$val) continue;
        $forum = qun("SELECT * FROM forums WHERE $col = ? AND ferme = 0 ORDER BY rang, id LIMIT 1",
                     [(int) $val]);
        if ($forum) break;
    }
    if (!$forum) {
        $forum = qun('SELECT * FROM forums WHERE parent_id IS NULL AND ferme = 0 ORDER BY rang, id LIMIT 1');
    }
    if (!$forum) return null;

    $titre = mb_substr((string) $a['titre'], 0, 200);
    $did = insere('discussions', [
        'forum_id' => (int) $forum['id'], 'auteur_id' => $auteur_id,
        'titre' => $titre, 'slug' => slug_unique('discussions', slug($titre)),
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
        'nb_vues' => 0, 'nb_reponses' => 0, 'nb_participants' => 1,
        'demo' => (int) $a['demo'],
    ]);

    // Le premier message pointe vers l'article ; il ne le recopie pas. Un
    // texte publie a deux endroits diverge des la premiere correction.
    $corps = t('portail_disc_amorce', ['titre' => $titre]) . "\n\n"
           . '[' . $titre . '](' . (cfg('domaine') ? url('/a/' . $a['slug']) : '/a/' . $a['slug']) . ')';
    ecrire_message($did, $auteur_id, $corps);

    maj('articles', $id, ['discussion_id' => $did]);
    q('UPDATE forums SET nb_discussions = nb_discussions + 1 WHERE id = ?', [(int) $forum['id']]);
    indexer_discussion($did);
    return $did;
}

/** Sources attachees a un article (table `sources`, partagee avec les projets). */
function sources_de_article(int $id): array
{
    return qtous('SELECT * FROM sources WHERE objet_type = ? AND objet_id = ? ORDER BY id',
                 ['article', $id]);
}

function ajouter_source_article(int $id, string $url, string $titre, string $editeur, int $par): void
{
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) return;   // rien d'autre n'est une source
    insere('sources', [
        'objet_type' => 'article', 'objet_id' => $id,
        'url' => mb_substr($url, 0, 500), 'titre' => mb_substr(trim($titre), 0, 255) ?: null,
        'editeur' => mb_substr(trim($editeur), 0, 150) ?: null,
        'ajoutee_par' => $par, 'cree_le' => maintenant(),
    ]);
}

/* ------------------------------------------------------------------ */
/* Composition de la page d'accueil du portail                          */
/* ------------------------------------------------------------------ */

/**
 * Ce que le portail affiche, calcule en une fois.
 *
 * Chaque bloc peut etre VIDE et chaque bloc vide le dit. Un portail dont
 * les rubriques sont muettes ressemble a un portail casse ; un portail qui
 * ecrit « aucun article dans cette rubrique pour l'instant » ressemble a un
 * portail neuf. La difference tient a une phrase, et elle change tout pour
 * qui decouvre le site.
 */
function composer_portail(): array
{
    $lang = langue();

    // On sert d'abord la langue du lecteur. S'il n'y a rien dans sa langue,
    // on montre ce qui existe plutot qu'une page vide, et la carte porte une
    // etiquette de langue pour que personne ne soit surpris en cliquant.
    $une = articles_publies(['une' => true, 'langue' => $lang, 'limite' => 3]);
    if (!$une) $une = preferer_langue(articles_publies(['une' => true, 'limite' => 3]));

    $exclure = array_column($une, 'id');
    $recents_bruts = articles_publies(['langue' => $lang, 'limite' => 12]);
    if (!$recents_bruts) $recents_bruts = preferer_langue(articles_publies(['limite' => 12]));
    $recents = array_values(array_filter($recents_bruts,
        fn($a) => !in_array($a['id'], $exclure, true)));
    $recents = array_slice($recents, 0, 8);

    $par_rubrique = [];
    foreach (rubriques_toutes() as $r) {
        $par_rubrique[] = [
            'rubrique' => $r,
            'articles' => articles_publies(['rubrique_id' => (int) $r['id'], 'limite' => 3]),
            'total'    => compter_articles(['rubrique_id' => (int) $r['id']]),
        ];
    }

    $discussions = qtous(
        'SELECT d.*, f.slug AS forum_slug, f.titre_fr, f.titre_en, f.titre_ar, u.identifiant
         FROM discussions d
         JOIN forums f ON f.id = d.forum_id
         LEFT JOIN utilisateurs u ON u.id = d.auteur_id
         WHERE d.masquee = 0
         ORDER BY d.dernier_message_le DESC, d.id DESC LIMIT 8');

    $villes = qtous(
        'SELECT v.*, p.slug AS pays_slug, p.nom_fr AS pays_fr, p.nom_en AS pays_en,
                p.nom_ar AS pays_ar,
                (SELECT COUNT(*) FROM forums f WHERE f.ville_id = v.id) AS nb_forums
         FROM villes v JOIN pays p ON p.id = v.pays_id
         ORDER BY nb_forums DESC, v.id LIMIT 8');

    // Chaque chiffre est COMPTE. Aucun n'est ecrit en dur dans la vue.
    $stats = [
        'articles'    => compter_articles(),
        'membres'     => (int) qval('SELECT COUNT(*) FROM utilisateurs WHERE actif = 1 AND banni = 0'),
        'discussions' => (int) qval('SELECT COUNT(*) FROM discussions WHERE masquee = 0'),
        'messages'    => (int) qval('SELECT COUNT(*) FROM messages WHERE masque = 0'),
        'pays'        => (int) qval('SELECT COUNT(*) FROM pays'),
        'villes'      => (int) qval('SELECT COUNT(*) FROM villes'),
        'projets'     => (int) qval('SELECT COUNT(*) FROM projets'),
    ];

    return compact('une', 'recents', 'par_rubrique', 'discussions', 'villes', 'stats');
}

/**
 * Le media de une d'un article, mis en cache pour la duree de la requete.
 *
 * Les cartes du portail affichent toutes une image ; sans ce cache, une page
 * de douze cartes fait douze requetes pour douze lignes de la meme table.
 * On a besoin de la LARGEUR et de la HAUTEUR, pas seulement de l'adresse :
 * une image sans dimensions declarees fait sauter la mise en page pendant le
 * chargement, et ce saut se compte dans les Core Web Vitals (section 10).
 */
function media_une(?int $id): ?array
{
    static $cache = [];
    if (!$id) return null;
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = qun('SELECT id, alt, largeur, hauteur FROM medias WHERE id = ?', [$id]);
    }
    return $cache[$id];
}

/** Le nom de la rubrique dans la langue courante, depuis une ligne d'article. */
function nom_rubrique_de_article(array $a): string
{
    return champ_langue(['nom_fr' => $a['rub_fr'] ?? '', 'nom_en' => $a['rub_en'] ?? '',
                         'nom_ar' => $a['rub_ar'] ?? '']);
}
