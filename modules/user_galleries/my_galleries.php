<?php
// modules/user_galleries/my_galleries.php
require_once __DIR__ . '/../../config/config.php';

$userToken = $_COOKIE['gallery_user_token'] ?? null;
$galleries = [];

if ($userToken) {
    $stmt = $pdo->prepare("SELECT id, title, description, share_token, created_at, (SELECT COUNT(*) FROM user_gallery_items WHERE gallery_id = user_galleries.id) as item_count FROM user_galleries WHERE user_token = ? ORDER BY created_at DESC");
    $stmt->execute([$userToken]);
    $galleries = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Galleries - <?= SITE_TITLE ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        h1, h2, h3, h4, .serif { font-family: 'Playfair Display', serif; color: #111827; }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight flex-shrink-0"><?= SITE_TITLE ?></a>
            <?php renderFrontendNav('user_galleries'); ?>
        </div>
    </header>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10">
            <h1 class="text-4xl font-extrabold serif mb-4">My Curated Galleries</h1>
            <p class="text-lg text-gray-600">View and manage your personal collections of items.</p>
        </div>

        <?php if (empty($galleries)): ?>
            <div class="text-center py-20 bg-white rounded-xl border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No galleries yet</h3>
                <p class="mt-1 text-sm text-gray-500">You haven't created any galleries. Browse the collection and click "Add to Gallery" on any item to get started.</p>
                <div class="mt-6">
                    <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">Explore Collection</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($galleries as $gallery): ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                        <div class="p-6 flex-grow">
                            <h3 class="text-xl font-bold text-gray-900 mb-2 truncate" title="<?= htmlspecialchars($gallery['title']) ?>"><?= htmlspecialchars($gallery['title']) ?></h3>
                            <?php if ($gallery['description']): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= nl2br(htmlspecialchars($gallery['description'])) ?></p>
                            <?php endif; ?>
                            <p class="text-sm font-medium text-gray-500 bg-gray-50 border border-gray-100 inline-block px-2 py-1 rounded"><?= $gallery['item_count'] ?> items</p>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-gray-50 text-sm">
                            <a href="<?= SITE_URL ?>/modules/user_galleries/view_gallery.php?token=<?= htmlspecialchars($gallery['share_token']) ?>" class="font-semibold text-gray-900 hover:underline">View Gallery &rarr;</a>
                            <button onclick="deleteGallery(<?= $gallery['id'] ?>)" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function deleteGallery(id) {
            if (!confirm('Are you sure you want to delete this gallery? This action cannot be undone.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_gallery');
            formData.append('gallery_id', id);
            
            fetch('<?= SITE_URL ?>/modules/user_galleries/api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.error);
                });
        }
    </script>
</body>
</html>
