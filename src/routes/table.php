<?php
/**
 * Table de routage. Les motifs sont ordonnes du plus specifique au plus
 * general ; le premier qui correspond gagne.
 *
 * Chaque route declare la PERMISSION qu'elle exige. C'est declaratif, donc
 * une route ajoutee sans permission se voit dans ce fichier, alors qu'un
 * `if` oublie au milieu d'un controleur ne se voit nulle part.
 */

declare(strict_types=1);

require __DIR__ . '/public.php';
require __DIR__ . '/portail.php';
require __DIR__ . '/compte.php';
require __DIR__ . '/ecrire.php';
require __DIR__ . '/mod.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/api.php';
require __DIR__ . '/seo.php';

function routes(): array
{
    return [
        // chemin (regex)                methode  fonction              permission
        // La racine sert le PORTAIL. L'ancienne page d'accueil du forum
        // (tendances, monde, villes, chiffres) n'a pas disparu : elle vit a
        // /communaute et reste liee depuis le portail et le pied de page.
        ['#^/$#',                        'GET',  'page_portail',        'forum.lire'],
        ['#^/actualites$#',              'GET',  'page_actualites',     'forum.lire'],
        ['#^/r/([\w\-]+)$#',             'GET',  'page_rubrique',       'forum.lire'],
        ['#^/a/([\w\-]+)$#',             'GET',  'page_article',        'forum.lire'],
        ['#^/portail/discussion$#',      'POST', 'post_article_discussion', 'forum.publier'],
        ['#^/flux\.xml$#',               'GET',  'flux_rss',            'forum.lire'],
        ['#^/communaute$#',              'GET',  'page_accueil',        'forum.lire'],
        ['#^/forums$#',                  'GET',  'page_forums',         'forum.lire'],
        ['#^/f/([\w\-]+)$#',             'GET',  'page_forum',          'forum.lire'],
        ['#^/d/([\w\-]+)$#',             'GET',  'page_discussion',     'forum.lire'],
        ['#^/m/(\d+)$#',                 'GET',  'aller_message',       'forum.lire'],
        ['#^/villes$#',                  'GET',  'page_villes',         'forum.lire'],
        ['#^/v/([\w\-]+)$#',             'GET',  'page_ville',          'forum.lire'],
        ['#^/pays/([\w\-]+)$#',          'GET',  'page_pays',           'forum.lire'],
        ['#^/continent/([\w\-]+)$#',     'GET',  'page_continent',      'forum.lire'],
        ['#^/projets$#',                 'GET',  'page_projets',        'forum.lire'],
        ['#^/recherche$#',               'GET',  'page_recherche',      'forum.lire'],
        ['#^/u/([^/]+)$#',               'GET',  'page_profil',         'forum.lire'],
        ['#^/aide$#',                    'GET',  'page_aide',           'forum.lire'],
        ['#^/a-renseigner$#',            'GET',  'page_a_renseigner',   'forum.lire'],
        ['#^/media/(\d+)$#',             'GET',  'route_media',         'forum.lire'],

        ['#^/inscription$#',             'GET',  'page_inscription',    'forum.lire'],
        ['#^/inscription$#',             'POST', 'post_inscription',    'forum.lire'],
        ['#^/connexion$#',               'GET',  'page_connexion',      'forum.lire'],
        ['#^/connexion$#',               'POST', 'post_connexion',      'forum.lire'],
        ['#^/deconnexion$#',             'POST', 'post_deconnexion',    'forum.lire'],
        ['#^/notifications$#',           'GET',  'page_notifications',  'forum.publier'],
        ['#^/notifications/lues$#',      'POST', 'post_tout_lu',        'forum.publier'],
        ['#^/parametres$#',              'GET',  'page_parametres',     'forum.publier'],
        ['#^/parametres$#',              'POST', 'post_parametres',     'forum.publier'],
        ['#^/signets$#',                 'GET',  'page_signets',        'forum.publier'],

        ['#^/nouvelle-discussion$#',     'GET',  'page_nouvelle',       'forum.publier'],
        ['#^/nouvelle-discussion$#',     'POST', 'post_nouvelle',       'forum.publier'],
        ['#^/repondre$#',                'POST', 'post_repondre',       'forum.publier'],
        ['#^/modifier/(\d+)$#',          'GET',  'page_modifier',       'forum.editer_sien'],
        ['#^/modifier/(\d+)$#',          'POST', 'post_modifier',       'forum.editer_sien'],
        ['#^/historique/(\d+)$#',        'GET',  'page_historique',     'forum.lire'],
        ['#^/reagir$#',                  'POST', 'post_reagir',         'forum.reagir'],
        ['#^/abonnement$#',              'POST', 'post_abonnement',     'forum.reagir'],
        ['#^/signet$#',                  'POST', 'post_signet',         'forum.reagir'],
        ['#^/signaler$#',                'GET',  'page_signaler',       'forum.signaler'],
        ['#^/signaler$#',                'POST', 'post_signaler',       'forum.signaler'],
        ['#^/bloquer$#',                 'POST', 'post_bloquer',        'forum.reagir'],

        ['#^/moderation$#',              'GET',  'page_moderation',     'moderation.file'],
        ['#^/moderation/revue$#',        'POST', 'post_revue',          'moderation.file'],
        ['#^/moderation/action$#',       'POST', 'post_action_mod',     'moderation.contenu'],
        ['#^/moderation/journal$#',      'GET',  'page_journal_mod',    'moderation.file'],

        // Redaction du portail. « nouveau » passe AVANT le motif numerique :
        // les deux ne se recouvrent pas ici, mais l'ordre reste celui du plus
        // specifique au plus general, comme le reste de la table.
        ['#^/admin/articles$#',          'GET',  'page_articles_admin', 'portail.rediger'],
        ['#^/admin/articles/nouveau$#',  'GET',  'page_article_edition','portail.rediger'],
        ['#^/admin/articles/(\d+)$#',    'GET',  'page_article_edition','portail.rediger'],
        ['#^/admin/articles$#',          'POST', 'post_article_edition','portail.rediger'],
        ['#^/admin/articles/source$#',   'POST', 'post_article_supprimer_source', 'portail.rediger'],

        ['#^/admin$#',                   'GET',  'page_admin',          'admin.statistiques'],
        ['#^/admin/taxonomie$#',         'GET',  'page_taxonomie',      'admin.taxonomie'],
        ['#^/admin/taxonomie$#',         'POST', 'post_taxonomie',      'admin.taxonomie'],
        ['#^/admin/permissions$#',       'GET',  'page_permissions',    'admin.utilisateurs'],
        ['#^/admin/roles$#',             'POST', 'post_role',           'admin.utilisateurs'],
        ['#^/admin/journal$#',           'GET',  'page_journal_erreurs','admin.statistiques'],
        ['#^/admin/export\.csv$#',       'GET',  'export_csv',          'admin.statistiques'],
        ['#^/admin/reindexer$#',         'POST', 'post_reindexer',      'admin.configuration'],

        ['#^/api/v1/portail$#',          'GET',  'api_portail',         'forum.lire'],
        ['#^/api/v1/articles$#',         'GET',  'api_articles',        'forum.lire'],
        ['#^/api/v1/articles/([\w\-]+)$#', 'GET', 'api_article',        'forum.lire'],
        ['#^/api/v1/forums$#',           'GET',  'api_forums',          'forum.lire'],
        ['#^/api/v1/forums/([\w\-]+)$#', 'GET',  'api_forum',           'forum.lire'],
        ['#^/api/v1/discussions/([\w\-]+)$#', 'GET', 'api_discussion',  'forum.lire'],
        ['#^/api/v1/recherche$#',        'GET',  'api_recherche',       'forum.lire'],
        ['#^/api/v1/autocomplete$#',     'GET',  'api_autocomplete',    'forum.lire'],
        ['#^/api/v1/apercu$#',           'POST', 'api_apercu',          'forum.publier'],
        ['#^/api/v1/messages$#',         'POST', 'api_message',         'forum.publier'],
        ['#^/api/v1/notifications$#',    'GET',  'api_notifications',   'forum.publier'],
        ['#^/api/v1/moderation/file$#',  'GET',  'api_file_mod',        'moderation.file'],
        ['#^/api/v1$#',                  'GET',  'api_racine',          'forum.lire'],

        ['#^/sitemap\.xml$#',            'GET',  'sitemap',             'forum.lire'],
        ['#^/robots\.txt$#',             'GET',  'robots',              'forum.lire'],
    ];
}

function router(string $chemin, string $methode)
{
    foreach (routes() as [$motif, $verbe, $fn, $perm]) {
        if ($verbe !== $methode) continue;
        if (!preg_match($motif, $chemin, $m)) continue;

        // Toute ecriture verifie le jeton CSRF ici, une fois, avant
        // d'atteindre le controleur. Un controleur ne peut donc pas
        // « oublier » la verification : elle n'est pas de son ressort.
        if ($methode === 'POST') verifie_csrf();

        exige($perm);
        array_shift($m);
        $fn(...$m);
        return true;
    }

    // Le chemin existe-t-il avec un autre verbe ? Alors c'est 405, pas 404.
    foreach (routes() as [$motif, $verbe, , ]) {
        if (preg_match($motif, $chemin)) {
            http_response_code(405);
            header('Allow: ' . $verbe);
            if (est_api()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['erreur' => 'methode non autorisee', 'code' => 405]);
            } else {
                rendre('erreur', ['code' => 405, 'message' => 'Methode non autorisee.']);
            }
            return true;
        }
    }
    return false;
}

function json_out($donnees, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
