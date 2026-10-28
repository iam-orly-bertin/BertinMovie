<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/film_web_dy/style.css">

<nav class="navbar">
    <!-- Logo -->
    <a href="/film_web_dy/admin/dashboard.php" class="logo">
        <i class="fas fa-film"></i>
        BERTIN <span>MOVIE</span>
    </a>

    <!-- Menu Horizontal -->
    <div class="nav-menu">
        <a href="/film_web_dy/admin/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"
            data-lang-fr="Accueil" data-lang-en="Home">
            <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="/film_web_dy/admin/film.php" class="<?= $current_page == 'film.php' ? 'active' : '' ?>"
            data-lang-fr="Films" data-lang-en="Movies">
            <i class="fas fa-film"></i> <span>Films</span>
        </a>
        <a href="/film_web_dy/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>"
            data-lang-fr="Vidéo" data-lang-en="Video">
            <i class="fas fa-video"></i> <span>Vidéo</span>
        </a>

        <span class="nav-divider"></span>

        <a href="/film_web_dy/admin/acteurs.php" class="<?= $current_page == 'acteurs.php' ? 'active' : '' ?>"
            data-lang-fr="Acteurs" data-lang-en="Actors">
            <i class="fas fa-users"></i> <span>Acteurs</span>
        </a>
        <a href="/film_web_dy/admin/realisateur.php" class="<?= $current_page == 'realisateur.php' ? 'active' : '' ?>"
            data-lang-fr="Réalisateurs" data-lang-en="Directors">
            <i class="fas fa-user-tie"></i> <span>Réalisateurs</span>
        </a>
        <a href="/film_web_dy/admin/categorie.php" class="<?= $current_page == 'categorie.php' ? 'active' : '' ?>"
            data-lang-fr="Catégories" data-lang-en="Categories">
            <i class="fas fa-tags"></i> <span>Catégories</span>
        </a>

        <span class="nav-divider"></span>

        <a href="/film_web_dy/admin/utilisateurs.php"
            class="<?= in_array($current_page, ['utilisateurs.php', 'utilisateur_detail.php', 'utilisateur_historique.php']) ? 'active' : '' ?>"
            data-lang-fr="Utilisateurs" data-lang-en="Users">
            <i class="fas fa-user-cog"></i> <span>Utilisateurs</span>
        </a>
        <a href="/film_web_dy/admin/messages.php" class="<?= $current_page == 'messages.php' ? 'active' : '' ?>"
            data-lang-fr="Messages" data-lang-en="Messages">
            <i class="fas fa-comments"></i> <span>Messages</span>
            <span class="bms-nav-badge"
                style="display:none;background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:700;padding:1px 7px;margin-left:4px"></span>
        </a>

    </div>

    <!-- Actions -->
    <div class="nav-actions">
        <button class="nav-btn" id="langBtn" onclick="toggleLanguage()" title="Changer de langue">
            <i class="fas fa-globe"></i>
            <span id="langLabel">FR</span>
        </button>

        <button class="nav-btn" onclick="toggleTheme()" title="Changer de thème">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <span class="nav-divider"></span>

        <a href="#" class="nav-logout" data-lang-fr="Déconnexion" data-lang-en="Logout"
            onclick="openLogoutModal(event)">
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
    <div class="sidebar-body">

        <!-- ============================================ -->
        <!-- 🔥 PROFIL ADMIN - EN HAUT DU SIDEBAR -->
        <!-- ============================================ -->
        <div class="sidebar-profile" onclick="window.location.href='/film_web_dy/admin/profil.php'">
            <div class="sidebar-avatar">
                <?php
                // Récupérer les infos de l'admin connecté
                if (isset($_SESSION['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT prenom, nom, nom_utilisateur FROM utilisateur WHERE id_utilisateur = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $admin = $stmt->fetch();
                        if ($admin) {
                            $initiales = strtoupper(substr($admin['prenom'] ?? '', 0, 1) . substr($admin['nom'] ?? '', 0, 1));
                            if (empty($initiales)) {
                                $initiales = strtoupper(substr($admin['nom_utilisateur'] ?? 'A', 0, 2));
                            }
                            $nom_complet = trim(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? ''));
                            if (empty($nom_complet)) {
                                $nom_complet = $admin['nom_utilisateur'] ?? 'Admin';
                            }
                        } else {
                            $initiales = 'AD';
                            $nom_complet = 'Administrateur';
                        }
                    } catch (PDOException $e) {
                        $initiales = 'AD';
                        $nom_complet = 'Administrateur';
                    }
                } else {
                    $initiales = 'AD';
                    $nom_complet = 'Administrateur';
                }
                ?>
                <span><?= $initiales ?></span>
            </div>
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name"><?= htmlspecialchars($nom_complet) ?></div>
                <div class="sidebar-profile-role" data-lang-fr="Administrateur" data-lang-en="Administrator">
                    Administrateur</div>
            </div>
            <div class="sidebar-profile-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>

        <div class="section-divider"></div>

        <!-- SECTION PRINCIPALE -->
        <span class="section-label" data-lang-fr="📌 Principal" data-lang-en="📌 Main">📌 Principal</span>
        <a href="/film_web_dy/admin/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"
            data-lang-fr="Accueil" data-lang-en="Home">
            <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="/film_web_dy/admin/film.php" class="<?= $current_page == 'film.php' ? 'active' : '' ?>"
            data-lang-fr="Films" data-lang-en="Movies">
            <i class="fas fa-film"></i> <span>Films</span>
        </a>
        <a href="/film_web_dy/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>"
            data-lang-fr="Vidéo" data-lang-en="Video">
            <i class="fas fa-video"></i> <span>Vidéo</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION GESTION -->
        <span class="section-label" data-lang-fr="⚙️ Gestion" data-lang-en="⚙️ Management">⚙️ Gestion</span>
        <a href="/film_web_dy/admin/acteurs.php" class="<?= $current_page == 'acteurs.php' ? 'active' : '' ?>"
            data-lang-fr="Acteurs" data-lang-en="Actors">
            <i class="fas fa-users"></i> <span>Acteurs</span>
        </a>
        <a href="/film_web_dy/admin/realisateur.php" class="<?= $current_page == 'realisateur.php' ? 'active' : '' ?>"
            data-lang-fr="Réalisateurs" data-lang-en="Directors">
            <i class="fas fa-user-tie"></i> <span>Réalisateurs</span>
        </a>
        <a href="/film_web_dy/admin/categorie.php" class="<?= $current_page == 'categorie.php' ? 'active' : '' ?>"
            data-lang-fr="Catégories" data-lang-en="Categories">
            <i class="fas fa-tags"></i> <span>Catégories</span>
        </a>
        <a href="/film_web_dy/admin/pays.php" class="<?= $current_page == 'pays.php' ? 'active' : '' ?>"
            data-lang-fr="Pays" data-lang-en="Countries">
            <i class="fas fa-flag"></i> <span>Pays</span>
        </a>
        <a href="/film_web_dy/admin/langue.php" class="<?= $current_page == 'langue.php' ? 'active' : '' ?>"
            data-lang-fr="Langues" data-lang-en="Languages">
            <i class="fas fa-language"></i> <span>Langues</span>
        </a>
        <a href="/film_web_dy/admin/studio.php" class="<?= $current_page == 'studio.php' ? 'active' : '' ?>"
            data-lang-fr="Studios" data-lang-en="Studios">
            <i class="fas fa-building"></i> <span>Studios</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION ASSOCIATIONS -->
        <span class="section-label" data-lang-fr="🔗 Associations" data-lang-en="🔗 Associations">🔗 Associations</span>
        <a href="/film_web_dy/admin/film_acteur.php" data-lang-fr="Film - Acteur" data-lang-en="Movie - Actor">
            <i class="fas fa-link"></i> <span>Film - Acteur</span>
        </a>
        <a href="/film_web_dy/admin/film_studio.php" data-lang-fr="Film - Studio" data-lang-en="Movie - Studio">
            <i class="fas fa-link"></i> <span>Film - Studio</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION ADMINISTRATION -->
        <span class="section-label" data-lang-fr="🛡️ Administration" data-lang-en="🛡️ Administration">🛡️
            Administration</span>
        <a href="/film_web_dy/admin/utilisateurs.php"
            class="<?= in_array($current_page, ['utilisateurs.php', 'utilisateur_detail.php', 'utilisateur_historique.php']) ? 'active' : '' ?>"
            data-lang-fr="Utilisateurs" data-lang-en="Users">
            <i class="fas fa-user-cog"></i> <span>Utilisateurs</span>
        </a>
        <a href="/film_web_dy/admin/messages.php" class="<?= $current_page == 'messages.php' ? 'active' : '' ?>"
            data-lang-fr="Messages" data-lang-en="Messages">
            <i class="fas fa-comments"></i> <span>Messages</span>
            <span class="bms-nav-badge"
                style="display:none;background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:700;padding:1px 7px;margin-left:4px"></span>
        </a>
        <a href="/film_web_dy/admin/documents.php" class="<?= $current_page == 'documents.php' ? 'active' : '' ?>"
            data-lang-fr="Documents" data-lang-en="Documents">
            <i class="fas fa-folder-open"></i> <span>Documents</span>
        </a>
        <a href="/film_web_dy/admin/historique.php" class="<?= $current_page == 'historique.php' ? 'active' : '' ?>"
            data-lang-fr="Historique" data-lang-en="History">
            <i class="fas fa-history"></i> <span>Historique</span>
        </a>
        <a href="/film_web_dy/admin/historique_actions.php"
            class="<?= $current_page == 'historique_actions.php' ? 'active' : '' ?>" data-lang-fr="📋 Notifications"
            data-lang-en="📋 Notifications">
            <i class="fas fa-bell"></i> <span>Notifications</span>
        </a>
        <a href="/film_web_dy/admin/commentaires.php" class="<?= $current_page == 'commentaires.php' ? 'active' : '' ?>"
            data-lang-fr="💬 Commentaires" data-lang-en="💬 Comments">
            <i class="fas fa-comments"></i> <span>Commentaires</span>
        </a>
        <a href="/film_web_dy/admin/corbeille.php" data-lang-fr="Corbeille" data-lang-en="Trash">
            <i class="fas fa-trash-alt"></i> <span>Corbeille</span>
        </a>
        <a href="/film_web_dy/admin/rapports.php" class="<?= $current_page == 'rapports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> <span>Rapports</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION PROFIL -->
        <span class="section-label" data-lang-fr="👤 Profil" data-lang-en="👤 Profile">👤 Profil</span>
        <a href="/film_web_dy/admin/profil.php" data-lang-fr="Voir profil" data-lang-en="View profile">
            <i class="fas fa-id-card"></i> <span>Voir profil</span>
        </a>
        <a href="/film_web_dy/admin/modifier_profil.php" data-lang-fr="Modifier profil" data-lang-en="Edit profile">
            <i class="fas fa-edit"></i> <span>Modifier profil</span>
        </a>
        <a href="/film_web_dy/admin/modifier_motdepasse.php" data-lang-fr="Modifier mot de passe"
            data-lang-en="Change password">
            <i class="fas fa-key"></i> <span>Modifier mot de passe</span>
        </a>

        <div class="section-divider"></div>

        <!-- SECTION SYSTÈME -->
        <span class="section-label" data-lang-fr="⚡ Système" data-lang-en="⚡ System">⚡ Système</span>

        <button class="sidebar-link" onclick="toggleLanguage(); closeMenu();">
            <i class="fas fa-globe"></i>
            <span data-lang-fr="Changer de langue" data-lang-en="Change language">Changer de langue</span>
            <span class="lang-badge" id="langLabelSidebar">FR</span>
        </button>

        <button class="sidebar-link" onclick="toggleTheme(); closeMenu();">
            <i class="fas fa-moon" id="themeIconSidebar"></i>
            <span id="themeLabel" data-lang-fr="Mode sombre" data-lang-en="Dark mode">Mode sombre</span>
        </button>
        <a href="parametres.php" class="sidebar-link" onclick="closeMenu();">
            <i class="fas fa-sliders"></i>
            <span data-lang-fr="Paramètres" data-lang-en="Settings">
                Paramètres
            </span>
        </a>

        <div class="section-divider"></div>

        <!-- Déconnexion -->
        <a href="#" class="logout" data-lang-fr="Déconnexion" data-lang-en="Logout" onclick="openLogoutModal(event)">
            <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
        </a>
    </div>
