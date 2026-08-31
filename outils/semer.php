<?php
/**
 * Contenu de demonstration.
 *
 *   php outils/semer.php
 *
 * ------------------------------------------------------------------------
 * CE QUE CE FICHIER N'ECRIT PAS, ET POURQUOI
 *
 * Aucune discussion de demonstration n'affirme un fait sur un projet reel.
 * Pas de budget, pas de hauteur, pas de date de livraison, pas de nom
 * d'entreprise, pas de « le metro de X ouvrira en 20XX ». Une phrase de ce
 * genre, ecrite pour meubler une maquette, se retrouve indexee, citee, puis
 * reprise ailleurs — et personne ne se souvient qu'elle venait d'un jeu de
 * test. Les messages de demonstration parlent donc de la plateforme
 * elle-meme : comment ecrire, citer, mentionner, signaler.
 *
 * Les noms de pays et de villes, eux, sont des noms propres, pas des
 * chiffres. Les coordonnees geographiques ne sont PAS semees : elles
 * viendront d'une source, ou la carte de la phase 2 restera vide.
 *
 * Tout ce qui est cree ici porte demo = 1 et disparait avec
 * outils/purge-demo.php.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/messages.php';
require $racine . '/src/recherche.php';
require $racine . '/src/notifications.php';
require $racine . '/src/moderation.php';
require $racine . '/src/vue.php';
require $racine . '/src/portail.php';

if (qval('SELECT COUNT(*) FROM roles') === null) {
    fwrite(STDERR, "Lance d'abord : php outils/installer.php\n");
    exit(1);
}

function ins_si_absent(string $table, array $cles, array $donnees): int
{
    $where = implode(' AND ', array_map(fn($c) => "`$c` = ?", array_keys($cles)));
    $id = qval("SELECT id FROM `$table` WHERE $where", array_values($cles));
    if ($id !== null) return (int) $id;
    return insere($table, $donnees + $cles);
}

/* ------------------------------------------------------------------ */
/* 1. Geographie                                                       */
/* ------------------------------------------------------------------ */

$CONTINENTS = [
    ['AF', 'afrique',          'Afrique',          'Africa',        'أفريقيا',        10],
    ['EU', 'europe',           'Europe',           'Europe',        'أوروبا',         20],
    ['AS', 'asie',             'Asie',             'Asia',          'آسيا',           30],
    ['NA', 'amerique-du-nord', 'Amérique du Nord', 'North America', 'أمريكا الشمالية', 40],
    ['SA', 'amerique-du-sud',  'Amérique du Sud',  'South America', 'أمريكا الجنوبية', 50],
    ['OC', 'oceanie',          'Océanie',          'Oceania',       'أوقيانوسيا',      60],
];

