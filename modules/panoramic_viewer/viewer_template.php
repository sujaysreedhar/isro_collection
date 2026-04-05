<?php
// modules/panoramic_viewer/viewer_template.php
/** @var array $panoramics */
if (empty($panoramics)) return;
$firstPano = $panoramics[0];
?>

<script>
let viewer = null;
let currentScene = null;

function loadScene(url) {
    const viewerElement = document.getElementById('panorama-viewer');
    if (!viewerElement) return;

    if (!viewer) {
        viewer = new Marzipano.Viewer(viewerElement, { controls: { mouseViewMode: 'drag' } });
    }

    var source = Marzipano.ImageUrlSource.fromString(url);
    var geometry = new Marzipano.EquirectGeometry([{ width: 8192 }]);
    var lims = Marzipano.RectilinearView.limit.traditional(2048, 120*Math.PI/180);
    var view = new Marzipano.RectilinearView({ yaw: 0, pitch: 0, fov: Math.PI/2 }, lims);

    currentScene = viewer.createScene({
        source: source,
        geometry: geometry,
        view: view,
        pinFirstLevel: true
    });

    currentScene.switchTo({ transitionDuration: 500 });
    
    var autorotate = Marzipano.autorotate({ yawSpeed: 0.05, targetPitch: 0, targetFov: Math.PI/2 });
    viewer.startMovement(autorotate);
    viewer.setIdleMovement(5000, autorotate);
}

window.switchPanorama = function(btn, url, caption) {
    const mainImg = document.getElementById('main-viewer');
    const panoContainer = document.getElementById('panorama-viewer');
    const captionEl = document.getElementById('image-caption');
    const placeholder = document.querySelector('.main-image-container .flex-col');
    
    if (mainImg) mainImg.style.display = 'none';
    if (placeholder) placeholder.style.display = 'none';
    if (panoContainer) panoContainer.classList.remove('hidden');

    // Update active states
    document.querySelectorAll('.gallery-thumbnail').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pano-thumbnail img').forEach(img => img.classList.remove('border-blue-500'));
    if (btn) {
        const img = btn.querySelector('img');
        if (img) img.classList.add('border-blue-500');
    }

    if (captionEl) captionEl.textContent = caption;

    loadScene(url);
};

// Start automatically if the primary media is empty (indicated by missing image source)
document.addEventListener("DOMContentLoaded", () => {
    const mainImg = document.getElementById('main-viewer');
    if (mainImg && !mainImg.hasAttribute('src')) {
        const firstBtn = document.querySelector('.pano-thumbnail');
        if (firstBtn) firstBtn.click();
    }
});
</script>
