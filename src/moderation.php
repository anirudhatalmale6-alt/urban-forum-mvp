<?php
/**
 * Moderation (section 6).
 *
 * Toute action passe par action_moderation(). C'est la seule ecriture dans
 * actions_moderation, donc le journal ne peut pas etre incomplet : il n'y a
 * pas de chemin qui modifie un contenu sans y passer. Un journal auquel il
 * manque une action est pire qu'aucun journal — on lui fait confiance.
 */

declare(strict_types=1);

const MOTIFS_SIGNALEMENT = ['spam', 'insulte', 'horssujet', 'faux', 'autre'];
const ETATS_SIGNALEMENT  = ['nouveau', 'en_revue', 'actionne', 'classe'];

function signaler(string $objet_type, int $objet_id, string $motif, string $commentaire = ''): array
{
    if (!in_array($objet_type, ['message', 'discussion', 'utilisateur'], true)) {
        return ['erreur' => 'objet'];
    }
    if (!in_array($motif, MOTIFS_SIGNALEMENT, true)) return ['erreur' => 'motif'];

    $u = utilisateur();
    if (!$u) return ['erreur' => 'connexion'];
    if (!limite_ok('signalement', (string) $u['id'])) return ['erreur' => 'limite'];

    // Deux fois le meme signalement par la meme personne ne cree pas deux
    // lignes : il remonte la priorite du signalement existant.
    $ex = qun('SELECT * FROM signalements WHERE signaleur_id = ? AND objet_type = ? AND objet_id = ?
               AND etat IN (?, ?)',
              [(int) $u['id'], $objet_type, $objet_id, 'nouveau', 'en_revue']);
    if ($ex) return ['id' => (int) $ex['id'], 'deja' => true];

    // La priorite monte avec le nombre de signalements DISTINCTS du meme
    // objet : trois personnes independantes valent mieux qu'une insistante.
    $n = (int) qval('SELECT COUNT(DISTINCT signaleur_id) FROM signalements
                     WHERE objet_type = ? AND objet_id = ? AND etat IN (?, ?)',
                    [$objet_type, $objet_id, 'nouveau', 'en_revue']);
    $priorite = $n >= 2 ? 'haute' : ($motif === 'spam' ? 'normale' : 'normale');

    $id = insere('signalements', [
        'signaleur_id' => (int) $u['id'],
        'objet_type' => $objet_type, 'objet_id' => $objet_id,
        'motif' => $motif, 'commentaire' => mb_substr($commentaire, 0, 2000),
        'priorite' => $priorite, 'etat' => 'nouveau', 'cree_le' => maintenant(),
    ]);
    audit('signalement', $objet_type . '#' . $objet_id, ['motif' => $motif]);
    return ['id' => $id];
}

function file_signalements(array $filtres = []): array
{
    $where = []; $p = [];
    if (!empty($filtres['etat'])) { $where[] = 'etat = ?'; $p[] = $filtres['etat']; }
    else { $where[] = 'etat IN (?, ?)'; $p[] = 'nouveau'; $p[] = 'en_revue'; }
    $sql = 'SELECT s.*, u.identifiant AS signaleur FROM signalements s
            LEFT JOIN utilisateurs u ON u.id = s.signaleur_id
            WHERE ' . implode(' AND ', $where) . "
            ORDER BY CASE s.priorite WHEN 'haute' THEN 0 WHEN 'normale' THEN 1 ELSE 2 END,
                     s.cree_le ASC LIMIT 200";
    return qtous($sql, $p);
}

