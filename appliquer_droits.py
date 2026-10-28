#!/usr/bin/env python3
"""
appliquer_droits.py : branche le système de droits dans vos fichiers (même travail que installer_droits.php,
mais lancé depuis le terminal avec VOTRE utilisateur, donc sans problème de droits Apache).

    cd /opt/lampp/htdocs/film_web_dy
    python3 appliquer_droits.py

Une copie « .bak_droits » est créée à côté de chaque fichier modifié. Relancer le script est sans danger.
"""
import os, re, shutil, sys

ROOT = os.path.dirname(os.path.abspath(__file__))
if os.path.basename(ROOT) in ("user", "admin", "includes") and os.path.isfile(os.path.join(os.path.dirname(ROOT), "login.php")):
    ROOT = os.path.dirname(ROOT)

AUTH = re.compile(r"""require_once\s+__DIR__\s*\.\s*['"]/\.\./includes/auth_check\.php['"]\s*;""")

def after_auth(file, perm):
    return dict(file=file, marker=f"require_permission('{perm}')", desc=f"droit « {perm} »", regex=AUTH,
                repl=lambda m, perm=perm: m.group(0) + f"\nrequire_once __DIR__ . '/../includes/permissions.php';\nrequire_permission('{perm}');")

def plain(file, marker, desc, a, b):
    return dict(file=file, marker=marker, desc=desc, plain=(a, b))

PATCHES = [
    after_auth("admin/rapports.php", "voir_rapports"),
    after_auth("admin/utilisateurs.php", "gerer_utilisateurs"),
    after_auth("admin/parametres.php", "modifier_parametres"),
    after_auth("admin/commentaires.php", "gerer_commentaires"),
    after_auth("admin/corbeille.php", "gerer_corbeille"),
    after_auth("admin/historique_actions.php", "voir_historique"),
    dict(file="admin/parametres.php", marker='href="permissions.php"', desc="lien « Droits d'accès » dans les Paramètres",
         regex=re.compile(r'(<i class="fas fa-code-branch"></i> Versions <b><\?= count\(\$filmsV\) \?></b>\s*</button>)'),
         repl=lambda m: m.group(1) + '\n            <a class="hx-tab" href="permissions.php" style="--c:#ef4444;text-decoration:none;" onclick="event.stopPropagation()">\n                <i class="fas fa-user-lock"></i> Droits d\'accès\n            </a>'),
    after_auth("user/watch.php", "voir_films"),
    after_auth("user/modifier_profil.php", "modifier_profil"),
    plain("user/watch.php", "&& can('aimer')", "droit « aimer »",
          "if (isset($_POST['toggle_like'])) {", "if (isset($_POST['toggle_like']) && can('aimer')) {"),
    plain("user/watch.php", "&& can('commenter')) {", "droit « commenter » (enregistrement)",
          "if (isset($_POST['ajouter_feedback']) && !empty($_POST['commentaire'])) {",
          "if (isset($_POST['ajouter_feedback']) && !empty($_POST['commentaire']) && can('commenter')) {"),
    plain("user/watch.php", "commentaires sont désactivés", "droit « commenter » (message + formulaire masqué)",
          '<form method="POST" class="watch-fb-form">',
          "<?php if (!can('commenter')): ?>\n                    <div class=\"fb-error\"><i class=\"fas fa-ban\"></i> Les commentaires sont désactivés pour votre compte.</div>\n                <?php endif; ?>\n                <form method=\"POST\" class=\"watch-fb-form\"<?= can('commenter') ? '' : ' style=\"display:none\"' ?>>"),
    plain("user/ajax_favori.php", "can('gerer_favoris')", "droit « gérer ses favoris »",
          "$user_id = $_SESSION['user_id'];",
          "$user_id = $_SESSION['user_id'];\n\nrequire_once __DIR__ . '/../includes/permissions.php';\nif (!can('gerer_favoris')) {\n    echo json_encode(['success' => false, 'message' => \"Action non autorisée sur votre compte\"]);\n    exit;\n}"),
    plain("user/support_api.php", "can('support_ecrire')", "droit « écrire au support »",
          "        support_json(['message' => support_do_send($pdo, $uid, 'user', null, 8)]);",
          "        require_once __DIR__ . '/../includes/permissions.php';\n        if (!can('support_ecrire')) {\n            support_json(['error' => \"L'envoi de messages est désactivé pour votre compte.\"], 403);\n        }\n        support_json(['message' => support_do_send($pdo, $uid, 'user', null, 8)]);"),
]

print("Dossier du site :", ROOT)
for need in ("includes/permissions.php", "admin/permissions.php"):
    if not os.path.isfile(os.path.join(ROOT, need)):
        print(f"ERREUR  {need} est manquant : copiez-le d'abord"); sys.exit(1)

by_file = {}
for p in PATCHES:
    by_file.setdefault(p["file"], []).append(p)

problemes = 0
for f, lst in by_file.items():
    path = os.path.join(ROOT, f)
    if not os.path.isfile(path):
        print(f"IGNORÉ        {f:32} fichier introuvable"); continue
    with open(path, encoding="utf-8", newline="") as fh:
        src = fh.read()
    new, ajoutes = src, []
    for p in lst:
        if p["marker"] in new:
            print(f"DÉJÀ FAIT     {f:32} {p['desc']}"); continue
        if "regex" in p:
            n = len(p["regex"].findall(new))
            if n != 1:
                print(f"À LA MAIN     {f:32} {p['desc']} : repère trouvé {n} fois (attendu 1)"); problemes += 1; continue
            new = p["regex"].sub(p["repl"], new, count=1)
        else:
            a, b = p["plain"]
            if new.count(a) != 1:
                print(f"À LA MAIN     {f:32} {p['desc']} : repère trouvé {new.count(a)} fois (attendu 1)"); problemes += 1; continue
            new = new.replace(a, b, 1)
        ajoutes.append(p)
        print(f"AJOUTÉ        {f:32} {p['desc']}")
    if new != src:
        try:
            if not os.path.isfile(path + ".bak_droits"):
                shutil.copy2(path, path + ".bak_droits")
            with open(path, "w", encoding="utf-8", newline="") as fh:
                fh.write(new)
            with open(path, encoding="utf-8", newline="") as fh:
                relu = fh.read()
            for p in ajoutes:
                if p["marker"] not in relu:
                    raise OSError("relecture : modification absente")
        except OSError as e:
            problemes += 1
            print(f"ÉCHEC         {f:32} {e}  -> relancez avec : sudo python3 appliquer_droits.py")

print("\nTerminé" + (f" avec {problemes} problème(s)." if problemes else " : tout est en place."))
sys.exit(1 if problemes else 0)
