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


DISC = slug_demo("Comment écrire un message")
DISC_AR = slug_demo("المشاركة بالعربية")

# nom, chemin, largeur, hauteur, defilement (px)
VUES = [
    ("uf-01-accueil",        "/",                          1280, 720, 0),
    ("uf-02-accueil-monde",  "/",                          1280, 720, 900),
    ("uf-03-forums",         "/forums",                    1280, 720, 120),
    ("uf-04-forum-ville",    "/f/v-alger",                 1280, 720, 0),
    ("uf-05-discussion",     "/d/" + DISC,                 1280, 720, 0),
    ("uf-06-discussion-bas", "/d/" + DISC,                 1280, 720, 1400),
    ("uf-07-recherche",      "/recherche?q=citation",      1280, 720, 0),
    ("uf-08-villes",         "/villes",                    1280, 720, 0),
    ("uf-09-pays",           "/pays/algerie",              1280, 720, 0),
    ("uf-10-projets",        "/projets",                   1280, 720, 0),
    ("uf-11-arabe-rtl",      "/?lang=ar",                  1280, 720, 0),
    ("uf-12-arabe-message",  "/d/" + DISC_AR + "?lang=ar", 1280, 720, 0),
    ("uf-13-anglais",        "/forums?lang=en",            1280, 720, 0),
    ("uf-14-a-renseigner",   "/a-renseigner",              1280, 720, 0),
    ("uf-15-inscription",    "/inscription",               1280, 720, 0),
    ("uf-16-mobile",         "/",                           390, 760, 0),
    ("uf-17-mobile-disc",    "/d/" + DISC,                  390, 760, 300),
    ("uf-18-mobile-arabe",   "/?lang=ar",                   390, 760, 0),
]

# Pages qui exigent un compte : on ouvre une session dans le navigateur.
CONNECTE = [
    ("uf-19-moderation",     "/moderation",                1280, 720, 0),
    ("uf-20-journal-mod",    "/moderation/journal",        1280, 720, 0),
    ("uf-21-admin",          "/admin",                     1280, 720, 0),
    ("uf-22-admin-bas",      "/admin",                     1280, 720, 700),
    ("uf-23-taxonomie",      "/admin/taxonomie",           1280, 720, 0),
    ("uf-24-permissions",    "/admin/permissions",         1280, 720, 0),
    ("uf-25-nouvelle",       "/nouvelle-discussion",       1280, 720, 0),
    ("uf-26-notifications",  "/notifications",             1280, 720, 0),
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