/* code, continent, slug, fr, en, ar, [villes : slug, fr, en, ar] */
$PAYS = [
  ['DZ','AF','algerie','Algérie','Algeria','الجزائر',[
      ['alger','Alger','Algiers','الجزائر العاصمة'],
      ['oran','Oran','Oran','وهران'],
      ['constantine','Constantine','Constantine','قسنطينة']]],
  ['MA','AF','maroc','Maroc','Morocco','المغرب',[
      ['casablanca','Casablanca','Casablanca','الدار البيضاء'],
      ['rabat','Rabat','Rabat','الرباط'],
      ['marrakech','Marrakech','Marrakesh','مراكش']]],
  ['TN','AF','tunisie','Tunisie','Tunisia','تونس',[
      ['tunis','Tunis','Tunis','تونس العاصمة']]],
  ['EG','AF','egypte','Égypte','Egypt','مصر',[
      ['le-caire','Le Caire','Cairo','القاهرة'],
      ['alexandrie','Alexandrie','Alexandria','الإسكندرية']]],
  ['NG','AF','nigeria','Nigeria','Nigeria','نيجيريا',[
      ['lagos','Lagos','Lagos','لاغوس'],
      ['abuja','Abuja','Abuja','أبوجا']]],
  ['ZA','AF','afrique-du-sud','Afrique du Sud','South Africa','جنوب أفريقيا',[
      ['johannesburg','Johannesburg','Johannesburg','جوهانسبرغ'],
      ['le-cap','Le Cap','Cape Town','كيب تاون']]],
  ['KE','AF','kenya','Kenya','Kenya','كينيا',[
      ['nairobi','Nairobi','Nairobi','نيروبي']]],
  ['SN','AF','senegal','Sénégal','Senegal','السنغال',[
      ['dakar','Dakar','Dakar','داكار']]],

  ['FR','EU','france','France','France','فرنسا',[
      ['paris','Paris','Paris','باريس'],
      ['lyon','Lyon','Lyon','ليون'],
      ['marseille','Marseille','Marseille','مرسيليا']]],
  ['DE','EU','allemagne','Allemagne','Germany','ألمانيا',[
      ['berlin','Berlin','Berlin','برلين'],
      ['munich','Munich','Munich','ميونخ']]],
  ['ES','EU','espagne','Espagne','Spain','إسبانيا',[
      ['madrid','Madrid','Madrid','مدريد'],
      ['barcelone','Barcelone','Barcelona','برشلونة']]],
  ['GB','EU','royaume-uni','Royaume-Uni','United Kingdom','المملكة المتحدة',[
      ['londres','Londres','London','لندن'],
      ['manchester','Manchester','Manchester','مانشستر']]],
  ['IT','EU','italie','Italie','Italy','إيطاليا',[
      ['rome','Rome','Rome','روما'],
      ['milan','Milan','Milan','ميلانو']]],
  ['NL','EU','pays-bas','Pays-Bas','Netherlands','هولندا',[
      ['amsterdam','Amsterdam','Amsterdam','أمستردام'],
      ['rotterdam','Rotterdam','Rotterdam','روتردام']]],
  ['PL','EU','pologne','Pologne','Poland','بولندا',[
      ['varsovie','Varsovie','Warsaw','وارسو']]],
  ['TR','EU','turquie','Turquie','Türkiye','تركيا',[
      ['istanbul','Istanbul','Istanbul','إسطنبول'],
      ['ankara','Ankara','Ankara','أنقرة']]],

  ['CN','AS','chine','Chine','China','الصين',[
      ['shanghai','Shanghai','Shanghai','شنغهاي'],
      ['pekin','Pékin','Beijing','بكين'],
      ['shenzhen','Shenzhen','Shenzhen','شنتشن']]],
  ['IN','AS','inde','Inde','India','الهند',[
      ['mumbai','Bombay','Mumbai','مومباي'],
      ['delhi','Delhi','Delhi','دلهي'],
      ['bangalore','Bangalore','Bengaluru','بنغالور']]],
  ['JP','AS','japon','Japon','Japan','اليابان',[
      ['tokyo','Tokyo','Tokyo','طوكيو'],
      ['osaka','Osaka','Osaka','أوساكا']]],
  ['KR','AS','coree-du-sud','Corée du Sud','South Korea','كوريا الجنوبية',[
      ['seoul','Séoul','Seoul','سيول']]],
  ['SG','AS','singapour','Singapour','Singapore','سنغافورة',[
      ['singapour-ville','Singapour','Singapore','سنغافورة']]],
  ['ID','AS','indonesie','Indonésie','Indonesia','إندونيسيا',[
      ['jakarta','Jakarta','Jakarta','جاكرتا']]],
  ['MY','AS','malaisie','Malaisie','Malaysia','ماليزيا',[
      ['kuala-lumpur','Kuala Lumpur','Kuala Lumpur','كوالالمبور']]],
  ['AE','AS','emirats-arabes-unis','Émirats arabes unis','United Arab Emirates','الإمارات العربية المتحدة',[
      ['dubai','Dubaï','Dubai','دبي'],
      ['abou-dabi','Abou Dabi','Abu Dhabi','أبوظبي']]],
  ['SA','AS','arabie-saoudite','Arabie saoudite','Saudi Arabia','السعودية',[
      ['riyad','Riyad','Riyadh','الرياض'],
      ['djeddah','Djeddah','Jeddah','جدة']]],
  ['QA','AS','qatar','Qatar','Qatar','قطر',[
      ['doha','Doha','Doha','الدوحة']]],

  ['US','NA','etats-unis','États-Unis','United States','الولايات المتحدة',[
      ['new-york','New York','New York','نيويورك'],
      ['chicago','Chicago','Chicago','شيكاغو'],
      ['los-angeles','Los Angeles','Los Angeles','لوس أنجلوس']]],
  ['CA','NA','canada','Canada','Canada','كندا',[
      ['toronto','Toronto','Toronto','تورونتو'],
      ['montreal','Montréal','Montreal','مونتريال'],
      ['vancouver','Vancouver','Vancouver','فانكوفر']]],
  ['MX','NA','mexique','Mexique','Mexico','المكسيك',[
      ['mexico','Mexico','Mexico City','مكسيكو']]],

  ['BR','SA','bresil','Brésil','Brazil','البرازيل',[
      ['sao-paulo','São Paulo','São Paulo','ساو باولو'],
      ['rio-de-janeiro','Rio de Janeiro','Rio de Janeiro','ريو دي جانيرو']]],
  ['AR','SA','argentine','Argentine','Argentina','الأرجنتين',[
      ['buenos-aires','Buenos Aires','Buenos Aires','بوينس آيرس']]],
  ['CL','SA','chili','Chili','Chile','تشيلي',[
      ['santiago','Santiago','Santiago','سانتياغو']]],
  ['CO','SA','colombie','Colombie','Colombia','كولومبيا',[
      ['bogota','Bogotá','Bogotá','بوغوتا']]],

  ['AU','OC','australie','Australie','Australia','أستراليا',[
      ['sydney','Sydney','Sydney','سيدني'],
      ['melbourne','Melbourne','Melbourne','ملبورن']]],
  ['NZ','OC','nouvelle-zelande','Nouvelle-Zélande','New Zealand','نيوزيلندا',[
      ['auckland','Auckland','Auckland','أوكلاند']]],
];

$cont_id = [];
foreach ($CONTINENTS as [$code, $slug, $fr, $en, $ar, $rang]) {
    $cont_id[$code] = ins_si_absent('continents', ['code' => $code], [
        'slug' => $slug, 'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar, 'rang' => $rang,
    ]);
}

$pays_id = []; $ville_id = [];
foreach ($PAYS as [$code, $cont, $slug, $fr, $en, $ar, $villes]) {
    $pid = ins_si_absent('pays', ['code' => $code], [
        'continent_id' => $cont_id[$cont], 'slug' => $slug,
        'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar,
        // latitude / longitude : volontairement absentes.
    ]);
    $pays_id[$code] = $pid;
    foreach ($villes as [$vslug, $vfr, $ven, $var]) {
        $ville_id[$vslug] = ins_si_absent('villes', ['pays_id' => $pid, 'slug' => $vslug], [
            'nom_fr' => $vfr, 'nom_en' => $ven, 'nom_ar' => $var, 'coord_approx' => 0,
        ]);
    }
}
echo 'Geographie : ' . count($cont_id) . ' continents, ' . count($pays_id) . ' pays, '
   . count($ville_id) . " villes.\n";

/* ------------------------------------------------------------------ */
/* 2. Taxonomie thematique                                             */
/* ------------------------------------------------------------------ */

$SECTEURS = [
    ['transport',      'Transport',      'Transport',      'النقل', 10],
    ['immobilier',     'Immobilier',     'Real estate',    'العقارات', 20],
    ['energie',        'Énergie',        'Energy',         'الطاقة', 30],
    ['portuaire',      'Portuaire',      'Ports',          'الموانئ', 40],
    ['aeroportuaire',  'Aéroportuaire',  'Airports',       'المطارات', 50],
    ['industriel',     'Industriel',     'Industry',       'الصناعة', 60],
    ['numerique',      'Numérique',      'Digital',        'الرقمنة', 70],
    ['environnement',  'Environnement',  'Environment',    'البيئة', 80],
    ['patrimoine',     'Patrimoine',     'Heritage',       'التراث', 90],
];
foreach ($SECTEURS as [$slug, $fr, $en, $ar, $rang]) {
    ins_si_absent('categories', ['type' => 'secteur', 'slug' => $slug], [
        'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar, 'rang' => $rang,
    ]);
}

