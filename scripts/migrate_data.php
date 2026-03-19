<?php
/**
 * Migration Script: migrate_data.php
 * Extracts data from https://postcardcollection.sujaysreedhar.com/
 * and imports it into the local database.
 */

set_time_limit(0);
require_once __DIR__ . '/../config/config.php';

$sourceUrl = "https://postcardcollection.sujaysreedhar.com/";
$itemsCategory = 10; // ID for "Postal Cancellations"
$uploadDir = __DIR__ . '/../uploads/originals/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function fetchHtml($url) {
    echo "Fetching: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) MigrationScript/1.0');
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// 0. Cleanup existing migration data to prevent duplicates
echo "Cleaning up previous migration attempts...\n";
$pdo->exec("DELETE FROM media WHERE item_id IN (SELECT id FROM items WHERE category_id = $itemsCategory)");
$pdo->exec("DELETE FROM item_tag WHERE item_id IN (SELECT id FROM items WHERE category_id = $itemsCategory)");
$pdo->exec("DELETE FROM items WHERE category_id = $itemsCategory");
$pdo->exec("DELETE FROM postmark_locations WHERE is_acquired = 1 AND latitude IS NULL");

// 1. Get all entry URLs
$homepageHtml = fetchHtml($sourceUrl);
$entryUrls = [];
if ($homepageHtml) {
    preg_match_all('/href="(entries\/[^"]+\.html)"/', $homepageHtml, $matches);
    $entryUrls = array_unique($matches[1]);
}

echo "Found " . count($entryUrls) . " entries.\n";

$count = 0;
foreach ($entryUrls as $relativeUrl) {
    $fullUrl = $sourceUrl . ltrim($relativeUrl, '/');
    $html = fetchHtml($fullUrl);
    if (!$html) continue;

    // Use DOMDocument for parsing
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Extract Data
    $title = $xpath->evaluate("string(//h1)") ?: "Untitled Card";
    
    // Extract metadata from infobox-table
    $meta = [];
    $rows = $xpath->query("//table[contains(@class, 'infobox-table')]//tr");
    foreach ($rows as $row) {
        $th = $xpath->query("th", $row)->item(0);
        $td = $xpath->query("td", $row)->item(0);
        if ($th && $td) {
            $key = strtolower(trim($th->nodeValue));
            $val = trim($td->nodeValue);
            if ($key && $val) {
                $meta[$key] = $val;
            }
        }
    }

    // Extract Description
    $description = "";
    $pNodes = $xpath->query("//p");
    foreach ($pNodes as $p) {
        $txt = trim($p->nodeValue);
        // Exclude footer/header text
        if ($txt && strlen($txt) > 20 && strpos($txt, 'Search results') === false && strpos($txt, 'Sujay Sreedhar') === false) {
            $description = $txt;
            break;
        }
    }

    // Extract Image from infobox-image
    $imageUrl = "";
    $imgNode = $xpath->query("//tr[contains(@class, 'infobox-image')]//img")->item(0);
    if ($imgNode) {
        $imgSrc = $imgNode->getAttribute('src');
        // Resolve relative path: ../assets/images/... -> assets/images/...
        if (strpos($imgSrc, '../') === 0) {
            $imgSrc = substr($imgSrc, 3);
        }
        $imageUrl = $sourceUrl . ltrim($imgSrc, '/');
    }

    // --- Prepare Data for Insertion ---
    $year = $meta['year'] ?? '';
    $origin = $meta['origin'] ?? '';
    $region = $meta['region'] ?? '';
    $type = $meta['type'] ?? '';
    
    // The previous analysis showed "Location" wasn't in the table on some pages.
    // Let's try to grab it from a specific paragraph with bi-geo-alt icon if possible, 
    // or regex the whole HTML.
    $locationStr = "";
    // On the homepage, it was <p class="card-text text-muted small"><i class="bi bi-geo-alt"></i> Mumbai</p>
    // In the detail page, let's look for similar.
    if (preg_match('/<i class="bi bi-geo-alt"><\/i>\s*([^<]+)/', $html, $locMatch)) {
        $locationStr = trim($locMatch[1]);
    }

    // Normalize PIN and Post Office
    $pin = "";
    $postOffice = "";
    if (preg_match('/^(.*?)\s*-\s*(\d{6})$/', $locationStr, $locMatches)) {
        $postOffice = trim($locMatches[1]);
        $pin = $locMatches[2];
    } else {
        $postOffice = $locationStr;
    }

    // --- 2. Insert into items ---
    $regNumber = "PC-" . sprintf("%04d", ++$count);
    $stmt = $pdo->prepare("INSERT INTO items (category_id, reg_number, title, physical_description, historical_significance, production_date, is_visible) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$itemsCategory, $regNumber, $title, $description, "Origin: $origin", $year]);
    $itemId = $pdo->lastInsertId();

    // --- 3. Handle Image ---
    if ($imageUrl) {
        $fileName = basename($imageUrl);
        $localPath = $uploadDir . $fileName;
        echo "Downloading image: $imageUrl\n";
        $imgData = @file_get_contents($imageUrl);
        if ($imgData) {
            file_put_contents($localPath, $imgData);
            
            // Insert into media
            $stmt = $pdo->prepare("INSERT INTO media (item_id, file_path, is_primary, media_type) VALUES (?, ?, 1, 'image')");
            $stmt->execute([$itemId, $fileName]);
        }
    }

    // --- 4. Handle Tags ---
    $tags = array_filter([$type, $region]);
    foreach ($tags as $tagName) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $tagName));
        $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (?, ?)");
        $stmt->execute([$tagName, $slug]);
        
        $tagIdStmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
        $tagIdStmt->execute([$tagName]);
        $tagId = $tagIdStmt->fetchColumn();
        
        if ($tagId) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO item_tag (item_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$itemId, $tagId]);
        }
    }

    // --- 5. Handle Atlas Locations ---
    if ($postOffice || $pin) {
        $stmt = $pdo->prepare("INSERT INTO postmark_locations (pin_code, post_office, state, is_acquired) VALUES (?, ?, ?, 1)");
        $stmt->execute([$pin, $postOffice, $region]);
    }

    echo "Imported: $title\n";
}

echo "\nMigration Complete. Imported $count items.\n";
