<?php $moi = utilisateur(); ?>
<div class="carte carte--profil">
  <?= avatar((string) $p['identifiant'], 72) ?>
  <div>
    <h1 class="sans-marge"><?= h((string) ($p['nom_public'] ?: $p['identifiant'])) ?></h1>
    <p class="carte__meta">@<?= h((string) $p['identifiant']) ?>
      <?php if (!empty($p['role_cle'])): ?> <?= badge_role((string) $p['role_cle']) ?><?php endif; ?>
      <?php if ((int) $p['banni']): ?><span class="etiq etiq--haute"><?= h(t('mod_bannir')) ?></span><?php endif; ?>
    </p>
    <p class="carte__meta">
      <?= h(t('cpt_inscrit_le')) ?> <?= h(date_lisible((string) $p['cree_le'])) ?>
      · <?= h(t('cpt_messages_publies')) ?> : <?= h(nombre((int) $p['nb_messages'])) ?>
    </p>
  </div>
</div>

<?php if ($prive): ?>
  <p class="avis avis--info"><?= h(t('cpt_profil_public')) ?> : <?= h(t('non')) ?></p>
<?php else: ?>
  <div class="grille grille--2 esp-bloc">
    <div class="carte"><h3><?= h(t('cpt_bio')) ?></h3>
      <p><?= $p['bio'] ? nl2br(h((string) $p['bio'])) : valeur('', 'profil-bio') ?></p></div>
    <div class="carte"><h3><?= h(t('cpt_localisation')) ?></h3>
      <p><?= valeur($p['localisation'], 'profil-localisation') ?></p>
      <h3><?= h(t('cpt_lien')) ?></h3>
      <p><?php if ($p['lien']): ?><a rel="nofollow noopener ugc" target="_blank" href="<?= h((string) $p['lien']) ?>"><?= h((string) $p['lien']) ?></a><?php else: ?><?= valeur('', 'profil-lien') ?><?php endif; ?></p>
    </div>
  </div>

  <h2><?= h(t('cpt_messages_publies')) ?></h2>
  <?php if (!$messages): ?><p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p><?php else: ?>
  <div class="liste">
    <?php foreach ($messages as $m): ?>
    <div class="liste__ligne">
      <div>
        <a class="liste__titre" href="<?= h(lien('/m/' . (int) $m['id'])) ?>"><?= h((string) $m['titre']) ?></a>
        <div class="liste__sous"><?= h(extrait((string) $m['corps'])) ?></div>
      </div>
      <div class="liste__dernier"><?= h(date_lisible((string) $m['cree_le'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($moi && (int) $moi['id'] !== (int) $p['id']): ?>
<p class="esp-haut-l">
  <form method="post" action="<?= h(lien('/bloquer')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="utilisateur" value="<?= (int) $p['id'] ?>">
    <button class="btn" type="submit"><?= h($bloque ? t('cpt_debloquer') : t('cpt_bloquer')) ?></button>
  </form>
  <?php if (peut('forum.signaler')): ?>
    <a class="btn" href="<?= h(lien('/signaler?type=utilisateur&id=' . (int) $p['id'])) ?>"><?= h(t('disc_signaler')) ?></a>
  <?php endif; ?>
  <?php if (peut('moderation.sanction')): ?>
  <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="objet_type" value="utilisateur">
    <input type="hidden" name="objet_id" value="<?= (int) $p['id'] ?>">
    <input type="hidden" name="action" value="suspendre">
    <input type="hidden" name="jours" value="7">
    <input type="hidden" name="retour" value="/u/<?= h(rawurlencode((string) $p['identifiant'])) ?>">
    <button class="btn btn--danger" type="submit"><?= h(t('mod_suspendre')) ?> (7 j)</button>
  </form>
  <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="objet_type" value="utilisateur">
    <input type="hidden" name="objet_id" value="<?= (int) $p['id'] ?>">
    <input type="hidden" name="action" value="<?= (int) $p['banni'] ? 'debannir' : 'bannir' ?>">
    <input type="hidden" name="retour" value="/u/<?= h(rawurlencode((string) $p['identifiant'])) ?>">
    <button class="btn btn--danger" type="submit"><?= h(t('mod_bannir')) ?></button>
  </form>
  <?php endif; ?>
</p>
<?php endif; ?>