$TYPOLOGIES = [
    ['metro','Métro','Metro','مترو'], ['tour','Tour','Tower','برج'],
    ['stade','Stade','Stadium','ملعب'], ['autoroute','Autoroute','Motorway','طريق سريع'],
    ['data-center','Data center','Data centre','مركز بيانات'],
    ['port','Port','Port','ميناء'], ['hopital','Hôpital','Hospital','مستشفى'],
    ['universite','Université','University','جامعة'],
    ['quartier','Quartier','District','حي'],
];
foreach ($TYPOLOGIES as $i => [$slug, $fr, $en, $ar]) {
    ins_si_absent('categories', ['type' => 'typologie', 'slug' => $slug], [
        'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar, 'rang' => ($i + 1) * 10,
    ]);
}

/* ------------------------------------------------------------------ */
/* 3. Forums : Monde > Continent > Pays > Ville, plus les secteurs      */
/* ------------------------------------------------------------------ */

$rang = 0;
$forum_id = [];

foreach ($CONTINENTS as [$code, $cslug, $cfr, $cen, $car, $crang]) {
    $rang += 10;
    $fid = ins_si_absent('forums', ['slug' => 'c-' . $cslug], [
        'parent_id' => null, 'titre_fr' => $cfr, 'titre_en' => $cen, 'titre_ar' => $car,
        'description_fr' => 'Projets urbains, transports et infrastructures.',
        'description_en' => 'Urban projects, transport and infrastructure.',
        'description_ar' => 'مشاريع عمرانية ونقل وبنية تحتية.',
        'continent_id' => $cont_id[$code], 'rang' => $crang,
        'ferme' => 0, 'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
    ]);
    $forum_id['c-' . $cslug] = $fid;
}

foreach ($PAYS as [$code, $cont, $pslug, $pfr, $pen, $par, $villes]) {
    $parent = $forum_id['c-' . array_column($CONTINENTS, 1, 0)[$cont]];
    $pf = ins_si_absent('forums', ['slug' => 'p-' . $pslug], [
        'parent_id' => $parent, 'titre_fr' => $pfr, 'titre_en' => $pen, 'titre_ar' => $par,
        'pays_id' => $pays_id[$code], 'rang' => 100, 'ferme' => 0,
        'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
    ]);
    $forum_id['p-' . $pslug] = $pf;

    foreach ($villes as [$vslug, $vfr, $ven, $var]) {
        $forum_id['v-' . $vslug] = ins_si_absent('forums', ['slug' => 'v-' . $vslug], [
            'parent_id' => $pf, 'titre_fr' => $vfr, 'titre_en' => $ven, 'titre_ar' => $var,
            'pays_id' => $pays_id[$code], 'ville_id' => $ville_id[$vslug],
            'rang' => 200, 'ferme' => 0, 'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
        ]);
    }
}

$sect_racine = ins_si_absent('forums', ['slug' => 'secteurs'], [
    'parent_id' => null, 'titre_fr' => 'Secteurs', 'titre_en' => 'Sectors', 'titre_ar' => 'القطاعات',
    'description_fr' => 'Discussions transversales, par domaine plutôt que par lieu.',
    'description_en' => 'Cross-cutting discussions, by field rather than by place.',
    'description_ar' => 'نقاشات عرضية حسب المجال لا حسب المكان.',
    'rang' => 500, 'ferme' => 0, 'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
]);
foreach ($SECTEURS as [$slug, $fr, $en, $ar, $r]) {
    $cid = (int) qval('SELECT id FROM categories WHERE type = ? AND slug = ?', ['secteur', $slug]);
    $forum_id['s-' . $slug] = ins_si_absent('forums', ['slug' => 's-' . $slug], [
        'parent_id' => $sect_racine, 'titre_fr' => $fr, 'titre_en' => $en, 'titre_ar' => $ar,
        'categorie_id' => $cid, 'rang' => $r, 'ferme' => 0,
        'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
    ]);
}

$forum_id['plateforme'] = ins_si_absent('forums', ['slug' => 'plateforme'], [
    'parent_id' => null, 'titre_fr' => 'La plateforme', 'titre_en' => 'The platform',
    'titre_ar' => 'المنصة',
    'description_fr' => 'Mode d’emploi, règles, retours et signalements.',
    'description_en' => 'How it works, rules, feedback and reports.',
    'description_ar' => 'طريقة الاستخدام والقواعد والملاحظات.',
    'regles' => "- Une discussion, un sujet.\n"
              . "- **Toute donnée chiffrée doit citer sa source.** Sans source, écrivez\n"
              . "  que c'est une estimation ou une rumeur : le forum distingue les trois.\n"
              . "- Pas de contenu protégé republié sans droit.\n"
              . "- Les désaccords se discutent, les personnes ne se commentent pas.",
    'rang' => 900, 'ferme' => 0, 'nb_discussions' => 0, 'nb_messages' => 0, 'demo' => 0,
]);

echo 'Forums : ' . (int) qval('SELECT COUNT(*) FROM forums') . ".\n";

/* ------------------------------------------------------------------ */
/* 4. Membres de demonstration                                         */
/* ------------------------------------------------------------------ */

$MEMBRES = [
    ['amina_b',  'membre',               'fr'],
    ['lucas_v',  'contributeur',         'fr'],
    ['kenji_t',  'contributeur_verifie', 'en'],
    ['sara_m',   'membre',               'ar'],
    ['diego_r',  'membre',               'en'],
    ['nour_h',   'moderateur',           'ar'],
    ['ines_l',   'redacteur',            'fr'],
];
$uid = [];
foreach ($MEMBRES as [$pseudo, $role, $lang]) {
    $rid = (int) qval('SELECT id FROM roles WHERE cle = ?', [$role]);
    $id = qval('SELECT id FROM utilisateurs WHERE identifiant = ?', [$pseudo]);
    if ($id === null) {
        $id = insere('utilisateurs', [
            'identifiant' => $pseudo, 'email' => $pseudo . '@demo.invalid',
            // Mot de passe aleatoire et jamais affiche : ces comptes servent
            // a peupler l'ecran, pas a se connecter. Ils ne sont donc pas
            // une porte d'entree si la demonstration reste en ligne.
            'mot_de_passe' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role_id' => $rid, 'nom_public' => $pseudo, 'langue' => $lang,
            'cree_le' => gmdate('Y-m-d H:i:s', time() - random_int(40, 800) * 86400),
            'actif' => 1, 'banni' => 0, 'demo' => 1, 'profil_public' => 1, 'nb_messages' => 0,
            'bio' => '', 'localisation' => '',
        ]);
        prefs_notif_par_defaut((int) $id);
    }
    $uid[$pseudo] = (int) $id;
}
echo 'Membres de demonstration : ' . count($uid) . ".\n";

