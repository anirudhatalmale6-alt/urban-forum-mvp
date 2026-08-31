<?php
/**
 * Une carte d'article. Attend $a (ligne d'article) et, facultativement,
 * $grande = true pour la carte de tete.
 *
 * L'etiquette de langue n'apparait QUE si l'article n'est pas dans la langue
 * de lecture. Elle evite le clic qui tombe sur un texte qu'on ne lit pas —
 * et elle est preferable a une traduction automatique que personne n'a
 * relue.
 */
$grande = $grande ?? false;
$m = media_une(!empty($a['media_une_id']) ? (int) $a['media_une_id'] : null);
$rub = nom_rubrique_de_article($a);

/* Un titre francais affiche dans une page arabe reste du francais : sans
   `lang` et `dir`, le navigateur lui applique l'algorithme bidirectionnel
   de la page et met le point final du mauvais cote. Ce sont des attributs
   HTML, pas du style : la CSP ne les bloque pas.
 *
 * Ils sont poses sur le TITRE et le CHAPEAU seulement, pas sur la carte
 * entiere. La ligne « par X · 12 mars 2026 » est du mobilier d'interface :
 * elle est ecrite dans la langue de LECTURE. Mise en rtl avec le reste,
 * elle donnait « أغسطس 2026 19:31 27 · par … », c'est-a-dire une date
 * retournee autour d'un mot francais. */
$autre_langue = ($a['langue'] ?? '') !== '' && $a['langue'] !== langue();
$attr_langue = $autre_langue
    ? ' lang="' . h($a['langue']) . '" dir="' . ($a['langue'] === 'ar' ? 'rtl' : 'ltr') . '"'
    : '';
?>
<article class="carte-art<?= $grande ? ' carte-art--grande' : '' ?>">
  <?php if ($m): ?>
    <a class="carte-art__img" href="<?= h(lien('/a/' . $a['slug'])) ?>" tabindex="-1" aria-hidden="true">
      <img src="/media/<?= (int) $m['id'] ?>" alt=""
           <?php if ($m['largeur'] && $m['hauteur']): ?>width="<?= (int) $m['largeur'] ?>" height="<?= (int) $m['hauteur'] ?>"<?php endif; ?>
           loading="lazy" decoding="async">
    </a>
  <?php endif; ?>
  <div class="carte-art__corps">
    <p class="carte-art__sur">
      <?php if ($rub !== ''): ?>
        <a class="etiq etiq--rub" href="<?= h(lien('/r/' . $a['rubrique_slug'])) ?>"><?= h($rub) ?></a>
      <?php endif; ?>
      <?php if (!empty($a['demo'])): ?><span class="etiq etiq--demo"><?= h(t('demo_etiquette')) ?></span><?php endif; ?>
      <?php if (($a['langue'] ?? '') !== langue()): ?>
        <span class="etiq etiq--langue" lang="<?= h($a['langue']) ?>"><?= h(t('langue_' . $a['langue'])) ?></span>
      <?php endif; ?>
    </p>
    <h3 class="carte-art__titre"<?= $attr_langue ?>>
      <a href="<?= h(lien('/a/' . $a['slug'])) ?>"><?= h($a['titre']) ?></a>
    </h3>
    <?php if (!empty($a['chapeau'])): ?>
      <p class="carte-art__chapeau"<?= $attr_langue ?>><?= h(extrait((string) $a['chapeau'], $grande ? 260 : 150)) ?></p>
    <?php endif; ?>
    <p class="carte-art__meta">
      <?php $sig = $a['signature'] ?: ($a['auteur'] ?? ''); ?>
      <?php if ($sig !== ''): ?><?= h(t('portail_par')) ?> <?= h($sig) ?> · <?php endif; ?>
      <time datetime="<?= h(str_replace(' ', 'T', (string) $a['publie_le'])) ?>Z"><?= h(date_lisible((string) $a['publie_le'])) ?></time>
    </p>
  </div>
</article>
