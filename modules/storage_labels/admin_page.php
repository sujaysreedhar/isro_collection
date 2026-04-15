<?php
// modules/storage_labels/admin_page.php
$pdo   = $this->pdo;
$items = $pdo->query("
    SELECT i.id, i.reg_number, i.title, s.album, s.page_number, s.box_id
    FROM items i
    LEFT JOIN module_storage s ON i.id = s.item_id
    ORDER BY i.reg_number ASC
")->fetchAll();
?>

<style>
/* ── QR Label Generator — Self-contained layout ── */
#qlg-root {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 130px);
    background: #f1f5f9;
    border-radius: 1.25rem;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 24px -4px rgba(0,0,0,0.08);
    font-family: 'Inter', system-ui, sans-serif;
}

/* ── LEFT PANEL ────────────────────────────────── */
#qlg-sidebar {
    width: 280px;
    min-width: 280px;
    background: #0f172a;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#qlg-sidebar-header {
    padding: 20px 20px 16px;
    border-bottom: 1px solid #1e293b;
}
#qlg-sidebar-header h2 {
    font-size: 14px;
    font-weight: 800;
    color: #f1f5f9;
    letter-spacing: 0.03em;
    margin: 0 0 2px 0;
}
#qlg-sidebar-header p {
    font-size: 11px;
    color: #64748b;
    margin: 0;
}
.qlg-section {
    padding: 16px 20px;
    border-bottom: 1px solid #1e293b;
}
.qlg-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #475569;
    margin-bottom: 12px;
}
.qlg-select {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    color: #e2e8f0;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    outline: none;
    margin-bottom: 10px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2364748b'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
}
.qlg-select:focus { border-color: #3b82f6; }

/* Toggle switch */
.qlg-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 0;
}
.qlg-toggle-label {
    font-size: 12px;
    font-weight: 600;
    color: #cbd5e1;
}
.qlg-toggle {
    position: relative;
    width: 36px;
    height: 20px;
    flex-shrink: 0;
}
.qlg-toggle input { opacity: 0; width: 0; height: 0; }
.qlg-toggle-track {
    position: absolute;
    inset: 0;
    background: #334155;
    border-radius: 20px;
    transition: background 0.2s;
    cursor: pointer;
}
.qlg-toggle input:checked + .qlg-toggle-track { background: #3b82f6; }
.qlg-toggle-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 14px;
    height: 14px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    pointer-events: none;
}
.qlg-toggle input:checked ~ .qlg-toggle-thumb { transform: translateX(16px); }

/* Catalog list */
#qlg-catalog {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#qlg-catalog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #1e293b;
}
#qlg-catalog-search {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    color: #e2e8f0;
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 12px;
    outline: none;
    margin: 0 20px 10px;
    width: calc(100% - 40px);
}
#qlg-catalog-search::placeholder { color: #475569; }
#qlg-catalog-search:focus { border-color: #3b82f6; }
#qlg-catalog-list {
    flex: 1;
    overflow-y: auto;
    padding: 6px 10px 10px;
}
#qlg-catalog-list::-webkit-scrollbar { width: 4px; }
#qlg-catalog-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
.qlg-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
    user-select: none;
}
.qlg-item:hover { background: #1e293b; }
.qlg-item.selected { background: #1d3a6e; }
.qlg-item-check {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 2px solid #334155;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.qlg-item.selected .qlg-item-check {
    background: #3b82f6;
    border-color: #3b82f6;
}
.qlg-item.selected .qlg-item-check::after {
    content: '';
    width: 8px;
    height: 5px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: rotate(-45deg) translate(1px, -1px);
    display: block;
}
.qlg-item-reg {
    font-size: 11px;
    font-weight: 800;
    color: #e2e8f0;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.qlg-item-title {
    font-size: 10px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── RIGHT PANEL ───────────────────────────────── */
#qlg-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#qlg-toolbar {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-shrink: 0;
}
#qlg-toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.qlg-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.qlg-badge-dark  { background: #0f172a; color: #fff; }
.qlg-badge-blue  { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
.qlg-badge-green { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }

#qlg-print-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #0f172a;
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    flex-shrink: 0;
}
#qlg-print-btn:hover { background: #1e293b; }
#qlg-print-btn:active { transform: scale(0.97); }
#qlg-print-btn svg { width: 14px; height: 14px; }

/* Canvas area */
#qlg-canvas-area {
    flex: 1;
    overflow: auto;
    background: #e2e8f0;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 32px;
}
#qlg-canvas-area::-webkit-scrollbar { width: 6px; height: 6px; }
#qlg-canvas-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }

/* The simulated paper */
#qlg-paper {
    background: #fff;
    box-shadow: 0 8px 40px -8px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.04);
    border-radius: 2px;
    flex-shrink: 0;
    /* Width/height set by JS based on paper size */
}

