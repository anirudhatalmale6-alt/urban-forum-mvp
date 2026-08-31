<?php /** Tous les articles publies, du plus recent au plus ancien. */ ?>

<?= fil([[cfg('nom_site'), '/'], [t('portail_actualites')]]) ?>

<div class="tete-page">
  <h1><?= h(t('portail_actualites')) ?></h1>
  <p class="lede"><?= h(t('portail_intro')) ?></p>
</div>

<form class="filtres" method="get" action="<?= h(lien('/actualites')) ?>">
  <?php if (langue() !== cfg('langue_defaut')): ?><input type="hidden" name="lang" value="<?= h(langue()) ?>"><?php endif; ?>
  <label for="f-langue"><?= h(t('portail_filtre_langue')) ?></label>
  <select id="f-langue" name="langue">
    <option value=""><?= h(t('portail_toutes_langues')) ?></option>
    <?php foreach (cfg('langues') as $l): ?>
      <option value="<?= h($l) ?>"<?= $langue_filtre === $l ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit"><?= h(t('rech_filtrer')) ?></button>
  <a class="lien-discret" href="/flux.xml"><?= h(t('portail_flux')) ?></a>
</form>

<?php if (!$articles): ?>
  <p class="vide-etat"><?= h(t('portail_aucun_article')) ?></p>
<?php else: ?>
  <p class="compte-resultats"><?= h(tn('portail_n_articles', (int) $total)) ?></p>
  <div class="grille-art">
    <?php foreach ($articles as $a): $grande = false; include __DIR__ . '/_carte_article.php'; endforeach; ?>
  </div>
  <?= pagination($page, $total, $pp, '/actualites' . ($langue_filtre ? '?langue=' . $langue_filtre : '')) ?>
<?php endif; ?>
