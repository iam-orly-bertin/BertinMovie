<?php
/**
 * admin/support_api.php : API du chat support côté admin (peut écrire à tous les utilisateurs)
 *   GET  ?action=count
 *   GET  ?action=conversations&q=      -> conversations existantes (+ non lus)
 *   GET  ?action=users&q=              -> recherche de n'importe quel utilisateur (démarrer une conversation)
 *   GET  ?action=poll&user=ID&after=ID -> messages de la conversation (marque les messages « user » comme lus)
 *   POST action=send, user=ID, contenu, fichier + X-CSRF-Token
 */
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/support_lib.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    support_json(['error' => 'session'], 401);
}
require_once __DIR__ . '/../includes/auth_check.php';

$aid = (int) $_SESSION['user_id'];
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($isPost) {
    support_check_post_size();
}
$action = $isPost ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

function support_display(array $u)
{
    $n = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    return $n !== '' ? $n : $u['nom_utilisateur'];
}

try {
    support_ensure_table($pdo);
    $csrfOk = $isPost ? support_csrf_ok() : true;
    session_write_close();

    if ($action === 'count') {
        support_json(['unread' => support_unread_for_admin($pdo)]);
    }

    if ($action === 'conversations') {
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = "SELECT u.id_utilisateur AS id, u.nom_utilisateur, u.prenom, u.nom,
                       m.id AS last_id, m.sender AS last_sender, m.contenu AS last_text, m.fichier_nom AS last_file, m.date_envoi AS last_t,
                       (SELECT COUNT(*) FROM support_messages x WHERE x.id_user = u.id_utilisateur AND x.sender = 'user' AND x.lu = 0) AS unread
                FROM utilisateur u
                JOIN support_messages m ON m.id = (SELECT MAX(id) FROM support_messages WHERE id_user = u.id_utilisateur)
                WHERE u.role <> 'admin'";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (u.nom_utilisateur LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ?)";
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $params = [$like, $like, $like];
        }
        $sql .= " ORDER BY m.id DESC LIMIT 100";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $list = [];
        foreach ($st->fetchAll() as $r) {
            $r['last_text'] = bm_decrypt($r['last_text']);   // contenu chiffré en base
            $list[] = [
                'id' => (int) $r['id'],
                'name' => support_display($r),
                'user' => $r['nom_utilisateur'],
                'last' => $r['last_text'] !== null && $r['last_text'] !== '' ? mb_substr($r['last_text'], 0, 80) : ('📎 ' . $r['last_file']),
                'mine' => $r['last_sender'] === 'admin',
                't' => $r['last_t'],
                'unread' => (int) $r['unread'],
            ];
        }
        support_json(['conversations' => $list, 'unread' => support_unread_for_admin($pdo)]);
    }

    if ($action === 'users') {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            support_json(['users' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $st = $pdo->prepare("SELECT id_utilisateur, nom_utilisateur, prenom, nom FROM utilisateur
                             WHERE role <> 'admin' AND (nom_utilisateur LIKE ? OR prenom LIKE ? OR nom LIKE ? OR email LIKE ?)
                             ORDER BY nom_utilisateur LIMIT 15");
        $st->execute([$like, $like, $like, $like]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = ['id' => (int) $r['id_utilisateur'], 'name' => support_display($r), 'user' => $r['nom_utilisateur']];
        }
        support_json(['users' => $out]);
    }

    // Les actions suivantes concernent une conversation précise
    $target = (int) ($isPost ? ($_POST['user'] ?? 0) : ($_GET['user'] ?? 0));
    $st = $pdo->prepare("SELECT id_utilisateur, nom_utilisateur, prenom, nom FROM utilisateur WHERE id_utilisateur = ? AND role <> 'admin'");
    $st->execute([$target]);
    $u = $st->fetch();
    if (!$u) {
        support_json(['error' => 'Utilisateur introuvable'], 404);
    }

    if ($action === 'poll') {
        $after = max(0, (int) ($_GET['after'] ?? 0));
        support_mark_read($pdo, $target, 'user');
        support_json([
            'user' => ['id' => (int) $u['id_utilisateur'], 'name' => support_display($u), 'user' => $u['nom_utilisateur']],
            'messages' => support_fetch($pdo, $target, $after),
            'peer_read' => support_peer_read($pdo, $target, 'admin'),
        ]);
    }

    if ($action === 'send' && $isPost) {
        if (!$csrfOk) {
            support_json(['error' => 'Session expirée, rechargez la page.'], 403);
        }
        support_json(['message' => support_do_send($pdo, $target, 'admin', $aid, 20)]);
    }

    support_json(['error' => 'Action inconnue'], 400);
} catch (Throwable $e) {
    error_log('support_api(admin): ' . $e->getMessage());
    support_json(['error' => 'Erreur serveur'], 500);
}