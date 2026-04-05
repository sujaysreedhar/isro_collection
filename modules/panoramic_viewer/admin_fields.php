<?php
// modules/panoramic_viewer/admin_fields.php
/** @var array $panoramics */
?>

<div class="mt-8 border-t border-gray-200 pt-8">
    <div class="flex items-center gap-2 mb-4">
        <div class="bg-indigo-100 text-indigo-600 p-2 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.5a2.5 2.5 0 012.5 2.5V14M10.5 20.5L12 19m0 0l1.5 1.5M12 19v-4m0 0L10.5 13.5M12 15l1.5-1.5"></path></svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900">360° Panoramic Views</h3>
    </div>

    <div class="space-y-4">
        <!-- New Uploads -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6">
            <label class="block text-sm font-semibold text-indigo-900 mb-2">Add New Panoramics</label>
            <p class="text-xs text-indigo-600 mb-4">Upload equitable high-res 360° images (equirectangular JPG/PNG). <span class="font-bold">Multiple files supported.</span></p>
            
            <div id="pano-upload-container" class="space-y-3">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="file" name="panoramic_files[]" accept=".jpg,.jpeg,.png,.webp" class="flex-1 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 transition">
                    <input type="text" name="panoramic_captions[]" placeholder="Enter caption (e.g. South View)" class="flex-1 input">
                </div>
            </div>
            
            <button type="button" onclick="addPanoField()" class="mt-4 text-xs font-bold text-indigo-600 bg-white border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-white hover:shadow-sm transition">
                + Add Another File
            </button>
        </div>

        <!-- Existing Panoramics -->
        <?php if (!empty($panoramics)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($panoramics as $pano): ?>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group relative">
                        <div class="aspect-video bg-gray-900 flex items-center justify-center">
                            <img src="<?= SITE_URL ?>/uploads/panoramics/<?= htmlspecialchars($pano['file_path']) ?>" class="w-full h-full object-cover opacity-60">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="bg-black/50 text-white text-[10px] font-bold px-2 py-1 rounded backdrop-blur-sm">360° PREVIEW</span>
                            </div>
                        </div>
                        <div class="p-4 flex flex-col justify-between">
                            <div class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($pano['caption'] ?: 'Unnamed View') ?></div>
                            <div class="text-[10px] text-gray-400 font-mono truncate mt-1"><?= htmlspecialchars($pano['file_path']) ?></div>
                            
                            <label class="mt-3 flex items-center gap-2 text-xs text-red-600 font-bold cursor-pointer">
                                <input type="checkbox" name="delete_panoramic[]" value="<?= (int)$pano['id'] ?>" class="rounded border-red-300 text-red-600 focus:ring-red-500">
                                Delete View
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function addPanoField() {
    const container = document.getElementById('pano-upload-container');
    const div = document.createElement('div');
    div.className = 'flex flex-col sm:flex-row gap-3 pt-2 border-t border-indigo-100 border-dashed';
    div.innerHTML = `
        <input type="file" name="panoramic_files[]" accept=".jpg,.jpeg,.png,.webp" class="flex-1 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 transition">
        <input type="text" name="panoramic_captions[]" placeholder="Enter caption" class="flex-1 input">
    `;
    container.appendChild(div);
}
</script>
