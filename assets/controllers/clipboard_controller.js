import { Controller } from '@hotwired/stimulus';

/* Copies a value to the clipboard and briefly shows a confirmation. */
export default class extends Controller {
    static values = { text: String, done: String };

    async copy(event) {
        event.preventDefault();
        try {
            await navigator.clipboard.writeText(this.textValue);
        } catch {
            return;
        }
        const original = this.element.innerHTML;
        this.element.innerHTML = `<i class="fa-solid fa-check"></i> ${this.doneValue}`;
        setTimeout(() => { this.element.innerHTML = original; }, 1800);
    }
}
