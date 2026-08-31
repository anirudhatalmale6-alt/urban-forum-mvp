<h1><?= h(t('nav_aide')) ?></h1>
<h2><?= h(t('ed_aide')) ?></h2>
<div class="tableau-boite">
<table class="tableau">
  <thead><tr><th><?= h(t('ed_corps')) ?></th><th><?= h(t('disc_previsualiser')) ?></th></tr></thead>
  <tbody>
  <?php foreach ([
      '**' . t('ed_gras') . '**',
      '*' . t('ed_italique') . '*',
      '`code`',
      '> ' . t('ed_citation'),
      '- ' . t('ed_liste'),
      '[' . t('ed_lien') . '](https://example.org)',
      '@' . t('cpt_identifiant'),
      'video:https://www.youtube.com/watch?v=…',
  ] as $ex): ?>
    <tr><td><code><?= h($ex) ?></code></td><td class="msg__texte"><?= rendre_message($ex) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<h2><?= h(t('sig_titre')) ?></h2>
<p><?= h(t('sig_envoye')) ?> <?= h(t('mod_file')) ?> : <?= h(implode(' → ', [t('mod_nouveau'), t('mod_en_revue'), t('mod_actionne'), t('mod_classe')])) ?>.</p>
<h2><?= h(t('notif_prefs')) ?></h2>
<p><?= h(t('notif_email_desactive')) ?></p>
