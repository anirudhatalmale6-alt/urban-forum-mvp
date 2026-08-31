<?= fil([
  [t('geo_monde'), '/'],
  [champ_langue($v, 'cont'), '/continent/' . $v['continent_slug']],
  [champ_langue($v, 'pays'), '/pays/' . $v['pays_slug']],
  [champ_langue($v), null],
]) ?>
<h1><?= h(champ_langue($v)) ?></h1>
<p class="lede"><?= h(champ_langue($v, 'pays')) ?></p>

<div class="grille grille--2 esp-bas-l">
  <div class="carte">
    <h3><?= h(t('nav_carte')) ?></h3>
    <?php if ($v['latitude'] === null || $v['longitude'] === null): ?>
      <p class="carte__meta">
        <?= valeur('', 'ville-' . $v['slug'] . '-coordonnees') ?>
        — <?= h(t('proj_phase2')) ?>
      </p>
    <?php else: ?>
      <p class="carte__meta"><?= h((string) $v['latitude']) ?>, <?= h((string) $v['longitude']) ?>
        <?= (int) $v['coord_approx'] ? '(' . h(t('proj_niveau_estimation')) . ')' : '' ?></p>
    <?php endif; ?>
  </div>
  <div class="carte">
    <h3><?= h(t('nav_forums')) ?></h3>
    <?php if (!$forums): ?>
      <p class="carte__meta"><?= h(t('forum_aucune_discussion')) ?></p>
    <?php else: ?>
      <ul><?php foreach ($forums as $f): ?>
        <li><a href="<?= h(lien('/f/' . $f['slug'])) ?>"><?= h(champ_langue($f, 'titre')) ?></a></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>

<h2><?= h(t('accueil_tendances')) ?></h2>
<?php if (!$discussions): ?><p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p><?php else: ?>
<div class="liste">
  <?php foreach ($discussions as $d): ?>
  <div class="liste__ligne">
    <div><a class="liste__titre" href="<?= h(lien('/d/' . $d['slug'])) ?>"><?= h($d['titre']) ?></a>
      <div class="liste__sous"><?= h(t('forum_par')) ?> <?= h((string) $d['identifiant']) ?></div></div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_reponses'])) ?></b><?= h(t('forum_reponses')) ?></div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_vues'])) ?></b><?= h(t('forum_vues')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