/* ------------------------------------------------------------------ */
/* 5. Discussions de demonstration                                     */
/* ------------------------------------------------------------------ */

function semer_discussion(int $forum, string $auteur, array $uid, string $titre,
                          array $messages, int $jours): ?int
{
    if (qval('SELECT id FROM discussions WHERE titre = ? AND forum_id = ?', [$titre, $forum]) !== null) {
        return null;
    }
    $t0 = time() - $jours * 86400;
    $did = insere('discussions', [
        'forum_id' => $forum, 'auteur_id' => $uid[$auteur],
        'titre' => $titre, 'slug' => slug_unique('discussions', slug($titre, 150)),
        'cree_le' => gmdate('Y-m-d H:i:s', $t0), 'maj_le' => gmdate('Y-m-d H:i:s', $t0),
        'epinglee' => 0, 'verrouillee' => 0, 'masquee' => 0,
        'nb_vues' => 0, 'nb_reponses' => 0, 'nb_participants' => 0, 'demo' => 1,
    ]);
    $pos = 0; $dernier = null; $dernier_le = null;
    foreach ($messages as $i => [$pseudo, $corps]) {
        $pos++;
        $le = gmdate('Y-m-d H:i:s', $t0 + $i * random_int(1800, 90000));
        $dernier = insere('messages', [
            'discussion_id' => $did, 'auteur_id' => $uid[$pseudo],
            'corps' => $corps, 'rendu' => rendre_message($corps),
            'cree_le' => $le, 'nb_editions' => 0, 'masque' => 0,
            'position' => $pos, 'ip' => '', 'demo' => 1,
        ]);
        $dernier_le = $le;
        q('UPDATE utilisateurs SET nb_messages = nb_messages + 1 WHERE id = ?', [$uid[$pseudo]]);
        indexer_message($dernier);
    }
    maj('discussions', $did, ['dernier_message_id' => $dernier, 'dernier_message_le' => $dernier_le]);
    recompter_discussion($did);
    indexer_discussion($did);
    return $did;
}

$D = [];

$D[] = semer_discussion($forum_id['plateforme'], 'nour_h', $uid,
  'Comment écrire un message : gras, citation, liste, mention',
  [
    ['nour_h', "Trois choses à connaître et vous avez fait le tour de l'éditeur.\n\n"
      . "**Le gras** s'écrit avec deux étoiles, *l'italique* avec une seule.\n\n"
      . "> Une ligne qui commence par un chevron devient une citation.\n\n"
      . "- une liste commence par un tiret\n- une ligne par élément\n\n"
      . "Pour appeler quelqu'un, écrivez son pseudo précédé d'une arobase : "
      . "@lucas_v reçoit alors une notification, à condition qu'il l'ait laissée active "
      . "dans ses paramètres.\n\n"
      . "Le bouton **Prévisualiser** montre le rendu avant publication."],
    ['lucas_v', "Reçu la notification, effectivement.\n\n"
      . "Question : est-ce qu'on peut citer plusieurs messages d'un coup ?"],
    ['nour_h', "Oui. Cliquez « Citer » sur chaque message que vous voulez reprendre : "
      . "les citations s'empilent dans le champ de réponse. Il n'y a pas de mode "
      . "« citation multiple » à activer, c'est le même bouton."],
    ['amina_b', "Et les images ? Je vois un bouton mais je ne trouve pas où téléverser."],
    ['nour_h', "Le téléversement passe par le formulaire de réponse. Une fois l'image "
      . "envoyée, elle s'insère avec un marqueur du type `!img:12`.\n\n"
      . "Deux limites volontaires : seules les images de ce site s'affichent — une adresse "
      . "d'image hébergée ailleurs reste un lien cliquable. C'est ce qui évite qu'un "
      . "message aille chercher un fichier sur un serveur tiers à chaque lecture, et donc "
      . "que l'adresse IP de chaque lecteur y soit transmise."],
  ], 21);

$D[] = semer_discussion($forum_id['plateforme'], 'nour_h', $uid,
  'Sources, estimations et rumeurs : la règle du forum',
  [
    ['nour_h', "Ce forum sépare trois niveaux d'information, et la distinction "
      . "n'est pas décorative.\n\n"
      . "- **Vérifié** : la valeur vient d'un document que vous pouvez citer.\n"
      . "- **Estimation** : c'est un calcul ou une extrapolation, et vous dites laquelle.\n"
      . "- **Rumeur** : vous l'avez lu quelque part sans pouvoir remonter à la source.\n\n"
      . "Un chiffre publié sans niveau finit recopié ailleurs comme s'il était vérifié. "
      . "C'est la seule règle du forum qui n'admet pas d'exception."],
    ['kenji_t', "This is the part most urban forums get wrong. A number posted once, "
      . "with no source, gets quoted for years.\n\n"
      . "> Un chiffre publié sans niveau finit recopié ailleurs\n\n"
      . "Exactly. I would add: when the source is a press article, link the article, "
      . "not the aggregator that copied it."],
    ['nour_h', "D'accord, et c'est déjà l'usage : le champ « source » d'une fiche projet "
      . "attend une URL et un éditeur, pas seulement un titre."],
  ], 17);

$D[] = semer_discussion($forum_id['plateforme'], 'amina_b', $uid,
  'À quoi sert le bouton « Signaler » et ce qu’il déclenche',
  [
    ['amina_b', "J'ai signalé un message hier et je n'ai pas eu de retour. Est-ce normal ?"],
    ['nour_h', "Oui, et voici pourquoi.\n\n"
      . "Un signalement passe par quatre états : nouveau, en revue, actionné, classé. "
      . "Un modérateur le prend en revue, applique une action ou le classe, et **chaque "
      . "action est écrite dans un journal consultable**. Vous ne recevez pas de message "
      . "à chaque étape : ce serait un canal facile à saturer pour quelqu'un qui signale "
      . "en masse.\n\n"
      . "Si votre contenu à vous est touché par une action de modération, là vous êtes "
      . "prévenu."],
  ], 9);

