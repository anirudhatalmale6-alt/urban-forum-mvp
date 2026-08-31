<?php
/**
 * Redaction d'un article.
 *
 * Le bouton « publier » n'apparait que si le compte a portail.publier — et
 * le controleur REFAIT le controle. L'interface qui cache et le serveur qui
 * refuse sont deux choses differentes ; seule la seconde protege.
 */
$v = fn(string $c, $d = '') => $a[$c] ?? $d;
?>

<h1><?= h($a ? t('portail_modifier') : t('portail_nouvel_article')) ?></h1>

<?php if (!empty($erreur_saisie)): ?>
  <p class="avis avis--erreur"><?= h($erreur_saisie) ?></p>
<?php endif; ?>

<?php if ($a): ?>
  <p class="barre-actions">
    <a class="btn" href="<?= h(lien('/a/' . $a['slug'])) ?>"><?= h(t('portail_apercu')) ?></a>
    <a class="btn" href="<?= h(lien('/admin/articles')) ?>"><?= h(t('retour')) ?></a>
  </p>
<?php endif; ?>

<form class="form form--large" method="post" action="<?= h(lien('/admin/articles')) ?>">
  <?= csrf_champ() ?>
  <?php if ($a): ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><?php endif; ?>
  <input type="hidden" name="groupe" value="<?= h((string) $v('groupe')) ?>">

  <div class="champ">
    <label for="titre"><?= h(t('portail_titre')) ?></label>
    <input id="titre" name="titre" type="text" required maxlength="255" value="<?= h((string) $v('titre')) ?>">
  </div>

  <div class="champ">
    <label for="chapeau"><?= h(t('portail_chapeau')) ?></label>
    <textarea id="chapeau" name="chapeau" class="ta-court" maxlength="600"><?= h((string) $v('chapeau')) ?></textarea>
    <small><?= h(t('portail_chapeau_aide')) ?></small>
  </div>

  <?php $valeur_corps = (string) $v('corps'); $label_corps = t('portail_corps');
        include __DIR__ . '/_editeur.php'; ?>

  <div class="grille grille--2">
    <div class="champ">
      <label for="rubrique_id"><?= h(t('portail_rubrique')) ?></label>
      <select id="rubrique_id" name="rubrique_id">
        <option value=""><?= h(t('portail_sans_rubrique')) ?></option>
        <?php foreach ($rubriques as $r): ?>
          <option value="<?= (int) $r['id'] ?>"<?= (int) $v('rubrique_id') === (int) $r['id'] ? ' selected' : '' ?>>
            <?= h(champ_langue($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="champ">
      <label for="langue"><?= h(t('portail_langue')) ?></label>
      <select id="langue" name="langue">
        <?php foreach (cfg('langues') as $l): ?>
          <option value="<?= h($l) ?>"<?= ((string) $v('langue', langue())) === $l ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option>
        <?php endforeach; ?>
      </select>
      <small><?= h(t('portail_langue_aide')) ?></small>
    </div>

    <div class="champ">
      <label for="ville_id"><?= h(t('portail_ville')) ?></label>
      <select id="ville_id" name="ville_id">
        <option value=""><?= h(t('portail_sans_ville')) ?></option>
        <?php foreach ($villes as $vi): ?>
          <option value="<?= (int) $vi['id'] ?>"<?= (int) $v('ville_id') === (int) $vi['id'] ? ' selected' : '' ?>>
            <?= h(champ_langue($vi)) ?> — <?= h(champ_langue($vi, 'pays')) ?></option>
        <?php endforeach; ?>
      </select>
      <small><?= h(t('portail_ville_aide')) ?></small>
    </div>

    <div class="champ">
      <label for="signature"><?= h(t('portail_signature')) ?></label>
      <input id="signature" name="signature" type="text" maxlength="190" value="<?= h((string) $v('signature')) ?>">
      <small><?= h(t('portail_signature_aide')) ?></small>
    </div>
  </div>

  <fieldset class="bloc-champ">
    <legend><?= h(t('portail_publication')) ?></legend>
    <div class="grille grille--2">
      <div class="champ">
        <label for="statut"><?= h(t('portail_statut')) ?></label>
        <select id="statut" name="statut"<?= peut('portail.publier') ? '' : ' disabled' ?>>
          <?php foreach (ARTICLE_STATUTS as $s): ?>
            <option value="<?= h($s) ?>"<?= ((string) $v('statut', 'brouillon')) === $s ? ' selected' : '' ?>><?= h(t('portail_statut_' . $s)) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!peut('portail.publier')): ?>
          <input type="hidden" name="statut" value="brouillon">
          <small><?= h(t('portail_sans_droit_publier')) ?></small>
        <?php endif; ?>
      </div>
      <div class="champ">
        <label for="publie_le"><?= h(t('portail_date_publication')) ?></label>
        <input id="publie_le" name="publie_le" type="text" maxlength="20" placeholder="2026-09-01 08:00"
               value="<?= h($a && $a['publie_le'] ? (string) $a['publie_le'] : '') ?>">
        <small><?= h(t('portail_date_aide')) ?></small>
      </div>
    </div>
    <?php if (peut('portail.une')): ?>
    <div class="grille grille--2">
      <div class="champ champ--case">
        <input id="une" name="une" type="checkbox" value="1"<?= (int) $v('une') ? ' checked' : '' ?>>
        <label for="une"><?= h(t('portail_une_case')) ?></label>
      </div>
      <div class="champ">
        <label for="rang_une"><?= h(t('portail_rang_une')) ?></label>
        <input id="rang_une" name="rang_une" type="number" min="1" max="999" value="<?= (int) $v('rang_une', 100) ?>">
      </div>
    </div>
    <?php endif; ?>
  </fieldset>

  <fieldset class="bloc-champ">
    <legend><?= h(t('portail_sources')) ?></legend>
    <p class="note"><?= h(t('portail_sources_aide')) ?></p>
    <div class="grille grille--3">
      <div class="champ">
        <label for="source_url"><?= h(t('portail_source_url')) ?></label>
        <input id="source_url" name="source_url" type="url" maxlength="500" placeholder="https://">
      </div>
      <div class="champ">
        <label for="source_titre"><?= h(t('portail_source_titre')) ?></label>
        <input id="source_titre" name="source_titre" type="text" maxlength="255">
      </div>
      <div class="champ">
        <label for="source_editeur"><?= h(t('portail_source_editeur')) ?></label>
        <input id="source_editeur" name="source_editeur" type="text" maxlength="150">
      </div>
    </div>
  </fieldset>

  <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
</form>

<?php if ($sources): ?>
<section class="bloc">
  <h2><?= h(t('portail_sources_enregistrees')) ?></h2>
  <ul class="sources">
    <?php foreach ($sources as $s): ?>
      <li>
        <a href="<?= h($s['url']) ?>" rel="nofollow noopener" target="_blank"><?= h($s['titre'] ?: $s['url']) ?></a>
        <?php if (!empty($s['editeur'])): ?><span class="sources__editeur"><?= h($s['editeur']) ?></span><?php endif; ?>
        <form method="post" action="<?= h(lien('/admin/articles/source')) ?>" class="en-ligne">
          <?= csrf_champ() ?>
          <input type="hidden" name="source" value="<?= (int) $s['id'] ?>">
          <input type="hidden" name="article" value="<?= (int) $a['id'] ?>">
          <button class="lien-bouton" type="submit"><?= h(t('portail_retirer')) ?></button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
