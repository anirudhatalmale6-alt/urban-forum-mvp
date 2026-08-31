<?php
/**
 * API JSON v1.
 *
 * Elle appelle EXACTEMENT les memes fonctions de domaine que les pages
 * HTML : meme controle de permission (dans la table de routage), meme
 * verification CSRF sur les ecritures, meme assainissement du corps des
 * messages. C'est ce qui rend verifiable le critere de recette n°8 —
 * l'API ne peut pas etre plus permissive que l'interface, elles partagent
 * le chemin d'autorisation.
 */

declare(strict_types=1);

function api_racine(): void
{
    json_out([
        'nom' => cfg('nom_site'),
        'version' => '1',
        'langues' => cfg('langues'),
        'points' => [
            'GET  /api/v1/forums',
            'GET  /api/v1/forums/{slug}',
            'GET  /api/v1/discussions/{slug}?page=1',
            'GET  /api/v1/recherche?q=&espace=forum|projets&tri=pertinence|date|activite',
            'GET  /api/v1/autocomplete?q=',
            'GET  /api/v1/notifications',
            'POST /api/v1/apercu     (corps, _csrf)',
            'POST /api/v1/messages   (discussion, corps, _csrf)',
            'GET  /api/v1/moderation/file',
        ],
        'note' => "Authentification par le cookie de session. Les ecritures exigent "
                . "le jeton _csrf, comme les formulaires.",
    ]);
}

function api_forums(): void
{
    $tous = qtous('SELECT id, parent_id, slug, titre_fr, titre_en, titre_ar,
                          pays_id, ville_id, nb_discussions, nb_messages
                   FROM forums ORDER BY rang, id');
    $out = [];
    foreach ($tous as $f) {
        $ag = compteurs_agreges((int) $f['id'], $tous);
        $out[] = [
            'slug' => $f['slug'],
            'titre' => champ_langue($f, 'titre'),
            'parent' => $f['parent_id'] ? (string) qval('SELECT slug FROM forums WHERE id = ?',
                                                        [(int) $f['parent_id']]) : null,
            'discussions' => $ag['discussions'],
            'messages' => $ag['messages'],
        ];
    }
    json_out(['forums' => $out, 'total' => count($out)]);
}

function api_forum(string $slug): void
{
    $f = qun('SELECT * FROM forums WHERE slug = ?', [$slug]);
    if (!$f) { json_out(['erreur' => t('err_404'), 'code' => 404], 404); return; }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = (int) cfg('discussions_par_page');
    $debut = ($page - 1) * $pp;
    $d = qtous("SELECT d.slug, d.titre, d.cree_le, d.nb_vues, d.nb_reponses, d.epinglee,
                       d.verrouillee, u.identifiant AS auteur
                FROM discussions d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
                WHERE d.forum_id = ? AND d.masquee = 0
                ORDER BY d.epinglee DESC, d.dernier_message_le DESC
                LIMIT $pp OFFSET $debut", [(int) $f['id']]);
    json_out([
        'forum' => ['slug' => $f['slug'], 'titre' => champ_langue($f, 'titre')],
        'page' => $page,
        'total' => (int) qval('SELECT COUNT(*) FROM discussions WHERE forum_id = ? AND masquee = 0',
                              [(int) $f['id']]),
        'discussions' => $d,
    ]);
}

function api_discussion(string $slug): void
{
    $d = qun('SELECT * FROM discussions WHERE slug = ? AND masquee = 0', [$slug]);
    if (!$d) { json_out(['erreur' => t('err_404'), 'code' => 404], 404); return; }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pp = (int) cfg('messages_par_page');
    $debut = ($page - 1) * $pp;
    $m = qtous("SELECT m.id, m.position, m.corps, m.rendu, m.cree_le, m.modifie_le,
                       m.nb_editions, m.masque, u.identifiant AS auteur
                FROM messages m LEFT JOIN utilisateurs u ON u.id = m.auteur_id
                WHERE m.discussion_id = ? ORDER BY m.position
                LIMIT $pp OFFSET $debut", [(int) $d['id']]);
    foreach ($m as &$x) {
        if ((int) $x['masque'] === 1) { $x['corps'] = null; $x['rendu'] = null; }
    }
    unset($x);
    json_out([
        'discussion' => [
            'slug' => $d['slug'], 'titre' => $d['titre'],
            'vues' => (int) $d['nb_vues'], 'reponses' => (int) $d['nb_reponses'],
            'verrouillee' => (bool) $d['verrouillee'], 'epinglee' => (bool) $d['epinglee'],
        ],
        'page' => $page,
        'total' => (int) qval('SELECT COUNT(*) FROM messages WHERE discussion_id = ?', [(int) $d['id']]),
        'messages' => $m,
    ]);
}

function api_recherche(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') { json_out(['erreur' => 'parametre q requis', 'code' => 400], 400); return; }
    $r = recherche_executer($q, [
        'espace' => ($_GET['espace'] ?? 'forum') === 'projets' ? 'projets' : 'forum',
        'tri' => in_array($_GET['tri'] ?? '', ['pertinence', 'date', 'activite'], true)
               ? $_GET['tri'] : 'pertinence',
        'limite' => min(50, max(1, (int) ($_GET['limite'] ?? 25))),
    ]);
    json_out($r);
}

function api_autocomplete(): void
{
    json_out(['termes' => autocomplete((string) ($_GET['q'] ?? ''))]);
}

function api_apercu(): void
{
    $corps = (string) ($_POST['corps'] ?? '');
    json_out(['rendu' => rendre_message($corps)]);
}

function api_message(): void
{
    $u = utilisateur();
    $did = (int) ($_POST['discussion'] ?? 0);
    $corps = trim((string) ($_POST['corps'] ?? ''));

    $d = qun('SELECT * FROM discussions WHERE id = ? AND masquee = 0', [$did]);
    if (!$d) { json_out(['erreur' => t('err_404'), 'code' => 404], 404); return; }
    if ((int) $d['verrouillee'] === 1 && !peut('moderation.contenu')) {
        json_out(['erreur' => t('forum_verrouillee_avis'), 'code' => 403], 403); return;
    }
    if (mb_strlen($corps) < 2) {
        json_out(['erreur' => 'corps trop court', 'code' => 422], 422); return;
    }
    if (!limite_ok('message', (string) $u['id'])) {
        json_out(['erreur' => t('err_limite'), 'code' => 429], 429); return;
    }
    $mid = ecrire_message($did, (int) $u['id'], $corps);
    recompter_discussion($did);
    recompter_forum((int) $d['forum_id']);
    indexer_discussion($did);
    $n = notifier_nouveau_message($mid);
    audit('api.message', 'message#' . $mid);
    json_out(['id' => $mid, 'url' => '/d/' . $d['slug'] . '#m' . $mid,
              'notifications' => $n], 201);
}

function api_notifications(): void
{
    $u = utilisateur();
    $n = qtous('SELECT id, type, lien, lue, cree_le FROM notifications
                WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT 50', [(int) $u['id']]);
    json_out(['non_lues' => notifications_non_lues((int) $u['id']), 'notifications' => $n]);
}

function api_file_mod(): void
{
    json_out(['signalements' => file_signalements()]);
}