/* Labels on paper */
#qlg-label-grid {
    display: grid;
    gap: 0;
    /* grid-template-columns set by JS */
    width: 100%;
    height: 100%;
    padding: var(--paper-padding, 10mm);
    box-sizing: border-box;
    grid-auto-rows: min-content;
    align-content: start;
}

/* Empty state */
#qlg-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 40px;
    color: #94a3b8;
    text-align: center;
    grid-column: 1 / -1;
}
#qlg-empty svg { width: 48px; height: 48px; opacity: 0.3; margin-bottom: 16px; }
#qlg-empty h3 { font-size: 16px; font-weight: 700; color: #64748b; margin: 0 0 6px; }
#qlg-empty p  { font-size: 13px; margin: 0; }

/* ── THE LABEL CARD (print-accurate) ────────────── */
.qlg-label-card {
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    box-sizing: border-box;
    overflow: hidden;
    padding: 6px 8px;
    /* Height set via JS per paper layout */
}
.qlg-label-qr {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qlg-label-qr canvas,
.qlg-label-qr img {
    display: block;
    image-rendering: crisp-edges;
}
.qlg-label-info { flex: 1; min-width: 0; }
.qlg-label-reg {
    font-size: 13px;
    font-weight: 900;
    color: #000;
    line-height: 1;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.qlg-label-title {
    font-size: 9.5px;
    font-weight: 600;
    color: #555;
    line-height: 1.3;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.qlg-label-loc {
    margin-top: 5px;
    font-size: 8px;
    font-weight: 800;
    text-transform: uppercase;
    color: #2563eb;
    background: #eff6ff;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Hide classes */
.qlg-hide-reg   .qlg-label-reg   { display: none; }
.qlg-hide-title .qlg-label-title { display: none; }
.qlg-hide-loc   .qlg-label-loc   { display: none; }

/* Select-all btn */
#qlg-select-all-btn {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #3b82f6;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    flex-shrink: 0;
}
#qlg-select-all-btn:hover { color: #60a5fa; }

/* ── PRINT STYLES ───────────────────────────────── */
@media print {
    @page { margin: 0; size: auto; }
    body > *:not(#qlg-print-root) { display: none !important; }
    #qlg-print-root {
        position: fixed;
        inset: 0;
        background: #fff;
        display: block !important;
        z-index: 99999;
    }
}
</style>

<div id="qlg-root">

    <!-- ═══ LEFT SIDEBAR ═══════════════════════════════════════ -->
    <div id="qlg-sidebar">

        <!-- Header -->
        <div id="qlg-sidebar-header">
            <h2>🏷 QR Label Generator</h2>
            <p>Select items, configure layout, print.</p>
        </div>

        <!-- Paper Format -->
        <div class="qlg-section">
            <div class="qlg-section-label">Paper Format</div>
            <select id="qlg-page-size" class="qlg-select">
                <option value="A4">Standard A4 (210 × 297 mm)</option>
                <option value="Letter">US Letter (215.9 × 279.4 mm)</option>
                <option value="4x6">Label Sheet (4 × 6 in)</option>
            </select>
            <div class="qlg-section-label" style="margin-top:10px;">Labels Per Row</div>
            <select id="qlg-cols" class="qlg-select">
                <option value="1">1 per row</option>
                <option value="2" selected>2 per row</option>
                <option value="3">3 per row</option>
                <option value="4">4 per row</option>
            </select>
        </div>

        <!-- Content Toggles -->
        <div class="qlg-section">
            <div class="qlg-section-label">Show on Label</div>
            <div class="qlg-toggle-row">
                <span class="qlg-toggle-label">Reg Number</span>
                <label class="qlg-toggle">
                    <input type="checkbox" id="qlg-show-reg" checked>
                    <div class="qlg-toggle-track"></div>
                    <div class="qlg-toggle-thumb"></div>
                </label>
            </div>
            <div class="qlg-toggle-row">
                <span class="qlg-toggle-label">Item Title</span>
                <label class="qlg-toggle">
                    <input type="checkbox" id="qlg-show-title" checked>
                    <div class="qlg-toggle-track"></div>
                    <div class="qlg-toggle-thumb"></div>
                </label>
            </div>
            <div class="qlg-toggle-row">
                <span class="qlg-toggle-label">Location Info</span>
                <label class="qlg-toggle">
                    <input type="checkbox" id="qlg-show-loc" checked>
                    <div class="qlg-toggle-track"></div>
                    <div class="qlg-toggle-thumb"></div>
                </label>
            </div>
        </div>

        <!-- Catalog -->
        <div id="qlg-catalog">
            <div id="qlg-catalog-header">
                <span class="qlg-section-label" style="margin:0">Catalog (<?= count($items) ?>)</span>
                <button id="qlg-select-all-btn">Select All</button>
            </div>
            <input type="text" id="qlg-catalog-search" placeholder="Search items…">
            <div id="qlg-catalog-list">
                <?php foreach ($items as $item):
                    $loc = '';
                    if ($item['album'])       $loc .= $item['album'];
                    if ($item['page_number']) $loc .= ($loc ? ' / Pg ' : '') . $item['page_number'];
                    if ($item['box_id'])      $loc .= ($loc ? ' · ' : '') . 'Box: ' . $item['box_id'];
                    if (!$loc) $loc = 'No location set';
                ?>
                <div class="qlg-item"
                     data-id="<?= $item['id'] ?>"
                     data-reg="<?= htmlspecialchars($item['reg_number']) ?>"
                     data-title="<?= htmlspecialchars($item['title']) ?>"
                     data-loc="<?= htmlspecialchars($loc) ?>"
                     data-url="<?= SITE_URL ?>/item/<?= $item['id'] ?>"
                     data-search="<?= strtolower(htmlspecialchars($item['reg_number'] . ' ' . $item['title'])) ?>">
                    <div class="qlg-item-check"></div>
                    <div style="flex:1;min-width:0">
                        <div class="qlg-item-reg"><?= htmlspecialchars($item['reg_number']) ?></div>
                        <div class="qlg-item-title"><?= htmlspecialchars($item['title']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ RIGHT MAIN PANEL ══════════════════════════════════ -->
    <div id="qlg-main">

        <!-- Toolbar -->
        <div id="qlg-toolbar">
            <div id="qlg-toolbar-left">
                <span class="qlg-badge qlg-badge-dark" id="qlg-paper-badge">A4</span>
                <span class="qlg-badge qlg-badge-blue" id="qlg-count-badge">0 labels selected</span>
                <span class="qlg-badge qlg-badge-green" id="qlg-pages-badge" style="display:none"></span>
            </div>
            <button id="qlg-print-btn" onclick="qlgPrint()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zM9 17V5a2 2 0 012-2h2a2 2 0 012 2v12H9z"/>
                </svg>
                Print Labels
            </button>
        </div>

        <!-- Canvas area -->
        <div id="qlg-canvas-area">
            <div id="qlg-paper">
                <div id="qlg-label-grid">
                    <div id="qlg-empty">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3>No items selected</h3>
                        <p>Click items in the catalog to add them to your label sheet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden print container -->
<div id="qlg-print-root" style="display:none"></div>

<script src="<?= SITE_URL ?>/includes/js/qrcode.min.js"></script>
<script>
(function () {
    'use strict';

    // ── Paper definitions (mm converted to px at 96dpi: 1mm ≈ 3.7795px) ──────
    const MM = 3.7795;
    const PAPERS = {
        A4:     { w: 210*MM, h: 297*MM, label: 'A4',    margin: 10*MM },
        Letter: { w: 215.9*MM, h: 279.4*MM, label: 'Letter', margin: 10*MM },
        '4x6':  { w: 101.6*MM, h: 152.4*MM, label: '4×6"', margin: 6*MM },
    };

    // ── State ─────────────────────────────────────────────────────────────────
    let selected   = new Set();
    let allItems   = [];
    let allSelected = false;
    let qrCache    = {};  // id → QRCode canvas

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const paper      = document.getElementById('qlg-paper');
    const grid       = document.getElementById('qlg-label-grid');
    const emptyState = document.getElementById('qlg-empty');
    const pageSelect = document.getElementById('qlg-page-size');
    const colSelect  = document.getElementById('qlg-cols');
    const showReg    = document.getElementById('qlg-show-reg');
    const showTitle  = document.getElementById('qlg-show-title');
    const showLoc    = document.getElementById('qlg-show-loc');
    const countBadge = document.getElementById('qlg-count-badge');
    const paperBadge = document.getElementById('qlg-paper-badge');
    const pagesBadge = document.getElementById('qlg-pages-badge');
    const searchBox  = document.getElementById('qlg-catalog-search');
    const selAllBtn  = document.getElementById('qlg-select-all-btn');
    const canvasArea = document.getElementById('qlg-canvas-area');

    // ── Collect catalog items ─────────────────────────────────────────────────
    document.querySelectorAll('#qlg-catalog-list .qlg-item').forEach(el => {
        const item = {
            id:    el.dataset.id,
            reg:   el.dataset.reg,
            title: el.dataset.title,
            loc:   el.dataset.loc,
            url:   el.dataset.url,
            search: el.dataset.search,
            el:    el,
        };
        allItems.push(item);
        el.addEventListener('click', () => toggleItem(item));
    });

    // ── Toggle item selection ─────────────────────────────────────────────────
    function toggleItem(item) {
        if (selected.has(item.id)) {
            selected.delete(item.id);
            item.el.classList.remove('selected');
        } else {
            selected.add(item.id);
            item.el.classList.add('selected');
        }
        refresh();
    }

    // ── Search ────────────────────────────────────────────────────────────────
    searchBox.addEventListener('input', () => {
        const q = searchBox.value.toLowerCase().trim();
        allItems.forEach(item => {
            item.el.style.display = (!q || item.search.includes(q)) ? '' : 'none';
        });
    });

    // ── Select All toggle ─────────────────────────────────────────────────────
    selAllBtn.addEventListener('click', () => {
        allSelected = !allSelected;
        const visible = allItems.filter(i => i.el.style.display !== 'none');
        if (allSelected) {
            visible.forEach(i => { selected.add(i.id); i.el.classList.add('selected'); });
            selAllBtn.textContent = 'Deselect All';
        } else {
            visible.forEach(i => { selected.delete(i.id); i.el.classList.remove('selected'); });
            selAllBtn.textContent = 'Select All';
        }
        refresh();
    });

    // ── Config change ─────────────────────────────────────────────────────────
    [pageSelect, colSelect, showReg, showTitle, showLoc].forEach(el =>
        el.addEventListener('change', refresh)
    );

    // ── Main refresh ──────────────────────────────────────────────────────────
    function refresh() {
        const paperKey = pageSelect.value;
        const paperDef = PAPERS[paperKey];
        const cols     = paperKey === '4x6' ? 1 : parseInt(colSelect.value);

        // Disable cols for 4x6
        colSelect.disabled = (paperKey === '4x6');

        // Scale paper to fit canvas area (with padding)
        const areaW = canvasArea.clientWidth  - 64;
        const areaH = canvasArea.clientHeight - 64;
        const scale = Math.min(1, areaW / paperDef.w, areaH / paperDef.h);

        paper.style.width  = (paperDef.w * scale) + 'px';
        paper.style.height = (paperDef.h * scale) + 'px';
        paper.style.overflow = 'hidden';
        paper.style.position = 'relative';

        // The inner grid is at full (unscaled) size and scaled via transform
        grid.style.width  = paperDef.w + 'px';
        grid.style.height = paperDef.h + 'px';
        grid.style.transform = `scale(${scale})`;
        grid.style.transformOrigin = 'top left';
        grid.style.padding = paperDef.margin + 'px';
        grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;

        // Label height: fill rows evenly, try 2 rows min for A4/Letter
        const usableH = paperDef.h - 2 * paperDef.margin;
        const usableW = paperDef.w - 2 * paperDef.margin;
        const labelW  = usableW / cols;
        const rows    = Math.max(2, Math.floor(usableH / (45 * MM)));
        const labelH  = Math.floor(usableH / rows);
        const qrSize  = Math.floor(Math.min(labelH - 12, labelW * 0.35));

        grid.style.gridAutoRows = labelH + 'px';

        // Badges
        paperBadge.textContent = paperDef.label;
        const cnt = selected.size;
        countBadge.textContent = cnt + (cnt === 1 ? ' label' : ' labels') + ' selected';

        if (cnt > 0) {
            const perPage = rows * cols;
            const pages   = Math.ceil(cnt / perPage);
            pagesBadge.textContent = pages + (pages === 1 ? ' page' : ' pages');
            pagesBadge.style.display = '';
        } else {
            pagesBadge.style.display = 'none';
        }

        // Toggle hide classes
        grid.classList.toggle('qlg-hide-reg',   !showReg.checked);
        grid.classList.toggle('qlg-hide-title', !showTitle.checked);
        grid.classList.toggle('qlg-hide-loc',   !showLoc.checked);

        // Render labels
        renderLabels(qrSize, labelH);
    }

    // ── Render label cards ────────────────────────────────────────────────────
    function renderLabels(qrSize, labelH) {
        // Remove cards for deselected items
        grid.querySelectorAll('.qlg-label-card').forEach(card => {
            if (!selected.has(card.dataset.id)) card.remove();
        });

        emptyState.style.display = selected.size === 0 ? '' : 'none';

        // Maintain insertion order from catalog
        const orderedSelected = allItems.filter(i => selected.has(i.id));

        orderedSelected.forEach(item => {
            let card = grid.querySelector(`.qlg-label-card[data-id="${item.id}"]`);

            if (!card) {
                card = document.createElement('div');
                card.className  = 'qlg-label-card';
                card.dataset.id = item.id;

                const qrDiv = document.createElement('div');
                qrDiv.className = 'qlg-label-qr';
                qrDiv.style.width  = qrSize + 'px';
                qrDiv.style.height = qrSize + 'px';

                const info = document.createElement('div');
                info.className = 'qlg-label-info';
                info.innerHTML = `
                    <div class="qlg-label-reg">${escHtml(item.reg)}</div>
                    <div class="qlg-label-title">${escHtml(item.title)}</div>
                    <div class="qlg-label-loc">${escHtml(item.loc)}</div>
                `;

                card.appendChild(qrDiv);
                card.appendChild(info);

                // IMPORTANT: append to DOM *before* calling QRCode so the
                // library can measure the element and render into it correctly.
                grid.insertBefore(card, emptyState);

                if (!qrCache[item.id]) {
                    qrCache[item.id] = new QRCode(qrDiv, {
                        text:         item.url,
                        width:        qrSize,
                        height:       qrSize,
                        colorDark:    '#000000',
                        colorLight:   '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H,
                    });
                } else {
                    // Re-render cached QR into this new qrDiv
                    new QRCode(qrDiv, {
                        text:         item.url,
                        width:        qrSize,
                        height:       qrSize,
                        colorDark:    '#000000',
                        colorLight:   '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H,
                    });
                }
            } else {
                // Card already exists — update QR size only
                const qrDiv2 = card.querySelector('.qlg-label-qr');
                if (qrDiv2) {
                    qrDiv2.style.width  = qrSize + 'px';
                    qrDiv2.style.height = qrSize + 'px';
                    const qrEl = qrDiv2.querySelector('canvas, img');
                    if (qrEl) {
                        qrEl.style.width  = qrSize + 'px';
                        qrEl.style.height = qrSize + 'px';
                    }
                }
            }
        });

        // Re-order cards to match catalog order
        orderedSelected.forEach(item => {
            const card = grid.querySelector(`.qlg-label-card[data-id="${item.id}"]`);
            if (card) grid.insertBefore(card, emptyState);
        });
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Print ─────────────────────────────────────────────────────────────────
    window.qlgPrint = function() {
        if (selected.size === 0) {
            alert('Please select at least one item to print.');
            return;
        }

        const paperKey = pageSelect.value;
        const paperDef = PAPERS[paperKey];
        const cols     = paperKey === '4x6' ? 1 : parseInt(colSelect.value);

        const usableH  = paperDef.h - 2 * paperDef.margin;
        const usableW  = paperDef.w - 2 * paperDef.margin;
        const labelW   = usableW / cols;
        const rows     = Math.max(2, Math.floor(usableH / (45 * MM)));
        const labelH   = Math.floor(usableH / rows);
        const qrSize   = Math.floor(Math.min(labelH - 12, labelW * 0.35));

        const orderedSelected = allItems.filter(i => selected.has(i.id));

        const perPage = rows * cols;
        const pagesCount = Math.ceil(orderedSelected.length / perPage);
        let pagesHtml = '';

        for (let i = 0; i < pagesCount; i++) {
            const pageItems = orderedSelected.slice(i * perPage, (i + 1) * perPage);
            const labelsHtml = pageItems.map(item => {
                const card = grid.querySelector(`.qlg-label-card[data-id="${item.id}"]`);
                const qrEl = card ? card.querySelector('.qlg-label-qr canvas, .qlg-label-qr img') : null;
                
                let qrImg  = '';
                if (qrEl && qrEl.tagName === 'CANVAS') {
                    qrImg = `<img src="${qrEl.toDataURL()}" style="width:${qrSize}px;height:${qrSize}px;display:block;image-rendering:crisp-edges;">`;
                } else if (qrEl && qrEl.tagName === 'IMG') {
                    qrImg = `<img src="${qrEl.src}" style="width:${qrSize}px;height:${qrSize}px;display:block;image-rendering:crisp-edges;">`;
                }
                const showRegStyle   = showReg.checked   ? '' : 'display:none;';
                const showTitleStyle = showTitle.checked ? '' : 'display:none;';
                const showLocStyle   = showLoc.checked   ? '' : 'display:none;';

                return `
                <div style="border:1px solid #eee;display:flex;align-items:center;gap:8px;background:#fff;
                            overflow:hidden;padding:5px 8px;box-sizing:border-box;
                            height:${labelH}px;width:${labelW}px;break-inside:avoid;page-break-inside:avoid;">
                    <div style="flex-shrink:0;width:${qrSize}px;height:${qrSize}px;">${qrImg}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="${showRegStyle}font-size:13px;font-weight:900;color:#000;letter-spacing:-0.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.reg)}</div>
                        <div style="${showTitleStyle}font-size:9.5px;font-weight:600;color:#555;line-height:1.3;margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${escHtml(item.title)}</div>
                        <div style="${showLocStyle}margin-top:4px;font-size:8px;font-weight:800;text-transform:uppercase;color:#2563eb;background:#eff6ff;padding:2px 6px;border-radius:4px;display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.loc)}</div>
                    </div>
                </div>`;
            }).join('');
            pagesHtml += `<div class="page">${labelsHtml}</div>`;
        }

        const paperW = Math.round(paperDef.w) + 'px';
        const paperH = Math.round(paperDef.h) + 'px';
        const pageMm_w = paperKey === 'A4' ? '210mm' : (paperKey === 'Letter' ? '215.9mm' : '101.6mm');
        const pageMm_h = paperKey === 'A4' ? '297mm' : (paperKey === 'Letter' ? '279.4mm' : '152.4mm');

        const printWin = window.open('', '_blank', 'width=900,height=700');
        printWin.document.write(`<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Print Labels</title>
<style>
@page { margin:0; size:${pageMm_w} ${pageMm_h}; }
* { box-sizing: border-box; }
body { margin:0; padding:0; background:#fff; font-family: Arial, sans-serif; }
.page {
    width:${paperW};
    height:${paperH};
    padding:${paperDef.margin}px;
    display:grid;
    grid-template-columns:repeat(${cols},1fr);
    grid-auto-rows:${labelH}px;
    align-content:start;
    page-break-after:always;
    overflow:hidden;
}
</style></head><body>
${pagesHtml}
<script>
window.onload = function() {
    setTimeout(function() {
        window.print();
    }, 500);
};
<\/script>
</body></html>`);
        printWin.document.close();
    };

    function escHtmlStr(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Initial render + resize observer ─────────────────────────────────────
    refresh();

    const ro = new ResizeObserver(() => refresh());
    ro.observe(canvasArea);

})();
</script>