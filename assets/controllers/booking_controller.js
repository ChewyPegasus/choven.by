import { Controller } from '@hotwired/stimulus';

/*
 * Booking form helper: live price (trip + add-ons − promo / gift certificate, prepayment)
 * and availability of the chosen dates. The server recalculates everything on submit.
 *
 * Values: availabilityUrl, promoUrl, price (tour price, BYN), unit ("person" | "group"),
 * depositPercent, labels (translated messages).
 */
export default class extends Controller {
    static targets = [
        'date', 'days', 'people', 'availability',
        'price', 'base', 'baseRow', 'summary', 'extraRows', 'extra',
        'discount', 'discountRow', 'deposit', 'depositRow',
        'promo', 'promoMessage',
    ];

    static values = {
        availabilityUrl: String,
        promoUrl: String,
        price: { type: Number, default: 0 },
        unit: { type: String, default: 'person' },
        depositPercent: { type: Number, default: 0 },
        labels: Object,
    };

    connect() {
        this.cache = null;
        this.discount = null;
        this.refresh();
        if (this.hasPromoTarget && this.promoTarget.value.trim() !== '') {
            this.checkPromo();
        }
    }

    refresh() {
        this.updatePrice();
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.checkAvailability(), 250);
    }

    get people() {
        return this.hasPeopleTarget ? Math.max(1, parseInt(this.peopleTarget.value, 10) || 1) : 1;
    }

    get days() {
        return this.hasDaysTarget ? Math.min(7, Math.max(1, parseInt(this.daysTarget.value, 10) || 1)) : 1;
    }

    updatePrice() {
        if (!this.hasPriceTarget) {
            return;
        }

        const people = this.people;
        const base = this.priceValue ? (this.unitValue === 'group' ? this.priceValue : this.priceValue * people) : 0;

        let extrasTotal = 0;
        const rows = [];
        for (const input of this.extraTargets) {
            const quantity = input.type === 'checkbox' ? (input.checked ? 1 : 0) : Math.max(0, parseInt(input.value, 10) || 0);
            if (quantity === 0) {
                continue;
            }
            const price = parseInt(input.dataset.price, 10) || 0;
            const count = input.dataset.unit === 'person' ? people : (input.dataset.unit === 'item' ? quantity : 1);
            const total = price * count * (input.dataset.perDay === '1' ? this.days : 1);
            extrasTotal += total;
            const label = this.element.querySelector(`label[for="${input.id}"]`)?.textContent.trim() ?? '';
            rows.push([input.dataset.unit === 'item' ? `${label} × ${quantity}` : label, total]);
        }

        if (this.hasExtraRowsTarget) {
            this.extraRowsTarget.replaceChildren(...rows.map(([label, total]) => {
                const tr = document.createElement('tr');
                const th = document.createElement('th');
                const td = document.createElement('td');
                th.textContent = label;
                td.textContent = this.money(total);
                tr.append(th, td);
                return tr;
            }));
        }

        if (!this.priceValue) {
            // No public price: the total is agreed by phone.
            if (this.hasSummaryTarget) {
                this.summaryTarget.hidden = true;
            }
            return;
        }

        const subtotal = base + extrasTotal;
        let discount = 0;
        if (this.discount?.kind === 'promo') {
            discount = this.discount.type === 'percent' ? Math.round(subtotal * this.discount.value / 100) : this.discount.value;
        } else if (this.discount?.kind === 'gift') {
            discount = this.discount.balance;
        }
        discount = Math.min(discount, subtotal);
        const total = subtotal - discount;

        if (this.hasSummaryTarget) {
            this.summaryTarget.hidden = false;
        }
        if (this.hasBaseTarget) {
            this.baseTarget.textContent = this.money(base);
        }
        if (this.hasDiscountRowTarget) {
            this.discountRowTarget.hidden = discount === 0;
            this.discountTarget.textContent = `−${this.money(discount)}`;
        }
        this.priceTarget.textContent = this.money(total);
        if (this.hasDepositTarget) {
            const deposit = Math.ceil(total * this.depositPercentValue / 100);
            this.depositRowTarget.hidden = deposit === 0;
            this.depositTarget.textContent = this.money(deposit);
        }
    }

    async checkPromo() {
        if (!this.hasPromoTarget || !this.promoUrlValue) {
            return;
        }

        const code = this.promoTarget.value.trim();
        this.discount = null;
        if (code === '') {
            this.setPromoMessage('', '');
            this.updatePrice();
            return;
        }

        try {
            const url = new URL(this.promoUrlValue, window.location.origin);
            url.searchParams.set('code', code);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (data.valid) {
                this.discount = data;
                const message = data.kind === 'gift'
                    ? this.labelsValue.giftApplied.replace('%amount%', data.balance)
                    : this.labelsValue.promoApplied;
                this.setPromoMessage(message, 'text-success');
            } else {
                this.setPromoMessage(data.message ?? '', 'text-danger');
            }
        } catch {
            this.setPromoMessage('', '');
        }
        this.updatePrice();
    }

    setPromoMessage(text, className) {
        if (!this.hasPromoMessageTarget) {
            return;
        }
        this.promoMessageTarget.textContent = text;
        this.promoMessageTarget.className = `text-small ${className}`;
        this.promoMessageTarget.hidden = !text;
    }

    async checkAvailability() {
        if (!this.hasAvailabilityTarget || !this.dateTarget.value) {
            this.setMessage('', '');
            return;
        }

        const start = new Date(`${this.dateTarget.value}T00:00:00`);
        if (Number.isNaN(start.getTime())) {
            return;
        }
        const days = this.days;
        const people = this.people;

        let calendar;
        try {
            calendar = await this.loadCalendar(start);
        } catch {
            this.setMessage('', '');
            return;
        }

        let minFree = Infinity;
        for (let i = 0; i < days; i++) {
            const day = new Date(start);
            day.setDate(day.getDate() + i);
            const info = calendar.days[this.format(day)];
            if (info?.blocked) {
                this.setMessage(this.labelsValue.blocked, 'is-full');
                return;
            }
            if (info && info.free !== null) {
                minFree = Math.min(minFree, info.free);
            }
        }

        if (minFree === Infinity) {
            this.setMessage(this.labelsValue.available, 'is-ok');
        } else if (minFree < people) {
            this.setMessage(minFree === 0 ? this.labelsValue.soldOut : this.labelsValue.notEnough.replace('%count%', minFree), 'is-full');
        } else if (calendar.capacity && minFree < calendar.capacity / 4) {
            this.setMessage(this.labelsValue.few.replace('%count%', minFree), 'is-low');
        } else {
            this.setMessage(this.labelsValue.available, 'is-ok');
        }
    }

    async loadCalendar(start) {
        const from = new Date(start);
        const to = new Date(start);
        to.setDate(to.getDate() + 7);
        const key = `${this.format(from)}:${this.format(to)}`;
        if (this.cache?.key === key) {
            return this.cache.data;
        }
        const url = new URL(this.availabilityUrlValue, window.location.origin);
        url.searchParams.set('from', this.format(from));
        url.searchParams.set('to', this.format(to));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(String(response.status));
        }
        const data = await response.json();
        this.cache = { key, data };
        return data;
    }

    setMessage(text, state) {
        this.availabilityTarget.textContent = text;
        this.availabilityTarget.className = `availability ${state}`;
        this.availabilityTarget.hidden = !text;
    }

    money(amount) {
        return `${amount.toLocaleString('ru-RU')} BYN`;
    }

    format(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }
}