</div>

<!-- =========================================================
   BOTTOM NAV - MOBILE UNIQUEMENT
========================================================= -->
<nav class="bottom-nav" id="bottomNav">
    <a href="/film_web_dy/admin/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span class="nav-label">Accueil</span>
    </a>
    <a href="/film_web_dy/admin/film.php" class="<?= $current_page == 'film.php' ? 'active' : '' ?>">
        <i class="fas fa-film"></i>
        <span class="nav-label">Films</span>
    </a>
    <a href="/film_web_dy/video.php" class="<?= $current_page == 'video.php' ? 'active' : '' ?>">
        <i class="fas fa-video"></i>
        <span class="nav-label">Vidéo</span>
    </a>
    <a href="/film_web_dy/admin/utilisateurs.php"
        class="<?= in_array($current_page, ['utilisateurs.php', 'utilisateur_detail.php', 'utilisateur_historique.php']) ? 'active' : '' ?>">
        <i class="fas fa-users"></i>
        <span class="nav-label">Utilisateurs</span>
    </a>
    <a href="/film_web_dy/admin/historique_actions.php"
        class="<?= $current_page == 'historique_actions.php' ? 'active' : '' ?>">
        <i class="fas fa-bell"></i>
        <span class="nav-label">Notifications</span>
    </a>
    <a href="/film_web_dy/admin/commentaires.php" class="<?= $current_page == 'commentaires.php' ? 'active' : '' ?>">
        <i class="fas fa-comments"></i>
        <span class="nav-label">Commentaires</span>
    </a>
