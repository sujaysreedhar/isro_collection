<?php
// modules/storage_labels/admin_page.php
$pdo = $this->pdo;
$items = $pdo->query("SELECT i.id, i.reg_number, i.title, s.album, s.page_number FROM items i LEFT JOIN module_storage s ON i.id = s.item_id ORDER BY i.reg_number ASC")->fetchAll();
?>

<div class="p-4 lg:p-8 min-h-screen bg-gray-50/30">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 px-2">
        <div>
            <h1 class="text-4xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                QR Label Generator
                <span class="bg-blue-600 text-white text-[10px] px-2 py-1 rounded font-bold uppercase tracking-widest align-middle shadow-sm">Pro</span>
            </h1>
            <p class="text-gray-500 mt-2 font-medium">Configure dimensions, toggle metadata, and generate physical labels.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="group bg-gray-900 text-white px-8 py-4 rounded-2xl font-bold hover:bg-black transition-all shadow-xl hover:shadow-2xl active:scale-95 flex items-center gap-3">
                <svg class="w-5 h-5 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 00-2 2h2m2 4h10a2 2 0 002-2v-4H7v4a2 2 0 002 2zM9 17V5a2 2 0 012-2h2a2 2 0 012 2v12H9z"/></svg>
                Print Selected Labels
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- SIDEBAR: Controls & Catalog -->
        <div class="lg:col-span-3 space-y-8">
            
            <!-- Output Settings -->
            <div class="bg-white rounded-[2rem] border border-gray-200 shadow-sm overflow-hidden p-6">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6">Output Parameters</h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Paper Format</label>
                        <select id="config-page-size" class="w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold focus:bg-white focus:border-blue-500 transition-all outline-none">
                            <option value="A4">Standard A4</option>
                            <option value="Letter">US Letter</option>
                            <option value="4x6">Label Sheet (4x6")</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Labels Per Row</label>
                        <select id="config-cols" class="w-full px-4 py-3 bg-gray-50 border border-transparent rounded-xl text-sm font-semibold focus:bg-white focus:border-blue-500 transition-all outline-none">
                            <option value="1">1 Column</option>
                            <option value="2" selected>2 Columns</option>
                            <option value="3">3 Columns</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Content Toggles -->
            <div class="bg-white rounded-[2rem] border border-gray-200 shadow-sm p-6">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6">Label Metadata</h3>
                <div class="space-y-4">
                    <label class="flex items-center gap-4 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" id="toggle-reg" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-blue-600 transition-colors"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black transition">Reg Number</span>
                    </label>
                    <label class="flex items-center gap-4 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" id="toggle-title" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-blue-600 transition-colors"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black transition">Item Title</span>
                    </label>
                    <label class="flex items-center gap-4 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" id="toggle-loc" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-blue-600 transition-colors"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black transition">Location Info</span>
                    </label>
                </div>
            </div>

            <!-- Item Selection -->
            <div class="bg-white rounded-[2rem] border border-gray-200 shadow-sm overflow-hidden flex flex-col h-[500px]">
                <div class="p-6 bg-gray-50/50 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest">Catalog</h3>
                    <button id="select-all-btn" class="text-[10px] font-black uppercase tracking-widest text-blue-600 hover:text-blue-800 transition">Select All</button>
                    <input type="checkbox" id="select-all-input" class="hidden">
                </div>
                <div class="overflow-y-auto flex-1 p-4 space-y-1" id="item-selector">
                    <?php foreach ($items as $item): ?>
                        <label class="flex items-start p-4 rounded-2xl hover:bg-gray-50 transition cursor-pointer group">
                            <input type="checkbox" class="item-checkbox w-5 h-5 mt-0.5 rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500 transition-all" 
                                   data-id="<?= $item['id'] ?>" 
                                   data-reg="<?= htmlspecialchars($item['reg_number']) ?>" 
                                   data-title="<?= htmlspecialchars($item['title']) ?>"
                                   data-loc="<?= htmlspecialchars(($item['album'] ? $item['album'] : 'N/A') . ($item['page_number'] ? ' / Pg ' . $item['page_number'] : '')) ?>"
                                   data-url="<?= SITE_URL ?>/item/<?= $item['id'] ?>">
                            <div class="ml-4 flex-1">
                                <span class="block text-sm font-black text-gray-900 group-hover:text-blue-600 transition tracking-tight"><?= htmlspecialchars($item['reg_number']) ?></span>
                                <span class="block text-xs text-gray-500 leading-relaxed mt-0.5"><?= htmlspecialchars($item['title']) ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- MAIN CANVAS: Preview -->
        <div class="lg:col-span-9">
            <div class="relative bg-white p-6 md:p-12 rounded-[3rem] border border-gray-200 shadow-sm overflow-hidden">
                <!-- Watermark / Context -->
                <div class="absolute top-10 right-10 text-right opacity-10 pointer-events-none">
                    <span class="block text-6xl font-black text-gray-900 uppercase">Preview</span>
                    <span class="block text-xl font-bold text-gray-700 tracking-[1em] mr-[-1em]">Labels</span>
                </div>

                <div class="relative z-10 flex flex-col items-center">
                    <!-- Preview Stats -->
                    <div class="flex items-center gap-6 mb-10 w-full max-w-[210mm]">
                        <div class="bg-blue-600 h-1 flex-1 rounded-full opacity-20"></div>
                        <div class="flex items-center gap-3">
                           <span class="px-4 py-1.5 bg-gray-900 text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg" id="preview-page-label">A4 CANVAS</span>
                           <span class="px-4 py-1.5 bg-blue-50 text-blue-700 rounded-full text-[10px] font-black uppercase tracking-widest border border-blue-100" id="preview-count-label">0 Labels</span>
                        </div>
                        <div class="bg-blue-600 h-1 flex-1 rounded-full opacity-20"></div>
                    </div>

                    <!-- Virtual Sheet -->
                    <div class="bg-gray-200 p-1 md:p-8 rounded-[2rem] shadow-inner w-full flex justify-center overflow-x-auto">
                        <div id="print-area-wrapper" class="bg-white shadow-2xl transition-all duration-700 ease-in-out origin-top border border-white" style="width: 210mm; min-height: 297mm;">
                            <div id="print-area" class="grid grid-cols-2 gap-4 p-[15mm]">
                                <!-- Labels injected via JS -->
                                <div id="empty-state" class="col-span-full flex flex-col items-center justify-center py-48 text-gray-300">
                                    <div class="w-32 h-32 mb-8 bg-gray-50 rounded-[2.5rem] flex items-center justify-center shadow-inner">
                                        <svg class="w-16 h-16 opacity-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                    <p class="font-black text-xl text-gray-400 tracking-tight">Label Canvas Empty</p>
                                    <p class="text-sm font-medium mt-2">Select items from your catalog to begin layout.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
:root {
    --grid-cols: 2;
}

@media print {
    @page { margin: 0; }
    body * { visibility: hidden !important; }
    #print-area-wrapper, 
    #print-area-wrapper * { 
        visibility: visible !important; 
        -webkit-print-color-adjust: exact !important; 
        print-color-adjust: exact !important;
    }
    #print-area-wrapper { 
        position: absolute !important; 
        left: 0 !important; 
        top: 0 !important; 
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .label-card { page-break-inside: avoid; break-inside: avoid; }
}

