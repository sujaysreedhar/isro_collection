<?php
// modules/panoramic_viewer/viewer_template.php
/** @var array $panoramics */
$firstPano = $panoramics[0];
?>

<div class="panoramic-viewer-section mb-12">
    <div class="flex items-center gap-3 mb-6">
        <div class="bg-indigo-600 text-white p-2 rounded-xl shadow-lg ring-4 ring-indigo-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 leading-none">360° Interactive Views</h2>
            <p class="text-sm text-gray-500 mt-1">Immerse yourself in high-resolution panoramas of this item.</p>
        </div>
    </div>

    <?php if (count($panoramics) > 1): ?>
    <div class="pano-nav">
        <?php foreach ($panoramics as $i => $pano): ?>
        <button class="pano-nav-btn <?= $i === 0 ? 'active' : '' ?>" 
                data-pano-url="<?= SITE_URL ?>/uploads/panoramics/<?= htmlspecialchars($pano['file_path']) ?>"
                data-pano-caption="<?= htmlspecialchars($pano['caption'] ?: 'View ' . ($i+1)) ?>"
                onclick="switchPanorama(this, <?= $i ?>)">
            <?= htmlspecialchars($pano['caption'] ?: 'View ' . ($i+1)) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div id="panorama-viewer" class="pano-container shadow-2xl ring-1 ring-gray-200"></div>
    
    <div id="pano-caption-box" class="mt-3 text-sm font-medium text-gray-600 text-center italic">
        <?= htmlspecialchars($firstPano['caption'] ?: '360° Exploration View') ?>
    </div>
</div>

<script>
let currentViewer = pannellum.viewer('panorama-viewer', {
    "type": "equirectangular",
    "panorama": "<?= SITE_URL ?>/uploads/panoramics/<?= htmlspecialchars($firstPano['file_path']) ?>",
    "autoLoad": true,
    "compass": true,
    "title": "<?= htmlspecialchars($firstPano['caption'] ?: '360° View') ?>",
    "author": "<?= SITE_TITLE ?>",
    "showFullscreenCtrl": true,
    "showControls": true,
    "orientationOnDeviceHelp": true
});

function switchPanorama(btn, index) {
    const url = btn.getAttribute('data-pano-url');
    const caption = btn.getAttribute('data-pano-caption');

    // Update buttons
    document.querySelectorAll('.pano-nav-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Update Caption
    document.getElementById('pano-caption-box').textContent = caption;

    // Load new panorama
    currentViewer.loadScene(url, undefined, undefined, {
        "title": caption
    });
    
    // If Pannellum loadScene isn't ideal for simple equirectangular switching, 
    // we can re-init or use the API properly. 
    // For single equitable images without a complex 'tour' config, 
    // sometimes a full destroy/init or .setPanorama is better:
    
    currentViewer.destroy();
    currentViewer = pannellum.viewer('panorama-viewer', {
        "type": "equirectangular",
        "panorama": url,
        "autoLoad": true,
        "compass": true,
        "title": caption,
        "author": "<?= SITE_TITLE ?>",
        "showFullscreenCtrl": true,
        "showControls": true
    });
}
</script>
