<h1><?= h(t('mod_journal')) ?></h1>
<p class="lede"><a href="<?= h(lien('/moderation')) ?>"><?= h(t('mod_file')) ?></a></p>
<?php if (!$lignes): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<div class="tableau-boite">
<table class="tableau">
  <thead><tr>
    <th><?= h(t('rech_tri_date')) ?></th><th><?= h(t('role_moderateur')) ?></th>
    <th><?= h(t('mod_action')) ?></th><th>objet</th><th><?= h(t('mod_motif')) ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?>
    <tr>
      <td><time datetime="<?= h(str_replace(' ', 'T', (string) $l['cree_le'])) ?>Z"><?= h(date_lisible((string) $l['cree_le'])) ?></time></td>
      <td><?= h((string) $l['identifiant']) ?></td>
      <td><span class="etiq"><?= h((string) $l['action']) ?></span></td>
      <td><?= h((string) $l['objet_type']) ?> #<?= (int) $l['objet_id'] ?></td>
      <td><?= $l['motif'] ? h((string) $l['motif']) : valeur('', 'mod-motif') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
