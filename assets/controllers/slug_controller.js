import { Controller } from '@hotwired/stimulus';

/*
 * Fills the URL slug from the (Russian) title while the slug was not edited by hand.
 *   <div data-controller="slug" data-slug-source-value="#tour_title_ru"> … <input data-slug-target="slug" data-action="slug#edited">
 */
const MAP = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', і: 'i', к: 'k', л: 'l', м: 'm',
    н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ў: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
    ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
};

export default class extends Controller {
    static targets = ['slug'];
    static values = { source: String };

    connect() {
        this.manual = this.slugTarget.value !== '';
        this.source = this.element.querySelector(this.sourceValue);
        this.onInput = () => this.sync();
        this.source?.addEventListener('input', this.onInput);
    }

    disconnect() {
        this.source?.removeEventListener('input', this.onInput);
    }

    edited() {
        this.manual = this.slugTarget.value !== '';
    }

    sync() {
        if (this.manual || !this.source) {
            return;
        }
        this.slugTarget.value = [...this.source.value.toLowerCase()]
            .map((c) => MAP[c] ?? c)
            .join('')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80);
    }
}
