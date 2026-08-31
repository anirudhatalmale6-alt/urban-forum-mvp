<?= fil([
  [t('geo_monde'), '/'],
  [champ_langue($p, 'cont'), '/continent/' . $p['continent_slug']],
  [champ_langue($p), null],
]) ?>
<h1><?= h(champ_langue($p)) ?></h1>
<p class="lede"><?= h(nombre(count($villes))) ?> <?= h(mb_strtolower(t('geo_ville'))) ?></p>

<h2><?= h(t('nav_villes')) ?></h2>
<?php if (!$villes): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<div class="grille grille--4">
  <?php foreach ($villes as $v): ?>
    <a class="carte" href="<?= h(lien('/v/' . $v['slug'])) ?>"><h3><?= h(champ_langue($v)) ?></h3></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($forums): ?>
<h2><?= h(t('nav_forums')) ?></h2>
<div class="liste">
  <?php foreach ($forums as $f): ?>
  <div class="liste__ligne">
    <div><a class="liste__titre" href="<?= h(lien('/f/' . $f['slug'])) ?>"><?= h(champ_langue($f, 'titre')) ?></a></div>
    <div class="liste__nb"><b><?= h(nombre((int) $f['nb_discussions'])) ?></b><?= h(t('forum_discussions')) ?></div>
    <div class="liste__nb"><b><?= h(nombre((int) $f['nb_messages'])) ?></b><?= h(t('forum_messages')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

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
