<?php
/**
 * Controleur frontal. Une seule porte d'entree pour les pages ET pour
 * l'API : c'est ce qui garantit qu'une permission verifiee une fois vaut
 * des deux cotes (critere de recette n°8).
 */

declare(strict_types=1);

/* Serveur de developpement PHP uniquement : quand ce fichier sert de script
   de routage, il doit rendre `false` pour les fichiers reellement presents,
   sinon la feuille de style et le script sont routes ici et repondent 404.
   En production c'est .htaccess qui fait ce tri, et ce bloc ne s'execute
   jamais — php_sapi_name() vaut « apache2handler » ou « fpm-fcgi ». */
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($f) && realpath($f) !== __FILE__) {
        if (str_ends_with($f, '.php')) { require $f; return true; }
        return false;
    }
}

$src = dirname(__DIR__) . '/src';
require $src . '/noyau.php';
require $src . '/i18n.php';
require $src . '/auth.php';
require $src . '/balisage.php';
require $src . '/messages.php';
require $src . '/recherche.php';
require $src . '/notifications.php';
require $src . '/moderation.php';
require $src . '/medias.php';
require $src . '/vue.php';

installe_gestion_erreurs();

/* En-tetes de securite. Ils partent sur CHAQUE reponse, y compris les
   erreurs — une page 500 sert la meme origine que les autres.
   La CSP interdit tout script distant et toute balise <script> en ligne :
   le seul JavaScript de la page vient de /assets/forum.js. */
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
         . "style-src 'self'; script-src 'self'; frame-ancestors 'none'; "
         . "form-action 'self'; base-uri 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('Vary: Accept-Language, Cookie');
}

$chemin = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
if ($chemin === '') $chemin = '/';
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* La base n'est pas installee : on le dit, on ne montre pas une trace. */
try {
    qval('SELECT COUNT(*) FROM roles');
} catch (Throwable $e) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Installation</title>'
       . '<div style="font:16px/1.6 system-ui;max-width:640px;margin:60px auto;padding:0 20px">'
       . '<h1>Base non installee</h1><p>Lancez <code>php outils/installer.php</code> '
       . "puis, pour la demonstration, <code>php outils/semer.php</code>.</p></div>";
    exit;
}

require dirname(__DIR__) . '/src/routes/table.php';

$reponse = router($chemin, $methode);
if ($reponse === false) {
    http_response_code(404);
    if (est_api()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => t('err_404'), 'code' => 404], JSON_UNESCAPED_UNICODE);
    } else {
        meta(['titre' => t('err_404'), 'noindex' => true]);
        rendre('erreur', ['code' => 404, 'message' => t('err_404')]);
    }
}
