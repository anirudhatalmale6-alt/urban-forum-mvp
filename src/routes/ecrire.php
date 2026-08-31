<?php
/** Ecriture : discussions, reponses, editions, reactions, signalements. */

declare(strict_types=1);

function page_nouvelle(array $erreurs = [], array $vals = []): void
{
    $forums = qtous('SELECT * FROM forums WHERE ferme = 0 ORDER BY rang, id');
    $forum_slug = (string) ($_GET['forum'] ?? ($vals['forum'] ?? ''));
    meta(['titre' => t('forum_nouvelle_discussion'), 'noindex' => true]);
    rendre('nouvelle', compact('forums', 'forum_slug', 'erreurs', 'vals'));
}

function post_nouvelle(): void
{
    $u = utilisateur();
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $corps = trim((string) ($_POST['corps'] ?? ''));
    $slug_forum = (string) ($_POST['forum'] ?? '');

    $erreurs = [];
    if (mb_strlen($titre) < 5 || mb_strlen($titre) > 200) $erreurs['titre'] = 'de 5 a 200 caracteres';
    if (mb_strlen($corps) < 10) $erreurs['corps'] = 'au moins 10 caracteres';
    $f = qun('SELECT * FROM forums WHERE slug = ? AND ferme = 0', [$slug_forum]);
    if (!$f) $erreurs['forum'] = 'forum inconnu';
    if (!limite_ok('message', (string) $u['id'])) $erreurs['global'] = t('err_limite');

    if ($erreurs) {
        http_response_code(422);
        page_nouvelle($erreurs, ['titre' => $titre, 'corps' => $corps, 'forum' => $slug_forum]);
        return;
    }

    $id = insere('discussions', [
        'forum_id' => (int) $f['id'], 'auteur_id' => (int) $u['id'],
        'titre' => $titre, 'slug' => slug_unique('discussions', slug($titre, 150)),
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
        'epinglee' => 0, 'verrouillee' => 0, 'masquee' => 0,
        'nb_vues' => 0, 'nb_reponses' => 0, 'nb_participants' => 1,
        'dernier_message_le' => maintenant(), 'demo' => 0,
    ]);
    $mid = ecrire_message($id, (int) $u['id'], $corps);

    // L'auteur suit sa propre discussion : sinon il ne recoit rien quand on
    // lui repond, ce qui est la premiere chose qu'on attend d'un forum.
    try {
        insere('abonnements', ['utilisateur_id' => (int) $u['id'], 'objet_type' => 'discussion',
                               'objet_id' => $id, 'cree_le' => maintenant()]);
    } catch (Throwable) {}

    recompter_discussion($id);
    recompter_forum((int) $f['id']);
    indexer_discussion($id);
    audit('discussion.creation', 'discussion#' . $id);

    $slug = (string) qval('SELECT slug FROM discussions WHERE id = ?', [$id]);
    redirige('/d/' . $slug . '#m' . $mid);
}

function post_repondre(): void
{
    $u = utilisateur();
    $did = (int) ($_POST['discussion'] ?? 0);
    $corps = trim((string) ($_POST['corps'] ?? ''));

    $d = qun('SELECT * FROM discussions WHERE id = ?', [$did]);
    if (!$d || (int) $d['masquee'] === 1) {
        http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return;
    }
    if ((int) $d['verrouillee'] === 1 && !peut('moderation.contenu')) {
        reponse_refus(403, t('forum_verrouillee_avis'));
    }
    if (mb_strlen($corps) < 2) redirige('/d/' . $d['slug']);
    if (!limite_ok('message', (string) $u['id'])) reponse_refus(429, t('err_limite'));

    $mid = ecrire_message($did, (int) $u['id'], $corps);
    recompter_discussion($did);
    recompter_forum((int) $d['forum_id']);
    indexer_discussion($did);
    notifier_nouveau_message($mid);
    audit('message.creation', 'message#' . $mid);

    $pp = (int) cfg('messages_par_page');
    $pos = (int) qval('SELECT position FROM messages WHERE id = ?', [$mid]);
    $page = (int) ceil($pos / $pp);
    redirige('/d/' . $d['slug'] . ($page > 1 ? '?page=' . $page : '') . '#m' . $mid);
}

