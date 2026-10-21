import { Controller } from '@hotwired/stimulus';

/* Show/hide password button: wraps the input in .password-field and adds a toggle. */
export default class extends Controller {
    static values = { show: String, hide: String };

    connect() {
        const input = this.element.querySelector('input[type="password"]');
        if (!input || this.element.querySelector('.password-toggle')) {
            return;
        }
        this.input = input;
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'password-toggle';
        this.button.setAttribute('aria-label', this.showValue || 'Show password');
        this.button.innerHTML = '<i class="fa-regular fa-eye"></i>';
        this.button.addEventListener('click', () => this.toggle());
        wrapper.appendChild(this.button);
    }

    toggle() {
        const visible = this.input.type === 'text';
        this.input.type = visible ? 'password' : 'text';
        this.button.innerHTML = visible ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
        this.button.setAttribute('aria-label', (visible ? this.showValue : this.hideValue) || '');
    }
}
