<?php
/** Gabarit unique. $corps_page contient la vue deja rendue. */
$m = $GLOBALS['uf_meta'] ?? [];
$titre = $m['titre'] ?? cfg('nom_site');
$desc  = $m['description'] ?? t('accueil_intro');
$u     = utilisateur();
$non_lues = $u ? notifications_non_lues((int) $u['id']) : 0;
$canonique = cfg('domaine') ? url($m['canonique'] ?? ($_SERVER['REQUEST_URI'] ?? '/')) : '';
?><!doctype html>
<html lang="<?= h(langue()) ?>" dir="<?= h(direction()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titre) ?><?= $titre === cfg('nom_site') ? '' : ' — ' . h(cfg('nom_site')) ?></title>
<meta name="description" content="<?= h($desc) ?>">
<?php if (!empty($m['noindex'])): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($canonique): ?>
<link rel="canonical" href="<?= h($canonique) ?>">
<?php else: ?>
<!-- Pas de <link rel="canonical"> : il exige une URL absolue et le domaine
     n'est pas encore renseigne. Une canonique fausse est pire qu'absente —
     elle desindexe. Elle apparait des que cfg('domaine') est rempli. -->
<?php endif; ?>
<?php foreach (langues_dispo() as $l): ?>
<link rel="alternate" hreflang="<?= h($l) ?>" href="<?= h(($canonique ?: ($_SERVER['REQUEST_URI'] ?? '/')) . (str_contains($canonique ?: ($_SERVER['REQUEST_URI'] ?? '/'), '?') ? '&' : '?') . 'lang=' . $l) ?>">
<?php endforeach; ?>
<meta property="og:type" content="<?= h($m['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= h($titre) ?>">
<meta property="og:description" content="<?= h($desc) ?>">
<meta property="og:locale" content="<?= h(langue()) ?>">
<?php if ($canonique): ?><meta property="og:url" content="<?= h($canonique) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary">
<link rel="stylesheet" href="/assets/style.css?v=1">
<link rel="icon" href="/assets/marque.svg" type="image/svg+xml">
<?php if (!empty($GLOBALS['uf_ld_fil'])): ?>
<script type="application/ld+json"><?= json_encode($GLOBALS['uf_ld_fil'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<?php if (!empty($m['ld'])): ?>
<script type="application/ld+json"><?= json_encode($m['ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
</head>
<body class="lang-<?= h(langue()) ?>">
<a class="saut" href="#contenu"><?= h(t('aller_contenu')) ?></a>

<?php if (cfg('mode_demo')): ?>
<div class="bandeau-demo" role="note"><?= h(t('demo_bandeau')) ?></div>
<?php endif; ?>

<header class="entete">
  <div class="entete__barre">
    <a class="marque" href="<?= h(lien('/')) ?>">
      <span class="marque__mot"><?= h(cfg('nom_site')) ?></span>
      <?php if (cfg('nom_provisoire')): ?><span class="marque__prov"><?= h(t('provisoire')) ?></span><?php endif; ?>
    </a>

    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav-principale">
      <span></span><span></span><span></span>
      <span class="hors-ecran"><?= h(t('nav_forums')) ?></span>
    </button>

    <nav id="nav-principale" class="nav" aria-label="<?= h(t('nav_forums')) ?>">
      <a href="<?= h(lien('/actualites')) ?>"><?= h(t('portail_actualites')) ?></a>
      <a href="<?= h(lien('/forums')) ?>"><?= h(t('nav_forums')) ?></a>
      <a href="<?= h(lien('/villes')) ?>"><?= h(t('nav_villes')) ?></a>
      <a href="<?= h(lien('/projets')) ?>"><?= h(t('nav_projets')) ?></a>
      <?php /* Pas de lien « Recherche » ici : le champ de recherche est juste
               a cote, avec son propre bouton du meme nom. Deux commandes
               identiques cote a cote font perdre une ligne d'entete a
               1280 px et n'apportent rien. */ ?>
      <?php if ($u && peut('portail.rediger')): ?>
        <a href="<?= h(lien('/admin/articles')) ?>"><?= h(t('portail_gestion')) ?></a>
      <?php endif; ?>
      <?php if ($u && peut('moderation.file')): ?>
        <a href="<?= h(lien('/moderation')) ?>"><?= h(t('nav_moderation')) ?></a>
      <?php endif; ?>
      <?php if ($u && peut('admin.statistiques')): ?>
        <a href="<?= h(lien('/admin')) ?>"><?= h(t('nav_admin')) ?></a>
      <?php endif; ?>
    </nav>

    <form class="rech-rapide" action="<?= h(lien('/recherche')) ?>" method="get" role="search">
      <?php if (langue() !== cfg('langue_defaut')): ?><input type="hidden" name="lang" value="<?= h(langue()) ?>"><?php endif; ?>
      <label class="hors-ecran" for="q-rapide"><?= h(t('nav_recherche')) ?></label>
      <input id="q-rapide" type="search" name="q" placeholder="<?= h(t('rech_placeholder')) ?>"
             value="<?= h($_GET['q'] ?? '') ?>" autocomplete="off">
      <?php /* Bouton a la loupe. Avec le mot « Recherche » ecrit dedans, le
               bouton mangeait les deux tiers de la largeur du bloc et il ne
               restait qu'une soixantaine de pixels pour le champ — mesure a
               l'ecran, pas devinee. Le nom reste lu par les lecteurs
               d'ecran via .hors-ecran et par title. Le SVG est en ligne
               dans le document : aucune requete, et la CSP ne bloque que le
               STYLE en ligne, pas le balisage. */ ?>
      <button type="submit" title="<?= h(t('nav_recherche')) ?>">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="2"></circle>
          <line x1="11" y1="11" x2="15" y2="15" stroke="currentColor" stroke-width="2"
                stroke-linecap="round"></line>
        </svg>
        <span class="hors-ecran"><?= h(t('nav_recherche')) ?></span>
      </button>
    </form>

    <div class="compte">
      <?php if ($u): ?>
        <a class="cloche" href="<?= h(lien('/notifications')) ?>">
          <?= h(t('nav_notifications')) ?>
          <?php if ($non_lues): ?><span class="pastille"><?= h(nombre($non_lues)) ?></span><?php endif; ?>
        </a>
        <a href="<?= h(lien('/u/' . rawurlencode((string) $u['identifiant']))) ?>"><?= avatar((string) $u['identifiant'], 28) ?><?= h($u['identifiant']) ?></a>
        <form method="post" action="<?= h(lien('/deconnexion')) ?>" class="en-ligne">
          <?= csrf_champ() ?><button class="lien-bouton" type="submit"><?= h(t('nav_deconnexion')) ?></button>
        </form>
      <?php else: ?>
        <a href="<?= h(lien('/connexion')) ?>"><?= h(t('nav_connexion')) ?></a>
        <a class="btn btn--plein" href="<?= h(lien('/inscription')) ?>"><?= h(t('nav_inscription')) ?></a>
      <?php endif; ?>
      <div class="langues">
        <?php foreach (langues_dispo() as $l):
            $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            $qs = $_GET; $qs['lang'] = $l; ?>
          <a<?= $l === langue() ? ' class="actif" aria-current="true"' : '' ?>
             href="<?= h($uri . '?' . http_build_query($qs)) ?>" lang="<?= h($l) ?>"><?= h(t('langue_' . $l)) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</header>

<main id="contenu" class="page">
<?= $corps_page ?>
</main>

<footer class="pied">
  <div class="pied__cols">
    <div>
      <p class="pied__marque"><?= h(cfg('nom_site')) ?></p>
      <p class="pied__ligne"><?= t('accueil_intro') ?></p>
    </div>
    <nav aria-label="<?= h(t('sitemap')) ?>">
      <a href="<?= h(lien('/actualites')) ?>"><?= h(t('portail_actualites')) ?></a>
      <a href="<?= h(lien('/communaute')) ?>"><?= h(t('nav_communaute')) ?></a>
      <a href="<?= h(lien('/forums')) ?>"><?= h(t('nav_forums')) ?></a>
      <a href="<?= h(lien('/villes')) ?>"><?= h(t('nav_villes')) ?></a>
      <a href="<?= h(lien('/projets')) ?>"><?= h(t('nav_projets')) ?></a>
      <a href="<?= h(lien('/aide')) ?>"><?= h(t('nav_aide')) ?></a>
      <a href="/sitemap.xml"><?= h(t('sitemap')) ?></a>
      <a href="/flux.xml"><?= h(t('portail_flux')) ?></a>
      <a href="<?= h(lien('/a-renseigner')) ?>"><?= h(t('nav_a_renseigner')) ?></a>
    </nav>
    <div class="pied__legal">
      <p><?= h(t('pied_mentions')) ?></p>
      <p><?= valeur(cfg('entite_juridique'), 'entite_juridique') ?></p>
      <p><?= valeur(cfg('adresse_postale'), 'adresse_postale') ?></p>
      <p><?= valeur(cfg('hebergeur'), 'hebergeur') ?></p>
    </div>
  </div>
</footer>

<script src="/assets/forum.js?v=1" defer></script>
</body>
</html>
