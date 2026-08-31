<?php
/** Administration : tableau de bord, taxonomie, roles, journaux, export. */

declare(strict_types=1);

function page_admin(): void
{
    // Toutes les valeurs sont COMPTEES. Aucune n'est estimee, et le tableau
    // de bord n'affiche pas de graphique de tendance : il n'y a pas encore
    // d'historique pour en tracer un.
    $j30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
    $j7  = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    $j1  = gmdate('Y-m-d H:i:s', time() - 86400);

    $chiffres = [
        'membres'        => (int) qval('SELECT COUNT(*) FROM utilisateurs WHERE banni = 0'),
        'inscriptions'   => (int) qval('SELECT COUNT(*) FROM utilisateurs WHERE cree_le >= ?', [$j30]),
        'messages_24h'   => (int) qval('SELECT COUNT(*) FROM messages WHERE cree_le >= ?', [$j1]),
        'actives_7j'     => (int) qval('SELECT COUNT(*) FROM discussions WHERE dernier_message_le >= ?', [$j7]),
        'projets'        => (int) qval('SELECT COUNT(*) FROM projets'),
        'signalements'   => (int) qval('SELECT COUNT(*) FROM signalements WHERE etat IN (?, ?)',
                                       ['nouveau', 'en_revue']),
        'stockage'       => stockage_total(),
        'medias'         => (int) qval('SELECT COUNT(*) FROM medias'),
    ];

    $plus_vues = qtous('SELECT titre, slug, nb_vues, nb_reponses FROM discussions
                        WHERE masquee = 0 ORDER BY nb_vues DESC, id DESC LIMIT 10');
    $vides = qtous('SELECT * FROM recherches_vides ORDER BY compte DESC, vu_le DESC LIMIT 15');
    $audit = qtous('SELECT a.*, u.identifiant FROM journal_audit a
                    LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
                    ORDER BY a.id DESC LIMIT 40');
    $index = (int) qval('SELECT COUNT(*) FROM index_recherche');

    meta(['titre' => t('adm_titre'), 'noindex' => true]);
    rendre('admin', compact('chiffres', 'plus_vues', 'vides', 'audit', 'index'));
}

function page_taxonomie(array $message = []): void
{
    $continents = qtous('SELECT * FROM continents ORDER BY rang, id');
    $pays = qtous('SELECT p.*, c.slug AS continent_slug FROM pays p
                   JOIN continents c ON c.id = p.continent_id ORDER BY p.nom_en');
    $villes = qtous('SELECT v.*, p.slug AS pays_slug FROM villes v
                     JOIN pays p ON p.id = v.pays_id ORDER BY p.nom_en, v.nom_en');
    $categories = qtous('SELECT * FROM categories ORDER BY type, rang, id');
    meta(['titre' => t('adm_taxonomie'), 'noindex' => true]);
    rendre('taxonomie', compact('continents', 'pays', 'villes', 'categories', 'message'));
}

function post_taxonomie(): void
{
    $quoi = (string) ($_POST['quoi'] ?? '');
    $msg = ['type' => 'ok', 'texte' => t('enregistrer')];

    if ($quoi === 'ville') {
        $pays_id = (int) ($_POST['pays_id'] ?? 0);
        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '' || !qval('SELECT id FROM pays WHERE id = ?', [$pays_id])) {
            $msg = ['type' => 'erreur', 'texte' => 'Pays ou nom manquant.'];
        } else {
            $lat = trim((string) ($_POST['latitude'] ?? ''));
            $lon = trim((string) ($_POST['longitude'] ?? ''));
            insere('villes', [
                'pays_id' => $pays_id,
                'slug' => slug_unique('villes', slug($nom), 'slug'),
                'nom_fr' => $nom, 'nom_en' => $nom, 'nom_ar' => trim((string) ($_POST['nom_ar'] ?? '')),
                // Une coordonnee absente reste NULL. On ne met pas 0/0 :
                // c'est un point reel au large du golfe de Guinee, et il
                // apparaitrait sur la carte comme une ville qui y serait.
                'latitude' => $lat === '' ? null : (float) $lat,
                'longitude' => $lon === '' ? null : (float) $lon,
                'coord_approx' => isset($_POST['approx']) ? 1 : 0,
            ]);
            audit('taxonomie.ville', $nom);
        }
    } elseif ($quoi === 'categorie') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $type = ($_POST['type'] ?? 'secteur') === 'typologie' ? 'typologie' : 'secteur';
        if ($nom === '') $msg = ['type' => 'erreur', 'texte' => 'Nom manquant.'];
        else {
            insere('categories', [
                'slug' => slug($nom), 'type' => $type,
                'nom_fr' => $nom, 'nom_en' => $nom,
                'nom_ar' => trim((string) ($_POST['nom_ar'] ?? '')),
                'rang' => (int) ($_POST['rang'] ?? 100),
            ]);
            audit('taxonomie.categorie', $nom);
        }
    }
    page_taxonomie($msg);
}

function page_permissions(): void
{
    $roles = qtous('SELECT r.*, (SELECT COUNT(*) FROM utilisateurs u WHERE u.role_id = r.id) AS nb
                    FROM roles r ORDER BY r.rang');
    $membres = qtous('SELECT u.id, u.identifiant, u.email, u.banni, u.suspendu_jusqu,
                             u.cree_le, u.nb_messages, r.cle AS role_cle
                      FROM utilisateurs u LEFT JOIN roles r ON r.id = u.role_id
                      ORDER BY u.id DESC LIMIT 200');
    meta(['titre' => t('adm_permissions'), 'noindex' => true]);
    rendre('permissions', compact('roles', 'membres'));
}

function post_role(): void
{
    $uid = (int) ($_POST['utilisateur'] ?? 0);
    $cle = (string) ($_POST['role'] ?? '');
    if (!isset(ROLES[$cle])) { redirige('/admin/permissions'); }

    $moi = utilisateur();
    if ($uid === (int) $moi['id']) {
        // Se retirer soi-meme le role d'administrateur peut laisser la
        // plateforme sans administrateur du tout. On refuse.
        http_response_code(422);
        rendre('erreur', ['code' => 422,
            'message' => "Un administrateur ne change pas son propre role."]);
        return;
    }
    $rid = (int) qval('SELECT id FROM roles WHERE cle = ?', [$cle]);
    maj('utilisateurs', $uid, ['role_id' => $rid]);
    audit('role.changement', 'utilisateur#' . $uid, ['role' => $cle]);
    redirige('/admin/permissions');
}

function page_journal_erreurs(): void
{
    $dir = cfg('chemin_journal');
    $fichiers = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];
    rsort($fichiers);
    $choisi = (string) ($_GET['f'] ?? ($fichiers[0] ?? ''));
    $lignes = [];
    if ($choisi !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $choisi)
        && is_file($dir . '/' . $choisi)) {
        $brut = file($dir . '/' . $choisi, FILE_IGNORE_NEW_LINES) ?: [];
        $brut = array_slice($brut, -300);
        foreach (array_reverse($brut) as $l) {
            $j = json_decode($l, true);
            if (is_array($j)) $lignes[] = $j;
        }
    }
    meta(['titre' => t('adm_journal_erreurs'), 'noindex' => true]);
    rendre('journal_erreurs', compact('fichiers', 'choisi', 'lignes'));
}

function export_csv(): void
{
    $quoi = in_array($_GET['quoi'] ?? '', ['membres', 'discussions', 'signalements'], true)
          ? $_GET['quoi'] : 'membres';

    $lignes = match ($quoi) {
        'discussions' => qtous('SELECT d.id, d.titre, d.slug, d.cree_le, d.nb_vues, d.nb_reponses,
                                       u.identifiant AS auteur, f.slug AS forum
                                FROM discussions d
                                LEFT JOIN utilisateurs u ON u.id = d.auteur_id
                                LEFT JOIN forums f ON f.id = d.forum_id ORDER BY d.id'),
        'signalements' => qtous('SELECT s.id, s.objet_type, s.objet_id, s.motif, s.priorite,
                                        s.etat, s.cree_le, s.traite_le, u.identifiant AS signaleur
                                 FROM signalements s
                                 LEFT JOIN utilisateurs u ON u.id = s.signaleur_id ORDER BY s.id'),
        default => qtous('SELECT u.id, u.identifiant, u.cree_le, u.nb_messages, u.banni,
                                 r.cle AS role FROM utilisateurs u
                          LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.id'),
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $quoi . '-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM UTF-8 : sans lui, Excel affiche les accents en mojibake et le
    // client conclut que l'export est casse.
    fwrite($out, "\xEF\xBB\xBF");
    if ($lignes) fputcsv($out, array_keys($lignes[0]), ',', '"', '\\');
    foreach ($lignes as $l) fputcsv($out, array_values($l), ',', '"', '\\');
    fclose($out);
    audit('export', $quoi, ['lignes' => count($lignes)]);
    exit;
}

function post_reindexer(): void
{
    $n = reindexer_tout();
    audit('reindexation', '', $n);
    redirige('/admin');
}
