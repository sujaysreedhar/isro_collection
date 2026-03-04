<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

echo renderAdminHeader("Manage Items");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Manage Items</h1>
        <p class="text-sm text-gray-500 mt-1">Server-side search · Toggle visibility · Bulk delete</p>
    </div>
    <div class="flex items-center gap-3">
        <button id="bulk-delete-btn" onclick="openBulkDeleteModal()" 
                class="hidden items-center px-4 py-2 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Delete Selected (<span id="selected-count">0</span>)
        </button>
        <a href="<?= SITE_URL ?>/admin/edit_item.php" class="bg-gray-900 text-white font-medium px-4 py-2 rounded-md hover:bg-gray-800 transition inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add New Item
        </a>
    </div>
</div>

<!-- Items DataTable  -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table id="items-table" class="w-full text-left text-sm text-gray-600" style="width:100%">
            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold text-xs border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3"><input type="checkbox" id="select-all" class="h-4 w-4 text-gray-900 border-gray-300 rounded focus:ring-gray-900" title="Select all"></th>
                    <th class="px-4 py-3">Reg #</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Visibility</th>
                    <th class="px-4 py-3">Media</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Bulk Delete Confirm Modal -->
<div id="bulk-delete-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeBulkDeleteModal()"></div>
        
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-start mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Bulk Delete</h3>
                    <p class="text-sm text-gray-500 mt-1">You are about to permanently delete <strong id="modal-count">0</strong> items. This will also remove all associated media records. This cannot be undone.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeBulkDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button onclick="executeBulkDelete()" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">Yes, Delete All</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables + AJAX JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<style>
/* Override DataTables styles to match the admin theme */
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #111827 !important;
    color: #fff !important;
    border-radius: 4px;
    border: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #374151 !important;
    color: #fff !important;
    border-radius: 4px;
    border: none !important;
}
#items-table_wrapper .dataTables_filter input {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 6px 12px;
    outline: none;
}
#items-table_wrapper .dataTables_length select {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 4px 8px;
}
#items-table_wrapper .dataTables_info,
#items-table_wrapper .dataTables_length,
#items-table_wrapper .dataTables_filter {
    padding: 12px 16px;
    font-size: 13px;
    color: #6b7280;
}
</style>

<script>
const AJAX_URL = '<?= SITE_URL ?>/admin/ajax.php';
let selectedIds = new Set();
let dataTable;

$(document).ready(function() {
    dataTable = $('#items-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: AJAX_URL,
            data: { action: 'datatable_items' }
        },
        columns: [
            { orderable: false, searchable: false, data: null, defaultContent: '' },
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6, orderable: false, searchable: false, className: 'text-right' }
        ],
        columnDefs: [{
            targets: 0,
            render: function(data, type, row) {
                // Extract the item id from the Edit button URL (which is in row[6], the 7th returned field)
                const match = row[6].match(/edit_item\.php\?id=(\d+)/);
                const id = match ? match[1] : '';
                return `<input type="checkbox" class="row-checkbox h-4 w-4 text-gray-900 border-gray-300 rounded" data-id="${id}">`;
            }
        }],
        pageLength: 20,
        language: { processing: '<div class="text-gray-500 text-sm py-2">Loading...</div>' }
    });
    
    // Select / Deselect All
    $('#select-all').on('change', function() {
        const checked = $(this).is(':checked');
        $('.row-checkbox').prop('checked', checked).each(function() {
            const id = parseInt($(this).data('id'));
            if (checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        updateBulkBar();
    });
    
    // Row checkbox delegation
    $('#items-table').on('change', '.row-checkbox', function() {
        const id = parseInt($(this).data('id'));
        if ($(this).is(':checked')) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
        updateBulkBar();
    });
    
    // Re-check boxes on pagination
    dataTable.on('draw', function() {
        selectedIds.forEach(id => {
            $(`.row-checkbox[data-id="${id}"]`).prop('checked', true);
        });
    });
});

function updateBulkBar() {
    const count = selectedIds.size;
    $('#selected-count').text(count);
    if (count > 0) {
        $('#bulk-delete-btn').removeClass('hidden').addClass('inline-flex');
    } else {
        $('#bulk-delete-btn').addClass('hidden').removeClass('inline-flex');
    }
}

// Toggle visibility via AJAX - no page reload
function toggleVisibility(id, btn) {
    $.post(AJAX_URL, { action: 'toggle_visibility', id: id }, function(res) {
        if (res.success) {
            dataTable.ajax.reload(null, false); // Reload preserving page position
        }
    }, 'json');
}

function confirmDelete(id) {
    selectedIds.clear();
    selectedIds.add(id);
    updateBulkBar();
    openBulkDeleteModal();
}

function openBulkDeleteModal() {
    if (selectedIds.size === 0) return;
    $('#modal-count').text(selectedIds.size);
    $('#bulk-delete-modal').removeClass('hidden');
}

function closeBulkDeleteModal() {
    $('#bulk-delete-modal').addClass('hidden');
}

function executeBulkDelete() {
    const ids = Array.from(selectedIds);
    $.post(AJAX_URL, { action: 'bulk_delete', ids: ids }, function(res) {
        closeBulkDeleteModal();
        if (res.success) {
            selectedIds.clear();
            updateBulkBar();
            dataTable.ajax.reload(null, false);
        } else {
            alert('Delete failed: ' + (res.message || 'Unknown error'));
        }
    }, 'json');
}
</script>

<?= renderAdminFooter(); ?>
