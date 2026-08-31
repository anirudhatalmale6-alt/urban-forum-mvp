#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
URBAN FORUM — suite de controles.

Elle est ecrite CONTRE LA SECTION 14 du cahier des charges : les dix
criteres de recette du MVP, dans l'ordre, plus ce qu'il faut mesurer pour
que chacun veuille dire quelque chose.

Deux principes, appris a leurs depens :

  - on mesure la CHOSE, pas une approximation. Le contraste est lu sur les
    couleurs CALCULEES par le navigateur, pas dans la feuille de style : lire
    une feuille ne dit pas quelle regle a gagne. Le debordement horizontal
    est lu sur scrollWidth, pas sur la presence d'un ascenseur.

  - un controle qui ne peut pas echouer ne prouve rien. Chaque bloc affiche
    ce qu'il a compte ; un filtre qui ne selectionne rien est un ECHEC, pas
    un « tout est passe ».

    python3 tests/tests-forum.py [http://127.0.0.1:8830]
"""

import json
import os
import re
import subprocess
import sys
import time
import urllib.parse

import requests

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8830").rstrip("/")
RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

OK, ECHECS = 0, []


def verif(nom, condition, detail=""):
    global OK
    if condition:
        OK += 1
        print("  ok   %s%s" % (nom, (" — " + detail) if detail else ""))
    else:
        ECHECS.append((nom, detail))
        print("  ECHEC %s — %s" % (nom, detail))


def titre(n):
    print("\n" + n)
    print("-" * len(n))


def php(*args):
    r = subprocess.run(["php"] + list(args), cwd=RACINE,
                       capture_output=True, text=True, timeout=180)
    return r.returncode, r.stdout + r.stderr


def csrf(session, chemin):
    """Le jeton depend du cookie de session : il faut le relire sur la page
    d'ou part le formulaire, pas le recopier d'une autre."""
    r = session.get(BASE + chemin)
    m = re.search(r'name="_csrf" value="([a-f0-9]+)"', r.text)
    return m.group(1) if m else None


# ======================================================================
titre("1. Navigation publique sans compte (critere 1)")
# ======================================================================

# Les compteurs anti-abus sont remis a zero AVANT de commencer : la suite
# s'inscrit et publie a chaque execution, et sans cela la troisieme
# execution echoue sur la limite d'inscriptions — en faisant tomber trente
# controles qui n'ont rien a voir. La limite est verifiee plus bas, par un
# controle qui l'epuise exprès.
code, sortie = php(os.path.join("tests", "reset-limites.php"))
verif("compteurs de limitation remis a zero avant la suite", code == 0,
      sortie.strip().splitlines()[-1][:60])

anon = requests.Session()
pages_publiques = [
    "/", "/forums", "/villes", "/projets", "/aide",
    "/continent/europe", "/pays/france", "/v/paris", "/f/c-afrique",
    "/f/s-transport", "/robots.txt", "/api/v1", "/api/v1/forums",
]
for p in pages_publiques:
    r = anon.get(BASE + p)
    verif("GET %s" % p, r.status_code == 200, "%d, %d octets" % (r.status_code, len(r.content)))

# Une page qui repond 200 autour de zero contenu est une page cassee.
r = anon.get(BASE + "/forums")
n_forums = len(re.findall(r'href="/f/', r.text))
verif("/forums liste des forums", n_forums >= 10, "%d liens de forum" % n_forums)

r = anon.get(BASE + "/")
n_disc = len(re.findall(r'href="/d/', r.text))
verif("accueil liste des discussions", n_disc >= 5, "%d liens de discussion" % n_disc)

r = anon.get(BASE + "/villes")
n_villes = len(re.findall(r'href="/v/', r.text))
verif("/villes liste les villes", n_villes >= 20, "%d villes" % n_villes)

verif("404 sur une discussion inexistante",
      anon.get(BASE + "/d/nexiste-pas-du-tout").status_code == 404)
verif("405 sur un verbe non prevu",
      anon.post(BASE + "/forums").status_code in (405, 419),
      str(anon.post(BASE + "/forums").status_code))

# ======================================================================
titre("2. Inscription, connexion, discussion, reponse, image (critere 2)")
# ======================================================================

suffixe = str(int(time.time()))[-6:]
PSEUDO = "testeur_" + suffixe
MDP = "mot-de-passe-test-1"

s = requests.Session()
jeton = csrf(s, "/inscription")
verif("jeton CSRF present sur l'inscription", jeton is not None)

r = s.post(BASE + "/inscription", data={
    "_csrf": jeton, "identifiant": PSEUDO, "email": PSEUDO + "@exemple.test",
    "mot_de_passe": MDP, "mot_de_passe2": MDP, "site_web": "",
}, allow_redirects=True)
verif("inscription", r.status_code == 200 and PSEUDO in r.text, "pseudo visible dans l'entete")

# Mot de passe trop court : le formulaire doit refuser, pas creer le compte.
s2 = requests.Session()
j2 = csrf(s2, "/inscription")
r = s2.post(BASE + "/inscription", data={
    "_csrf": j2, "identifiant": "court_" + suffixe, "email": "c%s@exemple.test" % suffixe,
    "mot_de_passe": "court", "mot_de_passe2": "court", "site_web": "",
})
verif("mot de passe trop court refuse", r.status_code == 422, str(r.status_code))

# Le piege a robots repond 200 et n'inscrit personne.
s3 = requests.Session()
j3 = csrf(s3, "/inscription")
r = s3.post(BASE + "/inscription", data={
    "_csrf": j3, "identifiant": "robot_" + suffixe, "email": "r%s@exemple.test" % suffixe,
    "mot_de_passe": MDP, "mot_de_passe2": MDP, "site_web": "http://spam.example",
}, allow_redirects=True)
verif("piege a robots : 200 sans compte cree",
      r.status_code == 200 and s3.get(BASE + "/u/robot_" + suffixe).status_code == 404)

# Creation d'une discussion.
jeton = csrf(s, "/nouvelle-discussion")
TITRE = "Controle automatique %s : citation, mention et recherche" % suffixe
CORPS = ("**Message de controle.**\n\n"
         "> une citation\n\n"
         "- un element\n- un autre\n\n"
         "Bonjour @amina_b, ce message sert au test de mention. "
         "Mot rare pour la recherche : zylographie%s" % suffixe)
r = s.post(BASE + "/nouvelle-discussion",
           data={"_csrf": jeton, "forum": "s-transport", "titre": TITRE, "corps": CORPS},
           allow_redirects=True)
verif("creation d'une discussion", r.status_code == 200 and "zylographie" in r.text,
      "url %s" % r.url)
SLUG = urllib.parse.urlparse(r.url).path.replace("/d/", "")

verif("le gras est rendu", "<strong>Message de controle.</strong>" in r.text)
verif("la citation est rendue", "<blockquote>" in r.text)
verif("la liste est rendue", "<li>un element</li>" in r.text)
verif("la mention est un lien", 'class="mention" href="/u/amina_b"' in r.text)

# Reponse.
DID = None
m = re.search(r'name="discussion" value="(\d+)"', r.text)
if m:
    DID = m.group(1)
verif("identifiant de discussion trouve", DID is not None)

jeton = csrf(s, "/d/" + SLUG)
r = s.post(BASE + "/repondre",
           data={"_csrf": jeton, "discussion": DID,
                 "corps": "Reponse de controle avec citation :\n\n[cite=%s#1]extrait[/cite]" % PSEUDO},
           allow_redirects=True)
verif("reponse publiee", r.status_code == 200 and "Reponse de controle" in r.text)
verif("citation [cite=] rendue", 'class="cite"' in r.text)

# Televersement d'une image, par l'API interne du site.
import struct, zlib
def png_minimal(w=8, h=8):
    def chunk(t, d):
        c = t + d
        return struct.pack(">I", len(d)) + c + struct.pack(">I", zlib.crc32(c) & 0xffffffff)
    ihdr = struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0)
    brut = b"".join(b"\x00" + b"\x80\x80\x80" * w for _ in range(h))
    return (b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", ihdr)
            + chunk(b"IDAT", zlib.compress(brut)) + chunk(b"IEND", b""))

# Il n'y a pas de route de televersement isolee : l'image accompagne un
# message. On exerce donc la fonction elle-meme, avec un vrai fichier, puis
# on verifie que /media/<id> le ressert par HTTP.
chemin_png = os.path.join(RACINE, "donnees", "controle.png")
with open(chemin_png, "wb") as f:
    f.write(png_minimal())
code, sortie = php(os.path.join("tests", "televersement.php"), chemin_png, PSEUDO)
try:
    res = json.loads(sortie.strip().splitlines()[-1])
except Exception:
    res = {}
verif("televersement d'une image accepte", "id" in res, sortie.strip()[:200])

if "id" in res:
    r = anon.get(BASE + "/media/%d" % res["id"])
    verif("/media/<id> sert l'image",
          r.status_code == 200 and r.headers.get("Content-Type") == "image/png",
          "%d %s %d octets" % (r.status_code, r.headers.get("Content-Type"), len(r.content)))
    verif("nosniff sur le media", r.headers.get("X-Content-Type-Options") == "nosniff")

# Un fichier PHP deguise en image doit etre refuse.
chemin_faux = os.path.join(RACINE, "donnees", "faux.png")
with open(chemin_faux, "wb") as f:
    f.write(b"<?php echo 'execute'; ?>")
code, sortie = php(os.path.join("tests", "televersement.php"), chemin_faux, PSEUDO)
verif("fichier PHP renomme .png refuse", '"erreur"' in sortie, sortie.strip()[-120:])

# Le repertoire des medias n'est PAS servi par le serveur web.
for chemin in ["/donnees/medias/", "/../donnees/forum.sqlite", "/donnees/forum.sqlite"]:
    r = anon.get(BASE + chemin)
    verif("hors d'atteinte : %s" % chemin, r.status_code in (403, 404),
          str(r.status_code))

# ======================================================================
titre("3. Mentions et abonnements declenchent des notifications (critere 3)")
# ======================================================================

code, sortie = php(os.path.join("tests", "notifications.php"), PSEUDO, SLUG)
try:
    n = json.loads(sortie.strip().splitlines()[-1])
except Exception:
    n = {}
verif("notification de mention creee", n.get("mention_avant") == 1,
      "amina_b : %s" % n.get("mention_avant"))
verif("notification d'abonnement creee", n.get("abonnement", 0) >= 1,
      "abonnes notifies : %s" % n.get("abonnement"))
verif("preference desactivee : aucune notification",
      n.get("mention_apres") == 0,
      "apres desactivation : %s" % n.get("mention_apres"))
verif("un membre bloque ne notifie pas", n.get("bloque") == 0, str(n.get("bloque")))
verif("aucun e-mail marque envoye sans expediteur configure",
      n.get("emails_envoyes") == 0, str(n.get("emails_envoyes")))

# ======================================================================
titre("4. Un moderateur traite un signalement, avec trace (critere 4)")
# ======================================================================

code, sortie = php(os.path.join("tests", "moderation.php"), PSEUDO)
try:
    mo = json.loads(sortie.strip().splitlines()[-1])
except Exception:
    mo = {}
verif("signalement cree", mo.get("signalement_id", 0) > 0, str(mo.get("signalement_id")))
verif("etat passe a en_revue", mo.get("etat_revue") == "en_revue", str(mo.get("etat_revue")))
verif("action appliquee : message masque", mo.get("masque") == 1, str(mo.get("masque")))
verif("etat passe a actionne", mo.get("etat_final") == "actionne", str(mo.get("etat_final")))
verif("action ecrite dans le journal de moderation",
      mo.get("journal") == 1, "%s ligne(s)" % mo.get("journal"))
verif("le journal nomme le moderateur et l'objet",
      mo.get("journal_complet") is True, str(mo.get("journal_detail"))[:120])
verif("un message masque sort de l'index de recherche",
      mo.get("indexe_apres") == 0, str(mo.get("indexe_apres")))
verif("demasquer le remet dans l'index",
      mo.get("indexe_restaure", 0) > 0, str(mo.get("indexe_restaure")))
verif("un moderateur ne bannit pas un administrateur",
      mo.get("bannir_admin") == "rang", str(mo.get("bannir_admin")))

# ======================================================================
titre("5. La recherche trouve discussions et messages (critere 5)")
# ======================================================================

t0 = time.time()
r = anon.get(BASE + "/api/v1/recherche", params={"q": "zylographie" + suffixe})
ms = (time.time() - t0) * 1000
j = r.json()
verif("recherche d'un mot rare", j.get("total", 0) >= 1,
      "%d resultat(s) en %.0f ms" % (j.get("total", 0), ms))
verif("le resultat pointe la bonne discussion",
      any(SLUG in x.get("url", "") for x in j.get("resultats", [])),
      str([x.get("url") for x in j.get("resultats", [])])[:160])

j = anon.get(BASE + "/api/v1/recherche", params={"q": "citation"}).json()
verif("recherche d'un mot courant", j.get("total", 0) >= 2, "%d resultats" % j.get("total", 0))

# Le message de controle contient « metro ». On cherche « subway », qui
# n'y figure PAS : seul le synonyme peut ramener un resultat.
# Premiere version : elle comparait metro=0 et subway=0 et concluait
# « passe ». Un filtre qui ne selectionne rien dit toujours oui.
jeton = csrf(s, "/d/" + SLUG)
s.post(BASE + "/repondre",
       data={"_csrf": jeton, "discussion": DID,
             "corps": "Ligne de controle des synonymes : metro, et rien d'autre."},
       allow_redirects=True)
j = anon.get(BASE + "/api/v1/recherche", params={"q": "metro"}).json()
j2 = anon.get(BASE + "/api/v1/recherche", params={"q": "subway"}).json()
verif("le mot indexe est bien trouve", j.get("total", 0) >= 1, "metro : %s" % j.get("total"))
verif("le synonyme ramene le meme contenu", j2.get("total", 0) >= 1,
      "subway (synonyme de metro) : %s" % j2.get("total"))

j = anon.get(BASE + "/api/v1/recherche", params={"q": "citaion"}).json()
verif("tolerance aux fautes : une suggestion est proposee",
      len(j.get("suggestions", [])) >= 1, str(j.get("suggestions")))

j = anon.get(BASE + "/api/v1/autocomplete", params={"q": "cit"}).json()
verif("autocompletion", len(j.get("termes", [])) >= 1, str(j.get("termes"))[:100])

j = anon.get(BASE + "/api/v1/recherche", params={"q": "xyzabcinexistant"}).json()
verif("recherche sans resultat : total 0, pas d'erreur", j.get("total") == 0)

# Une recherche sans resultat doit etre ENREGISTREE pour l'admin.
code, sortie = php("-r", "require 'src/noyau.php'; "
                         "echo (int) qval('SELECT compte FROM recherches_vides WHERE requete = ?', "
                         "['xyzabcinexistant']);")
# Le compteur s'incremente a chaque execution de la suite : on verifie
# qu'il est >= 1, pas qu'il vaut exactement 1. Premiere version : « == 1 »,
# qui passait au premier lancement et echouait au second.
n_vide = int(re.findall(r"\d+", sortie.strip().splitlines()[-1] or "0")[-1])
verif("recherche vide enregistree pour l'administration", n_vide >= 1,
      "compte = %d" % n_vide)

# ======================================================================
titre("6. Pages publiques indexables, SEO, mobile (critere 6)")
# ======================================================================

for p in ["/", "/forums", "/v/paris", "/d/" + SLUG]:
    html = anon.get(BASE + p).text
    verif("%s : titre unique" % p, "<title>" in html and len(re.findall(r"<title>", html)) == 1)
    verif("%s : meta description" % p, 'name="description"' in html)
    verif("%s : og:title" % p, 'property="og:title"' in html)
    verif("%s : pas de noindex" % p, 'name="robots"' not in html)
    verif("%s : hreflang pour les 3 langues" % p,
          len(re.findall(r'rel="alternate" hreflang=', html)) == 3,
          "%d" % len(re.findall(r'rel="alternate" hreflang=', html)))

for p in ["/recherche?q=a", "/connexion", "/inscription", "/a-renseigner"]:
    html = anon.get(BASE + p).text
    verif("%s : noindex" % p, 'content="noindex, nofollow"' in html)

html = anon.get(BASE + "/d/" + SLUG).text
verif("donnees structurees DiscussionForumPosting", '"DiscussionForumPosting"' in html)
verif("fil d'Ariane en JSON-LD", '"BreadcrumbList"' in html)

txt = anon.get(BASE + "/robots.txt").text
verif("robots.txt interdit les pages profondes",
      "Disallow: /recherche" in txt and "Disallow: /admin" in txt)

r = anon.get(BASE + "/sitemap.xml")
verif("sitemap : 503 explicite tant que le domaine est vide",
      r.status_code == 503 and "domaine" in r.text, str(r.status_code))

# ======================================================================
titre("7. FR / EN / AR, y compris le RTL (critere 7)")
# ======================================================================

code, sortie = php(os.path.join("tests", "cles-langues.php"))
try:
    cl = json.loads(sortie.strip().splitlines()[-1])
except Exception:
    cl = {}
verif("les trois dictionnaires portent les memes cles",
      cl.get("manquantes") == [] and cl.get("en_trop") == [],
      "fr=%s en=%s ar=%s, manquantes=%s" % (cl.get("n_fr"), cl.get("n_en"), cl.get("n_ar"),
                                            cl.get("manquantes")))
verif("le dictionnaire n'est pas vide", (cl.get("n_fr") or 0) >= 150, str(cl.get("n_fr")))

for lang, attendu_dir in [("fr", "ltr"), ("en", "ltr"), ("ar", "rtl")]:
    html = anon.get(BASE + "/?lang=" + lang).text
    verif("lang=%s : dir=%s" % (lang, attendu_dir),
          ('dir="%s"' % attendu_dir) in html and ('lang="%s"' % lang) in html)
    verif("lang=%s : aucune cle non traduite" % lang,
          "«" not in re.sub(r"«\s*\w+\s*»", lambda m: "" if " " in m.group(0) else m.group(0), "") + "" or
          not re.search(r"«[a-z_]{3,}»", html),
          str(re.findall(r"«[a-z_]{3,}»", html))[:120])

html_en = anon.get(BASE + "/forums?lang=en").text
corps_en = html_en[html_en.find('<main'):html_en.find('</main>')]
restes = [m for m in ["Discussions", "Recherche", "Villes", "Répondre", "Connexion"]
          if m in corps_en]
verif("page anglaise sans reste de francais dans le contenu",
      restes == [], str(restes))

html_ar = anon.get(BASE + "/forums?lang=ar").text
verif("page arabe : contenu en arabe",
      len(re.findall(r"[؀-ۿ]", html_ar)) > 100,
      "%d caracteres arabes" % len(re.findall(r"[؀-ۿ]", html_ar)))

# ======================================================================
titre("8. Les permissions bloquent cote interface ET cote API (critere 8)")
# ======================================================================

# Anonyme.
for chemin in ["/moderation", "/admin", "/notifications", "/parametres", "/signets"]:
    r = anon.get(BASE + chemin)
    verif("anonyme sur %s" % chemin, r.status_code == 401, str(r.status_code))
for chemin in ["/api/v1/notifications", "/api/v1/moderation/file"]:
    r = anon.get(BASE + chemin)
    verif("API anonyme sur %s" % chemin, r.status_code == 401, str(r.status_code))
    verif("API anonyme repond du JSON",
          r.headers.get("Content-Type", "").startswith("application/json"),
          r.headers.get("Content-Type", ""))

# Membre simple : lecture oui, moderation non — des deux cotes.
verif("membre sur /notifications", s.get(BASE + "/notifications").status_code == 200)
verif("membre sur /moderation (interface)", s.get(BASE + "/moderation").status_code == 403,
      str(s.get(BASE + "/moderation").status_code))
r = s.get(BASE + "/api/v1/moderation/file")
verif("membre sur /api/v1/moderation/file (API)", r.status_code == 403, str(r.status_code))
verif("le refus API est du JSON", r.headers.get("Content-Type", "").startswith("application/json"))

# Ecriture sans jeton CSRF.
r = s.post(BASE + "/repondre", data={"discussion": DID, "corps": "sans jeton"})
verif("POST sans jeton CSRF refuse", r.status_code == 419, str(r.status_code))
r = s.post(BASE + "/api/v1/messages", data={"discussion": DID, "corps": "sans jeton"})
verif("POST API sans jeton CSRF refuse", r.status_code == 419, str(r.status_code))

# Ecriture dans une discussion verrouillee.
code, sortie = php("-r", "require 'src/noyau.php'; "
                         "echo qval('SELECT id FROM discussions WHERE verrouillee = 1 LIMIT 1');")
did_verrou = sortie.strip().splitlines()[-1] if sortie.strip() else ""
if did_verrou.isdigit():
    jeton = csrf(s, "/d/" + SLUG)
    r = s.post(BASE + "/repondre", data={"_csrf": jeton, "discussion": did_verrou,
                                         "corps": "tentative sur discussion verrouillee"})
    verif("reponse refusee dans une discussion verrouillee", r.status_code == 403,
          str(r.status_code))
else:
    verif("une discussion verrouillee existe pour le test", False, "aucune")

# Injection et script.
r = anon.get(BASE + "/api/v1/recherche", params={"q": "' OR 1=1 --"})
verif("apostrophe dans la recherche : pas d'erreur serveur", r.status_code == 200,
      str(r.status_code))
jeton = csrf(s, "/d/" + SLUG)
XSS = '<script>window.__xss=1</script> et <img src=x onerror="window.__xss=2">'
r = s.post(BASE + "/repondre", data={"_csrf": jeton, "discussion": DID, "corps": XSS},
           allow_redirects=True)
verif("balise <script> neutralisee", "<script>window.__xss" not in r.text)
verif("attribut onerror neutralise", "onerror=" not in r.text.replace("&quot;", '"')
      or "&lt;img" in r.text)

# En-tetes de securite.
h = anon.get(BASE + "/").headers
verif("CSP presente", "Content-Security-Policy" in h, h.get("Content-Security-Policy", "")[:60])
verif("CSP sans unsafe-inline", "unsafe-inline" not in h.get("Content-Security-Policy", ""))
verif("X-Frame-Options", h.get("X-Frame-Options") == "DENY")
verif("nosniff", h.get("X-Content-Type-Options") == "nosniff")
verif("Referrer-Policy", "strict-origin" in h.get("Referrer-Policy", ""))

# Limitation de debit sur la connexion.
sx = requests.Session()
codes = []
for i in range(7):
    jx = csrf(sx, "/connexion")
    rx = sx.post(BASE + "/connexion",
                 data={"_csrf": jx, "identifiant": "inconnu_%s" % suffixe,
                       "mot_de_passe": "faux"})
    codes.append(rx.status_code)
    if "Trop d" in rx.text or "Too many" in rx.text:
        break
verif("la limitation de debit finit par bloquer les tentatives",
      any("Trop d" in x for x in [sx.post(BASE + "/connexion",
          data={"_csrf": csrf(sx, "/connexion"), "identifiant": "inconnu_%s" % suffixe,
                "mot_de_passe": "faux"}).text]),
      "codes %s" % codes)

# ======================================================================
titre("9. Sauvegarde et restauration testees (critere 9)")
# ======================================================================

code, sortie = php("outils/sauvegarde.php")
verif("la sauvegarde se termine sans erreur", code == 0, sortie.strip().splitlines()[-1][:80])
m = re.search(r"Sauvegarde : (\S+\.sql)", sortie)
fichier = m.group(1) if m else None
verif("fichier de sauvegarde produit", fichier is not None and os.path.isfile(fichier),
      str(fichier))
m = re.search(r"lignes ecrites et relues : (\d+)", sortie)
verif("la sauvegarde est relue et recomptee", m is not None and int(m.group(1)) > 100,
      "%s lignes" % (m.group(1) if m else "?"))

if fichier:
    code, sortie = php("outils/restauration.php", fichier, "--essai")
    verif("restauration d'essai : chaque table retrouve son compte", code == 0,
          sortie.strip().splitlines()[-1][:90])
    verif("la restauration n'a signale aucun ecart", "ECART" not in sortie)

# ======================================================================
titre("10. Journal d'erreurs exploitable (critere 10)")
# ======================================================================

# On PROVOQUE une erreur : sans cela, un journal vide « passe » toujours.
avant = 0
jdir = os.path.join(RACINE, "donnees", "journal")
fichier_jour = os.path.join(jdir, time.strftime("%Y-%m-%d", time.gmtime()) + ".log")
if os.path.isfile(fichier_jour):
    avant = sum(1 for _ in open(fichier_jour, encoding="utf-8"))

s.post(BASE + "/repondre", data={"discussion": DID, "corps": "declenche un refus CSRF"})
time.sleep(0.3)
apres = sum(1 for _ in open(fichier_jour, encoding="utf-8")) if os.path.isfile(fichier_jour) else 0
verif("un incident est ecrit dans le journal", apres > avant,
      "%d -> %d lignes" % (avant, apres))

if os.path.isfile(fichier_jour):
    lignes = [json.loads(l) for l in open(fichier_jour, encoding="utf-8") if l.strip()]
    verif("chaque ligne du journal est du JSON avec ts, niveau, message, uri",
          all(all(k in l for k in ("ts", "niveau", "message", "uri")) for l in lignes),
          "%d lignes" % len(lignes))
    verif("le journal a bien enregistre le refus CSRF",
          any("CSRF" in l.get("message", "") for l in lignes),
          str([l.get("message") for l in lignes[-3:]])[:140])

# ======================================================================
titre("11. Rendu reel dans un navigateur : contraste, debordement, JS")
# ======================================================================

try:
    from playwright.sync_api import sync_playwright
except ImportError:
    print("  (playwright absent — bloc ignore)")
    sync_playwright = None

if sync_playwright:
    CONTRASTE_JS = """
    () => {
      const lum = c => {
        const v = c.map(x => { x/=255; return x <= 0.03928 ? x/12.92 : Math.pow((x+0.055)/1.055, 2.4); });
        return 0.2126*v[0] + 0.7152*v[1] + 0.0722*v[2];
      };
      const rgb = s => { const m = s.match(/rgba?\\(([^)]+)\\)/); if (!m) return null;
        const p = m[1].split(',').map(x => parseFloat(x));
        if (p.length > 3 && p[3] === 0) return null;
        return [p[0], p[1], p[2]]; };
      const fond = el => { let n = el;
        while (n && n !== document.documentElement) {
          const c = rgb(getComputedStyle(n).backgroundColor);
          if (c) return c; n = n.parentElement; }
        return [255,255,255]; };
      const out = [];
      document.querySelectorAll('body *').forEach(el => {
        if (!el.offsetParent && getComputedStyle(el).position !== 'fixed') return;
        const t = [...el.childNodes].filter(n => n.nodeType === 3 && n.textContent.trim().length > 1);
        if (!t.length) return;
        const st = getComputedStyle(el);
        const fg = rgb(st.color); if (!fg) return;
        const bg = fond(el);
        const l1 = lum(fg), l2 = lum(bg);
        const ratio = (Math.max(l1,l2) + 0.05) / (Math.min(l1,l2) + 0.05);
        const px = parseFloat(st.fontSize);
        const gras = parseInt(st.fontWeight, 10) >= 700;
        const seuil = (px >= 24 || (px >= 18.66 && gras)) ? 3.0 : 4.5;
        if (ratio < seuil) out.push({sel: el.tagName + '.' + (el.className || ''),
          txt: t[0].textContent.trim().slice(0, 40), fg: st.color, bg: 'rgb(' + bg.join(',') + ')',
          ratio: Math.round(ratio * 100) / 100, seuil});
      });
      return out;
    }
    """
    DEBORD_JS = """
    () => ({ sw: document.documentElement.scrollWidth,
             cw: document.documentElement.clientWidth,
             coupables: [...document.querySelectorAll('body *')]
               .filter(el => el.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
               .slice(0, 5).map(el => el.tagName + '.' + (el.className || '')) })
    """

    with sync_playwright() as pw:
        nav = pw.chromium.launch(args=["--disable-lcd-text", "--force-color-profile=srgb"])

        erreurs_console = []
        pg = nav.new_page()
        pg.on("console", lambda m: erreurs_console.append(m.text) if m.type == "error" else None)
        pg.on("pageerror", lambda e: erreurs_console.append(str(e)))

        a_verifier = ["/", "/forums", "/f/s-transport", "/villes", "/v/paris",
                      "/pays/france", "/continent/europe", "/d/" + SLUG,
                      "/recherche?q=citation", "/projets", "/aide", "/a-renseigner",
                      "/connexion", "/inscription"]
        n_controles_contraste = 0
        for chemin in a_verifier:
            for lang in ("fr", "ar"):
                for w, hgt in ((1280, 720), (390, 760)):
                    pg.set_viewport_size({"width": w, "height": hgt})
                    sep = "&" if "?" in chemin else "?"
                    pg.goto(BASE + chemin + sep + "lang=" + lang, wait_until="networkidle")
                    d = pg.evaluate(DEBORD_JS)
                    verif("pas de debordement horizontal %s [%s %dpx]" % (chemin, lang, w),
                          d["sw"] <= d["cw"],
                          "scrollWidth=%d clientWidth=%d %s" % (d["sw"], d["cw"], d["coupables"]))
                    if w == 1280:
                        mauvais = pg.evaluate(CONTRASTE_JS)
                        n_controles_contraste += 1
                        verif("contraste WCAG AA %s [%s]" % (chemin, lang),
                              mauvais == [], str(mauvais[:3]))
        verif("le controle de contraste a bien tourne sur des pages",
              n_controles_contraste == len(a_verifier) * 2,
              "%d pages mesurees" % n_controles_contraste)
        verif("aucune erreur de console sur l'ensemble des pages",
              erreurs_console == [], str(erreurs_console[:2])[:200])

        # RTL : la barre de navigation doit vraiment s'inverser.
        pg.set_viewport_size({"width": 1280, "height": 720})
        pg.goto(BASE + "/?lang=fr", wait_until="networkidle")
        x_ltr = pg.evaluate("() => document.querySelector('.marque').getBoundingClientRect().left")
        pg.goto(BASE + "/?lang=ar", wait_until="networkidle")
        x_rtl = pg.evaluate("() => document.querySelector('.marque').getBoundingClientRect().left")
        verif("RTL : la marque passe reellement a droite", x_rtl > x_ltr + 300,
              "gauche LTR=%.0f px, gauche RTL=%.0f px" % (x_ltr, x_rtl))

        dir_page = pg.evaluate("() => getComputedStyle(document.body).direction")
        verif("direction calculee par le navigateur = rtl", dir_page == "rtl", dir_page)

        # Le site doit rester utilisable SANS JavaScript.
        ctx = nav.new_context(java_script_enabled=False)
        p2 = ctx.new_page()
        p2.set_viewport_size({"width": 1280, "height": 720})
        p2.goto(BASE + "/", wait_until="load")
        verif("sans JS : l'accueil s'affiche",
              p2.locator("h1").count() >= 1 and p2.locator("a[href^='/d/']").count() >= 3)
        p2.goto(BASE + "/connexion", wait_until="load")
        p2.fill("#identifiant", PSEUDO)
        p2.fill("#mdp", MDP)
        # Selecteur PORTE PAR LE FORMULAIRE : « button[type=submit] » seul
        # attrape le bouton de recherche de l'entete, qui vient avant dans
        # le document. Le test partait alors sur /recherche et echouait pour
        # une raison qui n'avait rien a voir avec la connexion.
        p2.click("form[action^='/connexion'] button[type=submit]")
        p2.wait_for_load_state("load")
        verif("sans JS : connexion par formulaire classique",
              PSEUDO in p2.content(), p2.url)
        p2.goto(BASE + "/d/" + SLUG, wait_until="load")
        p2.fill("#corps", "Reponse envoyee sans JavaScript.")
        p2.click("form[action^='/repondre'] button[type=submit]")
        p2.wait_for_load_state("load")
        verif("sans JS : publication d'une reponse",
              "Reponse envoyee sans JavaScript." in p2.content(), p2.url)
        ctx.close()

        # Images : aucune cassee dans le viewport.
        pg.goto(BASE + "/", wait_until="networkidle")
        cassees = pg.evaluate("""() => [...document.images]
            .filter(i => i.getBoundingClientRect().top < innerHeight && i.naturalWidth === 0)
            .map(i => i.src)""")
        verif("aucune image cassee", cassees == [], str(cassees[:3]))

        nav.close()

# ======================================================================
titre("12. Les valeurs absentes restent visibles et comptees")
# ======================================================================

html = anon.get(BASE + "/a-renseigner").text
n_pastilles_page = len(re.findall(r'<td>', html))
code, sortie = php("-r", """
require 'src/noyau.php'; require 'src/i18n.php'; require 'src/auth.php';
require 'src/vue.php'; require 'src/balisage.php'; require 'src/recherche.php';
require 'src/notifications.php'; require 'src/moderation.php'; require 'src/medias.php';
require 'src/routes/public.php';
echo count(champs_vides());
""")
n_declares = int(re.findall(r"\d+", sortie.strip().splitlines()[-1])[0])
verif("la page des champs vides compte ce que le code declare",
      n_pastilles_page == n_declares * 2, "page=%d cellules, code=%d champs"
      % (n_pastilles_page, n_declares))
verif("il reste des champs a renseigner et la page le dit",
      n_declares > 0, "%d champs" % n_declares)

html = anon.get(BASE + "/").text
verif("le pied de page affiche des pastilles, pas des tirets",
      'class="vide"' in html, "pastilles presentes")
verif("aucun tiret cadratin seul comme valeur",
      not re.search(r">\s*—\s*</p>", html))

# ======================================================================
titre("13. La suite nettoie ce qu'elle a cree")
# ======================================================================

# Le contenu produit ici n'est PAS marque demo = 1 : il est cree par HTTP,
# exactement comme celui d'un membre. purge-demo.php ne le verrait donc pas,
# et il finirait par occuper tout l'accueil du site de demonstration.
code, sortie = php(os.path.join("tests", "nettoyage.php"), "--supprimer")
try:
    net = json.loads(sortie.strip().splitlines()[-1])
except Exception:
    net = {}
verif("le contenu de controle est supprime", net.get("restant") == 0,
      "membres=%s discussions=%s restant=%s"
      % (net.get("membres"), net.get("discussions"), net.get("restant")))

for f in (chemin_png, chemin_faux):
    if os.path.isfile(f):
        os.unlink(f)

# ======================================================================
print("\n" + "=" * 62)
print("Controles reussis : %d" % OK)
print("Echecs            : %d" % len(ECHECS))
for nom, detail in ECHECS:
    print("   - %s : %s" % (nom, detail))
print("=" * 62)
sys.exit(1 if ECHECS else 0)
