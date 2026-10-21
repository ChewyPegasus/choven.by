import { Controller } from '@hotwired/stimulus';

/*
 * Booking form helper: live price estimate and availability of the chosen dates.
 *
 * Values: availabilityUrl, price (tour price, BYN), unit ("person" | "group"), labels (translated messages).
 */
export default class extends Controller {
    static targets = ['date', 'days', 'people', 'availability', 'price', 'priceBox'];
    static values = {
        availabilityUrl: String,
        price: { type: Number, default: 0 },
        unit: { type: String, default: 'person' },
        labels: Object,
    };

    connect() {
        this.cache = null;
        this.refresh();
    }

    refresh() {
        this.updatePrice();
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.checkAvailability(), 250);
    }

    updatePrice() {
        if (!this.hasPriceTarget || !this.priceValue) {
            return;
        }
        const people = Math.max(1, parseInt(this.peopleTarget.value, 10) || 1);
        const total = this.unitValue === 'group' ? this.priceValue : this.priceValue * people;
        this.priceTarget.textContent = `${total.toLocaleString('ru-RU')} BYN`;
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
        const days = Math.min(7, Math.max(1, parseInt(this.daysTarget.value, 10) || 1));
        const people = Math.max(1, parseInt(this.peopleTarget.value, 10) || 1);

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

    format(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }
}
