<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$apply = in_array('--apply', $argv, true);
$afterId = 0;
$limit = 100;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--after-id=')) $afterId = max(0, (int)substr($arg, 11));
    if (str_starts_with($arg, '--limit=')) $limit = max(1, min(1000, (int)substr($arg, 8)));
}
if ($apply && !in_array('--confirm-media-write', $argv, true)) {
    fwrite(STDERR, "--apply requires --confirm-media-write. Review the dry run first.\n");
    exit(2);
}

require_once __DIR__ . '/../config/config.php';
$statement = $pdo->prepare("SELECT id, item_id, file_path FROM media WHERE media_type = 'image' AND id > ? ORDER BY id ASC LIMIT ?");
$statement->bindValue(1, $afterId, PDO::PARAM_INT);
$statement->bindValue(2, $limit, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
$lastId = $afterId;
$converted = $skipped = 0;
foreach ($rows as $row) {
    $id = (int)$row['id'];
    $lastId = $id;
    $filename = basename((string)$row['file_path']);
    if ($filename !== $row['file_path'] || preg_match('/^img_\d+_[a-f0-9]{16}_1200\.webp$/', $filename)) {
        $skipped++;
        continue;
    }
    $source = null;
    foreach (['original', 'display'] as $dir) {
        $candidate = ABSPATH . '/uploads/' . $dir . '/' . $filename;
        if (is_file($candidate)) { $source = $candidate; break; }
    }
    if ($source === null) {
        fwrite(STDERR, "Skip media $id: local source unavailable; restore it before migrating S3-only media.\n");
        $skipped++;
        continue;
    }
    try { $info = ImageUploadValidator::inspect($source, false); }
    catch (DomainException $error) {
        fwrite(STDERR, "Skip media $id: " . $error->getMessage() . "\n");
        $skipped++;
        continue;
    }
    $base = 'img_' . (int)$row['item_id'] . '_' . substr(hash('sha256', $id . ':' . $filename), 0, 16);
    $newName = $base . '_1200.webp';
    echo ($apply ? 'Convert' : 'Would convert') . " media $id ($filename, {$info['width']}x{$info['height']}) -> $newName\n";
    if (!$apply) { $converted++; continue; }
    $created = [];
    try {
        $originalPath = 'original/' . $base . '.' . match ($info['mime']) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'
        };
        if (!$storage->put($originalPath, $source, $info['mime'])) throw new RuntimeException('Original write failed.');
        $created[] = $originalPath;
        foreach ([200, 640, 1200] as $size) {
            $temp = tempnam(sys_get_temp_dir(), 'audit-variant-');
            if ($temp === false) throw new RuntimeException('Temporary image unavailable.');
            try {
                if (!MediaProcessor::optimizeImage($source, $temp, $size, $size, 85, $size === 200)) throw new RuntimeException('Image encode failed.');
                $path = "thumbnails/{$base}_{$size}.webp";
                if (!$storage->put($path, $temp, 'image/webp')) throw new RuntimeException('Variant write failed.');
                $created[] = $path;
            } finally { @unlink($temp); }
        }
        $update = $pdo->prepare('UPDATE media SET file_path = ? WHERE id = ? AND file_path = ?');
        $update->execute([$newName, $id, $filename]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Media row changed concurrently.');
        $converted++;
    } catch (Throwable $error) {
        foreach ($created as $path) $storage->delete($path);
        fwrite(STDERR, "Failed media $id: " . $error->getMessage() . "\n");
        $skipped++;
    }
}
echo json_encode(['mode' => $apply ? 'apply' : 'dry-run', 'converted' => $converted, 'skipped' => $skipped, 'last_id' => $lastId], JSON_UNESCAPED_SLASHES) . "\n";