#print-area { grid-template-columns: repeat(var(--grid-cols), 1fr); }

.label-card {
    border: 1px solid #eee;
    padding: 12px;
    display: flex;
    gap: 15px;
    align-items: center;
    background: white;
    height: 42mm;
    overflow: hidden;
    position: relative;
}

.label-qr { flex-shrink: 0; width: 30mm; height: 30mm; border: 1px solid #f9f9f9; padding: 2px; }
.label-qr img { width: 100% !important; height: 100% !important; image-rendering: crisp-edges; }
.label-info { flex: 1; min-width: 0; }
.label-reg { font-size: 15px; font-weight: 900; color: #000; line-height: 1; letter-spacing: -0.01em; }
.label-title { font-size: 11px; font-weight: 600; color: #555; line-height: 1.3; margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.label-loc { margin-top: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; color: #2563eb; background: #eff6ff; padding: 3px 8px; border-radius: 6px; display: inline-block; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.hide-reg .label-reg { display: none; }
.hide-title .label-title { display: none; }
.hide-loc .label-loc { display: none; }

/* Scrollbar styling for catalog */
#item-selector::-webkit-scrollbar { width: 5px; }
#item-selector::-webkit-scrollbar-track { background: transparent; }
#item-selector::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAllBtn = document.getElementById('select-all-btn');
    const selectAllInput = document.getElementById('select-all-input');
    const printArea = document.getElementById('print-area');
    const wrapper = document.getElementById('print-area-wrapper');
    const emptyState = document.getElementById('empty-state');
    const countLabel = document.getElementById('preview-count-label');
    
    const configPage = document.getElementById('config-page-size');
    const configCols = document.getElementById('config-cols');
    const tReg = document.getElementById('toggle-reg');
    const tTitle = document.getElementById('toggle-title');
    const tLoc = document.getElementById('toggle-loc');
    const pageLabel = document.getElementById('preview-page-label');

    function updateConfig() {
        printArea.classList.toggle('hide-reg', !tReg.checked);
        printArea.classList.toggle('hide-title', !tTitle.checked);
        printArea.classList.toggle('hide-loc', !tLoc.checked);
        document.documentElement.style.setProperty('--grid-cols', configCols.value);
        
        const sizes = { 'A4': '210mm', 'Letter': '215.9mm', '4x6': '101.6mm' };
        const heights = { 'A4': '297mm', 'Letter': '279.4mm', '4x6': '152.4mm' };
        
        wrapper.style.width = sizes[configPage.value];
        wrapper.style.minHeight = heights[configPage.value];
        pageLabel.innerText = configPage.value + ' CANVAS';
        
        if (configPage.value === '4x6') {
            document.documentElement.style.setProperty('--grid-cols', '1');
            configCols.disabled = true;
            configCols.value = '1';
        } else {
            configCols.disabled = false;
        }
    }

    function generateLabels() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked);
        countLabel.innerText = selected.length + ' Labels';
        
        if (selected.length === 0) {
            emptyState.style.display = 'flex';
            Array.from(printArea.children).forEach(child => { if (child.id !== 'empty-state') child.remove(); });
            return;
        }

        emptyState.style.display = 'none';
        const selectedIds = selected.map(cb => cb.dataset.id);
        Array.from(printArea.children).forEach(child => {
            if (child.id !== 'empty-state' && !selectedIds.includes(child.dataset.id)) child.remove();
        });

        selected.forEach((cb, idx) => {
            if (!printArea.querySelector(`.label-card[data-id="${cb.dataset.id}"]`)) {
                const card = document.createElement('div');
                card.className = 'label-card';
                card.dataset.id = cb.dataset.id;
                card.innerHTML = `
                    <div class="label-qr" id="qr-${cb.dataset.id}"></div>
                    <div class="label-info">
                        <div class="label-reg">${cb.dataset.reg}</div>
                        <div class="label-title">${cb.dataset.title}</div>
                        <div class="label-loc">${cb.dataset.loc}</div>
                    </div>
                `;
                printArea.appendChild(card);
                new QRCode(document.getElementById(`qr-${cb.dataset.id}`), {
                    text: cb.dataset.url,
                    width: 120,
                    height: 120,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
    }

    [configPage, configCols, tReg, tTitle, tLoc].forEach(el => el.addEventListener('change', updateConfig));
    checkboxes.forEach(cb => cb.addEventListener('change', generateLabels));
    
    selectAllBtn.addEventListener('click', () => {
        const newState = !selectAllInput.checked;
        selectAllInput.checked = newState;
        checkboxes.forEach(cb => cb.checked = newState);
        generateLabels();
        selectAllBtn.innerText = newState ? 'Deselect All' : 'Select All';
    });

    updateConfig();
});
</script>
