<h1><?= h(t('notif_titre')) ?></h1>
<?php if (!cfg('mail_expediteur')): ?>
  <p class="avis avis--attention"><?= h(t('notif_email_desactive')) ?></p>
<?php endif; ?>
<p>
  <form method="post" action="<?= h(lien('/notifications/lues')) ?>" class="en-ligne">
    <?= csrf_champ() ?><button class="btn" type="submit"><?= h(t('notif_tout_lu')) ?></button>
  </form>
  <a class="btn" href="<?= h(lien('/parametres')) ?>"><?= h(t('notif_prefs')) ?></a>
</p>
<?php if (!$notifs): ?><p class="vide-etat"><?= h(t('notif_aucune')) ?></p><?php else: ?>
<div class="liste">
  <?php foreach ($notifs as $n): ?>
  <div class="liste__ligne">
    <div>
      <?php if (!(int) $n['lue']): ?><span class="etiq etiq--epingle"><?= h(t('mod_nouveau')) ?></span><?php endif; ?>
      <a class="liste__titre" href="<?= h(lien((string) ($n['lien'] ?: '/'))) ?>"><?= h(texte_notification($n)) ?></a>
      <div class="liste__sous"><?= h(il_y_a((string) $n['cree_le'])) ?></div>
    </div>
    <div class="liste__nb"><?= h($n['type']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
