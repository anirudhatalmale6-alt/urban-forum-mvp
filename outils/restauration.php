<?php
/**
 * Restauration d'une sauvegarde .jsonl.
 *
 *   php outils/restauration.php <fichier.jsonl> [--essai]
 *
 * --essai  restaure dans une base SQLite JETABLE et compare, sans toucher a
 *          la production. C'est le mode a utiliser pour VERIFIER qu'une
 *          sauvegarde est restaurable : un test de restauration qui ecrase
 *          la production n'est pas un test.
 *
 * Sans --essai, la restauration VIDE les tables avant de recharger. C'est
 * destructif et le script le demande explicitement.
 *
 * ------------------------------------------------------------------------
 * CE QUI EST COMPARE, ET POURQUOI PAS SEULEMENT LES COMPTES
 *
 * La version precedente comparait le nombre de lignes par table. Elle
 * repondait « restauration verifiee » alors qu'un retour a la ligne dans un
 * message pouvait avoir ete transforme en deux caracteres « \n » : meme
 * nombre de lignes, contenu different. Un comptage peut infirmer, il ne
 * confirme jamais.
 *
 * On compare donc, pour chaque table, une EMPREINTE du contenu relu depuis
 * la base restauree face au contenu du fichier. Si un seul octet d'un seul
 * champ a bouge, l'empreinte ne tombe pas.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/schema.php';

$fichier = $argv[1] ?? '';
$essai = in_array('--essai', $argv, true);

if ($fichier === '') {
    fwrite(STDERR, "Usage : php outils/restauration.php <fichier.jsonl> [--essai]\n");
    exit(1);
}
if (!is_file($fichier)) {
    $alt = $racine . '/donnees/sauvegardes/' . basename($fichier);
    if (is_file($alt)) $fichier = $alt;
    else { fwrite(STDERR, "Introuvable : $fichier\n"); exit(1); }
}

/* --- Lecture du fichier ---------------------------------------------- */
$lignes_par_table = [];
$entete = null;
$fh = fopen($fichier, 'r');
$n_ligne = 0;
while (($l = fgets($fh)) !== false) {
    $l = rtrim($l, "\n");
    $n_ligne++;
    if ($l === '') continue;
    $o = json_decode($l, true);
    if (!is_array($o)) {
        fwrite(STDERR, "Ligne $n_ligne illisible.\n"); exit(1);
    }
    if (isset($o['_entete'])) { $entete = $o; continue; }
    if (!isset($o['t'], $o['r'])) {
        fwrite(STDERR, "Ligne $n_ligne : enregistrement incomplet.\n"); exit(1);
    }
    $lignes_par_table[$o['t']][] = $o['r'];
}
fclose($fh);

if (!$entete) {
    fwrite(STDERR, "Ce fichier n'a pas d'en-tete URBAN FORUM. Format inattendu.\n");
    exit(1);
}
$total = array_sum(array_map('count', $lignes_par_table));
echo "Fichier : $fichier\n";
echo "Origine : {$entete['date_utc']} UTC, pilote {$entete['pilote']}\n";
echo "Enregistrements : $total sur " . count($lignes_par_table) . " table(s)\n\n";

/* --- Cible ------------------------------------------------------------ */
if ($essai) {
    $cible_fichier = sys_get_temp_dir() . '/uf-essai-' . bin2hex(random_bytes(6)) . '.sqlite';
    $pdo = new PDO('sqlite:' . $cible_fichier);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    foreach (schema_ddl('sqlite') as $sql) $pdo->exec($sql);
    echo "Mode ESSAI — base jetable : $cible_fichier\n\n";
} else {
    fwrite(STDOUT, "ATTENTION : cette operation VIDE les tables de la base de production\n"
                 . "puis les recharge depuis le fichier. Tape « oui » pour continuer : ");
    $rep = trim((string) fgets(STDIN));
    if ($rep !== 'oui') { echo "Annule.\n"; exit(0); }
    $pdo = bd();
    foreach (array_reverse(array_keys(schema_tables())) as $tb) {
        $pdo->exec("DELETE FROM `$tb`");
    }
}

/* --- Rejeu, en requetes PREPAREES ------------------------------------ */
// C'est le pilote qui met les valeurs en forme, pas moi. Il n'y a donc
// aucun echappement a inventer, et le meme fichier se restaure a
// l'identique sur SQLite comme sur MySQL.
$pdo->beginTransaction();
$n = 0; $erreurs = 0;
foreach ($lignes_par_table as $table => $lignes) {
    foreach ($lignes as $r) {
        $cols = array_keys($r);
        $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table, implode('`, `', $cols),
            implode(', ', array_fill(0, count($cols), '?')));
        try {
            $st = $pdo->prepare($sql);
            $st->execute(array_values($r));
            $n++;
        } catch (PDOException $e) {
            $erreurs++;
            if ($erreurs <= 5) fwrite(STDERR, "  echec [$table] : " . $e->getMessage() . "\n");
        }
    }
}
$pdo->commit();
echo "Enregistrements rejoues : $n" . ($erreurs ? " — echecs : $erreurs" : '') . "\n\n";

/* --- Verification : compte ET contenu -------------------------------- */
$ecarts = 0;
foreach ($lignes_par_table as $table => $lignes) {
    $attendu = count($lignes);
    $reel = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

    // Empreinte du fichier.
    $h1 = hash_init('sha256');
    foreach ($lignes as $r) {
        hash_update($h1, json_encode(['t' => $table, 'r' => $r],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    // Empreinte de ce qui est REELLEMENT dans la base restauree.
    $h2 = hash_init('sha256');
    foreach ($pdo->query("SELECT * FROM `$table` ORDER BY id") as $r) {
        hash_update($h2, json_encode(['t' => $table, 'r' => $r],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $e1 = substr(hash_final($h1), 0, 16);
    $e2 = substr(hash_final($h2), 0, 16);

    // Troisieme empreinte, et c'est LA seule qui prouve quelque chose sur
    // la sauvegarde elle-meme : celle de la base SOURCE.
    //
    // Comparer le fichier a la base restauree depuis ce fichier ne teste que
    // la moitie du chemin — les deux cotes viennent du meme fichier, donc
    // un fichier deja abime se compare a lui-meme et repond « ok ». C'est
    // exactement ce qui est arrive au premier essai. Le seul aller-retour
    // reel est : base d'origine -> fichier -> base restauree.
    $e3 = null;
    if ($essai) {
        $h3 = hash_init('sha256');
        foreach (bd()->query("SELECT * FROM `$table` ORDER BY id") as $r) {
            hash_update($h3, json_encode(['t' => $table, 'r' => $r],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $e3 = substr(hash_final($h3), 0, 16);
    }

    $ok = ($reel === $attendu) && ($e1 === $e2) && ($e3 === null || $e3 === $e1);
    if (!$ok) $ecarts++;
    printf("  %-22s %-6d lignes   %s %s %s  %s\n", $table, $attendu, $e1, $e2,
           $e3 ?? '                ', $ok ? 'ok' : 'ECART');
}

if ($essai) {
    @unlink($cible_fichier);
    echo "\nBase d'essai supprimee.\n";
}

if ($ecarts || $erreurs) {
    fwrite(STDERR, "\nRESTAURATION INCOMPLETE : $ecarts ecart(s), $erreurs erreur(s).\n");
    exit(1);
}
echo "\nRestauration verifiee : chaque table a le bon nombre de lignes ET la\n";
echo "meme empreinte de contenu que la sauvegarde. Un retour a la ligne, un\n";
echo "accent ou un caractere arabe modifie ferait tomber l'empreinte.\n";
