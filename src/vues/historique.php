<h1><?= h(t('disc_historique')) ?></h1>
<p class="lede"><a href="<?= h(lien('/d/' . $m['slug'] . '#m' . (int) $m['id'])) ?>"><?= h((string) $m['titre']) ?></a>
  — <?= h(t('disc_position', ['n' => (int) $m['position']])) ?></p>
<h2><?= h(t('disc_previsualiser')) ?></h2>
<div class="carte msg__texte"><?= $m['rendu'] ?></div>
<?php if (!$revs): ?><p class="vide-etat"><?= h(t('aucun_resultat')) ?></p><?php else: ?>
<?php foreach ($revs as $r): ?>
<div class="carte esp-haut">
  <p class="carte__meta"><?= h(date_lisible((string) $r['cree_le'])) ?>
    · <?= h((string) $r['identifiant']) ?>
    <?php if ($r['motif']): ?> · <?= h(t('disc_motif_edition')) ?> : <?= h((string) $r['motif']) ?><?php endif; ?></p>
  <div class="msg__texte"><?= rendre_message((string) $r['corps_avant']) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
