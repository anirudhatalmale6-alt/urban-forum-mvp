<?php
/**
 * Recherche : index inverse maison.
 *
 * Pourquoi pas FULLTEXT MySQL ni FTS5 SQLite : parce que le projet doit
 * tourner a l'identique sur les deux, et parce que la section 4.4 demande
 * deux index SEPARES (forum / fiches structurees) avec des synonymes
 * administrables. Un index maison donne les deux et se deplace avec le zip.
 *
 * Limite assumee, ecrite ici pour ne pas la decouvrir en production : la
 * tolerance aux fautes de frappe compare la requete aux termes distincts de
 * l'index. C'est bon jusqu'a quelques centaines de milliers de messages. Au
 * dela, la section 8 du cahier des charges recommande OpenSearch, et c'est
 * le bon moment pour y passer — le reste du code ne bouge pas, seule
 * recherche_executer() change.
 */

declare(strict_types=1);

const MOTS_VIDES = [
    'fr' => ['le','la','les','de','des','du','un','une','et','ou','a','au','aux','en','dans',
             'pour','par','sur','avec','ce','cette','ces','que','qui','est','sont','il','elle'],
    'en' => ['the','a','an','of','and','or','to','in','on','for','with','is','are','this',
             'that','these','it','at','by','from','as'],
    'ar' => ['في','من','على','عن','إلى','و','أو','هذا','هذه','التي','الذي','مع','كان','هو','هي'],
];

function normalise_terme(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        // On enleve les diacritiques latins (metro = métro) SANS toucher a
        // l'arabe : « Latin-ASCII » ne s'applique qu'au script latin.
        $t = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $s);
        if ($t !== false) $s = $t;
    }
    return mb_substr($s, 0, 64, 'UTF-8');
}

