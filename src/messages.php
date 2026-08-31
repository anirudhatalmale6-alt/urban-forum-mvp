<?php
/**
 * Ecriture d'un message — la fonction de domaine.
 *
 * Elle vivait dans src/routes/ecrire.php, c'est-a-dire dans un controleur.
 * Elle est ici parce que TROIS appelants la partagent : le formulaire HTML,
 * l'API JSON et les scripts de controle. Une regle metier qui habite un
 * controleur n'est partageable que par copie, et deux copies divergent.
 */

declare(strict_types=1);

/**
 * Insere un message, met a jour tout ce qui en depend, et rend son id.
 * Ne notifie PAS : la notification est un effet separe, declenche par
 * l'appelant, parce que la creation d'une discussion et la reponse a une
 * discussion ne notifient pas les memes personnes.
 */
function ecrire_message(int $discussion_id, int $auteur_id, string $corps): int
{
    $pos = (int) qval('SELECT COALESCE(MAX(position), 0) + 1 FROM messages WHERE discussion_id = ?',
                      [$discussion_id]);
    $id = insere('messages', [
        'discussion_id' => $discussion_id,
        'auteur_id'     => $auteur_id,
        'corps'         => $corps,
        // Le rendu est calcule A L'ECRITURE et stocke. La lecture d'une
        // discussion tres suivie ne rejoue donc pas l'analyse du texte a
        // chaque affichage, et une edition du moteur de rendu ne peut pas
        // changer retroactivement le sens d'un vieux message.
        'rendu'         => rendre_message($corps),
        'cree_le'       => maintenant(),
        'nb_editions'   => 0,
        'masque'        => 0,
        'position'      => $pos,
        'ip'            => ip_client(),
        'demo'          => 0,
    ]);
    q('UPDATE utilisateurs SET nb_messages = nb_messages + 1 WHERE id = ?', [$auteur_id]);
    maj('discussions', $discussion_id, [
        'dernier_message_id' => $id,
        'dernier_message_le' => maintenant(),
        'maj_le'             => maintenant(),
    ]);
    indexer_message($id);
    return $id;
}
