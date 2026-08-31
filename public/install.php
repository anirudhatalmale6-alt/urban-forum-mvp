<?php
/**
 * Installateur par le navigateur.
 *
 * Il existe pour UNE raison precise : sur l'hebergement mutualise vise, il
 * n'y a pas d'acces SSH, donc `php outils/installer.php` n'est pas jouable.
 * Ce fichier fait exactement la meme chose depuis une page web.
 *
 * Trois garde-fous, parce qu'un installateur laisse en ligne est une porte :
 *   1. il REFUSE de tourner si la table des roles contient deja des lignes ;
 *   2. il affiche le mot de passe administrateur UNE fois, genere au hasard ;
 *   3. il essaie de SE SUPPRIMER a la fin et dit clairement s'il n'y est pas
 *      arrive, auquel cas il faut effacer le fichier a la main.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/auth.php';
require $racine . '/src/schema.php';
require $racine . '/src/balisage.php';
require $racine . '/src/recherche.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$deja = false;
try {
    $deja = ((int) qval('SELECT COUNT(*) FROM roles')) > 0;
} catch (Throwable) {
    $deja = false;
}

$sortie = [];
$mdp = null;
$supprime = null;
$lance = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$deja;

if ($lance) {
    $pilote = cfg('bd')['pilote'];
    $n = 0;
    foreach (schema_ddl($pilote) as $sql) {
        try { bd()->exec($sql); $n++; }
        catch (PDOException $e) {
            if (!str_contains($e->getMessage(), '1061')
                && !str_contains($e->getMessage(), 'Duplicate key name')) {
                $sortie[] = 'ECHEC : ' . $e->getMessage();
            }
        }
    }
    $sortie[] = "Tables et index : $n instructions.";

    foreach (PERMISSIONS as $cle => $desc) {
        if (qval('SELECT id FROM permissions WHERE cle = ?', [$cle]) === null) {
            insere('permissions', ['cle' => $cle, 'description' => $desc]);
        }
    }
    foreach (ROLES as $cle => $def) {
        $id = qval('SELECT id FROM roles WHERE cle = ?', [$cle]);
        if ($id === null) {
            $id = insere('roles', ['cle' => $cle, 'rang' => $def['rang'],
                'nom_fr' => $def['fr'], 'nom_en' => $def['en'], 'nom_ar' => $def['ar']]);
        }
        $perms = $def['perms'] === '*' ? array_keys(PERMISSIONS) : $def['perms'];
        foreach ($perms as $p) {
            $pid = (int) qval('SELECT id FROM permissions WHERE cle = ?', [$p]);
            if ($pid && qval('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                             [(int) $id, $pid]) === null) {
                insere('role_permissions', ['role_id' => (int) $id, 'permission_id' => $pid]);
            }
        }
    }
    $sortie[] = 'Roles : ' . count(ROLES) . ' — permissions : ' . count(PERMISSIONS) . '.';

    $local = $racine . '/src/config.local.php';
    if (!is_file($local)) {
        $sel = bin2hex(random_bytes(32));
        $ok = @file_put_contents($local,
            "<?php\n/* Genere par public/install.php. Ne pas publier. */\nreturn [\n"
          . "    'sel_session' => '$sel',\n];\n");
        $sortie[] = $ok
            ? 'Ecrit : src/config.local.php (sel de session).'
            : 'ATTENTION : src/config.local.php non ecrit — verifie les droits du dossier src/.';
    } else {
        $sortie[] = 'src/config.local.php existait deja — inchange.';
    }

    $admin_role = (int) qval('SELECT id FROM roles WHERE cle = ?', ['administrateur']);
    if (qval('SELECT id FROM utilisateurs WHERE role_id = ?', [$admin_role]) === null) {
        $mdp = bin2hex(random_bytes(9));
        $uid = insere('utilisateurs', [
            'identifiant' => 'admin', 'email' => 'admin@localhost',
            'mot_de_passe' => password_hash($mdp, PASSWORD_DEFAULT),
            'role_id' => $admin_role, 'nom_public' => 'admin',
            'langue' => cfg('langue_defaut'), 'cree_le' => maintenant(),
            'actif' => 1, 'banni' => 0, 'demo' => 0, 'profil_public' => 1, 'nb_messages' => 0,
        ]);
        prefs_notif_par_defaut($uid);
    } else {
        $sortie[] = 'Un administrateur existait deja — aucun compte cree.';
    }

    foreach ([cfg('chemin_medias'), cfg('chemin_journal')] as $d) {
        if (!is_dir($d)) @mkdir($d, 0775, true);
    }

    $supprime = @unlink(__FILE__);
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation — URBAN FORUM</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="page page--etroite">
<h1>Installation</h1>

<?php if ($deja): ?>
  <p class="avis avis--attention">
    La base contient deja des roles : l'installation a donc deja eu lieu.
    Cet installateur refuse de tourner une seconde fois.
    <strong>Supprime ce fichier</strong> (<code>public/install.php</code>) s'il est
    encore la.
  </p>
  <p><a class="btn btn--plein" href="/">Aller au site</a></p>

<?php elseif ($lance): ?>
  <?php foreach ($sortie as $l): ?>
    <p class="avis avis--<?= str_starts_with($l, 'ECHEC') || str_starts_with($l, 'ATTENTION') ? 'erreur' : 'ok' ?>"><?= h($l) ?></p>
  <?php endforeach; ?>

  <?php if ($mdp !== null): ?>
  <div class="bande">
    <h2>Compte administrateur</h2>
    <p>identifiant : <strong>admin</strong></p>
    <p>mot de passe : <strong class="mdp"><?= h($mdp) ?></strong></p>
    <p>Note-le maintenant. Il est affiche une seule fois et n'est ecrit dans aucun
       fichier. Change ensuite l'adresse e-mail depuis <em>Parametres</em>.</p>
  </div>
  <?php endif; ?>

  <p class="avis avis--<?= $supprime ? 'ok' : 'erreur' ?>">
    <?= $supprime
        ? "Cet installateur s'est supprime tout seul."
        : "Cet installateur N'A PAS pu se supprimer. Efface public/install.php a la main, maintenant." ?>
  </p>
  <p><a class="btn btn--plein" href="/">Aller au site</a></p>

<?php else: ?>
  <p class="lede">Cree les tables, les roles, les permissions et le compte
     administrateur. Ne remplit aucun contenu.</p>
  <p>Base cible : <code><?= h(cfg('bd')['pilote']) ?></code>
     <?php if (cfg('bd')['pilote'] === 'mysql'): ?> — <code><?= h(cfg('bd')['base'] ?: 'a renseigner') ?></code><?php endif; ?>
  </p>
  <form method="post">
    <button class="btn btn--plein" type="submit">Installer</button>
  </form>
  <p class="carte__meta esp-haut-l">
    Ce fichier tentera de se supprimer apres l'installation. S'il n'y arrive pas,
    efface-le a la main : un installateur laisse en ligne est une porte ouverte.
  </p>
<?php endif; ?>
</main>
</body>
</html>
