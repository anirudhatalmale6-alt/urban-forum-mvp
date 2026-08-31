# URBAN FORUM — Phase 1 (MVP) + Portail

Plateforme communautaire internationale dédiée à l'urbanisme, l'architecture
et aux infrastructures. **Portail éditorial public devant un forum structuré
géographiquement**, multilingue FR / EN / AR, prêt à recevoir les fiches
projets et la cartographie de la phase 2.

Référence du cahier des charges : **UF-CDC-01 v1.0**.
Ce dépôt couvre la **Phase 1 — MVP** de la section 13, il est vérifié
contre les **dix critères de recette de la section 14**, et il ajoute le
**portail** (voir la section 13 ci-dessous).

---

## 1. Ce qui est fait, ce qui ne l'est pas

**Fait (phase 1).** Comptes et rôles, forum hiérarchique
Monde → Continent → Pays → Ville → Secteur, discussions et messages,
éditeur enrichi avec citations simples et multiples, mentions, réactions,
signets, abonnements, brouillons, prévisualisation, historique d'édition
avec motif, signalements, file et journal de modération, notifications,
recherche plein texte avec synonymes et tolérance aux fautes, médias,
i18n FR/EN/AR avec RTL réel, SEO, administration, sauvegarde et
restauration, API JSON documentée.

**Fait (portail).** Page d'accueil éditoriale, rubriques, articles avec
chapeau, signature, rattachement géographique et bloc de sources, mise à
la une, programmation d'une publication à une date future, aperçu des
brouillons réservé aux rédacteurs, rôle `redacteur` distinct de la
modération, flux RSS, discussion de forum ouverte depuis un article,
articles dans les résultats de recherche et dans le sitemap.

**Pas fait, et volontairement (phase 2 et suivantes).** Fiches projets
remplies, cartographie, entreprises vérifiées, badges attribués
automatiquement, réputation, applications mobiles, traduction automatique.
Les **tables existent déjà** — `projets`, `projet_maj`, `projet_entreprise`,
`entreprises`, `sources`, `badges` — donc la phase 2 remplit un schéma en
place, elle ne le refait pas.

---

## 2. La décision technique, et pourquoi

Le cahier des charges recommande Next.js + NestJS + PostgreSQL + Redis +
OpenSearch. C'est le bon choix sur le papier. Aucun des cinq ne tourne sur
l'hébergement mutualisé visé : ni Node, ni PostgreSQL, ni Redis, ni
OpenSearch, et l'accès SSH est fermé.

Le code est donc en **PHP 8 + MySQL**, sans aucune dépendance externe et
sans composer : il se dépose et il tourne. Ce qui est préservé pour plus
tard :

- l'**API JSON** existe et appelle les mêmes fonctions de domaine que les
  pages HTML — donc une application mobile ou un front Next.js peut se
  brancher sans réécrire les règles ;
- le **modèle de données** est celui de la section 9, complet ;
- la **recherche** est isolée derrière une seule fonction,
  `recherche_executer()`. Passer à OpenSearch, c'est réécrire cette
  fonction, rien d'autre.

Le jour où un VPS est disponible, ce qui change est la couche de
présentation, pas le métier.

---

## 3. Installation

### Avec un accès SSH

```bash
php outils/installer.php     # tables, rôles, permissions, compte admin
php outils/semer.php         # contenu de démonstration (facultatif)
php -S 127.0.0.1:8830 -t public public/index.php
```

### Sans accès SSH (Hostinger, cPanel)

1. Déposer le dossier. La racine du domaine doit pointer sur **`public/`**.
   `src/`, `donnees/` et `outils/` restent **au-dessus** de la racine web.
2. Ouvrir `https://votre-domaine/install.php` et cliquer sur *Installer*.
3. **Noter le mot de passe administrateur affiché** : il n'apparaît qu'une
   fois et n'est écrit dans aucun fichier.
4. L'installateur essaie de se supprimer. S'il annonce qu'il n'y est pas
   arrivé, effacer `public/install.php` à la main, immédiatement.

### Base MySQL en production

