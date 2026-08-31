<h1><?= h(t('adm_titre')) ?></h1>
<p class="lede">
  <a href="<?= h(lien('/admin/taxonomie')) ?>"><?= h(t('adm_taxonomie')) ?></a> ·
  <a href="<?= h(lien('/admin/permissions')) ?>"><?= h(t('adm_permissions')) ?></a> ·
  <a href="<?= h(lien('/admin/journal')) ?>"><?= h(t('adm_journal_erreurs')) ?></a> ·
  <a href="<?= h(lien('/moderation/journal')) ?>"><?= h(t('mod_journal')) ?></a> ·
  <a href="<?= h(lien('/a-renseigner')) ?>"><?= h(t('nav_a_renseigner')) ?></a>
</p>

<div class="chiffres">
  <div class="chiffre"><b><?= h(nombre($chiffres['membres'])) ?></b><span><?= h(t('adm_membres')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($chiffres['inscriptions'])) ?></b><span><?= h(t('adm_inscriptions')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($chiffres['messages_24h'])) ?></b><span><?= h(t('adm_messages_jour')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($chiffres['actives_7j'])) ?></b><span><?= h(t('adm_discussions_actives')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($chiffres['projets'])) ?></b><span><?= h(t('adm_projets')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($chiffres['signalements'])) ?></b><span><?= h(t('adm_signalements')) ?></span></div>
  <div class="chiffre"><b><?= h(taille_lisible($chiffres['stockage'])) ?></b><span><?= h(t('adm_stockage')) ?> (<?= h(nombre($chiffres['medias'])) ?>)</span></div>
  <div class="chiffre"><b><?= h(nombre($index)) ?></b><span>index</span></div>
</div>

<h2><?= h(t('adm_export')) ?></h2>
<p>
  <a class="btn" href="<?= h(lien('/admin/export.csv?quoi=membres')) ?>"><?= h(t('adm_membres')) ?></a>
  <a class="btn" href="<?= h(lien('/admin/export.csv?quoi=discussions')) ?>"><?= h(t('forum_discussions')) ?></a>
  <a class="btn" href="<?= h(lien('/admin/export.csv?quoi=signalements')) ?>"><?= h(t('adm_signalements')) ?></a>
  <?php if (peut('admin.configuration')): ?>
  <form method="post" action="<?= h(lien('/admin/reindexer')) ?>" class="en-ligne">
    <?= csrf_champ() ?><button class="btn" type="submit">Reindexer</button>
  </form>
  <?php endif; ?>
</p>

<div class="grille grille--2">
  <div>
    <h2><?= h(t('adm_contenus_vus')) ?></h2>
    <?php if (!$plus_vues): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
    <div class="tableau-boite"><table class="tableau">
      <thead><tr><th><?= h(t('ed_titre')) ?></th><th><?= h(t('forum_vues')) ?></th><th><?= h(t('forum_reponses')) ?></th></tr></thead>
      <tbody><?php foreach ($plus_vues as $v): ?>
        <tr><td><a href="<?= h(lien('/d/' . $v['slug'])) ?>"><?= h($v['titre']) ?></a></td>
            <td><?= h(nombre((int) $v['nb_vues'])) ?></td><td><?= h(nombre((int) $v['nb_reponses'])) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div>
    <h2><?= h(t('adm_recherches_vides')) ?></h2>
    <?php if (!$vides): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
    <div class="tableau-boite"><table class="tableau">
      <thead><tr><th><?= h(t('nav_recherche')) ?></th><th>n</th></tr></thead>
      <tbody><?php foreach ($vides as $v): ?>
        <tr><td><?= h((string) $v['requete']) ?></td><td><?= h(nombre((int) $v['compte'])) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<h2><?= h(t('adm_audit')) ?></h2>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th><?= h(t('rech_tri_date')) ?></th><th><?= h(t('adm_membres')) ?></th><th><?= h(t('mod_action')) ?></th><th>objet</th></tr></thead>
  <tbody><?php foreach ($audit as $a): ?>
    <tr><td><?= h(date_lisible((string) $a['cree_le'])) ?></td>
        <td><?= h((string) ($a['identifiant'] ?? '—')) ?></td>
        <td><span class="etiq"><?= h((string) $a['action']) ?></span></td>
        <td><?= h((string) $a['objet']) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
