<h1><?= h(t('vide_titre')) ?></h1>
<p class="lede"><?= h(t('vide_intro')) ?></p>
<?php if (!$champs): ?>
  <p class="avis avis--ok"><?= h(t('vide_aucun')) ?></p>
<?php else: ?>
<p><b><?= h(nombre(count($champs))) ?></b></p>
<div class="tableau-boite">
<table class="tableau">
  <thead><tr><th><?= h(t('vide_champ')) ?></th><th>config</th></tr></thead>
  <tbody>
    <?php foreach ($champs as $c): ?>
    <tr><td><?= h($c['libelle']) ?></td><td><code><?= h($c['cle']) ?></code></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
