<?php
/**
 * Noyau : configuration, base de donnees, journal, utilitaires.
 * Aucune dependance externe, aucun composer. Le fichier se depose tel quel.
 */

declare(strict_types=1);

function cfg(?string $cle = null)
{
    static $c = null;
    if ($c === null) {
        $c = require __DIR__ . '/config.php';
        // config.local.php n'est PAS dans le depot (.gitignore). C'est lui
        // qui porte les identifiants de base et le sel de session.
        $local = __DIR__ . '/config.local.php';
        if (is_file($local)) {
            $c = array_replace_recursive($c, require $local);
        }
    }
    if ($cle === null) return $c;
    return $c[$cle] ?? null;
}

function bd(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $b = cfg('bd');
    if ($b['pilote'] === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $b['hote'], $b['port'], $b['base']);
        $pdo = new PDO($dsn, $b['user'], $b['passe']);
    } else {
        $chemin = $b['sqlite'];
        if (!is_dir(dirname($chemin))) mkdir(dirname($chemin), 0775, true);
        $pdo = new PDO('sqlite:' . $chemin);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

/* Toutes les requetes passent par ici, et toutes sont preparees. Il n'y a
 * pas une seule concatenation de valeur dans une chaine SQL du projet ;
 * c'est la seule defense contre l'injection qui ne demande pas d'y penser. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = bd()->prepare($sql);
    $st->execute($params);
    return $st;
}
function qun(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}
function qtous(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function qval(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $r === false ? null : $r[0];
}
function insere(string $table, array $donnees): int
{
    $cols = array_keys($donnees);
    $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table, implode('`, `', $cols),
        implode(', ', array_fill(0, count($cols), '?')));
    q($sql, array_values($donnees));
    return (int) bd()->lastInsertId();
}
function maj(string $table, int $id, array $donnees): void
{
    if (!$donnees) return;
    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($donnees)));
    q("UPDATE `$table` SET $sets WHERE id = ?", [...array_values($donnees), $id]);
}

function maintenant(): string { return gmdate('Y-m-d H:i:s'); }

/* ------------------------------------------------------------------ */
/* Echappement                                                         */
/* ------------------------------------------------------------------ */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Un slug qui garde l'arabe et les accents lisibles quand c'est possible,
 * et qui ne rend JAMAIS une chaine vide : une URL vide casse le routage. */
function slug(string $s, int $max = 120): string
{
    $s = trim($s);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
        if ($t !== false && $t !== '') $s = $t;
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = mb_substr($s, 0, $max, 'UTF-8');
    return $s === '' ? 'n-' . substr(bin2hex(random_bytes(4)), 0, 8) : $s;
}

function slug_unique(string $table, string $base, string $col = 'slug'): string
{
    $s = $base; $n = 1;
    while (qval("SELECT id FROM `$table` WHERE `$col` = ?", [$s]) !== null) {
        $n++;
        $s = $base . '-' . $n;
    }
    return $s;
}

/* ------------------------------------------------------------------ */
/* Journal d'erreurs (critere de recette n°10)                          */
/* ------------------------------------------------------------------ */

function journal(string $niveau, string $message, array $ctx = []): void
{
    $dir = cfg('chemin_journal');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ligne = json_encode([
        'ts'      => maintenant(),
        'niveau'  => $niveau,
        'message' => $message,
        'uri'     => $_SERVER['REQUEST_URI'] ?? 'cli',
        'ip'      => ip_client(),
        'ctx'     => $ctx,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir . '/' . gmdate('Y-m-d') . '.log', $ligne . "\n", FILE_APPEND | LOCK_EX);
}

function installe_gestion_erreurs(): void
{
    set_error_handler(function ($no, $msg, $f, $l) {
        journal('erreur', $msg, ['fichier' => $f, 'ligne' => $l, 'no' => $no]);
        return false;      // laisse PHP faire son travail par-dessus
    });
    set_exception_handler(function (Throwable $e) {
        journal('critique', $e->getMessage(), [
            'classe' => get_class($e),
            'fichier' => $e->getFile(), 'ligne' => $e->getLine(),
        ]);
        http_response_code(500);
        // Le detail va au journal, pas a l'ecran : un message d'exception
        // affiche publiquement raconte le chemin des fichiers et la requete.
        echo '<!doctype html><meta charset="utf-8"><title>500</title>'
           . '<p style="font:16px/1.5 system-ui;padding:40px">'
           . 'Une erreur est survenue. Elle est enregistree dans le journal.</p>';
    });
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            journal('fatale', $e['message'], ['fichier' => $e['file'], 'ligne' => $e['line']]);
        }
    });
}

function ip_client(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64);
}

/* ------------------------------------------------------------------ */
/* Limitation de debit                                                  */
/* ------------------------------------------------------------------ */

function limite_ok(string $action, string $sujet): bool
{
    $l = cfg('limites')[$action] ?? null;
    if (!$l) return true;
    $cle = $action . ':' . $sujet;
    $r = qun('SELECT * FROM limites_taux WHERE cle = ?', [$cle]);
    $now = time();
    if (!$r) {
        insere('limites_taux', ['cle' => $cle, 'compte' => 1, 'debut' => maintenant()]);
        return true;
    }
    $debut = strtotime($r['debut'] . ' UTC');
    if ($now - $debut > $l['fenetre']) {
        maj('limites_taux', (int) $r['id'], ['compte' => 1, 'debut' => maintenant()]);
        return true;
    }
    if ((int) $r['compte'] >= $l['nb']) {
        journal('alerte', 'limite de debit atteinte', ['action' => $action, 'sujet' => $sujet]);
        return false;
    }
    maj('limites_taux', (int) $r['id'], ['compte' => (int) $r['compte'] + 1]);
    return true;
}

/* ------------------------------------------------------------------ */
/* Reglages en base                                                     */
/* ------------------------------------------------------------------ */

function reglage(string $cle, $defaut = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (qtous('SELECT cle, valeur FROM reglages') as $r) {
                $cache[$r['cle']] = $r['valeur'];
            }
        } catch (Throwable) { /* avant installation */ }
    }
    return $cache[$cle] ?? $defaut;
}

/* ------------------------------------------------------------------ */
/* Valeur absente = pastille visible, jamais un tiret discret           */
/* ------------------------------------------------------------------ */

function valeur($v, string $cle = ''): string
{
    if ($v !== null && $v !== '') return h($v);
    return '<span class="vide" data-vide="' . h($cle) . '">' . t('a_renseigner') . '</span>';
}

function audit(string $action, string $objet = '', array $detail = []): void
{
    try {
        insere('journal_audit', [
            'utilisateur_id' => utilisateur()['id'] ?? null,
            'action' => $action, 'objet' => $objet,
            'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'ip' => ip_client(), 'cree_le' => maintenant(),
        ]);
    } catch (Throwable $e) {
        journal('erreur', 'audit impossible : ' . $e->getMessage());
    }
}
