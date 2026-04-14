<?php
/**
 * install.php - Collection Software Installation Wizard
 * 
 * This script sets up the database schema, creates the first admin user, 
 * and generates the config/config.php file.
 */

session_start();

$step = (int)($_GET['step'] ?? 1);
$error = $_SESSION['install_error'] ?? null;
unset($_SESSION['install_error']);

// ── Step 1: Welcome & Requirements ───────────────────────────────────────────
if ($step === 1) {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'Config Dir Writable' => is_writable(__DIR__ . '/config'),
        'Uploads Dir Writable' => (is_dir(__DIR__ . '/uploads') ? is_writable(__DIR__ . '/uploads') : is_writable(__DIR__)),
    ];
    $ready = !in_array(false, $requirements, true);
}

// ── Step 2: DB Connection Logic ──────────────────────────────────────────────
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'collection';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';

    try {
        // Try creating DB if it doesn't exist
        $pdo_raw = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo_raw->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        
        // Connect to the specific DB
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Store for later steps
        $_SESSION['db_config'] = compact('db_host', 'db_name', 'db_user', 'db_pass');
        header('Location: ?step=4');
        exit;
    } catch (Exception $e) {
        $_SESSION['install_error'] = "Database Connection Failed: " . $e->getMessage();
        header('Location: ?step=2');
        exit;
    }
}

