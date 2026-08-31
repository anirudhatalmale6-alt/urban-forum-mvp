<h1><?= h((string) $code) ?></h1>
<p class="lede"><?= h($message) ?></p>
<p><a class="btn" href="<?= h(lien('/')) ?>"><?= h(t('nav_accueil')) ?></a>
   <a class="btn" href="<?= h(lien('/forums')) ?>"><?= h(t('nav_forums')) ?></a></p>
