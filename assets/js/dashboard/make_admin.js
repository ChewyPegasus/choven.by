function promoteUser(userId) {
    const userItem = document.getElementById('user-' + userId);
    const button = userItem.querySelector('.btn-promote');
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + window.adminTranslations.promoting;
    button.disabled = true;
    userItem.classList.add('loading');
    
    fetch(window.adminUrls.makeAdmin, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'userId=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(data.message, 'error');
            button.innerHTML = '<i class="fas fa-user-plus"></i> ' + window.adminTranslations.makeAdmin;
            button.disabled = false;
            userItem.classList.remove('loading');
        }
    })
    .catch(error => {
        showToast(window.adminTranslations.errorOccurred, 'error');
        button.innerHTML = '<i class="fas fa-user-plus"></i> ' + window.adminTranslations.makeAdmin;
        button.disabled = false;
        userItem.classList.remove('loading');
    });
}

function removeAdmin(userId) {
    if (!confirm(window.adminTranslations.confirmRemove)) {
        return;
    }
    
    const adminItem = document.querySelector(`[onclick="removeAdmin(${userId})"]`).closest('.admin-item');
    const button = adminItem.querySelector('.btn-demote');
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + window.adminTranslations.removing;
    button.disabled = true;
    adminItem.classList.add('loading');
    
    fetch(window.adminUrls.removeAdmin.replace('__ID__', userId), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(data.message, 'error');
            button.innerHTML = '<i class="fas fa-user-minus"></i> ' + window.adminTranslations.removeAdmin;
            button.disabled = false;
            adminItem.classList.remove('loading');
        }
    })
    .catch(error => {
        showToast(window.adminTranslations.errorOccurred, 'error');
        button.innerHTML = '<i class="fas fa-user-minus"></i> ' + window.adminTranslations.removeAdmin;
        button.disabled = false;
        adminItem.classList.remove('loading');
    });
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