<?php
/**
 * Un article.
 *
 * Le corps affiche est $a['rendu'] : du HTML que NOUS avons fabrique a
 * l'ecriture a partir de la syntaxe de l'editeur, jamais du HTML recu. Voir
 * src/balisage.php — le texte est echappe en entier puis on re-injecte un
 * petit nombre de balises. C'est la seule raison pour laquelle il n'y a pas
 * de h() autour de cette ligne, et elle vaut d'etre ecrite ici.
 */
$rub = nom_rubrique_de_article($a);
$programme = $a['statut'] === 'publie' && !empty($a['publie_le'])
             && strtotime($a['publie_le'] . ' UTC') > time();

/* Titre, chapeau et corps portent la langue de l'ARTICLE ; le fil
   d'Ariane, la signature et la date restent dans la langue de LECTURE.
   Melanger les deux dans un meme bloc rtl retourne les dates autour des
   mots latins qu'elles contiennent. */
$attr_langue = ($a['langue'] ?? '') !== '' && $a['langue'] !== langue()
    ? ' lang="' . h($a['langue']) . '" dir="' . ($a['langue'] === 'ar' ? 'rtl' : 'ltr') . '"'
    : '';
?>

<?= fil(array_values(array_filter([
    [cfg('nom_site'), '/'],
    [t('portail_actualites'), '/actualites'],
    $rub !== '' ? [$rub, '/r/' . $a['rubrique_slug']] : null,
    [$a['titre']],
]))) ?>

<?php if (!$publie): ?>
  <div class="avis avis--attention" role="note">
    <?php if ($programme): ?>
      <?= h(t('portail_programme_avis', ['date' => date_lisible((string) $a['publie_le'])])) ?>
    <?php else: ?>
      <?= h(t('portail_brouillon_avis', ['etat' => t('portail_statut_' . $a['statut'])])) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<article class="article">
  <header class="article__tete">
    <p class="article__sur">
      <?php if ($rub !== ''): ?>
        <a class="etiq etiq--rub" href="<?= h(lien('/r/' . $a['rubrique_slug'])) ?>"><?= h($rub) ?></a>
      <?php endif; ?>
      <?php if (!empty($a['demo'])): ?><span class="etiq etiq--demo"><?= h(t('demo_etiquette')) ?></span><?php endif; ?>
      <?php if (($a['langue'] ?? '') !== langue()): ?>
        <span class="etiq etiq--langue" lang="<?= h($a['langue']) ?>"><?= h(t('langue_' . $a['langue'])) ?></span>
      <?php endif; ?>
    </p>
    <h1<?= $attr_langue ?>><?= h($a['titre']) ?></h1>
    <?php if (!empty($a['chapeau'])): ?>
      <p class="article__chapeau"<?= $attr_langue ?>><?= h($a['chapeau']) ?></p>
    <?php endif; ?>
    <p class="article__meta">
      <?php $sig = $a['signature'] ?: ($a['auteur'] ?? ''); ?>
      <?php if ($sig !== ''): ?><?= h(t('portail_par')) ?> <?= h($sig) ?> · <?php endif; ?>
      <?php if (!empty($a['publie_le'])): ?>
        <time datetime="<?= h(str_replace(' ', 'T', (string) $a['publie_le'])) ?>Z"><?= h(date_lisible((string) $a['publie_le'])) ?></time>
      <?php endif; ?>
      <?php if ($publie): ?> · <?= h(tn('portail_n_vues', (int) $a['nb_vues'])) ?><?php endif; ?>
      <?php if (peut('portail.rediger')): ?>
        · <a href="<?= h(lien('/admin/articles/' . (int) $a['id'])) ?>"><?= h(t('disc_modifier')) ?></a>
      <?php endif; ?>
    </p>
  </header>

  <?php if ($media): ?>
    <figure class="article__figure">
      <img src="/media/<?= (int) $media['id'] ?>" alt="<?= h((string) $media['alt']) ?>"
           <?php if ($media['largeur'] && $media['hauteur']): ?>width="<?= (int) $media['largeur'] ?>" height="<?= (int) $media['hauteur'] ?>"<?php endif; ?>
           decoding="async">
      <?php if (!empty($media['alt'])): ?><figcaption><?= h((string) $media['alt']) ?></figcaption><?php endif; ?>
    </figure>
  <?php endif; ?>

  <div class="article__corps"<?= $attr_langue ?>><?= $a['rendu'] ?></div>

  <section class="article__sources">
    <h2><?= h(t('portail_sources')) ?></h2>
    <?php if (!$sources): ?>
      <p class="vide-etat"><?= h(t('portail_sans_source')) ?></p>
      <p class="note"><?= h(t('portail_sans_source_aide')) ?></p>
    <?php else: ?>
      <ol class="sources">
        <?php foreach ($sources as $s): ?>
          <li>
            <a href="<?= h($s['url']) ?>" rel="nofollow noopener" target="_blank"><?= h($s['titre'] ?: $s['url']) ?></a>
            <?php if (!empty($s['editeur'])): ?><span class="sources__editeur"><?= h($s['editeur']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

  <?php if ($trad): ?>
    <p class="article__trad"><?= h(t('portail_aussi_en')) ?>
      <?php foreach ($trad as $x): ?>
        <a href="<?= h(lien('/a/' . $x['slug'])) ?>" lang="<?= h($x['langue']) ?>" hreflang="<?= h($x['langue']) ?>"><?= h(t('langue_' . $x['langue'])) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>
</article>

<section class="bloc article__discussion">
  <h2><?= h(t('portail_discuter_titre')) ?></h2>
  <?php if ($discussion): ?>
    <p><?= h(t('portail_discussion_ouverte', ['n' => nombre((int) $discussion['nb_reponses'])])) ?></p>
    <p><a class="btn btn--plein" href="<?= h(lien('/d/' . $discussion['slug'])) ?>"><?= h(t('portail_suivre_discussion')) ?></a></p>
  <?php elseif (peut('forum.publier')): ?>
    <p><?= h(t('portail_discuter_aide')) ?></p>
    <form method="post" action="<?= h(lien('/portail/discussion')) ?>">
      <?= csrf_champ() ?>
      <input type="hidden" name="article" value="<?= (int) $a['id'] ?>">
      <button class="btn btn--plein" type="submit"><?= h(t('portail_ouvrir_discussion')) ?></button>
    </form>
  <?php else: ?>
    <p><?= h(t('portail_discuter_connexion')) ?></p>
    <p><a class="btn" href="<?= h(lien('/connexion')) ?>"><?= h(t('nav_connexion')) ?></a>
       <a class="btn btn--plein" href="<?= h(lien('/inscription')) ?>"><?= h(t('nav_inscription')) ?></a></p>
  <?php endif; ?>
</section>

<?php if ($lies): ?>
<section class="bloc">
  <h2><?= h(t('portail_a_lire')) ?></h2>
  <div class="grille-art">
    <?php foreach ($lies as $x): $a_courant = $a; $a = $x; $grande = false;
        include __DIR__ . '/_carte_article.php'; $a = $a_courant; endforeach; ?>
  </div>
</section>
<?php endif; ?>
