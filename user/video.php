<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

// Récupérer toutes les vidéos
$videos = $pdo->query("
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
")->fetchAll();

// Récupérer les catégories pour le filtre
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();

// Récupérer les favoris
$favoris_ids = [];
try {
    $stmt = $pdo->prepare("SELECT id_video FROM user_favoris WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $favoris_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-fr="BERTIN MOVIE - Vidéos" data-lang-en="BERTIN MOVIE - Videos">BERTIN MOVIE - Vidéos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        .filter-section {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: center;
        }

        .filter-section select,
        .filter-section input {
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        /* =========================================================
           SELECT STYLISÉ
           ========================================================= */
        .filter-section select {
            min-width: 160px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 12px 44px 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: inherit;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px 8px;
        }

        .filter-section select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .filter-section select option {
            padding: 10px 16px;
            background: var(--bg-card, #ffffff);
            color: var(--text-primary, #0f172a);
        }

        /* Dark mode - select */
        body.dark-mode .filter-section select {
            background-color: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2394a3b8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        }

        body.dark-mode .filter-section select option {
            background: #1e293b;
            color: #e2e8f0;
        }

        .filter-section input {
            flex: 1;
            min-width: 200px;
        }

        .filter-section input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
        }

        .filter-section .btn-search {
            padding: 10px 24px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }

        .filter-section .btn-search:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
        }

        .filter-section .btn-search:active {
            transform: scale(0.97);
        }

        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .stats-row .stat-item {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stats-row .stat-item strong {
            color: var(--text-primary);
            font-size: 16px;
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

        .no-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #e5edff;
            color: #3b82f6;
            font-weight: 500;
            font-size: 14px;
            padding: 10px;
            text-align: center;
        }

        .no-image i {
            font-size: 36px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        .duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination .page {
            padding: 8px 16px;
            border-radius: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .pagination .page:hover {
            background: var(--primary-light);
            color: #fff;
            border-color: var(--primary-light);
        }

        .pagination .page.active {
            background: var(--primary-light);
            color: #fff;
            border-color: var(--primary-light);
        }

        /* =========================================================
           SELECT PERSONNALISÉ (style uniquement pour le custom select)
           ========================================================= */
        .custom-select-wrapper {
            position: relative;
            min-width: 160px;
            flex: 0 1 auto;
        }

        .custom-select-wrapper select {
            display: none;
        }

        .custom-select-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 44px 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            min-height: 48px;
            position: relative;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px 8px;
        }

        body.dark-mode .custom-select-trigger {
            background-color: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2394a3b8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        }

        .custom-select-trigger:hover {
            border-color: #3b82f6;
        }

        .custom-select-trigger .selected-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .custom-select-options {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--bg-card, #ffffff);
            border: 2px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
        }

        body.dark-mode .custom-select-options {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .custom-select-options.open {
            max-height: 300px;
            opacity: 1;
            visibility: visible;
            overflow-y: auto;
        }

        .custom-select-options .option {
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-primary, #0f172a);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color, #f1f5f9);
        }

        body.dark-mode .custom-select-options .option {
            color: #e2e8f0;
            border-color: #334155;
        }

        .custom-select-options .option:last-child {
            border-bottom: none;
        }

        .custom-select-options .option:hover {
            background: rgba(59, 130, 246, 0.08);
            color: #3b82f6;
        }

        body.dark-mode .custom-select-options .option:hover {
            background: rgba(59, 130, 246, 0.12);
        }

        .custom-select-options .option.active {
            background: #3b82f6;
            color: #ffffff;
        }

        body.dark-mode .custom-select-options .option.active {
            background: #3b82f6;
            color: #ffffff;
        }

        .custom-select-options .option .check-icon {
            float: right;
            color: #3b82f6;
        }

        body.dark-mode .custom-select-options .option .check-icon {
            color: #60a5fa;
        }

        .custom-select-options .option.active .check-icon {
            color: #ffffff;
        }

        /* =========================================================
           TRADUCTION - HIDE ELEMENTS WITH DATA-LANG
           ========================================================= */
        [data-lang-fr][data-lang-en] {
            transition: opacity 0.2s ease;
        }

        /* =========================================================
           RESPONSIVE - MOBILE
           ========================================================= */
        @media (max-width: 600px) {
            .custom-select-wrapper {
                min-width: 100%;
                width: 100%;
            }

            .custom-select-trigger {
                padding: 14px 44px 14px 16px;
                font-size: 16px;
                min-height: 52px;
            }

            .custom-select-options .option {
                padding: 14px 16px;
                font-size: 16px;
            }

            .filter-section input {
                font-size: 16px;
                padding: 14px 16px;
            }
        }

        @media (max-width: 480px) {
            .custom-select-trigger {
                padding: 12px 40px 12px 14px;
                font-size: 15px;
                min-height: 46px;
            }

            .custom-select-options .option {
                padding: 12px 14px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-video"></i>
                <span data-lang-fr="Toutes les vidéos" data-lang-en="All videos">Toutes les vidéos</span>
                <span style="color:var(--text-secondary);font-weight:400;font-size:18px;">(<?= count($videos) ?>)</span>
            </h1>
        </div>

        <!-- CATÉGORIES (barre à défiler, style YouTube — visible sur téléphone) -->
        <div class="chips-bar" id="chipsBar">
            <button type="button" class="chip active" data-cat="" data-lang-fr="Tout" data-lang-en="All">Tout</button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="chip"
                    data-cat="<?= (int) $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom_categorie']) ?></button>
            <?php endforeach; ?>
        </div>

        <!-- FILTRES -->
        <div class="filter-section">
            <!-- SELECT PERSONNALISÉ -->
            <div class="custom-select-wrapper" id="customSelectWrapper">
                <div class="custom-select-trigger" id="customSelectTrigger">
                    <span class="selected-text" data-lang-fr="Toutes les catégories" data-lang-en="All categories">
                        Toutes les catégories
                    </span>
                </div>
                <div class="custom-select-options" id="customSelectOptions">
                    <div class="option active" data-value="" data-lang-fr="Toutes les catégories"
                        data-lang-en="All categories">
                        Toutes les catégories
                        <span class="check-icon"><i class="fas fa-check"></i></span>
                    </div>
                    <?php foreach ($categories as $cat): ?>
                        <div class="option" data-value="<?= $cat['id_categorie'] ?>">
                            <?= htmlspecialchars($cat['nom_categorie']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Select caché pour la compatibilité -->
                <select id="categorieFilter" style="display:none;">
                    <option value="" data-lang-fr="Toutes les catégories" data-lang-en="All categories">
                        Toutes les catégories
                    </option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id_categorie'] ?>">
                            <?= htmlspecialchars($cat['nom_categorie']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <select id="sortSelect" aria-label="Trier par" title="Trier par">
                <option value="recent" data-lang-fr="Trier : plus récents" data-lang-en="Sort: newest">Trier : plus
                    récents</option>
                <option value="ancien" data-lang-fr="Trier : plus anciens" data-lang-en="Sort: oldest">Trier : plus
                    anciens</option>
                <option value="az" data-lang-fr="Trier : nom A → Z" data-lang-en="Sort: name A → Z">Trier : nom A → Z
                </option>
                <option value="za" data-lang-fr="Trier : nom Z → A" data-lang-en="Sort: name Z → A">Trier : nom Z → A
                </option>
                <option value="vus" data-lang-fr="Trier : plus vus" data-lang-en="Sort: most viewed">Trier : plus vus
                </option>
            </select>

            <input type="text" id="searchInput" placeholder="Rechercher un film..."
                data-lang-fr-placeholder="Rechercher un film..." data-lang-en-placeholder="Search for a movie...">
            <button class="btn-search" onclick="filterVideos()">
                <i class="fas fa-search"></i>
                <span data-lang-fr="Rechercher" data-lang-en="Search">Rechercher</span>
            </button>
        </div>

        <!-- STATISTIQUES -->
        <div class="stats-row">
            <span class="stat-item">
                <i class="fas fa-video" style="color:var(--primary-light);"></i>
                <span data-lang-fr="Total" data-lang-en="Total">Total</span> :
                <strong><?= count($videos) ?></strong>
                <span data-lang-fr="vidéos" data-lang-en="videos">vidéos</span>
            </span>
            <span class="stat-item">
                <i class="fas fa-eye" style="color:#8b5cf6;"></i>
                <span data-lang-fr="Vues totales" data-lang-en="Total views">Vues totales</span> :
                <strong><?= array_sum(array_column($videos, 'views')) ?></strong>
            </span>
            <span class="stat-item">
                <i class="fas fa-clock" style="color:#06b6d4;"></i>
                <span data-lang-fr="Dernière mise à jour" data-lang-en="Last update">Dernière mise à jour</span> :
                <strong><?= date('d/m/Y') ?></strong>
            </span>
        </div>

        <!-- GRILLE -->
        <div class="movies-grid" id="videoGrid">
            <?php foreach ($videos as $film): ?>
                <div class="movie-card" data-categorie="<?= $film['id_categorie'] ?>"
                    data-titre="<?= htmlspecialchars(mb_strtolower($film['nom_film'])) ?>"
                    data-nom="<?= htmlspecialchars($film['nom_film']) ?>"
                    data-date="<?= (int) strtotime($film['date_upload'] ?? '') ?>"
                    data-views="<?= (int) ($film['views'] ?? 0) ?>">
                    <div class="thumb">
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

        <?php if (count($videos) == 0): ?>
            <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <h3 data-lang-fr="Aucune vidéo disponible" data-lang-en="No video available">
                    Aucune vidéo disponible
                </h3>
                <p data-lang-fr="Revenez plus tard pour découvrir de nouveaux films."
                    data-lang-en="Come back later to discover new movies.">
                    Revenez plus tard pour découvrir de nouveaux films.
                </p>
            </div>
        <?php endif; ?>

        <!-- PAGINATION -->
        <div class="pagination">
            <span class="page active">1</span>
            <span class="page">2</span>
            <span class="page">3</span>
            <span class="page">4</span>
            <span class="page"><i class="fas fa-chevron-right"></i></span>
        </div>
    </div>

    <script>
        // ============================================
        // SELECT PERSONNALISÉ
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            const trigger = document.getElementById('customSelectTrigger');
            const optionsContainer = document.getElementById('customSelectOptions');
            const hiddenSelect = document.getElementById('categorieFilter');
            const selectedText = trigger.querySelector('.selected-text');

            // Ouvrir/fermer le select
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                optionsContainer.classList.toggle('open');
            });

            // Sélectionner une option
            optionsContainer.querySelectorAll('.option').forEach(function (option) {
                option.addEventListener('click', function (e) {
                    e.stopPropagation();

                    // Mettre à jour le texte affiché
                    const value = this.dataset.value;
                    const text = this.textContent.trim().replace(/✓$/, '').trim();
                    selectedText.textContent = text;

                    // Mettre à jour le select caché
                    hiddenSelect.value = value;

                    // Mettre à jour les classes actives
                    optionsContainer.querySelectorAll('.option').forEach(function (opt) {
                        opt.classList.remove('active');
                        const checkIcon = opt.querySelector('.check-icon');
                        if (checkIcon) checkIcon.remove();
                    });
                    this.classList.add('active');

                    // Ajouter l'icône de check
                    const checkSpan = document.createElement('span');
                    checkSpan.className = 'check-icon';
                    checkSpan.innerHTML = '<i class="fas fa-check"></i>';
                    this.appendChild(checkSpan);

                    // Fermer la liste
                    optionsContainer.classList.remove('open');

                    // Déclencher le filtrage
                    filterVideos();
                });
            });

            // Fermer quand on clique ailleurs
            document.addEventListener('click', function () {
                optionsContainer.classList.remove('open');
            });

            // Empêcher la fermeture quand on clique à l'intérieur des options
            optionsContainer.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });

        // ============================================
        // FILTRES
        // ============================================
        function filterVideos() {
            const categorie = document.getElementById('categorieFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.movie-card');

            cards.forEach(card => {
                const cat = card.dataset.categorie;
                const titre = card.dataset.titre;
                let show = true;

                if (categorie && cat != categorie) show = false;
                if (search && !titre.includes(search)) show = false;

                card.style.display = show ? '' : 'none';
            });
        }

        // ============================================
        // TRI (liste fixe de tris autorisés, sans rechargement)
        // ============================================
        (function () {
            var cmp = {
                recent: function (a, b) { return b.dataset.date - a.dataset.date; },
                ancien: function (a, b) { return a.dataset.date - b.dataset.date; },
                az: function (a, b) { return a.dataset.nom.localeCompare(b.dataset.nom, 'fr', { sensitivity: 'base' }); },
                za: function (a, b) { return b.dataset.nom.localeCompare(a.dataset.nom, 'fr', { sensitivity: 'base' }); },
                vus: function (a, b) { return (b.dataset.views - a.dataset.views) || a.dataset.nom.localeCompare(b.dataset.nom, 'fr'); }
            };
            function sortVideos(key) {
                if (!cmp[key]) { key = 'recent'; }
                var grid = document.getElementById('videoGrid');
                if (!grid) { return; }
                Array.prototype.slice.call(grid.querySelectorAll('.movie-card')).sort(cmp[key]).forEach(function (c) { grid.appendChild(c); });
                try { localStorage.setItem('bm_sort_video', key); } catch (e) { }
            }
            document.addEventListener('DOMContentLoaded', function () {
                var sel = document.getElementById('sortSelect');
                if (!sel) { return; }
                var saved = 'recent';
                try { saved = localStorage.getItem('bm_sort_video') || 'recent'; } catch (e) { }
                if (cmp[saved]) { sel.value = saved; if (saved !== 'recent') { sortVideos(saved); } }
                sel.addEventListener('change', function () { sortVideos(sel.value); });
            });
        })();

        document.getElementById('searchInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterVideos();
            }
        });

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
        // PAGINATION
        // ============================================
        document.querySelectorAll('.pagination .page').forEach(page => {
            page.addEventListener('click', function () {
                document.querySelectorAll('.pagination .page').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
            });
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

        const originalApplyLanguage = window.applyLanguage;
        window.applyLanguage = function (lang) {
            if (typeof originalApplyLanguage === 'function') {
                originalApplyLanguage(lang);
            }
            applyLanguageForPlaceholders(lang);
        };

        document.addEventListener('DOMContentLoaded', function () {
            const currentLang = localStorage.getItem('lang') || 'fr';
            applyLanguageForPlaceholders(currentLang);
        });
    </script>

    <script>
        // Barre de catégories : réutilise le filtre existant (filterVideos)
        (function () {
            var bar = document.getElementById('chipsBar');
            if (!bar) { return; }
            bar.addEventListener('click', function (e) {
                var chip = e.target.closest('.chip');
                if (!chip) { return; }
                bar.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                document.getElementById('categorieFilter').value = chip.dataset.cat;
                filterVideos();
                chip.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            });
        })();
    </script>
</body>

</html>