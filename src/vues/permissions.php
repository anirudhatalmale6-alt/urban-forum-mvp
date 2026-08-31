<h1><?= h(t('adm_permissions')) ?></h1>
<h2><?= h(t('adm_permissions')) ?></h2>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th><?= h(t('role_membre')) ?></th><th>rang</th><th>n</th><th>permissions</th></tr></thead>
  <?php /* Les permissions affichees sont celles que le serveur APPLIQUE,
           c'est-a-dire celles de la table role_permissions — pas la
           declaration du code. Si les deux divergent, c'est la table qui
           gagne a l'execution ; une page qui montrerait le code montrerait
           donc autre chose que la realite. */ ?>
  <tbody><?php foreach ($roles as $r): $perms = permissions_de_role((string) $r['cle']); ?>
    <tr>
      <td><?= badge_role((string) $r['cle']) ?></td>
      <td><?= h(nombre((int) $r['rang'])) ?></td>
      <td><?= h(nombre((int) $r['nb'])) ?></td>
      <td><?php if ($perms === '*'): ?><b>*</b><?php else: ?>
        <?php foreach ($perms as $p): ?><code><?= h($p) ?></code> <?php endforeach; ?>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>

<h2><?= h(t('adm_membres')) ?></h2>
<div class="tableau-boite"><table class="tableau">
  <thead><tr><th><?= h(t('cpt_identifiant')) ?></th><th><?= h(t('cpt_inscrit_le')) ?></th>
    <th><?= h(t('cpt_messages_publies')) ?></th><th><?= h(t('mod_etat')) ?></th><th><?= h(t('mod_action')) ?></th></tr></thead>
  <tbody><?php foreach ($membres as $m): ?>
    <tr>
      <td><a href="<?= h(lien('/u/' . rawurlencode((string) $m['identifiant']))) ?>"><?= h((string) $m['identifiant']) ?></a>
          <?= badge_role((string) ($m['role_cle'] ?? 'membre')) ?></td>
      <td><?= h(date_lisible((string) $m['cree_le'])) ?></td>
      <td><?= h(nombre((int) $m['nb_messages'])) ?></td>
      <td><?php if ((int) $m['banni']): ?><span class="etiq etiq--haute"><?= h(t('mod_bannir')) ?></span>
          <?php elseif ($m['suspendu_jusqu'] && strtotime((string) $m['suspendu_jusqu'] . ' UTC') > time()): ?>
            <span class="etiq"><?= h(t('mod_suspendre')) ?></span>
          <?php else: ?><span class="etiq"><?= h(t('oui')) ?></span><?php endif; ?></td>
      <td>
        <form method="post" action="<?= h(lien('/admin/roles')) ?>" class="en-ligne">
          <?= csrf_champ() ?>
          <input type="hidden" name="utilisateur" value="<?= (int) $m['id'] ?>">
          <select name="role" aria-label="<?= h(t('adm_permissions')) ?>">
            <?php foreach (array_keys(ROLES) as $cle): if ($cle === 'visiteur') continue; ?>
              <option value="<?= h($cle) ?>"<?= $cle === ($m['role_cle'] ?? '') ? ' selected' : '' ?>><?= h(t('role_' . $cle)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn--petit" type="submit"><?= h(t('enregistrer')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
