<?php
/** Exerce televerser() avec un fichier reel. */
require __DIR__ . '/_amorce.php';
$fichier = $argv[1] ?? '';
$pseudo  = $argv[2] ?? '';
if (!is_file($fichier)) { echo json_encode(['erreur' => 'fichier absent']), "\n"; exit(1); }
en_tant_que($pseudo);
// La copie est necessaire : televerser() DEPLACE le fichier.
$tmp = tempnam(sys_get_temp_dir(), 'uf');
copy($fichier, $tmp);
putenv('UF_TEST_UPLOAD=1');   // autorise le contournement de is_uploaded_file
$r = televerser([
    'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
    'tmp_name' => $tmp, 'name' => basename($fichier),
], 'image de controle');
if (is_file($tmp)) @unlink($tmp);
sortir($r);
