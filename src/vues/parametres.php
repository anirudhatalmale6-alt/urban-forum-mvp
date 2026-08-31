<h1><?= h(t('nav_parametres')) ?></h1>
<?php if (!empty($message)): ?>
  <p class="avis avis--<?= h($message['type']) ?>"><?= h($message['texte']) ?></p>
<?php endif; ?>
<form class="form" method="post" action="<?= h(lien('/parametres')) ?>">
  <?= csrf_champ() ?>
  <div class="champ"><label for="nom_public"><?= h(t('cpt_identifiant')) ?></label>
    <input id="nom_public" name="nom_public" type="text" maxlength="120" value="<?= h((string) $u['nom_public']) ?>"></div>
  <div class="champ"><label for="bio"><?= h(t('cpt_bio')) ?></label>
    <textarea id="bio" name="bio" maxlength="2000" class="ta-court"><?= h((string) $u['bio']) ?></textarea></div>
  <div class="champ"><label for="loc"><?= h(t('cpt_localisation')) ?></label>
    <input id="loc" name="localisation" type="text" maxlength="120" value="<?= h((string) $u['localisation']) ?>"></div>
  <div class="champ"><label for="lien"><?= h(t('cpt_lien')) ?></label>
    <input id="lien" name="lien" type="url" maxlength="255" value="<?= h((string) $u['lien']) ?>"></div>
  <div class="champ"><label for="langue"><?= h(t('cpt_langue')) ?></label>
    <select id="langue" name="langue">
      <?php foreach (langues_dispo() as $l): ?>
        <option value="<?= h($l) ?>"<?= $l === $u['langue'] ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="champ">
    <label><input type="checkbox" name="profil_public" value="1"<?= (int) $u['profil_public'] ? ' checked' : '' ?>>
      <?= h(t('cpt_profil_public')) ?></label>
  </div>

  <h2><?= h(t('notif_prefs')) ?></h2>
  <div class="tableau-boite">
  <table class="tableau">
    <thead><tr><th></th><th><?= h(t('notif_canal_app')) ?></th><th><?= h(t('notif_canal_email')) ?></th></tr></thead>
    <tbody>
    <?php foreach (['reponse', 'mention', 'abonnement', 'moderation'] as $type): ?>
      <tr>
        <td><?= h(t('notif_' . $type, ['n' => '…'])) ?></td>
        <?php foreach (['app', 'email'] as $canal): ?>
        <td><input type="checkbox" name="notif[<?= h($type) ?>][<?= h($canal) ?>]" value="1"
             aria-label="<?= h($type . ' ' . $canal) ?>"
             <?= !empty($prefs[$type][$canal]) ? ' checked' : '' ?>></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (!cfg('mail_expediteur')): ?>
    <p class="avis avis--attention"><?= h(t('notif_email_desactive')) ?></p>
  <?php endif; ?>
  <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
</form>
