let currentUserId = null;

function getCurrentLocale() {
    // Locale from URL
    const path = window.location.pathname;
    const localeMatch = path.match(/^\/([a-z]{2})\//);
    
    if (localeMatch) {
        return localeMatch[1];
    }
    
    // If not from URL then from lang-attribute
    return document.documentElement.lang || 'ru';
}

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

function openCreateModal() {
    document.getElementById('newUserEmail').value = '';
    document.getElementById('newUserPhone').value = '';
    document.getElementById('newUserPassword').value = '';
    document.getElementById('newUserConfirmed').checked = true;
    document.getElementById('newUserAdmin').checked = false;
    showModal('createModal');
}

async function createUser() {
    const email = document.getElementById('newUserEmail').value.trim();
    const phone = document.getElementById('newUserPhone').value.trim();
    const password = document.getElementById('newUserPassword').value;
    const isConfirmed = document.getElementById('newUserConfirmed').checked;
    const isAdmin = document.getElementById('newUserAdmin').checked;
    
    if (!email || !password) {
        showToast(window.translations.error_required_fields, 'error');
        return;
    }
    
    const createButton = document.querySelector('#createModal .btn-success');
    createButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${window.translations.creating}`;
    createButton.disabled = true;
    
    const locale = getCurrentLocale();
    
    try {
        const response = await fetch(`/${locale}/admin/users/api`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                email: email,
                phone: phone || null,
                password: password,
                isConfirmed: isConfirmed,
                isAdmin: isAdmin
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(window.translations.success_created, 'success');
            closeModal('createModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || window.translations.error_creating);
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        createButton.innerHTML = `<i class="fas fa-plus"></i> ${window.translations.create}`;
        createButton.disabled = false;
    }
}

async function editUser(userId) {
    console.log('Editing user:', userId);
    
    const locale = getCurrentLocale();
    const apiUrl = `/${locale}/admin/users/api/${userId}`;
    
    try {
        const response = await fetch(apiUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        console.log('Response status:', response.status);
        console.log('API URL:', apiUrl);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Response error text:', errorText);
            
            try {
                const errorData = JSON.parse(errorText);
                throw new Error(errorData.error || window.translations.error_loading);
            } catch (parseError) {
                console.error('Parse error:', parseError);
                throw new Error(`Server error (${response.status}): ${errorText}`);
            }
        }
        
        const userData = await response.json();
        console.log('User data received:', userData);
        
        currentUserId = userId;
        
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserEmail').value = userData.email;
        document.getElementById('editUserPhone').value = userData.phone || '';
        document.getElementById('editUserPassword').value = '';
        document.getElementById('editUserConfirmed').checked = userData.isConfirmed;
        document.getElementById('editUserAdmin').checked = userData.roles.includes('ROLE_ADMIN');
        
        showModal('editModal');
    } catch (error) {
        console.error('Edit user error:', error);
        showToast(error.message, 'error');
    }
}

async function saveUser() {
    const email = document.getElementById('editUserEmail').value.trim();
    const phone = document.getElementById('editUserPhone').value.trim();
    const password = document.getElementById('editUserPassword').value;
    const isConfirmed = document.getElementById('editUserConfirmed').checked;
    const isAdmin = document.getElementById('editUserAdmin').checked;
    
    if (!email) {
        showToast(window.translations.error_email_required, 'error');
        return;
    }
    
    const saveButton = document.querySelector('#editModal .btn-primary');
    saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${window.translations.saving}`;
    saveButton.disabled = true;
    
    const locale = getCurrentLocale();
    
    try {
        const requestData = {
            email: email,
            phone: phone || null,
            isConfirmed: isConfirmed,
            isAdmin: isAdmin
        };
        
        if (password) {
            requestData.password = password;
        }
        
        const response = await fetch(`/${locale}/admin/users/api/${currentUserId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(requestData)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(window.translations.success_updated, 'success');
            closeModal('editModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || window.translations.error_updating);
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        saveButton.innerHTML = `<i class="fas fa-save"></i> ${window.translations.save}`;
        saveButton.disabled = false;
    }
}

async function deleteUser(userId) {
    if (!confirm(window.translations.confirm_delete)) {
        return;
    }
    
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    row.classList.add('loading');
    
    const locale = getCurrentLocale();
    
    try {
        const response = await fetch(`/${locale}/admin/users/api/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(window.translations.success_deleted, 'success');
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            throw new Error(result.error || window.translations.error_deleting);
        }
    } catch (error) {
        showToast(error.message, 'error');
        row.classList.remove('loading');
    }
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
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