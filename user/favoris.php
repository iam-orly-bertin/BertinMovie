<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_session.php';

if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Créer la table si elle n'existe pas
try {
    $pdo->query("SELECT 1 FROM user_favoris LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_favoris (
        id_favori INT AUTO_INCREMENT PRIMARY KEY,
        id_utilisateur INT NOT NULL,
        id_video INT NOT NULL,
        date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favori (id_utilisateur, id_video)
    )");
}

$favoris = $pdo->prepare("
    SELECT f.*, v.id_video, v.thumbnail, v.views, c.nom_categorie
    FROM user_favoris uf
    INNER JOIN video_film v ON uf.id_video = v.id_video
    INNER JOIN film f ON v.id_film = f.id_film
    LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
    WHERE uf.id_utilisateur = ? AND f.is_deleted = 0 AND f.est_archive = 0
    ORDER BY uf.date_ajout DESC
");
$favoris->execute([$user_id]);
$videos = $favoris->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-lang-fr="Mes favoris" data-lang-en="My favorites">Mes favoris</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/film_web_dy/user/css/style-user.css">
    <style>
        /* =========================================================
           FAVORIS.PHP - DESIGN MODERNE
           ========================================================= */

        .favoris-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 10px 16px 100px;
        }

        /* =========================================================
           PAGE HEADER
           ========================================================= */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .page-header h1 i {
            color: #3b82f6;
            font-size: 26px;
        }

        .page-header h1 span {
            color: var(--text-secondary, #64748b);
            font-weight: 400;
            font-size: 18px;
        }

        body.dark-mode .page-header h1 {
            color: #e2e8f0;
        }

        body.dark-mode .page-header h1 span {
            color: #94a3b8;
        }

        /* =========================================================
           GRILLE
           ========================================================= */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        /* =========================================================
           CARTE FILM
           ========================================================= */
        .movie-card {
            background: var(--bg-card, #ffffff);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .movie-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.12);
            border-color: #3b82f6;
        }

        body.dark-mode .movie-card {
            background: #1e293b;
            border-color: #334155;
        }

        body.dark-mode .movie-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.15);
        }

        /* =========================================================
           MINIATURE
           ========================================================= */
        .thumb {
            position: relative;
            padding-top: 56.25%;
            background: #f1f5f9;
            overflow: hidden;
        }

        body.dark-mode .thumb {
            background: #0f172a;
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

        body.dark-mode .no-image {
            background: #1e293b;
            color: #60a5fa;
        }

        .no-image i {
            font-size: 36px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        /* =========================================================
           DURÉE
           ========================================================= */
        .duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        /* =========================================================
           FAVORITE BUTTON - BLEU UNIFORME (AVEC !important)
           ========================================================= */
        .favorite-btn {
            position: absolute !important;
            top: 12px !important;
            right: 12px !important;
            background: rgba(0, 0, 0, 0.5) !important;
            border: none !important;
            border-radius: 50% !important;
            width: 38px !important;
            height: 38px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            color: #fff !important;
            font-size: 16px !important;
            backdrop-filter: blur(4px) !important;
            z-index: 10 !important;
            padding: 0 !important;
        }

        .favorite-btn:hover {
            transform: scale(1.15) !important;
            background: rgba(0, 0, 0, 0.7) !important;
        }

        .favorite-btn.active {
            background: #3b82f6 !important;
            color: #fff !important;
            box-shadow: 0 2px 12px rgba(59, 130, 246, 0.4) !important;
        }

        .favorite-btn.active:hover {
            background: #2563eb !important;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.5) !important;
            transform: scale(1.15) !important;
        }

        .favorite-btn i {
            pointer-events: none !important;
        }

        /* =========================================================
           INFOS
           ========================================================= */
        .info {
            padding: 16px 16px 18px;
        }

        .info h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary, #0f172a);
            margin: 0 0 8px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        body.dark-mode .info h4 {
            color: #e2e8f0;
        }

        .info .meta {
            display: flex;
            gap: 14px;
            font-size: 12px;
            color: var(--text-secondary, #64748b);
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        body.dark-mode .info .meta {
            color: #94a3b8;
        }

        .info .meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .info .meta i {
            color: #3b82f6;
            font-size: 12px;
        }

        /* =========================================================
           BOUTON REGARDER
           ========================================================= */
        .btn-play {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 16px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-play:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35);
        }

        .btn-play:active {
            transform: scale(0.97);
        }

        /* =========================================================
           ÉTAT VIDE
           ========================================================= */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card, #ffffff);
            border-radius: 20px;
            border: 1px solid var(--border-color, #e2e8f0);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        body.dark-mode .empty-state {
            background: #1e293b;
            border-color: #334155;
        }

        .empty-state i {
            font-size: 56px;
            color: #94a3b8;
            margin-bottom: 16px;
            display: block;
        }

        body.dark-mode .empty-state i {
            color: #475569;
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            margin-bottom: 8px;
        }

        body.dark-mode .empty-state h3 {
            color: #e2e8f0;
        }

        .empty-state p {
            color: var(--text-secondary, #64748b);
            max-width: 400px;
            margin: 0 auto 20px;
            font-size: 15px;
        }

        body.dark-mode .empty-state p {
            color: #94a3b8;
        }

        .empty-state .btn-play {
            display: inline-flex;
            width: auto;
            padding: 12px 32px;
        }

        /* =========================================================
           ANIMATION
           ========================================================= */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .movie-card {
            animation: slideUp 0.4s ease-out both;
        }

        .movie-card:nth-child(1) {
            animation-delay: 0.00s;
        }

        .movie-card:nth-child(2) {
            animation-delay: 0.05s;
        }

        .movie-card:nth-child(3) {
            animation-delay: 0.10s;
        }

        .movie-card:nth-child(4) {
            animation-delay: 0.15s;
        }

        .movie-card:nth-child(5) {
            animation-delay: 0.20s;
        }

        .movie-card:nth-child(6) {
            animation-delay: 0.25s;
        }

        .movie-card:nth-child(7) {
            animation-delay: 0.30s;
        }

        .movie-card:nth-child(8) {
            animation-delay: 0.35s;
        }

        .movie-card:nth-child(9) {
            animation-delay: 0.40s;
        }

        .movie-card:nth-child(10) {
            animation-delay: 0.45s;
        }

        /* =========================================================
           RESPONSIVE - MOBILE
           ========================================================= */
        @media (max-width: 768px) {
            .favoris-container {
                padding: 8px 12px 90px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .page-header h1 i {
                font-size: 22px;
            }

            .movies-grid {
                grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
                gap: 16px;
            }

            .info {
                padding: 12px 14px 14px;
            }

            .info h4 {
                font-size: 14px;
            }

            .btn-play {
                font-size: 13px;
                padding: 8px 12px;
            }

            /* BOUTON FAVORI - MOBILE */
            .favorite-btn {
                width: 34px !important;
                height: 34px !important;
                font-size: 14px !important;
                top: 10px !important;
                right: 10px !important;
            }

            .favorite-btn.active {
                background: #3b82f6 !important;
                color: #fff !important;
                box-shadow: 0 2px 12px rgba(59, 130, 246, 0.4) !important;
            }
        }

        @media (max-width: 480px) {
            .favoris-container {
                padding: 6px 8px 80px;
            }

            .page-header h1 {
                font-size: 17px;
            }

            .page-header h1 i {
                font-size: 18px;
            }

            .page-header h1 span {
                font-size: 14px;
            }

            .movies-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .info {
                padding: 10px 10px 12px;
            }

            .info h4 {
                font-size: 12px;
                margin-bottom: 4px;
            }

            .info .meta {
                font-size: 10px;
                gap: 8px;
                margin-bottom: 8px;
                flex-direction: column;
                gap: 2px;
            }

            .info .meta i {
                font-size: 10px;
            }

            .btn-play {
                font-size: 11px;
                padding: 6px 10px;
                border-radius: 8px;
            }

            .btn-play i {
                font-size: 10px;
            }

            /* BOUTON FAVORI - PETIT MOBILE */
            .favorite-btn {
                width: 28px !important;
                height: 28px !important;
                font-size: 11px !important;
                top: 8px !important;
                right: 8px !important;
            }

            .favorite-btn.active {
                background: #3b82f6 !important;
                box-shadow: 0 2px 12px rgba(59, 130, 246, 0.4) !important;
            }

            .duration {
                font-size: 10px;
                padding: 2px 8px;
                bottom: 8px;
                right: 8px;
            }

            .empty-state {
                padding: 40px 16px;
            }

            .empty-state i {
                font-size: 40px;
            }

            .empty-state h3 {
                font-size: 18px;
            }

            .empty-state p {
                font-size: 13px;
            }

            .empty-state .btn-play {
                font-size: 13px;
                padding: 10px 24px;
            }
        }

        @media (max-width: 380px) {
            .favoris-container {
                padding: 4px 4px 70px;
            }

            .page-header h1 {
                font-size: 15px;
            }

            .movies-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .info {
                padding: 6px 8px 10px;
            }

            .info h4 {
                font-size: 11px;
            }

            .info .meta {
                font-size: 9px;
            }

            .btn-play {
                font-size: 10px;
                padding: 4px 8px;
                border-radius: 6px;
            }

            /* BOUTON FAVORI - TRÈS PETIT MOBILE */
            .favorite-btn {
                width: 24px !important;
                height: 24px !important;
                font-size: 10px !important;
                top: 6px !important;
                right: 6px !important;
            }

            .favorite-btn.active {
                background: #3b82f6 !important;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/menu-user.php'; ?>

    <div class="favoris-container">
        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <h1>
                <i class="fas fa-heart"></i>
                <span data-lang-fr="Mes favoris" data-lang-en="My favorites">Mes favoris</span>
                <span>(
                    <?= count($videos) ?>)
                </span>
            </h1>
        </div>

        <!-- ===== GRILLE ===== -->
        <?php if (count($videos) > 0): ?>
            <div class="movies-grid">
                <?php foreach ($videos as $film): ?>
                    <div class="movie-card">
                        <div class="thumb">
                            <?php if (!empty($film['thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($film['thumbnail']) ?>"
                                    alt="<?= htmlspecialchars($film['nom_film']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-film"></i>
                                    <span>
                                        <?= htmlspecialchars($film['nom_film']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <span class="duration">
                                <?= htmlspecialchars($film['duree'] ?? '00:00') ?>
                            </span>
                            <button class="favorite-btn active"
                                onclick="event.preventDefault(); removeFavori(<?= $film['id_video'] ?>, this)"
                                title="Retirer des favoris">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                        <div class="info">
                            <h4>
                                <?= htmlspecialchars($film['nom_film']) ?>
                            </h4>
                            <div class="meta">
                                <span><i class="fas fa-eye"></i>
                                    <?= number_format($film['views'] ?? 0, 0, ',', ' ') ?>
                                </span>
                                <span><i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($film['nom_categorie'] ?? 'Non catégorisé') ?>
                                </span>
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
                <i class="fas fa-heart"></i>
                <h3 data-lang-fr="Aucun favori" data-lang-en="No favorites">Aucun favori</h3>
                <p data-lang-fr="Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo."
                    data-lang-en="Add videos to your favorites by clicking the ❤️ on a video.">
                    Ajoutez des vidéos à vos favoris en cliquant sur le ❤️ sur une vidéo.
                </p>
                <a href="video.php" class="btn-play">
                    <i class="fas fa-film"></i>
                    <span data-lang-fr="Découvrir des vidéos" data-lang-en="Discover videos">Découvrir des vidéos</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ============================================
        // RETIRER DES FAVORIS AVEC ALERTE STYLISÉE
        // ============================================
        function removeFavori(idVideo, btn) {
            // Utiliser la fonction confirmLogout de menu-user.php comme base
            // Mais avec un message personnalisé
            const lang = localStorage.getItem('lang') || 'fr';
            const messages = {
                fr: {
                    title: 'Retirer des favoris',
                    confirm: 'Voulez-vous vraiment retirer cette vidéo de vos favoris ?',
                    yes: 'Oui, retirer',
                    no: 'Annuler'
                },
                en: {
                    title: 'Remove from favorites',
                    confirm: 'Do you really want to remove this video from your favorites?',
                    yes: 'Yes, remove',
                    no: 'Cancel'
                }
            };

            const msg = messages[lang] || messages.fr;

            // Créer l'overlay
            const overlay = document.createElement('div');
            overlay.className = 'custom-confirm-overlay';
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

            const isDarkMode = document.body.classList.contains('light-mode') ? false : true;

            const modal = document.createElement('div');
            modal.style.cssText = `
                background: ${isDarkMode ? '#1e293b' : '#ffffff'};
                border-radius: 20px;
                padding: 32px 28px 28px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                text-align: center;
                animation: slideUp 0.3s ease;
                border: 1px solid ${isDarkMode ? '#334155' : '#e2e8f0'};
                position: relative;
            `;

            modal.innerHTML = `
                <div style="margin-bottom: 16px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: #dbeafe; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-heart" style="font-size: 28px; color: #3b82f6;"></i>
                    </div>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; color: ${isDarkMode ? '#e2e8f0' : '#0f172a'}; margin-bottom: 8px;">
                    ${msg.title}
                </h2>
                <p style="font-size: 15px; color: ${isDarkMode ? '#94a3b8' : '#475569'}; margin-bottom: 24px; line-height: 1.5;">
                    ${msg.confirm}
                </p>
                <div style="display: flex; gap: 10px;">
                    <button onclick="this.closest('.custom-confirm-overlay').remove()" 
                            style="flex: 1; padding: 12px; border: 2px solid ${isDarkMode ? '#334155' : '#e2e8f0'}; border-radius: 12px; background: transparent; color: ${isDarkMode ? '#94a3b8' : '#475569'}; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">
                        ${msg.no}
                    </button>
                    <button onclick="confirmRemoveFavori(${idVideo}, this)" 
                            style="flex: 1; padding: 12px; border: none; border-radius: 12px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #ffffff; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-heart"></i> ${msg.yes}
                    </button>
                </div>
            `;

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            // Fermer en cliquant sur l'overlay
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.remove();
                }
            });

            // Fermer avec Escape
            const closeHandler = function (e) {
                if (e.key === 'Escape') {
                    overlay.remove();
                    document.removeEventListener('keydown', closeHandler);
                }
            };
            document.addEventListener('keydown', closeHandler);
        }

        // ============================================
        // CONFIRMER LE RETRAIT DES FAVORIS
        // ============================================
        function confirmRemoveFavori(idVideo, btn) {
            // Récupérer l'overlay
            const overlay = btn.closest('.custom-confirm-overlay');

            fetch('/film_web_dy/user/ajax_favori.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_video=' + idVideo + '&action=remove'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fermer le modal
                        if (overlay) overlay.remove();

                        // Trouver la carte et la supprimer avec animation
                        const cards = document.querySelectorAll('.movie-card');
                        let targetCard = null;
                        cards.forEach(card => {
                            const btn = card.querySelector('.favorite-btn');
                            if (btn && btn.getAttribute('onclick').includes(idVideo)) {
                                targetCard = card;
                            }
                        });

                        if (targetCard) {
                            targetCard.style.transition = 'all 0.3s ease';
                            targetCard.style.transform = 'scale(0.9)';
                            targetCard.style.opacity = '0';
                            setTimeout(() => {
                                targetCard.remove();
                                // Mettre à jour le compteur
                                const count = document.querySelector('.page-header h1 span:last-child');
                                if (count) {
                                    const current = parseInt(count.textContent.replace(/[()]/g, ''));
                                    count.textContent = '(' + (current - 1) + ')';
                                }
                                // Vérifier s'il reste des films
                                const grid = document.querySelector('.movies-grid');
                                if (grid && grid.children.length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // ============================================
        // TRADUCTION - GESTION DES TEXTES
        // ============================================
        function applyLanguageForFavoris(lang) {
            // Les textes avec data-lang-fr et data-lang-en sont gérés par menu-user.js
        }

        // Surcharger la fonction applyLanguage si elle existe
        const originalApplyLanguage = window.applyLanguage;
        window.applyLanguage = function (lang) {
            if (typeof originalApplyLanguage === 'function') {
                originalApplyLanguage(lang);
            }
            applyLanguageForFavoris(lang);
        };

        // Initialiser au chargement
        document.addEventListener('DOMContentLoaded', function () {
            const currentLang = localStorage.getItem('lang') || 'fr';
            if (typeof applyLanguageForFavoris === 'function') {
                applyLanguageForFavoris(currentLang);
            }
        });
    </script>

</body>

</html>