<?php
// modules/user_galleries/api.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Csrf.php';

header('Content-Type: application/json');

// Initialize or retrieve the user's gallery token
$tokenName = 'gallery_user_token';
$userToken = $_COOKIE[$tokenName] ?? null;

if (!is_string($userToken) || !preg_match('/^[a-f0-9]{64}$/', $userToken)) {
    $userToken = bin2hex(random_bytes(32));
    setcookie($tokenName, $userToken, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function galleryLock(PDO $pdo, string $key): bool {
    $name = 'gallery:' . substr(hash('sha256', $key), 0, 48);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn() === 1;
}
function galleryUnlock(PDO $pdo, string $key): void {
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->execute(['gallery:' . substr(hash('sha256', $key), 0, 48)]);
}
function galleryPositiveId($raw): int {
    if (!is_string($raw) || !ctype_digit($raw) || (int)$raw <= 0) throw new DomainException('Invalid ID.');
    return (int)$raw;
}

try {
    if (!is_string($action)) throw new DomainException('Invalid action.');
    if ($action !== 'list') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') throw new DomainException('Invalid method.');
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Security check failed.']));
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ownerLimit = RateLimiter::consume($pdo, 'gallery_owner', $userToken, 60, 600);
        $ipLimit = RateLimiter::consume($pdo, 'gallery_ip', $ip, 120, 600);
        if (!$ownerLimit['allowed'] || !$ipLimit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . max($ownerLimit['retry_after'], $ipLimit['retry_after']));
            exit(json_encode(['success' => false, 'error' => 'Too many requests.']));
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new DomainException('Invalid method.');
    }
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("SELECT id, title, description, share_token FROM user_galleries WHERE user_token = ? ORDER BY created_at DESC");
            $stmt->execute([$userToken]);
            $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If item_id is provided, check which galleries it's already in
            $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
            if ($itemId) {
                foreach ($galleries as &$gallery) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM user_gallery_items WHERE gallery_id = ? AND item_id = ?");
                    $chk->execute([$gallery['id'], $itemId]);
                    $gallery['has_item'] = ($chk->fetchColumn() > 0);
                }
            }
            
            echo json_encode(['success' => true, 'galleries' => $galleries]);
            break;

        case 'create':
            if (!is_string($_POST['title'] ?? null) || strlen($_POST['title']) > 200 || !is_string($_POST['description'] ?? '')) throw new DomainException('Invalid gallery details.');
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            if (strlen($description) > 2000) throw new DomainException('Description is too long.');
            
            if (empty($title)) {
                throw new DomainException('Gallery title is required.');
            }
            $createLimit = RateLimiter::consume($pdo, 'gallery_create_ip', $ip, 10, 3600);
            if (!$createLimit['allowed']) {
                http_response_code(429);
                header('Retry-After: ' . $createLimit['retry_after']);
                exit(json_encode(['success' => false, 'error' => 'Too many galleries created.']));
            }
            if (!galleryLock($pdo, $userToken)) throw new DomainException('Gallery is busy. Please retry.');
            try {
                $pdo->beginTransaction();
                $count = $pdo->prepare('SELECT COUNT(*) FROM user_galleries WHERE user_token = ?');
                $count->execute([$userToken]);
                if ((int)$count->fetchColumn() >= 50) throw new DomainException('Gallery limit reached.');
            $shareToken = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO user_galleries (user_token, title, description, share_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userToken, $title, $description, $shareToken]);
            $newId = $pdo->lastInsertId();
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            } finally {
                galleryUnlock($pdo, $userToken);
            }
            echo json_encode(['success' => true, 'gallery_id' => $newId, 'share_token' => $shareToken]);
            break;

        case 'add_item':
            $galleryId = galleryPositiveId($_POST['gallery_id'] ?? null);
            $itemId = galleryPositiveId($_POST['item_id'] ?? null);
            
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new DomainException('Gallery not found or access denied.');
            }
            $visible = $pdo->prepare('SELECT id FROM items WHERE id = ? AND is_visible = 1');
            $visible->execute([$itemId]);
            if (!$visible->fetchColumn()) throw new DomainException('Item unavailable.');
            if (!galleryLock($pdo, 'items:' . $galleryId)) throw new DomainException('Gallery is busy. Please retry.');
            try {
                $pdo->beginTransaction();
                $count = $pdo->prepare('SELECT COUNT(*) FROM user_gallery_items WHERE gallery_id = ?');
                $count->execute([$galleryId]);
                if ((int)$count->fetchColumn() >= 500) throw new DomainException('Gallery item limit reached.');
            // Check if already in gallery
            $dup = $pdo->prepare("SELECT id FROM user_gallery_items WHERE gallery_id = ? AND item_id = ?");
            $dup->execute([$galleryId, $itemId]);
            if ($dup->fetch()) {
                throw new DomainException('Item is already in this gallery.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO user_gallery_items (gallery_id, item_id) VALUES (?, ?)");
            $stmt->execute([$galleryId, $itemId]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            } finally {
                galleryUnlock($pdo, 'items:' . $galleryId);
            }
            echo json_encode(['success' => true]);
            break;

        case 'remove_item':
            $galleryId = galleryPositiveId($_POST['gallery_id'] ?? null);
            $itemId = galleryPositiveId($_POST['item_id'] ?? null);
            
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new DomainException('Gallery not found or access denied.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM user_gallery_items WHERE gallery_id = ? AND item_id = ?");
            $stmt->execute([$galleryId, $itemId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_gallery':
            $galleryId = galleryPositiveId($_POST['gallery_id'] ?? null);
            
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new DomainException('Gallery not found or access denied.');
            }
            
            // Cascade delete will handle items if FK was set up, but let's manual delete to be safe
            $delItems = $pdo->prepare("DELETE FROM user_gallery_items WHERE gallery_id = ?");
            $delItems->execute([$galleryId]);
            
            $delGal = $pdo->prepare("DELETE FROM user_galleries WHERE id = ?");
            $delGal->execute([$galleryId]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            throw new DomainException('Invalid action.');
    }
} catch (DomainException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(PublicError::payload($e));
}
