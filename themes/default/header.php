<?php
// themes/default/header.php

// Defaults
$pageTitle = $pageTitle ?? SITE_TITLE;
$ogTitle = $ogTitle ?? $pageTitle;
$ogDescription = $ogDescription ?? 'Pictorial Cancellation Collection Archive';
$ogUrl = $ogUrl ?? SITE_URL;
$ogImage = $ogImage ?? '';
$jsonLdJson = $jsonLdJson ?? '';
$currentMenu = $currentMenu ?? '';
$hideHeaderSearch = $hideHeaderSearch ?? false;
$searchParams = $searchParams ?? ['q' => ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <?php
        $siteFavicon = $appSettings['site_favicon'] ?? '';
        if ($siteFavicon && file_exists(__DIR__ . '/../uploads/branding/' . $siteFavicon)):
    ?>
        <link rel="icon" href="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteFavicon) ?>" type="image/<?= pathinfo($siteFavicon, PATHINFO_EXTENSION) === 'svg' ? 'svg+xml' : pathinfo($siteFavicon, PATHINFO_EXTENSION) ?>">
    <?php endif; ?>

    <meta name="description" content="<?= htmlspecialchars($ogDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">

    <!-- OpenGraph -->
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <?php if ($ogImage): ?><meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>
    <meta property="og:site_name"   content="<?= SITE_TITLE ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <?php if ($ogImage): ?><meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>

    <?php if ($jsonLdJson): ?>
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
<?= $jsonLdJson ?>
    </script>
    <?php endif; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo AssetManager::renderStyles(['themes/default/style.css']); ?>
    
    <!-- User injected head -->
    <?php if (isset($additionalHead)) echo $additionalHead; ?>
    
    <!-- Module hooks -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
</head>
<body class="text-gray-800 antialiased">
    <!-- Global Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); } ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center flex-shrink-0">
                <a href="<?= SITE_URL ?>" class="flex items-center gap-3">
                    <?php
                        $siteLogo = $appSettings['site_logo'] ?? '';
                        if ($siteLogo && file_exists(__DIR__ . '/../uploads/branding/' . $siteLogo)):
                    ?>
                        <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="<?= SITE_TITLE ?>" class="h-10 w-auto object-contain">
                    <?php else: ?>
                        <span class="text-2xl font-bold serif tracking-tight"><?= SITE_TITLE ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if (!$hideHeaderSearch): ?>
            <!-- Global Search Bar -->
            <div class="flex-1 max-w-2xl ml-8 hidden md:block">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <?php 
                        // Preserve active filters
                        $p_cats = $searchParams['category_ids'] ?? [];
                        foreach ($p_cats as $cid): 
                    ?>
                        <input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>">
                    <?php endforeach; ?>
                    <?php if (!empty($searchParams['has_images'])): ?>
                        <input type="hidden" name="has_images" value="1">
                    <?php endif; ?>
                    <?php if (!empty($searchParams['tag'])): ?>
                        <input type="hidden" name="tag" value="<?= htmlspecialchars($searchParams['tag']) ?>">
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchParams['q'] ?? '') ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-gray-900 focus:border-gray-900 sm:text-sm transition duration-150 ease-in-out" placeholder="Search the collections... (e.g. Steam Engine)">
                </form>
            </div>
            <?php endif; ?>

            <?php if (function_exists('renderFrontendNav')) { renderFrontendNav($currentMenu); } ?>
        </div>
    </header>
