import { Controller } from '@hotwired/stimulus';

/* Flash message that disappears by itself (errors stay until closed). */
export default class extends Controller {
    static values = { timeout: { type: Number, default: 6000 } };

    connect() {
        if (this.timeoutValue > 0 && !this.element.classList.contains('alert-error')) {
            this.timer = setTimeout(() => this.close(), this.timeoutValue);
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        clearTimeout(this.timer);
        this.element.classList.add('is-leaving');
        this.element.addEventListener('animationend', () => this.element.remove(), { once: true });
    }
}
