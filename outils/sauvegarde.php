<?php
/**
 * Sauvegarde complete : base + medias.
 *
 *   php outils/sauvegarde.php [dossier_de_destination]
 *
 * ------------------------------------------------------------------------
 * POURQUOI LE FORMAT N'EST PAS DU SQL
 *
 * La premiere version ecrivait des INSERT. Elle echappait les retours a la
 * ligne en « \n » — ce que MySQL redecode en retour a la ligne, et ce que
 * SQLite laisse tel quel, deux caracteres. Le meme fichier restaure donc un
 * texte DIFFERENT selon le moteur, et une sauvegarde qui rend un autre
 * contenu que l'original n'est pas une sauvegarde.
 *
 * Pire : mon controle de restauration comparait le NOMBRE DE LIGNES par
 * table. Il passait au vert avec un contenu abime. Un comptage peut
 * infirmer, il ne confirme jamais.
 *
 * Le format est donc du JSON, une ligne par enregistrement. Il n'y a plus
 * d'echappement a inventer : c'est le decodeur JSON qui rend exactement la
 * chaine d'origine, sur les deux moteurs. Et la restauration passe par des
 * requetes preparees, donc par le pilote lui-meme.
 *
 * Pour qui veut un fichier importable dans phpMyAdmin :
 *   php outils/sauvegarde.php --sql
 * produit en plus un .sql echappe par le PILOTE en cours (PDO::quote), donc
 * valable pour le moteur d'ou il sort — et pour lui seul.
 * ------------------------------------------------------------------------
 *
 * Le fichier produit est relu, recompte ET recompare champ a champ juste
 * apres ecriture. Une sauvegarde qu'on n'a pas rouverte n'est pas une
 * sauvegarde, c'est un fichier.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/schema.php';

$args = array_slice($argv, 1);
$aussi_sql = in_array('--sql', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--sql'));
$dest = $args[0] ?? ($racine . '/donnees/sauvegardes');

if (!is_dir($dest) && !mkdir($dest, 0775, true)) {
    fwrite(STDERR, "Impossible de creer $dest\n"); exit(1);
}
$horodatage = gmdate('Ymd-His');
$fichier = $dest . '/forum-' . $horodatage . '.jsonl';

$pilote = cfg('bd')['pilote'];
$tables = array_keys(schema_tables());

$fh = fopen($fichier, 'w');
if (!$fh) { fwrite(STDERR, "Ecriture impossible : $fichier\n"); exit(1); }

fwrite($fh, json_encode([
    '_entete'   => 'URBAN FORUM',
    'format'    => 'jsonl-1',
    'date_utc'  => maintenant(),
    'pilote'    => $pilote,
    'tables'    => $tables,
], JSON_UNESCAPED_UNICODE) . "\n");

$total = 0;
$comptes = [];
$empreintes = [];
foreach ($tables as $table) {
    $n = 0;
    $h = hash_init('sha256');
    $st = q("SELECT * FROM `$table`");
    while ($ligne = $st->fetch()) {
        $json = json_encode(['t' => $table, 'r' => $ligne],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Une ligne = un enregistrement. json_encode n'emet jamais de retour
        // a la ligne brut dans une chaine : il ecrit \n, et c'est le
        // decodeur qui le rendra. L'ambiguite disparait.
        fwrite($fh, $json . "\n");
        hash_update($h, $json);
        $n++; $total++;
    }
    $comptes[$table] = $n;
    $empreintes[$table] = substr(hash_final($h), 0, 16);
}
fclose($fh);

/* --- Relecture : on recompte ET on recompare les empreintes ---------- */
$relu = 0;
$comptes_relus = [];
$emp_relues = [];
$hs = [];
foreach ($tables as $t) { $hs[$t] = hash_init('sha256'); $comptes_relus[$t] = 0; }

