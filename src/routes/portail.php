<?php
/**
 * Controleurs du portail.
 *
 * Toutes les pages de lecture sont accessibles SANS COMPTE (critere de
 * recette n°1). L'ecriture passe par les permissions portail.* declarees
 * dans src/routes/table.php — aucun controleur d'ici ne verifie un role.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Lecture publique                                                     */
/* ------------------------------------------------------------------ */

function page_portail(): void
{
    $p = composer_portail();
    meta([
        'titre' => cfg('nom_site'),
        'description' => t('portail_intro'),
        'canonique' => '/',
    ]);
    rendre('portail', $p);
}

function page_actualites(): void
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = 12;
    $langue_filtre = in_array($_GET['langue'] ?? '', cfg('langues'), true) ? $_GET['langue'] : null;
    $opts = ['limite' => $pp, 'depart' => ($page - 1) * $pp];
    if ($langue_filtre) $opts['langue'] = $langue_filtre;

    $articles = articles_publies($opts);
    $total = compter_articles($langue_filtre ? ['langue' => $langue_filtre] : []);

    meta([
        'titre' => t('portail_actualites'),
        'description' => t('portail_intro'),
        'canonique' => '/actualites',
    ]);
    rendre('actualites', compact('articles', 'total', 'page', 'pp', 'langue_filtre'));
}

function page_rubrique(string $slug): void
{
    $r = rubrique_par_slug($slug);
    if (!$r) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = 12;
    $articles = articles_publies(['rubrique_id' => (int) $r['id'], 'limite' => $pp,
                                  'depart' => ($page - 1) * $pp]);
    $total = compter_articles(['rubrique_id' => (int) $r['id']]);

    meta([
        'titre' => champ_langue($r),
        'description' => extrait(champ_langue($r, 'description') ?: t('portail_intro')),
        'canonique' => '/r/' . $r['slug'],
    ]);
    rendre('rubrique', compact('r', 'articles', 'total', 'page', 'pp'));
}

function page_article(string $slug): void
{
    // Un redacteur peut ouvrir son brouillon a la meme adresse. La page
    // porte alors un bandeau et un noindex : c'est un apercu, pas une
    // publication deguisee.
    $apercu = peut('portail.rediger');
    $a = article_par_slug($slug, $apercu);
    if (!$a) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $publie = $a['statut'] === 'publie' && !empty($a['publie_le'])
              && strtotime($a['publie_le'] . ' UTC') <= time();
    if ($publie) compter_vue_article((int) $a['id']);

    $sources = sources_de_article((int) $a['id']);
    $trad = traductions_de($a['groupe'] ?? null, (int) $a['id']);
    $lies = articles_publies([
        'rubrique_id' => (int) ($a['rubrique_id'] ?: 0),
        'exclure' => (int) $a['id'], 'limite' => 3,
    ]);
    $discussion = null;
    if (!empty($a['discussion_id'])) {
        $discussion = qun('SELECT * FROM discussions WHERE id = ? AND masquee = 0',
                          [(int) $a['discussion_id']]);
    }
    $media = !empty($a['media_une_id'])
        ? qun('SELECT * FROM medias WHERE id = ?', [(int) $a['media_une_id']]) : null;

    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $a['titre'],
        'inLanguage' => $a['langue'],
        'datePublished' => $a['publie_le'] ? str_replace(' ', 'T', $a['publie_le']) . 'Z' : null,
        'dateModified' => $a['maj_le'] ? str_replace(' ', 'T', $a['maj_le']) . 'Z' : null,
    ];
    // L'auteur n'est ecrit que s'il y en a un. Un « author » vide dans le
    // JSON-LD est une donnee structuree fausse, pas une donnee absente.
    $signature = $a['signature'] ?: ($a['auteur'] ?? '');
    if ($signature) $ld['author'] = ['@type' => 'Person', 'name' => $signature];
    if (cfg('domaine')) $ld['mainEntityOfPage'] = url('/a/' . $a['slug']);
    $ld = array_filter($ld, fn($v) => $v !== null && $v !== '');

    meta([
        'titre' => $a['titre'],
        'description' => extrait((string) ($a['chapeau'] ?: $a['corps']), 200),
        'canonique' => '/a/' . $a['slug'],
        'og_type' => 'article',
        'noindex' => !$publie,
        'ld' => $ld,
    ]);
    rendre('article', compact('a', 'publie', 'sources', 'trad', 'lies', 'discussion', 'media'));
}

