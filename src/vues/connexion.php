<h1><?= h(t('nav_connexion')) ?></h1>
<?php if (!empty($erreurs['global'])): ?><p class="avis avis--erreur"><?= h($erreurs['global']) ?></p><?php endif; ?>
<form class="form" method="post" action="<?= h(lien('/connexion')) ?>">
  <?= csrf_champ() ?>
  <input type="hidden" name="suite" value="<?= h($_GET['suite'] ?? '/') ?>">
  <div class="champ">
    <label for="identifiant"><?= h(t('cpt_identifiant')) ?></label>
    <input id="identifiant" name="identifiant" type="text" required
           value="<?= h($vals['identifiant'] ?? '') ?>" autocomplete="username">
  </div>
  <div class="champ">
    <label for="mdp"><?= h(t('cpt_mot_de_passe')) ?></label>
    <input id="mdp" name="mot_de_passe" type="password" required autocomplete="current-password">
  </div>
  <button class="btn btn--plein" type="submit"><?= h(t('cpt_se_connecter')) ?></button>
  <p><?= h(t('cpt_pas_compte')) ?> <a href="<?= h(lien('/inscription')) ?>"><?= h(t('cpt_creer_compte')) ?></a></p>
</form>
