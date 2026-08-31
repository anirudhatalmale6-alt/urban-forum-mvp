<h1><?= h(t('adm_journal_erreurs')) ?></h1>
<p class="lede"><?= h(cfg('chemin_journal')) ?></p>
<?php if (!$fichiers): ?>
  <p class="avis avis--ok"><?= h(t('aucun_resultat')) ?></p>
<?php else: ?>
<p><?php foreach ($fichiers as $f): ?>
  <a class="btn btn--petit<?= $f === $choisi ? ' btn--plein' : '' ?>" href="<?= h(lien('/admin/journal?f=' . rawurlencode($f))) ?>"><?= h($f) ?></a>
<?php endforeach; ?></p>
<?php if (!$lignes): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th>ts</th><th>niveau</th><th>message</th><th>uri</th></tr></thead>
  <tbody><?php foreach ($lignes as $l): ?>
    <tr>
      <td><?= h((string) ($l['ts'] ?? '')) ?></td>
      <td><span class="etiq<?= in_array($l['niveau'] ?? '', ['critique', 'fatale'], true) ? ' etiq--haute' : '' ?>"><?= h((string) ($l['niveau'] ?? '')) ?></span></td>
      <td><?= h((string) ($l['message'] ?? '')) ?>
        <?php if (!empty($l['ctx'])): ?><br><small><?= h(json_encode($l['ctx'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></small><?php endif; ?></td>
      <td><?= h((string) ($l['uri'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
