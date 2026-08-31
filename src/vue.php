<?php
/**
 * Couche de presentation : gabarit, fil d'Ariane, pagination, meta SEO.
 * Les vues ne parlent jamais a la base directement ; elles recoivent un
 * tableau. C'est ce qui rend l'API JSON possible sans dupliquer la logique.
 */

declare(strict_types=1);

function url(string $chemin = '/'): string
{
    $base = rtrim((string) cfg('domaine'), '/');
    return $base . $chemin;
}

/** Conserve la langue choisie en la propageant dans les liens internes. */
function lien(string $chemin): string
{
    if (langue() === cfg('langue_defaut')) return $chemin;
    return $chemin . (str_contains($chemin, '?') ? '&' : '?') . 'lang=' . langue();
}

function redirige(string $chemin): never
{
    header('Location: ' . lien($chemin), true, 303);
    exit;
}

$GLOBALS['uf_meta'] = [];

function meta(array $m): void
{
    $GLOBALS['uf_meta'] = array_merge($GLOBALS['uf_meta'], $m);
}

function rendre(string $vue, array $donnees = []): void
{
    $donnees['vue'] = $vue;
    extract($donnees, EXTR_SKIP);
    $contenu_vue = __DIR__ . '/vues/' . $vue . '.php';
    if (!is_file($contenu_vue)) {
        journal('erreur', 'vue introuvable', ['vue' => $vue]);
        http_response_code(500);
        echo 'vue introuvable';
        return;
    }
    ob_start();
    include $contenu_vue;
    $corps_page = ob_get_clean();
    include __DIR__ . '/vues/gabarit.php';
}

/** Fil d'Ariane + le JSON-LD BreadcrumbList qui va avec. */
function fil(array $items): string
{
    $html = '<nav class="fil" aria-label="' . h(t('geo_monde')) . '"><ol>';
    $ld = [];
    $i = 1;
    foreach ($items as $it) {
        $nom = (string) $it[0];
        $u   = $it[1] ?? null;
        $html .= '<li>' . ($u ? '<a href="' . h(lien($u)) . '">' . h($nom) . '</a>' : '<span aria-current="page">' . h($nom) . '</span>') . '</li>';
        $e = ['@type' => 'ListItem', 'position' => $i++, 'name' => $nom];
        if ($u && cfg('domaine')) $e['item'] = url($u);
        $ld[] = $e;
    }
    $html .= '</ol></nav>';
    $GLOBALS['uf_ld_fil'] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
                             'itemListElement' => $ld];
    return $html;
}

function pagination(int $page, int $total, int $par_page, string $base): string
{
    $pages = max(1, (int) ceil($total / max(1, $par_page)));
    if ($pages <= 1) return '';
    $out = '<nav class="pagination" aria-label="' . h(t('page')) . '">';
    $lien_page = function (int $p) use ($base) {
        $sep = str_contains($base, '?') ? '&' : '?';
        return h(lien($p <= 1 ? $base : $base . $sep . 'page=' . $p));
    };
    if ($page > 1) $out .= '<a rel="prev" href="' . $lien_page($page - 1) . '">' . h(t('precedent')) . '</a>';
    $debut = max(1, $page - 2); $fin = min($pages, $page + 2);
    if ($debut > 1) $out .= '<a href="' . $lien_page(1) . '">1</a><span class="ellipse">…</span>';
    for ($p = $debut; $p <= $fin; $p++) {
        $out .= $p === $page
            ? '<span class="courante" aria-current="page">' . $p . '</span>'
            : '<a href="' . $lien_page($p) . '">' . $p . '</a>';
    }
    if ($fin < $pages) $out .= '<span class="ellipse">…</span><a href="' . $lien_page($pages) . '">' . $pages . '</a>';
    if ($page < $pages) $out .= '<a rel="next" href="' . $lien_page($page + 1) . '">' . h(t('suivant')) . '</a>';
    return $out . '</nav>';
}

/**
 * Avatar : initiale sur un fond derive du pseudo. Pas d'image distante.
 *
 * La teinte passe par une CLASSE, pas par un attribut style. La politique de
 * securite du site interdit le style en ligne (style-src 'self'), et un
 * `style="--h:210"` est SILENCIEUSEMENT bloque par le navigateur : la page
 * s'affiche, l'avatar n'a simplement plus de couleur, et rien n'apparait
 * dans les journaux du serveur. Douze teintes fixes suffisent a distinguer
 * les membres a l'oeil.
 */
function avatar(string $pseudo, int $taille = 36): string
{
    $seau = (int) (hexdec(substr(md5($pseudo), 0, 4)) % 12);
    $t = $taille >= 64 ? 'xl' : ($taille >= 48 ? 'l' : ($taille >= 34 ? 'm' : 's'));
    return '<span class="avatar avatar--h' . $seau . ' avatar--' . $t . '" aria-hidden="true">'
         . h(mb_strtoupper(mb_substr($pseudo, 0, 1))) . '</span>';
}

function badge_role(string $cle): string
{
    return '<span class="badge-role badge-' . h($cle) . '">' . h(t('role_' . $cle)) . '</span>';
}
