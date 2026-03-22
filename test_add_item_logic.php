<?php
require_once 'config/config.php';
require_once 'admin/auth.php';

// 1. Get an existing collection ID
$col = $pdo->query("SELECT id FROM collections LIMIT 1")->fetch();
if (!$col) die("No collections found.");
$colId = $col['id'];

// 2. Get an item to add
$item = $pdo->query("SELECT id FROM items LIMIT 1")->fetch();
if (!$item) die("No items found.");
$itemId = $item['id'];

echo "Testing Add Item: Item ID $itemId to Collection ID $colId\n";

// 4. Simulate POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'dummy_token',
    'add_item' => '1',
    'item_id' => $itemId
];
$_SESSION['csrf_token'] = 'dummy_token';

// 5. Mock SITE_URL for header test
define('SITE_URL_TEST', SITE_URL);

// 6. Include the logic file directly (emulating being inside Module class)
class MockModule {
    public $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function test($colId) {
        $action = 'edit';
        $_GET['action'] = 'edit';
        $_GET['id'] = $colId;
        require 'modules/curated_collections/admin_collections_logic.php';
    }
}

$mock = new MockModule($pdo);

// We expect a "Headers already sent" warning if it tries to redirect, 
// or it might just exit if I didn't mock header()
try {
    $mock->test($colId);
} catch (Exception $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

echo "Check DB for result...\n";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM collection_items WHERE collection_id = ? AND item_id = ?");
$stmt->execute([$colId, $itemId]);
$cnt = $stmt->fetchColumn();

if ($cnt > 0) {
    echo "SUCCESS: Item is in collection.\n";
} else {
    echo "FAILED: Item not found in collection items table.\n";
}
?>