/**
 * Flux RSS.
 *
 * Meme regle que le sitemap : un flux ne contient que des URL absolues.
 * Sans domaine configure il repond 503 et explique, au lieu d'emettre un
 * fichier que tous les lecteurs de flux rejetteront en silence.
 */
function flux_rss(): void
{
    if (!cfg('domaine')) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Un flux RSS ne contient que des adresses absolues, donc le domaine du site.\n"
           . "Renseignez 'domaine' dans src/config.local.php et rechargez cette adresse.\n";
        return;
    }
    $lang = in_array($_GET['lang'] ?? '', cfg('langues'), true) ? $_GET['lang'] : null;
    $articles = articles_publies($lang ? ['langue' => $lang, 'limite' => 30] : ['limite' => 30]);

    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n";
    echo '<title>' . h(cfg('nom_site')) . "</title>\n";
    echo '<link>' . h(url('/')) . "</link>\n";
    echo '<description>' . h(t('portail_intro')) . "</description>\n";
    echo '<atom:link rel="self" type="application/rss+xml" href="' . h(url('/flux.xml')) . "\"/>\n";
    foreach ($articles as $a) {
        echo "<item>\n";
        echo '  <title>' . h($a['titre']) . "</title>\n";
        echo '  <link>' . h(url('/a/' . $a['slug'])) . "</link>\n";
        echo '  <guid isPermaLink="true">' . h(url('/a/' . $a['slug'])) . "</guid>\n";
        echo '  <pubDate>' . h(gmdate('D, d M Y H:i:s', strtotime($a['publie_le'] . ' UTC'))) . " GMT</pubDate>\n";
        echo '  <description>' . h((string) $a['chapeau']) . "</description>\n";
        echo "</item>\n";
    }
    echo "</channel></rss>\n";
}

