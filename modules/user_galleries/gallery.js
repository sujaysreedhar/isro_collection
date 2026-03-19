let currentItemId = null;

function openGalleryModal(itemId) {
    currentItemId = itemId;
    const modal = document.getElementById('ug-modal');
    modal.classList.add('active');
    loadGalleries();
}

function closeGalleryModal() {
    const modal = document.getElementById('ug-modal');
    modal.classList.remove('active');
    currentItemId = null;
}

function loadGalleries() {
    const body = document.getElementById('ug-modal-body');
    body.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Loading your galleries...</p>';
    
    fetch(`${SITE_URL}/modules/user_galleries/api.php?action=list&item_id=${currentItemId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<p class="text-sm text-red-500">${data.error}</p>`;
                return;
            }
            renderGalleries(data.galleries);
        })
        .catch(err => {
            body.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Failed to load galleries.</p>';
        });
}

function renderGalleries(galleries) {
    const body = document.getElementById('ug-modal-body');
    let html = '<div class="space-y-2 max-h-60 overflow-y-auto pr-2">';
    
    if (galleries.length === 0) {
        html += '<p class="text-sm text-gray-500 text-center py-2">You don\'t have any galleries yet.</p>';
    } else {
        galleries.forEach(g => {
            html += `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <span class="font-medium text-gray-900 block truncate" style="max-width: 70%;" title="${escapeHtml(g.title)}">${escapeHtml(g.title)}</span>
                ${g.has_item 
                    ? `<button onclick="removeFromGallery(${g.id})" class="text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded hover:bg-red-100 transition-colors">Remove</button>`
                    : `<button onclick="addToGallery(${g.id})" class="text-xs font-semibold text-gray-900 bg-gray-200 px-3 py-1 rounded hover:bg-gray-300 transition-colors">Add</button>`
                }
            </div>`;
        });
    }
    html += '</div>';
    
    // Create new gallery form
    html += `
    <div class="mt-6 pt-4 border-t border-gray-200">
        <h4 class="text-sm font-semibold text-gray-900 mb-2">Create New Gallery</h4>
        <div class="flex gap-2">
            <input type="text" id="ug-new-title" placeholder="Gallery Name" class="flex-1 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-gray-900 focus:border-gray-900 outline-none" onkeydown="if(event.key==='Enter') createGallery()">
            <button onclick="createGallery()" class="px-4 py-1.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">Create</button>
        </div>
    </div>`;
    
    body.innerHTML = html;
}

function addToGallery(galleryId) {
    const formData = new FormData();
    formData.append('action', 'add_item');
    formData.append('gallery_id', galleryId);
    formData.append('item_id', currentItemId);
    
    fetch(`${SITE_URL}/modules/user_galleries/api.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            loadGalleries(); // reload to show 'Remove' button
        } else {
            alert(data.error);
        }
    });
}

function removeFromGallery(galleryId) {
    const formData = new FormData();
    formData.append('action', 'remove_item');
    formData.append('gallery_id', galleryId);
    formData.append('item_id', currentItemId);
    
    fetch(`${SITE_URL}/modules/user_galleries/api.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            loadGalleries();
        } else {
            alert(data.error);
        }
    });
}

function createGallery() {
    const titleInput = document.getElementById('ug-new-title');
    const title = titleInput.value.trim();
    if (!title) return;
    
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('title', title);
    
    fetch(`${SITE_URL}/modules/user_galleries/api.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Automatically add the current item to the newly created gallery
            addToGallery(data.gallery_id);
        } else {
            alert(data.error);
        }
    });
}

function escapeHtml(unsafe) {
    return (unsafe||"").replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
