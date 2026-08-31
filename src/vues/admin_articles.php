<?php /** Gestion du portail : la liste des articles, tous etats confondus. */ ?>

<h1><?= h(t('portail_gestion')) ?></h1>

<div class="chiffres chiffres--compact">
  <div class="chiffre"><b><?= h(nombre($comptes['publie'])) ?></b><span><?= h(t('portail_statut_publie')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($comptes['programme'])) ?></b><span><?= h(t('portail_statut_programme')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($comptes['brouillon'])) ?></b><span><?= h(t('portail_statut_brouillon')) ?></span></div>
  <div class="chiffre"><b><?= h(nombre($comptes['retire'])) ?></b><span><?= h(t('portail_statut_retire')) ?></span></div>
</div>

<p class="barre-actions">
  <a class="btn btn--plein" href="<?= h(lien('/admin/articles/nouveau')) ?>"><?= h(t('portail_nouvel_article')) ?></a>
  <a class="btn<?= $etat === null ? ' btn--actif' : '' ?>" href="<?= h(lien('/admin/articles')) ?>"><?= h(t('portail_tous')) ?></a>
  <?php foreach (ARTICLE_STATUTS as $s): ?>
    <a class="btn<?= $etat === $s ? ' btn--actif' : '' ?>"
       href="<?= h(lien('/admin/articles?etat=' . $s)) ?>"><?= h(t('portail_statut_' . $s)) ?></a>
  <?php endforeach; ?>
</p>

<?php if (!$articles): ?>
  <p class="vide-etat"><?= h(t('portail_aucun_brouillon')) ?></p>
<?php else: ?>
<div class="tableau-boite">
<table class="tableau">
  <thead>
    <tr>
      <th scope="col"><?= h(t('portail_titre')) ?></th>
      <th scope="col"><?= h(t('portail_rubrique')) ?></th>
      <th scope="col"><?= h(t('portail_langue')) ?></th>
      <th scope="col"><?= h(t('portail_statut')) ?></th>
      <th scope="col"><?= h(t('portail_date_publication')) ?></th>
      <th scope="col"><?= h(t('forum_vues')) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($articles as $a):
      $prog = $a['statut'] === 'publie' && !empty($a['publie_le'])
              && strtotime($a['publie_le'] . ' UTC') > time(); ?>
    <tr>
      <td>
        <a href="<?= h(lien('/admin/articles/' . (int) $a['id'])) ?>"><?= h($a['titre']) ?></a>
        <?php if ((int) $a['une']): ?><span class="etiq etiq--une"><?= h(t('portail_une')) ?></span><?php endif; ?>
        <?php if ((int) $a['demo']): ?><span class="etiq etiq--demo"><?= h(t('demo_etiquette')) ?></span><?php endif; ?>
      </td>
      <td><?= h(nom_rubrique_de_article($a)) ?></td>
      <td><?= h(t('langue_' . $a['langue'])) ?></td>
      <td><?= h($prog ? t('portail_statut_programme') : t('portail_statut_' . $a['statut'])) ?></td>
      <?php /* Pas de pastille « à renseigner » ici. Cette pastille est
               reservee aux valeurs que le proprietaire du site DOIT fournir
               (domaine, entite juridique…). Un brouillon sans date de
               publication n'est pas une valeur manquante : c'est l'etat
               normal d'un brouillon. */ ?>
      <td><?= $a['publie_le'] ? h(date_lisible((string) $a['publie_le'])) : '<span class="sans-valeur">—</span>' ?></td>
      <td><?= h(nombre((int) $a['nb_vues'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
