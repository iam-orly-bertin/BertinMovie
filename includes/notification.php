<?php
// =========================================================
// notification.php - Système complet de notifications
// Version avec style compact et responsive
// =========================================================

// =========================================================
// 1. VÉRIFICATION/CREATION DE LA TABLE
// =========================================================

function verifierTableHistorique($pdo)
{
    try {
        $pdo->query("SELECT 1 FROM historique_actions LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS historique_actions (
                id_action INT PRIMARY KEY AUTO_INCREMENT,
                id_utilisateur INT NOT NULL,
                type_action VARCHAR(50) NOT NULL,
                entite VARCHAR(50) NOT NULL,
                entite_id INT NULL,
                nom_entite VARCHAR(255) NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                lu BOOLEAN DEFAULT 0,
                date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_utilisateur (id_utilisateur),
                INDEX idx_type (type_action),
                INDEX idx_entite (entite),
                INDEX idx_date (date_action),
                INDEX idx_lu (lu)
            )
        ");
    }
}

// =========================================================
// 2. FONCTIONS DE GESTION DE L'HISTORIQUE
// =========================================================

function addHistoriqueAction($pdo, $id_utilisateur, $type_action, $entite, $entite_id = null, $nom_entite = null, $details = null)
{
    if (!$id_utilisateur)
        return false;

    verifierTableHistorique($pdo);

    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO historique_actions 
            (id_utilisateur, type_action, entite, entite_id, nom_entite, details, ip_address, user_agent, date_action) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_utilisateur,
            $type_action,
            $entite,
            $entite_id,
            $nom_entite,
            $details,
            $ip_address,
            $user_agent
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur addHistoriqueAction: " . $e->getMessage());
        return false;
    }
}

