<?php
/* Francais — dictionnaire de reference. Les trois fichiers portent
   EXACTEMENT les memes cles ; tests-forum.py le verifie. */
return [

/* generique */
'a_renseigner' => 'à renseigner',
'oui' => 'Oui', 'non' => 'Non',
'enregistrer' => 'Enregistrer', 'annuler' => 'Annuler', 'envoyer' => 'Envoyer',
'fermer' => 'Fermer', 'retour' => 'Retour',
'suivant' => 'Suivant', 'precedent' => 'Précédent', 'page' => 'Page',
'aucun_resultat' => 'Aucun résultat.',
'obligatoire' => 'obligatoire', 'facultatif' => 'facultatif',
'a_l_instant' => "à l'instant",
'il_y_a_min' => 'il y a {n} min', 'il_y_a_h' => 'il y a {n} h', 'il_y_a_j' => 'il y a {n} j',

/* navigation */
'nav_accueil' => 'Accueil', 'nav_forums' => 'Forums', 'nav_villes' => 'Villes',
'nav_projets' => 'Projets', 'nav_carte' => 'Carte', 'nav_recherche' => 'Recherche',
'nav_membres' => 'Membres', 'nav_aide' => 'Aide',
'nav_admin' => 'Administration', 'nav_moderation' => 'Modération',
'nav_connexion' => 'Connexion', 'nav_inscription' => 'Inscription',
'nav_deconnexion' => 'Déconnexion', 'nav_profil' => 'Profil',
'nav_notifications' => 'Notifications', 'nav_signets' => 'Signets',
'nav_parametres' => 'Paramètres', 'nav_a_renseigner' => 'À renseigner',

/* accueil */
'accueil_tendances' => 'Discussions actives',
'accueil_dernieres_maj' => 'Dernières mises à jour',
'accueil_villes' => 'Villes',
'accueil_continents' => 'Parcourir le monde',
'accueil_stats' => 'La plateforme en chiffres',
'accueil_intro' => 'Suivre, documenter et discuter les projets urbains, ville par ville.',

/* forum */
'forum_discussions' => 'Discussions', 'forum_messages' => 'Messages',
'forum_sous_forums' => 'Sous-forums', 'forum_regles' => 'Règles du forum',
'forum_dernier_message' => 'Dernier message',
'forum_aucune_discussion' => "Aucune discussion pour l'instant.",
'forum_nouvelle_discussion' => 'Nouvelle discussion',
'forum_par' => 'par', 'forum_vues' => 'Vues', 'forum_reponses' => 'Réponses',
'forum_participants' => 'Participants',
'forum_epinglee' => 'Épinglée', 'forum_verrouillee' => 'Verrouillée',
'forum_verrouillee_avis' => 'Cette discussion est verrouillée : on ne peut plus y répondre.',

/* discussion */
'disc_repondre' => 'Répondre', 'disc_citer' => 'Citer',
'disc_modifier' => 'Modifier', 'disc_supprimer' => 'Supprimer',
'disc_signaler' => 'Signaler', 'disc_previsualiser' => 'Prévisualiser',
'disc_brouillon_enregistre' => 'Brouillon enregistré',
'disc_abonner' => "S'abonner", 'disc_desabonner' => 'Se désabonner',
'disc_signet_ajouter' => 'Mettre en signet', 'disc_signet_retirer' => 'Retirer le signet',
'disc_modifie_le' => 'Modifié le', 'disc_motif_edition' => "Motif de l'édition",
'disc_historique' => "Historique d'édition",
'disc_message_masque' => 'Message masqué par la modération.',
'disc_reagir' => 'Réagir', 'disc_permalien' => 'Lien permanent',
'disc_position' => 'Message n° {n}',
'disc_reponse_publiee' => 'Réponse publiée.',

/* editeur */
'ed_gras' => 'Gras', 'ed_italique' => 'Italique', 'ed_lien' => 'Lien',
'ed_citation' => 'Citation', 'ed_liste' => 'Liste', 'ed_image' => 'Image',
'ed_video' => 'Vidéo', 'ed_aide' => 'Aide à la rédaction',
'ed_corps' => 'Votre message', 'ed_titre' => 'Titre de la discussion',

/* compte */
'cpt_identifiant' => 'Identifiant', 'cpt_email' => 'Adresse e-mail',
'cpt_mot_de_passe' => 'Mot de passe', 'cpt_mot_de_passe2' => 'Confirmation',
'cpt_se_connecter' => 'Se connecter', 'cpt_creer_compte' => 'Créer un compte',
'cpt_deja_compte' => 'Déjà un compte ?', 'cpt_pas_compte' => 'Pas encore de compte ?',
'cpt_bienvenue' => 'Bienvenue, {n}.',
'cpt_erreur_identifiants' => 'Identifiant ou mot de passe incorrect.',
'cpt_erreur_pris' => 'Cet identifiant ou cette adresse est déjà utilisé.',
'cpt_erreur_email' => 'Adresse e-mail invalide.',
'cpt_erreur_mdp_court' => 'Le mot de passe doit faire au moins 10 caractères.',
'cpt_erreur_mdp_differents' => 'Les deux mots de passe ne correspondent pas.',
'cpt_trop_essais' => "Trop d'essais. Réessayez plus tard.",
'cpt_deconnecte' => 'Vous êtes déconnecté.',
'cpt_inscrit_le' => 'Inscrit le', 'cpt_messages_publies' => 'Messages publiés',
'cpt_bio' => 'Biographie', 'cpt_localisation' => 'Localisation',
'cpt_lien' => 'Lien', 'cpt_langue' => 'Langue',
'cpt_profil_public' => 'Profil visible par les visiteurs',
'cpt_bloquer' => 'Bloquer ce membre', 'cpt_debloquer' => 'Débloquer',

/* notifications */
'notif_titre' => 'Notifications', 'notif_aucune' => 'Aucune notification.',
'notif_tout_lu' => 'Tout marquer comme lu',
'notif_reponse' => '{n} a répondu dans une discussion que vous suivez',
'notif_mention' => '{n} vous a mentionné',
'notif_abonnement' => 'Nouveau message dans « {n} »',
'notif_moderation' => 'Action de modération sur votre contenu',
'notif_prefs' => 'Préférences de notification',
'notif_canal_app' => 'Dans le site', 'notif_canal_email' => 'Par e-mail',
'notif_email_desactive' => "Aucune adresse d'expédition n'est configurée : les notifications par e-mail ne partent pas. Elles restent dans ce centre.",

/* recherche */
'rech_placeholder' => 'Rechercher une ville, un projet, une discussion…',
'rech_resultats' => '{n} résultat(s)',
'rech_aucun' => 'Aucun résultat pour cette recherche.',
'rech_dans_forum' => 'Forum', 'rech_dans_projets' => 'Projets',
'rech_tri_pertinence' => 'Pertinence', 'rech_tri_date' => 'Date',
'rech_tri_activite' => 'Activité',
'rech_filtres' => 'Filtres', 'rech_suggestion' => 'Vouliez-vous dire :',

/* moderation */
'mod_titre' => 'Modération', 'mod_file' => 'File des signalements',
'mod_signalement' => 'Signalement', 'mod_motif' => 'Motif', 'mod_etat' => 'État',
'mod_nouveau' => 'Nouveau', 'mod_en_revue' => 'En revue',
'mod_actionne' => 'Actionné', 'mod_classe' => 'Classé',
'mod_priorite' => 'Priorité', 'mod_prendre' => 'Prendre en revue',
'mod_action' => 'Action', 'mod_masquer' => 'Masquer', 'mod_demasquer' => 'Démasquer',
'mod_epingler' => 'Épingler', 'mod_verrouiller' => 'Verrouiller',
'mod_deplacer' => 'Déplacer', 'mod_fusionner' => 'Fusionner',
'mod_avertir' => 'Avertir', 'mod_suspendre' => 'Suspendre', 'mod_bannir' => 'Bannir',
'mod_journal' => 'Journal des actions', 'mod_applique' => 'Action enregistrée.',
'mod_aucune' => 'Aucun signalement en attente.',

/* signalement */
'sig_titre' => 'Signaler ce contenu',
'sig_motif_spam' => 'Spam ou publicité', 'sig_motif_insulte' => 'Propos injurieux',
'sig_motif_horssujet' => 'Hors sujet', 'sig_motif_faux' => 'Information fausse',
'sig_motif_autre' => 'Autre',
'sig_commentaire' => 'Précision (facultatif)',
'sig_envoye' => 'Signalement transmis à la modération.',

/* admin */
'adm_titre' => 'Administration', 'adm_membres' => 'Membres',
'adm_inscriptions' => 'Inscriptions (30 j)', 'adm_messages_jour' => 'Messages (24 h)',
'adm_discussions_actives' => 'Discussions actives (7 j)', 'adm_projets' => 'Projets',
'adm_signalements' => 'Signalements ouverts', 'adm_stockage' => 'Stockage médias',
'adm_taxonomie' => 'Taxonomie', 'adm_permissions' => 'Rôles et permissions',
'adm_contenus_vus' => 'Contenus les plus consultés',
'adm_recherches_vides' => 'Recherches sans résultat',
'adm_export' => 'Export CSV', 'adm_audit' => "Journal d'audit",
'adm_journal_erreurs' => "Journal d'erreurs",

/* geographie */
'geo_continent' => 'Continent', 'geo_pays' => 'Pays', 'geo_region' => 'Région',
'geo_ville' => 'Ville', 'geo_secteur' => 'Secteur', 'geo_projet' => 'Projet',
'geo_monde' => 'Monde',

/* projets — phase 2 */
'proj_statut' => 'Statut', 'proj_propose' => 'Proposé', 'proj_approuve' => 'Approuvé',
'proj_appel_offres' => "Appel d'offres", 'proj_construction' => 'En construction',
'proj_suspendu' => 'Suspendu', 'proj_livre' => 'Livré', 'proj_annule' => 'Annulé',
'proj_budget' => 'Budget', 'proj_hauteur' => 'Hauteur', 'proj_surface' => 'Surface',
'proj_longueur' => 'Longueur', 'proj_capacite' => 'Capacité',
'proj_maitre_ouvrage' => "Maître d'ouvrage", 'proj_architecte' => 'Architecte',
'proj_dates' => 'Dates clés', 'proj_sources' => 'Sources', 'proj_galerie' => 'Galerie',
'proj_historique' => 'Historique des modifications',
'proj_niveau_verifie' => 'Vérifié', 'proj_niveau_estimation' => 'Estimation',
'proj_niveau_rumeur' => 'Rumeur',
'proj_phase2' => "Les fiches projets et la cartographie sont la phase 2. Le modèle de données est en place, il n'est pas rempli : un budget, une hauteur ou une date de livraison viennent d'une source ou restent vides.",

/* roles */
'role_visiteur' => 'Visiteur', 'role_membre' => 'Membre',
'role_contributeur' => 'Contributeur', 'role_contributeur_verifie' => 'Contributeur vérifié',
'role_moderateur' => 'Modérateur', 'role_administrateur' => 'Administrateur',
'role_pro' => 'Compte professionnel',

/* divers */
'demo_bandeau' => 'Contenu de démonstration. Les discussions et les membres visibles ici servent à faire fonctionner le site ; ils ne rapportent aucun fait réel. Un script les efface avant la mise en ligne.',
'provisoire' => 'nom provisoire',
'refuse_droit' => "Votre rôle ne permet pas cette action.",
'refuse_connexion' => 'Connectez-vous pour faire cela.',
'err_404' => 'Page introuvable.',
'err_403' => 'Accès refusé.',
'err_csrf' => 'Formulaire expiré. Recommencez.',
'err_limite' => "Trop d'actions en peu de temps. Réessayez dans quelques minutes.",
'pied_mentions' => 'Mentions légales',
'langue_fr' => 'Français', 'langue_en' => 'English', 'langue_ar' => 'العربية',
'aller_contenu' => 'Aller au contenu',
'sitemap' => 'Plan du site',

/* page des champs vides */
'vide_titre' => 'Ce qui reste à renseigner',
'vide_intro' => "Chaque ligne est une valeur que je n'ai pas et que je n'invente pas. Elle s'affiche sur le site comme une pastille visible, jamais comme un tiret discret.",
'vide_champ' => 'Champ', 'vide_aucun' => 'Tout est renseigné.',
];
