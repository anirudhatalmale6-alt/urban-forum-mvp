<?php
/**
 * Restauration d'une sauvegarde.
 *
 *   php outils/restauration.php <fichier.sql> [--essai]
 *
 * --essai  restaure dans une base SQLite JETABLE et compare les comptes,
 *          sans toucher a la base de production. C'est le mode a utiliser
 *          pour VERIFIER qu'une sauvegarde est restaurable — un test de
 *          restauration qui ecrase la production n'est pas un test.
 *
 * Sans --essai, la restauration VIDE les tables avant de recharger. C'est
 * destructif et le script le demande explicitement.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/schema.php';

$fichier = $argv[1] ?? '';
$essai = in_array('--essai', $argv, true);

if ($fichier === '') {
    fwrite(STDERR, "Usage : php outils/restauration.php <fichier.sql> [--essai]\n");
    exit(1);
}
if (!is_file($fichier)) {
    $alt = $racine . '/donnees/sauvegardes/' . basename($fichier);
    if (is_file($alt)) $fichier = $alt;
    else { fwrite(STDERR, "Introuvable : $fichier\n"); exit(1); }
}

/* --- Comptage de ce que le fichier contient, par table --------------- */
$attendu = [];
$fh = fopen($fichier, 'r');
while (($l = fgets($fh)) !== false) {
    if (preg_match('/^INSERT INTO `([^`]+)`/', $l, $m)) {
        $attendu[$m[1]] = ($attendu[$m[1]] ?? 0) + 1;
    }
}
fclose($fh);
$total_attendu = array_sum($attendu);
echo "Fichier : $fichier\n";
echo "Lignes INSERT : $total_attendu sur " . count($attendu) . " table(s)\n\n";

/* --- Cible ------------------------------------------------------------ */
if ($essai) {
    $cible_fichier = sys_get_temp_dir() . '/uf-essai-' . bin2hex(random_bytes(6)) . '.sqlite';
    $pdo = new PDO('sqlite:' . $cible_fichier);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (schema_ddl('sqlite') as $sql) $pdo->exec($sql);
    echo "Mode ESSAI — base jetable : $cible_fichier\n\n";
} else {
    fwrite(STDOUT, "ATTENTION : cette operation VIDE les tables de la base de production\n"
                 . "puis les recharge depuis le fichier. Tape « oui » pour continuer : ");
    $rep = trim((string) fgets(STDIN));
    if ($rep !== 'oui') { echo "Annule.\n"; exit(0); }
    $pdo = bd();
    // Ordre inverse pour ne pas buter sur les contraintes.
    foreach (array_reverse(array_keys(schema_tables())) as $tb) {
        $pdo->exec("DELETE FROM `$tb`");
    }
}

/* --- Rejeu ------------------------------------------------------------ */
$pdo->beginTransaction();
$n = 0; $erreurs = 0;
$fh = fopen($fichier, 'r');
while (($l = fgets($fh)) !== false) {
    $l = rtrim($l);
    if (!str_starts_with($l, 'INSERT INTO ')) continue;
    try {
        $pdo->exec($l);
        $n++;
    } catch (PDOException $e) {
        $erreurs++;
        if ($erreurs <= 5) fwrite(STDERR, "  echec : " . $e->getMessage() . "\n");
    }
}
fclose($fh);
$pdo->commit();

/* --- Verification : on RECOMPTE dans la base restauree ---------------- */
echo "Lignes rejouees : $n" . ($erreurs ? " — echecs : $erreurs" : '') . "\n\n";
$ecarts = 0;
foreach ($attendu as $table => $nb) {
    $reel = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $ok = $reel === $nb;
    if (!$ok) $ecarts++;
    printf("  %-22s attendu %-6d trouve %-6d %s\n", $table, $nb, $reel, $ok ? 'ok' : 'ECART');
}

if ($essai) {
    @unlink($cible_fichier);
    echo "\nBase d'essai supprimee.\n";
}

if ($ecarts || $erreurs) {
    fwrite(STDERR, "\nRESTAURATION INCOMPLETE : $ecarts ecart(s), $erreurs erreur(s).\n");
    exit(1);
}
echo "\nRestauration verifiee : chaque table contient exactement le nombre de lignes\n";
echo "presentes dans la sauvegarde.\n";
