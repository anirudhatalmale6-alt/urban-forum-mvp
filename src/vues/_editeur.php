<?php
/** Editeur partage : nouvelle discussion, reponse, edition. */
$valeur_corps = $valeur_corps ?? '';
/* Le libelle est parametrable : le meme editeur sert « Votre message » dans
   le forum et « Texte de l'article » dans la redaction du portail. Sans
   cela, le formulaire d'article demandait un « message ». */
$label_corps = $label_corps ?? t('ed_corps');
?>
<div class="champ">
  <label for="corps"><?= h($label_corps) ?></label>
  <div class="barre-edition">
    <button type="button" data-outil="gras"><b>B</b> <?= h(t('ed_gras')) ?></button>
    <button type="button" data-outil="italique"><i>I</i> <?= h(t('ed_italique')) ?></button>
    <button type="button" data-outil="lien"><?= h(t('ed_lien')) ?></button>
    <button type="button" data-outil="citation"><?= h(t('ed_citation')) ?></button>
    <button type="button" data-outil="liste"><?= h(t('ed_liste')) ?></button>
    <button type="button" data-outil="video"><?= h(t('ed_video')) ?></button>
    <button type="button" data-apercu="1"><?= h(t('disc_previsualiser')) ?></button>
  </div>
  <textarea id="corps" name="corps" required minlength="2" maxlength="20000"><?= h($valeur_corps) ?></textarea>
  <small>
    <?= h(t('ed_aide')) ?> :
    <code>**<?= h(t('ed_gras')) ?>**</code>
    <code>*<?= h(t('ed_italique')) ?>*</code>
    <code>&gt; <?= h(t('ed_citation')) ?></code>
    <code>- <?= h(t('ed_liste')) ?></code>
    <code>[<?= h(t('ed_lien')) ?>](https://…)</code>
    <code>@<?= h(t('cpt_identifiant')) ?></code>
    — <span data-compteur="1">0</span>/20000
  </small>
</div>
<div class="msg__texte carte" id="apercu" hidden></div>
