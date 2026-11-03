<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

$error = '';
$previewItems = [];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [
    'item_ids' => $_GET['ids'] ?? '', 'album_title' => 'My collection', 'page_size' => 'A4', 'per_page' => 4, 'captions' => 1,
];
$dependenciesReady = class_exists('Dompdf\\Dompdf') && class_exists('BaconQrCode\\Writer');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); throw new DomainException('Security check failed. Reload the page and try again.'); }
        if (!$dependenciesReady) throw new DomainException('Install the application Composer dependencies to enable PDF generation.');
        $ids = AlbumPages::ids((string)($_POST['item_ids'] ?? ''));
        $options = AlbumPages::options($_POST);
        $previewItems = AlbumPages::items($pdo, $ids);
        if (($_POST['action'] ?? '') === 'download') {
            $bytes = AlbumPages::pdf($previewItems, $options, $storage ?? null);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="collection-album.pdf"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes; exit;
        }
    } catch (DomainException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Album PDF: ' . $e->getMessage()); $error = 'The album could not be generated. Check the selected images and try a smaller selection.'; }
}
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo renderAdminHeader('Printable album pages');
?>
<style>
.album-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.8fr);gap:24px}.album-panel{background:#fff;border:1px solid #dce1e7;border-radius:10px;padding:24px}.album-panel label{display:block;font-size:14px;font-weight:600;margin:16px 0 6px}.album-panel input:not([type=checkbox]),.album-panel textarea,.album-panel select{width:100%;border:1px solid #b6c0cd;border-radius:6px;padding:10px;color:#172033;background:white}.album-panel input:focus-visible,.album-panel textarea:focus-visible,.album-panel select:focus-visible,.album-panel button:focus-visible{outline:3px solid #1677ff;outline-offset:2px}.album-order li{display:flex;gap:12px;align-items:center;border-bottom:1px solid #e6e9ed;padding:12px 0}.album-order li span{flex:1;min-width:0;overflow-wrap:anywhere}.album-order button{border:1px solid #c2cad5;border-radius:5px;padding:6px 10px}.album-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.album-actions button{border:1px solid #a4afbd;border-radius:6px;padding:10px 16px}.album-actions .primary{background:#172033;color:#fff;border-color:#172033}.album-actions button:disabled{opacity:.5;cursor:not-allowed}.album-note{font-size:13px;line-height:1.6;color:#526071}@media(max-width:800px){.album-layout{grid-template-columns:1fr}.album-panel{padding:16px}}
</style>
<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Printable album pages</h1><p class="text-sm text-gray-600 mt-2">Choose catalogue items, arrange their order, and download an album with complete images.</p></div>
<?php if ($error): ?><div role="alert" class="p-4 mb-4 rounded border border-red-200 bg-red-50 text-red-800"><?= $e($error) ?></div><?php endif; ?>
<?php if (!$dependenciesReady): ?><div role="status" class="p-4 mb-4 bg-amber-50 border border-amber-200 rounded">PDF libraries are missing. Run <code>composer install</code> on the server.</div><?php endif; ?>
<form method="post" class="album-layout" id="album-form">
    <?= Csrf::input() ?>
    <section class="album-panel" aria-labelledby="album-options"><h2 id="album-options" class="text-lg font-semibold">Page setup</h2>
        <label for="album-title">Album title</label><input id="album-title" name="album_title" maxlength="120" required value="<?= $e($input['album_title'] ?? '') ?>">
        <label for="album-items">Item IDs in page order</label><textarea id="album-items" name="item_ids" rows="3" required aria-describedby="album-item-help"><?= $e($input['item_ids'] ?? '') ?></textarea>
        <p id="album-item-help" class="album-note mt-2">Separate IDs with commas. Select up to 60 published items from <a class="underline" href="<?= SITE_URL ?>/admin/items.php">Manage items</a>, then choose Album pages. Preview to reorder them below.</p>
        <div class="grid grid-cols-2 gap-4"><div><label for="album-size">Paper size</label><select id="album-size" name="page_size"><?php foreach (['A4' => 'A4', 'letter' => 'US Letter'] as $v => $label): ?><option value="<?= $v ?>" <?= ($input['page_size'] ?? '') === $v ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div><label for="album-count">Items per page</label><select id="album-count" name="per_page"><?php foreach ([1,2,4,6] as $v): ?><option value="<?= $v ?>" <?= (int)($input['per_page'] ?? 4) === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div></div>
        <label><input type="checkbox" name="captions" value="1" <?= !empty($input['captions']) ? 'checked' : '' ?>> Include image captions</label>
        <label><input type="checkbox" name="description" value="1" <?= !empty($input['description']) ? 'checked' : '' ?>> Include short catalogue descriptions</label>
        <label><input type="checkbox" name="qr" value="1" <?= !empty($input['qr']) ? 'checked' : '' ?>> Add QR links to public item records</label>
        <div class="album-actions"><button type="submit" name="action" value="preview">Preview selection</button><button class="primary" type="submit" name="action" value="download" <?= $dependenciesReady ? '' : 'disabled' ?>>Download PDF</button></div>
    </section>
    <section class="album-panel" aria-labelledby="album-selection"><h2 id="album-selection" class="text-lg font-semibold">Album order</h2>
        <p class="album-note mt-2">Move items up or down to arrange the pages. Images retain their original proportions.</p>
        <?php if (!$previewItems): ?><p class="mt-8 text-gray-500">Preview a selection to see the items here.</p><?php else: ?>
        <ol class="album-order mt-4" id="album-order"><?php foreach ($previewItems as $i): ?><li data-id="<?= (int)$i['id'] ?>"><span><strong><?= $e($i['title']) ?></strong><br><small><?= $e($i['reg_number']) ?> · #<?= (int)$i['id'] ?></small></span><button type="button" data-move="-1" aria-label="Move <?= $e($i['title']) ?> up">↑</button><button type="button" data-move="1" aria-label="Move <?= $e($i['title']) ?> down">↓</button></li><?php endforeach; ?></ol>
        <?php endif; ?>
        <p class="album-note mt-6">Shareable PDFs contain catalogue details only. Purchase prices, valuations, storage locations, and private notes stay in the admin panel.</p>
        <p id="album-status" role="status" class="album-note mt-4"></p>
    </section>
</form>
<script>
document.getElementById('album-order')?.addEventListener('click', event => {
    const button = event.target.closest('button[data-move]'); if (!button) return;
    const row = button.closest('li'), list = row.parentNode, direction = Number(button.dataset.move);
    const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    if (direction < 0) list.insertBefore(row, sibling); else list.insertBefore(sibling, row);
    document.getElementById('album-items').value = Array.from(list.children, el => el.dataset.id).join(', ');
    button.focus(); document.getElementById('album-status').textContent = 'Album order updated.';
});
</script>
<?= renderAdminFooter() ?>