/** Le resume lisible de l'objet signale, pour la file. */
function apercu_objet(string $type, int $id): array
{
    if ($type === 'message') {
        $m = qun('SELECT m.*, d.titre, d.slug, u.identifiant FROM messages m
                  JOIN discussions d ON d.id = m.discussion_id
                  LEFT JOIN utilisateurs u ON u.id = m.auteur_id WHERE m.id = ?', [$id]);
        if (!$m) return ['titre' => '—', 'url' => '', 'extrait' => '', 'auteur' => ''];
        return ['titre' => (string) $m['titre'], 'url' => '/d/' . $m['slug'] . '#m' . $id,
                'extrait' => extrait((string) $m['corps'], 200),
                'auteur' => (string) $m['identifiant'], 'masque' => (int) $m['masque']];
    }
    if ($type === 'discussion') {
        $d = qun('SELECT d.*, u.identifiant FROM discussions d
                  LEFT JOIN utilisateurs u ON u.id = d.auteur_id WHERE d.id = ?', [$id]);
        if (!$d) return ['titre' => '—', 'url' => '', 'extrait' => '', 'auteur' => ''];
        return ['titre' => (string) $d['titre'], 'url' => '/d/' . $d['slug'],
                'extrait' => '', 'auteur' => (string) $d['identifiant'],
                'masque' => (int) $d['masquee']];
    }
    $u = qun('SELECT * FROM utilisateurs WHERE id = ?', [$id]);
    return ['titre' => (string) ($u['identifiant'] ?? '—'),
            'url' => $u ? '/u/' . rawurlencode((string) $u['identifiant']) : '',
            'extrait' => (string) ($u['bio'] ?? ''), 'auteur' => (string) ($u['identifiant'] ?? '')];
}

const ACTIONS = ['masquer', 'demasquer', 'epingler', 'desepingler', 'verrouiller',
                 'deverrouiller', 'deplacer', 'fusionner', 'avertir', 'suspendre',
                 'bannir', 'debannir', 'classer'];

/**
 * Applique une action et l'ecrit dans le journal, dans cet ordre :
 * on ENREGISTRE d'abord ce qu'on s'apprete a faire (l'etat avant), puis on
 * le fait. Si l'action echoue a mi-chemin, il reste une trace de la
 * tentative — l'inverse laisserait un contenu modifie sans explication.
 */
function action_moderation(string $action, string $objet_type, int $objet_id,
                           array $opts = []): array
{
    if (!in_array($action, ACTIONS, true)) return ['erreur' => 'action inconnue'];
    $mod = utilisateur();
    if (!$mod) return ['erreur' => 'connexion'];

    $besoin = in_array($action, ['avertir', 'suspendre', 'bannir', 'debannir'], true)
        ? 'moderation.sanction' : 'moderation.contenu';
    if (!peut($besoin)) return ['erreur' => 'droit'];

    $avant = [];
    $detail = $opts;

    switch ($action) {
        case 'masquer':
        case 'demasquer':
            $v = $action === 'masquer' ? 1 : 0;
            if ($objet_type === 'message') {
                $avant['masque'] = (int) qval('SELECT masque FROM messages WHERE id = ?', [$objet_id]);
                maj('messages', $objet_id, ['masque' => $v]);
                $v ? desindexer('message', $objet_id) : indexer_message($objet_id);
            } else {
                $avant['masquee'] = (int) qval('SELECT masquee FROM discussions WHERE id = ?', [$objet_id]);
                maj('discussions', $objet_id, ['masquee' => $v]);
                $v ? desindexer('discussion', $objet_id) : indexer_discussion($objet_id);
            }
            break;

        case 'epingler': case 'desepingler':
            maj('discussions', $objet_id, ['epinglee' => $action === 'epingler' ? 1 : 0]);
            break;

        case 'verrouiller': case 'deverrouiller':
            maj('discussions', $objet_id, ['verrouillee' => $action === 'verrouiller' ? 1 : 0]);
            break;

        case 'deplacer':
            $cible = (int) ($opts['forum_id'] ?? 0);
            if (!qval('SELECT id FROM forums WHERE id = ?', [$cible])) return ['erreur' => 'forum'];
            $avant['forum_id'] = (int) qval('SELECT forum_id FROM discussions WHERE id = ?', [$objet_id]);
            maj('discussions', $objet_id, ['forum_id' => $cible]);
            recompter_forum($avant['forum_id']); recompter_forum($cible);
            break;

        case 'fusionner':
            $cible = (int) ($opts['discussion_id'] ?? 0);
            if ($cible === $objet_id || !qval('SELECT id FROM discussions WHERE id = ?', [$cible])) {
                return ['erreur' => 'cible'];
            }
            $pos = (int) qval('SELECT COALESCE(MAX(position),0) FROM messages WHERE discussion_id = ?', [$cible]);
            foreach (qtous('SELECT id FROM messages WHERE discussion_id = ? ORDER BY position', [$objet_id]) as $m) {
                $pos++;
                maj('messages', (int) $m['id'], ['discussion_id' => $cible, 'position' => $pos]);
                indexer_message((int) $m['id']);
            }
            maj('discussions', $objet_id, ['fusionnee_dans' => $cible, 'masquee' => 1]);
            desindexer('discussion', $objet_id);
            recompter_discussion($cible); recompter_discussion($objet_id);
            $detail['fusionnee_dans'] = $cible;
            break;

        case 'avertir':
            notifier($objet_id, 'moderation', ['acteur_id' => (int) $mod['id'], 'lien' => '/notifications']);
            break;

        case 'suspendre':
            $jours = max(1, min(365, (int) ($opts['jours'] ?? 7)));
            $avant['suspendu_jusqu'] = qval('SELECT suspendu_jusqu FROM utilisateurs WHERE id = ?', [$objet_id]);
            maj('utilisateurs', $objet_id, ['suspendu_jusqu' => gmdate('Y-m-d H:i:s', time() + $jours * 86400)]);
            $detail['jours'] = $jours;
            notifier($objet_id, 'moderation', ['acteur_id' => (int) $mod['id'], 'lien' => '/notifications']);
            break;

        case 'bannir': case 'debannir':
            $cible_role = role_de(qun('SELECT * FROM utilisateurs WHERE id = ?', [$objet_id]));
            $mon_role = role_de($mod);
            // Un moderateur ne sanctionne pas quelqu'un de rang superieur ou
            // egal. Sans cette ligne, deux moderateurs peuvent se bannir.
            if (($cible_role['rang'] ?? 0) >= ($mon_role['rang'] ?? 0)) return ['erreur' => 'rang'];
            maj('utilisateurs', $objet_id, ['banni' => $action === 'bannir' ? 1 : 0]);
            if ($action === 'bannir') q('DELETE FROM sessions WHERE utilisateur_id = ?', [$objet_id]);
            break;

        case 'classer':
            break;   // ne touche que le signalement, plus bas
    }

    if ($objet_type === 'message') {
        $did = (int) qval('SELECT discussion_id FROM messages WHERE id = ?', [$objet_id]);
        if ($did) recompter_discussion($did);
    } elseif ($objet_type === 'discussion' && in_array($action, ['masquer', 'demasquer'], true)) {
        $fid = (int) qval('SELECT forum_id FROM discussions WHERE id = ?', [$objet_id]);
        if ($fid) recompter_forum($fid);
    }

    $detail['avant'] = $avant;
    insere('actions_moderation', [
        'moderateur_id' => (int) $mod['id'], 'action' => $action,
        'objet_type' => $objet_type, 'objet_id' => $objet_id,
        'signalement_id' => $opts['signalement_id'] ?? null,
        'motif' => mb_substr((string) ($opts['motif'] ?? ''), 0, 2000),
        'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
        'cree_le' => maintenant(),
    ]);

    if (!empty($opts['signalement_id'])) {
        maj('signalements', (int) $opts['signalement_id'], [
            'etat' => $action === 'classer' ? 'classe' : 'actionne',
            'traite_par' => (int) $mod['id'], 'traite_le' => maintenant(),
        ]);
    }
    audit('moderation.' . $action, $objet_type . '#' . $objet_id);
    return ['ok' => true];
}

function prendre_en_revue(int $signalement_id): void
{
    $mod = utilisateur();
    if (!$mod || !peut('moderation.file')) return;
    maj('signalements', $signalement_id, ['etat' => 'en_revue', 'traite_par' => (int) $mod['id']]);
    audit('moderation.revue', 'signalement#' . $signalement_id);
}

/* ------------------------------------------------------------------ */
/* Compteurs                                                           */
/* ------------------------------------------------------------------ */

function recompter_discussion(int $id): void
{
    $n = qun('SELECT COUNT(*) n, COUNT(DISTINCT auteur_id) p, MAX(id) dernier,
                     MAX(cree_le) le
              FROM messages WHERE discussion_id = ? AND masque = 0', [$id]);
    maj('discussions', $id, [
        'nb_reponses'         => max(0, (int) $n['n'] - 1),
        'nb_participants'     => (int) $n['p'],
        'dernier_message_id'  => $n['dernier'] ? (int) $n['dernier'] : null,
        'dernier_message_le'  => $n['le'],
    ]);
}

function recompter_forum(int $id): void
{
    $d = qun('SELECT COUNT(*) n FROM discussions WHERE forum_id = ? AND masquee = 0', [$id]);
    $m = qun('SELECT COUNT(*) n, MAX(m.id) dernier FROM messages m
              JOIN discussions d ON d.id = m.discussion_id
              WHERE d.forum_id = ? AND m.masque = 0 AND d.masquee = 0', [$id]);
    maj('forums', $id, [
        'nb_discussions' => (int) $d['n'],
        'nb_messages'    => (int) $m['n'],
        'dernier_message_id' => $m['dernier'] ? (int) $m['dernier'] : null,
    ]);
    // Volontairement PAS de remontee recursive vers le forum parent. Les
    // colonnes stockees comptent ce que le forum contient LUI-MEME ; le
    // total avec ses enfants est calcule a l'affichage par
    // compteurs_agreges(). Une recursion ici boucle des qu'un parent_id
    // pointe, par accident d'administration, sur un descendant.
}

/** Total d'un forum avec toute sa descendance, calcule a la lecture. */
function compteurs_agreges(int $id, array $tous): array
{
    $d = 0; $m = 0;
    $pile = [$id]; $vus = [];
    while ($pile) {
        $cur = array_pop($pile);
        if (isset($vus[$cur])) continue;      // garde-fou anti-cycle
        $vus[$cur] = true;
        foreach ($tous as $f) {
            if ((int) $f['id'] === $cur) {
                $d += (int) $f['nb_discussions'];
                $m += (int) $f['nb_messages'];
            }
            if ((int) ($f['parent_id'] ?? 0) === $cur) $pile[] = (int) $f['id'];
        }
    }
    return ['discussions' => $d, 'messages' => $m];
}
