<h1><?= h(t('nav_recherche')) ?></h1>
<form class="form form--large" method="get" action="<?= h(lien('/recherche')) ?>" role="search">
  <?php if (langue() !== cfg('langue_defaut')): ?><input type="hidden" name="lang" value="<?= h(langue()) ?>"><?php endif; ?>
  <div class="champ">
    <label for="q"><?= h(t('rech_placeholder')) ?></label>
    <input id="q" type="search" name="q" value="<?= h($q) ?>" autofocus>
  </div>
  <div class="grille grille--3">
    <div class="champ">
      <label for="espace"><?= h(t('rech_filtres')) ?></label>
      <select id="espace" name="espace">
        <option value="forum"<?= $espace === 'forum' ? ' selected' : '' ?>><?= h(t('rech_dans_forum')) ?></option>
        <option value="projets"<?= $espace === 'projets' ? ' selected' : '' ?>><?= h(t('rech_dans_projets')) ?></option>
      </select>
    </div>
    <div class="champ">
      <label for="tri"><?= h(t('rech_tri_pertinence')) ?></label>
      <select id="tri" name="tri">
        <option value="pertinence"<?= $tri === 'pertinence' ? ' selected' : '' ?>><?= h(t('rech_tri_pertinence')) ?></option>
        <option value="date"<?= $tri === 'date' ? ' selected' : '' ?>><?= h(t('rech_tri_date')) ?></option>
        <option value="activite"<?= $tri === 'activite' ? ' selected' : '' ?>><?= h(t('rech_tri_activite')) ?></option>
      </select>
    </div>
    <div class="champ champ--bas">
      <button class="btn btn--plein" type="submit"><?= h(t('nav_recherche')) ?></button>
    </div>
  </div>
</form>

<?php if ($q !== ''): ?>
  <p class="lede"><?= h(t('rech_resultats', ['n' => nombre((int) $res['total'])])) ?></p>

  <?php if (!empty($res['suggestions'])): ?>
    <p class="avis avis--info"><?= h(t('rech_suggestion')) ?>
      <?php foreach ($res['suggestions'] as $s): ?>
        <a href="<?= h(lien('/recherche?q=' . rawurlencode($s))) ?>"><?= h($s) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if (!$res['resultats']): ?>
    <p class="vide-etat"><?= h(t('rech_aucun')) ?></p>
  <?php else: ?>
  <div class="liste">
    <?php foreach ($res['resultats'] as $r): ?>
    <div class="liste__ligne">
      <div>
        <span class="etiq"><?= h($r['type']) ?></span>
        <a class="liste__titre" href="<?= h(lien($r['url'])) ?>"><?= h($r['titre']) ?></a>
        <?php if (!empty($r['extrait'])): ?><div class="liste__sous"><?= h($r['extrait']) ?></div><?php endif; ?>
        <?php if (!empty($r['auteur'])): ?>
          <div class="liste__sous"><?= h(t('forum_par')) ?> <?= h($r['auteur']) ?>
            · <?= h(date_lisible((string) $r['date'])) ?></div>
        <?php endif; ?>
      </div>
      <div class="liste__nb"><b><?= h(nombre((int) $r['score'])) ?></b><?= h(t('rech_tri_pertinence')) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?= pagination((int) $page, (int) $res['total'], (int) $pp,
        '/recherche?q=' . rawurlencode($q) . '&espace=' . $espace . '&tri=' . $tri) ?>
  <?php endif; ?>
<?php endif; ?>
