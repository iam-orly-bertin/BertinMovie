<?php
require_once __DIR__ . '/../includes/session_boot.php';

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/update_session.php';

// 🔥 D'ABORD : Créer/mettre à jour la session dans la base de données
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

// 🔥 ENSUITE : Vérifier l'authentification
require_once __DIR__ . '/../includes/auth_check.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_nom = $_SESSION['user_nom'] ?? 'Utilisateur';

// Récupérer le terme de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ✅ UNIQUEMENT LES FILMS QUI ONT UNE VIDÉO
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT f.*, 
               v.id_video, 
               v.thumbnail, 
               v.views, 
               v.date_upload,
               c.nom_categorie
        FROM film f
        INNER JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        WHERE f.is_deleted = 0 AND f.est_archive = 0 AND (f.nom_film LIKE :search OR f.titre LIKE :search)
        ORDER BY v.date_upload DESC
        LIMIT 12
    ");
    $stmt->execute([':search' => "%$search%"]);
    $films = $stmt->fetchAll();
} else {
    $films = $pdo->query("
        SELECT f.*, 
               v.id_video, 
               v.thumbnail, 
               v.views, 
               v.date_upload,
               c.nom_categorie
        FROM film f
        INNER JOIN video_film v ON f.id_film = v.id_film
        LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
        WHERE f.is_deleted = 0 AND f.est_archive = 0
        ORDER BY v.date_upload DESC
        LIMIT 12
    ")->fetchAll();
}

// Catégories (barre à défiler, style YouTube)
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Récupérer les favoris
$favoris_ids = [];
try {
    $stmt = $pdo->prepare("SELECT id_video FROM user_favoris WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $favoris_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer si la table n'existe pas
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-fr="BERTIN MOVIE - Accueil" data-lang-en="BERTIN MOVIE - Home">BERTIN MOVIE - Accueil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        /* =========================================================
           HEADER AVEC RECHERCHE
           ========================================================= */
        .page-header-with-search {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header-with-search .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-with-search .header-left h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header-with-search .header-left h1 i {
            color: var(--primary-light);
        }

        .page-header-with-search .header-left .user-link {
            color: var(--text-secondary);
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .page-header-with-search .header-left .user-link:hover {
            background: var(--primary-light);
            color: #fff;
            border-color: var(--primary-light);
        }

        /* =========================================================
           BARRE DE RECHERCHE MODERNISÉE
           ========================================================= */
        .search-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 14px;
            padding: 4px;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 200px;
            max-width: 500px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .search-bar:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1), 0 4px 16px rgba(59, 130, 246, 0.08);
            transform: translateY(-1px);
        }

        .search-bar .search-icon {
            color: var(--text-secondary);
            font-size: 15px;
            padding-left: 12px;
            flex-shrink: 0;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .search-bar:focus-within .search-icon {
            opacity: 1;
            color: #3b82f6;
        }

        .search-bar input {
            border: none;
            background: transparent;
            padding: 10px 4px;
            font-size: 14px;
            color: var(--text-primary);
            width: 100%;
            outline: none;
            font-family: inherit;
        }

        .search-bar input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .search-bar .btn-clear {
            display: none;
            padding: 6px 10px;
            background: transparent;
            color: var(--text-secondary);
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 50%;
            transition: all 0.3s ease;
            flex-shrink: 0;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
        }

        .search-bar .btn-clear.visible {
            display: flex;
        }

        .search-bar .btn-clear:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            transform: scale(1.1);
        }

        .search-bar .btn-clear:active {
            transform: scale(0.9);
        }

        .search-bar .btn-search {
            padding: 8px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }

        .search-bar .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35);
        }

        .search-bar .btn-search:active {
            transform: scale(0.96);
        }

        .search-bar .btn-search i {
            font-size: 13px;
        }

        /* =========================================================
           FAVORITE BUTTON - COULEUR BLEUE
           ========================================================= */
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            cursor: pointer;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .favorite-btn.active {
            background: #3b82f6 !important;
            box-shadow: 0 2px 12px rgba(59, 130, 246, 0.4);
        }

        .favorite-btn.active i {
            color: #fff !important;
        }

        /* =========================================================
           THUMBNAIL CLICKABLE
           ========================================================= */
        .thumb-link {
            display: block;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 5;
            cursor: pointer;
        }

        .thumb {
            position: relative;
            padding-top: 56.25%;
            background: #f1f5f9;
            overflow: hidden;
            cursor: pointer;
        }

        .thumb img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .movie-card:hover .thumb img {
            transform: scale(1.05);
        }

        /* =========================================================
           RESULTATS DE RECHERCHE
           ========================================================= */
        .search-results-info {
            margin-bottom: 16px;
            padding: 12px 16px;
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .search-results-info strong {
            color: var(--text-primary);
        }

        .search-results-info .result-badge {
            background: #3b82f6;
            color: #fff;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* =========================================================
           RESPONSIVE - MOBILE
           ========================================================= */
        @media (max-width: 768px) {
            .page-header-with-search {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .page-header-with-search .header-left {
                justify-content: space-between;
                width: 100%;
            }

            .page-header-with-search .header-left h1 {
                font-size: 20px;
            }

            .page-header-with-search .header-left .user-link {
                font-size: 13px;
                padding: 4px 10px;
            }

            .search-bar {
                min-width: 100%;
                max-width: 100%;
                padding: 4px;
                border-radius: 12px;
            }

            .search-bar input {
                font-size: 14px;
                padding: 8px 4px;
            }

            .search-bar .btn-search {
                padding: 6px 16px;
                font-size: 13px;
            }

            .search-bar .btn-search span {
                display: inline;
            }

            .search-bar .search-icon {
                padding-left: 10px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .page-header-with-search .header-left h1 {
                font-size: 17px;
            }

            .page-header-with-search .header-left h1 i {
                font-size: 18px;
            }

            .page-header-with-search .header-left .user-link {
                font-size: 12px;
                padding: 4px 8px;
            }

            .page-header-with-search .header-left .user-link span {
                display: none;
            }

            .search-bar {
                padding: 3px;
                border-radius: 10px;
                gap: 4px;
            }

            .search-bar input {
                font-size: 13px;
                padding: 6px 4px;
            }

            .search-bar .search-icon {
                padding-left: 8px;
                font-size: 13px;
            }

            .search-bar .btn-search {
                padding: 5px 12px;
                font-size: 12px;
                border-radius: 8px;
            }

            .search-bar .btn-search i {
                font-size: 12px;
            }

            .search-bar .btn-search span {
                display: none;
            }

            .search-bar .btn-clear {
                width: 30px;
                height: 30px;
                font-size: 14px;
                padding: 4px 8px;
            }

            .favorite-btn {
                width: 30px;
                height: 30px;
                font-size: 13px;
                top: 8px;
                right: 8px;
            }

            .search-results-info {
                font-size: 13px;
                padding: 10px 14px;
            }
        }

        @media (max-width: 380px) {
            .page-header-with-search .header-left h1 {
                font-size: 15px;
            }

            .search-bar input {
                font-size: 12px;
            }

            .search-bar .btn-search {
                padding: 4px 10px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>

    <div class="page-content">
        <!-- ===== HEADER AVEC RECHERCHE ===== -->
        <div class="page-header-with-search">
            <div class="header-left">
                <h1>
                    <i class="fas fa-film"></i>
                    <span data-lang-fr="Accueil" data-lang-en="Home">Accueil</span>
                </h1>
                <a href="/film_web_dy/user/profil.php" class="user-link">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($user_nom) ?></span>
                </a>
            </div>

            <!-- BARRE DE RECHERCHE -->
            <form method="GET" class="search-bar" id="searchForm" action="">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" id="searchInput" placeholder="Rechercher un film..."
                    data-lang-fr-placeholder="Rechercher un film..." data-lang-en-placeholder="Search for a movie..."
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="btn-clear <?= !empty($search) ? 'visible' : '' ?>" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i>
                    <span data-lang-fr="Rechercher" data-lang-en="Search">Rechercher</span>
                </button>
            </form>
        </div>

        <!-- RÉSULTATS DE RECHERCHE -->
        <?php if (!empty($search)): ?>
            <div class="search-results-info">
                <span data-lang-fr="Résultats pour" data-lang-en="Results for">Résultats pour</span>
                <strong>"<?= htmlspecialchars($search) ?>"</strong>
                <span class="result-badge"><?= count($films) ?></span>
                <span data-lang-fr="film(s) trouvé(s)" data-lang-en="film(s) found">film(s) trouvé(s)</span>
            </div>
        <?php endif; ?>

        <!-- GRILLE DES FILMS -->
        <?php if (count($films) > 0): ?>
            <?php if (!empty($categories)): ?>
                <div class="chips-bar" id="chipsBar">
                    <button type="button" class="chip active" data-cat="" data-lang-fr="Tout" data-lang-en="All">Tout</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="chip"
                            data-cat="<?= (int) $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom_categorie']) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="movies-grid">
                <?php foreach ($films as $film): ?>
                    <div class="movie-card" data-categorie="<?= (int) ($film['id_categorie'] ?? 0) ?>">
                        <div class="thumb">
                            <!-- Lien cliquable sur toute la miniature -->
                            <a href="/film_web_dy/user/watch.php?id_video=<?= $film['id_video'] ?>" class="thumb-link"></a>

                            <?php if (!empty($film['thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($film['thumbnail']) ?>"
                                    alt="<?= htmlspecialchars($film['nom_film']) ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-film"></i>
                                    <span><?= htmlspecialchars($film['nom_film']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($film['duree'])): ?>
                                <span class="duration"><?= htmlspecialchars($film['duree']) ?></span>
                            <?php endif; ?>

                            <button class="favorite-btn <?= in_array($film['id_video'], $favoris_ids) ? 'active' : '' ?>"
                                onclick="event.stopPropagation(); toggleFavori(<?= $film['id_video'] ?>, this)">
                                <i class="<?= in_array($film['id_video'], $favoris_ids) ? 'fas' : 'far' ?> fa-heart"></i>
                            </button>
                        </div>
                        <div class="info">
                            <h4><?= htmlspecialchars($film['nom_film']) ?></h4>
                            <div class="meta">
                                <span><i class="fas fa-eye"></i> <?= number_format($film['views'] ?? 0, 0, ',', ' ') ?></span>
                                <span><i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé') ?></span>
                            </div>
                            <a href="/film_web_dy/user/watch.php?id_video=<?= $film['id_video'] ?>" class="btn-play">
                                <i class="fas fa-play"></i>
                                <span data-lang-fr="Regarder" data-lang-en="Watch">Regarder</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <?php if (!empty($search)): ?>
                    <h3 data-lang-fr="Aucun résultat trouvé" data-lang-en="No results found">Aucun résultat trouvé</h3>
                    <p data-lang-fr="Aucun film ne correspond à votre recherche." data-lang-en="No movie matches your search.">
                        Aucun film ne correspond à votre recherche.
                    </p>
                <?php else: ?>
                    <h3 data-lang-fr="Aucun film disponible" data-lang-en="No movie available">Aucun film disponible</h3>
                    <p data-lang-fr="Revenez plus tard pour découvrir de nouveaux films."
                        data-lang-en="Come back later to discover new movies.">
                        Revenez plus tard pour découvrir de nouveaux films.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ============================================
        // FAVORIS
        // ============================================
        function toggleFavori(idVideo, btn) {
            const icon = btn.querySelector('i');
            const isActive = btn.classList.contains('active');

            fetch('/film_web_dy/user/ajax_favori.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_video=' + idVideo + '&action=' + (isActive ? 'remove' : 'add')
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (isActive) {
                            btn.classList.remove('active');
                            icon.className = 'far fa-heart';
                        } else {
                            btn.classList.add('active');
                            icon.className = 'fas fa-heart';
                        }
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // ============================================
        // EFFACER LA RECHERCHE - CORRIGÉ
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            const clearBtn = document.getElementById('clearSearch');
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');

            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.preventDefault();

                    // Vider le champ
                    searchInput.value = '';

                    // Cacher le bouton clear
                    this.classList.remove('visible');

                    // Rediriger vers la page sans paramètre search
                    window.location.href = window.location.pathname;
                });
            }

            // Afficher/cacher le bouton clear
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const clearBtn = document.getElementById('clearSearch');
                    if (this.value.length > 0) {
                        clearBtn.classList.add('visible');
                    } else {
                        clearBtn.classList.remove('visible');
                    }
                });
            }
        });

        // ============================================
        // TRADUCTION - GESTION DES PLACEHOLDERS
        // ============================================
        function applyLanguageForPlaceholders(lang) {
            document.querySelectorAll('[data-lang-fr-placeholder][data-lang-en-placeholder]').forEach(el => {
                const placeholder = el.getAttribute('data-lang-' + lang + '-placeholder');
                if (placeholder !== null) {
                    el.placeholder = placeholder;
                }
            });
        }

        // Surcharger la fonction applyLanguage pour inclure les placeholders
        const originalApplyLanguage = window.applyLanguage;
        window.applyLanguage = function (lang) {
            if (typeof originalApplyLanguage === 'function') {
                originalApplyLanguage(lang);
            }
            applyLanguageForPlaceholders(lang);
        };

        // Initialiser les placeholders au chargement
        document.addEventListener('DOMContentLoaded', function () {
            const currentLang = localStorage.getItem('lang') || 'fr';
            applyLanguageForPlaceholders(currentLang);
        });

        // ============================================
        // VÉRIFICATION DE SESSION
        // ============================================
        setInterval(function () {
            fetch('/film_web_dy/check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        window.location.href = '/film_web_dy/index.php';
                    }
                })
                .catch(function () { });
        }, 10000);
    </script>

    <script>
        // Barre de catégories : filtre les films affichés sur l'accueil
        (function () {
            var bar = document.getElementById('chipsBar');
            if (!bar) { return; }
            bar.addEventListener('click', function (e) {
                var chip = e.target.closest('.chip');
                if (!chip) { return; }
                bar.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                var cat = chip.dataset.cat;
                document.querySelectorAll('.movies-grid .movie-card').forEach(function (card) {
                    card.style.display = (!cat || card.dataset.categorie === cat) ? '' : 'none';
                });
                chip.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            });
        })();
    </script>
</body>

</html>