function page_modifier(string $id, array $erreurs = []): void
{
    $m = charge_message_editable((int) $id);
    if (!$m) return;
    meta(['titre' => t('disc_modifier'), 'noindex' => true]);
    rendre('modifier', ['m' => $m, 'erreurs' => $erreurs]);
}

function charge_message_editable(int $id): ?array
{
    $u = utilisateur();
    $m = qun('SELECT m.*, d.slug, d.verrouillee FROM messages m
              JOIN discussions d ON d.id = m.discussion_id WHERE m.id = ?', [$id]);
    if (!$m) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return null; }
    $sien = (int) $m['auteur_id'] === (int) $u['id'];
    if (!$sien && !peut('moderation.contenu')) { reponse_refus(403, t('refuse_droit')); }
    if ($sien && (int) $m['verrouillee'] === 1 && !peut('moderation.contenu')) {
        reponse_refus(403, t('forum_verrouillee_avis'));
    }
    return $m;
}

function post_modifier(string $id): void
{
    $m = charge_message_editable((int) $id);
    if (!$m) return;
    $corps = trim((string) ($_POST['corps'] ?? ''));
    $motif = mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 255);
    if (mb_strlen($corps) < 2) { http_response_code(422); page_modifier($id, ['corps' => 'trop court']); return; }

    // L'historique est ecrit AVANT la mise a jour, avec le corps d'avant.
    // Ecrire apres reviendrait a archiver la nouvelle version.
    insere('revisions_message', [
        'message_id' => (int) $m['id'], 'editeur_id' => (int) utilisateur()['id'],
        'corps_avant' => (string) $m['corps'], 'motif' => $motif, 'cree_le' => maintenant(),
    ]);
    maj('messages', (int) $m['id'], [
        'corps' => $corps, 'rendu' => rendre_message($corps),
        'modifie_le' => maintenant(), 'nb_editions' => (int) $m['nb_editions'] + 1,
    ]);
    indexer_message((int) $m['id']);
    if ((int) $m['position'] === 1) indexer_discussion((int) $m['discussion_id']);
    audit('message.edition', 'message#' . $m['id'], ['motif' => $motif]);
    redirige('/d/' . $m['slug'] . '#m' . $m['id']);
}

function page_historique(string $id): void
{
    $m = qun('SELECT m.*, d.slug, d.titre, u.identifiant FROM messages m
              JOIN discussions d ON d.id = m.discussion_id
              LEFT JOIN utilisateurs u ON u.id = m.auteur_id WHERE m.id = ?', [(int) $id]);
    if (!$m) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }
    $revs = qtous('SELECT r.*, u.identifiant FROM revisions_message r
                   LEFT JOIN utilisateurs u ON u.id = r.editeur_id
                   WHERE r.message_id = ? ORDER BY r.cree_le DESC', [(int) $id]);
    meta(['titre' => t('disc_historique'), 'noindex' => true]);
    rendre('historique', compact('m', 'revs'));
}

function post_reagir(): void
{
    $u = utilisateur();
    $mid = (int) ($_POST['message'] ?? 0);
    $type = in_array($_POST['type'] ?? '', ['utile', 'accord', 'merci'], true) ? $_POST['type'] : 'utile';
    $m = qun('SELECT m.id, d.slug FROM messages m JOIN discussions d ON d.id = m.discussion_id
              WHERE m.id = ?', [$mid]);
    if (!$m) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('err_404')]); return; }

    $ex = qval('SELECT id FROM reactions WHERE message_id = ? AND utilisateur_id = ? AND type = ?',
               [$mid, (int) $u['id'], $type]);
    if ($ex !== null) q('DELETE FROM reactions WHERE id = ?', [(int) $ex]);
    else insere('reactions', ['message_id' => $mid, 'utilisateur_id' => (int) $u['id'],
                              'type' => $type, 'cree_le' => maintenant()]);
    redirige('/d/' . $m['slug'] . '#m' . $mid);
}

