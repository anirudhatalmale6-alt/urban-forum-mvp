<?php
/**
 * Le schema, decrit UNE FOIS, emis en SQLite ou en MySQL.
 *
 * Pourquoi pas deux fichiers .sql : parce que deux fichiers derivent. On
 * corrige une colonne dans l'un, on oublie l'autre, et la difference ne se
 * voit qu'en production sur l'autre moteur. Ici il n'y a qu'une source.
 *
 * Le modele couvre la SECTION 9 du cahier des charges en entier :
 *   User, Role, Permission, Country, Region, City, Category, Forum, Thread,
 *   Post, Project, ProjectUpdate, ProjectCompany, Company, Media, Source,
 *   Reaction, Subscription, Notification, Report, ModerationAction, Badge,
 *   UserBadge.
 * Les tables de la Phase 2 (projets, entreprises, sources, cartographie)
 * sont CREEES des maintenant et vides : le schema ne bougera pas quand on
 * les remplira, donc aucune migration destructive plus tard.
 */

/* Types portables :
 *   pk      identifiant auto-incremente
 *   int     entier          bool    0/1
 *   str:N   VARCHAR(N)      text    texte long
 *   ts      date + heure    float   nombre a virgule
 * Les VARCHAR indexes en UNIQUE restent <= 190 : au-dela, MySQL en utf8mb4
 * depasse la longueur de cle maximale.
 */
