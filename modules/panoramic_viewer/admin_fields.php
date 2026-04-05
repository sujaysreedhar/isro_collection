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
        <div class="p-6 border-t border-gray-200">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">360° Panoramic Views</h3>
            <p class="text-xs text-gray-500 mt-1">Add multiple equirectangular images to create an interactive viewer.</p>
        </div>
        <button type="button" onclick="addPanoramicField()" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Another View
        </button>
    </div>

    <!-- Existing Panoramics section -->
    <?php if (!empty($panoramics)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <?php foreach ($panoramics as $pano): ?>
            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 bg-gray-50/50 group">
                <div class="w-24 h-16 rounded-lg overflow-hidden flex-shrink-0 bg-black border border-gray-200">
                    <img src="<?= SITE_URL ?>/uploads/panoramics/<?= htmlspecialchars($pano['file_path']) ?>" class="w-full h-full object-cover opacity-80 hover:opacity-100 transition-opacity" alt="Preview">
                </div>
                <div class="flex-1 min-w-0">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Edit Caption</label>
                    <input type="text" name="existing_panoramic_captions[<?= $pano['id'] ?>]" value="<?= htmlspecialchars($pano['caption']) ?>" 
                           class="block w-full px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    
                    <label class="inline-flex items-center mt-3 cursor-pointer group/del">
                        <input type="checkbox" name="delete_panoramic[]" value="<?= $pano['id'] ?>" class="hidden peer">
                        <span class="w-4 h-4 border-2 border-gray-300 rounded flex items-center justify-center mr-2 peer-checked:bg-red-500 peer-checked:border-red-500 transition-all">
                            <svg class="w-2.5 h-2.5 text-white scale-0 peer-checked:scale-100 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                        </span>
                        <span class="text-xs font-semibold text-gray-500 group-hover/del:text-red-600 transition-colors">Delete this view</span>
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div id="panoramic-upload-container" class="space-y-4">
        <!-- New upload fields will be injected here -->
    </div>
</div>

<script>
function addPanoramicField() {
    const container = document.getElementById('panoramic-upload-container');
    const div = document.createElement('div');
    div.className = 'p-4 rounded-xl border border-dashed border-gray-300 bg-white shadow-sm flex flex-col md:flex-row gap-4 relative animate-fade-in';
    div.innerHTML = `
        <div class="flex-1">
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Select Image</label>
            <input type="file" name="panoramic_files[]" accept="image/*" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
        </div>
        <div class="flex-1">
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">View Label / Caption</label>
            <input type="text" name="panoramic_captions[]" placeholder="e.g. South View 360" class="block w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    `;
    container.appendChild(div);
}
</script>
        </div>
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
