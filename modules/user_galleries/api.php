<?php
// modules/user_galleries/api.php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Initialize or retrieve the user's gallery token
$tokenName = 'gallery_user_token';
$userToken = $_COOKIE[$tokenName] ?? null;

if (!$userToken) {
    $userToken = bin2hex(random_bytes(32));
    // Set cookie for 1 year
    setcookie($tokenName, $userToken, time() + (365 * 24 * 60 * 60), '/');
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
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
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($title)) {
                throw new Exception('Gallery title is required.');
            }
            
            $shareToken = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO user_galleries (user_token, title, description, share_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userToken, $title, $description, $shareToken]);
            
            echo json_encode(['success' => true, 'gallery_id' => $pdo->lastInsertId(), 'share_token' => $shareToken]);
            break;

        case 'add_item':
            $galleryId = (int)($_POST['gallery_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new Exception('Gallery not found or access denied.');
            }
            
            // Check if already in gallery
            $dup = $pdo->prepare("SELECT id FROM user_gallery_items WHERE gallery_id = ? AND item_id = ?");
            $dup->execute([$galleryId, $itemId]);
            if ($dup->fetch()) {
                throw new Exception('Item is already in this gallery.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO user_gallery_items (gallery_id, item_id) VALUES (?, ?)");
            $stmt->execute([$galleryId, $itemId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'remove_item':
            $galleryId = (int)($_POST['gallery_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new Exception('Gallery not found or access denied.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM user_gallery_items WHERE gallery_id = ? AND item_id = ?");
            $stmt->execute([$galleryId, $itemId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_gallery':
            $galleryId = (int)($_POST['gallery_id'] ?? 0);
            
            $check = $pdo->prepare("SELECT id FROM user_galleries WHERE id = ? AND user_token = ?");
            $check->execute([$galleryId, $userToken]);
            if (!$check->fetch()) {
                throw new Exception('Gallery not found or access denied.');
            }
            
            // Cascade delete will handle items if FK was set up, but let's manual delete to be safe
            $delItems = $pdo->prepare("DELETE FROM user_gallery_items WHERE gallery_id = ?");
            $delItems->execute([$galleryId]);
            
            $delGal = $pdo->prepare("DELETE FROM user_galleries WHERE id = ?");
            $delGal->execute([$galleryId]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
