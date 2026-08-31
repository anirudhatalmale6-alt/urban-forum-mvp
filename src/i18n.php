<?php
/**
 * Internationalisation FR / EN / AR, avec RTL reel.
 *
 * Un seul dictionnaire par langue, meme jeu de cles dans les trois. Un test
 * compare les cles : une cle presente en francais et absente en arabe est
 * une erreur, pas un repli silencieux. Sans ce test, une traduction
 * manquante s'affiche en francais au milieu d'une page arabe et personne ne
 * le voit avant un utilisateur.
 */

declare(strict_types=1);

function langues_dispo(): array { return cfg('langues'); }

function langue(): string
{
    static $l = null;
    if ($l !== null) return $l;

    $dispo = langues_dispo();

    // 1. le choix explicite dans l'URL      2. le cookie      3. le profil
    // 4. l'en-tete du navigateur            5. la langue par defaut
    $c = $_GET['lang'] ?? $_COOKIE['uf_lang'] ?? null;
    if (!in_array($c, $dispo, true)) {
        $u = utilisateur();
        $c = ($u && in_array($u['langue'] ?? '', $dispo, true)) ? $u['langue'] : null;
    }
    if (!$c) {
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $morceau) {
            $code = strtolower(substr(trim(explode(';', $morceau)[0]), 0, 2));
            if (in_array($code, $dispo, true)) { $c = $code; break; }
        }
    }
    $l = in_array($c, $dispo, true) ? $c : cfg('langue_defaut');
    return $l;
}

function rtl(): bool { return langue() === 'ar'; }
function direction(): string { return rtl() ? 'rtl' : 'ltr'; }

function dico(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?: langue();
    if (!isset($cache[$lang])) {
        $f = __DIR__ . '/lang/' . $lang . '.php';
        $cache[$lang] = is_file($f) ? require $f : [];
    }
    return $cache[$lang];
}

/**
 * t('cle') ou t('cle', ['n' => 3]).
 * Une cle inconnue renvoie la cle entre chevrons : visible a l'ecran et
 * attrapable par un test. Elle ne renvoie pas une chaine vide, qui
 * ressemblerait a un choix de design.
 */
function t(string $cle, array $vars = []): string
{
    $d = dico();
    $s = $d[$cle] ?? null;
    if ($s === null) {
        $s = dico(cfg('langue_defaut'))[$cle] ?? null;
        if ($s === null) return '«' . $cle . '»';
    }
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

/**
 * Forme du pluriel : 'un' ou 'autre'.
 *
 * Le francais met au singulier a ZERO comme a un (« 0 article »), l'anglais
 * non (« 0 articles »). Sans cette distinction, une des deux langues ecrit
 * une faute a chaque compteur de la page.
 *
 * L'arabe a six formes (zero, un, deux, few, many, other). On n'en gere ici
 * que deux : c'est une SIMPLIFICATION assumee, pas un oubli. Le jour ou les
 * compteurs comptent vraiment, il faudra les six — et il faudra un
 * relecteur arabophone pour les ecrire, ce que je ne suis pas.
 */
function pluriel(int $n): string
{
    return match (langue()) {
        'fr'    => $n <= 1 ? 'un' : 'autre',
        default => $n === 1 ? 'un' : 'autre',
    };
}

/** t() avec choix automatique de la forme : tn('portail_n_articles', 3). */
function tn(string $cle, int $n, array $vars = []): string
{
    return t($cle . '_' . pluriel($n), $vars + ['n' => nombre($n)]);
}

/** Choisit le champ traduit d'une ligne : nom_fr / nom_en / nom_ar. */
function champ_langue(array $ligne, string $prefixe = 'nom'): string
{
    $l = langue();
    foreach ([$l, cfg('langue_defaut'), 'en', 'fr'] as $c) {
        $k = $prefixe . '_' . $c;
        if (!empty($ligne[$k])) return (string) $ligne[$k];
    }
    return '';
}

/** Nombres : espace insecable fine en francais, virgule arabe en arabe. */
function nombre($n): string
{
    $n = (int) $n;
    return match (langue()) {
        'fr' => number_format($n, 0, ',', "\u{202F}"),
        'ar' => number_format($n, 0, '.', "\u{066C}"),
        default => number_format($n, 0, '.', ','),
    };
}

/**
 * Date lisible.
 *
 * Les noms de mois sont ECRITS ICI, par langue. gmdate() ne connait que
 * l'anglais : sans cette table, une page francaise affiche « 10 Aug 2026 »
 * et une page arabe aussi. C'est le genre de detail qui ne casse rien, ne
 * remonte nulle part, et qu'un lecteur voit immediatement.
 */
const MOIS = [
    'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août',
             'sept.', 'oct.', 'nov.', 'déc.'],
    'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
             'Sep', 'Oct', 'Nov', 'Dec'],
    'ar' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس',
             'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
];

function date_lisible(?string $ts): string
{
    if (!$ts) return '';
    $t = strtotime($ts . ' UTC');
    $l = langue();
    $mois = MOIS[$l] ?? MOIS['en'];
    $m = $mois[(int) gmdate('n', $t) - 1];
    $j = (int) gmdate('j', $t);
    $a = gmdate('Y', $t);
    $hm = gmdate('H:i', $t);
    return match ($l) {
        'en' => "$m $j, $a $hm",
        default => "$j $m $a $hm",
    };
}

function il_y_a(?string $ts): string
{
    if (!$ts) return '';
    $d = time() - strtotime($ts . ' UTC');
    if ($d < 60)     return t('a_l_instant');
    if ($d < 3600)   return t('il_y_a_min', ['n' => (int) ($d / 60)]);
    if ($d < 86400)  return t('il_y_a_h',   ['n' => (int) ($d / 3600)]);
    if ($d < 2592000) return t('il_y_a_j',  ['n' => (int) ($d / 86400)]);
    return date_lisible($ts);
}