function schema_tables(): array
{
    return [

        // ---------- Identite, roles, permissions -------------------------
        'roles' => [
            'cols' => [
                'id' => 'pk', 'cle' => 'str:60', 'rang' => 'int',
                'nom_fr' => 'str:120', 'nom_en' => 'str:120', 'nom_ar' => 'str:120',
            ],
            'uniques' => [['cle']],
        ],
        'permissions' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:80', 'description' => 'str:255'],
            'uniques' => [['cle']],
        ],
        'role_permissions' => [
            'cols' => ['id' => 'pk', 'role_id' => 'int', 'permission_id' => 'int'],
            'uniques' => [['role_id', 'permission_id']],
        ],
        'utilisateurs' => [
            'cols' => [
                'id' => 'pk',
                'identifiant' => 'str:60',      // pseudo public, unique
                'email' => 'str:190',
                'mot_de_passe' => 'str:255',
                'role_id' => 'int',
                'nom_public' => 'str:120',
                'bio' => 'text',
                'localisation' => 'str:120',
                'lien' => 'str:255',
                'avatar_media_id' => 'int',
                'langue' => 'str:5',
                'cree_le' => 'ts',
                'vu_le' => 'ts',
                'actif' => 'bool',
                'suspendu_jusqu' => 'ts',
                'banni' => 'bool',
                'demo' => 'bool',               // contenu de demonstration
                'profil_public' => 'bool',
                'nb_messages' => 'int',
            ],
            'uniques' => [['identifiant'], ['email']],
            'index'   => [['role_id']],
        ],
        'sessions' => [
            'cols' => [
                'id' => 'pk', 'jeton' => 'str:190', 'utilisateur_id' => 'int',
                'cree_le' => 'ts', 'expire_le' => 'ts', 'ip' => 'str:64',
                'agent' => 'str:255',
            ],
            'uniques' => [['jeton']],
            'index'   => [['utilisateur_id']],
        ],
        'preferences_notif' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'type' => 'str:60',
                'canal' => 'str:20',    // 'app' | 'email'
                'actif' => 'bool',
            ],
            'uniques' => [['utilisateur_id', 'type', 'canal']],
        ],
        'blocages' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'bloque_id' => 'int', 'cree_le' => 'ts'],
            'uniques' => [['utilisateur_id', 'bloque_id']],
        ],

        // ---------- Geographie (section 3) -------------------------------
        'continents' => [
            'cols' => ['id' => 'pk', 'code' => 'str:8', 'slug' => 'str:80',
                       'nom_fr' => 'str:120', 'nom_en' => 'str:120', 'nom_ar' => 'str:120',
                       'rang' => 'int'],
            'uniques' => [['code'], ['slug']],
        ],
        'pays' => [
            'cols' => [
                'id' => 'pk', 'continent_id' => 'int', 'code' => 'str:8',
                'slug' => 'str:120', 'nom_fr' => 'str:150', 'nom_en' => 'str:150',
                'nom_ar' => 'str:150',
                // Volontairement vides tant qu'une source n'est pas fournie.
                'latitude' => 'float', 'longitude' => 'float',
            ],
            'uniques' => [['code'], ['slug']],
            'index'   => [['continent_id']],
        ],
        'regions' => [
            'cols' => ['id' => 'pk', 'pays_id' => 'int', 'slug' => 'str:150',
                       'nom_fr' => 'str:150', 'nom_en' => 'str:150', 'nom_ar' => 'str:150'],
            'uniques' => [['pays_id', 'slug']],
        ],
        'villes' => [
            'cols' => [
                'id' => 'pk', 'pays_id' => 'int', 'region_id' => 'int',
                'slug' => 'str:150', 'nom_fr' => 'str:150', 'nom_en' => 'str:150',
                'nom_ar' => 'str:150',
                'latitude' => 'float', 'longitude' => 'float',
                'coord_approx' => 'bool',   // section 4.3 : coordonnee approchee
            ],
            'uniques' => [['pays_id', 'slug']],
            'index'   => [['region_id']],
        ],

        // ---------- Taxonomie thematique ---------------------------------
        'categories' => [
            'cols' => [
                'id' => 'pk', 'parent_id' => 'int', 'slug' => 'str:120',
                'type' => 'str:20',     // 'secteur' | 'typologie'
                'nom_fr' => 'str:150', 'nom_en' => 'str:150', 'nom_ar' => 'str:150',
                'rang' => 'int',
            ],
            'uniques' => [['type', 'slug']],
        ],

        // ---------- Forum -------------------------------------------------
        'forums' => [
            'cols' => [
                'id' => 'pk', 'parent_id' => 'int', 'slug' => 'str:150',
                'titre_fr' => 'str:190', 'titre_en' => 'str:190', 'titre_ar' => 'str:190',
                'description_fr' => 'text', 'description_en' => 'text', 'description_ar' => 'text',
                'regles' => 'text',
                'continent_id' => 'int', 'pays_id' => 'int', 'ville_id' => 'int',
                'categorie_id' => 'int',
                'rang' => 'int', 'ferme' => 'bool',
                'nb_discussions' => 'int', 'nb_messages' => 'int',
                'dernier_message_id' => 'int', 'demo' => 'bool',
            ],
            'uniques' => [['slug']],
            'index'   => [['parent_id'], ['pays_id'], ['ville_id'], ['categorie_id']],
        ],
        'discussions' => [
            'cols' => [
                'id' => 'pk', 'forum_id' => 'int', 'auteur_id' => 'int',
                'titre' => 'str:255', 'slug' => 'str:190',
                'cree_le' => 'ts', 'maj_le' => 'ts',
                'epinglee' => 'bool', 'verrouillee' => 'bool', 'masquee' => 'bool',
                'fusionnee_dans' => 'int',
                'nb_vues' => 'int', 'nb_reponses' => 'int', 'nb_participants' => 'int',
                'dernier_message_id' => 'int', 'dernier_message_le' => 'ts',
                'projet_id' => 'int', 'demo' => 'bool',
            ],
            'uniques' => [['slug']],
            'index'   => [['forum_id'], ['auteur_id'], ['dernier_message_le']],
        ],
        'messages' => [
            'cols' => [
                'id' => 'pk', 'discussion_id' => 'int', 'auteur_id' => 'int',
                'corps' => 'text',            // source, dans la syntaxe de l'editeur
                'rendu' => 'text',            // HTML assaini, calcule a l'ecriture
                'cree_le' => 'ts', 'modifie_le' => 'ts',
                'nb_editions' => 'int', 'masque' => 'bool',
                'position' => 'int',          // 1, 2, 3 ... dans la discussion
                'ip' => 'str:64', 'demo' => 'bool',
            ],
            'index' => [['discussion_id'], ['auteur_id'], ['cree_le']],
        ],
        'revisions_message' => [
            'cols' => [
                'id' => 'pk', 'message_id' => 'int', 'editeur_id' => 'int',
                'corps_avant' => 'text', 'motif' => 'str:255', 'cree_le' => 'ts',
            ],
            'index' => [['message_id']],
        ],
        'brouillons' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'discussion_id' => 'int',
                'forum_id' => 'int', 'titre' => 'str:255', 'corps' => 'text',
                'maj_le' => 'ts',
            ],
            'index' => [['utilisateur_id']],
        ],
        'reactions' => [
            'cols' => [
                'id' => 'pk', 'message_id' => 'int', 'utilisateur_id' => 'int',
                'type' => 'str:20', 'cree_le' => 'ts',
            ],
            'uniques' => [['message_id', 'utilisateur_id', 'type']],
        ],
        'signets' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'discussion_id' => 'int', 'cree_le' => 'ts'],
            'uniques' => [['utilisateur_id', 'discussion_id']],
        ],
        'abonnements' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int',
                'objet_type' => 'str:20',   // 'discussion' | 'forum' | 'projet' | 'utilisateur'
                'objet_id' => 'int', 'cree_le' => 'ts',
            ],
            'uniques' => [['utilisateur_id', 'objet_type', 'objet_id']],
        ],
        'mentions' => [
            'cols' => ['id' => 'pk', 'message_id' => 'int', 'utilisateur_id' => 'int'],
            'uniques' => [['message_id', 'utilisateur_id']],
        ],

        // ---------- Notifications ----------------------------------------
        'notifications' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'type' => 'str:60',
                'acteur_id' => 'int', 'discussion_id' => 'int', 'message_id' => 'int',
                'lien' => 'str:255', 'lue' => 'bool', 'cree_le' => 'ts',
                'email_envoye' => 'bool',
            ],
            'index' => [['utilisateur_id', 'lue'], ['cree_le']],
        ],

        // ---------- Moderation, confiance, securite (section 6) ----------
        'signalements' => [
            'cols' => [
                'id' => 'pk', 'signaleur_id' => 'int',
                'objet_type' => 'str:20',   // 'message' | 'discussion' | 'utilisateur'
                'objet_id' => 'int',
                'motif' => 'str:40',        // spam, insulte, hors-sujet, faux, autre
                'commentaire' => 'text',
                'priorite' => 'str:20',     // basse | normale | haute
                'etat' => 'str:20',         // nouveau | en_revue | actionne | classe
                'traite_par' => 'int', 'traite_le' => 'ts', 'cree_le' => 'ts',
            ],
            'index' => [['etat'], ['objet_type', 'objet_id']],
        ],
        'actions_moderation' => [
            'cols' => [
                'id' => 'pk', 'moderateur_id' => 'int', 'action' => 'str:40',
                'objet_type' => 'str:20', 'objet_id' => 'int',
                'signalement_id' => 'int', 'motif' => 'text',
                'detail' => 'text',          // JSON : ancien/nouveau, cible d'un deplacement
                'cree_le' => 'ts',
            ],
            'index' => [['moderateur_id'], ['cree_le']],
        ],
        'journal_audit' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'action' => 'str:60',
                'objet' => 'str:120', 'detail' => 'text', 'ip' => 'str:64', 'cree_le' => 'ts',
            ],
            'index' => [['cree_le']],
        ],
        'limites_taux' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:190', 'compte' => 'int', 'debut' => 'ts'],
            'uniques' => [['cle']],
        ],

        // ---------- Medias -------------------------------------------------
        'medias' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'nom_fichier' => 'str:190',
                'nom_origine' => 'str:255', 'type_mime' => 'str:80',
                'octets' => 'int', 'largeur' => 'int', 'hauteur' => 'int',
                'alt' => 'str:255', 'cree_le' => 'ts',
                'objet_type' => 'str:20', 'objet_id' => 'int', 'demo' => 'bool',
            ],
            'uniques' => [['nom_fichier']],
            'index'   => [['utilisateur_id']],
        ],

        // ---------- Recherche (section 4.4) --------------------------------
        // Index inverse maison. Deux raisons : il tourne a l'identique sur
        // SQLite et sur MySQL, et il permet d'indexer SEPAREMENT le forum et
        // les fiches structurees, ce que demande la section 4.4.
        'index_recherche' => [
            'cols' => [
                'id' => 'pk', 'terme' => 'str:64', 'espace' => 'str:20',
                'objet_type' => 'str:20', 'objet_id' => 'int', 'poids' => 'int',
            ],
            'index' => [['terme'], ['objet_type', 'objet_id']],
        ],
        'synonymes' => [
            'cols' => ['id' => 'pk', 'terme' => 'str:64', 'vers' => 'str:64'],
            'index' => [['terme']],
        ],
        'recherches_vides' => [
            'cols' => ['id' => 'pk', 'requete' => 'str:190', 'compte' => 'int', 'vu_le' => 'ts'],
            'uniques' => [['requete']],
        ],

        // ---------- Phase 2 : projets, entreprises, sources ---------------
        // Creees maintenant, remplies plus tard, JAMAIS avec des chiffres
        // inventes. Un budget, une hauteur, une date de livraison sont des
        // faits : ils viennent d'une source ou ils restent vides.
        'entreprises' => [
            'cols' => [
                'id' => 'pk', 'slug' => 'str:150', 'nom' => 'str:190',
                'type' => 'str:40',     // promoteur, architecte, ingenierie, constructeur
                'pays_id' => 'int', 'site' => 'str:255', 'description' => 'text',
                'verifiee' => 'bool', 'cree_le' => 'ts',
            ],
            'uniques' => [['slug']],
        ],
        'projets' => [
            'cols' => [
                'id' => 'pk', 'slug' => 'str:190',
                'nom_officiel' => 'str:255', 'nom_usuel' => 'str:255',
                'pays_id' => 'int', 'region_id' => 'int', 'ville_id' => 'int',
                'latitude' => 'float', 'longitude' => 'float', 'coord_approx' => 'bool',
                'categorie_id' => 'int', 'sous_categorie_id' => 'int',
                'statut' => 'str:30',   // proposed|approved|tender|construction|hold|completed|cancelled
                'resume' => 'text', 'description' => 'text',
                'budget' => 'float', 'devise' => 'str:8',
                'annee_annonce' => 'int', 'debut_travaux' => 'ts',
                'livraison_prevue' => 'ts',
                'hauteur_m' => 'float', 'surface_m2' => 'float',
                'longueur_km' => 'float', 'capacite' => 'str:120',
                'niveau_info' => 'str:20',   // verifie | estimation | rumeur (section 6)
                'cree_par' => 'int', 'cree_le' => 'ts', 'maj_le' => 'ts',
            ],
            'uniques' => [['slug']],
            'index'   => [['pays_id'], ['ville_id'], ['statut']],
        ],
        'projet_maj' => [
            'cols' => [
                'id' => 'pk', 'projet_id' => 'int', 'auteur_id' => 'int',
                'champ' => 'str:60', 'valeur_avant' => 'text', 'valeur_apres' => 'text',
                'source_id' => 'int', 'cree_le' => 'ts',
            ],
            'index' => [['projet_id']],
        ],
        'projet_entreprise' => [
            'cols' => ['id' => 'pk', 'projet_id' => 'int', 'entreprise_id' => 'int', 'role' => 'str:60'],
            'uniques' => [['projet_id', 'entreprise_id', 'role']],
        ],
        'projet_discussion' => [
            // Section 9 : un projet peut etre lie a PLUSIEURS discussions et
            // une discussion peut referencer PLUSIEURS projets.
            'cols' => ['id' => 'pk', 'projet_id' => 'int', 'discussion_id' => 'int'],
            'uniques' => [['projet_id', 'discussion_id']],
        ],
        'sources' => [
            'cols' => [
                'id' => 'pk', 'objet_type' => 'str:20', 'objet_id' => 'int',
                'url' => 'str:500', 'titre' => 'str:255', 'editeur' => 'str:150',
                'date_source' => 'ts', 'fiabilite' => 'str:20',
                'ajoutee_par' => 'int', 'cree_le' => 'ts',
            ],
            'index' => [['objet_type', 'objet_id']],
        ],

        // ---------- Badges (section 4.5) -----------------------------------
        'badges' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:60', 'nom_fr' => 'str:120',
                       'nom_en' => 'str:120', 'nom_ar' => 'str:120', 'description' => 'str:255'],
            'uniques' => [['cle']],
        ],
        'badges_utilisateur' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'badge_id' => 'int', 'cree_le' => 'ts'],
            'uniques' => [['utilisateur_id', 'badge_id']],
        ],

        // ---------- Reglages editables en admin ----------------------------
        'reglages' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:120', 'valeur' => 'text'],
            'uniques' => [['cle']],
        ],
        'vues_discussion' => [
            // Compteur de vues deduplique : une empreinte par jour.
            'cols' => ['id' => 'pk', 'discussion_id' => 'int', 'empreinte' => 'str:64', 'jour' => 'str:10'],
            'uniques' => [['discussion_id', 'empreinte', 'jour']],
        ],
    ];
}