/** POST /a/discussion — ouvre la discussion attachee a un article. */
function post_article_discussion(): void
{
    $id = (int) ($_POST['article'] ?? 0);
    $a = qun('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$a) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }
    $u = utilisateur();
    $did = discussion_de_article($id, (int) $u['id']);
    if (!$did) { redirige('/a/' . $a['slug']); }
    $slug = qval('SELECT slug FROM discussions WHERE id = ?', [$did]);
    redirige('/d/' . $slug);
}

/* ------------------------------------------------------------------ */
/* Redaction                                                            */
/* ------------------------------------------------------------------ */

function page_articles_admin(): void
{
    $etat = in_array($_GET['etat'] ?? '', ARTICLE_STATUTS, true) ? $_GET['etat'] : null;
    $params = [];
    $where = '1 = 1';
    if ($etat) { $where .= ' AND a.statut = ?'; $params[] = $etat; }

    $articles = qtous(
        "SELECT a.*, u.identifiant AS auteur, r.slug AS rubrique_slug,
                r.nom_fr AS rub_fr, r.nom_en AS rub_en, r.nom_ar AS rub_ar
         FROM articles a
         LEFT JOIN utilisateurs u ON u.id = a.auteur_id
         LEFT JOIN rubriques r ON r.id = a.rubrique_id
         WHERE $where
         ORDER BY a.maj_le DESC, a.id DESC LIMIT 200", $params);

    $comptes = [];
    foreach (ARTICLE_STATUTS as $s) {
        $comptes[$s] = (int) qval('SELECT COUNT(*) FROM articles WHERE statut = ?', [$s]);
    }
    // Programmes : publies mais dans le futur. Ils meritent leur propre
    // ligne, sinon ils se confondent avec ce qui est en ligne.
    $comptes['programme'] = (int) qval(
        'SELECT COUNT(*) FROM articles WHERE statut = ? AND publie_le > ?',
        ['publie', maintenant()]);

    meta(['titre' => t('portail_gestion'), 'noindex' => true]);
    rendre('admin_articles', compact('articles', 'comptes', 'etat'));
}

function page_article_edition(?string $id = null): void
{
    $a = null;
    if ($id !== null) {
        $a = qun('SELECT * FROM articles WHERE id = ?', [(int) $id]);
        if (!$a) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }
    }
    $rubriques = rubriques_toutes();
    $villes = qtous('SELECT v.id, v.nom_fr, v.nom_en, v.nom_ar, p.nom_fr AS pays_fr,
                            p.nom_en AS pays_en, p.nom_ar AS pays_ar
                     FROM villes v JOIN pays p ON p.id = v.pays_id
                     ORDER BY v.nom_fr LIMIT 500');
    $sources = $a ? sources_de_article((int) $a['id']) : [];

    meta(['titre' => $a ? t('portail_modifier') : t('portail_nouvel_article'), 'noindex' => true]);
    rendre('article_edition', compact('a', 'rubriques', 'villes', 'sources'));
}

function post_article_edition(): void
{
    $u = utilisateur();
    $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;

    // Publier est une permission SEPAREE de rediger. Un redacteur sans
    // portail.publier voit le bouton disparaitre, mais surtout : meme s'il
    // forge le champ, la valeur est ramenee a « brouillon » ici. L'interface
    // cache, le serveur refuse — et c'est le serveur qui compte.
    $statut = $_POST['statut'] ?? 'brouillon';
    if (in_array($statut, ['publie', 'retire'], true) && !peut('portail.publier')) {
        $statut = 'brouillon';
    }
    $une = !empty($_POST['une']) && peut('portail.une');

    try {
        $id = enregistrer_article($id, [
            'titre'        => $_POST['titre'] ?? '',
            'chapeau'      => $_POST['chapeau'] ?? '',
            'corps'        => $_POST['corps'] ?? '',
            'langue'       => $_POST['langue'] ?? langue(),
            'rubrique_id'  => $_POST['rubrique_id'] ?? null,
            'ville_id'     => $_POST['ville_id'] ?? null,
            'signature'    => $_POST['signature'] ?? '',
            'statut'       => $statut,
            'une'          => $une,
            'rang_une'     => $_POST['rang_une'] ?? 100,
            'publie_le'    => $_POST['publie_le'] ?? '',
            'groupe'       => $_POST['groupe'] ?? '',
        ], (int) $u['id']);
    } catch (InvalidArgumentException) {
        // Un article sans titre n'est pas enregistrable : le titre est le
        // slug, le fil d'Ariane et le <title>. On renvoie le formulaire avec
        // le message plutot que d'ecrire une ligne « (sans titre) » en base.
        http_response_code(422);
        $rubriques = rubriques_toutes();
        $villes = qtous('SELECT v.id, v.nom_fr, v.nom_en, v.nom_ar, p.nom_fr AS pays_fr,
                                p.nom_en AS pays_en, p.nom_ar AS pays_ar
                         FROM villes v JOIN pays p ON p.id = v.pays_id
                         ORDER BY v.nom_fr LIMIT 500');
        meta(['titre' => t('portail_nouvel_article'), 'noindex' => true]);
        rendre('article_edition', ['a' => null, 'rubriques' => $rubriques,
                                   'villes' => $villes, 'sources' => [],
                                   'erreur_saisie' => t('portail_titre_obligatoire')]);
        return;
    }

    $url = trim((string) ($_POST['source_url'] ?? ''));
    if ($url !== '') {
        ajouter_source_article($id, $url, (string) ($_POST['source_titre'] ?? ''),
                               (string) ($_POST['source_editeur'] ?? ''), (int) $u['id']);
    }
    redirige('/admin/articles/' . $id);
}

function post_article_supprimer_source(): void
{
    $sid = (int) ($_POST['source'] ?? 0);
    $s = qun('SELECT * FROM sources WHERE id = ? AND objet_type = ?', [$sid, 'article']);
    if ($s) {
        q('DELETE FROM sources WHERE id = ?', [$sid]);
        audit('article.source_retiree', 'article#' . $s['objet_id'], ['source' => $sid]);
    }
    redirige('/admin/articles/' . (int) ($_POST['article'] ?? 0));
}
