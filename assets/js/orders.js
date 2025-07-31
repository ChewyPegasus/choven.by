function initComponents() {
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const targetId = this.getAttribute('href');
            
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            document.querySelector(targetId).classList.add('show', 'active');
        });
    });
}

document.addEventListener('DOMContentLoaded', initComponents);
document.addEventListener('turbo:load', initComponents);