function schema_type(string $t, string $pilote): string
{
    if ($t === 'pk') {
        return $pilote === 'mysql'
            ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }
    if (str_starts_with($t, 'str:')) {
        return 'VARCHAR(' . (int) substr($t, 4) . ') NULL';
    }
    return match ($t) {
        'int'   => 'INTEGER NULL',
        'bool'  => $pilote === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'float' => 'DOUBLE NULL',
        'ts'    => 'DATETIME NULL',
        default => 'TEXT NULL',
    };
}

function schema_ddl(string $pilote): array
{
    $out = [];
    foreach (schema_tables() as $table => $def) {
        $lignes = [];
        foreach ($def['cols'] as $col => $type) {
            $lignes[] = "  `$col` " . schema_type($type, $pilote);
        }
        foreach ($def['uniques'] ?? [] as $u) {
            $lignes[] = '  UNIQUE (`' . implode('`, `', $u) . '`)';
        }
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $lignes) . "\n)";
        if ($pilote === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $out[] = $sql;

        foreach ($def['index'] ?? [] as $ix) {
            // Nom global unique : SQLite partage l'espace de noms des index.
            $nom = 'ix_' . $table . '_' . implode('_', $ix);
            // MySQL n'accepte PAS « CREATE INDEX IF NOT EXISTS » — c'est une
            // erreur de syntaxe, pas un avertissement. L'installeur rejoue le
            // schema a chaque lancement, donc sur MySQL il attrape et ignore
            // l'erreur 1061 « duplicate key name ». Sur SQLite la clause
            // existe et fait le travail.
            $si = $pilote === 'mysql' ? '' : 'IF NOT EXISTS ';
            $out[] = "CREATE INDEX $si`$nom` ON `$table` (`" . implode('`, `', $ix) . "`)";
        }
    }
    return $out;
}
