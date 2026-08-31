<?php
/**
 * Critere de recette n°4 : « Un moderateur peut traiter un signalement et
 * appliquer une action TRACABLE. »
 *
 * On joue le parcours entier avec deux comptes differents — celui qui
 * signale et celui qui modere — puis on relit le journal. Un test qui
 * appellerait action_moderation() en tant qu'administrateur prouverait que
 * la fonction marche, pas que le parcours marche.
 *
 *   php tests/moderation.php <pseudo_du_signaleur>
 */

require __DIR__ . '/_amorce.php';

$signaleur_pseudo = $argv[1] ?? '';
$resultat = [];

/* --- Le message a signaler : un message de demonstration ------------- */
$cible = qun('SELECT m.*, d.slug FROM messages m JOIN discussions d ON d.id = m.discussion_id
              WHERE m.demo = 1 AND m.masque = 0 ORDER BY m.id DESC LIMIT 1');
if (!$cible) { fwrite(STDERR, "aucun message de demonstration a signaler\n"); exit(1); }
$mid = (int) $cible['id'];

/* --- 1. Un membre signale ------------------------------------------- */
en_tant_que($signaleur_pseudo);
$r = signaler('message', $mid, 'horssujet', 'Signalement produit par la suite de controle.');
$resultat['signalement_id'] = (int) ($r['id'] ?? 0);
$sid = $resultat['signalement_id'];

/* --- 2. Un moderateur le prend en revue ------------------------------ */
$mod = en_tant_que('nour_h');
$role = role_de($mod);
$resultat['moderateur_role'] = $role['cle'];
prendre_en_revue($sid);
$resultat['etat_revue'] = (string) qval('SELECT etat FROM signalements WHERE id = ?', [$sid]);

/* --- 3. Il applique une action --------------------------------------- */
$avant_journal = (int) qval('SELECT COUNT(*) FROM actions_moderation');
$indexe_avant = (int) qval('SELECT COUNT(*) FROM index_recherche
                            WHERE objet_type = ? AND objet_id = ?', ['message', $mid]);

$act = action_moderation('masquer', 'message', $mid, [
    'signalement_id' => $sid,
    'motif' => 'Controle automatique : masquage puis retour a l\'etat initial.',
]);
$resultat['action'] = $act;
$resultat['masque'] = (int) qval('SELECT masque FROM messages WHERE id = ?', [$mid]);
$resultat['etat_final'] = (string) qval('SELECT etat FROM signalements WHERE id = ?', [$sid]);
$resultat['journal'] = (int) qval('SELECT COUNT(*) FROM actions_moderation') - $avant_journal;
$resultat['indexe_avant'] = $indexe_avant;
$resultat['indexe_apres'] = (int) qval('SELECT COUNT(*) FROM index_recherche
                                        WHERE objet_type = ? AND objet_id = ?', ['message', $mid]);

/* --- 4. Le journal doit dire QUI, QUOI, SUR QUOI, QUAND -------------- */
$ligne = qun('SELECT a.*, u.identifiant FROM actions_moderation a
              LEFT JOIN utilisateurs u ON u.id = a.moderateur_id
              ORDER BY a.id DESC LIMIT 1');
$resultat['journal_detail'] = [
    'moderateur' => $ligne['identifiant'] ?? null,
    'action' => $ligne['action'] ?? null,
    'objet' => ($ligne['objet_type'] ?? '') . '#' . ($ligne['objet_id'] ?? ''),
    'signalement' => $ligne['signalement_id'] ?? null,
    'quand' => $ligne['cree_le'] ?? null,
];
$resultat['journal_complet'] =
       ($ligne['identifiant'] ?? '') === 'nour_h'
    && ($ligne['action'] ?? '') === 'masquer'
    && (int) ($ligne['objet_id'] ?? 0) === $mid
    && (int) ($ligne['signalement_id'] ?? 0) === $sid
    && !empty($ligne['cree_le'])
    && !empty($ligne['motif']);

/* --- 5. On remet le message en place et on verifie l'index ----------- */
action_moderation('demasquer', 'message', $mid, ['motif' => 'Fin du controle.']);
$resultat['indexe_restaure'] = (int) qval('SELECT COUNT(*) FROM index_recherche
                                           WHERE objet_type = ? AND objet_id = ?', ['message', $mid]);
$resultat['masque_restaure'] = (int) qval('SELECT masque FROM messages WHERE id = ?', [$mid]);

/* --- 6. Un moderateur ne sanctionne pas un rang superieur ou egal ---- */
$admin = qun('SELECT u.* FROM utilisateurs u JOIN roles r ON r.id = u.role_id
              WHERE r.cle = ? LIMIT 1', ['administrateur']);
$r2 = action_moderation('bannir', 'utilisateur', (int) $admin['id'], []);
$resultat['bannir_admin'] = $r2['erreur'] ?? 'AUTORISE';

/* --- 7. Le signalement est referme ----------------------------------- */
maj('signalements', $sid, ['etat' => 'classe']);

sortir($resultat);
