<?php
$service = new WishlistService($this->pdo);
$h = static fn($value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
if (!$service->schemaReady()) {
    echo '<div role="status" class="rounded-lg border border-amber-300 bg-amber-50 p-5 text-amber-900"><h2 class="font-semibold">Seller tracking needs a database update</h2><p class="mt-2">Apply the project update.sql using the database updater, then reload this page. Your existing wanted items and seller links are preserved.</p></div>';
    return;
}
$items = $service->all();
$themes = [];
try { $themes = $this->pdo->query('SELECT id,name FROM module_themes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { /* Taxonomy is optional. */ }
$themeMap = [];
foreach ($themes as $theme) $themeMap[$theme['id']] = $theme['name'];
$pending = 0; $offerCount = 0;
foreach ($items as $item) { if ($item['status'] !== 'acquired') $pending++; foreach ($item['stores'] as $offer) if (!$offer['archived']) $offerCount++; }
$money = static fn($value) => $value === null ? 'Not recorded' : number_format((float)$value, 2);
$safeLink = static function ($url): bool { return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http','https'], true); };
?>
<link rel="stylesheet" href="<?= $h(SITE_URL) ?>/modules/wishlist/assets/wishlist.css">
<section class="wantlist" aria-labelledby="wantlist-title">
    <div class="wl-heading">
        <div><h1 id="wantlist-title">Wantlist & seller tracking</h1><p>Keep each stamp or cover together with its seller links and dated price observations. Visible to administrators only.</p></div>
        <div class="wl-actions"><a class="wl-button" href="<?= $h(SITE_URL) ?>/admin/ajax.php?action=wishlist_export">Export CSV</a><button class="wl-button" type="button" id="wl-bulk-open">Add several items</button><button class="wl-button wl-primary" type="button" id="wl-add">Add wanted item</button></div>
    </div>
    <div class="wl-summary"><span><strong><?= count($items) ?></strong> wanted items</span><span><strong><?= $pending ?></strong> still to acquire</span><span><strong><?= $offerCount ?></strong> seller offers</span></div>
    <div id="wl-notice" role="status" aria-live="polite" hidden></div>
    <div class="wl-filters">
        <label>Search<input type="search" id="wl-search" placeholder="Name, catalogue reference, seller…"></label>
        <label>Status<select id="wl-filter-status"><option value="">All statuses</option><?php foreach (WishlistService::STATUSES as $value => $label): ?><option value="<?= $h($value) ?>"><?= $h($label) ?></option><?php endforeach; ?></select></label>
        <label>Type<select id="wl-filter-type"><option value="">All types</option><option value="stamp">Stamp</option><option value="cover">Cover</option><option value="other">Other</option></select></label>
        <label>Theme<select id="wl-filter-theme"><option value="">All themes</option><option value="0">Unassigned</option><?php foreach ($themes as $theme): ?><option value="<?= (int)$theme['id'] ?>"><?= $h($theme['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <p class="wl-help">Prices are entered manually. Best offers include shipping and compare available offers within the same currency. Leave shipping blank when unknown; enter 0 for free shipping.</p>
    <div id="wl-items">
    <?php foreach ($items as $item): $activeOffers = array_filter($item['stores'], static fn($offer) => !$offer['archived']); $archivedOffers = array_filter($item['stores'], static fn($offer) => $offer['archived']); ?>
        <article class="wl-item" data-item="<?= (int)$item['id'] ?>" data-status="<?= $h($item['status']) ?>" data-type="<?= $h($item['item_type']) ?>" data-theme="<?= (int)$item['theme_id'] ?>">
            <header class="wl-item-heading">
                <?php if (!empty($item['image_url'])): $imageName = rawurlencode(basename($item['image_url'])); ?>
                <a href="<?= $h(SITE_URL) ?>/uploads/originals/<?= $h($imageName) ?>" target="_blank" rel="noopener noreferrer" aria-label="View full reference image for <?= $h($item['name']) ?>"><img class="wl-reference" src="<?= $h(SITE_URL) ?>/uploads/<?= is_file(ABSPATH . '/uploads/thumbnails/' . basename($item['image_url'])) ? 'thumbnails' : 'originals' ?>/<?= $h($imageName) ?>" alt="<?= $h($item['name']) ?>" loading="lazy"></a>
                <?php endif; ?>
                <div class="wl-item-title"><h2><?= $h($item['name']) ?></h2><p><?= $h(ucfirst($item['item_type'])) ?><?= $item['catalogue_ref'] ? ' · ' . $h($item['catalogue_ref']) : '' ?> · <?= $h($themeMap[$item['theme_id']] ?? 'Unassigned theme') ?></p>
                    <div class="wl-meta"><span class="wl-badge wl-status-<?= $h($item['status']) ?>"><?= $h(WishlistService::STATUSES[$item['status']] ?? $item['status']) ?></span><span>Priority <?= (int)$item['priority'] ?>/5</span><?php if ($item['target_price'] !== null): ?><span>Target <?= $h($item['target_currency']) ?> <?= $money($item['target_price']) ?></span><?php endif; ?></div>
                </div>
                <div class="wl-actions"><button type="button" class="wl-button" data-edit="<?= (int)$item['id'] ?>">Edit item & offers</button><?php if (!empty($item['collection_item_id'])): ?><a class="wl-button" href="<?= $h(SITE_URL) ?>/admin/edit_item.php?id=<?= (int)$item['collection_item_id'] ?>">Collection record</a><?php else: ?><button type="button" class="wl-button" data-migrate="<?= (int)$item['id'] ?>">Add to collection</button><?php endif; ?><button type="button" class="wl-button wl-danger" data-delete="<?= (int)$item['id'] ?>">Delete</button></div>
            </header>
            <?php if ($item['description']): ?><p class="wl-notes"><?= nl2br($h($item['description'])) ?></p><?php endif; ?>
            <?php if ($item['best']): ?><div class="wl-best">Best delivered offers: <?php foreach ($item['best'] as $currency => $best): ?><span><strong><?= $h($currency) ?> <?= number_format($best['total_cents'] / 100, 2) ?></strong> at <?= $h($best['seller']) ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if (!$activeOffers): ?><p class="wl-empty-offers">No seller offers yet. Edit this item to add links and compare prices.</p><?php else: ?>
            <div class="wl-table-wrap"><table><caption class="wl-sr-only">Seller offers for <?= $h($item['name']) ?></caption><thead><tr><th scope="col">Seller</th><th scope="col">Price</th><th scope="col">Shipping</th><th scope="col">Delivered total</th><th scope="col">Availability</th><th scope="col">Last checked</th><th scope="col">History & notes</th></tr></thead><tbody>
                <?php foreach ($activeOffers as $offer): $isBest = ($item['best'][$offer['currency'] ?? '']['id'] ?? null) === (int)$offer['id']; ?>
                <tr<?= $isBest ? ' class="wl-best-row"' : '' ?>><td><?php if ($safeLink($offer['store_url'])): ?><a href="<?= $h($offer['store_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $h($offer['store_name']) ?> ↗</a><?php else: ?><?= $h($offer['store_name']) ?><?php endif; ?><?php if ($isBest): ?><span class="wl-badge">Best in <?= $h($offer['currency']) ?></span><?php endif; ?></td>
                    <td><?= $offer['amount'] !== null ? $h($offer['currency']) . ' ' . $money($offer['amount']) : ($offer['price'] ? $h($offer['price']) . '<small>Legacy price; add amount and currency to compare.</small>' : 'Not recorded') ?></td>
                    <td><?= $offer['shipping'] !== null ? $h($offer['currency']) . ' ' . $money($offer['shipping']) : 'Unknown' ?></td>
                    <td><?= $offer['amount'] !== null && $offer['shipping'] !== null ? $h($offer['currency']) . ' ' . number_format((float)$offer['amount'] + (float)$offer['shipping'], 2) : 'Incomplete' ?></td>
                    <td><?= $h(WishlistService::AVAILABILITY[$offer['availability']] ?? 'Not confirmed') ?></td><td><?= $h($offer['last_checked'] ?: 'Not recorded') ?></td>
                    <td><details><summary><?= count($offer['history']) ?> <?= count($offer['history']) === 1 ? 'observation' : 'observations' ?></summary><?php if ($offer['notes']): ?><p class="wl-notes"><?= nl2br($h($offer['notes'])) ?></p><?php endif; ?><ol class="wl-history"><?php foreach ($offer['history'] as $entry): ?><li><strong><?= $h($entry['recorded_at']) ?></strong><br><?= $entry['amount'] === null ? 'No price recorded' : $h($entry['currency']) . ' ' . $money($entry['amount']) ?>; shipping <?= $entry['shipping'] === null ? 'unknown' : $h($entry['currency']) . ' ' . $money($entry['shipping']) ?>; <?= $h(WishlistService::AVAILABILITY[$entry['availability']] ?? '') ?><?= $entry['checked_on'] ? '; checked ' . $h($entry['checked_on']) : '' ?></li><?php endforeach; ?></ol></details></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
            <?php if ($archivedOffers): ?><details class="wl-archived"><summary><?= count($archivedOffers) ?> removed offers (history retained)</summary><?php foreach ($archivedOffers as $offer): ?><details><summary><?= $h($offer['store_name']) ?> · <?= count($offer['history']) ?> <?= count($offer['history']) === 1 ? 'observation' : 'observations' ?></summary><ol class="wl-history"><?php foreach ($offer['history'] as $entry): ?><li><?= $h($entry['recorded_at']) ?>: <?= $entry['amount'] === null ? 'No price recorded' : $h($entry['currency']) . ' ' . $money($entry['amount']) ?>; shipping <?= $entry['shipping'] === null ? 'unknown' : $h($entry['currency']) . ' ' . $money($entry['shipping']) ?>; <?= $h(WishlistService::AVAILABILITY[$entry['availability']] ?? '') ?></li><?php endforeach; ?></ol></details><?php endforeach; ?></details><?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
    <div id="wl-empty" class="wl-empty"<?= $items ? ' hidden' : '' ?>><h2><?= $items ? 'No matching wanted items' : 'Start your wantlist' ?></h2><p><?= $items ? 'Try a different search or filter.' : 'Add a stamp or cover, then keep every seller offer and price observation together.' ?></p></div>
</section>

<dialog id="wl-dialog" class="wl-dialog" aria-labelledby="wl-dialog-title">
<form id="wl-form" enctype="multipart/form-data">
    <header class="wl-dialog-heading"><div><h2 id="wl-dialog-title">Add wanted item</h2><p>Offers, prices, and notes are private to administrators.</p></div><button class="wl-button" type="button" data-close="wl-dialog" aria-label="Close wanted item editor">Close</button></header>
    <input type="hidden" name="id" id="wl-id"><input type="hidden" name="csrf_token" value="<?= $h(ensureCsrfToken()) ?>">
    <div id="wl-form-error" class="wl-error" role="alert" hidden></div>
    <div class="wl-form-grid">
        <label class="wl-span">Item name<input name="name" required maxlength="255" autofocus></label>
        <label>Type<select name="item_type"><option value="stamp">Stamp</option><option value="cover">Cover</option><option value="other">Other</option></select></label>
        <label>Catalogue reference<input name="catalogue_ref" maxlength="120" placeholder="For example, catalogue number"></label>
        <label>Theme<select name="theme_id"><option value="">Unassigned</option><?php foreach ($themes as $theme): ?><option value="<?= (int)$theme['id'] ?>"><?= $h($theme['name']) ?></option><?php endforeach; ?></select></label>
        <label>Status<select name="status"><?php foreach (WishlistService::STATUSES as $value => $label): ?><option value="<?= $h($value) ?>"><?= $h($label) ?></option><?php endforeach; ?></select></label>
        <label>Priority<select name="priority"><?php for ($p = 0; $p <= 5; $p++): ?><option value="<?= $p ?>"><?= $p ?><?= $p === 0 ? ' (none)' : ($p === 5 ? ' (highest)' : '') ?></option><?php endfor; ?></select></label>
        <label>Target price<input name="target_price" type="number" min="0" step="0.01" max="999999999999.99" inputmode="decimal"></label>
        <label>Target currency<input name="target_currency" maxlength="3" pattern="[A-Za-z]{3}" placeholder="INR / USD / EUR"></label>
        <label>Reference image<input name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" id="wl-image"><small>Original is preserved; the preview fits the full image.</small></label>
        <img id="wl-image-preview" class="wl-image-preview" alt="Reference image preview" hidden>
        <label class="wl-span">Private collecting notes<textarea name="description" rows="3" maxlength="20000"></textarea></label>
    </div>
    <div class="wl-sellers-heading"><div><h3>Seller offers</h3><p>Record prices manually and update the checked date to retain a new observation.</p></div><button type="button" id="wl-offer-add" class="wl-button">Add seller link</button></div>
    <div id="wl-offers"></div>
    <footer class="wl-dialog-footer"><button class="wl-button" type="button" data-close="wl-dialog">Cancel</button><button class="wl-button wl-primary" type="submit">Save item & offers</button></footer>
</form>
</dialog>

<dialog id="wl-bulk-dialog" class="wl-dialog wl-bulk-dialog" aria-labelledby="wl-bulk-title"><form id="wl-bulk-form"><header class="wl-dialog-heading"><h2 id="wl-bulk-title">Add several wanted items</h2><button class="wl-button" type="button" data-close="wl-bulk-dialog">Close</button></header><div class="wl-error" id="wl-bulk-error" role="alert" hidden></div><label>One item name per line (up to 100)<textarea name="names" rows="9" required maxlength="25600"></textarea></label><div class="wl-form-grid"><label>Type<select name="item_type"><option value="stamp">Stamp</option><option value="cover">Cover</option><option value="other">Other</option></select></label><label>Theme<select name="theme_id"><option value="">Unassigned</option><?php foreach ($themes as $theme): ?><option value="<?= (int)$theme['id'] ?>"><?= $h($theme['name']) ?></option><?php endforeach; ?></select></label></div><footer class="wl-dialog-footer"><button type="button" class="wl-button" data-close="wl-bulk-dialog">Cancel</button><button type="submit" class="wl-button wl-primary">Add items</button></footer></form></dialog>

<script type="application/json" id="wl-data"><?= json_encode(['items'=>$items,'ajaxUrl'=>SITE_URL . '/admin/ajax.php','siteUrl'=>SITE_URL,'csrf'=>ensureCsrfToken()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="<?= $h(SITE_URL) ?>/modules/wishlist/assets/wishlist.js" defer></script>
