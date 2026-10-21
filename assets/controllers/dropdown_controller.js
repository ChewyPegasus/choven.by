import { Controller } from '@hotwired/stimulus';

/*
 * Accessible dropdown: opens on click (works on touch screens), closes on outside click or Escape.
 *   <div class="dropdown" data-controller="dropdown"><button data-action="dropdown#toggle" aria-expanded="false">…
 */
export default class extends Controller {
    connect() {
        this.onDocumentClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.onKeydown = (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('click', this.onDocumentClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    toggle(event) {
        event.preventDefault();
        this.element.classList.contains('is-open') ? this.close() : this.open();
    }

    open() {
        this.element.classList.add('is-open');
        this.element.querySelector('[aria-expanded]')?.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.element.classList.remove('is-open');
        this.element.querySelector('[aria-expanded]')?.setAttribute('aria-expanded', 'false');
    }
}