</nav>

<!-- =========================================================
   MODAL DE DÉCONNEXION - DESIGN PERSONNALISÉ
========================================================= -->
<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal">
        <div class="logout-modal-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3 class="logout-modal-title" data-lang-fr="Déconnexion" data-lang-en="Logout">Déconnexion</h3>
        <p class="logout-modal-message" data-lang-fr="Êtes-vous sûr de vouloir vous déconnecter ?"
            data-lang-en="Are you sure you want to logout?">
            Êtes-vous sûr de vouloir vous déconnecter ?
        </p>
        <div class="logout-modal-buttons">
            <button class="logout-modal-btn logout-modal-btn-cancel" onclick="closeLogoutModal()">
                <i class="fas fa-times"></i>
                <span data-lang-fr="Annuler" data-lang-en="Cancel">Annuler</span>
            </button>
            <a href="/film_web_dy/admin/logout.php" class="logout-modal-btn logout-modal-btn-confirm">
                <i class="fas fa-check"></i>
                <span data-lang-fr="Déconnecter" data-lang-en="Logout">Déconnecter</span>
            </a>
        </div>
    </div>
</div>


<!-- =========================================================
   MODAL DE CONFIRMATION GÉNÉRIQUE
========================================================= -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal">
        <div class="confirm-modal-icon danger" id="confirmModalIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="confirm-modal-title" id="confirmModalTitle">Confirmation</h3>
        <p class="confirm-modal-message" id="confirmModalMessage">Êtes-vous sûr de vouloir continuer ?</p>
        <div class="confirm-modal-buttons">
            <button class="confirm-modal-btn confirm-modal-btn-cancel" id="confirmModalCancel">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button class="confirm-modal-btn confirm-modal-btn-confirm danger" id="confirmModalConfirm">
                <i class="fas fa-check"></i> Confirmer
            </button>
        </div>
    </div>