function post_abonnement(): void
{
    $u = utilisateur();
    $type = in_array($_POST['objet_type'] ?? '', ['discussion', 'forum'], true)
          ? $_POST['objet_type'] : 'discussion';
    $oid = (int) ($_POST['objet_id'] ?? 0);
    $ex = qval('SELECT id FROM abonnements WHERE utilisateur_id = ? AND objet_type = ? AND objet_id = ?',
               [(int) $u['id'], $type, $oid]);
    if ($ex !== null) q('DELETE FROM abonnements WHERE id = ?', [(int) $ex]);
    else insere('abonnements', ['utilisateur_id' => (int) $u['id'], 'objet_type' => $type,
                                'objet_id' => $oid, 'cree_le' => maintenant()]);
    $retour = $type === 'discussion'
        ? '/d/' . (string) qval('SELECT slug FROM discussions WHERE id = ?', [$oid])
        : '/f/' . (string) qval('SELECT slug FROM forums WHERE id = ?', [$oid]);
    redirige($retour);
}

function post_signet(): void
{
    $u = utilisateur();
    $did = (int) ($_POST['discussion'] ?? 0);
    $ex = qval('SELECT id FROM signets WHERE utilisateur_id = ? AND discussion_id = ?',
               [(int) $u['id'], $did]);
    if ($ex !== null) q('DELETE FROM signets WHERE id = ?', [(int) $ex]);
    else insere('signets', ['utilisateur_id' => (int) $u['id'], 'discussion_id' => $did,
                            'cree_le' => maintenant()]);
    redirige('/d/' . (string) qval('SELECT slug FROM discussions WHERE id = ?', [$did]));
}

function page_signaler(): void
{
    $type = in_array($_GET['type'] ?? '', ['message', 'discussion', 'utilisateur'], true)
          ? $_GET['type'] : 'message';
    $id = (int) ($_GET['id'] ?? 0);
    $apercu = apercu_objet($type, $id);
    meta(['titre' => t('sig_titre'), 'noindex' => true]);
    rendre('signaler', compact('type', 'id', 'apercu'));
}

function post_signaler(): void
{
    $type = (string) ($_POST['objet_type'] ?? 'message');
    $id = (int) ($_POST['objet_id'] ?? 0);
    $r = signaler($type, $id, (string) ($_POST['motif'] ?? ''), (string) ($_POST['commentaire'] ?? ''));
    if (!empty($r['erreur'])) {
        http_response_code(422);
        rendre('erreur', ['code' => 422, 'message' => t('err_limite')]);
        return;
    }
    $ap = apercu_objet($type, $id);
    meta(['titre' => t('sig_titre'), 'noindex' => true]);
    rendre('signale', ['retour' => $ap['url'] ?: '/']);
}

function post_bloquer(): void
{
    $u = utilisateur();
    $cible = (int) ($_POST['utilisateur'] ?? 0);
    if ($cible === (int) $u['id'] || $cible <= 0) redirige('/');
    $ex = qval('SELECT id FROM blocages WHERE utilisateur_id = ? AND bloque_id = ?',
               [(int) $u['id'], $cible]);
    if ($ex !== null) q('DELETE FROM blocages WHERE id = ?', [(int) $ex]);
    else insere('blocages', ['utilisateur_id' => (int) $u['id'], 'bloque_id' => $cible,
                             'cree_le' => maintenant()]);
    $p = (string) qval('SELECT identifiant FROM utilisateurs WHERE id = ?', [$cible]);
    redirige('/u/' . rawurlencode($p));
}
