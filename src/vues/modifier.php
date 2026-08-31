<h1><?= h(t('disc_modifier')) ?></h1>
<form class="form form--large" method="post" action="<?= h(lien('/modifier/' . (int) $m['id'])) ?>">
  <?= csrf_champ() ?>
  <?php $valeur_corps = (string) $m['corps']; include __DIR__ . '/_editeur.php'; ?>
  <?php if (isset($erreurs['corps'])): ?><p class="erreur"><?= h($erreurs['corps']) ?></p><?php endif; ?>
  <div class="champ">
    <label for="motif"><?= h(t('disc_motif_edition')) ?></label>
    <input id="motif" name="motif" type="text" maxlength="255">
    <small><?= h(t('facultatif')) ?></small>
  </div>
  <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  <a class="btn" href="<?= h(lien('/d/' . $m['slug'] . '#m' . (int) $m['id'])) ?>"><?= h(t('annuler')) ?></a>
</form>
