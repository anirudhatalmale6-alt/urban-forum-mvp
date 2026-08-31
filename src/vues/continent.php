<?= fil([[t('geo_monde'), '/'], [champ_langue($c), null]]) ?>
<h1><?= h(champ_langue($c)) ?></h1>
<p class="lede"><?= h(nombre(count($pays))) ?> <?= h(mb_strtolower(t('geo_pays'))) ?></p>
<?php if (!$pays): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<div class="grille grille--4">
  <?php foreach ($pays as $p): ?>
  <a class="carte" href="<?= h(lien('/pays/' . $p['slug'])) ?>">
    <h3><?= h(champ_langue($p)) ?></h3>
    <p class="carte__meta"><?= h(nombre((int) $p['nb_villes'])) ?> <?= h(mb_strtolower(t('geo_ville'))) ?></p>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
