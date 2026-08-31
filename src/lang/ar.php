<?php
/* العربية — نفس المفاتيح الموجودة في fr.php و en.php.
   Arabe. Memes cles que fr.php et en.php ; l'ecart est une erreur de test,
   pas un repli silencieux vers le francais. */
return [

/* generique */
'a_renseigner' => 'بانتظار التعبئة',
'oui' => 'نعم', 'non' => 'لا',
'enregistrer' => 'حفظ', 'annuler' => 'إلغاء', 'envoyer' => 'إرسال',
'fermer' => 'إغلاق', 'retour' => 'رجوع',
'suivant' => 'التالي', 'precedent' => 'السابق', 'page' => 'صفحة',
'aucun_resultat' => 'لا توجد نتائج.',
'obligatoire' => 'إلزامي', 'facultatif' => 'اختياري',
'a_l_instant' => 'الآن',
'il_y_a_min' => 'قبل {n} دقيقة', 'il_y_a_h' => 'قبل {n} ساعة', 'il_y_a_j' => 'قبل {n} يوم',

/* navigation */
'nav_accueil' => 'الرئيسية', 'nav_forums' => 'المنتديات', 'nav_villes' => 'المدن',
'nav_projets' => 'المشاريع', 'nav_carte' => 'الخريطة', 'nav_recherche' => 'البحث',
'nav_membres' => 'الأعضاء', 'nav_aide' => 'المساعدة',
'nav_admin' => 'الإدارة', 'nav_moderation' => 'الإشراف',
'nav_connexion' => 'تسجيل الدخول', 'nav_inscription' => 'إنشاء حساب',
'nav_deconnexion' => 'تسجيل الخروج', 'nav_profil' => 'الملف الشخصي',
'nav_notifications' => 'التنبيهات', 'nav_signets' => 'المحفوظات',
'nav_parametres' => 'الإعدادات', 'nav_a_renseigner' => 'بانتظار التعبئة',

/* accueil */
'accueil_tendances' => 'نقاشات نشطة',
'accueil_dernieres_maj' => 'آخر التحديثات',
'accueil_villes' => 'المدن',
'accueil_continents' => 'تصفّح العالم',
'accueil_stats' => 'المنصة بالأرقام',
'accueil_intro' => 'متابعة المشاريع العمرانية وتوثيقها ومناقشتها، مدينةً مدينة.',

/* forum */
'forum_discussions' => 'النقاشات', 'forum_messages' => 'المشاركات',
'forum_sous_forums' => 'المنتديات الفرعية', 'forum_regles' => 'قواعد المنتدى',
'forum_dernier_message' => 'آخر مشاركة',
'forum_aucune_discussion' => 'لا توجد نقاشات بعد.',
'forum_nouvelle_discussion' => 'نقاش جديد',
'forum_par' => 'بواسطة', 'forum_vues' => 'المشاهدات', 'forum_reponses' => 'الردود',
'forum_participants' => 'المشاركون',
'forum_epinglee' => 'مثبّت', 'forum_verrouillee' => 'مغلق',
'forum_verrouillee_avis' => 'هذا النقاش مغلق ولا يمكن الرد عليه.',

/* discussion */
'disc_repondre' => 'رد', 'disc_citer' => 'اقتباس',
'disc_modifier' => 'تعديل', 'disc_supprimer' => 'حذف',
'disc_signaler' => 'إبلاغ', 'disc_previsualiser' => 'معاينة',
'disc_brouillon_enregistre' => 'تم حفظ المسودة',
'disc_abonner' => 'متابعة', 'disc_desabonner' => 'إلغاء المتابعة',
'disc_signet_ajouter' => 'إضافة إلى المحفوظات', 'disc_signet_retirer' => 'إزالة من المحفوظات',
'disc_modifie_le' => 'عُدّل في', 'disc_motif_edition' => 'سبب التعديل',
'disc_historique' => 'سجل التعديلات',
'disc_message_masque' => 'أُخفيت هذه المشاركة من قبل الإشراف.',
'disc_reagir' => 'تفاعل', 'disc_permalien' => 'رابط دائم',
'disc_position' => 'المشاركة رقم {n}',
'disc_reponse_publiee' => 'تم نشر الرد.',

/* editeur */
'ed_gras' => 'عريض', 'ed_italique' => 'مائل', 'ed_lien' => 'رابط',
'ed_citation' => 'اقتباس', 'ed_liste' => 'قائمة', 'ed_image' => 'صورة',
'ed_video' => 'فيديو', 'ed_aide' => 'مساعدة في التنسيق',
'ed_corps' => 'نص المشاركة', 'ed_titre' => 'عنوان النقاش',

/* compte */
'cpt_identifiant' => 'اسم المستخدم', 'cpt_email' => 'البريد الإلكتروني',
'cpt_mot_de_passe' => 'كلمة المرور', 'cpt_mot_de_passe2' => 'تأكيد كلمة المرور',
'cpt_se_connecter' => 'تسجيل الدخول', 'cpt_creer_compte' => 'إنشاء حساب',
'cpt_deja_compte' => 'لديك حساب بالفعل؟', 'cpt_pas_compte' => 'ليس لديك حساب بعد؟',
'cpt_bienvenue' => 'أهلًا، {n}.',
'cpt_erreur_identifiants' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
'cpt_erreur_pris' => 'اسم المستخدم أو البريد مستعمل بالفعل.',
'cpt_erreur_email' => 'بريد إلكتروني غير صالح.',
'cpt_erreur_mdp_court' => 'يجب ألا تقل كلمة المرور عن ١٠ محارف.',
'cpt_erreur_mdp_differents' => 'كلمتا المرور غير متطابقتين.',
'cpt_trop_essais' => 'محاولات كثيرة. أعد المحاولة لاحقًا.',
'cpt_deconnecte' => 'تم تسجيل خروجك.',
'cpt_inscrit_le' => 'تاريخ الانضمام', 'cpt_messages_publies' => 'عدد المشاركات',
'cpt_bio' => 'نبذة', 'cpt_localisation' => 'الموقع',
'cpt_lien' => 'رابط', 'cpt_langue' => 'اللغة',
'cpt_profil_public' => 'الملف الشخصي ظاهر للزوار',
'cpt_bloquer' => 'حظر هذا العضو', 'cpt_debloquer' => 'إلغاء الحظر',

/* notifications */
'notif_titre' => 'التنبيهات', 'notif_aucune' => 'لا توجد تنبيهات.',
'notif_tout_lu' => 'تعليم الكل كمقروء',
'notif_reponse' => 'رد {n} في نقاش تتابعه',
'notif_mention' => 'أشار إليك {n}',
'notif_abonnement' => 'مشاركة جديدة في «{n}»',
'notif_moderation' => 'إجراء إشرافي على محتواك',
'notif_prefs' => 'تفضيلات التنبيهات',
'notif_canal_app' => 'داخل الموقع', 'notif_canal_email' => 'بالبريد الإلكتروني',
'notif_email_desactive' => 'لم يُضبط عنوان مُرسِل، لذلك لا تُرسَل تنبيهات البريد الإلكتروني. تبقى هنا في مركز التنبيهات.',

/* recherche */
'rech_placeholder' => 'ابحث عن مدينة أو مشروع أو نقاش…',
'rech_resultats' => '{n} نتيجة',
'rech_aucun' => 'لا توجد نتائج لهذا البحث.',
'rech_dans_forum' => 'المنتدى', 'rech_dans_projets' => 'المشاريع',
'rech_tri_pertinence' => 'الصلة', 'rech_tri_date' => 'التاريخ',
'rech_tri_activite' => 'النشاط',
'rech_filtres' => 'المرشّحات', 'rech_suggestion' => 'هل تقصد:',

/* moderation */
'mod_titre' => 'الإشراف', 'mod_file' => 'قائمة البلاغات',
'mod_signalement' => 'بلاغ', 'mod_motif' => 'السبب', 'mod_etat' => 'الحالة',
'mod_nouveau' => 'جديد', 'mod_en_revue' => 'قيد المراجعة',
'mod_actionne' => 'تم اتخاذ إجراء', 'mod_classe' => 'مغلق',
'mod_priorite' => 'الأولوية', 'mod_prendre' => 'أخذه للمراجعة',
'mod_action' => 'إجراء', 'mod_masquer' => 'إخفاء', 'mod_demasquer' => 'إظهار',
'mod_epingler' => 'تثبيت', 'mod_verrouiller' => 'إغلاق',
'mod_deplacer' => 'نقل', 'mod_fusionner' => 'دمج',
'mod_avertir' => 'تحذير', 'mod_suspendre' => 'إيقاف مؤقت', 'mod_bannir' => 'حظر',
'mod_journal' => 'سجل الإجراءات', 'mod_applique' => 'تم تسجيل الإجراء.',
'mod_aucune' => 'لا توجد بلاغات معلّقة.',

/* signalement */
'sig_titre' => 'الإبلاغ عن هذا المحتوى',
'sig_motif_spam' => 'رسائل مزعجة أو إعلانات', 'sig_motif_insulte' => 'ألفاظ مسيئة',
'sig_motif_horssujet' => 'خارج الموضوع', 'sig_motif_faux' => 'معلومة خاطئة',
'sig_motif_autre' => 'أخرى',
'sig_commentaire' => 'توضيح (اختياري)',
'sig_envoye' => 'أُرسل البلاغ إلى فريق الإشراف.',

/* admin */
'adm_titre' => 'الإدارة', 'adm_membres' => 'الأعضاء',
'adm_inscriptions' => 'التسجيلات (٣٠ يومًا)', 'adm_messages_jour' => 'المشاركات (٢٤ ساعة)',
'adm_discussions_actives' => 'نقاشات نشطة (٧ أيام)', 'adm_projets' => 'المشاريع',
'adm_signalements' => 'بلاغات مفتوحة', 'adm_stockage' => 'مساحة الوسائط',
'adm_taxonomie' => 'التصنيفات', 'adm_permissions' => 'الأدوار والصلاحيات',
'adm_contenus_vus' => 'الأكثر مشاهدة',
'adm_recherches_vides' => 'عمليات بحث بلا نتائج',
'adm_export' => 'تصدير CSV', 'adm_audit' => 'سجل التدقيق',
'adm_journal_erreurs' => 'سجل الأخطاء',

/* geographie */
'geo_continent' => 'القارة', 'geo_pays' => 'الدولة', 'geo_region' => 'المنطقة',
'geo_ville' => 'المدينة', 'geo_secteur' => 'القطاع', 'geo_projet' => 'المشروع',
'geo_monde' => 'العالم',

/* projets — phase 2 */
'proj_statut' => 'الحالة', 'proj_propose' => 'مقترح', 'proj_approuve' => 'معتمد',
'proj_appel_offres' => 'طرح المناقصة', 'proj_construction' => 'قيد الإنشاء',
'proj_suspendu' => 'متوقف', 'proj_livre' => 'مُنجز', 'proj_annule' => 'ملغى',
'proj_budget' => 'الميزانية', 'proj_hauteur' => 'الارتفاع', 'proj_surface' => 'المساحة',
'proj_longueur' => 'الطول', 'proj_capacite' => 'الطاقة الاستيعابية',
'proj_maitre_ouvrage' => 'صاحب المشروع', 'proj_architecte' => 'المكتب المعماري',
'proj_dates' => 'تواريخ رئيسية', 'proj_sources' => 'المصادر', 'proj_galerie' => 'معرض الصور',
'proj_historique' => 'سجل التعديلات',
'proj_niveau_verifie' => 'مُتحقَّق منه', 'proj_niveau_estimation' => 'تقدير',
'proj_niveau_rumeur' => 'إشاعة',
'proj_phase2' => 'بطاقات المشاريع والخريطة من المرحلة الثانية. نموذج البيانات جاهز وفارغ عن قصد: الميزانية أو الارتفاع أو تاريخ التسليم يأتي من مصدر أو يبقى فارغًا.',

/* roles */
'role_visiteur' => 'زائر', 'role_membre' => 'عضو',
'role_contributeur' => 'مساهم', 'role_contributeur_verifie' => 'مساهم موثّق',
'role_moderateur' => 'مشرف', 'role_administrateur' => 'مدير',
'role_pro' => 'حساب مؤسسي',

/* divers */
'demo_bandeau' => 'محتوى تجريبي. النقاشات والأعضاء الظاهرون هنا موجودون لتشغيل الموقع فقط، ولا يذكرون أي معلومة حقيقية. يمسحهم سكربت واحد قبل الإطلاق.',
'provisoire' => 'اسم مؤقت',
'refuse_droit' => 'دورك لا يسمح بهذا الإجراء.',
'refuse_connexion' => 'سجّل الدخول للقيام بذلك.',
'err_404' => 'الصفحة غير موجودة.',
'err_403' => 'الوصول مرفوض.',
'err_csrf' => 'انتهت صلاحية النموذج. أعد المحاولة.',
'err_limite' => 'إجراءات كثيرة في وقت قصير. أعد المحاولة بعد دقائق.',
'pied_mentions' => 'إشعار قانوني',
'langue_fr' => 'Français', 'langue_en' => 'English', 'langue_ar' => 'العربية',
'aller_contenu' => 'تخطَّ إلى المحتوى',
'sitemap' => 'خريطة الموقع',

/* page des champs vides */
'vide_titre' => 'ما يبقى بانتظار التعبئة',
'vide_intro' => 'كل سطر هنا قيمة لا أملكها ولن أختلقها. تظهر في الموقع كعلامة مرئية، لا كشرطة صغيرة.',
'vide_champ' => 'الحقل', 'vide_aucun' => 'كل الحقول مُعبّأة.',
];
