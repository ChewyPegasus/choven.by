import './bootstrap.js';
import './styles/app.css';
import './styles/pages.css';

/*
 * Small global behaviours that do not need a Stimulus controller.
 * Listeners are attached to `document`, which survives Turbo navigations, so they are added once.
 */

// <button data-modal-open="dialog-id"> opens a native <dialog>; [data-modal-close] or a click on the backdrop closes it.
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.modalOpen);
        if (dialog instanceof HTMLDialogElement) {
            event.preventDefault();
            dialog.showModal();
        }
        return;
    }

    if (event.target.closest('[data-modal-close]')) {
        event.target.closest('dialog')?.close();
        return;
    }

    if (event.target instanceof HTMLDialogElement && event.target.open) {
        // Click on the backdrop: the dialog element itself is the target.
        const rect = event.target.getBoundingClientRect();
        const inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) {
            event.target.close();
        }
    }
});

// Do not keep open dialogs in the Turbo page cache.
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
});
