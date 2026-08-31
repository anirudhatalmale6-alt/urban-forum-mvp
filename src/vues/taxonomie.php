<h1><?= h(t('adm_taxonomie')) ?></h1>
<?php if (!empty($message)): ?><p class="avis avis--<?= h($message['type']) ?>"><?= h($message['texte']) ?></p><?php endif; ?>

<div class="grille grille--2">
  <form class="carte" method="post" action="<?= h(lien('/admin/taxonomie')) ?>">
    <?= csrf_champ() ?>
    <input type="hidden" name="quoi" value="ville">
    <h3><?= h(t('geo_ville')) ?></h3>
    <div class="champ"><label for="pays_id"><?= h(t('geo_pays')) ?></label>
      <select id="pays_id" name="pays_id" required>
        <?php foreach ($pays as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h(champ_langue($p)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="nom"><?= h(t('geo_ville')) ?></label>
      <input id="nom" name="nom" type="text" required maxlength="150"></div>
    <div class="champ"><label for="nom_ar">العربية</label>
      <input id="nom_ar" name="nom_ar" type="text" maxlength="150" dir="rtl"></div>
    <div class="grille grille--2">
      <div class="champ"><label for="lat">Latitude</label>
        <input id="lat" name="latitude" type="text" inputmode="decimal">
        <small><?= h(t('facultatif')) ?></small></div>
      <div class="champ"><label for="lon">Longitude</label>
        <input id="lon" name="longitude" type="text" inputmode="decimal">
        <small><?= h(t('facultatif')) ?></small></div>
    </div>
    <div class="champ"><label><input type="checkbox" name="approx" value="1"> <?= h(t('proj_niveau_estimation')) ?></label></div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>

  <form class="carte" method="post" action="<?= h(lien('/admin/taxonomie')) ?>">
    <?= csrf_champ() ?>
    <input type="hidden" name="quoi" value="categorie">
    <h3><?= h(t('geo_secteur')) ?></h3>
    <div class="champ"><label for="cnom"><?= h(t('geo_secteur')) ?></label>
      <input id="cnom" name="nom" type="text" required maxlength="150"></div>
    <div class="champ"><label for="cnom_ar">العربية</label>
      <input id="cnom_ar" name="nom_ar" type="text" maxlength="150" dir="rtl"></div>
    <div class="champ"><label for="ctype">Type</label>
      <select id="ctype" name="type"><option value="secteur">secteur</option><option value="typologie">typologie</option></select></div>
    <div class="champ"><label for="crang">Rang</label>
      <input id="crang" name="rang" type="number" value="100"></div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>
</div>

<h2><?= h(t('geo_ville')) ?> (<?= h(nombre(count($villes))) ?>)</h2>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th><?= h(t('geo_ville')) ?></th><th><?= h(t('geo_pays')) ?></th><th>lat</th><th>lon</th></tr></thead>
  <tbody><?php foreach ($villes as $v): ?>
    <tr><td><a href="<?= h(lien('/v/' . $v['slug'])) ?>"><?= h(champ_langue($v)) ?></a></td>
        <td><?= h((string) $v['pays_slug']) ?></td>
        <td><?= $v['latitude'] === null ? valeur('', 'ville-' . $v['slug'] . '-lat') : h((string) $v['latitude']) ?></td>
        <td><?= $v['longitude'] === null ? valeur('', 'ville-' . $v['slug'] . '-lon') : h((string) $v['longitude']) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>

<h2><?= h(t('geo_secteur')) ?> (<?= h(nombre(count($categories))) ?>)</h2>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th>fr</th><th>en</th><th>ar</th><th>type</th></tr></thead>
  <tbody><?php foreach ($categories as $c): ?>
    <tr><td><?= h((string) $c['nom_fr']) ?></td><td><?= h((string) $c['nom_en']) ?></td>
        <td dir="rtl"><?= $c['nom_ar'] ? h((string) $c['nom_ar']) : valeur('', 'cat-' . $c['slug'] . '-ar') ?></td>
        <td><?= h((string) $c['type']) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
