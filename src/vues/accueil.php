<?php /** Accueil : tendances, monde, villes, chiffres. Section 5. */ ?>

<div class="hero">
  <h1><?= h(cfg('nom_site')) ?><?php if (cfg('baseline')): ?> — <?= h(cfg('baseline')) ?><?php endif; ?></h1>
  <p class="lede"><?= h(t('accueil_intro')) ?></p>
  <p>
    <a class="btn btn--plein" href="<?= h(lien('/forums')) ?>"><?= h(t('nav_forums')) ?></a>
    <a class="btn" href="<?= h(lien('/villes')) ?>"><?= h(t('nav_villes')) ?></a>
    <?php if (!connecte()): ?>
      <a class="btn" href="<?= h(lien('/inscription')) ?>"><?= h(t('nav_inscription')) ?></a>
    <?php endif; ?>
  </p>
</div>

<h2><?= h(t('accueil_tendances')) ?></h2>
<?php if (!$actives): ?>
  <p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p>
<?php else: ?>
<div class="liste">
  <?php foreach ($actives as $d): ?>
  <div class="liste__ligne">
    <div>
      <?php if ((int) $d['epinglee']): ?><span class="etiq etiq--epingle"><?= h(t('forum_epinglee')) ?></span><?php endif; ?>
      <?php if ((int) $d['demo']): ?><span class="etiq etiq--demo">démo</span><?php endif; ?>
      <a class="liste__titre" href="<?= h(lien('/d/' . $d['slug'])) ?>"><?= h($d['titre']) ?></a>
      <div class="liste__sous">
        <a href="<?= h(lien('/f/' . $d['forum_slug'])) ?>"><?= h(champ_langue($d, 'titre')) ?></a>
        · <?= h(t('forum_par')) ?> <?= h((string) $d['identifiant']) ?>
        · <time datetime="<?= h(str_replace(' ', 'T', (string) $d['cree_le'])) ?>Z"><?= h(il_y_a((string) $d['dernier_message_le'])) ?></time>
      </div>
    </div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_reponses'])) ?></b><?= h(t('forum_reponses')) ?></div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_vues'])) ?></b><?= h(t('forum_vues')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2><?= h(t('accueil_continents')) ?></h2>
<div class="grille grille--4">
  <?php foreach ($continents as $c): ?>
  <a class="carte" href="<?= h(lien('/continent/' . $c['slug'])) ?>">
    <h3><?= h(champ_langue($c)) ?></h3>
    <p class="carte__meta"><?= h(nombre((int) qval('SELECT COUNT(*) FROM pays WHERE continent_id = ?', [(int) $c['id']]))) ?> <?= h(mb_strtolower(t('geo_pays'))) ?></p>
  </a>
  <?php endforeach; ?>
</div>

<h2><?= h(t('accueil_villes')) ?></h2>
<div class="grille grille--3">
  <?php foreach ($villes as $v): ?>
  <a class="carte" href="<?= h(lien('/v/' . $v['slug'])) ?>">
    <h3><?= h(champ_langue($v)) ?></h3>
    <p class="carte__meta"><?= h(champ_langue($v, 'pays')) ?></p>
  </a>
  <?php endforeach; ?>
</div>

<section class="bande">
  <h2><?= h(t('accueil_stats')) ?></h2>
  <div class="chiffres">
    <div class="chiffre"><b><?= h(nombre($stats['membres'])) ?></b><span><?= h(t('adm_membres')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['discussions'])) ?></b><span><?= h(t('forum_discussions')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['messages'])) ?></b><span><?= h(t('forum_messages')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['pays'])) ?></b><span><?= h(t('geo_pays')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['villes'])) ?></b><span><?= h(t('geo_ville')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['projets'])) ?></b><span><?= h(t('nav_projets')) ?></span></div>
  </div>
  <p><?= h(t('proj_phase2')) ?></p>
</section>
