<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/film_web_dy/user/css/style-user.css?v=2">
<link rel="stylesheet" href="/film_web_dy/user/css/mobile-user.css?v=4">

<nav class="navbar">
    <!-- Logo -->
    <a href="/film_web_dy/user/index.php" class="logo">
        <i class="fas fa-film"></i>
        BERTIN <span>MOVIE</span>
    </a>

    <!-- Menu Horizontal -->
    <div class="nav-menu">
        <a href="/film_web_dy/user/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"
            data-lang-fr="Accueil" data-lang-en="Home">
            <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="/film_web_dy/user/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>"
            data-lang-fr="Vidéos" data-lang-en="Videos">
            <i class="fas fa-video"></i> <span>Vidéos</span>
        </a>
        <a href="/film_web_dy/user/favoris.php" class="<?= $current_page == 'favoris.php' ? 'active' : '' ?>"
            data-lang-fr="Favoris" data-lang-en="Favorites">
            <i class="fas fa-heart"></i> <span>Favoris</span>
        </a>
        <a href="/film_web_dy/user/historique.php" class="<?= $current_page == 'historique.php' ? 'active' : '' ?>"
            data-lang-fr="Historique" data-lang-en="History">
            <i class="fas fa-history"></i> <span>Historique</span>
        </a>
        <a href="/film_web_dy/user/profil.php" class="<?= $current_page == 'profil.php' ? 'active' : '' ?>"
            data-lang-fr="Profil" data-lang-en="Profile">
            <i class="fas fa-user"></i> <span>Profil</span>
        </a>
    </div>

    <!-- Actions -->
    <div class="nav-actions">
        <!-- 🤖 ASSISTANT IA (l'infobulle est traduite automatiquement par chat-widget.php) -->
        <button class="nav-btn" id="chatNavBtn" onclick="bmChatToggle()" title="Assistant IA" aria-expanded="false">
            <i class="fas fa-robot"></i>
        </button>

        <a href="/film_web_dy/user/video.php#search" class="nav-btn nav-search" id="navSearchBtn"
            onclick="return bmOpenSearch(event)" aria-label="Rechercher" title="Rechercher">
            <i class="fas fa-search"></i>
        </a>

        <button class="nav-btn" id="langBtn" onclick="toggleLanguage()" title="Changer de langue">
            <i class="fas fa-globe"></i>
            <span id="langLabel">FR</span>
        </button>

        <button class="nav-btn" onclick="toggleTheme()" title="Changer de thème">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <span class="nav-divider"></span>

        <a href="#" class="nav-logout" onclick="confirmLogout(event)" data-lang-fr="Déconnexion" data-lang-en="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </a>

        <!-- BURGER - TOUJOURS VISIBLE -->
        <div class="nav-burger" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</nav>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeMenu()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-side">BERTIN <span>MOVIE</span></div>
        <div class="close-btn" onclick="closeMenu()">✕</div>
    </div>
    <?php
    $__sb_nom = trim((string) ($_SESSION['user_nom'] ?? 'Utilisateur'));
    if ($__sb_nom === '') {
        $__sb_nom = 'Utilisateur';
    }
    $__sb_init = function_exists('mb_substr') ? mb_strtoupper(mb_substr($__sb_nom, 0, 1)) : strtoupper(substr($__sb_nom, 0, 1));
    ?>
    <a href="/film_web_dy/user/profil.php" class="sidebar-user">
        <span class="su-avatar"><?= htmlspecialchars($__sb_init) ?></span>
        <span class="su-text">
            <strong><?= htmlspecialchars($__sb_nom) ?></strong>
            <small data-lang-fr="Voir mon profil" data-lang-en="View my profile">Voir mon profil</small>
        </span>
        <i class="fas fa-chevron-right"></i>
    </a>
    <div class="sidebar-body">
        <!-- SECTION PRINCIPALE -->
        <span class="section-label" data-lang-fr="Principal" data-lang-en="Main">Principal</span>
        <a href="/film_web_dy/user/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"
            data-lang-fr="Accueil" data-lang-en="Home">
            <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="/film_web_dy/user/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>"
            data-lang-fr="Vidéos" data-lang-en="Videos">
            <i class="fas fa-video"></i> <span>Vidéos</span>
        </a>
        <a href="/film_web_dy/user/favoris.php" class="<?= $current_page == 'favoris.php' ? 'active' : '' ?>"
            data-lang-fr="Favoris" data-lang-en="Favorites">
            <i class="fas fa-heart"></i> <span>Favoris</span>
        </a>
        <a href="/film_web_dy/user/historique.php" class="<?= $current_page == 'historique.php' ? 'active' : '' ?>"
            data-lang-fr="Historique" data-lang-en="History">
            <i class="fas fa-history"></i> <span>Historique</span>
        </a>
        <!-- Messages : ouvre le chat avec l'administrateur -->
        <button class="sidebar-link" type="button"
            onclick="closeMenu(); if (window.bmsOpenSupport) { bmsOpenSupport(); }">
            <i class="fas fa-comments"></i>
            <span data-lang-fr="Messages" data-lang-en="Messages">Messages</span>
            <span class="sb-msg-badge" id="sbMsgBadge">0</span>
        </button>

        <div class="section-divider"></div>

        <!-- SECTION PROFIL -->
        <span class="section-label" data-lang-fr="Profil" data-lang-en="Profile">Profil</span>
        <a href="/film_web_dy/user/profil.php" data-lang-fr="Voir profil" data-lang-en="View profile">
            <i class="fas fa-id-card"></i> <span>Voir profil</span>
        </a>
        <a href="/film_web_dy/user/modifier_profil.php" data-lang-fr="Modifier profil" data-lang-en="Edit profile">
            <i class="fas fa-edit"></i> <span>Modifier profil</span>
        </a>
        <a href="/film_web_dy/user/modifier_motdepasse.php" data-lang-fr="Modifier mot de passe"
            data-lang-en="Change password">
            <i class="fas fa-key"></i> <span>Modifier mot de passe</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION SYSTÈME -->
        <span class="section-label" data-lang-fr="Système" data-lang-en="System">Système</span>

        <!-- Assistant IA -->
        <button class="sidebar-link" onclick="bmChatToggle(); closeMenu();">
            <i class="fas fa-robot"></i>
            <span data-lang-fr="Assistant IA" data-lang-en="AI Assistant">Assistant IA</span>
        </button>

        <!-- À propos -->
        <button class="sidebar-link" type="button" onclick="closeMenu(); bmAboutOpen();">
            <i class="fas fa-circle-info"></i>
            <span data-lang-fr="À propos" data-lang-en="About us">À propos</span>
        </button>

        <!-- Langue -->
        <button class="sidebar-link" onclick="toggleLanguage(); closeMenu();">
            <i class="fas fa-globe"></i>
            <span data-lang-fr="Changer de langue" data-lang-en="Change language">Changer de langue</span>
            <span class="lang-badge" id="langLabelSidebar">FR</span>
        </button>

        <!-- Dark Mode -->
        <button class="sidebar-link" onclick="toggleTheme(); closeMenu();">
            <i class="fas fa-moon" id="themeIconSidebar"></i>
            <span id="themeLabel" data-lang-fr="Mode sombre" data-lang-en="Dark mode">Mode sombre</span>
        </button>

        <div class="section-divider"></div>

        <!-- Déconnexion -->
        <a href="#" class="logout" onclick="confirmLogout(event)" data-lang-fr="Déconnexion" data-lang-en="Logout">
            <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
        </a>
    </div>
</div>

<!-- =========================================================
   À PROPOS (ouvert depuis le menu burger)
========================================================= -->
<style>
    .sidebar .sidebar-body .sb-msg-badge {
        margin-left: auto;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 11px;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        background: #ef4444;
    }

    .bmab-modal {
        position: fixed;
        inset: 0;
        z-index: 9600;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(2, 6, 23, .68);
        backdrop-filter: blur(6px);
    }

    .bmab-modal.open {
        display: flex;
        animation: bmabFade .2s ease;
    }

    .bmab-box {
        position: relative;
        width: 100%;
        max-width: 440px;
        max-height: 92vh;
        overflow-y: auto;
        padding: 30px 26px 26px;
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 26px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
        animation: bmabPop .3s cubic-bezier(.2, 1.3, .4, 1);
    }

    .bmab-x {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 50%;
        cursor: pointer;
        color: var(--text-secondary);
        background: rgba(148, 163, 184, .14);
        transition: all .25s ease;
    }

    .bmab-x:hover {
        background: #ef4444;
        color: #fff;
    }

    .bmab-head {
        text-align: center;
        margin-bottom: 20px;
    }

    .bmab-logo {
        width: 64px;
        height: 64px;
        margin: 0 auto 12px;
        border-radius: 20px;
        display: grid;
        place-items: center;
        font-size: 26px;
        color: #fff;
        background: var(--primary-gradient);
        box-shadow: 0 10px 28px rgba(59, 130, 246, .4);
    }

    .bmab-head h3 {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.3px;
    }

    .bmab-head h3 span {
        color: var(--primary-light);
    }

    .bmab-head p {
        margin-top: 4px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .bmab-dev {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        margin-bottom: 14px;
        border-radius: 18px;
        background: rgba(59, 130, 246, .08);
        border: 1px solid rgba(59, 130, 246, .2);
    }

    .bmab-avatar {
        flex: 0 0 52px;
        height: 52px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 22px;
        font-weight: 800;
        color: #fff;
        background: var(--primary-gradient);
    }

    .bmab-dev small {
        display: block;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .bmab-dev strong {
        display: block;
        font-size: 18px;
        font-weight: 800;
        margin: 1px 0;
    }

    .bmab-dev .role {
        font-size: 13px;
        font-weight: 600;
        color: var(--primary-light);
    }

    .bmab-rows {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 14px;
    }

    .bmab-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        color: var(--text-primary);
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        transition: all .25s ease;
    }

    .bmab-row:hover {
        transform: translateX(4px);
        border-color: var(--primary-light);
    }

    .bmab-row .ic {
        flex: 0 0 38px;
        height: 38px;
        border-radius: 11px;
        display: grid;
        place-items: center;
        font-size: 18px;
        color: #fff;
    }

    .bmab-row .ic.gh {
        background: #24292f;
    }

    .bmab-row .ic.fb {
        background: #1877f2;
    }

    .bmab-row .ic.wa {
        background: #25d366;
    }

    .bmab-row small {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: var(--text-muted);
        font-weight: 700;
    }

    .bmab-row b {
        display: block;
        font-size: 15px;
        font-weight: 700;
        word-break: break-all;
    }

    .bmab-row .go {
        margin-left: auto;
        font-size: 12px;
        color: var(--text-muted);
    }

    .bmab-help {
        padding: 16px;
        border-radius: 18px;
        text-align: center;
        background: rgba(34, 197, 94, .08);
        border: 1px solid rgba(34, 197, 94, .28);
    }

    .bmab-help .heart {
        font-size: 22px;
        color: #ef4444;
        margin-bottom: 6px;
    }

    .bmab-help p {
        font-size: 14px;
        line-height: 1.55;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .bmab-wa {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        padding: 12px;
        border-radius: 13px;
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        background: linear-gradient(135deg, #25d366, #128c7e);
        box-shadow: 0 8px 22px rgba(37, 211, 102, .32);
        transition: all .25s ease;
    }

    .bmab-wa:hover {
        transform: translateY(-2px);
    }

    @keyframes bmabFade {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes bmabPop {
        from {
            opacity: 0;
            transform: scale(.9) translateY(10px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }
</style>

<div class="bmab-modal" id="bmAbout" role="dialog" aria-modal="true" aria-labelledby="bmAboutTitle"
    onclick="if (event.target === this) { bmAboutClose(); }">
    <div class="bmab-box">
        <button type="button" class="bmab-x" onclick="bmAboutClose()" aria-label="Fermer"><i
                class="fas fa-xmark"></i></button>

        <div class="bmab-head">
            <div class="bmab-logo"><i class="fas fa-film"></i></div>
            <h3 id="bmAboutTitle">BERTIN <span>MOVIE</span></h3>
            <p data-lang-fr="À propos de nous" data-lang-en="About us">À propos de nous</p>
        </div>

        <div class="bmab-dev">
            <div class="bmab-avatar">O</div>
            <div>
                <small data-lang-fr="BERTIN MOVIE est développé par" data-lang-en="BERTIN MOVIE is developed by">BERTIN
                    MOVIE est développé par</small>
                <strong>Orly Bertin</strong>
                <span class="role" data-lang-fr="Ingénieur logiciel" data-lang-en="Software Engineer">Ingénieur
                    logiciel</span>
            </div>
        </div>

        <div class="bmab-rows">
            <a class="bmab-row" href="https://github.com/iam-orly-bertin" target="_blank" rel="noopener noreferrer">
                <span class="ic gh"><i class="fab fa-github"></i></span>
                <span><small>GitHub</small><b>iam-orly-bertin</b></span>
                <i class="fas fa-arrow-up-right-from-square go"></i>
            </a>
            <a class="bmab-row" href="https://www.facebook.com/search/top?q=oly%20berthin" target="_blank"
                rel="noopener noreferrer">
                <span class="ic fb"><i class="fab fa-facebook-f"></i></span>
                <span><small>Facebook</small><b>oly berthin</b></span>
                <i class="fas fa-arrow-up-right-from-square go"></i>
            </a>
            <a class="bmab-row" href="https://wa.me/25767846547" target="_blank" rel="noopener noreferrer">
                <span class="ic wa"><i class="fab fa-whatsapp"></i></span>
                <span><small>WhatsApp</small><b>67846547</b></span>
                <i class="fas fa-arrow-up-right-from-square go"></i>
            </a>
        </div>

        <div class="bmab-help">
            <div class="heart"><i class="fas fa-heart"></i></div>
            <p data-lang-fr="Vous voulez m'aider ? Vous pouvez me soutenir en m'offrant des abonnements. Contactez-moi sur WhatsApp."
                data-lang-en="Want to help me? You can support me by giving me subscriptions. Contact me on WhatsApp.">
                Vous voulez m'aider ? Vous pouvez me soutenir en m'offrant des abonnements. Contactez-moi sur WhatsApp.
            </p>
            <a class="bmab-wa"
                href="https://wa.me/25767846547?text=Bonjour%20Orly%2C%20je%20viens%20de%20BERTIN%20MOVIE%20et%20je%20voudrais%20vous%20aider."
                target="_blank" rel="noopener noreferrer">
                <i class="fab fa-whatsapp"></i>
                <span data-lang-fr="Me contacter sur WhatsApp" data-lang-en="Contact me on WhatsApp">Me contacter sur
                    WhatsApp</span>
            </a>
        </div>
    </div>
</div>

<script>
    function bmAboutOpen() { document.getElementById('bmAbout').classList.add('open'); }
    function bmAboutClose() { document.getElementById('bmAbout').classList.remove('open'); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { bmAboutClose(); } });

    // Pastille de messages non lus : recopie celle du chat support dans le menu burger
    document.addEventListener('DOMContentLoaded', function () {
        var src = document.getElementById('bmsBadge'), dst = document.getElementById('sbMsgBadge');
        if (!src || !dst) { return; }
        function sync() {
            var n = (src.textContent || '').trim();
            var show = src.style.display !== 'none' && n !== '' && n !== '0';
            dst.textContent = n;
            dst.style.display = show ? 'inline-flex' : 'none';
        }
        new MutationObserver(sync).observe(src, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['style'] });
        sync();
    });
</script>

<!-- =========================================================
   BOTTOM NAV - MOBILE UNIQUEMENT (4 MENUS + ASSISTANT)
========================================================= -->
<nav class="bottom-nav" id="bottomNav">
    <a href="/film_web_dy/user/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"
        data-lang-fr="Accueil" data-lang-en="Home">
        <i class="fas fa-home"></i>
        <span class="nav-label" data-lang-fr="Accueil" data-lang-en="Home">Accueil</span>
    </a>
    <a href="/film_web_dy/user/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>"
        data-lang-fr="Vidéos" data-lang-en="Videos">
        <i class="fas fa-video"></i>
        <span class="nav-label" data-lang-fr="Vidéos" data-lang-en="Videos">Vidéos</span>
    </a>
    <a href="/film_web_dy/user/favoris.php" class="<?= $current_page == 'favoris.php' ? 'active' : '' ?>"
        data-lang-fr="Favoris" data-lang-en="Favorites">
        <i class="fas fa-heart"></i>
        <span class="nav-label" data-lang-fr="Favoris" data-lang-en="Favorites">Favoris</span>
    </a>
    <a href="#" onclick="event.preventDefault(); bmChatToggle();" data-lang-fr="Assistant" data-lang-en="Assistant">
        <i class="fas fa-robot"></i>
        <span class="nav-label" data-lang-fr="Assistant" data-lang-en="Assistant">Assistant</span>
    </a>
    <a href="/film_web_dy/user/profil.php" class="<?= $current_page == 'profil.php' ? 'active' : '' ?>"
        data-lang-fr="Profil" data-lang-en="Profile">
        <i class="fas fa-user"></i>
        <span class="nav-label" data-lang-fr="Profil" data-lang-en="Profile">Profil</span>
    </a>
</nav>

<script>
    // ============================================
    // TRADUCTIONS COMPLÈTES
    // ============================================
    const translations = {
        fr: {
            // ===== MENU =====
            'Accueil': 'Accueil',
            'Vidéos': 'Vidéos',
            'Favoris': 'Favoris',
            'Historique': 'Historique',
            'Profil': 'Profil',
            'Déconnexion': 'Déconnexion',
            'Home': 'Accueil',
            'Videos': 'Vidéos',
            'Favorites': 'Favoris',
            'History': 'Historique',
            'Profile': 'Profil',
            'Logout': 'Déconnexion',
            'View profile': 'Voir profil',
            'Edit profile': 'Modifier profil',
            'Change password': 'Modifier mot de passe',
            'Dark mode': 'Mode sombre',
            'Mode sombre': 'Mode sombre',
            'Change language': 'Changer de langue',
            'Changer de langue': 'Changer de langue',
            'Principal': 'Principal',
            'Main': 'Principal',
            'Profile': 'Profil',
            'System': 'Système',
            'Système': 'Système',
            'Assistant IA': 'Assistant IA',
            'AI Assistant': 'Assistant IA',
            'Assistant': 'Assistant',

            // ===== INDEX.PHP =====
            'Toutes les vidéos': 'Toutes les vidéos',
            'All videos': 'Toutes les vidéos',
            'Total': 'Total',
            'Vues totales': 'Vues totales',
            'Dernière mise à jour': 'Dernière mise à jour',
            'Rechercher': 'Rechercher',
            'Search': 'Rechercher',
            'Rechercher un film...': 'Rechercher un film...',
            'Search for a movie...': 'Rechercher un film...',
            'Toutes les catégories': 'Toutes les catégories',
            'All categories': 'Toutes les catégories',
            'vidéos': 'vidéos',
            'videos': 'vidéos',
            'Regarder': 'Regarder',
            'Watch': 'Regarder',
            'Aucune vidéo disponible': 'Aucune vidéo disponible',
            'No video available': 'Aucune vidéo disponible',
            'Revenez plus tard pour découvrir de nouveaux films.': 'Revenez plus tard pour découvrir de nouveaux films.',
            'Come back later to discover new movies.': 'Revenez plus tard pour découvrir de nouveaux films.',
            'Total views': 'Vues totales',
            'Last update': 'Dernière mise à jour',
            'Résultats pour': 'Résultats pour',
            'Results for': 'Résultats pour',
            'film(s) trouvé(s)': 'film(s) trouvé(s)',
            'film(s) found': 'film(s) trouvé(s)',
            'Aucun résultat trouvé': 'Aucun résultat trouvé',
            'No results found': 'Aucun résultat trouvé',
            'Aucun film ne correspond à votre recherche.': 'Aucun film ne correspond à votre recherche.',
            'No movie matches your search.': 'Aucun film ne correspond à votre recherche.',

            // ===== FAVORIS.PHP =====
            'Mes favoris': 'Mes favoris',
            'My favorites': 'Mes favoris',
            'Aucun favori': 'Aucun favori',
            'No favorites': 'Aucun favori',
            'Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo.': 'Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo.',
            'Add videos to your favorites by clicking the ❤️ on a video.': 'Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo.',
            'Découvrir des vidéos': 'Découvrir des vidéos',
            'Discover videos': 'Découvrir des vidéos',
            'Retirer des favoris': 'Retirer des favoris',
            'Remove from favorites': 'Retirer des favoris',
            'Retirer cette vidéo de vos favoris ?': 'Retirer cette vidéo de vos favoris ?',
            'Remove this video from your favorites?': 'Retirer cette vidéo de vos favoris ?',

            // ===== HISTORIQUE.PHP =====
            'Mon historique': 'Mon historique',
            'My history': 'Mon historique',
            'Aucun historique': 'Aucun historique',
            'No history': 'Aucun historique',
            'Commencez à regarder des films pour remplir votre historique.': 'Commencez à regarder des films pour remplir votre historique.',
            'Start watching movies to fill your history.': 'Commencez à regarder des films pour remplir votre historique.',
            'Reprendre': 'Reprendre',
            'Resume': 'Reprendre',
            'Vu': 'Vu',
            'Watched': 'Vu',

            // ===== PROFIL.PHP =====
            'Mon profil': 'Mon profil',
            'My profile': 'Mon profil',
            'Membre': 'Membre',
            'Member': 'Membre',
            'Admin': 'Admin',
            'Membre depuis': 'Membre depuis',
            'Member since': 'Membre depuis',
            'Modifier': 'Modifier',
            'Edit': 'Modifier',
            'Sécurité': 'Sécurité',
            'Security': 'Sécurité',
            'Quitter': 'Quitter',
            'Leave': 'Quitter',
            'Masculin': 'Masculin',
            'Féminin': 'Féminin',
            'Non renseigné': 'Non renseigné',
            'Not specified': 'Non renseigné',
            'Non renseignée': 'Non renseignée',
            'Not specified': 'Non renseignée',

            // ===== MODIFIER PROFIL =====
            'Modifier mon profil': 'Modifier mon profil',
            'Edit my profile': 'Modifier mon profil',
            'Nom d\'utilisateur': 'Nom d\'utilisateur',
            'Username': 'Nom d\'utilisateur',
            'Prénom': 'Prénom',
            'First name': 'Prénom',
            'Nom': 'Nom',
            'Last name': 'Nom',
            'Email': 'Email',
            'Sexe': 'Sexe',
            'Gender': 'Sexe',
            'Date naissance': 'Date naissance',
            'Birth date': 'Date naissance',
            'Nationalité': 'Nationalité',
            'Nationality': 'Nationalité',
            'Enregistrer les modifications': 'Enregistrer les modifications',
            'Save changes': 'Enregistrer les modifications',
            'L\'email ne peut pas être modifié': 'L\'email ne peut pas être modifié',
            'Email cannot be changed': 'L\'email ne peut pas être modifié',

            // ===== MODIFIER MOT DE PASSE =====
            'Modifier mot de passe': 'Modifier mot de passe',
            'Change password': 'Modifier mot de passe',
            'Ancien mot de passe': 'Ancien mot de passe',
            'Old password': 'Ancien mot de passe',
            'Nouveau mot de passe': 'Nouveau mot de passe',
            'New password': 'Nouveau mot de passe',
            'Confirmer le mot de passe': 'Confirmer le mot de passe',
            'Confirm password': 'Confirmer le mot de passe',
            'Minimum 6 caractères': 'Minimum 6 caractères',
            'Minimum 6 characters': 'Minimum 6 caractères',
            'Force : Très faible': 'Force : Very weak',
            'Force : Faible': 'Force : Weak',
            'Force : Moyen': 'Force : Medium',
            'Force : Fort': 'Force : Strong',
            'Force : Très fort': 'Force : Very strong',
            'Retour au profil': 'Retour au profil',
            'Back to profile': 'Retour au profil',
            'Entrez votre mot de passe actuel': 'Entrez votre mot de passe actuel',
            'Enter your current password': 'Entrez votre mot de passe actuel',
            'Répétez le nouveau mot de passe': 'Répétez le nouveau mot de passe',
            'Repeat the new password': 'Répétez le nouveau mot de passe'
        },
        en: {
            // ===== MENU =====
            'Accueil': 'Home',
            'Vidéos': 'Videos',
            'Favoris': 'Favorites',
            'Historique': 'History',
            'Profil': 'Profile',
            'Déconnexion': 'Logout',
            'Home': 'Home',
            'Videos': 'Videos',
            'Favorites': 'Favorites',
            'History': 'History',
            'Profile': 'Profile',
            'Logout': 'Logout',
            'View profile': 'View profile',
            'Edit profile': 'Edit profile',
            'Change password': 'Change password',
            'Dark mode': 'Dark mode',
            'Mode sombre': 'Dark mode',
            'Change language': 'Change language',
            'Changer de langue': 'Change language',
            'Principal': 'Main',
            'Main': 'Main',
            'Profile': 'Profile',
            'System': 'System',
            'Système': 'System',
            'Assistant IA': 'AI Assistant',
            'AI Assistant': 'AI Assistant',
            'Assistant': 'Assistant',

            // ===== INDEX.PHP =====
            'Toutes les vidéos': 'All videos',
            'All videos': 'All videos',
            'Total': 'Total',
            'Vues totales': 'Total views',
            'Dernière mise à jour': 'Last update',
            'Rechercher': 'Search',
            'Search': 'Search',
            'Rechercher un film...': 'Search for a movie...',
            'Search for a movie...': 'Search for a movie...',
            'Toutes les catégories': 'All categories',
            'All categories': 'All categories',
            'vidéos': 'videos',
            'videos': 'videos',
            'Regarder': 'Watch',
            'Watch': 'Watch',
            'Aucune vidéo disponible': 'No video available',
            'No video available': 'No video available',
            'Revenez plus tard pour découvrir de nouveaux films.': 'Come back later to discover new movies.',
            'Come back later to discover new movies.': 'Come back later to discover new movies.',
            'Total views': 'Total views',
            'Last update': 'Last update',
            'Résultats pour': 'Results for',
            'Results for': 'Results for',
            'film(s) trouvé(s)': 'film(s) found',
            'film(s) found': 'film(s) found',
            'Aucun résultat trouvé': 'No results found',
            'No results found': 'No results found',
            'Aucun film ne correspond à votre recherche.': 'No movie matches your search.',
            'No movie matches your search.': 'No movie matches your search.',

            // ===== FAVORIS.PHP =====
            'Mes favoris': 'My favorites',
            'My favorites': 'My favorites',
            'Aucun favori': 'No favorites',
            'No favorites': 'No favorites',
            'Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo.': 'Add videos to your favorites by clicking the ❤️ on a video.',
            'Add videos to your favorites by clicking the ❤️ on a video.': 'Add videos to your favorites by clicking the ❤️ on a video.',
            'Découvrir des vidéos': 'Discover videos',
            'Discover videos': 'Discover videos',
            'Retirer des favoris': 'Remove from favorites',
            'Remove from favorites': 'Remove from favorites',
            'Retirer cette vidéo de vos favoris ?': 'Remove this video from your favorites?',
            'Remove this video from your favorites?': 'Remove this video from your favorites?',

            // ===== HISTORIQUE.PHP =====
            'Mon historique': 'My history',
            'My history': 'My history',
            'Aucun historique': 'No history',
            'No history': 'No history',
            'Commencez à regarder des films pour remplir votre historique.': 'Start watching movies to fill your history.',
            'Start watching movies to fill your history.': 'Start watching movies to fill your history.',
            'Reprendre': 'Resume',
            'Resume': 'Resume',
            'Vu': 'Watched',
            'Watched': 'Watched',

            // ===== PROFIL.PHP =====
            'Mon profil': 'My profile',
            'My profile': 'My profile',
            'Membre': 'Member',
            'Member': 'Member',
            'Admin': 'Admin',
            'Membre depuis': 'Member since',
            'Member since': 'Member since',
            'Modifier': 'Edit',
            'Edit': 'Edit',
            'Sécurité': 'Security',
            'Security': 'Security',
            'Quitter': 'Leave',
            'Leave': 'Leave',
            'Masculin': 'Male',
            'Féminin': 'Female',
            'Non renseigné': 'Not specified',
            'Not specified': 'Not specified',
            'Non renseignée': 'Not specified',

            // ===== MODIFIER PROFIL =====
            'Modifier mon profil': 'Edit my profile',
            'Edit my profile': 'Edit my profile',
            'Nom d\'utilisateur': 'Username',
            'Username': 'Username',
            'Prénom': 'First name',
            'First name': 'First name',
            'Nom': 'Last name',
            'Last name': 'Last name',
            'Email': 'Email',
            'Sexe': 'Gender',
            'Gender': 'Gender',
            'Date naissance': 'Birth date',
            'Birth date': 'Birth date',
            'Nationalité': 'Nationality',
            'Nationality': 'Nationality',
            'Enregistrer les modifications': 'Save changes',
            'Save changes': 'Save changes',
            'L\'email ne peut pas être modifié': 'Email cannot be changed',
            'Email cannot be changed': 'Email cannot be changed',

            // ===== MODIFIER MOT DE PASSE =====
            'Modifier mot de passe': 'Change password',
            'Change password': 'Change password',
            'Ancien mot de passe': 'Old password',
            'Old password': 'Old password',
            'Nouveau mot de passe': 'New password',
            'New password': 'New password',
            'Confirmer le mot de passe': 'Confirm password',
            'Confirm password': 'Confirm password',
            'Minimum 6 caractères': 'Minimum 6 characters',
            'Minimum 6 characters': 'Minimum 6 characters',
            'Force : Très faible': 'Strength : Very weak',
            'Force : Faible': 'Strength : Weak',
            'Force : Moyen': 'Strength : Medium',
            'Force : Fort': 'Strength : Strong',
            'Force : Très fort': 'Strength : Very strong',
            'Retour au profil': 'Back to profile',
            'Back to profile': 'Back to profile',
            'Entrez votre mot de passe actuel': 'Enter your current password',
            'Enter your current password': 'Enter your current password',
            'Répétez le nouveau mot de passe': 'Repeat the new password',
            'Repeat the new password': 'Repeat the new password'
        }
    };

    let currentLang = localStorage.getItem('lang') || 'fr';

    // ============================================
    // MENU
    // ============================================
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
        document.querySelector('.nav-burger').classList.toggle('active');
        document.body.style.overflow = document.body.style.overflow === 'hidden' ? '' : 'hidden';
    }

    // Recherche (icône loupe de la barre du haut, mobile)
    function bmFindSearchInput() {
        return document.getElementById('searchInput') || document.querySelector('.search-bar input');
    }
    function bmOpenSearch(e) {
        var i = bmFindSearchInput();
        if (!i) { return true; }          // pas de champ sur cette page : on va sur Vidéos
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(function () { i.focus(); }, 250);
        return false;
    }
    document.addEventListener('DOMContentLoaded', function () {
        if (location.hash === '#search') {
            var i = bmFindSearchInput();
            if (i) { i.focus(); }
        }
    });

    function closeMenu() {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
        document.querySelector('.nav-burger').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });

    // ============================================
    // DARK MODE
    // ============================================
    function toggleTheme() {
        document.body.classList.toggle('light-mode');

        const icon = document.getElementById('themeIcon');
        const iconSidebar = document.getElementById('themeIconSidebar');
        const label = document.getElementById('themeLabel');

        if (document.body.classList.contains('light-mode')) {
            icon.className = 'fas fa-sun';
            if (iconSidebar) iconSidebar.className = 'fas fa-sun';
            if (label) label.textContent = 'Mode clair';
            localStorage.setItem('theme', 'light');
        } else {
            icon.className = 'fas fa-moon';
            if (iconSidebar) iconSidebar.className = 'fas fa-moon';
            if (label) label.textContent = 'Mode sombre';
            localStorage.setItem('theme', 'dark');
        }
    }

    if (localStorage.getItem('theme') === 'light') {
        document.body.classList.add('light-mode');
        document.getElementById('themeIcon').className = 'fas fa-sun';
        const iconSidebar = document.getElementById('themeIconSidebar');
        if (iconSidebar) iconSidebar.className = 'fas fa-sun';
        const label = document.getElementById('themeLabel');
        if (label) label.textContent = 'Mode clair';
    }

    // ============================================
    // LANGUE
    // ============================================
    function toggleLanguage() {
        currentLang = (currentLang === 'fr') ? 'en' : 'fr';
        localStorage.setItem('lang', currentLang);

        document.getElementById('langLabel').textContent = currentLang.toUpperCase();
        const langLabelSidebar = document.getElementById('langLabelSidebar');
        if (langLabelSidebar) {
            langLabelSidebar.textContent = currentLang.toUpperCase();
        }

        applyLanguage(currentLang);
    }

    function applyLanguage(lang) {
        // Traduire les éléments avec data-lang-fr et data-lang-en
        document.querySelectorAll('[data-lang-fr][data-lang-en]').forEach(el => {
            const text = el.getAttribute('data-lang-' + lang);
            if (text !== null) {
                // Vérifier si l'élément a un span enfant
                const span = el.querySelector('span');
                if (span && el.children.length === 1) {
                    // Si c'est un span unique, on le traduit
                    span.textContent = text;
                } else if (span && el.children.length > 1) {
                    // Si plusieurs enfants, on cherche le span de texte
                    const textSpan = el.querySelector('span:not(.nav-label)');
                    if (textSpan) {
                        textSpan.textContent = text;
                    }
                } else {
                    // Pas de span, on traduit directement le texte
                    el.textContent = text;
                }
            }
        });

        // Traduire les section labels
        document.querySelectorAll('.section-label').forEach(el => {
            const text = el.getAttribute('data-lang-' + lang);
            if (text !== null) {
                el.textContent = text;
            }
        });
    }

    // ============================================
    // CONFIRMATION DE DÉCONNEXION AVEC ALERTE STYLISÉE
    // ============================================
    function confirmLogout(event) {
        event.preventDefault();

        const lang = currentLang;
        const messages = {
            fr: {
                title: 'Déconnexion',
                confirm: 'Voulez-vous vraiment vous déconnecter ?',
                yes: 'Oui, me déconnecter',
                no: 'Annuler'
            },
            en: {
                title: 'Logout',
                confirm: 'Do you really want to logout?',
                yes: 'Yes, logout',
                no: 'Cancel'
            }
        };

        const msg = messages[lang] || messages.fr;

        // Vérifier si un overlay existe déjà et le supprimer
        const existingOverlay = document.querySelector('.logout-overlay');
        if (existingOverlay) {
            existingOverlay.remove();
            return;
        }

        // Créer l'overlay
        const overlay = document.createElement('div');
        overlay.className = 'logout-overlay';
        overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    `;

        // Empêcher le scroll
        document.body.style.overflow = 'hidden';

        const modal = document.createElement('div');
        modal.style.cssText = `
        background: ${document.body.classList.contains('light-mode') ? '#ffffff' : '#1e293b'};
        border-radius: 20px;
        padding: 32px 28px 28px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        text-align: center;
        animation: slideUp 0.3s ease;
        border: 1px solid ${document.body.classList.contains('light-mode') ? '#e2e8f0' : '#334155'};
        position: relative;
    `;

        modal.innerHTML = `
        <div style="margin-bottom: 16px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <i class="fas fa-sign-out-alt" style="font-size: 28px; color: #ef4444;"></i>
            </div>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: ${document.body.classList.contains('light-mode') ? '#0f172a' : '#e2e8f0'}; margin-bottom: 8px;">
            ${msg.title}
        </h2>
        <p style="font-size: 15px; color: ${document.body.classList.contains('light-mode') ? '#475569' : '#94a3b8'}; margin-bottom: 24px; line-height: 1.5;">
            ${msg.confirm}
        </p>
        <div style="display: flex; gap: 10px;">
            <button onclick="closeLogoutModal()" 
                    style="flex: 1; padding: 12px; border: 2px solid ${document.body.classList.contains('light-mode') ? '#e2e8f0' : '#334155'}; border-radius: 12px; background: transparent; color: ${document.body.classList.contains('light-mode') ? '#475569' : '#94a3b8'}; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">
                ${msg.no}
            </button>
            <a href="/film_web_dy/user/logout.php" 
               style="flex: 1; padding: 12px; border: none; border-radius: 12px; background: linear-gradient(135deg, #ef4444, #dc2626); color: #ffffff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease;">
                <i class="fas fa-sign-out-alt"></i> ${msg.yes}
            </a>
        </div>
    `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Fermer en cliquant sur l'overlay (en dehors du modal)
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        // Fermer avec Escape
        const closeHandler = function (e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
                document.removeEventListener('keydown', closeHandler);
            }
        };
        document.addEventListener('keydown', closeHandler);
    }

    // ============================================
    // FERMER LE MODAL DE DÉCONNEXION
    // ============================================
    function closeLogoutModal() {
        const overlay = document.querySelector('.logout-overlay');
        if (overlay) {
            overlay.remove();
        }
        // Réactiver le scroll
        document.body.style.overflow = '';
    }

    // ============================================
    // INITIALISATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        // Mettre à jour le label de langue
        document.getElementById('langLabel').textContent = currentLang.toUpperCase();
        const langLabelSidebar = document.getElementById('langLabelSidebar');
        if (langLabelSidebar) {
            langLabelSidebar.textContent = currentLang.toUpperCase();
        }
        applyLanguage(currentLang);
    });
</script>
<?php include __DIR__ . '/chat-widget.php'; ?>
<?php include __DIR__ . '/support-widget.php'; ?>