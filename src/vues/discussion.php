<?php
/** Une discussion : messages, citations, reactions, moderation. */
$u = utilisateur();
$peut_repondre = $u && peut('forum.publier') && ((int) $d['verrouillee'] === 0 || peut('moderation.contenu'));
?>

<?= fil([
    [t('geo_monde'), '/'],
    [t('nav_forums'), '/forums'],
    [champ_langue($f, 'titre'), '/f/' . $f['slug']],
    [$d['titre'], null],
]) ?>

<h1>
  <?php if ((int) $d['epinglee']): ?><span class="etiq etiq--epingle"><?= h(t('forum_epinglee')) ?></span><?php endif; ?>
  <?php if ((int) $d['verrouillee']): ?><span class="etiq etiq--verrou"><?= h(t('forum_verrouillee')) ?></span><?php endif; ?>
  <?= h($d['titre']) ?>
</h1>
<p class="lede">
  <?= h(nombre((int) $d['nb_reponses'])) ?> <?= h(mb_strtolower(t('forum_reponses'))) ?>
  · <?= h(nombre((int) $d['nb_vues'])) ?> <?= h(mb_strtolower(t('forum_vues'))) ?>
  · <?= h(nombre((int) $d['nb_participants'])) ?> <?= h(mb_strtolower(t('forum_participants'))) ?>
</p>

<?php if ($u): ?>
<p>
  <form method="post" action="<?= h(lien('/abonnement')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="objet_type" value="discussion">
    <input type="hidden" name="objet_id" value="<?= (int) $d['id'] ?>">
    <button class="btn btn--petit" type="submit"><?= h($abonne ? t('disc_desabonner') : t('disc_abonner')) ?></button>
  </form>
  <form method="post" action="<?= h(lien('/signet')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="discussion" value="<?= (int) $d['id'] ?>">
    <button class="btn btn--petit" type="submit"><?= h($en_signet ? t('disc_signet_retirer') : t('disc_signet_ajouter')) ?></button>
  </form>
</p>
<?php endif; ?>

<?php if ((int) $d['verrouillee'] === 1): ?>
  <p class="avis avis--attention"><?= h(t('forum_verrouillee_avis')) ?></p>
<?php endif; ?>

<?php foreach ($messages as $m):
    $masque = (int) $m['masque'] === 1;
    $visible = !$masque || peut('moderation.contenu');
    $pseudo = (string) ($m['identifiant'] ?? '—'); ?>
