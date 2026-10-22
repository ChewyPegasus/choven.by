import { Controller } from '@hotwired/stimulus';

/*
 * Gift certificate form: amount presets and a live preview of the certificate.
 */
export default class extends Controller {
    static targets = ['amount', 'recipient', 'message', 'previewAmount', 'previewRecipient', 'previewMessage'];
    static values = { defaultRecipient: String };

    connect() {
        this.update();
    }

    preset(event) {
        this.amountTarget.value = event.params.amount;
        this.update();
    }

    update() {
        const amount = parseInt(this.amountTarget.value, 10);
        this.previewAmountTarget.textContent = Number.isNaN(amount) ? '—' : amount.toLocaleString('ru-RU');
        const recipient = this.recipientTarget.value.trim();
        this.previewRecipientTarget.textContent = recipient || this.defaultRecipientValue;
        this.previewMessageTarget.textContent = this.messageTarget.value.trim();
    }
}
