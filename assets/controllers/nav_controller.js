import { Controller } from '@hotwired/stimulus';

/*
 * Mobile navigation drawer.
 *   <header data-controller="nav"> … <button data-action="nav#toggle" data-nav-target="button"> … <nav data-nav-target="menu">
 */
export default class extends Controller {
    static targets = ['menu', 'button'];

    connect() {
        this.close = this.close.bind(this);
        this.onKeydown = (event) => event.key === 'Escape' && this.close();
        document.addEventListener('turbo:visit', this.close);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('turbo:visit', this.close);
        document.removeEventListener('keydown', this.onKeydown);
    }

    toggle() {
        const open = !this.menuTarget.classList.contains('is-open');
        this.menuTarget.classList.toggle('is-open', open);
        this.buttonTarget.setAttribute('aria-expanded', String(open));
        this.buttonTarget.querySelector('i')?.classList.toggle('fa-xmark', open);
        this.buttonTarget.querySelector('i')?.classList.toggle('fa-bars', !open);
    }

    close() {
        if (!this.hasMenuTarget) {
            return;
        }
        this.menuTarget.classList.remove('is-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        this.buttonTarget.querySelector('i')?.classList.replace('fa-xmark', 'fa-bars');
    }
}