$D[] = semer_discussion($forum_id['s-transport'], 'lucas_v', $uid,
  'Comment documenter un projet de transport sans se tromper de source',
  [
    ['lucas_v', "Une méthode qui marche, indépendamment du pays :\n\n"
      . "- le **document d'enquête publique** donne le tracé et les dates de procédure ;\n"
      . "- la **délibération** de l'autorité organisatrice donne la décision, pas le coût ;\n"
      . "- le **marché attribué** donne un montant, et c'est le seul document qui en donne un.\n\n"
      . "Tout le reste — communiqués, articles, forums — cite l'un de ces trois. "
      . "Autant citer directement la pièce."],
    ['kenji_t', "Same three documents everywhere, under different names. The trap is the "
      . "press release that quotes a *range* and gets copied as a single figure."],
    ['diego_r', "> the press release that quotes a range\n\n"
      . "That is exactly how a project ends up with three different budgets on three sites, "
      . "all sourced from the same paragraph."],
  ], 12);

$D[] = semer_discussion($forum_id['s-environnement'], 'sara_m', $uid,
  'المشاركة بالعربية: هل تعمل الواجهة من اليمين إلى اليسار؟',
  [
    ['sara_m', "أكتب هذه المشاركة للتأكد من أن الواجهة تعمل فعلًا من اليمين إلى اليسار، "
      . "وليس مجرد نص عربي داخل تصميم موجّه من اليسار.\n\n"
      . "- عنصر أول في قائمة\n- عنصر ثانٍ\n\n"
      . "> اقتباس عربي داخل مشاركة عربية.\n\n"
      . "الترقيم، وشريط التنقّل، وحدود المشاركة: كلها تنقلب في الاتجاه الصحيح."],
    ['nour_h', "الاتجاه مضبوط على مستوى الصفحة، والتنسيقات تستعمل الخصائص المنطقية "
      . "لا اليمين واليسار الثابتين. لذلك تنقلب الواجهة كاملةً عند اختيار العربية.\n\n"
      . "@sara_m شكرًا على التجربة."],
  ], 6);

$D[] = semer_discussion($forum_id['v-alger'], 'amina_b', $uid,
  'Ouvrir un fil de ville : par quoi commencer',
  [
    ['amina_b', "Un fil de ville devient utile quand il rassemble trois choses :\n\n"
      . "- ce qui est **décidé** et où en est la procédure,\n"
      . "- ce qui est **visible sur place**, avec la date de l'observation,\n"
      . "- ce qui est **annoncé** et par qui.\n\n"
      . "Le troisième point est celui qui vieillit le plus vite. Autant l'écrire "
      . "avec sa date."],
    ['lucas_v', "Et dater les photos. Une photo sans date, dans deux ans, ne prouve plus rien."],
  ], 5);

$D[] = semer_discussion($forum_id['v-dubai'], 'kenji_t', $uid,
  'Reading a skyline: what a photo can and cannot tell you',
  [
    ['kenji_t', "A photograph proves that something existed at a moment. It does not "
      . "prove a height, a completion date, or a name.\n\n"
      . "- **Height** comes from the permit or the structural drawings.\n"
      . "- **Completion** comes from the handover document.\n"
      . "- **The name** changes, sometimes twice, before opening.\n\n"
      . "So: post the photo, date it, and keep the claims separate from it."],
    ['diego_r', "Agreed. I would add the direction the photo was taken from — without it, "
      . "two towers half a kilometre apart look adjacent."],
  ], 8);

$D[] = semer_discussion($forum_id['v-paris'], 'lucas_v', $uid,
  'Un fil par projet ou un fil par ville ?',
  [
    ['lucas_v', "Les deux, et le modèle du site le permet : une discussion peut "
      . "référencer plusieurs projets, et un projet peut être lié à plusieurs discussions.\n\n"
      . "En pratique : un fil de ville pour le suivi courant, un fil dédié dès qu'un "
      . "projet dépasse une centaine de messages."],
    ['amina_b', "Et quand un fil dédié s'essouffle, un modérateur peut le fusionner "
      . "dans le fil de ville. Les liens déjà partagés continuent de fonctionner, ils "
      . "redirigent vers la discussion qui a absorbé l'autre."],
    ['nour_h', "Exact. La fusion déplace les messages, conserve leur ordre et laisse une "
      . "trace dans le journal de modération."],
  ], 14);

$D[] = semer_discussion($forum_id['v-toronto'], 'diego_r', $uid,
  'Naming things: official name, working name, nickname',
  [
    ['diego_r', "Three names for the same object is normal, not an error:\n\n"
      . "- the **official name** in the permit,\n"
      . "- the **working name** used during construction,\n"
      . "- the **nickname** locals actually use.\n\n"
      . "The project record has a field for the first two. The third belongs in the "
      . "discussion, where it can be explained."],
    ['kenji_t', "And the official name is the one that changes when the building is sold."],
  ], 4);

$D[] = semer_discussion($forum_id['s-patrimoine'], 'sara_m', $uid,
  'Photographier un bâtiment : ce que l’on a le droit de publier',
  [
    ['sara_m', "La question revient à chaque fil, et la réponse dépend du pays où le "
      . "bâtiment se trouve, pas du pays où vous vivez.\n\n"
      . "Ce qui est constant : **la photo est à celui qui l'a prise**. Publier ici la "
      . "photo de quelqu'un d'autre demande son accord, même si elle circule déjà.\n\n"
      . "Le reste — panorama, œuvre récente, usage commercial — change d'un pays à l'autre. "
      . "Si vous n'êtes pas sûr, dites-le dans le message plutôt que de publier."],
    ['nour_h', "@sara_m merci, c'est exactement la formulation que je mettrai dans les règles."],
  ], 3);

$D = array_values(array_filter($D));
echo 'Discussions de demonstration : ' . count($D) . ".\n";

/* ------------------------------------------------------------------ */
/* 5 bis. Portail : rubriques et articles de demonstration             */
/* ------------------------------------------------------------------ */

/* Les RUBRIQUES sont une structure, pas du contenu : elles survivent a
   purge-demo.php, comme la geographie et les forums. Une rubrique vide
   affiche « aucun article pour l'instant », qui est l'etat normal d'un
   portail neuf. */