<article class="msg<?= $masque ? ' msg--masque' : '' ?>" id="m<?= (int) $m['id'] ?>">
  <div class="msg__auteur">
    <?= avatar($pseudo, 52) ?>
    <div><a href="<?= h(lien('/u/' . rawurlencode($pseudo))) ?>"><b><?= h($pseudo) ?></b></a></div>
    <?php if (!empty($m['role_cle'])): ?><div><?= badge_role((string) $m['role_cle']) ?></div><?php endif; ?>
    <div><?= h(t('cpt_messages_publies')) ?> : <?= h(nombre((int) $m['nb_messages'])) ?></div>
    <div><?= h(t('cpt_inscrit_le')) ?> <?= h(date_lisible((string) $m['inscrit_le'])) ?></div>
  </div>

  <div class="msg__corps">
    <div class="msg__entete">
      <span>
        <a href="<?= h(lien('/m/' . (int) $m['id'])) ?>" title="<?= h(t('disc_permalien')) ?>">
          <?= h(t('disc_position', ['n' => (int) $m['position']])) ?>
        </a>
        · <time datetime="<?= h(str_replace(' ', 'T', (string) $m['cree_le'])) ?>Z"><?= h(date_lisible((string) $m['cree_le'])) ?></time>
      </span>
      <?php if ((int) $m['nb_editions'] > 0): ?>
      <span>
        <?= h(t('disc_modifie_le')) ?> <?= h(date_lisible((string) $m['modifie_le'])) ?>
        · <a href="<?= h(lien('/historique/' . (int) $m['id'])) ?>"><?= h(t('disc_historique')) ?></a>
      </span>
      <?php endif; ?>
    </div>

    <?php if (!$visible): ?>
      <p><?= h(t('disc_message_masque')) ?></p>
    <?php else: ?>
      <?php if ($masque): ?><p class="avis avis--attention"><?= h(t('disc_message_masque')) ?></p><?php endif; ?>
      <div class="msg__texte" id="corps<?= (int) $m['id'] ?>"
           data-brut="<?= h(extrait((string) $m['corps'], 1200)) ?>"><?= $m['rendu'] ?></div>
    <?php endif; ?>

    <?php $r = $reactions[(int) $m['id']] ?? []; ?>
    <div class="msg__actions">
      <?php if ($u && peut('forum.reagir')): ?>
        <?php foreach (['utile', 'accord', 'merci'] as $type): ?>
        <form method="post" action="<?= h(lien('/reagir')) ?>" class="en-ligne">
          <?= csrf_champ() ?>
          <input type="hidden" name="message" value="<?= (int) $m['id'] ?>">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <button class="reaction" type="submit"><?= h($type) ?> <?= isset($r[$type]) ? '· ' . h(nombre($r[$type])) : '' ?></button>
        </form>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($r as $type => $n): ?>
          <span class="reaction"><?= h($type) ?> · <?= h(nombre($n)) ?></span>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($peut_repondre): ?>
        <button class="btn btn--petit" type="button"
                data-citer="corps<?= (int) $m['id'] ?>"
                data-auteur="<?= h($pseudo) ?>"
                data-message="<?= (int) $m['id'] ?>"><?= h(t('disc_citer')) ?></button>
        <button class="btn btn--petit" type="button"
                data-mentionner="<?= h($pseudo) ?>">@<?= h($pseudo) ?></button>
      <?php endif; ?>

      <?php if ($u && ((int) $m['auteur_id'] === (int) $u['id'] || peut('moderation.contenu'))): ?>
        <a class="btn btn--petit" href="<?= h(lien('/modifier/' . (int) $m['id'])) ?>"><?= h(t('disc_modifier')) ?></a>
      <?php endif; ?>

      <?php if ($u && peut('forum.signaler')): ?>
        <a class="btn btn--petit" href="<?= h(lien('/signaler?type=message&id=' . (int) $m['id'])) ?>"><?= h(t('disc_signaler')) ?></a>
      <?php endif; ?>

      <?php if (peut('moderation.contenu')): ?>
        <form method="post" action="<?= h(lien('/moderation/action')) ?>" class="en-ligne">
          <?= csrf_champ() ?>
          <input type="hidden" name="objet_type" value="message">
          <input type="hidden" name="objet_id" value="<?= (int) $m['id'] ?>">
          <input type="hidden" name="action" value="<?= $masque ? 'demasquer' : 'masquer' ?>">
          <input type="hidden" name="retour" value="/d/<?= h($d['slug']) ?>">
          <button class="btn btn--petit btn--danger" type="submit"><?= h($masque ? t('mod_demasquer') : t('mod_masquer')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</article>
<?php endforeach; ?>

<?= pagination((int) $page, (int) $total, (int) $pp, '/d/' . $d['slug']) ?>

<?php if (peut('moderation.contenu')): ?>
<details class="carte esp-haut-l">
  <summary><b><?= h(t('mod_titre')) ?></b></summary>
  <div class="grille grille--2 esp-haut">
    <form method="post" action="<?= h(lien('/moderation/action')) ?>">
      <?= csrf_champ() ?>
      <input type="hidden" name="objet_type" value="discussion">
      <input type="hidden" name="objet_id" value="<?= (int) $d['id'] ?>">
      <input type="hidden" name="retour" value="/d/<?= h($d['slug']) ?>">
      <div class="champ">
        <label for="mod-action"><?= h(t('mod_action')) ?></label>
        <select id="mod-action" name="action">
          <option value="<?= (int) $d['epinglee'] ? 'desepingler' : 'epingler' ?>"><?= h(t('mod_epingler')) ?></option>
          <option value="<?= (int) $d['verrouillee'] ? 'deverrouiller' : 'verrouiller' ?>"><?= h(t('mod_verrouiller')) ?></option>
          <option value="<?= (int) $d['masquee'] ? 'demasquer' : 'masquer' ?>"><?= h(t('mod_masquer')) ?></option>
        </select>
      </div>
      <div class="champ">
        <label for="mod-motif"><?= h(t('mod_motif')) ?></label>
        <input id="mod-motif" type="text" name="motif" maxlength="200">
      </div>
      <button class="btn" type="submit"><?= h(t('mod_action')) ?></button>
    </form>

    <form method="post" action="<?= h(lien('/moderation/action')) ?>">
      <?= csrf_champ() ?>
      <input type="hidden" name="objet_type" value="discussion">
      <input type="hidden" name="objet_id" value="<?= (int) $d['id'] ?>">
      <input type="hidden" name="action" value="deplacer">
      <input type="hidden" name="retour" value="/d/<?= h($d['slug']) ?>">
      <div class="champ">
        <label for="mod-forum"><?= h(t('mod_deplacer')) ?></label>
        <select id="mod-forum" name="forum_id">
          <?php foreach (qtous('SELECT * FROM forums ORDER BY rang, id') as $ff): ?>
            <option value="<?= (int) $ff['id'] ?>"<?= (int) $ff['id'] === (int) $d['forum_id'] ? ' selected' : '' ?>>
              <?= h(champ_langue($ff, 'titre')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit"><?= h(t('mod_deplacer')) ?></button>
    </form>
  </div>
</details>
<?php endif; ?>

<?php if ($peut_repondre): ?>
<h2 id="repondre"><?= h(t('disc_repondre')) ?></h2>
<form class="form form--large" method="post" action="<?= h(lien('/repondre')) ?>">
  <?= csrf_champ() ?>
  <input type="hidden" name="discussion" value="<?= (int) $d['id'] ?>">
  <?php include __DIR__ . '/_editeur.php'; ?>
  <button class="btn btn--plein" type="submit"><?= h(t('disc_repondre')) ?></button>
  <span data-etat-brouillon="<?= h(t('disc_brouillon_enregistre')) ?>" class="carte__meta"></span>
</form>
<?php elseif (!$u): ?>
<p class="avis avis--info">
  <a href="<?= h(lien('/connexion')) ?>"><?= h(t('refuse_connexion')) ?></a>
</p>
<?php endif; ?>
