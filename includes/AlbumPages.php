<?php
declare(strict_types=1);

/** Printable album rendering. Only explicitly selected public catalogue fields enter a PDF. */
final class AlbumPages
{
    private static function availableMemory(): ?int
    {
        $value = trim((string)ini_get('memory_limit'));
        if ($value === '-1' || !preg_match('/^(\d+)([KMG]?)$/i', $value, $match)) return null;
        $factor = ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824][strtoupper($match[2])];
        return max(0, (int)$match[1] * $factor - memory_get_usage(true));
    }

    public static function ids(string $input): array
    {
        $parts = preg_split('/[\s,]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts || count($parts) > 60) throw new DomainException('Select between 1 and 60 items per album.');
        $ids = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part) || strlen($part) > 10 || (int)$part < 1 || (int)$part > 2147483647) throw new DomainException('Use positive item IDs separated by commas.');
            $ids[] = (int)$part;
        }
        return array_values(array_unique($ids));
    }

    public static function options(array $input): array
    {
        $title = trim((string)($input['album_title'] ?? 'My collection'));
        if ($title === '' || mb_strlen($title) > 120) throw new DomainException('Album title must contain 1 to 120 characters.');
        $size = (string)($input['page_size'] ?? 'A4');
        $perPage = (int)($input['per_page'] ?? 4);
        if (!in_array($size, ['A4', 'letter'], true) || !in_array($perPage, [1, 2, 4, 6], true)) throw new DomainException('Choose a valid paper size and page layout.');
        return ['title' => $title, 'size' => $size, 'per_page' => $perPage,
            'captions' => !empty($input['captions']), 'qr' => !empty($input['qr']), 'description' => !empty($input['description'])];
    }

    public static function items(PDO $pdo, array $ids): array
    {
        // No SELECT *, private joins, hidden records or module metadata in a shareable album.
        $primaryOrder = AppConfig::get('media_has_is_primary', '0') === '1' ? 'm.is_primary DESC, ' : '';
        $sql = "SELECT i.id, i.reg_number, i.title, i.production_date, i.physical_description,
            (SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$primaryOrder}m.id ASC LIMIT 1) AS image,
            (SELECT m.caption FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$primaryOrder}m.id ASC LIMIT 1) AS caption
            FROM items i WHERE i.is_visible = 1 AND i.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) $byId[(int)$item['id']] = $item;
        if (count($byId) !== count($ids)) throw new DomainException('Some selected items are missing or private. Select published items for a shareable album.');
        return array_map(static fn($id) => $byId[$id], $ids);
    }

    private static function imageData(string $filename, ?StorageInterface $storage): string
    {
        if ($filename === '' || basename($filename) !== $filename) return '';
        $paths = array_filter([MediaProcessor::originalStoragePath($filename, $storage), MediaProcessor::variantStoragePath($filename, 'display')]);
        foreach ($paths as $path) {
            $local = ABSPATH . '/uploads/' . $path;
            $bytes = null;
            if (is_file($local) && filesize($local) <= MediaProcessor::MAX_IMG_BYTES) $bytes = file_get_contents($local);
            elseif ($storage instanceof S3Storage) $bytes = $storage->albumImageBytes($path);
            if (!is_string($bytes) || $bytes === '') continue;
            $info = @getimagesizefromstring($bytes);
            if (!$info || $info[0] * $info[1] > 20000000 || !in_array($info['mime'] ?? '', MediaProcessor::ALLOWED_IMG, true)) continue;
            $available = self::availableMemory();
            // Prefer a smaller preview if decoding the high-resolution source would exhaust the server.
            if ($available !== null && $info[0] * $info[1] * 8 + 40 * 1048576 > $available) { unset($bytes); continue; }
            $source = @imagecreatefromstring($bytes);
            unset($bytes);
            if (!$source) continue;
            $ratio = min(1, 1800 / max($info[0], $info[1]));
            $image = imagecreatetruecolor(max(1, (int)round($info[0] * $ratio)), max(1, (int)round($info[1] * $ratio)));
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $info[0], $info[1]);
            ob_start(); imagejpeg($image, null, 92); $jpeg = ob_get_clean();
            unset($image, $source);
            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        }
        return '';
    }

    public static function html(array $items, array $options, ?StorageInterface $storage = null): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $perPage = $options['per_page'];
        $columns = $perPage <= 2 ? 1 : 2;
        $rows = (int)ceil($perPage / $columns);
        $pageHeight = $options['size'] === 'letter' ? 279.4 : 297;
        $pageWidth = $options['size'] === 'letter' ? 215.9 : 210;
        // Give every sheet a physical budget. Fixed positioned cells avoid table rows expanding
        // beyond their requested height when Dompdf adds padding, wrapped text, and QR images.
        $sheetHeight = $pageHeight - 29;
        $sheetWidth = $pageWidth - 28;
        $gridTop = 30;
        $gap = 4;
        $cellWidth = ($sheetWidth - $gap * ($columns - 1)) / $columns;
        $cellHeight = ($sheetHeight - $gridTop - $gap * ($rows - 1) - 1) / $rows;
        $captionLimit = $perPage === 6 ? 80 : ($columns === 2 ? 120 : 180);
        $descriptionLimit = $perPage === 6 ? 100 : ($columns === 2 ? 180 : 240);
        $textHeight = ($options['qr'] ? 20 : 16) + ($options['captions'] ? ($columns === 2 ? 10 : 7) : 0) + ($options['description'] ? ($columns === 2 ? 15 : 10) : 0);
        $imageHeight = max(18, $cellHeight - $textHeight - 10);
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:14mm}body{font-family:DejaVu Sans,sans-serif;color:#20252c;font-size:9pt;margin:0;padding:0}.sheet{position:relative;width:' . $sheetWidth . 'mm;height:' . $sheetHeight . 'mm;margin:0;padding:0}header{position:absolute;left:0;top:0;width:100%;height:25mm;border-bottom:1px solid #c8ced6;overflow:hidden}h1{font-size:16pt;line-height:1.15;margin:0 0 2mm;word-wrap:break-word}small{color:#526071;font-size:7.5pt;line-height:1.2}.cell{position:absolute;border:1px solid #e1e5ea;margin:0;padding:0;overflow:hidden}.picture{position:absolute;left:3mm;right:3mm;top:3mm;height:' . $imageHeight . 'mm;text-align:center;line-height:' . $imageHeight . 'mm}.scan{max-width:100%;max-height:' . $imageHeight . 'mm;vertical-align:middle}.record{position:absolute;left:3mm;right:3mm;top:' . ($imageHeight + 6) . 'mm;overflow:hidden}.record-heading{position:relative;min-height:' . ($options['qr'] ? 20 : 16) . 'mm;overflow:hidden}.record-text{margin-right:' . ($options['qr'] ? 22 : 0) . 'mm}.qr{position:absolute;top:0;right:0;width:18mm;height:18mm}h2{font-size:9.5pt;line-height:1.2;margin:0 0 1mm;word-wrap:break-word}p{margin:1mm 0 0;font-size:7.5pt;line-height:1.3;word-wrap:break-word}.blank{font-size:8pt;color:#697586}</style></head><body>';
        $chunks = array_chunk($items, $perPage);
        foreach ($chunks as $page => $chunk) {
            $html .= '<section class="sheet"' . ($page > 0 ? ' style="page-break-before:always"' : '') . '><header><h1>' . $e($options['title']) . '</h1><small>' . $e(mb_strimwidth(SITE_TITLE, 0, 100, '…')) . ' &middot; ' . ($page + 1) . ' / ' . count($chunks) . '</small></header>';
            foreach ($chunk as $index => $item) {
                $left = ($index % $columns) * ($cellWidth + $gap);
                $top = $gridTop + (int)floor($index / $columns) * ($cellHeight + $gap);
                $image = self::imageData((string)($item['image'] ?? ''), $storage);
                $url = SITE_URL . '/item/' . (int)$item['id'];
                $html .= '<div class="cell" style="left:' . $left . 'mm;top:' . $top . 'mm;width:' . ($cellWidth - 0.4) . 'mm;height:' . ($cellHeight - 0.4) . 'mm"><div class="picture">' . ($image ? '<img class="scan" src="' . $image . '" alt="' . $e($item['title']) . '">' : '<span class="blank">Image not available</span>') . '</div><div class="record"><div class="record-heading">';
                if ($options['qr']) {
                    $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(160, 4), new \BaconQrCode\Renderer\Image\SvgImageBackEnd()));
                    $html .= '<a href="' . $e($url) . '"><img class="qr" alt="Record QR code" src="data:image/svg+xml;base64,' . base64_encode($writer->writeString($url)) . '"></a>';
                }
                $html .= '<div class="record-text"><h2>' . $e(mb_strimwidth($item['title'], 0, $columns === 2 ? ($perPage === 6 ? 55 : 72) : 130, '…')) . '</h2><small>' . $e(mb_strimwidth($item['reg_number'], 0, $columns === 2 ? 25 : 45, '…')) . '<br>' . $e(mb_strimwidth($item['production_date'] ?? '', 0, 30, '…')) . '</small></div></div>';
                if ($options['captions'] && !empty($item['caption'])) $html .= '<p>' . $e(mb_strimwidth(strip_tags($item['caption']), 0, $captionLimit, '…')) . '</p>';
                if ($options['description']) $html .= '<p>' . $e(mb_strimwidth(strip_tags($item['physical_description'] ?? ''), 0, $descriptionLimit, '…')) . '</p>';
                $html .= '</div></div>';
            }
            $html .= '</section>';
        }
        return $html . '</body></html>';
    }

    public static function pdf(array $items, array $options, ?StorageInterface $storage = null): string
    {
        $available = self::availableMemory();
        if ($available !== null && count($items) * 4 * 1048576 + 32 * 1048576 > $available) {
            throw new DomainException('This album is too large for the server memory limit. Select fewer items and generate another album.');
        }
        $settings = new \Dompdf\Options();
        $settings->set('isRemoteEnabled', false);
        $settings->set('isPhpEnabled', false);
        $settings->set('isJavascriptEnabled', false);
        $settings->set('chroot', ABSPATH . '/uploads');
        $settings->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($settings);
        $pdf->setPaper($options['size'], 'portrait');
        $pdf->loadHtml(self::html($items, $options, $storage), 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }
}