$RUBRIQUES = [
    ['actualites', 'Actualités', 'News', 'أخبار', 10,
     "Ce qui vient de sortir : décisions, annonces, mises en service.",
     'What just happened: decisions, announcements, openings.',
     'ما استجدّ: قرارات وإعلانات ومنشآت دخلت الخدمة.'],
    ['projets', 'Projets', 'Projects', 'مشاريع', 20,
     "Un projet, son état d'avancement et les sources qui le documentent.",
     'A project, its stage, and the sources that document it.',
     'مشروع وحالة تقدّمه والمصادر التي توثّقه.'],
    ['mobilite', 'Mobilité et transports', 'Mobility and transport', 'التنقّل والنقل', 30,
     "Réseaux, lignes, gares, et ce qu'ils changent pour la ville.",
     'Networks, lines, stations, and what they change for the city.',
     'الشبكات والخطوط والمحطّات وما تغيّره في المدينة.'],
    ['patrimoine', 'Patrimoine et paysage urbain', 'Heritage and urban landscape',
     'التراث والمشهد العمراني', 40,
     "Ce qui existait avant, et ce qu'on en fait.",
     'What was there before, and what is being done with it.',
     'ما كان قائمًا من قبل، وما يُفعل به.'],
    ['logement', 'Logement et densité', 'Housing and density', 'السكن والكثافة', 50,
     "Où l'on construit, combien, et pour qui.",
     'Where building happens, how much, and for whom.',
     'أين يُبنى وبأي حجم ولمن.'],
    ['chantiers', 'Chantiers', 'Construction sites', 'أوراش البناء', 60,
     "L'avancement vu du sol, mois après mois.",
     'Progress seen from the ground, month after month.',
     'التقدّم كما يُرى من الأرض، شهرًا بعد شهر.'],
    ['debats', 'Débats', 'Debates', 'نقاشات', 70,
     "Les désaccords qui valent d'être posés à plat.",
     'The disagreements worth laying out in full.',
     'الخلافات التي تستحق أن تُعرض كاملة.'],
];
$rub_id = [];
foreach ($RUBRIQUES as [$slug, $fr, $en, $ar, $rang, $dfr, $den, $dar]) {
    $rub_id[$slug] = ins_si_absent('rubriques', ['slug' => $slug], [
        'nom_fr' => $fr, 'nom_en' => $en, 'nom_ar' => $ar, 'rang' => $rang,
        'description_fr' => $dfr, 'description_en' => $den, 'description_ar' => $dar,
        'demo' => 0,
    ]);
}
echo 'Rubriques : ' . count($rub_id) . ".\n";

/*
 * ARTICLES DE DEMONSTRATION.
 *
 * AUCUN N'AFFIRME UN FAIT SUR UN PROJET REEL. Pas un budget, pas une
 * hauteur, pas une date de livraison, pas un nom de maitre d'ouvrage. Ils
 * parlent du portail lui-meme : ce qu'il publie, comment on y ecrit,
 * pourquoi une fiche reste vide sans source.
 *
 * La raison est la meme que pour les discussions de demonstration : une
 * phrase ecrite pour meubler une maquette se retrouve indexee, citee, puis
 * reprise ailleurs comme si quelqu'un l'avait verifiee. Un article de
 * portail a une signature et une date — il a exactement l'apparence d'une
 * information verifiee.
 *
 * Et ils ne citent AUCUNE source, volontairement : c'est ce qui rend
 * visible la mention « cet article ne cite aucune source » sur la page. Une
 * source inventee pour faire joli serait le defaut meme que cette mention
 * existe pour signaler.
 */
function semer_article(array $uid, array $rub_id, string $auteur, string $langue,
                       ?string $rubrique, string $titre, string $chapeau, string $corps,
                       array $opts = []): ?int
{
    if (qval('SELECT id FROM articles WHERE titre = ? AND langue = ?', [$titre, $langue]) !== null) {
        return null;                                   // deja seme
    }
    $id = enregistrer_article(null, [
        'titre' => $titre, 'chapeau' => $chapeau, 'corps' => $corps,
        'langue' => $langue,
        'rubrique_id' => $rubrique ? $rub_id[$rubrique] : null,
        'signature' => $opts['signature'] ?? '',
        'statut' => $opts['statut'] ?? 'publie',
        'une' => $opts['une'] ?? false,
        'rang_une' => $opts['rang_une'] ?? 100,
        'publie_le' => $opts['publie_le'] ?? gmdate('Y-m-d H:i:s', time() - random_int(1, 25) * 86400),
        'groupe' => $opts['groupe'] ?? '',
        'demo' => 1,
    ], $uid[$auteur]);
    // Aucun article de demonstration n'est rattache a une ville : un texte
    // qui explique le fonctionnement du portail n'a pas a apparaitre dans la
    // page d'une ville reelle comme s'il parlait d'elle. Le rattachement
    // geographique existe dans le formulaire de redaction et il est verifie
    // par la suite de controle, pas peuple ici.
    return $id;
}

$groupe_charte = bin2hex(random_bytes(8));

$A = [];
$A[] = semer_article($uid, $rub_id, 'ines_l', 'fr', 'debats',
    "Ce que ce portail publie, et ce qu'il ne publie pas",
    "Une règle simple : un chiffre sans source ne s'écrit pas. Ni ici, ni dans une fiche projet, ni dans un titre.",
    "Un portail urbain vit de chiffres : une hauteur, un budget, un nombre de logements, "
  . "une date de mise en service. Ce sont les phrases les plus reprises, et ce sont "
  . "exactement celles qui se propagent le plus vite quand elles sont fausses.\n\n"
  . "La règle de ce site tient en une ligne :\n\n"
  . "- un fait vient d'une source, ou il n'est pas écrit ;\n"
  . "- une estimation est présentée comme une estimation ;\n"
  . "- une rumeur porte le mot rumeur, ou elle attend.\n\n"
  . "**Concrètement.** Chaque article a un bloc *Sources* sous le texte. S'il est vide, "
  . "la page l'écrit : « cet article ne cite aucune source ». La mention est là pour se "
  . "voir. Un texte sans source doit ressembler à un texte sans source, pas à une "
  . "information vérifiée.\n\n"
  . "> Le jour où un chiffre non sourcé passe en page d'accueil, il devient la référence "
  . "de quelqu'un d'autre. C'est le moment où l'on cesse de pouvoir le corriger.\n\n"
  . "Cela vaut aussi pour ce que le portail *ne reprend pas* : le nom, les textes, "
  . "l'interface ou les données d'un autre forum. On construit les mêmes fonctions, "
  . "on n'emprunte pas l'identité.",
    ['une' => true, 'rang_une' => 1, 'signature' => 'La rédaction', 'groupe' => $groupe_charte]);

