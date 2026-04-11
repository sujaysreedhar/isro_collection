<?php
require_once __DIR__ . '/../config/config.php';
// Need to mock BaseModule or include it
require_once __DIR__ . '/../includes/BaseModule.php';
require_once __DIR__ . '/../modules/sitemap/module.php';

$sitemap = new SitemapModule($pdo, 'sitemap', []);
ob_start();
$sitemap->generateSitemap();
$output = ob_get_clean();
file_put_contents(__DIR__ . '/sitemap_output.xml', $output);
echo "Sitemap generated to scripts/sitemap_output.xml\n";
