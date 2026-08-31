<?php /** Une rubrique du portail. */ ?>

<?= fil([[cfg('nom_site'), '/'], [t('portail_actualites'), '/actualites'], [champ_langue($r)]]) ?>

<div class="tete-page">
  <h1><?= h(champ_langue($r)) ?></h1>
  <?php if (champ_langue($r, 'description') !== ''): ?>
    <p class="lede"><?= h(champ_langue($r, 'description')) ?></p>
  <?php endif; ?>
</div>

<?php if (!$articles): ?>
  <p class="vide-etat"><?= h(t('portail_rubrique_vide')) ?></p>
  <p><a href="<?= h(lien('/actualites')) ?>"><?= h(t('portail_tout_voir')) ?></a></p>
<?php else: ?>
  <p class="compte-resultats"><?= h(tn('portail_n_articles', (int) $total)) ?></p>
  <div class="grille-art">
    <?php foreach ($articles as $a): $grande = false; include __DIR__ . '/_carte_article.php'; endforeach; ?>
  </div>
  <?= pagination($page, $total, $pp, '/r/' . $r['slug']) ?>
<?php endif; ?>
