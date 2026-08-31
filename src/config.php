<?php
/**
 * URBAN FORUM — configuration.
 *
 * ------------------------------------------------------------------------
 * CE QUI EST VIDE ICI EST VIDE EXPRES.
 *
 * Le nom de la plateforme, le domaine, l'entite juridique, l'adresse de
 * contact, l'expediteur des e-mails : je ne les connais pas. Je n'invente
 * pas un nom de societe ni une adresse. Chaque valeur laissee vide
 * s'affiche sur le site comme une pastille « a renseigner », visible, qui
 * se compte sur la page /a-renseigner. Elle ne s'affiche jamais comme un
 * tiret discret ni comme une valeur plausible.
 *
 * NOM_PROVISOIRE : « URBAN FORUM » est le titre de TON cahier des charges,
 * pas une marque choisie. Il est marque provisoire tant que tu n'as pas
 * tranche. Aucun nom, texte, logo ou element d'interface d'un forum
 * existant n'est repris — c'est la regle de ta section 18.
 * ------------------------------------------------------------------------
 */

return [

    // --- Identite (a renseigner par le proprietaire) ---------------------
    'nom_site'          => 'URBAN FORUM',
    'nom_provisoire'    => true,      // passe a false quand le nom est choisi
    'baseline'          => '',        // ex. « Le forum des projets urbains »
    'domaine'           => '',        // ex. https://exemple.com  (sans / final)
    'entite_juridique'  => '',        // raison sociale + forme + immatriculation
    'adresse_postale'   => '',
    'contact_public'    => '',        // page ou formulaire de contact public
    'directeur_publication' => '',
    'hebergeur'         => '',        // mentions legales : nom + adresse

    // --- Base de donnees -------------------------------------------------
    // 'sqlite' : zero configuration, sert la demonstration.
    // 'mysql'  : la production chez Hostinger. Renseigne les 4 champs.
    'bd' => [
        'pilote' => 'sqlite',
        'sqlite' => __DIR__ . '/../donnees/forum.sqlite',
        'hote'   => 'localhost',
        'base'   => '',
        'user'   => '',
        'passe'  => '',
        'port'   => 3306,
    ],

    // --- Chemins ---------------------------------------------------------
    // Les medias sont HORS de la racine web et servis par un script.
    // Ce n'est pas de la coquetterie : un repertoire d'upload accessible en
    // direct est un chemin d'execution tant qu'on n'a pas prouve le
    // contraire. Ici le serveur ne peut pas atteindre le fichier du tout.
    'chemin_medias'  => __DIR__ . '/../donnees/medias',
    'chemin_journal' => __DIR__ . '/../donnees/journal',

    // --- Langues ---------------------------------------------------------
    'langues'        => ['fr', 'en', 'ar'],
    'langue_defaut'  => 'fr',

    // --- Sessions et securite -------------------------------------------
    // Laisse 'sel_session' vide : l'installeur en genere un et l'ecrit dans
    // config.local.php. Un sel ecrit en dur dans un depot public n'est pas
    // un secret.
    'sel_session'    => '',
    'duree_session'  => 60 * 60 * 24 * 30,
    'cookie_secure'  => false,   // passe a true derriere HTTPS (donc en prod)

    // --- Envoi d'e-mails -------------------------------------------------
    // Vide = AUCUN e-mail n'est envoye. Les notifications restent dans le
    // centre in-app et le site le dit clairement, plutot que de faire croire
    // qu'un message est parti.
    'mail_expediteur' => '',
    'mail_nom'        => '',

    // --- Limites ---------------------------------------------------------
    'messages_par_page'    => 20,
    'discussions_par_page' => 25,
    'taille_max_image'     => 4 * 1024 * 1024,   // 4 Mo
    'types_image'          => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],

    // Anti-abus : nombre d'actions autorisees par fenetre de temps.
    'limites' => [
        'connexion'   => ['nb' => 5,  'fenetre' => 900],   // 5 essais / 15 min
        'inscription' => ['nb' => 3,  'fenetre' => 3600],
        'message'     => ['nb' => 15, 'fenetre' => 600],
        'signalement' => ['nb' => 10, 'fenetre' => 3600],
        'televersement' => ['nb' => 20, 'fenetre' => 3600],
    ],

    // --- Monetisation (section 12) --------------------------------------
    // Aucun emplacement publicitaire n'est active et aucun tarif premium
    // n'est ecrit : un prix depend du marche, de la devise et de la regie.
    // Les emplacements existent dans le gabarit, ils sont vides et visibles.
    'publicite_active'  => false,
    'premium_actif'     => false,

    // --- Demonstration ---------------------------------------------------
    // true  : bandeau « contenu de demonstration » sur tout le site.
    // Passe a false APRES avoir lance outils/purge-demo.php.
    'mode_demo' => true,
];
