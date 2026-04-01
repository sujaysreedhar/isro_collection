<?php
// modules/people/person.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

global $pdo, $activeModulesSlugs;

$slug = $_GET['slug'] ?? '';
if (!in_array('people', $activeModulesSlugs)) {
    header("HTTP/1.0 404 Not Found");
    require_once ThemeManager::getHeader();
    echo '<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24 text-center"><h1 class="text-4xl font-bold text-gray-900 mb-4">404 - Page Not Found</h1><p class="text-gray-600">The People & Biographies module is currently disabled.</p></div>';
    require_once ThemeManager::getFooter();
    exit;
}

if (!$slug) {
    header("Location: " . SITE_URL . "/people.php");
    exit;
}

// Fetch person
$stmt = $pdo->prepare("SELECT * FROM people WHERE slug = ? AND is_public = 1");
$stmt->execute([$slug]);
$person = $stmt->fetch();

if (!$person) {
    http_response_code(404);
    require_once ThemeManager::getHeader();
    echo '<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24 text-center"><h1 class="text-4xl font-bold text-gray-900 mb-4">Biography Not Found</h1><p class="text-gray-600">The person you are looking for is not in our records.</p><a href="' . SITE_URL . '/people.php" class="mt-6 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg">Browse People</a></div>';
    require_once ThemeManager::getFooter();
    exit;
}

$pageTitle = $person['name'] . ' - Biography';
$currentMenu = 'people';

// Fetch related items
$stmtItems = $pdo->prepare("
    SELECT i.*, ip.role 
    FROM items i 
    JOIN item_people ip ON i.id = ip.item_id 
    WHERE ip.person_id = ? 
    ORDER BY i.production_date DESC
");
$stmtItems->execute([$person['id']]);
$relatedItems = $stmtItems->fetchAll();

require_once ThemeManager::getHeader();
?>

<div class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
    <!-- Breadcrumbs -->
    <nav class="flex mb-8 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2">
            <li><a href="<?= SITE_URL ?>/people.php" class="hover:text-blue-600 transition-colors">People</a></li>
            <li><svg class="w-4 h-4 text-slate-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg></li>
            <li class="text-slate-900"><?= htmlspecialchars($person['name']) ?></li>
        </ol>
    </nav>

    <div class="flex flex-col lg:flex-row gap-12">
        <!-- Main Content -->
        <div class="lg:flex-1">
            <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 serif mb-2 tracking-tight"><?= htmlspecialchars($person['name']) ?></h1>
            <p class="text-xl text-slate-500 italic mb-8 serif"><?= htmlspecialchars($person['short_description']) ?></p>

            <div class="prose prose-slate prose-lg max-w-none leading-relaxed text-slate-700">
                <?php if (!empty($person['biography'])): ?>
                    <?= nl2br(htmlspecialchars($person['biography'])) ?>
                <?php else: ?>
                    <p class="text-slate-400 italic">Biography is currently being researched.</p>
                <?php endif; ?>
            </div>

            <!-- Related Artifacts Section -->
            <?php if ($relatedItems): ?>
                <div class="mt-16 pt-12 border-t border-slate-200">
                    <h2 class="text-2xl font-bold text-slate-900 mb-8 font-serif">Related Artifacts & Records</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                        <?php foreach ($relatedItems as $item): ?>
                            <?php 
                                // Fetch main image
                                $imgStmt = $pdo->prepare("SELECT file_path FROM media WHERE item_id = ? AND media_type = 'image' ORDER BY id ASC LIMIT 1");
                                $imgStmt->execute([$item['id']]);
                                $img = $imgStmt->fetchColumn();
                                $imgUrl = $img ? SITE_URL . '/uploads/display/' . $img : '';
                            ?>
                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group block bg-white border border-slate-200 rounded-2xl overflow-hidden hover:shadow-lg transition-all">
                                <div class="aspect-square bg-slate-50 relative overflow-hidden">
                                    <?php if ($imgUrl): ?>
                                        <img src="<?= htmlspecialchars($imgUrl) ?>" class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-700">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-200">
                                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate"><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($item['reg_number']) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Wikipedia Infobox Sidebar -->
        <aside class="w-full lg:w-80 flex-shrink-0">
            <div class="bg-white border text-slate-900 rounded-xl overflow-hidden shadow-sm sticky top-24">
                <div class="bg-slate-50 border-b p-4 text-center">
                    <h2 class="font-extrabold text-lg tracking-tight"><?= htmlspecialchars($person['name']) ?></h2>
                </div>
                
                <div class="p-4">
                    <?php if ($person['profile_image']): ?>
                        <div class="mb-4 rounded-lg overflow-hidden ring-1 ring-slate-100 shadow-inner bg-slate-50">
                            <img src="<?= SITE_URL ?>/uploads/display/<?= htmlspecialchars($person['profile_image']) ?>" alt="<?= htmlspecialchars($person['name']) ?>" class="w-full object-cover">
                        </div>
                    <?php endif; ?>

                    <table class="w-full text-xs border-collapse">
                        <tbody>
                            <?php if ($person['birth_date']): ?>
                                <tr class="border-b border-slate-50 last:border-0">
                                    <th class="py-2.5 pr-2 font-bold text-slate-500 text-left w-1/3 align-top">Born</th>
                                    <td class="py-2.5 text-slate-900 align-top"><?= htmlspecialchars($person['birth_date']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($person['death_date']): ?>
                                <tr class="border-b border-slate-50 last:border-0">
                                    <th class="py-2.5 pr-2 font-bold text-slate-500 text-left w-1/3 align-top">Died</th>
                                    <td class="py-2.5 text-slate-900 align-top"><?= htmlspecialchars($person['death_date']) ?></td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php 
                            $infobox = json_decode($person['infobox_data'] ?? '[]', true);
                            foreach ($infobox as $row):
                                if (empty($row['label']) || empty($row['value'])) continue;
                            ?>
                                <tr class="border-b border-slate-50 last:border-0">
                                    <th class="py-2.5 pr-2 font-bold text-slate-500 text-left w-1/3 align-top"><?= htmlspecialchars($row['label']) ?></th>
                                    <td class="py-2.5 text-slate-900 align-top"><?= htmlspecialchars($row['value']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-slate-50 border-t p-3 text-[10px] text-slate-400 text-center uppercase tracking-widest font-bold">
                    Historical Record
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once ThemeManager::getFooter(); ?>
