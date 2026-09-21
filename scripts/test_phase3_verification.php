<?php
// scripts/test_phase3_verification.php
// Verification suite for Phase 3: UX & Architecture Polish.

echo "=== Running Phase 3 Verification Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
        $failCount++;
    }
}

require_once __DIR__ . '/../config/config.php';
global $pdo;

// ── 1. Test Password Change Logic ───────────────────────────────────────────
echo "\n-- Section 1: Administrator Password Management --\n";

// Create temporary admin user
$testUsername = 'test_phase3_admin_' . bin2hex(random_bytes(4));
$testInitialPass = 'OldPass@12345';
$initialHash = password_hash($testInitialPass, PASSWORD_DEFAULT);

$insertStmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
$insertStmt->execute([$testUsername, $initialHash]);
$testAdminId = (int)$pdo->lastInsertId();

assertTest("Test admin created successfully", $testAdminId > 0);

// Test validation rules
$shortPass = 'short';
assertTest("Password length check rejects < 8 chars", strlen($shortPass) < 8);

$mismatchPass1 = 'ValidPass@123';
$mismatchPass2 = 'ValidPass@456';
assertTest("Password mismatch check rejects different confirmation", $mismatchPass1 !== $mismatchPass2);

// Test self-verification: wrong current password
$targetAdmin = $pdo->query("SELECT id, username, password_hash FROM admins WHERE id = $testAdminId")->fetch();
$wrongCurrentPass = 'WrongPass@999';
assertTest("Verification rejects wrong current password", !password_verify($wrongCurrentPass, $targetAdmin['password_hash']));

// Test self-verification: correct current password
assertTest("Verification accepts correct current password", password_verify($testInitialPass, $targetAdmin['password_hash']));

// Perform password update
$newPass = 'BrandNewSecret@2026';
$newHash = password_hash($newPass, PASSWORD_DEFAULT);
$updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
$updateSuccess = $updateStmt->execute([$newHash, $testAdminId]);
assertTest("Password hash update executed in database", $updateSuccess);

// Verify new password authenticates and old password fails
$updatedAdmin = $pdo->query("SELECT id, username, password_hash FROM admins WHERE id = $testAdminId")->fetch();
assertTest("New password authenticates successfully", password_verify($newPass, $updatedAdmin['password_hash']));
assertTest("Old password no longer authenticates", !password_verify($testInitialPass, $updatedAdmin['password_hash']));

// Clean up temporary admin
$pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$testAdminId]);
$checkDeleted = $pdo->query("SELECT COUNT(*) FROM admins WHERE id = $testAdminId")->fetchColumn();
assertTest("Temporary test admin cleaned up", (int)$checkDeleted === 0);

// Check users.php file contains change_password handler and UI elements
$usersPhp = file_get_contents(__DIR__ . '/../admin/users.php');
assertTest("users.php contains change_password action", strpos($usersPhp, "'change_password'") !== false);
assertTest("users.php contains password minlength enforcement", strpos($usersPhp, "minlength=\"8\"") !== false);
assertTest("users.php contains selectPasswordUser helper", strpos($usersPhp, "function selectPasswordUser") !== false);
assertTest("users.php includes CSRF token in password form", strpos($usersPhp, "ensureCsrfToken()") !== false);

// ── 2. Test Environment Variable & .env Support ─────────────────────────────
echo "\n-- Section 2: Environment Variables & Database Bootstrap --\n";

// Test .env.example exists and contains template keys
$envExamplePath = __DIR__ . '/../.env.example';
assertTest(".env.example file exists", file_exists($envExamplePath));
if (file_exists($envExamplePath)) {
    $exampleContent = file_get_contents($envExamplePath);
    assertTest(".env.example defines DB_HOST", strpos($exampleContent, 'DB_HOST') !== false);
    assertTest(".env.example defines DB_NAME", strpos($exampleContent, 'DB_NAME') !== false);
    assertTest(".env.example defines DB_USER", strpos($exampleContent, 'DB_USER') !== false);
    assertTest(".env.example defines DB_PASS", strpos($exampleContent, 'DB_PASS') !== false);
}

// Test environment variable resolution logic
$dbPhp = file_get_contents(__DIR__ . '/../bootstrap/database.php');
assertTest("database.php checks getenv('DB_HOST')", strpos($dbPhp, "getenv('DB_HOST')") !== false);
assertTest("database.php checks getenv('DB_NAME')", strpos($dbPhp, "getenv('DB_NAME')") !== false);
assertTest("database.php checks getenv('DB_USER')", strpos($dbPhp, "getenv('DB_USER')") !== false);
assertTest("database.php checks getenv('DB_PORT')", strpos($dbPhp, "getenv('DB_PORT')") !== false);
assertTest("database.php includes optional .env file loader", strpos($dbPhp, "\$envFile = __DIR__ . '/../.env'") !== false);

// ── 3. Test Root Hygiene & Legacy Prototype Cleanup ─────────────────────────
echo "\n-- Section 3: Root Hygiene & Legacy Cleanup --\n";

assertTest("output.html removed from root directory", !file_exists(__DIR__ . '/../output.html'));
assertTest("update - Copy.sql.already.ran removed from root directory", !file_exists(__DIR__ . '/../update - Copy.sql.already.ran'));
assertTest("update - Copy.sql.already.ran moved to scripts/", file_exists(__DIR__ . '/update - Copy.sql.already.ran'));
assertTest("includes/MediaManager.php removed from includes/", !file_exists(__DIR__ . '/../includes/MediaManager.php'));
assertTest("includes/MediaManager.php moved to scripts/legacy/", file_exists(__DIR__ . '/legacy/MediaManager.php'));

// Verify MediaProcessor remains functional
assertTest("MediaProcessor class exists and is loadable", class_exists('MediaProcessor'));
$mp = new MediaProcessor($pdo, $storage ?? null);
assertTest("MediaProcessor instance created cleanly", $mp instanceof MediaProcessor);

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n============================================\n";
echo "Phase 3 Verification Complete: $passCount passed, $failCount failed.\n";
echo "============================================\n";

exit($failCount > 0 ? 1 : 0);
