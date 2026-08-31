<h1><?= h(t('sig_titre')) ?></h1>
<div class="carte">
  <p class="carte__meta"><?= h($apercu['auteur']) ?> — <?= h($apercu['titre']) ?></p>
  <?php if ($apercu['extrait']): ?><p><?= h($apercu['extrait']) ?></p><?php endif; ?>
</div>
<form class="form esp-haut-l" method="post" action="<?= h(lien('/signaler')) ?>">
  <?= csrf_champ() ?>
  <input type="hidden" name="objet_type" value="<?= h($type) ?>">
  <input type="hidden" name="objet_id" value="<?= (int) $id ?>">
  <div class="champ">
    <label for="motif"><?= h(t('mod_motif')) ?></label>
    <select id="motif" name="motif" required>
      <?php foreach (MOTIFS_SIGNALEMENT as $mo): ?>
        <option value="<?= h($mo) ?>"><?= h(t('sig_motif_' . $mo)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="champ">
    <label for="commentaire"><?= h(t('sig_commentaire')) ?></label>
    <textarea id="commentaire" name="commentaire" maxlength="2000" class="ta-court"></textarea>
  </div>
  <button class="btn btn--plein" type="submit"><?= h(t('envoyer')) ?></button>
  <a class="btn" href="<?= h(lien($apercu['url'] ?: '/')) ?>"><?= h(t('annuler')) ?></a>
</form>