$A[] = semer_article($uid, $rub_id, 'kenji_t', 'en', 'debats',
    'What this portal publishes, and what it does not',
    "One rule: a figure without a source does not get written. Not here, not in a project record, not in a headline.",
    "An urban portal runs on figures: a height, a budget, a number of homes, an opening "
  . "date. Those are the most quoted sentences on any such site, and they are exactly the "
  . "ones that travel fastest when they are wrong.\n\n"
  . "The rule here fits on one line:\n\n"
  . "- a fact comes from a source, or it is not written;\n"
  . "- an estimate is labelled as an estimate;\n"
  . "- a rumour carries the word rumour, or it waits.\n\n"
  . "**In practice.** Every article has a *Sources* block under the text. If it is empty, "
  . "the page says so: \"this article cites no source\". That notice exists to be seen. "
  . "A text without sources should look like a text without sources, not like verified "
  . "information.\n\n"
  . "> The day an unsourced figure reaches the front page, it becomes someone else's "
  . "reference. That is the moment it stops being correctable.\n\n"
  . "The same applies to what the portal does *not* borrow: the name, texts, interface or "
  . "data of another forum. We build the same functions; we do not take the identity.",
    ['signature' => 'The editors', 'groupe' => $groupe_charte]);

$A[] = semer_article($uid, $rub_id, 'sara_m', 'ar', 'debats',
    'ما تنشره هذه البوّابة وما لا تنشره',
    'قاعدة واحدة: رقم بلا مصدر لا يُكتب. لا هنا، ولا في بطاقة مشروع، ولا في عنوان.',
    "تعيش أي بوّابة عمرانية على الأرقام: ارتفاع، ميزانية، عدد مساكن، تاريخ دخول الخدمة. "
  . "وهي أكثر الجمل اقتباسًا، وأسرعها انتشارًا حين تكون خاطئة.\n\n"
  . "القاعدة هنا تختصر في سطر:\n\n"
  . "- الواقعة تأتي من مصدر، أو لا تُكتب؛\n"
  . "- التقدير يُعرض بوصفه تقديرًا؛\n"
  . "- الإشاعة تحمل كلمة إشاعة، أو تنتظر.\n\n"
  . "**عمليًا.** لكل مقال كتلة *المصادر* تحت النص. وإن كانت فارغة قالت الصفحة ذلك: "
  . "«لا يستند هذا المقال إلى أي مصدر». هذا التنبيه موجود ليُرى. النص بلا مصادر يجب أن "
  . "يبدو نصًا بلا مصادر، لا معلومة موثّقة.\n\n"
  . "> يوم يصل رقم بلا مصدر إلى الصفحة الأولى يصير مرجعًا لغيرنا. عندها يتوقّف عن كونه "
  . "قابلًا للتصحيح.",
    ['signature' => 'هيئة التحرير', 'groupe' => $groupe_charte]);

$A[] = semer_article($uid, $rub_id, 'ines_l', 'fr', 'actualites',
    "Comment proposer un article",
    "Le portail est ouvert aux contributions. Voici ce qu'on attend d'un texte avant de le publier.",
    "Écrire ici ne demande pas d'être journaliste. Cela demande trois choses.\n\n"
  . "**Un titre qui dit ce qui s'est passé.** Pas une question, pas une accroche. "
  . "Un lecteur doit savoir de quoi il s'agit sans ouvrir.\n\n"
  . "**Un chapeau de deux ou trois phrases.** C'est ce qui s'affiche en liste, dans le "
  . "flux et dans les résultats de recherche. Laissé vide, il est repris du début du "
  . "texte, ce qui donne rarement un bon résumé.\n\n"
  . "**Au moins une source, avec son éditeur et sa date.** Un communiqué, un document "
  . "d'urbanisme, un compte rendu de séance, un article de presse. Une capture d'écran "
  . "sans adresse n'est pas une source.\n\n"
  . "L'article se rattache ensuite à une ville. Le pays et le continent en sont déduits "
  . "automatiquement — c'est ce rattachement qui fait apparaître le texte sur la page de "
  . "la ville, et qui choisit le forum où s'ouvrira la discussion.",
    ['une' => true, 'rang_une' => 2, 'signature' => 'La rédaction']);

$A[] = semer_article($uid, $rub_id, 'lucas_v', 'fr', 'projets',
    "Pourquoi une fiche projet peut rester vide",
    "Les champs budget, hauteur et date de livraison existent déjà. Ils sont vides, et c'est volontaire.",
    "La base contient tout ce qu'il faut pour décrire un projet : nom officiel et nom "
  . "d'usage, statut, coordonnées, budget, hauteur, surface, date de livraison prévue, "
  . "intervenants, sources.\n\n"
  . "Ces champs sont vides pour l'instant. Les remplir avec des valeurs plausibles pour "
  . "que la maquette « ait l'air complète » reviendrait à publier des chiffres que "
  . "personne n'a vérifiés, sous une mise en page qui leur donnerait l'autorité d'une "
  . "fiche technique.\n\n"
  . "Chaque champ porte donc son *niveau d'information* : vérifié, estimation, rumeur. "
  . "Une fiche à moitié remplie et honnête est plus utile qu'une fiche complète et fausse "
  . "— surtout parce que la seconde ne se corrige jamais : personne ne va vérifier ce qui "
  . "a déjà l'air sûr.",
    ['signature' => 'Lucas V.']);

$A[] = semer_article($uid, $rub_id, 'kenji_t', 'en', 'mobilite',
    'Reading a project record: status, sources, level of information',
    'Three fields decide how much weight a record deserves. They are the first three to read.',
    "A project record here carries a **status** (proposed, approved, tender, under "
  . "construction, on hold, completed, cancelled), a **level of information** (verified, "
  . "estimate, rumour) and a list of **sources**.\n\n"
  . "Read them in that order, before the figures.\n\n"
  . "A record marked *rumour* with no source is not a weaker version of a verified "
  . "record — it is a different kind of object. It says: someone reported this, nobody "
  . "has confirmed it. Keeping the two visually distinct is the whole point of having "
  . "the field at all.\n\n"
  . "When a figure changes, the previous value is not overwritten in silence: the change "
  . "is kept, with its source and its date. That history is what lets a reader see "
  . "whether a project has been slipping for three years.",
    ['signature' => 'Kenji T.']);

