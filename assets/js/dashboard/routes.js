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
        const url = window.routesConfig.urls.get.replace('__ID__', routeId);
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.error || window.routesConfig.translations.error_loading);
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
        const url = window.routesConfig.urls.get.replace('__ID__', routeId);
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.error || window.routesConfig.translations.error_loading);
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
        showToast(window.routesConfig.translations.error_empty_data, 'error');
        return;
    }
    
    try {
        JSON.parse(routeData);
    } catch (e) {
        showToast(window.routesConfig.translations.error_invalid_json, 'error');
        return;
    }
    
    const saveButton = document.querySelector('#editModal .btn-primary');
    saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${window.routesConfig.translations.saving}`;
    saveButton.disabled = true;
    
    try {
        const url = window.routesConfig.urls.update.replace('__ID__', currentRouteId);
        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: routeData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(window.routesConfig.translations.success_saved, 'success');
            closeModal('editModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || window.routesConfig.translations.error_saving);
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        saveButton.innerHTML = `<i class="fas fa-save"></i> ${window.routesConfig.translations.save}`;
        saveButton.disabled = false;
    }
}

async function deleteRoute(routeId) {
    if (!confirm(window.routesConfig.translations.confirm_delete)) {
        return;
    }
    
    const row = document.querySelector(`tr[data-route-id="${routeId}"]`);
    row.classList.add('loading');
    
    try {
        const url = window.routesConfig.urls.delete.replace('__ID__', routeId);
        const response = await fetch(url, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(window.routesConfig.translations.success_deleted, 'success');
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            throw new Error(result.error || window.routesConfig.translations.error_deleting);
        }
    } catch (error) {
        showToast(error.message, 'error');
        row.classList.remove('loading');
    }
}

function openCreateModal() {
    document.getElementById('newRouteId').value = '';
    document.getElementById('newRouteData').value = JSON.stringify({
        "name": window.routesConfig.translations.template.name,
        "description": window.routesConfig.translations.template.description,
        "difficulty": window.routesConfig.translations.template.difficulty,
        "duration": window.routesConfig.translations.template.duration,
        "distance": window.routesConfig.translations.template.distance,
        "color": "#ff6b00",
        "points": [
            {
                "name": window.routesConfig.translations.template.start_point,
                "description": window.routesConfig.translations.template.start_description,
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
        showToast(window.routesConfig.translations.error_empty_id, 'error');
        return;
    }
    
    if (!routeData.trim()) {
        showToast(window.routesConfig.translations.error_empty_data, 'error');
        return;
    }
    
    try {
        JSON.parse(routeData);
    } catch (e) {
        showToast(window.routesConfig.translations.error_invalid_json, 'error');
        return;
    }
    
    const createButton = document.querySelector('#createModal .btn-primary');
    createButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${window.routesConfig.translations.creating}`;
    createButton.disabled = true;
    
    try {
        const url = window.routesConfig.urls.create;
        const response = await fetch(url, {
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
            showToast(window.routesConfig.translations.success_created, 'success');
            closeModal('createModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || window.routesConfig.translations.error_creating);
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        createButton.innerHTML = `<i class="fas fa-plus"></i> ${window.routesConfig.translations.create}`;
        createButton.disabled = false;
    }
}

function copyRouteContent() {
    const content = document.getElementById('routeContent').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(content).then(() => {
            showToast(window.routesConfig.translations.success_copied, 'success');
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
        showToast(window.routesConfig.translations.success_copied, 'success');
    } catch (err) {
        showToast(window.routesConfig.translations.error_copying, 'error');
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