<h1><?= h(t('mod_titre')) ?></h1>
<p class="lede"><a href="<?= h(lien('/moderation/journal')) ?>"><?= h(t('mod_journal')) ?></a></p>

<p>
  <a class="btn<?= $etat === '' ? ' btn--plein' : '' ?>" href="<?= h(lien('/moderation')) ?>"><?= h(t('mod_file')) ?></a>
  <?php foreach (ETATS_SIGNALEMENT as $e): ?>
    <a class="btn<?= $etat === $e ? ' btn--plein' : '' ?>" href="<?= h(lien('/moderation?etat=' . $e)) ?>">
      <?= h(t('mod_' . $e)) ?> · <?= h(nombre($compte[$e])) ?></a>
  <?php endforeach; ?>
</p>

<?php if (!$file): ?>
  <p class="vide-etat"><?= h(t('mod_aucune')) ?></p>
<?php else: ?>
<?php foreach ($file as $s): $a = $s['apercu']; ?>
<div class="carte esp-bas">
  <p class="carte__meta">
    <span class="etiq<?= $s['priorite'] === 'haute' ? ' etiq--haute' : '' ?>"><?= h($s['priorite']) ?></span>
    <span class="etiq"><?= h(t('mod_' . $s['etat'])) ?></span>
    <?= h(t('mod_motif')) ?> : <b><?= h(t('sig_motif_' . $s['motif'])) ?></b>
    · <?= h(t('forum_par')) ?> <?= h((string) $s['signaleur']) ?>
    · <?= h(il_y_a((string) $s['cree_le'])) ?>
    · <?= h($s['objet_type']) ?> #<?= (int) $s['objet_id'] ?>
  </p>
  <h3><?php if ($a['url']): ?><a href="<?= h(lien($a['url'])) ?>"><?= h($a['titre']) ?></a><?php else: ?><?= h($a['titre']) ?><?php endif; ?></h3>
  <?php if ($a['extrait']): ?><p><?= h($a['extrait']) ?></p><?php endif; ?>
  <?php if ($s['commentaire']): ?><p class="carte__meta">« <?= h((string) $s['commentaire']) ?> »</p><?php endif; ?>

  <div class="msg__actions">
    <?php if ($s['etat'] === 'nouveau'): ?>
    <form method="post" action="<?= h(lien('/moderation/revue')) ?>" class="en-ligne">
      <?= csrf_champ() ?><input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <button class="btn btn--petit" type="submit"><?= h(t('mod_prendre')) ?></button>
    </form>
    <?php endif; ?>

    <?php if (in_array($s['objet_type'], ['message', 'discussion'], true)): ?>
    <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
      <?= csrf_champ() ?>
      <input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="objet_type" value="<?= h($s['objet_type']) ?>">
      <input type="hidden" name="objet_id" value="<?= (int) $s['objet_id'] ?>">
      <input type="hidden" name="action" value="<?= !empty($a['masque']) ? 'demasquer' : 'masquer' ?>">
      <input type="hidden" name="motif" value="<?= h((string) $s['motif']) ?>">
      <button class="btn btn--petit btn--danger" type="submit">
        <?= h(!empty($a['masque']) ? t('mod_demasquer') : t('mod_masquer')) ?></button>
    </form>
    <?php endif; ?>

    <?php if ($s['objet_type'] === 'discussion'): ?>
    <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
      <?= csrf_champ() ?>
      <input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="objet_type" value="discussion">
      <input type="hidden" name="objet_id" value="<?= (int) $s['objet_id'] ?>">
      <input type="hidden" name="action" value="verrouiller">
      <button class="btn btn--petit" type="submit"><?= h(t('mod_verrouiller')) ?></button>
    </form>
    <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
      <?= csrf_champ() ?>
      <input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="objet_type" value="discussion">
      <input type="hidden" name="objet_id" value="<?= (int) $s['objet_id'] ?>">
      <input type="hidden" name="action" value="deplacer">
      <select name="forum_id" aria-label="<?= h(t('mod_deplacer')) ?>">
        <?php foreach ($forums as $ff): ?>
          <option value="<?= (int) $ff['id'] ?>"><?= h(champ_langue($ff, 'titre')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--petit" type="submit"><?= h(t('mod_deplacer')) ?></button>
    </form>
    <?php endif; ?>

    <?php if (peut('moderation.sanction')): ?>
    <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
      <?= csrf_champ() ?>
      <input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="objet_type" value="utilisateur">
      <input type="hidden" name="objet_id"
             value="<?= (int) (qval('SELECT id FROM utilisateurs WHERE identifiant = ?', [$a['auteur']]) ?? 0) ?>">
      <input type="hidden" name="action" value="avertir">
      <button class="btn btn--petit" type="submit"><?= h(t('mod_avertir')) ?></button>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
      <?= csrf_champ() ?>
      <input type="hidden" name="signalement" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="objet_type" value="<?= h($s['objet_type']) ?>">
      <input type="hidden" name="objet_id" value="<?= (int) $s['objet_id'] ?>">
      <input type="hidden" name="action" value="classer">
      <button class="btn btn--petit" type="submit"><?= h(t('mod_classe')) ?></button>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
