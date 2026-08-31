<?php /** Arborescence des forums : racines puis enfants. */
$racines = array_values(array_filter($tous, fn($f) => empty($f['parent_id'])));
$enfants_de = function (int $id) use ($tous) {
    return array_values(array_filter($tous, fn($f) => (int)($f['parent_id'] ?? 0) === $id));
};
?>
<?= fil([[t('geo_monde'), '/'], [t('nav_forums'), null]]) ?>
<h1><?= h(t('nav_forums')) ?></h1>
<p class="lede"><?= h(t('accueil_intro')) ?></p>

<?php if (connecte() && peut('forum.publier')): ?>
  <p><a class="btn btn--plein" href="<?= h(lien('/nouvelle-discussion')) ?>"><?= h(t('forum_nouvelle_discussion')) ?></a></p>
<?php endif; ?>

<?php if (!$racines): ?>
  <p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p>
<?php endif; ?>

<?php foreach ($racines as $r): $sous = $enfants_de((int) $r['id']); ?>
<h2><a href="<?= h(lien('/f/' . $r['slug'])) ?>"><?= h(champ_langue($r, 'titre')) ?></a></h2>
<?php if (champ_langue($r, 'description')): ?><p class="carte__meta"><?= h(champ_langue($r, 'description')) ?></p><?php endif; ?>
<div class="liste">
  <?php foreach (($sous ?: [$r]) as $f): $ag = compteurs_agreges((int) $f['id'], $tous); ?>
  <div class="liste__ligne">
    <div>
      <a class="liste__titre" href="<?= h(lien('/f/' . $f['slug'])) ?>"><?= h(champ_langue($f, 'titre')) ?></a>
      <?php if (champ_langue($f, 'description')): ?>
        <div class="liste__sous"><?= h(champ_langue($f, 'description')) ?></div>
      <?php endif; ?>
    </div>
    <div class="liste__nb"><b><?= h(nombre($ag['discussions'])) ?></b><?= h(t('forum_discussions')) ?></div>
    <div class="liste__nb"><b><?= h(nombre($ag['messages'])) ?></b><?= h(t('forum_messages')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