</div>


<script>
    // ============================================
    // TRADUCTIONS
    // ============================================
    const translations = {
        fr: {
            'Accueil': 'Accueil',
            'Films': 'Films',
            'Vidéo': 'Vidéo',
            'Acteurs': 'Acteurs',
            'Réalisateurs': 'Réalisateurs',
            'Catégories': 'Catégories',
            'Pays': 'Pays',
            'Langues': 'Langues',
            'Studios': 'Studios',
            'Historique': 'Historique',
            'Utilisateurs': 'Utilisateurs',
            'Notifications': 'Notifications',
            'Commentaires': 'Commentaires',
            'Déconnexion': 'Déconnexion',
            'Home': 'Accueil',
            'Movies': 'Films',
            'Video': 'Vidéo',
            'Actors': 'Acteurs',
            'Directors': 'Réalisateurs',
            'Categories': 'Catégories',
            'Countries': 'Pays',
            'Languages': 'Langues',
            'History': 'Historique',
            'Users': 'Utilisateurs',
            'Logout': 'Déconnexion',
            'Associations': 'Associations',
            'Movie - Actor': 'Film - Acteur',
            'Movie - Studio': 'Film - Studio',
            'Profile': 'Profil',
            'View profile': 'Voir profil',
            'Edit profile': 'Modifier profil',
            'Change password': 'Modifier mot de passe',
            'System': 'Système',
            'Trash': 'Corbeille',
            'Corbeille': 'Corbeille',
            'Dark mode': 'Mode sombre',
            'Mode sombre': 'Mode sombre',
            'Change language': 'Changer de langue',
            'Changer de langue': 'Changer de langue',
            'Principal': 'Principal',
            'Gestion': 'Gestion',
            'Administration': 'Administration',
            'Management': 'Gestion',
            'Main': 'Principal',
            'Cancel': 'Annuler',
            'Annuler': 'Annuler',
            'Déconnecter': 'Déconnecter',
            'Administrateur': 'Administrateur',
            'Administrator': 'Administrator'
        },
        en: {
            'Accueil': 'Home',
            'Films': 'Movies',
            'Vidéo': 'Video',
            'Acteurs': 'Actors',
            'Réalisateurs': 'Directors',
            'Catégories': 'Categories',
            'Pays': 'Countries',
            'Langues': 'Languages',
            'Studios': 'Studios',
            'Historique': 'History',
            'Utilisateurs': 'Users',
            'Notifications': 'Notifications',
            'Commentaires': 'Comments',
            'Déconnexion': 'Logout',
            'Home': 'Home',
            'Movies': 'Movies',
            'Video': 'Video',
            'Actors': 'Actors',
            'Directors': 'Directors',
            'Categories': 'Categories',
            'Countries': 'Countries',
            'Languages': 'Languages',
            'History': 'History',
            'Users': 'Users',
            'Logout': 'Logout',
            'Associations': 'Associations',
            'Movie - Actor': 'Movie - Actor',
            'Movie - Studio': 'Movie - Studio',
            'Profil': 'Profile',
            'Voir profil': 'View profile',
            'Modifier profil': 'Edit profile',
            'Modifier mot de passe': 'Change password',
            'Système': 'System',
            'Corbeille': 'Trash',
            'Trash': 'Trash',
            'Dark mode': 'Dark mode',
            'Mode sombre': 'Dark mode',
            'Change language': 'Change language',
            'Changer de langue': 'Change language',
            'Principal': 'Main',
            'Gestion': 'Management',
            'Administration': 'Administration',
            'Management': 'Management',
            'Main': 'Main',
            'Cancel': 'Cancel',
            'Annuler': 'Cancel',
            'Déconnecter': 'Logout',
            'Administrateur': 'Administrator',
            'Administrator': 'Administrator'
        }
    };

    // ============================================
    // MENU
    // ============================================
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
        document.querySelector('.nav-burger').classList.toggle('active');
        document.body.style.overflow = document.body.style.overflow === 'hidden' ? '' : 'hidden';
    }

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
    // MODAL DE DÉCONNEXION
    // ============================================
    function openLogoutModal(event) {
        if (event) event.preventDefault();
        document.getElementById('logoutModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLogoutModal() {
        document.getElementById('logoutModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLogoutModal();
    });

    document.getElementById('logoutModal').addEventListener('click', function (e) {
        if (e.target === this) closeLogoutModal();
    });

    // ============================================
    // DARK MODE
    // ============================================
    function toggleTheme() {
        document.body.classList.toggle('dark-mode');

        const icon = document.getElementById('themeIcon');
        const iconSidebar = document.getElementById('themeIconSidebar');
        const label = document.getElementById('themeLabel');

        if (document.body.classList.contains('dark-mode')) {
            icon.className = 'fas fa-sun';
            if (iconSidebar) iconSidebar.className = 'fas fa-sun';
            if (label) label.textContent = 'Mode clair';
            localStorage.setItem('theme', 'dark');
        } else {
            icon.className = 'fas fa-moon';
            if (iconSidebar) iconSidebar.className = 'fas fa-moon';
            if (label) label.textContent = 'Mode sombre';
            localStorage.setItem('theme', 'light');
        }
    }

    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.getElementById('themeIcon').className = 'fas fa-sun';
        const iconSidebar = document.getElementById('themeIconSidebar');
        if (iconSidebar) iconSidebar.className = 'fas fa-sun';
        const label = document.getElementById('themeLabel');
        if (label) label.textContent = 'Mode clair';
    }

    // ============================================
    // LANGUE
    // ============================================
    let currentLang = localStorage.getItem('lang') || 'fr';

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
        document.querySelectorAll('[data-lang-fr][data-lang-en]').forEach(el => {
            const text = el.getAttribute('data-lang-' + lang);
            if (text !== null) {
                const span = el.querySelector('span');
                if (span) {
                    span.textContent = text;
                } else {
                    el.textContent = text;
                }
            }
        });

        document.querySelectorAll('.section-label').forEach(el => {
            const text = el.getAttribute('data-lang-' + lang);
            if (text !== null) {
                el.textContent = text;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('langLabel').textContent = currentLang.toUpperCase();
        const langLabelSidebar = document.getElementById('langLabelSidebar');
        if (langLabelSidebar) {
            langLabelSidebar.textContent = currentLang.toUpperCase();
        }
        applyLanguage(currentLang);
    });

    // ============================================
    // FERMER LE SIDEBAR APRÈS CLIC SUR UN LIEN
    // ============================================
    document.querySelectorAll('.sidebar a, .sidebar .sidebar-link').forEach(function (el) {
        el.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                setTimeout(closeMenu, 300);
            }
        });
    });

    // ============================================
    // MODAL DE CONFIRMATION
    // ============================================
    const confirmModal = {
        overlay: document.getElementById('confirmModal'),
        icon: document.getElementById('confirmModalIcon'),
        title: document.getElementById('confirmModalTitle'),
        message: document.getElementById('confirmModalMessage'),
        cancelBtn: document.getElementById('confirmModalCancel'),
        confirmBtn: document.getElementById('confirmModalConfirm'),
        callback: null,
        confirmData: null,

        init() {
            this.cancelBtn.addEventListener('click', () => this.close());
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) this.close();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.close();
            });
            this.confirmBtn.addEventListener('click', () => {
                if (this.callback) {
                    this.callback(this.confirmData);
                }
                this.close();
            });
        },

        open(options) {
            const type = options.type || 'danger';
            const iconMap = {
                danger: 'fa-exclamation-triangle',
                warning: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };

            this.icon.className = 'confirm-modal-icon ' + type;
            this.icon.innerHTML = '<i class="fas ' + iconMap[type] + '"></i>';
            this.title.textContent = options.title || 'Confirmation';
            this.message.textContent = options.message || 'Êtes-vous sûr de vouloir continuer ?';

            this.confirmBtn.className = 'confirm-modal-btn confirm-modal-btn-confirm ' + type;
            this.confirmBtn.innerHTML = '<i class="fas fa-check"></i> ' + (options.confirmText || 'Confirmer');
            this.cancelBtn.innerHTML = '<i class="fas fa-times"></i> ' + (options.cancelText || 'Annuler');

            this.callback = options.callback || null;
            this.confirmData = options.data || null;

            this.overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
            this.callback = null;
            this.confirmData = null;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        confirmModal.init();
    });

    // ============================================
    // FONCTIONS UTILITAIRES
    // ============================================
    function confirmDelete(id, url, message) {
        const lang = localStorage.getItem('lang') || 'fr';
        const msg = message || (lang === 'fr' ? 'Supprimer cet élément ?' : 'Delete this item?');

        confirmModal.open({
            type: 'danger',
            title: lang === 'fr' ? 'Confirmation de suppression' : 'Delete confirmation',
            message: msg,
            confirmText: lang === 'fr' ? 'Supprimer' : 'Delete',
            cancelText: lang === 'fr' ? 'Annuler' : 'Cancel',
            callback: function () {
                window.location.href = url + '?supp=' + id;
            }
        });
    }

    function confirmAction(id, url, param, message, type) {
        const lang = localStorage.getItem('lang') || 'fr';
        const msg = message || (lang === 'fr' ? 'Confirmer cette action ?' : 'Confirm this action?');
        const typeIcon = type || 'danger';

        confirmModal.open({
            type: typeIcon,
            title: lang === 'fr' ? 'Confirmation' : 'Confirmation',
            message: msg,
            confirmText: lang === 'fr' ? 'Confirmer' : 'Confirm',
            cancelText: lang === 'fr' ? 'Annuler' : 'Cancel',
            callback: function () {
                window.location.href = url + '?' + param + '=' + id;
            }
        });
    }
    // ============================================
    // VIDER LA CORBEILLE
    // ============================================
    function confirmEmptyTrash() {
        const lang = localStorage.getItem('lang') || 'fr';
        const message = lang === 'fr'
            ? 'Vider la corbeille définitivement ? Cette action est irréversible.'
            : 'Empty trash permanently? This action is irreversible.';

        confirmModal.open({
            type: 'danger',
            title: lang === 'fr' ? 'Vider la corbeille' : 'Empty trash',
            message: message,
            confirmText: lang === 'fr' ? 'Vider' : 'Empty',
            cancelText: lang === 'fr' ? 'Annuler' : 'Cancel',
            callback: function () {
                window.location.href = 'corbeille.php?vider=1';
            }
        });
    }
</script>

<script>
    /* Pastille « messages non lus » (chat support) : vérifie toutes les 30 s, en pause si l'onglet est caché */
    (function () {
        function upd(n) {
            document.querySelectorAll('.bms-nav-badge').forEach(function (b) { b.textContent = n > 99 ? '99+' : n; b.style.display = n > 0 ? 'inline-block' : 'none'; });
        }
        function chk() {
            if (document.hidden) { return; }
            fetch('/film_web_dy/admin/support_api.php?action=count', { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) { if (j) { upd(j.unread || 0); } })
                .catch(function () { });
        }
        chk(); setInterval(chk, 30000);
        document.addEventListener('visibilitychange', chk);
    })();
</script>
<?php /* Traductions FR/EN de toutes les pages admin (admin_lang/translations.js) */ include __DIR__ . "/lang-admin.php"; ?>
