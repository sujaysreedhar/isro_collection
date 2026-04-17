<?php
/**
 * scripts/optimize_existing_images.php
 * 
 * One-time migration script to optimize existing category and branding images.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/SafePDO.php';
require_once __DIR__ . '/../includes/MediaProcessor.php';

// config.php initializes $pdo
$pdo = $GLOBALS['pdo'];
$uploadRoot = __DIR__ . '/../uploads/';

echo "Starting image optimization migration...\n";

// 1. Categories
$categoriesDir = $uploadRoot . 'categories/';
if (is_dir($categoriesDir)) {
    echo "Processing Categories...\n";
    $stmt = $pdo->query("SELECT id, image_path FROM categories WHERE image_path IS NOT NULL AND image_path != ''");
    while ($cat = $stmt->fetch()) {
        $oldFile = $cat['image_path'];
        $oldPath = $categoriesDir . $oldFile;
        
        if (file_exists($oldPath)) {
            $newFile = pathinfo($oldFile, PATHINFO_FILENAME) . '.webp';
            $newPath = $categoriesDir . $newFile;
            
            echo "  Optimizing: $oldFile -> $newFile\n";
            if (MediaProcessor::optimizeImage($oldPath, $newPath, 800, 800, 85)) {
                $pdo->prepare("UPDATE categories SET image_path = ? WHERE id = ?")->execute([$newFile, $cat['id']]);
                if ($oldFile !== $newFile) {
                    unlink($oldPath);
                }
            } else {
                echo "    [ERROR] Failed to optimize $oldFile\n";
            }
        }
    }
}

// 2. Branding (Hero Image)
$brandingDir = $uploadRoot . 'branding/';
if (is_dir($brandingDir)) {
    echo "Processing Branding (Hero Image)...\n";
    $heroImage = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'theme_studio_hero_image'")->fetchColumn();
    
    if ($heroImage && file_exists($brandingDir . $heroImage)) {
        $oldPath = $brandingDir . $heroImage;
        $newFile = pathinfo($heroImage, PATHINFO_FILENAME) . '.webp';
        $newPath = $brandingDir . $newFile;
        
        echo "  Optimizing Hero: $heroImage -> $newFile\n";
        if (MediaProcessor::optimizeImage($oldPath, $newPath, 2000, 2000, 80)) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'theme_studio_hero_image'")->execute([$newFile]);
            if ($heroImage !== $newFile) {
                unlink($oldPath);
            }
        } else {
            echo "    [ERROR] Failed to optimize Hero image\n";
        }
    }

    // Also optimize Site Logo if it exists
    $siteLogo = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'")->fetchColumn();
    if ($siteLogo && file_exists($brandingDir . $siteLogo)) {
        $oldPath = $brandingDir . $siteLogo;
        // Don't convert SVG logos
        if (pathinfo($siteLogo, PATHINFO_EXTENSION) !== 'svg') {
            $newFile = pathinfo($siteLogo, PATHINFO_FILENAME) . '.webp';
            $newPath = $brandingDir . $newFile;
            echo "  Optimizing Logo: $siteLogo -> $newFile\n";
            if (MediaProcessor::optimizeImage($oldPath, $newPath, 400, 400, 90)) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_logo'")->execute([$newFile]);
                if ($siteLogo !== $newFile) {
                    unlink($oldPath);
                }
            }
        }
    }
}

echo "Migration completed.\n";
