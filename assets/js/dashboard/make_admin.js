function promoteUser(userId) {
    const userItem = document.getElementById('user-' + userId);
    const button = userItem.querySelector('.btn-promote');
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ "admin.make_admin.promoting"|trans }}';
    button.disabled = true;
    userItem.classList.add('loading');
    
    fetch('{{ path("app_admin_make_admin") }}', {
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
            button.innerHTML = '<i class="fas fa-user-plus"></i> {{ "admin.make_admin.make_admin"|trans }}';
            button.disabled = false;
            userItem.classList.remove('loading');
        }
    })
    .catch(error => {
        showToast('{{ "admin.make_admin.error_occurred"|trans }}', 'error');
        button.innerHTML = '<i class="fas fa-user-plus"></i> {{ "admin.make_admin.make_admin"|trans }}';
        button.disabled = false;
        userItem.classList.remove('loading');
    });
}

function removeAdmin(userId) {
    if (!confirm('{{ "admin.make_admin.confirm_remove"|trans }}')) {
        return;
    }
    
    const adminItem = document.querySelector(`[onclick="removeAdmin(${userId})"]`).closest('.admin-item');
    const button = adminItem.querySelector('.btn-demote');
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ "admin.make_admin.removing"|trans }}';
    button.disabled = true;
    adminItem.classList.add('loading');
    
    fetch('{{ path("app_admin_remove_admin", {"id": "__ID__"}) }}'.replace('__ID__', userId), {
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
            button.innerHTML = '<i class="fas fa-user-minus"></i> {{ "admin.make_admin.remove_admin"|trans }}';
            button.disabled = false;
            adminItem.classList.remove('loading');
        }
    })
    .catch(error => {
        showToast('{{ "admin.make_admin.error_occurred"|trans }}', 'error');
        button.innerHTML = '<i class="fas fa-user-minus"></i> {{ "admin.make_admin.remove_admin"|trans }}';
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
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 3000);
}