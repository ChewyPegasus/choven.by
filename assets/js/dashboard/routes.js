let currentRouteId = null;
    
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

async function viewRoute(routeId) {
    try {
        const response = await fetch(`{{ path('app_admin_routes_api_get', {'routeId': 'ROUTE_ID'}) }}`.replace('ROUTE_ID', routeId), {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.error || '{{ "admin.routes.error_loading"|trans }}');
        }
        
        const routeData = await response.json();
        document.getElementById('routeContent').textContent = JSON.stringify(routeData, null, 2);
        showModal('viewModal');
    } catch (error) {
        showToast(error.message, 'error');
    }
}

async function editRoute(routeId) {
    try {
        const response = await fetch(`{{ path('app_admin_routes_api_get', {'routeId': 'ROUTE_ID'}) }}`.replace('ROUTE_ID', routeId), {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.error || '{{ "admin.routes.error_loading"|trans }}');
        }
        
        const routeData = await response.json();
        currentRouteId = routeId;
        
        document.getElementById('routeId').value = routeId;
        document.getElementById('routeData').value = JSON.stringify(routeData, null, 2);
        
        showModal('editModal');
    } catch (error) {
        showToast(error.message, 'error');
    }
}

async function saveRoute() {
    const routeData = document.getElementById('routeData').value;
    
    if (!routeData.trim()) {
        showToast('{{ "admin.routes.error_empty_data"|trans }}', 'error');
        return;
    }
    
    try {
        JSON.parse(routeData);
    } catch (e) {
        showToast('{{ "admin.routes.error_invalid_json"|trans }}', 'error');
        return;
    }
    
    const saveButton = document.querySelector('#editModal .btn-primary');
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ "admin.routes.saving"|trans }}';
    saveButton.disabled = true;
    
    try {
        const response = await fetch(`{{ path('app_admin_routes_api_update', {'routeId': 'ROUTE_ID'}) }}`.replace('ROUTE_ID', currentRouteId), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: routeData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('{{ "admin.routes.success_saved"|trans }}', 'success');
            closeModal('editModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || '{{ "admin.routes.error_saving"|trans }}');
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        saveButton.innerHTML = '<i class="fas fa-save"></i> {{ "admin.routes.save"|trans }}';
        saveButton.disabled = false;
    }
}

async function deleteRoute(routeId) {
    if (!confirm('{{ "admin.routes.confirm_delete"|trans }}')) {
        return;
    }
    
    const row = document.querySelector(`tr[data-route-id="${routeId}"]`);
    row.classList.add('loading');
    
    try {
        const response = await fetch(`{{ path('app_admin_routes_api_delete', {'routeId': 'ROUTE_ID'}) }}`.replace('ROUTE_ID', routeId), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('{{ "admin.routes.success_deleted"|trans }}', 'success');
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            throw new Error(result.error || '{{ "admin.routes.error_deleting"|trans }}');
        }
    } catch (error) {
        showToast(error.message, 'error');
        row.classList.remove('loading');
    }
}

function openCreateModal() {
    document.getElementById('newRouteId').value = '';
    document.getElementById('newRouteData').value = JSON.stringify({
        "name": "{{ 'admin.routes.template.name'|trans }}",
        "description": "{{ 'admin.routes.template.description'|trans }}",
        "difficulty": "{{ 'admin.routes.template.difficulty'|trans }}",
        "duration": "{{ 'admin.routes.template.duration'|trans }}",
        "distance": "{{ 'admin.routes.template.distance'|trans }}",
        "color": "#ff6b00",
        "points": [
            {
                "name": "{{ 'admin.routes.template.start_point'|trans }}",
                "description": "{{ 'admin.routes.template.start_description'|trans }}",
                "coordinates": [53.9, 27.56],
                "type": "start"
            }
        ]
    }, null, 2);
    showModal('createModal');
}

async function createRoute() {
    const routeId = document.getElementById('newRouteId').value.trim();
    const routeData = document.getElementById('newRouteData').value;
    
    if (!routeId) {
        showToast('{{ "admin.routes.error_empty_id"|trans }}', 'error');
        return;
    }
    
    if (!routeData.trim()) {
        showToast('{{ "admin.routes.error_empty_data"|trans }}', 'error');
        return;
    }
    
    try {
        JSON.parse(routeData);
    } catch (e) {
        showToast('{{ "admin.routes.error_invalid_json"|trans }}', 'error');
        return;
    }
    
    const createButton = document.querySelector('#createModal .btn-primary');
    createButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ "admin.routes.creating"|trans }}';
    createButton.disabled = true;
    
    try {
        const response = await fetch(`{{ path('app_admin_routes_api_create') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id: routeId,
                data: JSON.parse(routeData)
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('{{ "admin.routes.success_created"|trans }}', 'success');
            closeModal('createModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || '{{ "admin.routes.error_creating"|trans }}');
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        createButton.innerHTML = '<i class="fas fa-plus"></i> {{ "admin.routes.create"|trans }}';
        createButton.disabled = false;
    }
}

function copyRouteContent() {
    const content = document.getElementById('routeContent').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(content).then(() => {
            showToast('{{ "admin.routes.success_copied"|trans }}', 'success');
        }).catch(() => {
            fallbackCopy(content);
        });
    } else {
        fallbackCopy(content);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('{{ "admin.routes.success_copied"|trans }}', 'success');
    } catch (err) {
        showToast('{{ "admin.routes.error_copying"|trans }}', 'error');
    }
    
    document.body.removeChild(textArea);
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 3000);
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            closeModal(openModal.id);
        }
    }
});