$fh = fopen($fichier, 'r');
$premiere = true;
while (($l = fgets($fh)) !== false) {
    $l = rtrim($l, "\n");
    if ($l === '') continue;
    if ($premiere) { $premiere = false; continue; }   // ligne d'en-tete
    $o = json_decode($l, true);
    if (!is_array($o) || !isset($o['t'])) {
        fwrite(STDERR, "ECHEC : ligne illisible dans la sauvegarde.\n"); exit(1);
    }
    $comptes_relus[$o['t']]++;
    hash_update($hs[$o['t']], $l);
    $relu++;
}
fclose($fh);
foreach ($tables as $t) $emp_relues[$t] = substr(hash_final($hs[$t]), 0, 16);

if ($relu !== $total) {
    fwrite(STDERR, "ECHEC : $total lignes ecrites, $relu relues.\n"); exit(1);
}
foreach ($tables as $t) {
    if ($comptes[$t] !== $comptes_relus[$t] || $empreintes[$t] !== $emp_relues[$t]) {
        fwrite(STDERR, "ECHEC : la table `$t` ne se relit pas a l'identique.\n"); exit(1);
    }
}

/* --- Variante SQL, pour phpMyAdmin ----------------------------------- */
$fichier_sql = null;
if ($aussi_sql) {
    $fichier_sql = $dest . '/forum-' . $horodatage . '.sql';
    $fs = fopen($fichier_sql, 'w');
    fwrite($fs, "-- URBAN FORUM — export SQL du " . maintenant() . " UTC\n");
    fwrite($fs, "-- Pilote d'origine : $pilote. Ce fichier n'est valable QUE\n");
    fwrite($fs, "-- pour ce moteur : l'echappement est celui du pilote.\n\n");
    foreach ($tables as $table) {
        $st = q("SELECT * FROM `$table`");
        while ($ligne = $st->fetch()) {
            $vals = array_map(
                fn($v) => $v === null ? 'NULL' : bd()->quote((string) $v),
                array_values($ligne));
            fwrite($fs, sprintf("INSERT INTO `%s` (`%s`) VALUES (%s);\n",
                $table, implode('`, `', array_keys($ligne)), implode(', ', $vals)));
        }
    }
    fclose($fs);
}

/* --- Medias ---------------------------------------------------------- */
$dossier_medias = cfg('chemin_medias');
$nb_medias = 0; $octets = 0;
if (is_dir($dossier_medias) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $chemin_zip = $dest . '/medias-' . $horodatage . '.zip';
    if ($zip->open($chemin_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dossier_medias, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getFilename() === '.gitkeep') continue;
            $rel = substr($f->getPathname(), strlen($dossier_medias) + 1);
            $zip->addFile($f->getPathname(), $rel);
            $nb_medias++; $octets += $f->getSize();
        }
        $zip->close();
        if ($nb_medias === 0) {
            @unlink($chemin_zip);
        } else {
            // On rouvre l'archive et on recompte ses entrees.
            $v = new ZipArchive();
            $ok = ($v->open($chemin_zip) === true) && $v->numFiles === $nb_medias;
            if ($v) $v->close();
            if (!$ok) {
                fwrite(STDERR, "ECHEC : l'archive des medias ne contient pas $nb_medias fichiers.\n");
                exit(1);
            }
        }
    }
}

echo "Sauvegarde : $fichier\n";
if ($fichier_sql) echo "Export SQL : $fichier_sql\n";
echo "  lignes ecrites, relues et recomparees : $total\n";
foreach ($comptes as $tt => $nn) {
    if ($nn > 0) echo sprintf("    %-22s %-6d %s\n", $tt, $nn, $empreintes[$tt]);
}
echo $nb_medias
    ? "  medias : $nb_medias fichier(s), " . number_format($octets / 1024, 1, ',', ' ') . " ko\n"
    : "  medias : aucun\n";
echo "\nA copier HORS du serveur. Une sauvegarde qui vit sur la machine\n";
echo "sauvegardee disparait avec elle.\n";
