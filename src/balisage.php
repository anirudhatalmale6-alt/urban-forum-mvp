<?php
/**
 * L'editeur : une syntaxe simple, rendue en HTML sur.
 *
 * PRINCIPE, et il n'y a pas de variante : on N'ACCEPTE JAMAIS de HTML de
 * l'utilisateur. Le corps est d'abord echappe en entier, puis on RE-INJECTE
 * un petit nombre de balises que l'on ecrit soi-meme. On ne nettoie donc
 * jamais un HTML hostile — on n'en recoit pas. C'est la seule facon de ne
 * pas dependre d'une liste noire toujours incomplete.
 *
 * Syntaxe :
 *   **gras**      *italique*      `code`
 *   > citation                    - liste
 *   [texte](https://…)            !img:12          (media televerse)
 *   @pseudo                       [cite=pseudo#42]…[/cite]
 *   video:https://…               (facade cliquable, voir plus bas)
 */

declare(strict_types=1);

/** Hotes video acceptes. Rien d'autre n'est transforme en lecteur. */
const VIDEO_HOTES = [
    'youtube.com' => 'youtube', 'www.youtube.com' => 'youtube',
    'youtu.be' => 'youtube', 'm.youtube.com' => 'youtube',
    'vimeo.com' => 'vimeo', 'www.vimeo.com' => 'vimeo',
    'dailymotion.com' => 'dailymotion', 'www.dailymotion.com' => 'dailymotion',
];

