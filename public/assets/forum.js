/* URBAN FORUM — amelioration progressive uniquement.
   Rien ici n'est necessaire pour lire, ecrire, repondre, chercher, signaler
   ou moderer : tout passe par des formulaires POST classiques. Ce fichier
   rend l'usage plus confortable. Avec JavaScript desactive, le site perd le
   menu deroulant mobile, l'apercu instantane et l'enregistrement du
   brouillon — pas une fonction. C'est verifie par un test qui rejoue le
   parcours complet avec JavaScript coupe. */
(function () {
  'use strict';

  /* ---- menu mobile ---- */
  var bouton = document.querySelector('.menu-btn');
  var nav = document.getElementById('nav-principale');
  if (bouton && nav) {
    bouton.addEventListener('click', function () {
      var ouvert = nav.classList.toggle('ouvert');
      bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
  }

  /* ---- barre d'edition ---- */
  var zone = document.querySelector('textarea[name="corps"]');
  var barre = document.querySelector('.barre-edition');

  function entoure(avant, apres) {
    if (!zone) return;
    var d = zone.selectionStart, f = zone.selectionEnd;
    var sel = zone.value.slice(d, f);
    zone.value = zone.value.slice(0, d) + avant + sel + apres + zone.value.slice(f);
    zone.focus();
    zone.selectionStart = d + avant.length;
    zone.selectionEnd = d + avant.length + sel.length;
    zone.dispatchEvent(new Event('input'));
  }
  function prefixeLignes(p) {
    if (!zone) return;
    var d = zone.selectionStart, f = zone.selectionEnd;
    var bloc = zone.value.slice(d, f) || '';
    var res = bloc.split('\n').map(function (l) { return p + l; }).join('\n');
    zone.value = zone.value.slice(0, d) + res + zone.value.slice(f);
    zone.dispatchEvent(new Event('input'));
    zone.focus();
  }
  if (barre) {
    barre.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-outil]');
      if (!b) return;
      e.preventDefault();
      switch (b.dataset.outil) {
        case 'gras': entoure('**', '**'); break;
        case 'italique': entoure('*', '*'); break;
        case 'lien': entoure('[', '](https://)'); break;
        case 'citation': prefixeLignes('> '); break;
        case 'liste': prefixeLignes('- '); break;
        case 'video': entoure('video:', ''); break;
      }
    });
  }

  /* ---- citation simple et multiple ----
     Le bouton « Citer » ajoute au champ. Plusieurs clics = plusieurs
     citations empilees, ce qui est la citation multiple demandee ; il n'y a
     pas de mode separe a activer. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-citer]');
    if (!b || !zone) return;
    e.preventDefault();
    var src = document.getElementById(b.dataset.citer);
    if (!src) return;
    var txt = (src.dataset.brut || src.textContent || '').trim();
    if (txt.length > 1200) txt = txt.slice(0, 1200) + '…';
    var bloc = '[cite=' + b.dataset.auteur + '#' + b.dataset.message + ']\n' + txt + '\n[/cite]\n\n';
    zone.value = zone.value ? zone.value.replace(/\s*$/, '\n\n') + bloc : bloc;
    zone.dispatchEvent(new Event('input'));
    zone.focus();
    zone.scrollIntoView({ block: 'center' });
  });

  /* ---- mention rapide ---- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mentionner]');
    if (!b || !zone) return;
    e.preventDefault();
    zone.value = zone.value.replace(/\s*$/, '') + (zone.value ? ' ' : '') + '@' + b.dataset.mentionner + ' ';
    zone.focus();
    zone.dispatchEvent(new Event('input'));
  });

  /* ---- apercu ---- */
  var apercu = document.getElementById('apercu');
  var btnApercu = document.querySelector('[data-apercu]');
  if (btnApercu && zone && apercu) {
    btnApercu.addEventListener('click', function (e) {
      e.preventDefault();
      var fd = new FormData();
      fd.append('corps', zone.value);
      fd.append('_csrf', (document.querySelector('input[name="_csrf"]') || {}).value || '');
      fetch('/api/v1/apercu', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          apercu.innerHTML = j.rendu || '';
          apercu.hidden = false;
          apercu.scrollIntoView({ block: 'nearest' });
        })
        .catch(function () { apercu.hidden = true; });
    });
  }

  /* ---- brouillon : enregistrement local, pas serveur ----
     Le brouillon reste dans le navigateur tant qu'il n'est pas publie. On
     n'envoie pas chaque frappe au serveur : ce serait une ecriture en base
     toutes les deux secondes par redacteur, pour un texte qui ne sera
     peut-etre jamais publie. */
  if (zone && window.localStorage) {
    var cle = 'uf-brouillon:' + location.pathname;
    var etat = document.querySelector('[data-etat-brouillon]');
    var titre = document.querySelector('input[name="titre"]');
    try {
      var sauve = JSON.parse(localStorage.getItem(cle) || 'null');
      if (sauve && !zone.value) {
        zone.value = sauve.corps || '';
        if (titre && !titre.value) titre.value = sauve.titre || '';
      }
    } catch (err) { /* stockage indisponible : on continue sans */ }

    var minuteur = null;
    var enregistre = function () {
      clearTimeout(minuteur);
      minuteur = setTimeout(function () {
        try {
          localStorage.setItem(cle, JSON.stringify({
            corps: zone.value, titre: titre ? titre.value : ''
          }));
          if (etat) etat.textContent = etat.dataset.etatBrouillon;
        } catch (err) { /* quota plein */ }
      }, 800);
    };
    zone.addEventListener('input', enregistre);
    if (titre) titre.addEventListener('input', enregistre);
    var f = zone.closest('form');
    if (f) f.addEventListener('submit', function () { try { localStorage.removeItem(cle); } catch (e) {} });
  }

  /* ---- autocompletion de recherche ---- */
  var q = document.getElementById('q-rapide');
  if (q) {
    var liste = document.createElement('datalist');
    liste.id = 'uf-suggestions';
    document.body.appendChild(liste);
    q.setAttribute('list', 'uf-suggestions');
    var tm = null;
    q.addEventListener('input', function () {
      clearTimeout(tm);
      var v = q.value.trim();
      if (v.length < 2) return;
      tm = setTimeout(function () {
        fetch('/api/v1/autocomplete?q=' + encodeURIComponent(v), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            liste.innerHTML = '';
            (j.termes || []).forEach(function (t) {
              var o = document.createElement('option');
              o.value = t;
              liste.appendChild(o);
            });
          })
          .catch(function () {});
      }, 200);
    });
  }

  /* ---- compteur de caracteres ---- */
  var compteur = document.querySelector('[data-compteur]');
  if (compteur && zone) {
    var maj = function () { compteur.textContent = zone.value.length; };
    zone.addEventListener('input', maj);
    maj();
  }
})();
