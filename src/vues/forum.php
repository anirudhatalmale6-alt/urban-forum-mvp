<?php /** Un forum : sous-forums puis discussions paginees. */ ?>
<?= fil([[t('geo_monde'), '/'], [t('nav_forums'), '/forums'], [champ_langue($f, 'titre'), null]]) ?>
<h1><?= h(champ_langue($f, 'titre')) ?></h1>
<?php if (champ_langue($f, 'description')): ?><p class="lede"><?= h(champ_langue($f, 'description')) ?></p><?php endif; ?>

<?php if (!empty($f['regles'])): ?>
<details class="carte esp-bas-l">
  <summary><b><?= h(t('forum_regles')) ?></b></summary>
  <div class="msg__texte"><?= rendre_message((string) $f['regles']) ?></div>
</details>
<?php endif; ?>

<p>
  <?php if (connecte() && peut('forum.publier') && (int) $f['ferme'] === 0): ?>
    <a class="btn btn--plein" href="<?= h(lien('/nouvelle-discussion?forum=' . $f['slug'])) ?>"><?= h(t('forum_nouvelle_discussion')) ?></a>
  <?php endif; ?>
  <?php if (connecte()): ?>
  <form method="post" action="<?= h(lien('/abonnement')) ?>" class="en-ligne">
    <?= csrf_champ() ?>
    <input type="hidden" name="objet_type" value="forum">
    <input type="hidden" name="objet_id" value="<?= (int) $f['id'] ?>">
    <button class="btn" type="submit"><?= h(t('disc_abonner')) ?></button>
  </form>
  <?php endif; ?>
</p>

<?php if ($enfants): ?>
<h2><?= h(t('forum_sous_forums')) ?></h2>
<div class="liste">
  <?php foreach ($enfants as $e): ?>
  <div class="liste__ligne">
    <div><a class="liste__titre" href="<?= h(lien('/f/' . $e['slug'])) ?>"><?= h(champ_langue($e, 'titre')) ?></a></div>
    <div class="liste__nb"><b><?= h(nombre((int) $e['nb_discussions'])) ?></b><?= h(t('forum_discussions')) ?></div>
    <div class="liste__nb"><b><?= h(nombre((int) $e['nb_messages'])) ?></b><?= h(t('forum_messages')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2><?= h(t('forum_discussions')) ?></h2>
<?php if (!$discussions): ?>
  <p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p>
<?php else: ?>
<div class="liste">
  <?php foreach ($discussions as $d): ?>
  <div class="liste__ligne">
    <div>
      <?php if ((int) $d['epinglee']): ?><span class="etiq etiq--epingle"><?= h(t('forum_epinglee')) ?></span><?php endif; ?>
      <?php if ((int) $d['verrouillee']): ?><span class="etiq etiq--verrou"><?= h(t('forum_verrouillee')) ?></span><?php endif; ?>
      <a class="liste__titre" href="<?= h(lien('/d/' . $d['slug'])) ?>"><?= h($d['titre']) ?></a>
      <div class="liste__sous"><?= h(t('forum_par')) ?> <?= h((string) $d['identifiant']) ?>
        · <time datetime="<?= h(str_replace(' ', 'T', (string) $d['cree_le'])) ?>Z"><?= h(date_lisible((string) $d['cree_le'])) ?></time></div>
    </div>
    <div class="liste__nb"><b><?= h(nombre((int) $d['nb_reponses'])) ?></b><?= h(t('forum_reponses')) ?></div>
    <div class="liste__dernier">
      <?= h(t('forum_dernier_message')) ?><br>
      <?= h((string) ($d['dernier_auteur'] ?? '—')) ?> · <?= h(il_y_a((string) $d['dernier_message_le'])) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?= pagination((int) $page, (int) $total, (int) $pp, '/f/' . $f['slug']) ?>