function rendre_message(string $corps, array $opts = []): string
{
    // 1. Tout est echappe. A partir d'ici il n'existe plus de < ni de " dans
    //    le texte, donc aucune des expressions suivantes ne peut fabriquer
    //    une balise a partir de ce que l'utilisateur a tape.
    $s = h($corps);
    $s = str_replace("\r\n", "\n", $s);

    // 2. Citations imbriquables : [cite=pseudo#42]…[/cite]
    $s = preg_replace_callback(
        '/\[cite=([\p{L}\p{N}_.-]{1,30})(?:#(\d+))?\]\n?(.*?)\[\/cite\]/us',
        function ($m) {
            $lien = $m[2] !== '' ? '<a href="/m/' . (int) $m[2] . '">#' . (int) $m[2] . '</a>' : '';
            return '<blockquote class="cite"><cite>' . $m[1] . ' ' . $lien . '</cite>'
                 . '<div>' . trim($m[3]) . '</div></blockquote>';
        }, $s) ?? $s;

    // 3. Images televersees. On ne prend QUE nos propres identifiants de
    //    media : pas d'URL distante, donc pas de fuite d'IP des lecteurs
    //    vers un serveur tiers, et pas de « pixel espion » dans un message.
    $s = preg_replace_callback('/!img:(\d+)/', function ($m) {
        $id = (int) $m[1];
        $md = qun('SELECT * FROM medias WHERE id = ?', [$id]);
        if (!$md) return '';
        $alt = h((string) ($md['alt'] ?? ''));
        $w = (int) $md['largeur']; $hh = (int) $md['hauteur'];
        // width/height ecrits sur la balise : sans eux la page saute pendant
        // le chargement, et le decalage compte dans les Core Web Vitals.
        $dim = ($w && $hh) ? ' width="' . $w . '" height="' . $hh . '"' : '';
        return '<img class="msg-img" src="/media/' . $id . '" alt="' . $alt . '"'
             . $dim . ' loading="lazy" decoding="async">';
    }, $s) ?? $s;

    // 4. Video : facade cliquable, pas d'iframe tierce chargee d'office.
    $s = preg_replace_callback('#video:(https?://[^\s<]+)#i', function ($m) {
        $u = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $p = parse_url($u);
        $hote = strtolower($p['host'] ?? '');
        if (!isset(VIDEO_HOTES[$hote])) return h($u);
        return '<span class="video-facade" data-video="' . h($u) . '">'
             . '<a href="' . h($u) . '" rel="nofollow noopener ugc" target="_blank">'
             . h($u) . '</a></span>';
    }, $s) ?? $s;

    // 5. Liens [texte](url) puis URLs nues. Schemas http/https uniquement :
    //    javascript: et data: ne franchissent pas ce filtre.
    $s = preg_replace_callback('/\[([^\]\n]{1,120})\]\((https?:\/\/[^\s)]{1,500})\)/i',
        fn($m) => lien_html($m[2], $m[1]), $s) ?? $s;
    // Liens INTERNES en chemin absolu : [texte](/a/mon-article). Sans cette
    // regle, un lien vers une page du site reste du texte brut tant que
    // cfg('domaine') est vide — c'est-a-dire pendant toute l'installation.
    // Le motif exige une seule barre suivie d'autre chose qu'une barre :
    // « //evil.example » est une URL absolue de protocole relatif, pas un
    // chemin interne, et il ne doit pas franchir ce filtre.
    $s = preg_replace_callback('#\[([^\]\n]{1,120})\]\((/[^/\s)][^\s)]{0,200})\)#',
        fn($m) => '<a href="' . h(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')) . '">'
                . $m[1] . '</a>', $s) ?? $s;
    $s = preg_replace_callback('#(?<![">=])\b(https?://[^\s<]{4,500})#i',
        fn($m) => lien_html($m[1], $m[1]), $s) ?? $s;

    // 6. Mentions @pseudo — liees seulement si le compte existe.
    $s = preg_replace_callback('/(^|[\s(])@([\p{L}\p{N}_.-]{3,30})/u', function ($m) {
        $existe = qval('SELECT id FROM utilisateurs WHERE identifiant = ?', [$m[2]]);
        if ($existe === null) return $m[0];
        return $m[1] . '<a class="mention" href="/u/' . rawurlencode($m[2]) . '">@' . $m[2] . '</a>';
    }, $s) ?? $s;

    // 7. Emphase et code.
    $s = preg_replace('/`([^`\n]{1,200})`/', '<code>$1</code>', $s) ?? $s;
    $s = preg_replace('/\*\*([^*\n]{1,300})\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\*\w])\*([^*\n]{1,300})\*(?!\w)/', '<em>$1</em>', $s) ?? $s;

    // 8. Blocs ligne a ligne : citation simple, listes, paragraphes.
    return blocs($s);
}

function lien_html(string $url, string $texte): string
{
    $u = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    if (!preg_match('#^https?://#i', $u)) return h($texte);
    $interne = false;
    $dom = cfg('domaine');
    if ($dom) {
        $interne = str_starts_with($u, rtrim($dom, '/'));
    }
    // rel="ugc nofollow" sur les liens sortants : c'est du contenu ecrit par
    // des membres, on ne transmet pas la reputation du domaine.
    $rel = $interne ? '' : ' rel="nofollow noopener ugc" target="_blank"';
    return '<a href="' . h($u) . '"' . $rel . '>' . $texte . '</a>';
}

function blocs(string $s): string
{
    $out = [];
    $liste = false; $cit = false; $para = [];

    $ferme_para = function () use (&$para, &$out) {
        if ($para) { $out[] = '<p>' . implode('<br>', $para) . '</p>'; $para = []; }
    };
    $ferme_liste = function () use (&$liste, &$out) {
        if ($liste) { $out[] = '</ul>'; $liste = false; }
    };
    $ferme_cit = function () use (&$cit, &$out) {
        if ($cit) { $out[] = '</blockquote>'; $cit = false; }
    };

    foreach (explode("\n", $s) as $ligne) {
        $l = rtrim($ligne);
        if (trim($l) === '') { $ferme_para(); $ferme_liste(); $ferme_cit(); continue; }

        if (preg_match('/^\s*&gt;\s?(.*)$/', $l, $m)) {
            $ferme_para(); $ferme_liste();
            if (!$cit) { $out[] = '<blockquote>'; $cit = true; }
            $out[] = '<p>' . $m[1] . '</p>';
            continue;
        }
        $ferme_cit();

        if (preg_match('/^\s*[-*]\s+(.*)$/', $l, $m)) {
            $ferme_para();
            if (!$liste) { $out[] = '<ul>'; $liste = true; }
            $out[] = '<li>' . $m[1] . '</li>';
            continue;
        }
        $ferme_liste();

        // Une ligne qui n'est QUE un bloc deja fabrique (citation, image,
        // video) ne doit pas etre enfermee dans un <p> : un <blockquote>
        // dans un <p> est invalide et le navigateur le sort du paragraphe,
        // ce qui casse la mise en page sans aucun message d'erreur.
        if (preg_match('#^\s*<(blockquote|img|span class="video-facade")#', $l)) {
            $ferme_para();
            $out[] = $l;
            continue;
        }
        $para[] = $l;
    }
    $ferme_para(); $ferme_liste(); $ferme_cit();
    return implode("\n", $out);
}

/** Les pseudos mentionnes dans un corps, pour les notifications. */
function mentions_du_corps(string $corps): array
{
    preg_match_all('/(?:^|[\s(])@([\p{L}\p{N}_.-]{3,30})/u', $corps, $m);
    return array_values(array_unique($m[1] ?? []));
}

/** Extrait sans balise, pour les listes, les meta et l'index de recherche. */
function extrait(string $corps, int $n = 180): string
{
    $t = preg_replace('/\[cite=[^\]]*\](.*?)\[\/cite\]/us', ' ', $corps) ?? $corps;
    $t = preg_replace('/!img:\d+|video:\S+|https?:\/\/\S+/u', ' ', $t) ?? $t;
    $t = preg_replace('/[*`>#\-\[\]()]/u', ' ', $t) ?? $t;
    $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
}
