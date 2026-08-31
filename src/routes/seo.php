<?php
/**
 * Sitemap et robots.
 *
 * Le sitemap est DYNAMIQUE (section 10) : continents, pays, villes, forums,
 * discussions. Il n'est emis que si un domaine est configure — un sitemap
 * ne peut contenir que des URL absolues, et un sitemap d'URL relatives est
 * rejete par les moteurs sans le moindre message. Plutot que d'en produire
 * un faux, on repond 503 et on explique.
 */

declare(strict_types=1);

function sitemap(): void
{
    if (!cfg('domaine')) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Le sitemap exige des URL absolues, donc le domaine du site.\n"
           . "Renseignez 'domaine' dans src/config.local.php et rechargez cette adresse.\n";
        return;
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
       . 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    $ecrire = function (string $chemin, ?string $maj = null, string $freq = 'weekly', string $prio = '0.5') {
        echo "  <url>\n    <loc>" . h(url($chemin)) . "</loc>\n";
        if ($maj) echo '    <lastmod>' . h(gmdate('Y-m-d', strtotime($maj . ' UTC'))) . "</lastmod>\n";
        echo "    <changefreq>$freq</changefreq>\n    <priority>$prio</priority>\n";
        foreach (cfg('langues') as $l) {
            $u = url($chemin) . (str_contains($chemin, '?') ? '&' : '?') . 'lang=' . $l;
            echo '    <xhtml:link rel="alternate" hreflang="' . h($l) . '" href="' . h($u) . "\"/>\n";
        }
        echo "  </url>\n";
    };

    $ecrire('/', null, 'daily', '1.0');
    $ecrire('/actualites', null, 'daily', '0.9');
    $ecrire('/communaute', null, 'daily', '0.8');
    $ecrire('/forums', null, 'daily', '0.9');
    $ecrire('/villes', null, 'weekly', '0.7');

    foreach (qtous('SELECT slug FROM rubriques ORDER BY rang, id') as $r) {
        $ecrire('/r/' . $r['slug'], null, 'daily', '0.7');
    }
    // Meme regle que pour les discussions masquees : seuls les articles
    // REELLEMENT en ligne sont listes. Un article programme pour vendredi
    // repondrait 404 aujourd'hui, et un sitemap qui pointe vers des 404 fait
    // baisser la confiance accordee au fichier entier.
    foreach (qtous('SELECT slug, publie_le, maj_le FROM articles
                    WHERE statut = ? AND publie_le IS NOT NULL AND publie_le <= ?
                    ORDER BY publie_le DESC LIMIT 20000',
                   ['publie', maintenant()]) as $r) {
        $ecrire('/a/' . $r['slug'], $r['maj_le'] ?: $r['publie_le'], 'monthly', '0.8');
    }

    foreach (qtous('SELECT slug FROM continents ORDER BY rang') as $r) $ecrire('/continent/' . $r['slug']);
    foreach (qtous('SELECT slug FROM pays ORDER BY id') as $r)          $ecrire('/pays/' . $r['slug']);
    foreach (qtous('SELECT slug FROM villes ORDER BY id') as $r)        $ecrire('/v/' . $r['slug'], null, 'weekly', '0.6');
    foreach (qtous('SELECT slug FROM forums ORDER BY rang, id') as $r)  $ecrire('/f/' . $r['slug'], null, 'daily', '0.7');

    // Les discussions masquees ne sont pas listees : un sitemap qui pointe
    // vers des 404 fait baisser la confiance accordee au fichier entier.
    foreach (qtous('SELECT slug, dernier_message_le FROM discussions
                    WHERE masquee = 0 ORDER BY dernier_message_le DESC LIMIT 40000') as $r) {
        $ecrire('/d/' . $r['slug'], $r['dernier_message_le'], 'weekly', '0.6');
    }
    echo "</urlset>\n";
}

function robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $lignes = ["User-agent: *"];
    // Les pages profondes qui n'apportent rien a un moteur et fabriquent
    // des URL a l'infini (section 10 : « indexation controlee »).
    foreach (['/recherche', '/connexion', '/inscription', '/parametres', '/notifications',
              '/signets', '/moderation', '/admin', '/api/', '/signaler', '/modifier/',
              '/historique/', '/nouvelle-discussion'] as $d) {
        $lignes[] = 'Disallow: ' . $d;
    }
    $lignes[] = 'Allow: /';
    if (cfg('domaine')) {
        $lignes[] = '';
        $lignes[] = 'Sitemap: ' . url('/sitemap.xml');
        $lignes[] = '# Flux du portail : ' . url('/flux.xml');
    } else {
        $lignes[] = '';
        $lignes[] = '# Sitemap absent : le domaine du site n\'est pas encore renseigne.';
    }
    echo implode("\n", $lignes) . "\n";
}
