<?= fil([[t('geo_monde'), '/'], [t('nav_villes'), null]]) ?>
<h1><?= h(t('nav_villes')) ?></h1>
<p class="lede"><?= h(nombre(count($villes))) ?> <?= h(mb_strtolower(t('geo_ville'))) ?></p>
<?php if (!$villes): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php endif; ?>
<div class="grille grille--3">
  <?php foreach ($villes as $v): ?>
  <a class="carte" href="<?= h(lien('/v/' . $v['slug'])) ?>">
    <h3><?= h(champ_langue($v)) ?></h3>
    <p class="carte__meta"><?= h(champ_langue($v, 'pays')) ?>
      · <?= h(nombre((int) $v['nb_discussions'])) ?> <?= h(mb_strtolower(t('forum_discussions'))) ?></p>
  </a>
  <?php endforeach; ?>
</div>
