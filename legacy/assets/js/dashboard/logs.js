function copyLogs() {
    const logContent = document.getElementById('logContent');
    const text = logContent.textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess();
        }).catch(function() {
            fallbackCopy(logContent);
        });
    } else {
        fallbackCopy(logContent);
    }
}

function fallbackCopy(element) {
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(element);
    selection.removeAllRanges();
    selection.addRange(range);
    
    try {
        document.execCommand('copy');
        showCopySuccess();
    } catch (err) {
        console.error("{{ 'admin.logs.copy_failed'|trans }}'", err);
    }
    
    selection.removeAllRanges();
}

function showCopySuccess() {
    const btn = document.querySelector('.copy-btn');
    const originalHTML = btn.innerHTML;
    
    btn.innerHTML = "<i class=\"fas fa-check\"></i> {{ 'admin.logs.copied'|trans }}";
    btn.style.background = '#218838';
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.style.background = '#28a745';
    }, 2000);
}

function initComponents() {
    const logContent = document.getElementById('logContent');
    if (logContent) {
        logContent.scrollTop = logContent.scrollHeight;
    }
}

document.addEventListener('DOMContentLoaded', initComponents);
document.addEventListener('turbo:load', initComponents);