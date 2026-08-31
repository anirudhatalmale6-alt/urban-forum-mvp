<?php
/**
 * Sauvegarde complete : base + medias.
 *
 *   php outils/sauvegarde.php [dossier_de_destination]
 *
 * Pourquoi pas mysqldump : sur un hebergement mutualise, l'execution de
 * binaires externes est souvent coupee, et une procedure de sauvegarde qui
 * ne marche que sur la machine du developpeur n'est pas une procedure de
 * sauvegarde. Ici tout se fait en PHP, avec le meme acces que le site.
 *
 * Le fichier produit est relu et recompte immediatement apres ecriture. Une
 * sauvegarde qu'on n'a pas rouverte n'est pas une sauvegarde, c'est un
 * fichier. La restauration est testee par outils/restauration.php sur une
 * base jetable.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/schema.php';

$dest = $argv[1] ?? ($racine . '/donnees/sauvegardes');
if (!is_dir($dest) && !mkdir($dest, 0775, true)) {
    fwrite(STDERR, "Impossible de creer $dest\n"); exit(1);
}
$horodatage = gmdate('Ymd-His');
$fichier = $dest . '/forum-' . $horodatage . '.sql';

$pilote = cfg('bd')['pilote'];
$tables = array_keys(schema_tables());

$fh = fopen($fichier, 'w');
if (!$fh) { fwrite(STDERR, "Ecriture impossible : $fichier\n"); exit(1); }

fwrite($fh, "-- URBAN FORUM — sauvegarde du " . gmdate('Y-m-d H:i:s') . " UTC\n");
fwrite($fh, "-- pilote d'origine : $pilote\n");
fwrite($fh, "-- Restauration : php outils/restauration.php " . basename($fichier) . "\n\n");

$total = 0;
$comptes = [];
foreach ($tables as $table) {
    $n = 0;
    $st = q("SELECT * FROM `$table`");
    while ($ligne = $st->fetch()) {
        $cols = array_keys($ligne);
        $vals = array_map(function ($v) {
            if ($v === null) return 'NULL';
            if (is_int($v) || is_float($v)) return (string) $v;
            return "'" . str_replace(["\\", "'", "\n", "\r"], ["\\\\", "''", "\\n", "\\r"], (string) $v) . "'";
        }, array_values($ligne));
        fwrite($fh, sprintf("INSERT INTO `%s` (`%s`) VALUES (%s);\n",
            $table, implode('`, `', $cols), implode(', ', $vals)));
        $n++; $total++;
    }
    $comptes[$table] = $n;
}
fclose($fh);

/* --- Relecture : on RECOMPTE ce qui a ete ecrit ---------------------- */
$relu = 0;
$fh = fopen($fichier, 'r');
while (($l = fgets($fh)) !== false) {
    if (str_starts_with($l, 'INSERT INTO ')) $relu++;
}
fclose($fh);

if ($relu !== $total) {
    fwrite(STDERR, "ECHEC : $total lignes ecrites, $relu relues. Sauvegarde suspecte.\n");
    exit(1);
}

/* --- Medias ---------------------------------------------------------- */
$dossier_medias = cfg('chemin_medias');
$nb_medias = 0; $octets = 0;
$zip_ok = false;
if (is_dir($dossier_medias) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $chemin_zip = $dest . '/medias-' . $horodatage . '.zip';
    if ($zip->open($chemin_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dossier_medias, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = substr($f->getPathname(), strlen($dossier_medias) + 1);
            $zip->addFile($f->getPathname(), $rel);
            $nb_medias++; $octets += $f->getSize();
        }
        $zip->close();
        // On rouvre l'archive et on recompte ses entrees.
        $v = new ZipArchive();
        if ($v->open($chemin_zip) === true) {
            $zip_ok = ($v->numFiles === $nb_medias);
            $v->close();
        }
        if (!$zip_ok) {
            fwrite(STDERR, "ECHEC : l'archive des medias ne contient pas $nb_medias fichiers.\n");
            exit(1);
        }
    }
}

echo "Sauvegarde : $fichier\n";
echo "  lignes ecrites et relues : $total\n";
foreach ($comptes as $tt => $nn) {
    if ($nn > 0) echo sprintf("    %-22s %d\n", $tt, $nn);
}
if ($nb_medias) {
    echo "  medias : $nb_medias fichier(s), " . number_format($octets / 1024, 1, ',', ' ') . " ko\n";
} else {
    echo "  medias : aucun\n";
}
echo "\nA copier HORS du serveur. Une sauvegarde qui vit sur la machine\n";
echo "sauvegardee disparait avec elle.\n";
