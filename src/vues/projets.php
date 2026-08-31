<h1><?= h(t('nav_projets')) ?></h1>
<p class="lede"><?= h(t('proj_phase2')) ?></p>
<div class="carte">
  <h3><?= h(t('adm_projets')) ?></h3>
  <p><b><?= h(nombre($n)) ?></b> — <?= h(t('vide_intro')) ?></p>
</div>
<h2><?= h(t('proj_statut')) ?></h2>
<div class="tableau-boite">
<table class="tableau">
  <thead><tr><th><?= h(t('vide_champ')) ?></th><th><?= h(t('proj_sources')) ?></th></tr></thead>
  <tbody>
  <?php foreach ([
      'proj_budget' => 'Contrat, appel d\'offres ou communique du maitre d\'ouvrage',
      'proj_hauteur' => 'Permis de construire, fiche technique de l\'architecte',
      'proj_surface' => 'Dossier de permis',
      'proj_longueur' => 'Declaration d\'utilite publique, dossier technique',
      'proj_capacite' => 'Exploitant',
      'proj_maitre_ouvrage' => 'Registre du commerce, communique officiel',
      'proj_architecte' => 'Communique, publication professionnelle',
      'proj_dates' => 'Calendrier officiel du projet',
  ] as $cle => $src): ?>
    <tr><td><?= h(t($cle)) ?> <?= valeur('', 'projet-' . $cle) ?></td><td><?= h($src) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
