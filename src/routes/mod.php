<?php
/** File de moderation et journal des actions. */

declare(strict_types=1);

function page_moderation(): void
{
    $etat = in_array($_GET['etat'] ?? '', ETATS_SIGNALEMENT, true) ? $_GET['etat'] : '';
    $file = file_signalements($etat ? ['etat' => $etat] : []);
    foreach ($file as &$s) {
        $s['apercu'] = apercu_objet((string) $s['objet_type'], (int) $s['objet_id']);
    }
    unset($s);
    $forums = qtous('SELECT id, slug, titre_fr, titre_en, titre_ar FROM forums ORDER BY rang, id');
    $compte = [];
    foreach (ETATS_SIGNALEMENT as $e) {
        $compte[$e] = (int) qval('SELECT COUNT(*) FROM signalements WHERE etat = ?', [$e]);
    }
    meta(['titre' => t('mod_titre'), 'noindex' => true]);
    rendre('moderation', compact('file', 'etat', 'forums', 'compte'));
}

function post_revue(): void
{
    prendre_en_revue((int) ($_POST['signalement'] ?? 0));
    redirige('/moderation');
}

function post_action_mod(): void
{
    $r = action_moderation(
        (string) ($_POST['action'] ?? ''),
        (string) ($_POST['objet_type'] ?? ''),
        (int) ($_POST['objet_id'] ?? 0),
        [
            'signalement_id' => ($_POST['signalement'] ?? '') !== '' ? (int) $_POST['signalement'] : null,
            'motif'          => (string) ($_POST['motif'] ?? ''),
            'forum_id'       => (int) ($_POST['forum_id'] ?? 0),
            'discussion_id'  => (int) ($_POST['discussion_cible'] ?? 0),
            'jours'          => (int) ($_POST['jours'] ?? 7),
        ]
    );
    if (!empty($r['erreur'])) {
        http_response_code(422);
        rendre('erreur', ['code' => 422, 'message' => t('refuse_droit') . ' (' . $r['erreur'] . ')']);
        return;
    }
    redirige((string) ($_POST['retour'] ?? '/moderation'));
}

function page_journal_mod(): void
{
    $lignes = qtous('SELECT a.*, u.identifiant FROM actions_moderation a
                     LEFT JOIN utilisateurs u ON u.id = a.moderateur_id
                     ORDER BY a.cree_le DESC, a.id DESC LIMIT 300');
    meta(['titre' => t('mod_journal'), 'noindex' => true]);
    rendre('journal_mod', ['lignes' => $lignes]);
}