function tokenise(string $texte): array
{
    $texte = preg_replace('/\s+/u', ' ', strip_tags($texte)) ?? $texte;
    $bruts = preg_split('/[^\p{L}\p{N}]+/u', $texte, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $vides = array_merge(...array_values(MOTS_VIDES));
    $out = [];
    foreach ($bruts as $b) {
        $t = normalise_terme($b);
        if (mb_strlen($t) < 2) continue;
        if (in_array($t, $vides, true)) continue;
        $out[] = $t;
    }
    return $out;
}

/**
 * (Re)indexe un objet. On supprime AVANT d'inserer : sans cela une edition
 * laisse les anciens termes dans l'index et le message reste trouvable par
 * un mot qu'il ne contient plus.
 */
function indexer(string $espace, string $type, int $id, array $champs): void
{
    q('DELETE FROM index_recherche WHERE objet_type = ? AND objet_id = ?', [$type, $id]);
    $poids_par_terme = [];
    foreach ($champs as $poids => $texte) {
        foreach (tokenise((string) $texte) as $t) {
            $poids_par_terme[$t] = ($poids_par_terme[$t] ?? 0) + (int) $poids;
        }
    }
    foreach ($poids_par_terme as $terme => $poids) {
        insere('index_recherche', [
            'terme' => $terme, 'espace' => $espace,
            'objet_type' => $type, 'objet_id' => $id, 'poids' => $poids,
        ]);
    }
}

function desindexer(string $type, int $id): void
{
    q('DELETE FROM index_recherche WHERE objet_type = ? AND objet_id = ?', [$type, $id]);
}

function indexer_discussion(int $id): void
{
    $d = qun('SELECT * FROM discussions WHERE id = ?', [$id]);
    if (!$d) return;
    $premier = qval('SELECT corps FROM messages WHERE discussion_id = ? ORDER BY position LIMIT 1', [$id]);
    indexer('forum', 'discussion', $id, [5 => $d['titre'], 2 => (string) $premier]);
}

function indexer_message(int $id): void
{
    $m = qun('SELECT * FROM messages WHERE id = ?', [$id]);
    if (!$m) return;
    indexer('forum', 'message', $id, [1 => $m['corps']]);
}

function synonymes_de(string $terme): array
{
    $r = qtous('SELECT vers FROM synonymes WHERE terme = ?', [$terme]);
    return array_column($r, 'vers');
}

/** Termes proches, pour « vouliez-vous dire ». */
function suggestions(string $terme, int $max = 3): array
{
    if (mb_strlen($terme) < 4) return [];
    $prefixe = mb_substr($terme, 0, 1) . '%';
    $cands = qtous(
        'SELECT terme, SUM(poids) p FROM index_recherche WHERE terme LIKE ?
         GROUP BY terme ORDER BY p DESC LIMIT 400', [$prefixe]);
    $prox = [];
    foreach ($cands as $c) {
        $d = levenshtein($terme, (string) $c['terme']);
        if ($d > 0 && $d <= 2) $prox[$c['terme']] = $d;
    }
    asort($prox);
    return array_slice(array_keys($prox), 0, $max);
}

/**
 * @return array{resultats: array, total: int, suggestions: array}
 */
function recherche_executer(string $requete, array $opts = []): array
{
    $espace = $opts['espace'] ?? 'forum';
    $tri    = $opts['tri'] ?? 'pertinence';
    $limite = (int) ($opts['limite'] ?? 25);
    $depart = (int) ($opts['depart'] ?? 0);

    $termes = tokenise($requete);
    if (!$termes) return ['resultats' => [], 'total' => 0, 'suggestions' => []];

    // Les synonymes elargissent la requete, ils ne la remplacent pas.
    $elargis = $termes;
    foreach ($termes as $t) {
        foreach (synonymes_de($t) as $s) $elargis[] = $s;
    }
    $elargis = array_values(array_unique($elargis));

    $ph = implode(',', array_fill(0, count($elargis), '?'));
    $params = [...$elargis, $espace];

    // Le score additionne les poids ; le nombre de termes DISTINCTS trouves
    // passe devant, sinon un message qui repete un mot dix fois bat un
    // message qui contient les trois mots demandes une fois chacun.
    $sql = "SELECT objet_type, objet_id,
                   COUNT(DISTINCT terme) AS n_termes,
                   SUM(poids) AS score
            FROM index_recherche
            WHERE terme IN ($ph) AND espace = ?
            GROUP BY objet_type, objet_id
            ORDER BY n_termes DESC, score DESC
            LIMIT 500";
    $brut = qtous($sql, $params);

    $sugg = [];
    if (!$brut) {
        foreach ($termes as $t) $sugg = array_merge($sugg, suggestions($t));
        note_recherche_vide($requete);
    }

    $total = count($brut);
    $lignes = [];
    foreach ($brut as $r) {
        $o = charger_resultat($r['objet_type'], (int) $r['objet_id']);
        if ($o) { $o['score'] = (int) $r['score']; $lignes[] = $o; }
    }

    if ($tri === 'date')     usort($lignes, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    if ($tri === 'activite') usort($lignes, fn($a, $b) => ($b['activite'] ?? 0) <=> ($a['activite'] ?? 0));

    return [
        'resultats'   => array_slice($lignes, $depart, $limite),
        'total'       => $total,
        'suggestions' => array_values(array_unique($sugg)),
    ];
}

function charger_resultat(string $type, int $id): ?array
{
    if ($type === 'discussion') {
        $d = qun('SELECT d.*, u.identifiant FROM discussions d
                  LEFT JOIN utilisateurs u ON u.id = d.auteur_id
                  WHERE d.id = ? AND d.masquee = 0', [$id]);
        if (!$d) return null;
        return [
            'type' => 'discussion', 'id' => $id, 'titre' => $d['titre'],
            'url' => '/d/' . $d['slug'], 'auteur' => $d['identifiant'],
            'date' => $d['cree_le'], 'activite' => (int) $d['nb_reponses'],
            'extrait' => '',
        ];
    }
    if ($type === 'message') {
        $m = qun('SELECT m.*, d.titre, d.slug, u.identifiant FROM messages m
                  JOIN discussions d ON d.id = m.discussion_id
                  LEFT JOIN utilisateurs u ON u.id = m.auteur_id
                  WHERE m.id = ? AND m.masque = 0 AND d.masquee = 0', [$id]);
        if (!$m) return null;
        return [
            'type' => 'message', 'id' => $id, 'titre' => $m['titre'],
            'url' => '/d/' . $m['slug'] . '#m' . $id, 'auteur' => $m['identifiant'],
            'date' => $m['cree_le'], 'activite' => 0,
            'extrait' => extrait((string) $m['corps']),
        ];
    }
    if ($type === 'article') {
        // La condition de visibilite est REJOUEE ici. L'index peut avoir une
        // seconde de retard sur un retrait ; la lecture, elle, ne ment pas.
        $a = qun('SELECT * FROM articles WHERE id = ? AND statut = ?
                  AND publie_le IS NOT NULL AND publie_le <= ?',
                 [$id, 'publie', maintenant()]);
        if (!$a) return null;
        return [
            'type' => 'article', 'id' => $id, 'titre' => $a['titre'],
            'url' => '/a/' . $a['slug'], 'auteur' => $a['signature'],
            'date' => $a['publie_le'], 'activite' => (int) $a['nb_vues'],
            'extrait' => extrait((string) ($a['chapeau'] ?: $a['corps'])),
        ];
    }
    if ($type === 'projet') {
        $p = qun('SELECT * FROM projets WHERE id = ?', [$id]);
        if (!$p) return null;
        return [
            'type' => 'projet', 'id' => $id,
            'titre' => $p['nom_officiel'] ?: $p['nom_usuel'],
            'url' => '/p/' . $p['slug'], 'auteur' => null,
            'date' => $p['cree_le'], 'activite' => 0,
            'extrait' => extrait((string) $p['resume']),
        ];
    }
    return null;
}

function note_recherche_vide(string $requete): void
{
    $r = mb_substr(trim($requete), 0, 190);
    if ($r === '') return;
    $ex = qun('SELECT * FROM recherches_vides WHERE requete = ?', [$r]);
    if ($ex) {
        maj('recherches_vides', (int) $ex['id'],
            ['compte' => (int) $ex['compte'] + 1, 'vu_le' => maintenant()]);
    } else {
        insere('recherches_vides', ['requete' => $r, 'compte' => 1, 'vu_le' => maintenant()]);
    }
}

/** Autocompletion : prefixes les plus lourds de l'index. */
function autocomplete(string $debut, int $max = 8): array
{
    $t = normalise_terme($debut);
    if (mb_strlen($t) < 2) return [];
    // LIMIT n'est pas parametre : PDO en preparation NATIVE lie le
    // parametre comme une chaine et MySQL refuse « LIMIT '8' ». $max est
    // deja un entier PHP, l'interpolation est sans risque ici — c'est la
    // seule du projet, et elle est castee juste au-dessus.
    $max = max(1, min(50, $max));
    $r = qtous("SELECT terme, SUM(poids) p FROM index_recherche
                WHERE terme LIKE ? GROUP BY terme ORDER BY p DESC LIMIT $max",
               [$t . '%']);
    return array_column($r, 'terme');
}

/** Reconstruction complete — utilisee par l'installeur et l'admin. */
function reindexer_tout(): array
{
    q('DELETE FROM index_recherche');
    $n = ['discussion' => 0, 'message' => 0, 'projet' => 0, 'article' => 0];
    foreach (qtous('SELECT id FROM discussions WHERE masquee = 0') as $r) {
        indexer_discussion((int) $r['id']); $n['discussion']++;
    }
    foreach (qtous('SELECT id FROM messages WHERE masque = 0') as $r) {
        indexer_message((int) $r['id']); $n['message']++;
    }
    foreach (qtous('SELECT * FROM projets') as $p) {
        indexer('projets', 'projet', (int) $p['id'],
                [5 => $p['nom_officiel'] . ' ' . $p['nom_usuel'], 2 => $p['resume'],
                 1 => $p['description']]);
        $n['projet']++;
    }
    // Seuls les articles REELLEMENT publies entrent dans l'index : un
    // brouillon ou un article programme pour la semaine prochaine n'a rien a
    // faire dans une recherche publique, et son titre seul suffirait a
    // reveler ce qui n'est pas encore sorti. La condition est ecrite ici et
    // non deleguee, pour que ce fichier reste utilisable par l'installeur
    // sans dependre de src/portail.php.
    foreach (qtous('SELECT * FROM articles WHERE statut = ? AND publie_le IS NOT NULL
                    AND publie_le <= ?', ['publie', maintenant()]) as $a) {
        indexer('portail', 'article', (int) $a['id'],
                [5 => $a['titre'], 3 => (string) $a['chapeau'], 1 => (string) $a['corps']]);
        $n['article']++;
    }
    return $n;
}