Dans `src/config.local.php` (créé par l'installateur) :

```php
return [
    'sel_session' => '…',                 // déjà généré, ne pas y toucher
    'bd' => ['pilote' => 'mysql', 'hote' => 'localhost',
             'base' => 'u640394362_xxx', 'user' => '…', 'passe' => '…'],
    'domaine' => 'https://votre-domaine.com',
    'cookie_secure' => true,
];
```

Puis relancer l'installateur : le schéma est produit dans le dialecte du
pilote choisi, à partir de la **même** définition (`src/schema.php`). Il n'y
a pas deux fichiers SQL à garder synchronisés.

`src/config.local.php` n'est **pas** dans le dépôt : il porte le sel de
session et le mot de passe de la base.

---

## 4. Ce qu'il reste à renseigner

La page **`/a-renseigner`** liste, à tout moment, les valeurs que je n'ai pas
et que je n'invente pas. Elles s'affichent sur le site comme une pastille
visible « à renseigner », jamais comme un tiret discret que personne ne
remarque.

Aujourd'hui :

| Valeur | Où |
|---|---|
| Nom définitif de la plateforme | `nom_site` + `nom_provisoire => false` |
| Signature / baseline | `baseline` |
| Nom de domaine | `domaine` — **il commande le sitemap et les canoniques** |
| Raison sociale, forme, immatriculation | `entite_juridique` |
| Adresse postale du siège | `adresse_postale` |
| Directeur de la publication | `directeur_publication` |
| Hébergeur (mentions légales) | `hebergeur` |
| Adresse d'expédition des e-mails | `mail_expediteur` |
| Base MySQL de production | `bd` |
| Coordonnées des villes | table `villes`, ou l'écran *Taxonomie* |

**« URBAN FORUM » est un nom de travail**, tiré du titre de ton document. Il
porte une étiquette *nom provisoire* dans l'en-tête tant que
`nom_provisoire` vaut `true`. Rien du nom, des textes, de l'interface ou des
données de SkyscraperCity n'est repris — c'est la règle de ta section 18 et
je la partage.

### Sur les chiffres

Aucune fiche projet n'est pré-remplie. Un budget, une hauteur, une surface
ou une date de livraison est un **fait** : il vient d'une source ou il reste
vide. La page `/projets` indique, pour chaque champ, quel document en est la
source légitime.

Les coordonnées géographiques ne sont pas semées non plus. En particulier,
une ville sans coordonnée reste à `NULL` et **jamais à `0, 0`** — un point
réel au large du golfe de Guinée, qui apparaîtrait sur la carte comme une
ville qui s'y trouve.

---

## 5. Contenu de démonstration

Le site est livré peuplé : sinon on ne peut rien juger. Mais aucun message
de démonstration n'affirme un fait sur un projet réel — ils parlent de la
plateforme elle-même : comment écrire, citer, mentionner, signaler. Une
phrase inventée pour meubler une maquette se retrouve indexée, citée, puis
reprise ailleurs, et plus personne ne se souvient qu'elle venait d'un jeu de
test.

Un bandeau le dit sur chaque page. Pour partir en production :

```bash
php outils/purge-demo.php               # compte ce qui serait supprimé
php outils/purge-demo.php --supprimer   # le fait
```

Ce qui disparaît : membres, discussions, messages, **articles** et médias
marqués `demo = 1`, avec tout ce qui en dépend — y compris la discussion
ouverte depuis un article de démonstration. Ce qui reste : la géographie
(6 continents, 35 pays, 63 villes), la taxonomie, les 115 forums, les
**7 rubriques** du portail et les rôles. Une rubrique vide affiche « aucun
article pour l'instant », qui est l'état normal d'un portail neuf.
Ensuite, `'mode_demo' => false` dans `src/config.local.php` retire le
bandeau.

---

## 6. Vérification

```bash
python3 tests/tests-forum.py http://127.0.0.1:8830
```

**322 contrôles, 0 échec** à la dernière exécution, dans l'ordre des dix
critères de recette de la section 14, plus une section entière consacrée au
portail. Ce qui est réellement mesuré :

1. **Navigation sans compte** — 18 pages publiques, et le *contenu* est
   compté : liens de forum, villes, discussions et articles. Une page qui
   répond 200 autour de zéro contenu est une page cassée.
2. **Inscription → connexion → discussion → réponse → image** — parcours
   complet par HTTP. Le rendu du gras, de la citation, de la liste et de la
   mention est vérifié dans le HTML produit.
3. **Notifications** — la mention notifie, l'abonnement notifie, **et une
   préférence désactivée empêche la notification**. C'est la seconde moitié
   du critère qui compte ; vérifier seulement la première ne prouve rien.
4. **Modération** — un membre signale, un modérateur prend en revue, masque,
   et le journal est relu : il doit nommer le modérateur, l'action, l'objet,
   le signalement et l'heure.
5. **Recherche** — mot rare, mot courant, synonyme (`subway` retrouve un
   message qui ne contient que `metro`), faute de frappe, autocomplétion,
   requête sans résultat.
6. **SEO et mobile** — titre unique, description, Open Graph, hreflang ×3,
   `DiscussionForumPosting`, `BreadcrumbList`, et **aucun débordement
   horizontal** mesuré sur `scrollWidth` à 1280 et 390 px.
7. **FR / EN / AR** — les trois dictionnaires portent exactement les mêmes
   310 clés, et la marque se déplace réellement de x=56 px à x=981 px quand
   on passe en arabe.
8. **Permissions** — bloquées côté interface **et** côté API, vérifié pour
   l'anonyme et pour un membre simple, plus CSRF, discussion verrouillée,
   injection, XSS et en-têtes de sécurité.
9. **Sauvegarde et restauration** — les deux scripts sont exécutés, la
   restauration d'essai compare **le contenu** et pas seulement les comptes,
   et une sauvegarde volontairement abîmée doit être **détectée**.
10. **Journal d'erreurs** — un incident est **provoqué**, puis retrouvé dans
    le journal. Un journal vide passerait toujours.

11. **Le portail** — un brouillon et un article programmé répondent 404 au
    public et n'apparaissent ni dans `/actualites`, ni dans la recherche, ni
    dans le flux, ni dans le sitemap ; un membre simple ne peut pas écrire ;
    sans `portail.publier` le serveur ramène l'article à « brouillon » ; un
    article ouvre bien une discussion qui pointe vers lui sans le recopier ;
    le HTML saisi ressort échappé ; un titre entièrement arabe produit une
    adresse ASCII qui répond 200 ; le flux et le sitemap répondent 503 sans
    domaine et sont corrects avec.

Plus : contraste WCAG AA mesuré sur les couleurs **calculées par le
navigateur** sur 36 pages, zéro erreur de console, et le parcours complet
rejoué **avec JavaScript désactivé**.

### Ce que la suite a réellement attrapé

Elle n'est pas décorative. Pendant le développement elle a trouvé :

- la politique de sécurité du site bloquait tous les `style=` en ligne —
  les avatars n'avaient plus de couleur, **sans aucune erreur serveur** ;
- `.compte a` battait `.btn--plein` en spécificité : le bouton *Inscription*
  affichait du gris sur du bleu, mesuré à 2,1 pour 1 ;
- `<cite>` dans une citation : `--gris-500` vaut 4,6 pour 1 sur blanc mais
  4,35 sur le gris de la citation. Un jeton de couleur n'a pas *un*
  contraste, il en a un **par fond** ;
- `.chiffre span` sur la bande sombre : 2,24 pour 1.

Aucune de ces quatre ne se voit en relisant la feuille de style.

Pour le portail elle a trouvé, en plus : **les adresses non-ASCII qui
répondaient 404** et **les permissions qui ne venaient pas de la base**
(section 13).

Elle peut échouer, et c'est vérifié plutôt qu'affirmé. Deux mutations
volontaires ont été passées au rouge : donner `moderation.file` au rôle
*membre* (attrapé côté interface **et** côté API), et retirer la condition
« date de publication passée » du portail (quatre contrôles au rouge, dont
l'index de recherche qui contenait alors un article de plus qu'il n'y a
d'articles visibles).

Captures : `apercus/`, 40 images, toutes sous 2000 px.

---

## 7. Architecture

```
public/          racine web — SEUL dossier exposé
  index.php      contrôleur frontal, pages ET API
  install.php    installateur navigateur (à supprimer après usage)
  assets/        style.css, forum.js, marque.svg
src/
  config.php     configuration livrée (valeurs vides = à renseigner)
  config.local.php   secrets, hors dépôt
  schema.php     LE schéma, émis en SQLite ou en MySQL
  noyau.php      config, PDO, journal, limitation de débit
  auth.php       sessions, rôles, permissions, CSRF
  messages.php   écriture d'un message (partagée HTML / API / outils)
  balisage.php   éditeur → HTML sûr
  recherche.php  index inversé, synonymes, suggestions
  notifications.php  moderation.php  medias.php
  i18n.php  lang/{fr,en,ar}.php
  vue.php   vues/*.php
  routes/   table.php (routage + permissions), public, compte, ecrire,
            mod, admin, api, seo
donnees/         HORS racine web : base, médias, journaux, sauvegardes
outils/          installer, semer, sauvegarde, restauration, purge-demo
tests/           suite de contrôles + captures
```

### Trois choix qui méritent une phrase

**Le schéma est décrit une seule fois** (`src/schema.php`) et émis dans le
dialecte du pilote. Deux fichiers `.sql` dérivent : on corrige une colonne
dans l'un, on oublie l'autre, et l'écart ne se voit qu'en production sur
l'autre moteur.

**Le contrôle d'accès est déclaratif.** Chaque route déclare sa permission
dans `src/routes/table.php`, et le routeur vérifie CSRF puis permission
avant d'appeler le contrôleur. Un contrôleur ne peut donc pas « oublier »
la vérification : ce n'est pas de son ressort. C'est aussi ce qui rend le
critère 8 vérifiable — l'API ne peut pas être plus permissive que
l'interface, elles partagent le chemin d'autorisation.

**Le HTML des utilisateurs n'est jamais accepté.** Le corps d'un message est
échappé en entier, puis on ré-injecte un petit nombre de balises qu'on écrit
soi-même. On ne nettoie donc jamais un HTML hostile — on n'en reçoit pas.
C'est la seule défense qui ne dépende pas d'une liste noire toujours
incomplète.

---

## 8. Sécurité

- **Le dossier des médias est hors de la racine web.** Un répertoire
  d'upload servi en direct est un chemin d'exécution tant qu'on n'a pas
  prouvé le contraire. Ici la question ne se pose pas : c'est `/media/<id>`
  qui relit le fichier, avec un type MIME que *nous* choisissons, et le type
  est déterminé par `getimagesize()`, pas par l'extension ni par l'en-tête
  du navigateur.
- CSP sans `unsafe-inline` ni script distant, `nosniff`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`.
- Toutes les requêtes SQL sont préparées. Il n'y a pas une seule
  concaténation de valeur dans une chaîne SQL du projet.
- Mots de passe en `password_hash()`, sessions par jeton haché en base,
  cookies `HttpOnly` + `SameSite=Lax` (+ `Secure` quand `cookie_secure`).
- Limitation de débit sur connexion, inscription, publication, signalement
  et téléversement. Piège à robots sur l'inscription : il répond 200 et une
  page normale — un 403 apprendrait au robot quel champ éviter.
- Aucun appel réseau sortant : une image distante dans un message reste un
  lien, elle ne devient pas une balise `<img>`. Sinon l'adresse IP de chaque
  lecteur part chez un tiers à chaque affichage.

---

## 9. Sauvegarde et restauration

```bash
php outils/sauvegarde.php                            # base + médias
php outils/sauvegarde.php --sql                      # + un .sql pour phpMyAdmin
php outils/restauration.php <fichier.jsonl> --essai  # test SANS toucher la prod
php outils/restauration.php <fichier.jsonl>          # restauration réelle
```

Tout est en PHP : sur un hébergement mutualisé, `mysqldump` est souvent
inaccessible, et une procédure qui ne marche que sur la machine du
développeur n'est pas une procédure.

**Le format est du JSON, une ligne par enregistrement, et pas du SQL.** La
première version écrivait des `INSERT` et échappait les retours à la ligne
en `\n` — ce que MySQL redécode en retour à la ligne et que SQLite laisse
tel quel, deux caractères. Le même fichier restaurait donc un texte
*différent* selon le moteur. En JSON il n'y a plus d'échappement à inventer,
et la restauration passe par des requêtes préparées : c'est le pilote qui
met les valeurs en forme.

**Et la vérification compare le contenu, pas les comptes.** Le premier
contrôle comparait le nombre de lignes par table : il répondait
« restauration vérifiée » avec un contenu abîmé, puisque le nombre de lignes,
lui, ne bouge pas. Le mode `--essai` compare maintenant **trois** empreintes
— celle du fichier, celle de la base restaurée, et celle de la **base
d'origine**. Seule la troisième prouve quelque chose : comparer un fichier à
la base restaurée depuis ce fichier, c'est comparer un fichier à lui-même.

La suite de contrôles abîme volontairement un seul retour à la ligne dans un
seul message et exige que la vérification le détecte. Même nombre de lignes,
empreinte différente, échec — c'est ce qui rend le critère 9 crédible.

La sauvegarde relit aussi son propre fichier après écriture et l'archive des
médias est rouverte pour recompter ses entrées. Une sauvegarde qu'on n'a pas
rouverte n'est pas une sauvegarde, c'est un fichier.

À copier **hors du serveur** : une sauvegarde qui vit sur la machine
sauvegardée disparaît avec elle.

## 10. API JSON

`GET /api/v1` liste les points d'entrée.

```
GET  /api/v1/portail
GET  /api/v1/articles?rubrique=&langue=&page=1
GET  /api/v1/articles/{slug}
GET  /api/v1/forums
GET  /api/v1/forums/{slug}?page=1
GET  /api/v1/discussions/{slug}?page=1
GET  /api/v1/recherche?q=&espace=forum|projets|portail&tri=pertinence|date|activite
GET  /api/v1/autocomplete?q=
GET  /api/v1/notifications
GET  /api/v1/moderation/file
POST /api/v1/apercu       (corps, _csrf)
POST /api/v1/messages     (discussion, corps, _csrf)
```

Authentification par le cookie de session ; les écritures exigent le jeton
`_csrf`, comme les formulaires. Les permissions sont exactement celles de
l'interface.

`/api/v1/articles/{slug}` sert le HTML **déjà assaini** : un consommateur de
l'API n'a jamais à interpréter lui-même la syntaxe de l'éditeur. Et l'API
n'a **pas** de mode aperçu : un brouillon y répond 404 même pour un
rédacteur connecté, alors que l'interface le lui montre avec un bandeau.

---

## 11. Limites connues

Elles sont écrites ici pour ne pas être découvertes en production.

- **La recherche** compare la requête aux termes distincts de l'index pour
  tolérer les fautes. C'est bon jusqu'à quelques centaines de milliers de
  messages ; au-delà, c'est le moment de passer à OpenSearch comme le
  recommande la section 8 — et seule `recherche_executer()` change.
- **Les e-mails ne partent pas** tant que `mail_expediteur` est vide. Le
  centre de notifications le dit explicitement plutôt que de faire croire
  qu'un message est parti, et la colonne `email_envoye` reste à 0.
- **Le sitemap répond 503** tant que le domaine n'est pas renseigné : il
  n'accepte que des URL absolues, et un sitemap d'URL relatives est rejeté
  par les moteurs sans le moindre message. Mieux vaut un refus explicite
  qu'un fichier faux.
- **La vidéo** s'affiche comme une façade cliquable et non comme un lecteur
  intégré chargé d'office, pour la même raison que les images distantes.
- Le compteur de vues déduplique par empreinte et par jour : un compteur
  gonflé par les rechargements est un compteur qu'on ne peut plus citer.
- **Le chemin MySQL n'a pas été exécuté ici.** Les 231 contrôles ont tourné
  sur SQLite ; je n'ai pas d'instance MySQL accessible sur cette machine. Le
  DDL MySQL est produit par la même définition et j'ai vérifié à la main les
  trois pièges connus — `CREATE INDEX` sans `IF NOT EXISTS` (rattrapé sur
  l'erreur 1061), aucune valeur par défaut sur une colonne `TEXT`, et toutes
  les colonnes indexées en `UNIQUE` sous la limite de longueur de clé
  d'`utf8mb4`. Ce n'est pas la même chose que de l'avoir fait tourner :
  quand tu me donnes une base MySQL, je relance la suite dessus avant de
  déclarer quoi que ce soit.

---

## 12. La suite

Phase 2, dans l'ordre où je la propose : fiches projets structurées avec
sources et historique, puis cartographie. La carte a besoin des coordonnées
des villes — c'est la seule donnée bloquante, et l'écran *Taxonomie* permet
déjà de les saisir une par une ou de les laisser vides.

---

## 13. Le portail

### Ce qu'il est

Le forum est une conversation ; le portail est ce que voit quelqu'un qui
n'a pas encore de compte. Il lit, il comprend de quoi parle le site, et
s'il veut participer il descend dans la discussion attachée à l'article.

- `/` — le portail : à la une, derniers articles, rubriques, dernières
  discussions du forum, villes, chiffres.
- `/actualites` — tous les articles publiés, filtrables par langue.
- `/r/<rubrique>` — une rubrique.
- `/a/<article>` — un article.
- `/flux.xml` — le flux RSS.
- `/communaute` — l'ancienne page d'accueil du forum. Elle n'a pas disparu,
  elle a changé d'adresse.
- `/admin/articles` — la rédaction, réservée à `portail.rediger`.

### Trois règles qui ne sont pas décoratives

**Un article est écrit dans UNE langue, pas dans trois.** La colonne
`groupe` relie les versions d'un même sujet ; `langue` dit dans laquelle
celle-ci est écrite. Trois colonnes `titre_fr` / `titre_en` / `titre_ar`
auraient obligé à inventer une traduction pour pouvoir enregistrer — et une
traduction inventée est un texte que personne n'a relu. Quand un lecteur
arabe ouvre le portail, il voit la version arabe d'un sujet si elle existe
*vraiment* ; sinon il voit l'original, avec une étiquette de langue et les
attributs `lang` / `dir` qui vont avec.

**Une date de publication dans le futur est une programmation.** Elle est
relue à *chaque affichage*, pas testée une fois à l'enregistrement. Un
article daté de la semaine prochaine n'apparaît ni sur le portail, ni dans
le flux, ni dans le sitemap, ni dans la recherche, et son adresse directe
répond 404 au public. Un rédacteur connecté, lui, la voit avec un bandeau
d'aperçu et un `noindex`.

**Publier est une permission séparée d'écrire.** `portail.rediger` permet
d'écrire et d'enregistrer ; `portail.publier` de mettre en ligne ;
`portail.une` de choisir la une. Sans `portail.publier`, l'interface cache
le bouton **et le serveur ramène l'article à « brouillon »** même si le
champ est forgé. L'interface qui cache et le serveur qui refuse sont deux
choses différentes ; seule la seconde protège.

### Les sources

Un budget, une hauteur, une date de livraison sont des faits : ils viennent
d'une source ou ils ne sont pas écrits. Chaque article a un bloc *Sources*
(table `sources`, la même que pour les fiches projets). **S'il est vide, la
page le dit** : « cet article ne cite aucune source ». La mention est là
pour se voir. Un texte sans source doit ressembler à un texte sans source,
pas à une information vérifiée.

Les articles de démonstration livrés n'affirment rien sur un projet réel :
ils expliquent le fonctionnement du portail. Et ils ne citent aucune
source, volontairement — c'est ce qui rend la mention visible.

### Deux corrections trouvées en construisant le portail

**Les adresses non-ASCII répondaient 404.** Les motifs de la table de
routage s'écrivent `[\w\-]+`, et `\w` sans le drapeau `/u` ne couvre que
l'ASCII. Un titre arabe produisait un slug que le routeur ne reconnaissait
plus : la page répondait 404 alors que la ligne existait en base. Une
discussion arabe de la démonstration était dans ce cas depuis la première
livraison. `slug()` produit désormais de l'ASCII, et l'installeur réécrit
les adresses fautives une fois, au passage.

La translittération seule ne suffisait pas : `Any-Latin; Latin-ASCII`
laisse passer des lettres modificatives comme « ʿ » (U+02BF) ou « ⁿ »
(U+207F), qui ressemblent à de l'ASCII sans en être.

**Les permissions ne venaient pas de la base.** `peut()` lisait la
constante `ROLES` du code ; la table `role_permissions`, remplie par
l'installeur, n'était jamais relue. Elle était donc décorative, et la page
d'administration des rôles montrait le code plutôt que ce que le serveur
applique. C'est maintenant la table qui fait foi, avec deux garde-fous :
un rôle déclaré `*` reste tout-puissant sans passer par la table, et un
rôle sans aucune ligne retombe sur la déclaration du code — une table vide
veut dire « pas encore installé », jamais « plus aucun droit ».

### Un délai qu'il faut connaître

Après modification de `src/config.local.php`, **le serveur met quelques
secondes à voir le changement** — mesuré jusqu'à trois secondes ici. C'est
le comportement normal d'un cache de bytecode. Si le domaine vient d'être
renseigné et que `/flux.xml` répond encore 503, il suffit d'attendre et de
recharger.
