<h1><?= h(t('nav_inscription')) ?></h1>
<?php if (!empty($erreurs['global'])): ?><p class="avis avis--erreur"><?= h($erreurs['global']) ?></p><?php endif; ?>
<form class="form" method="post" action="<?= h(lien('/inscription')) ?>">
  <?= csrf_champ() ?>
  <div class="champ<?= isset($erreurs['identifiant']) ? ' champ--erreur' : '' ?>">
    <label for="identifiant"><?= h(t('cpt_identifiant')) ?></label>
    <input id="identifiant" name="identifiant" type="text" required minlength="3" maxlength="30"
           value="<?= h($vals['identifiant'] ?? '') ?>" autocomplete="username">
    <?php if (isset($erreurs['identifiant'])): ?><p class="erreur"><?= h($erreurs['identifiant']) ?></p><?php endif; ?>
  </div>
  <div class="champ<?= isset($erreurs['email']) ? ' champ--erreur' : '' ?>">
    <label for="email"><?= h(t('cpt_email')) ?></label>
    <input id="email" name="email" type="email" required value="<?= h($vals['email'] ?? '') ?>" autocomplete="email">
    <?php if (isset($erreurs['email'])): ?><p class="erreur"><?= h($erreurs['email']) ?></p><?php endif; ?>
  </div>
  <div class="champ<?= isset($erreurs['mot_de_passe']) ? ' champ--erreur' : '' ?>">
    <label for="mdp"><?= h(t('cpt_mot_de_passe')) ?></label>
    <input id="mdp" name="mot_de_passe" type="password" required minlength="10" autocomplete="new-password">
    <small><?= h(t('cpt_erreur_mdp_court')) ?></small>
    <?php if (isset($erreurs['mot_de_passe'])): ?><p class="erreur"><?= h($erreurs['mot_de_passe']) ?></p><?php endif; ?>
  </div>
  <div class="champ<?= isset($erreurs['mot_de_passe2']) ? ' champ--erreur' : '' ?>">
    <label for="mdp2"><?= h(t('cpt_mot_de_passe2')) ?></label>
    <input id="mdp2" name="mot_de_passe2" type="password" required autocomplete="new-password">
    <?php if (isset($erreurs['mot_de_passe2'])): ?><p class="erreur"><?= h($erreurs['mot_de_passe2']) ?></p><?php endif; ?>
  </div>
  <div class="hors-ecran" aria-hidden="true">
    <label for="site_web">Site web</label>
    <input id="site_web" name="site_web" type="text" tabindex="-1" autocomplete="off">
  </div>
  <button class="btn btn--plein" type="submit"><?= h(t('cpt_creer_compte')) ?></button>
  <p><?= h(t('cpt_deja_compte')) ?> <a href="<?= h(lien('/connexion')) ?>"><?= h(t('cpt_se_connecter')) ?></a></p>
</form>