// ── Step 4: Admin & Schema Logic ─────────────────────────────────────────────
if ($step === 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $site_title = trim($_POST['site_title'] ?? 'My Collection');
    $site_url   = rtrim(trim($_POST['site_url'] ?? 'http://localhost/collection'), '/');

    if (!$admin_user || strlen($admin_pass) < 6) {
        $_SESSION['install_error'] = "Invalid Admin details. Password must be at least 6 characters.";
        header('Location: ?step=4');
        exit;
    }

    $db = $_SESSION['db_config'] ?? null;
    if (!$db) {
        header('Location: ?step=2');
        exit;
    }

    try {
        $dsn = "mysql:host={$db['db_host']};dbname={$db['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // ── 1. Create Core Tables ────────────────────────────────────────────
        $schemas = [
            "categories" => "id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE",
            "items" => "id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, reg_number VARCHAR(100) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, physical_description TEXT, historical_significance TEXT, production_date VARCHAR(100), credit_line VARCHAR(255), is_visible TINYINT(1) DEFAULT 1, FULLTEXT KEY (title, physical_description)",
            "media" => "id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, caption VARCHAR(255), license_type VARCHAR(100), file_size INT UNSIGNED, mime_type VARCHAR(50), dimensions VARCHAR(50), upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, media_type ENUM('image', 'pdf', 'youtube') DEFAULT 'image', youtube_url VARCHAR(512)",
            "settings" => "setting_key VARCHAR(100) NOT NULL PRIMARY KEY, setting_value TEXT",
            "admins" => "id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "tags" => "id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE",
            "item_tag" => "item_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(item_id, tag_id)",
            "narratives" => "id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content_body LONGTEXT NOT NULL",
            "item_narrative" => "item_id INT NOT NULL, narrative_id INT NOT NULL, PRIMARY KEY(item_id, narrative_id)"
        ];

        foreach ($schemas as $table => $def) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` ($def) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // ── 2. Insert Admin & Settings ───────────────────────────────────────
        $pdo->prepare("INSERT IGNORE INTO admins (username, password_hash) VALUES (?, ?)")
            ->execute([$admin_user, password_hash($admin_pass, PASSWORD_DEFAULT)]);
        
        $initialSettings = [
            'site_title' => $site_title,
            'site_url' => $site_url,
            'storage_driver' => 'local',
            'active_modules' => json_encode(['blog', 'people', 'curated_collections', 'user_galleries'])
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($initialSettings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // ── 3. Activate Modules (Triggers table creation for modules) ────────
        require_once __DIR__ . '/includes/BaseModule.php';
        require_once __DIR__ . '/includes/ModuleDB.php';
        foreach (json_decode($initialSettings['active_modules']) as $slug) {
            $path = __DIR__ . "/modules/$slug/module.php";
            if (file_exists($path)) {
                require_once $path;
                $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $slug))) . 'Module';
                if (class_exists($className)) {
                    $mod = new $className($pdo, $slug, []);
                    if (method_exists($mod, 'activate')) {
                        $mod->activate();
                    }
                }
            }
        }

        // ── 4. Generate Config File ──────────────────────────────────────────
        $configTemplate = "<?php
// config/config.php - Generated by installer
\$host = '{$db['db_host']}';
\$db   = '{$db['db_name']}';
\$user = '{$db['db_user']}';
\$pass = '{$db['db_pass']}';
\$charset = 'utf8mb4';

\$dsn = \"mysql:host=\$host;dbname=\$db;charset=\$charset\";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

require_once __DIR__ . '/../includes/Autoloader.php';
try {
     \$pdo = new SafePDO(\$dsn, \$user, \$pass, \$options);
} catch (\\PDOException \$e) {
     throw new \\PDOException(\$e->getMessage(), (int)\$e->getCode());
}

// Default Site Settings (Can be overridden in Admin)
define('SITE_URL_DEFAULT', '$site_url');
define('SITE_TITLE_DEFAULT', '$site_title');

require_once __DIR__ . '/../includes/LocalStorage.php';
require_once __DIR__ . '/../includes/S3Storage.php';

function loadSettings(PDO \$pdo): array {
    try {
        \$stmt = \$pdo->query(\"SELECT setting_key, setting_value FROM settings\");
        \$settings = [];
        while (\$row = \$stmt->fetch()) {
            \$settings[\$row['setting_key']] = \$row['setting_value'];
        }
        return \$settings;
    } catch (\\PDOException \$e) {
        return [];
    }
}
\$appSettings = loadSettings(\$pdo);

define('SITE_URL',  rtrim(\$appSettings['site_url'] ?? SITE_URL_DEFAULT, '/'));
define('SITE_TITLE', \$appSettings['site_title'] ?? SITE_TITLE_DEFAULT);

\$storageDriver = \$appSettings['storage_driver'] ?? 'local';
if (\$storageDriver === 's3') {
    \$storage = new S3Storage([
        'bucket' => \$appSettings['s3_bucket'],
        'region' => \$appSettings['s3_region'] ?? 'us-east-1',
        'access_key' => \$appSettings['s3_access_key'],
        'secret_key' => \$appSettings['s3_secret_key'],
    ]);
} else {
    \$storage = new LocalStorage(realpath(__DIR__ . '/../uploads'), SITE_URL . '/uploads');
}

require_once __DIR__ . '/../includes/frontend.php';
\$activeModulesJson = \$appSettings['active_modules'] ?? '[]';
\$activeModulesSlugs = json_decode(\$activeModulesJson, true) ?: [];
\$moduleManager = new ModuleManager(\$pdo, __DIR__ . '/../modules', \$activeModulesSlugs);
\$moduleManager->bootActiveModules();
?>";
        
        file_put_contents(__DIR__ . '/config/config.php', $configTemplate);

        header('Location: ?step=6');
        exit;
    } catch (Exception $e) {
        $_SESSION['install_error'] = "Setup Failed: " . $e->getMessage();
        header('Location: ?step=4');
        exit;
    }
}

// ── UI Helper Functions ───────────────────────────────────────────────────────
function stepClass($currentStep, $targetStep) {
    if ($currentStep === $targetStep) return 'bg-blue-600 text-white shadow-lg shadow-blue-200';
    if ($currentStep > $targetStep) return 'bg-green-500 text-white';
    return 'bg-gray-100 text-gray-400';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Setup — Step <?= $step ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07); }
    </style>
</head>
<body class="h-full flex flex-col items-center justify-center p-6 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50 via-white to-slate-100">

    <div class="max-w-xl w-full">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Collection System <span class="text-blue-600">Installer</span></h1>
            <p class="text-slate-500 mt-2">Setting up your digital gallery has never been easier.</p>
        </div>

        <!-- Progress Bar -->
        <div class="flex items-center justify-between mb-10 px-2">
            <?php foreach ([1 => 'Start', 2 => 'Database', 4 => 'Admin', 6 => 'Finish'] as $s => $label): ?>
                <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-500 <?= stepClass($step, $s) ?>">
                        <?= $s < 6 ? ($s == 4 ? 3 : ($s == 2 ? 2 : 1)) : 4 ?>
                    </div>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400"><?= $label ?></span>
                </div>
                <?php if ($s < 6): ?><div class="h-px bg-slate-200 flex-grow mx-4"></div><?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="glass rounded-3xl p-8 sm:p-10">
            
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-sm flex items-start gap-3">
                    <svg class="h-5 w-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- STAGES -->
            
            <?php if ($step === 1): ?>
                <div class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Confirm Requirements</h2>
                    <div class="space-y-3">
                        <?php foreach ($requirements as $req => $passed): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl border <?= $passed ? 'bg-green-50/50 border-green-100 text-green-700' : 'bg-red-50/50 border-red-100 text-red-600' ?>">
                                <span class="font-medium"><?= $req ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-60"><?= $passed ? 'Verified' : 'Failed' ?></span>
                                    <?php if ($passed): ?>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <?php else: ?>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($ready): ?>
                        <a href="?step=2" class="block w-full text-center bg-blue-600 text-white font-bold py-4 rounded-2xl hover:bg-blue-700 transition shadow-lg shadow-blue-100">Get Started &rarr;</a>
                    <?php else: ?>
                        <div class="p-4 bg-amber-50 text-amber-700 rounded-2xl text-sm border border-amber-100">Please fix the requirements above to continue.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($step === 2): ?>
                <form action="?step=3" method="POST" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Database Connection</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Host</label>
                            <input type="text" name="db_host" value="localhost" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Database Name</label>
                            <input type="text" name="db_name" value="collection" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Username</label>
                                <input type="text" name="db_user" value="root" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Password</label>
                                <input type="password" name="db_pass" placeholder="Optional" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-4 rounded-2xl hover:bg-blue-700 transition shadow-lg shadow-blue-100">Connect Database &rarr;</button>
                </form>

            <?php elseif ($step === 4): ?>
                <form action="?step=5" method="POST" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Account & Settings</h2>
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Site Identity</h3>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Collection Title</label>
                            <input type="text" name="site_title" value="Pictorial Cancellation Collection" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Base URL</label>
                            <input type="text" name="site_url" value="<?= 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . str_replace('/install.php', '', $_SERVER['REQUEST_URI'] ?? '/collection') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                        </div>
                        
                        <div class="pt-4 border-t border-slate-100"></div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Master Admin Account</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Admin Username</label>
                                <input type="text" name="admin_user" value="admin" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Password</label>
                                <input type="password" name="admin_pass" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 outline-none transition" required minlength="6">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-4 rounded-2xl hover:bg-blue-700 transition shadow-lg shadow-blue-100">Finalize Installation &rarr;</button>
                </form>

            <?php elseif ($step === 6): ?>
                <div class="text-center space-y-6">
                    <div class="w-20 h-20 bg-green-500 text-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-xl shadow-green-100">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900">Success!</h2>
                    <p class="text-slate-500">The software has been successfully installed and configured. Database schema created, modules activated, and admin user registered.</p>
                    <div class="p-4 bg-amber-50 text-amber-700 rounded-2xl text-xs font-bold border border-amber-100 flex items-center gap-3 justify-center">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        SECURITY ALERT: Delete install.php manually from your server.
                    </div>
                    <a href="admin/login.php" class="block w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-slate-800 transition shadow-lg">Login to Admin Dashboard &rarr;</a>
                    <a href="index.php" class="mt-4 block text-slate-500 font-medium hover:text-slate-800">Visit Public Site</a>
                </div>
            <?php endif; ?>

        </div>

        <p class="mt-8 text-center text-slate-400 text-xs font-medium uppercase tracking-[0.2em]">&copy; <?= date('Y') ?> Advanced Collection System</p>
    </div>

</body>
</html>
