import { Controller } from '@hotwired/stimulus';

/* Submits a (GET filter) form when a field changes; text inputs are debounced. */
export default class extends Controller {
    static values = { delay: { type: Number, default: 400 } };

    submit(event) {
        clearTimeout(this.timer);
        const isText = event?.target?.matches('input[type="search"], input[type="text"]');
        this.timer = setTimeout(() => this.element.requestSubmit(), isText ? this.delayValue : 0);
    }

    disconnect() {
        clearTimeout(this.timer);
    }
}