function getDernieresNotifications($pdo, $limit = 10)
{
    try {
        $stmt = $pdo->prepare("
            SELECT ha.*, u.nom_utilisateur 
            FROM historique_actions ha
            INNER JOIN utilisateur u ON ha.id_utilisateur = u.id_utilisateur
            ORDER BY ha.date_action DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getNotificationsNonLues($pdo, $id_utilisateur)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM historique_actions 
            WHERE id_utilisateur = ? AND lu = 0
        ");
        $stmt->execute([$id_utilisateur]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function marquerNotificationLue($pdo, $id_action)
{
    try {
        $stmt = $pdo->prepare("UPDATE historique_actions SET lu = 1 WHERE id_action = ?");
        return $stmt->execute([$id_action]);
    } catch (PDOException $e) {
        return false;
    }
}

function marquerToutesNotificationsLues($pdo, $id_utilisateur)
{
    try {
        $stmt = $pdo->prepare("UPDATE historique_actions SET lu = 1 WHERE id_utilisateur = ?");
        return $stmt->execute([$id_utilisateur]);
    } catch (PDOException $e) {
        return false;
    }
}

// =========================================================
// 3. FONCTIONS D'ICÔNES ET COULEURS
// =========================================================

function getIconForAction($type_action)
{
    $icons = [
        'ajout' => 'fa-plus-circle',
        'modification' => 'fa-edit',
        'suppression' => 'fa-trash-alt',
        'export' => 'fa-file-export',
        'connexion' => 'fa-sign-in-alt',
        'deconnexion' => 'fa-sign-out-alt',
        'like' => 'fa-heart',
        'commentaire' => 'fa-comment',
        'visionnage' => 'fa-eye'
    ];
    return $icons[$type_action] ?? 'fa-circle';
}

function getColorForAction($type_action)
{
    $colors = [
        'ajout' => '#22c55e',
        'modification' => '#3b82f6',
        'suppression' => '#ef4444',
        'export' => '#8b5cf6',
        'connexion' => '#22c55e',
        'deconnexion' => '#f59e0b',
        'like' => '#ec4899',
        'commentaire' => '#06b6d4',
        'visionnage' => '#64748b'
    ];
    return $colors[$type_action] ?? '#64748b';
}

function getLabelForAction($type_action)
{
    $labels = [
        'ajout' => 'Ajout',
        'modification' => 'Modification',
        'suppression' => 'Suppression',
        'export' => 'Export',
        'connexion' => 'Connexion',
        'deconnexion' => 'Déconnexion',
        'like' => 'Like',
        'commentaire' => 'Commentaire',
        'visionnage' => 'Visionnage'
    ];
    return $labels[$type_action] ?? ucfirst($type_action);
}

function getEntiteIcon($entite)
{
    $icons = [
        'film' => 'fa-film',
        'acteur' => 'fa-user',
        'realisateur' => 'fa-user-tie',
        'categorie' => 'fa-tags',
        'pays' => 'fa-flag',
        'langue' => 'fa-language',
        'studio' => 'fa-building',
        'utilisateur' => 'fa-user-cog',
        'like' => 'fa-heart',
        'commentaire' => 'fa-comment',
        'historique' => 'fa-history',
        'profil' => 'fa-id-card',
        'video' => 'fa-video'
    ];
    return $icons[$entite] ?? 'fa-circle';
}

// =========================================================
// 4. FONCTION POUR AFFICHER LES NOTIFICATIONS (SESSION)
// =========================================================

function showNotif($type, $message, $title = null)
{
    $titles = [
        'success' => '✅ Succès',
        'error' => '❌ Erreur',
        'warning' => '⚠️ Attention',
        'info' => 'ℹ️ Information'
    ];

    $_SESSION['notif'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title ?? $titles[$type] ?? 'Notification'
    ];
}

// =========================================================
// 5. RENDU HTML DES NOTIFICATIONS (STYLE COMPACT)
// =========================================================

function renderNotifications()
{
    ?>
    <style>
        /* =========================================================
                   NOTIFICATIONS - STYLE ULTRA COMPACT
                ========================================================= */
        .notification-container {
            position: fixed;
            top: 78px;
            right: 12px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-width: 340px;
            width: 100%;
            pointer-events: none;
            /* IMPORTANT: z-index plus élevé que la bottom-nav */
        }

        .notification-item {
            background: #ffffff;
            border-radius: 10px;
            padding: 10px 14px;
            box-shadow: 0 3px 16px rgba(0, 0, 0, 0.10);
            border-left: 4px solid #3b82f6;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: auto;
            transition: all 0.2s ease;
            border: 1px solid #e8ecf0;
            max-width: 340px;
            min-height: 44px;
            flex-shrink: 0;
        }

        body.dark-mode .notification-item {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 3px 16px rgba(0, 0, 0, 0.3);
        }

        .notification-item:hover {
            transform: translateX(-3px);
        }

        /* Icône - TRÈS PETITE */
        .notification-item .notif-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
            flex-shrink: 0;
        }

        /* Contenu - TRÈS COMPACT */
        .notification-item .notif-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .notification-item .notif-title {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
            margin: 0;
        }

        body.dark-mode .notification-item .notif-title {
            color: #e2e8f0;
        }

        .notification-item .notif-message {
            font-size: 11px;
            color: #64748b;
            line-height: 1.3;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        body.dark-mode .notification-item .notif-message {
            color: #94a3b8;
        }

        .notification-item .notif-time {
            font-size: 9px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 3px;
            margin-top: 1px;
        }

        body.dark-mode .notification-item .notif-time {
            color: #64748b;
        }

        /* Bouton fermeture - TRÈS PETIT */
        .notification-item .notif-close-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 10px;
            padding: 2px;
            transition: all 0.2s ease;
            flex-shrink: 0;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -4px -4px 0 0;
            opacity: 0.5;
        }

        .notification-item .notif-close-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            opacity: 1;
        }

        body.dark-mode .notification-item .notif-close-btn {
            color: #64748b;
        }

        body.dark-mode .notification-item .notif-close-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        /* Types de notifications */
        .notification-item.notif-success {
            border-left-color: #22c55e;
        }

        .notification-item.notif-success .notif-icon {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .notification-item.notif-error {
            border-left-color: #ef4444;
        }

        .notification-item.notif-error .notif-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .notification-item.notif-warning {
            border-left-color: #f59e0b;
        }

        .notification-item.notif-warning .notif-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .notification-item.notif-info {
            border-left-color: #3b82f6;
        }

        .notification-item.notif-info .notif-icon {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        /* Animations */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }

            to {
                opacity: 0;
                transform: translateX(40px) scale(0.95);
            }
        }

        .notification-item.removing {
            animation: slideOutRight 0.25s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        /* =========================================================
                   RESPONSIVE - MOBILE (CHANGEMENTS VISIBLES)
                ========================================================= */
        @media (max-width: 768px) {
            .notification-container {
                top: 72px;
                right: 8px;
                max-width: calc(100% - 16px);
                gap: 5px;
                /* z-index très élevé pour être au-dessus de tout */
                z-index: 999999 !important;
            }

            .notification-item {
                padding: 8px 12px;
                border-radius: 8px;
                border-left-width: 3px;
                gap: 8px;
                min-height: 38px;
                max-width: 100%;
            }

            .notification-item .notif-icon {
                width: 26px;
                height: 26px;
                font-size: 10px;
            }

            .notification-item .notif-title {
                font-size: 11px;
            }

            .notification-item .notif-message {
                font-size: 10px;
                -webkit-line-clamp: 1;
            }

            .notification-item .notif-time {
                font-size: 8px;
            }

            .notification-item .notif-close-btn {
                width: 18px;
                height: 18px;
                font-size: 8px;
                margin: -2px -2px 0 0;
            }
        }

        @media (max-width: 480px) {
            .notification-container {
                top: 64px;
                right: 6px;
                max-width: calc(100% - 12px);
                gap: 4px;
                z-index: 999999 !important;
            }

            .notification-item {
                padding: 6px 10px;
                border-radius: 6px;
                border-left-width: 3px;
                gap: 6px;
                min-height: 34px;
            }

            .notification-item .notif-icon {
                width: 22px;
                height: 22px;
                font-size: 9px;
            }

            .notification-item .notif-title {
                font-size: 10px;
            }

            .notification-item .notif-message {
                font-size: 9px;
                -webkit-line-clamp: 1;
            }

            .notification-item .notif-time {
                font-size: 8px;
            }

            .notification-item .notif-close-btn {
                width: 16px;
                height: 16px;
                font-size: 7px;
                margin: -2px -2px 0 0;
            }
        }

        @media (max-width: 380px) {
            .notification-container {
                top: 58px;
                right: 4px;
                max-width: calc(100% - 8px);
                gap: 3px;
                z-index: 999999 !important;
            }

            .notification-item {
                padding: 5px 8px;
                border-radius: 5px;
                border-left-width: 2px;
                gap: 5px;
                min-height: 30px;
            }

            .notification-item .notif-icon {
                width: 20px;
                height: 20px;
                font-size: 8px;
            }

            .notification-item .notif-title {
                font-size: 9px;
            }

            .notification-item .notif-message {
                font-size: 8px;
                -webkit-line-clamp: 1;
            }

            .notification-item .notif-time {
                font-size: 7px;
            }

            .notification-item .notif-close-btn {
                width: 14px;
                height: 14px;
                font-size: 6px;
            }
        }
    </style>

    <div class="notification-container" id="notificationContainer"></div>

    <script>
        // =========================================================
        // SYSTÈME DE NOTIFICATIONS - JAVASCRIPT
        // =========================================================
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('notificationContainer');
                if (!this.container) {
                    this.container = document.createElement('div');
                    this.container.className = 'notification-container';
                    this.container.id = 'notificationContainer';
                    document.body.appendChild(this.container);
                }
                this.count = 0;
            }

            show(message, title = 'Information', type = 'info', duration = 3500) {
                const id = 'notif-' + Date.now() + '-' + (this.count++);
                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                const icon = icons[type] || icons.info;
                const notif = document.createElement('div');
                notif.className = `notification-item notif-${type}`;
                notif.id = id;
                const time = new Date();
                const timeStr = time.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                notif.innerHTML = `
                    <div class="notif-icon"><i class="fas ${icon}"></i></div>
                    <div class="notif-content">
                        <div class="notif-title">${title}</div>
                        <div class="notif-message">${message}</div>
                        <div class="notif-time"><i class="fas fa-clock"></i> ${timeStr}</div>
                    </div>
                    <button class="notif-close-btn" onclick="window.notifSystem.close('${id}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                this.container.appendChild(notif);
                if (duration > 0) {
                    setTimeout(() => { this.close(id); }, duration);
                }
                return id;
            }

            close(id) {
                const notif = document.getElementById(id);
                if (notif) {
                    notif.classList.add('removing');
                    setTimeout(() => { if (notif.parentNode) notif.parentNode.removeChild(notif); }, 250);
                }
            }

            success(message, title = '✅ Succès', duration = 3000) {
                return this.show(message, title, 'success', duration);
            }
            error(message, title = '❌ Erreur', duration = 4000) {
                return this.show(message, title, 'error', duration);
            }
            warning(message, title = '⚠️ Attention', duration = 3000) {
                return this.show(message, title, 'warning', duration);
            }
            info(message, title = 'ℹ️ Information', duration = 2500) {
                return this.show(message, title, 'info', duration);
            }
        }

        // Instance globale
        if (typeof window.notifSystem === 'undefined') {
            window.notifSystem = new NotificationSystem();
        }

        // Fonctions helpers globales
        function showNotification(message, title, type, duration) {
            return window.notifSystem.show(message, title, type, duration);
        }
        function showSuccess(message) { return window.notifSystem.success(message); }
        function showError(message) { return window.notifSystem.error(message); }
        function showInfo(message) { return window.notifSystem.info(message); }
        function showWarning(message) { return window.notifSystem.warning(message); }

            <?php if (isset($_SESSION['notif'])): ?>
                    <?php
                    $notif = $_SESSION['notif'];
                    $type = $notif['type'] ?? 'info';
                    $message = addslashes($notif['message'] ?? '');
                    $title = addslashes($notif['title'] ?? 'Notification');
                    unset($_SESSION['notif']);
                    ?>
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    showNotification('<?= $message ?>', '<?= $title ?>', '<?= $type ?>');
                }, 500);
            });
            <?php endif; ?>
    </script>
    <?php
}

// =========================================================
// 6. FONCTION POUR LE BADGE DANS LE MENU
// =========================================================

function getNotifBadge($pdo, $id_utilisateur)
{
    $count = getNotificationsNonLues($pdo, $id_utilisateur);
    if ($count > 0) {
        return '<span class="badge-count" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;line-height:1.4;">' . $count . '</span>';
    }
    return '';
}
?>