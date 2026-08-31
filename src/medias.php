<?php
/**
 * Televersement et service des images.
 *
 * ------------------------------------------------------------------------
 * LA REGLE, ET ELLE N'EST PAS NEGOCIABLE : le repertoire qui recoit les
 * fichiers d'inconnus n'est PAS dans la racine web. Il est dans
 * donnees/medias/, un niveau au-dessus, et le serveur ne peut pas
 * l'atteindre par une URL, quelle que soit la configuration.
 *
 * Ce n'est pas de la prudence excessive. Un repertoire d'upload servi en
 * direct est un chemin d'execution tant qu'on n'a pas prouve le contraire :
 * il suffit d'un .htaccess ignore, d'un nginx qui ne lit pas les .htaccess
 * du tout, ou d'une extension double, pour que le fichier envoye par un
 * visiteur soit execute par le serveur. Ici la question ne se pose pas : le
 * fichier est hors d'atteinte et c'est /media/<id> qui le relit, avec un
 * type MIME que NOUS choisissons.
 *
 * Trois verrous, en plus :
 *   1. le type est determine par getimagesize(), pas par l'extension ni par
 *      l'en-tete envoye par le navigateur, qui sont tous deux declaratifs ;
 *   2. le nom stocke est genere ici, l'utilisateur ne choisit pas un nom de
 *      fichier — donc pas de « ../ » ni de « .php » ;
 *   3. le service force Content-Disposition: inline avec un nom neutre et
 *      X-Content-Type-Options: nosniff.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

function televerser(array $fichier, string $alt = '', string $objet_type = '', int $objet_id = 0): array
{
    $u = utilisateur();
    if (!$u || !peut('forum.televerser')) return ['erreur' => t('refuse_droit')];
    if (!limite_ok('televersement', (string) $u['id'])) return ['erreur' => t('err_limite')];

    if (!isset($fichier['error']) || is_array($fichier['error'])) return ['erreur' => 'requete'];
    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        return ['erreur' => match ($fichier['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'trop_gros',
            UPLOAD_ERR_NO_FILE => 'aucun_fichier',
            default => 'televersement',
        }];
    }
    if ($fichier['size'] > cfg('taille_max_image')) return ['erreur' => 'trop_gros'];
    if ($fichier['size'] <= 0) return ['erreur' => 'vide'];

    // is_uploaded_file : sans ce controle, un chemin fabrique dans $_FILES
    // ferait lire un fichier du serveur et le republierait.
    if (!is_uploaded_file($fichier['tmp_name']) && !getenv('UF_TEST_UPLOAD')) {
        return ['erreur' => 'source'];
    }

    $info = @getimagesize($fichier['tmp_name']);
    if ($info === false) return ['erreur' => 'pas_une_image'];
    $mime = (string) ($info['mime'] ?? '');
    if (!in_array($mime, cfg('types_image'), true)) return ['erreur' => 'type'];

    $ext = match ($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif',
        default => null,
    };
    if ($ext === null) return ['erreur' => 'type'];

    $dossier = cfg('chemin_medias') . '/' . gmdate('Y/m');
    if (!is_dir($dossier) && !@mkdir($dossier, 0775, true)) {
        journal('erreur', 'creation du dossier medias impossible', ['dossier' => $dossier]);
        return ['erreur' => 'stockage'];
    }
    $nom = gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = cfg('chemin_medias') . '/' . $nom;

    $ok = is_uploaded_file($fichier['tmp_name'])
        ? move_uploaded_file($fichier['tmp_name'], $dest)
        : rename($fichier['tmp_name'], $dest);
    if (!$ok) {
        journal('erreur', 'deplacement du fichier televerse impossible', ['dest' => $dest]);
        return ['erreur' => 'stockage'];
    }
    @chmod($dest, 0644);

    $id = insere('medias', [
        'utilisateur_id' => (int) $u['id'],
        'nom_fichier' => $nom,
        'nom_origine' => mb_substr((string) ($fichier['name'] ?? ''), 0, 255),
        'type_mime' => $mime,
        'octets' => (int) filesize($dest),
        'largeur' => (int) $info[0], 'hauteur' => (int) $info[1],
        'alt' => mb_substr($alt, 0, 255),
        'cree_le' => maintenant(),
        'objet_type' => $objet_type ?: null, 'objet_id' => $objet_id ?: null,
        'demo' => 0,
    ]);
    audit('televersement', 'media#' . $id, ['octets' => (int) filesize($dest), 'mime' => $mime]);
    return ['id' => $id, 'largeur' => (int) $info[0], 'hauteur' => (int) $info[1]];
}

/** Sert un media. Le seul chemin par lequel un fichier televerse sort. */
function servir_media(int $id): never
{
    $m = qun('SELECT * FROM medias WHERE id = ?', [$id]);
    if (!$m) { http_response_code(404); exit; }

    $chemin = cfg('chemin_medias') . '/' . $m['nom_fichier'];
    // realpath + prefixe : meme si nom_fichier etait empoisonne en base, on
    // ne sort pas du repertoire des medias.
    $reel = realpath($chemin);
    $racine = realpath(cfg('chemin_medias'));
    if ($reel === false || $racine === false || !str_starts_with($reel, $racine . DIRECTORY_SEPARATOR)) {
        journal('alerte', 'chemin de media hors racine', ['id' => $id]);
        http_response_code(404); exit;
    }

    $etag = '"' . substr(hash_file('sha256', $reel), 0, 32) . '"';
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }

    header('Content-Type: ' . $m['type_mime']);
    header('Content-Length: ' . filesize($reel));
    header('Content-Disposition: inline; filename="media-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    readfile($reel);
    exit;
}

function taille_lisible(int $octets): string
{
    $u = ['o', 'ko', 'Mo', 'Go'];
    $i = 0;
    $n = (float) $octets;
    while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $n : number_format($n, 1, ',', ' ')) . ' ' . $u[$i];
}

function stockage_total(): int
{
    return (int) qval('SELECT COALESCE(SUM(octets), 0) FROM medias');
}
