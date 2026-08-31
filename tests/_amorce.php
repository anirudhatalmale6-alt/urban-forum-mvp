<?php
/** Amorce commune aux scripts de controle cote PHP. */
declare(strict_types=1);
$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/messages.php';
require $racine . '/src/recherche.php';
require $racine . '/src/notifications.php';
require $racine . '/src/moderation.php';
require $racine . '/src/medias.php';
require $racine . '/src/vue.php';

function en_tant_que(string $pseudo): array
{
    $u = qun('SELECT * FROM utilisateurs WHERE identifiant = ?', [$pseudo]);
    if (!$u) { fwrite(STDERR, "utilisateur inconnu : $pseudo\n"); exit(1); }
    $GLOBALS['uf_utilisateur_force'] = $u;
    return $u;
}
function sortir(array $donnees): never
{
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
}
