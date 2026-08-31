<?php
/**
 * Installation : cree les tables, les roles, les permissions, le compte
 * administrateur, et ecrit src/config.local.php.
 *
 *   php outils/installer.php
 *
 * Rejouable sans risque : tout est en CREATE TABLE IF NOT EXISTS et en
 * INSERT conditionnel. On ne teste PAS l'existence du fichier SQLite avant
 * de se connecter — ouvrir un fichier SQLite le CREE, donc le test
 * « le fichier existe-t-il » repond faux puis vrai dans la meme seconde et
 * la deuxieme execution croirait avoir affaire a une base neuve.
 *
 * Le mot de passe administrateur est TIRE AU HASARD et affiche UNE FOIS.
 * Il n'est pas ecrit dans un fichier du depot : un mot de passe par defaut
 * livre dans une archive publique est un compte ouvert.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/auth.php';
require $racine . '/src/schema.php';
require $racine . '/src/balisage.php';
require $racine . '/src/recherche.php';

$pilote = cfg('bd')['pilote'];
echo "Pilote : $pilote\n";

/* --- 1. Tables ------------------------------------------------------- */
$n = 0;
foreach (schema_ddl($pilote) as $sql) {
    try {
        bd()->exec($sql);
        $n++;
    } catch (PDOException $e) {
        // 1061 = index deja present sur MySQL, qui n'a pas de
        // « CREATE INDEX IF NOT EXISTS ». C'est le seul code tolere.
        if (str_contains($e->getMessage(), '1061') || str_contains($e->getMessage(), 'Duplicate key name')) {
            continue;
        }
        fwrite(STDERR, "ECHEC : $sql\n" . $e->getMessage() . "\n");
        exit(1);
    }
}
echo "Instructions DDL executees : $n\n";

/* --- 2. Roles et permissions ---------------------------------------- */
foreach (PERMISSIONS as $cle => $desc) {
    if (qval('SELECT id FROM permissions WHERE cle = ?', [$cle]) === null) {
        insere('permissions', ['cle' => $cle, 'description' => $desc]);
    }
}
foreach (ROLES as $cle => $def) {
    $id = qval('SELECT id FROM roles WHERE cle = ?', [$cle]);
    if ($id === null) {
        $id = insere('roles', [
            'cle' => $cle, 'rang' => $def['rang'],
            'nom_fr' => $def['fr'], 'nom_en' => $def['en'], 'nom_ar' => $def['ar'],
        ]);
    }
    $perms = $def['perms'] === '*' ? array_keys(PERMISSIONS) : $def['perms'];
    foreach ($perms as $p) {
        $pid = (int) qval('SELECT id FROM permissions WHERE cle = ?', [$p]);
        if (!$pid) continue;
        $existe = qval('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                       [(int) $id, $pid]);
        if ($existe === null) {
            insere('role_permissions', ['role_id' => (int) $id, 'permission_id' => $pid]);
        }
    }
}
echo "Roles : " . count(ROLES) . " — permissions : " . count(PERMISSIONS) . "\n";

/* --- 3. Badges ------------------------------------------------------- */
foreach ([
    ['premier-message', 'Premier message', 'First post', 'أول مشاركة'],
    ['contributeur-projet', 'Premiere fiche projet', 'First project record', 'أول بطاقة مشروع'],
    ['veteran', 'Un an de presence', 'One year here', 'سنة في المنتدى'],
] as [$cle, $fr, $en, $ar]) {
    if (qval('SELECT id FROM badges WHERE cle = ?', [$cle]) === null) {
        insere('badges', ['cle' => $cle, 'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar,
                          'description' => '']);
    }
}

/* --- 4. Synonymes de recherche (administrables ensuite) -------------- */
foreach ([
    ['metro', 'subway'], ['subway', 'metro'], ['tramway', 'tram'], ['tram', 'tramway'],
    ['tour', 'tower'], ['tower', 'tour'], ['aeroport', 'airport'], ['airport', 'aeroport'],
    ['port', 'harbour'], ['gare', 'station'], ['station', 'gare'],
    ['logement', 'housing'], ['housing', 'logement'],
] as [$a, $b]) {
    if (qval('SELECT id FROM synonymes WHERE terme = ? AND vers = ?', [$a, $b]) === null) {
        insere('synonymes', ['terme' => $a, 'vers' => $b]);
    }
}

/* --- 5. config.local.php : sel de session ---------------------------- */
$local = $racine . '/src/config.local.php';
if (!is_file($local)) {
    $sel = bin2hex(random_bytes(32));
    $contenu = "<?php\n"
        . "/* Genere par outils/installer.php. NE PAS mettre dans un depot public :\n"
        . "   ce fichier porte le sel de session et, en production, les\n"
        . "   identifiants de la base. Il est exclu par .gitignore. */\n"
        . "return [\n"
        . "    'sel_session' => '$sel',\n"
        . "    // Decommente et renseigne pour MySQL en production :\n"
        . "    // 'bd' => ['pilote' => 'mysql', 'hote' => 'localhost',\n"
        . "    //          'base' => '', 'user' => '', 'passe' => ''],\n"
        . "    // 'domaine' => 'https://exemple.com',\n"
        . "    // 'cookie_secure' => true,\n"
        . "];\n";
    file_put_contents($local, $contenu);
    @chmod($local, 0640);
    echo "Ecrit : src/config.local.php (sel de session genere)\n";
} else {
    echo "src/config.local.php existe deja — inchange.\n";
}

/* --- 6. Compte administrateur ---------------------------------------- */
$admin_role = (int) qval('SELECT id FROM roles WHERE cle = ?', ['administrateur']);
$existe = qval('SELECT id FROM utilisateurs WHERE role_id = ?', [$admin_role]);
if ($existe === null) {
    $mdp = bin2hex(random_bytes(9));   // 18 caracteres hexadecimaux
    $id = insere('utilisateurs', [
        'identifiant' => 'admin', 'email' => 'admin@localhost',
        'mot_de_passe' => password_hash($mdp, PASSWORD_DEFAULT),
        'role_id' => $admin_role, 'nom_public' => 'admin',
        'langue' => cfg('langue_defaut'), 'cree_le' => maintenant(),
        'actif' => 1, 'banni' => 0, 'demo' => 0, 'profil_public' => 1, 'nb_messages' => 0,
    ]);
    prefs_notif_par_defaut($id);
    echo "\n";
    echo "====================================================\n";
    echo " COMPTE ADMINISTRATEUR\n";
    echo "   identifiant : admin\n";
    echo "   mot de passe : $mdp\n";
    echo " Note-le maintenant : il n'est affiche qu'une fois et\n";
    echo " il n'est ecrit dans aucun fichier.\n";
    echo " Change l'adresse e-mail depuis /parametres.\n";
    echo "====================================================\n\n";
} else {
    echo "Un administrateur existe deja — aucun compte cree.\n";
}

/* --- 7. Protection des repertoires de donnees ------------------------ */
foreach ([cfg('chemin_medias'), cfg('chemin_journal')] as $d) {
    if (!is_dir($d)) mkdir($d, 0775, true);
    $ht = dirname($d) . '/.htaccess';
    if (!is_file($ht)) {
        // Ceinture ET bretelles : ces repertoires sont deja HORS de la
        // racine web. Ce .htaccess sert au cas ou quelqu'un deplacerait
        // le dossier donnees/ dans public/ un jour de fatigue.
        file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}

echo "Installation terminee.\n";
echo "Pour la demonstration : php outils/semer.php\n";
