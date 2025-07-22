let currentOrderId = null;

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

async function editOrder(orderId) {
    currentOrderId = orderId;
    const url = window.orderUrls.get.replace('__ID__', orderId);

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(window.translations.error_loading);
        
        const order = await response.json();

        document.getElementById('editOrderEmail').textContent = order.email;
        document.getElementById('editOrderDate').textContent = new Date(order.startDate).toLocaleDateString();
        document.getElementById('editOrderRiver').textContent = order.river;
        document.getElementById('editOrderPackage').textContent = order.package;
        document.getElementById('editOrderPeople').textContent = order.amountOfPeople;
        document.getElementById('editOrderDuration').textContent = order.durationDays;
        document.getElementById('editOrderDescription').value = order.description || '';
        
        showModal('editModal');
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

window.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});