#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Captures d'ecran.

Contrainte respectee partout : jamais full_page, et la fenetre est reglee
AVANT la capture. Une page longue capturee entierement produit une image de
plusieurs milliers de pixels de haut, que l'API de messagerie refuse.

    python3 tests/captures.py [http://127.0.0.1:8830]
"""

import os
import sys
import subprocess

from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8830").rstrip("/")
RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEST = os.path.join(RACINE, "apercus")
os.makedirs(DEST, exist_ok=True)


def slug_demo(motif):
    r = subprocess.run(
        ["php", "-r",
         "require 'src/noyau.php'; echo (string) qval("
         "'SELECT slug FROM discussions WHERE titre LIKE ? ORDER BY id LIMIT 1', ['%s%%']);" % motif],
        cwd=RACINE, capture_output=True, text=True)
    return r.stdout.strip()


def slug_article(motif):
    r = subprocess.run(
        ["php", "-r",
         "require 'src/noyau.php'; echo (string) qval("
         "'SELECT slug FROM articles WHERE titre LIKE ? AND statut = ? ORDER BY id LIMIT 1', "
         "['%s%%', 'publie']);" % motif],
        cwd=RACINE, capture_output=True, text=True)
    return r.stdout.strip()


DISC = slug_demo("Comment écrire un message")
DISC_AR = slug_demo("المشاركة بالعربية")
ART = slug_article("Ce que ce portail publie")
ART2 = slug_article("Comment proposer un article")
ART_AR = slug_article("ما تنشره")

# nom, chemin, largeur, hauteur, defilement (px)
# Le portail vient en tete : c'est la premiere page que voit un visiteur.
VUES = [
    ("uf-01-portail",        "/",                          1280, 720, 0),
    ("uf-02-portail-une",    "/",                          1280, 720, 620),
    ("uf-03-portail-rub",    "/",                          1280, 720, 1500),
    ("uf-04-actualites",     "/actualites",                1280, 720, 0),
    ("uf-05-rubrique",       "/r/projets",                 1280, 720, 0),
    ("uf-06-article",        "/a/" + ART,                  1280, 720, 0),
    ("uf-07-article-corps",  "/a/" + ART,                  1280, 720, 700),
    ("uf-08-article-sources", "/a/" + ART,                 1280, 720, 1500),
    ("uf-09-article-2",      "/a/" + ART2,                 1280, 720, 0),
    ("uf-10-portail-arabe",  "/?lang=ar",                  1280, 720, 0),
    ("uf-11-article-arabe",  "/a/" + ART_AR + "?lang=ar",  1280, 720, 0),
    ("uf-12-portail-anglais", "/?lang=en",                 1280, 720, 0),
    ("uf-13-recherche-portail", "/recherche?q=source&espace=portail", 1280, 720, 0),
    ("uf-14-communaute",     "/communaute",                1280, 720, 0),
    ("uf-15-forums",         "/forums",                    1280, 720, 120),
    ("uf-16-forum-ville",    "/f/v-alger",                 1280, 720, 0),
    ("uf-17-discussion",     "/d/" + DISC,                 1280, 720, 0),
    ("uf-18-discussion-bas", "/d/" + DISC,                 1280, 720, 1400),
    ("uf-19-recherche",      "/recherche?q=citation",      1280, 720, 0),
    ("uf-20-villes",         "/villes",                    1280, 720, 0),
    ("uf-21-pays",           "/pays/algerie",              1280, 720, 0),
    ("uf-22-projets",        "/projets",                   1280, 720, 0),
    ("uf-23-arabe-message",  "/d/" + DISC_AR + "?lang=ar", 1280, 720, 0),
    ("uf-24-a-renseigner",   "/a-renseigner",              1280, 720, 0),
    ("uf-25-inscription",    "/inscription",               1280, 720, 0),
    ("uf-26-mobile-portail", "/",                           390, 760, 0),
    ("uf-27-mobile-article", "/a/" + ART,                   390, 760, 200),
    ("uf-28-mobile-disc",    "/d/" + DISC,                  390, 760, 300),
    ("uf-29-mobile-arabe",   "/?lang=ar",                   390, 760, 0),
]

# Pages qui exigent un compte : on ouvre une session dans le navigateur.
CONNECTE = [
    ("uf-30-gestion-portail", "/admin/articles",           1280, 720, 0),
    ("uf-31-redaction",      "/admin/articles/nouveau",    1280, 720, 0),
    ("uf-32-redaction-bas",  "/admin/articles/nouveau",    1280, 720, 700),
    ("uf-33-moderation",     "/moderation",                1280, 720, 0),
    ("uf-34-journal-mod",    "/moderation/journal",        1280, 720, 0),
    ("uf-35-admin",          "/admin",                     1280, 720, 0),
    ("uf-36-admin-bas",      "/admin",                     1280, 720, 700),
    ("uf-37-taxonomie",      "/admin/taxonomie",           1280, 720, 0),
    ("uf-38-permissions",    "/admin/permissions",         1280, 720, 0),
    ("uf-39-nouvelle",       "/nouvelle-discussion",       1280, 720, 0),
    ("uf-40-notifications",  "/notifications",             1280, 720, 0),
]

MDP_ADMIN = os.environ.get("UF_ADMIN_MDP", "")


def tirer(pg, nom, chemin, w, h, defil):
    pg.set_viewport_size({"width": w, "height": h})
    pg.goto(BASE + chemin, wait_until="networkidle")
    if defil:
        pg.evaluate("y => window.scrollTo(0, y)", defil)
        pg.wait_for_timeout(250)
    chemin_img = os.path.join(DEST, nom + ".png")
    pg.screenshot(path=chemin_img)          # jamais full_page
    print("  %-22s %s" % (nom, chemin_img))


with sync_playwright() as pw:
    nav = pw.chromium.launch(args=["--disable-lcd-text", "--force-color-profile=srgb"])
    pg = nav.new_page()
    for v in VUES:
        tirer(pg, *v)

    if MDP_ADMIN:
        pg.goto(BASE + "/connexion", wait_until="networkidle")
        pg.fill("#identifiant", "admin")
        pg.fill("#mdp", MDP_ADMIN)
        pg.click("form[action^='/connexion'] button[type=submit]")
        pg.wait_for_load_state("networkidle")
        for v in CONNECTE:
            tirer(pg, *v)
    else:
        print("  (UF_ADMIN_MDP absent : pages d'administration non capturees)")
    nav.close()

# Verification : aucune image ne doit depasser 2000 px, dans aucune dimension.
try:
    from PIL import Image
    trop = []
    for f in sorted(os.listdir(DEST)):
        if not f.endswith(".png"):
            continue
        w, h = Image.open(os.path.join(DEST, f)).size
        if w > 2000 or h > 2000:
            trop.append((f, w, h))
    print("\nImages : %d" % len([f for f in os.listdir(DEST) if f.endswith('.png')]))
    if trop:
        print("HORS LIMITE : %s" % trop)
        sys.exit(1)
    print("Toutes sous 2000 px dans les deux dimensions.")
except ImportError:
    print("(Pillow absent : dimensions non verifiees)")
