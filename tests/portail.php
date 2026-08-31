<?php
/**
 * Aides cote PHP pour les controles du portail.
 *
 *   php tests/portail.php compte
 *       Ce que le portail considere comme visible, mesure sur la BASE et
 *       non sur la page : total d'articles, brouillons, programmes.
 *
 *   php tests/portail.php redacteur <pseudo>
 *       Donne le role « redacteur » a un compte existant. La suite s'inscrit
 *       en membre par HTTP — c'est le seul chemin d'inscription du site — et
 *       il faut ensuite un moyen de lui donner le metier a tester.
 *
 *   php tests/portail.php revoque-publier / rend-publier
 *       Retire puis rend la permission portail.publier au role redacteur.
 *       C'est ce qui permet de verifier que le SERVEUR ramene un article a
 *       « brouillon » quand le compte n'a pas le droit de publier, et pas
 *       seulement que l'interface cache le bouton. Les deux sont differents
 *       et seul le second protege.
 *
 *   php tests/portail.php slugs
 *       Toutes les adresses de la base sont-elles en ASCII ? Un slug qui
 *       contient une lettre non latine n'est jamais reconnu par la table de
 *       routage (\w sans /u) : la page repond 404 alors que la ligne existe.
 *
 *   php tests/portail.php domaine <url|->
 *       Ecrit (ou retire) 'domaine' dans src/config.local.php. Le flux RSS
 *       et le sitemap n'emettent QUE des adresses absolues ; sans domaine
 *       ils repondent 503 exprès. Pour verifier qu'ils emettent le bon
 *       contenu AVEC un domaine, il faut donc en poser un le temps du
 *       controle. La suite remet le fichier d'origine, octet pour octet.
 */
require __DIR__ . '/_amorce.php';

$cmd = $argv[1] ?? '';

if ($cmd === 'compte') {
    $tot = (int) qval('SELECT COUNT(*) FROM articles');
    sortir([
        'total'      => $tot,
        'visibles'   => compter_articles(),
        'brouillons' => (int) qval('SELECT COUNT(*) FROM articles WHERE statut = ?', ['brouillon']),
        'programmes' => (int) qval('SELECT COUNT(*) FROM articles WHERE statut = ? AND publie_le > ?',
                                   ['publie', maintenant()]),
        'retires'    => (int) qval('SELECT COUNT(*) FROM articles WHERE statut = ?', ['retire']),
        'indexes'    => (int) qval('SELECT COUNT(DISTINCT objet_id) FROM index_recherche WHERE espace = ?',
                                   ['portail']),
        'rubriques'  => (int) qval('SELECT COUNT(*) FROM rubriques'),
        'slug_brouillon' => (string) qval('SELECT slug FROM articles WHERE statut = ? ORDER BY id LIMIT 1',
                                          ['brouillon']),
        'slug_programme' => (string) qval('SELECT slug FROM articles WHERE statut = ? AND publie_le > ?
                                           ORDER BY id LIMIT 1', ['publie', maintenant()]),
        'slug_publie'    => (string) qval('SELECT slug FROM articles WHERE statut = ? AND publie_le <= ?
                                           ORDER BY id LIMIT 1', ['publie', maintenant()]),
    ]);
}

if ($cmd === 'redacteur') {
    $u = qun('SELECT * FROM utilisateurs WHERE identifiant = ?', [$argv[2] ?? '']);
    if (!$u) sortir(['erreur' => 'compte inconnu']);
    $rid = (int) qval('SELECT id FROM roles WHERE cle = ?', ['redacteur']);
    maj('utilisateurs', (int) $u['id'], ['role_id' => $rid]);
    sortir(['pseudo' => $u['identifiant'], 'role_id' => $rid]);
}

if ($cmd === 'revoque-publier' || $cmd === 'rend-publier') {
    $rid = (int) qval('SELECT id FROM roles WHERE cle = ?', ['redacteur']);
    $pid = (int) qval('SELECT id FROM permissions WHERE cle = ?', ['portail.publier']);
    if ($cmd === 'revoque-publier') {
        q('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$rid, $pid]);
    } elseif (qval('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                   [$rid, $pid]) === null) {
        insere('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
    }
    sortir(['role' => 'redacteur', 'portail.publier' => qval(
        'SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$rid, $pid]) !== null]);
}

if ($cmd === 'slugs') {
    $mauvais = [];
    foreach (['articles', 'discussions', 'forums', 'rubriques', 'villes', 'pays', 'continents'] as $t) {
        foreach (qtous("SELECT id, slug FROM `$t`") as $r) {
            if (!preg_match('/^[a-z0-9-]+$/', (string) $r['slug'])) {
                $mauvais[] = $t . '#' . $r['id'] . ' => ' . $r['slug'];
            }
        }
    }
    sortir(['non_ascii' => count($mauvais), 'exemples' => array_slice($mauvais, 0, 5)]);
}

if ($cmd === 'domaine') {
    $fichier = dirname(__DIR__) . '/src/config.local.php';
    $val = $argv[2] ?? '-';
    $src = file_get_contents($fichier);
    // On enleve d'abord toute ligne 'domaine' deja posee par un passage
    // precedent, sinon deux executions empilent deux cles.
    $src = preg_replace("/^\s*'domaine'\s*=>.*\n/m", '', $src);
    if ($val !== '-') {
        $src = preg_replace('/^return \[\n/m',
            "return [\n    'domaine' => '" . addslashes($val) . "',\n", $src, 1);
    }
    file_put_contents($fichier, $src);
    sortir(['domaine' => $val === '-' ? null : $val, 'octets' => strlen($src)]);
}

if ($cmd === 'nettoyer') {
    // Les articles crees par la suite passent par HTTP : ils portent
    // demo = 0, exactement comme ceux d'un vrai redacteur, et
    // outils/purge-demo.php ne peut donc pas les voir.
    $ids = array_column(qtous(
        "SELECT id FROM articles WHERE titre LIKE 'Controle automatique%'"), 'id');
    $ids = array_map('intval', $ids);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach (qtous("SELECT discussion_id FROM articles
                        WHERE id IN ($ph) AND discussion_id IS NOT NULL", $ids) as $r) {
            $did = (int) $r['discussion_id'];
            q('DELETE FROM messages WHERE discussion_id = ?', [$did]);
            q('DELETE FROM index_recherche WHERE objet_type = ? AND objet_id = ?', ['discussion', $did]);
            q('DELETE FROM discussions WHERE id = ?', [$did]);
        }
        q("DELETE FROM vues_article WHERE article_id IN ($ph)", $ids);
        q("DELETE FROM sources WHERE objet_type = 'article' AND objet_id IN ($ph)", $ids);
        q("DELETE FROM index_recherche WHERE objet_type = 'article' AND objet_id IN ($ph)", $ids);
        q("DELETE FROM articles WHERE id IN ($ph)", $ids);
    }
    $restant = (int) qval("SELECT COUNT(*) FROM articles WHERE titre LIKE 'Controle automatique%'");
    sortir(['articles' => count($ids), 'restant' => $restant]);
}

fwrite(STDERR, "commande inconnue : $cmd\n");
exit(1);
