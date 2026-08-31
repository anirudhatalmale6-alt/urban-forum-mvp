<h1><?= h(t('nav_signets')) ?></h1>
<?php if (!$lignes): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<div class="liste">
  <?php foreach ($lignes as $d): ?>
  <div class="liste__ligne">
    <div><a class="liste__titre" href="<?= h(lien('/d/' . $d['slug'])) ?>"><?= h($d['titre']) ?></a>
      <div class="liste__sous"><?= h(il_y_a((string) $d['mis_le'])) ?></div></div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_reponses'])) ?></b><?= h(t('forum_reponses')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
