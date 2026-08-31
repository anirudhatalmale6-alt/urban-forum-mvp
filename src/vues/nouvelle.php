<h1><?= h(t('forum_nouvelle_discussion')) ?></h1>
<?php if (!empty($erreurs['global'])): ?><p class="avis avis--erreur"><?= h($erreurs['global']) ?></p><?php endif; ?>
<form class="form form--large" method="post" action="<?= h(lien('/nouvelle-discussion')) ?>">
  <?= csrf_champ() ?>
  <div class="champ<?= isset($erreurs['forum']) ? ' champ--erreur' : '' ?>">
    <label for="forum"><?= h(t('nav_forums')) ?></label>
    <select id="forum" name="forum" required>
      <?php foreach ($forums as $f): ?>
        <option value="<?= h($f['slug']) ?>"<?= $f['slug'] === $forum_slug ? ' selected' : '' ?>>
          <?= h(champ_langue($f, 'titre')) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($erreurs['forum'])): ?><p class="erreur"><?= h($erreurs['forum']) ?></p><?php endif; ?>
  </div>
  <div class="champ<?= isset($erreurs['titre']) ? ' champ--erreur' : '' ?>">
    <label for="titre"><?= h(t('ed_titre')) ?></label>
    <input id="titre" name="titre" type="text" required minlength="5" maxlength="200"
           value="<?= h($vals['titre'] ?? '') ?>">
    <?php if (isset($erreurs['titre'])): ?><p class="erreur"><?= h($erreurs['titre']) ?></p><?php endif; ?>
  </div>
  <?php $valeur_corps = $vals['corps'] ?? ''; include __DIR__ . '/_editeur.php'; ?>
  <?php if (isset($erreurs['corps'])): ?><p class="erreur"><?= h($erreurs['corps']) ?></p><?php endif; ?>
  <button class="btn btn--plein" type="submit"><?= h(t('envoyer')) ?></button>
  <span data-etat-brouillon="<?= h(t('disc_brouillon_enregistre')) ?>" class="carte__meta"></span>
</form>
