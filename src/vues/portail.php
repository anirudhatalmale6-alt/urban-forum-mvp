<?php
/**
 * Le portail : ce que voit quelqu'un qui arrive sans compte.
 *
 * Chaque bloc peut etre vide, et chaque bloc vide LE DIT. Un portail neuf
 * dont les rubriques sont muettes ressemble a un portail casse ; le meme
 * portail qui ecrit « aucun article pour l'instant » ressemble a ce qu'il
 * est : un site qui vient d'ouvrir.
 *
 * $grande est TOUJOURS assignee avant chaque inclusion de la carte : le
 * partiel lit la variable du contexte appelant, donc une valeur laissee de
 * l'inclusion precedente rendrait toutes les cartes suivantes en grand.
 */
$vide_partout = !$une && !$recents;
?>

<div class="hero hero--portail">
  <h1><?= h(cfg('nom_site')) ?><?php if (cfg('baseline')): ?> — <?= h(cfg('baseline')) ?><?php endif; ?></h1>
  <p class="lede"><?= h(t('portail_intro')) ?></p>
  <p class="hero__actions">
    <a class="btn btn--plein" href="<?= h(lien('/actualites')) ?>"><?= h(t('portail_actualites')) ?></a>
    <a class="btn" href="<?= h(lien('/forums')) ?>"><?= h(t('nav_forums')) ?></a>
    <a class="btn" href="<?= h(lien('/villes')) ?>"><?= h(t('nav_villes')) ?></a>
    <?php if (!connecte()): ?>
      <a class="btn" href="<?= h(lien('/inscription')) ?>"><?= h(t('nav_inscription')) ?></a>
    <?php endif; ?>
  </p>
</div>

<?php if ($vide_partout): ?>
  <section class="encart">
    <h2><?= h(t('portail_aucun_article')) ?></h2>
    <p><?= h(t('portail_aucun_article_aide')) ?></p>
    <?php if (peut('portail.rediger')): ?>
      <p><a class="btn btn--plein" href="<?= h(lien('/admin/articles/nouveau')) ?>"><?= h(t('portail_ecrire')) ?></a></p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($une): ?>
<section class="bloc">
  <h2><?= h(t('portail_une')) ?></h2>
  <div class="une">
    <?php $premier = true; foreach ($une as $a):
        $grande = $premier; $premier = false;
        include __DIR__ . '/_carte_article.php';
    endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($recents): ?>
<section class="bloc">
  <div class="bloc__tete">
    <h2><?= h(t('portail_derniers')) ?></h2>
    <a href="<?= h(lien('/actualites')) ?>"><?= h(t('portail_tout_voir')) ?></a>
  </div>
  <div class="grille-art">
    <?php foreach ($recents as $a): $grande = false; include __DIR__ . '/_carte_article.php'; endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($par_rubrique): ?>
<section class="bloc">
  <h2><?= h(t('portail_rubriques')) ?></h2>
  <?php foreach ($par_rubrique as $b): ?>
    <div class="rub-bloc">
      <div class="bloc__tete">
        <h3><a href="<?= h(lien('/r/' . $b['rubrique']['slug'])) ?>"><?= h(champ_langue($b['rubrique'])) ?></a></h3>
        <span class="bloc__nb"><?= h(tn('portail_n_articles', (int) $b['total'])) ?></span>
      </div>
      <?php if (champ_langue($b['rubrique'], 'description') !== ''): ?>
        <p class="rub-bloc__desc"><?= h(champ_langue($b['rubrique'], 'description')) ?></p>
      <?php endif; ?>
      <?php if (!$b['articles']): ?>
        <p class="vide-etat"><?= h(t('portail_rubrique_vide')) ?></p>
      <?php else: ?>
        <ul class="liste-liens">
          <?php foreach ($b['articles'] as $x): ?>
            <li>
              <a href="<?= h(lien('/a/' . $x['slug'])) ?>"><?= h($x['titre']) ?></a>
              <time datetime="<?= h(str_replace(' ', 'T', (string) $x['publie_le'])) ?>Z"><?= h(il_y_a((string) $x['publie_le'])) ?></time>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="bloc">
  <div class="bloc__tete">
    <h2><?= h(t('portail_forum_recent')) ?></h2>
    <a href="<?= h(lien('/communaute')) ?>"><?= h(t('portail_tout_voir')) ?></a>
  </div>
  <?php if (!$discussions): ?>
    <p class="vide-etat"><?= h(t('forum_aucune_discussion')) ?></p>
  <?php else: ?>
  <div class="liste">
    <?php foreach ($discussions as $d): ?>
    <div class="liste__ligne">
      <div>
        <?php if ((int) $d['demo']): ?><span class="etiq etiq--demo"><?= h(t('demo_etiquette')) ?></span><?php endif; ?>
        <a class="liste__titre" href="<?= h(lien('/d/' . $d['slug'])) ?>"><?= h($d['titre']) ?></a>
        <div class="liste__sous">
          <a href="<?= h(lien('/f/' . $d['forum_slug'])) ?>"><?= h(champ_langue($d, 'titre')) ?></a>
          · <?= h(t('forum_par')) ?> <?= h((string) $d['identifiant']) ?>
          · <time datetime="<?= h(str_replace(' ', 'T', (string) $d['cree_le'])) ?>Z"><?= h(il_y_a((string) $d['dernier_message_le'])) ?></time>
        </div>
      </div>
      <div class="liste__nb"><b><?= h(nombre((int) $d['nb_reponses'])) ?></b><?= h(t('forum_reponses')) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="bloc">
  <div class="bloc__tete">
    <h2><?= h(t('portail_villes')) ?></h2>
    <a href="<?= h(lien('/villes')) ?>"><?= h(t('portail_tout_voir')) ?></a>
  </div>
  <div class="grille grille--4">
    <?php foreach ($villes as $v): ?>
    <a class="carte" href="<?= h(lien('/v/' . $v['slug'])) ?>">
      <h3><?= h(champ_langue($v)) ?></h3>
      <p class="carte__meta"><?= h(champ_langue($v, 'pays')) ?></p>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="bande">
  <h2><?= h(t('portail_chiffres')) ?></h2>
  <div class="chiffres">
    <div class="chiffre"><b><?= h(nombre($stats['articles'])) ?></b><span><?= h(t('portail_articles')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['discussions'])) ?></b><span><?= h(t('forum_discussions')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['messages'])) ?></b><span><?= h(t('forum_messages')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['membres'])) ?></b><span><?= h(t('adm_membres')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['pays'])) ?></b><span><?= h(t('geo_pays')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['villes'])) ?></b><span><?= h(t('geo_ville')) ?></span></div>
    <div class="chiffre"><b><?= h(nombre($stats['projets'])) ?></b><span><?= h(t('nav_projets')) ?></span></div>
  </div>
  <p><?= h(t('proj_phase2')) ?></p>
</section>