$A[] = semer_article($uid, $rub_id, 'nour_h', 'ar', 'patrimoine',
    'كيف نصف مكانًا دون أن ندّعي معرفته',
    'وصف حيّ قديم أسهل ما يُكتب وأسرع ما يُخطئ. بعض القواعد العملية.',
    "أسهل ما يُكتب عن حيّ قديم هو ما يبدو بديهيًا: تاريخ البناء، اسم المعماري، سبب الهدم. "
  . "وهذه بالضبط أكثر المعلومات تداولًا بلا سند.\n\n"
  . "ثلاث قواعد تكفي غالبًا:\n\n"
  . "- اكتب ما تراه، وميّزه عمّا قرأته؛\n"
  . "- إن كان التاريخ محلّ خلاف، فاذكر الخلاف بدل أن تختار؛\n"
  . "- الصورة ليست مصدرًا لتاريخ، بل لحالة المكان يوم التقاطها.\n\n"
  . "لا يعني هذا الامتناع عن الكتابة. يعني أن يعرف القارئ، في كل جملة، من أين جاءت.",
    ['signature' => 'نور ح.']);

// Un brouillon et un article PROGRAMME : les deux etats doivent exister
// quelque part, sinon rien ne prouve qu'ils se comportent differemment d'un
// article en ligne. Le programme est date dans le FUTUR — il ne doit
// apparaitre ni sur le portail, ni dans le flux, ni dans le sitemap, ni
// dans la recherche, et son adresse directe doit repondre 404 au public.
$A[] = semer_article($uid, $rub_id, 'ines_l', 'fr', 'chantiers',
    "Brouillon de démonstration",
    "Ce texte est enregistré mais pas publié. Il ne doit apparaître nulle part côté public.",
    "Un brouillon sert à vérifier une chose précise : qu'il ne fuit pas.\n\n"
  . "Il ne figure pas sur le portail, pas dans le flux RSS, pas dans le sitemap, pas dans "
  . "la recherche. Son adresse directe répond 404 à un visiteur, et affiche un bandeau "
  . "d'aperçu à un rédacteur connecté.",
    ['statut' => 'brouillon']);

$A[] = semer_article($uid, $rub_id, 'ines_l', 'fr', 'chantiers',
    "Article programmé de démonstration",
    "Publié, mais daté dans le futur. Invisible jusqu'à l'heure dite, y compris par son adresse directe.",
    "La date de publication est relue **à chaque affichage**, pas une seule fois au "
  . "moment de l'enregistrement. Un article daté de la semaine prochaine est donc "
  . "invisible aujourd'hui pour tout le monde, y compris pour qui devinerait son adresse.\n\n"
  . "C'est la différence entre une date décorative et une date qui compte.",
    ['statut' => 'publie', 'publie_le' => gmdate('Y-m-d H:i:s', time() + 7 * 86400)]);

$A = array_values(array_filter($A));
echo 'Articles de demonstration : ' . count($A) . ".\n";

// Une discussion ouverte depuis un article, pour que le lien portail -> forum
// se voie. Elle porte demo = 1 parce que l'article la porte.
$prem = qval('SELECT id FROM articles WHERE statut = ? AND une = 1 ORDER BY rang_une LIMIT 1', ['publie']);
if ($prem !== null && qval('SELECT discussion_id FROM articles WHERE id = ?', [(int) $prem]) === null) {
    discussion_de_article((int) $prem, $uid['ines_l']);
}

/* ------------------------------------------------------------------ */
/* 6. Etats qui rendent la demonstration realiste                      */
/* ------------------------------------------------------------------ */

// Une discussion epinglee, une verrouillee : les deux etats doivent se voir.
$epingle = qval('SELECT id FROM discussions WHERE titre LIKE ? LIMIT 1',
                ['Comment écrire un message%']);
if ($epingle !== null) maj('discussions', (int) $epingle, ['epinglee' => 1]);
$verrou = qval('SELECT id FROM discussions WHERE titre LIKE ? LIMIT 1',
               ['Sources, estimations%']);
if ($verrou !== null) maj('discussions', (int) $verrou, ['verrouillee' => 1]);

// Abonnements, signets, reactions.
foreach ($D as $did) {
    foreach (['amina_b', 'lucas_v'] as $p) {
        try {
            insere('abonnements', ['utilisateur_id' => $uid[$p], 'objet_type' => 'discussion',
                                   'objet_id' => $did, 'cree_le' => maintenant()]);
        } catch (Throwable) {}
    }
    $premier = qval('SELECT id FROM messages WHERE discussion_id = ? ORDER BY position LIMIT 1', [$did]);
    if ($premier !== null) {
        foreach (['kenji_t' => 'utile', 'diego_r' => 'accord', 'sara_m' => 'merci'] as $p => $type) {
            try {
                insere('reactions', ['message_id' => (int) $premier, 'utilisateur_id' => $uid[$p],
                                     'type' => $type, 'cree_le' => maintenant()]);
            } catch (Throwable) {}
        }
    }
}

// Un signalement ouvert, pour que la file de moderation ne soit pas vide.
if (qval('SELECT COUNT(*) FROM signalements') == 0) {
    $cible = qval('SELECT id FROM messages WHERE demo = 1 ORDER BY id DESC LIMIT 1');
    if ($cible !== null) {
        insere('signalements', [
            'signaleur_id' => $uid['diego_r'], 'objet_type' => 'message',
            'objet_id' => (int) $cible, 'motif' => 'horssujet',
            'commentaire' => "Signalement de démonstration : rien à reprocher à ce message, "
                           . "il sert à montrer la file de modération.",
            'priorite' => 'normale', 'etat' => 'nouveau', 'cree_le' => maintenant(),
        ]);
    }
}

// Compteurs de forum, une fois tout en place.
foreach (qtous('SELECT id FROM forums') as $f) recompter_forum((int) $f['id']);

$n = reindexer_tout();
echo 'Index de recherche : ' . array_sum($n) . " entrees d'objets.\n";
echo "Termine. Le bandeau « contenu de demonstration » reste affiche tant que\n";
echo "cfg('mode_demo') vaut true. Pour nettoyer : php outils/purge-demo.php\n";
