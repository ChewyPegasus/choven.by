let currentOrderId = null;

// --- Modal management ---
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        currentOrderId = null;
    }, 300);
}

// --- CRUD operations ---

function openCreateModal() {
    document.getElementById('createOrderForm').reset();
    showModal('createModal');
}

async function editOrder(orderId) {
    currentOrderId = orderId;
    const url = window.orderUrls.get.replace('__ID__', orderId);

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(window.translations.error_loading);
        
        const order = await response.json();
        
        document.getElementById('editEmail').value = order.email;
        document.getElementById('editStartDate').value = order.startDate.split('T')[0];
        document.getElementById('editRiver').value = order.riverValue;
        document.getElementById('editPackage').value = order.packageValue;
        document.getElementById('editAmountOfPeople').value = order.amountOfPeople;
        document.getElementById('editDurationDays').value = order.durationDays;
        document.getElementById('editDescription').value = order.description || '';
        
        showModal('editModal');
    } catch (error) {
        showToast(error.message, 'error');
    }
}

async function saveOrder(event) {
    event.preventDefault();
    const form = event.target;
    const modalId = form.closest('.modal').id;
    const isCreating = (currentOrderId === null);
    
    const url = isCreating 
        ? window.orderUrls.create 
        : window.orderUrls.update.replace('__ID__', currentOrderId);
    
    const method = isCreating ? 'POST' : 'PUT';

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || window.translations.error_saving);
        }

        closeModal(modalId);
        showToast(window.translations.success_saved, 'success');
        setTimeout(() => location.reload(), 1500);

    } catch (error) {
        showToast(error.message, 'error');
    }
}

async function deleteOrder(orderId) {
    if (!confirm(window.translations.confirm_delete)) {
        return;
    }

    const url = window.orderUrls.delete.replace('__ID__', orderId);
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    row.classList.add('loading');

    try {
        const response = await fetch(url, { method: 'DELETE' });
        if (!response.ok) throw new Error(window.translations.error_deleting);

        showToast(window.translations.success_deleted, 'success');
        row.remove();
    } catch (error) {
        showToast(error.message, 'error');
        row.classList.remove('loading');
    }
}

// --- Toast notifications ---
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// --- Global event listeners ---
window.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});
