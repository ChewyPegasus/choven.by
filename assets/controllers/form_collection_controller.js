import { Controller } from '@hotwired/stimulus';

/*
 * Add/remove entries of a Symfony CollectionType.
 *   <div data-controller="form-collection" data-form-collection-prototype-value="…" data-form-collection-index-value="3">
 *       <div data-form-collection-target="list">…<button data-action="form-collection#remove">…</div>
 *       <button data-action="form-collection#add">Add</button>
 */
export default class extends Controller {
    static targets = ['list'];
    static values = { prototype: String, index: Number };

    add(event) {
        event?.preventDefault();
        const html = this.prototypeValue.replace(/__name__/g, String(this.indexValue));
        this.indexValue++;

        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const item = template.content.firstElementChild;
        this.listTarget.appendChild(item);
        this.renumber();
        this.dispatch('added', { detail: { item } });

        return item;
    }

    remove(event) {
        event.preventDefault();
        event.currentTarget.closest('[data-collection-item]')?.remove();
        this.renumber();
        this.dispatch('removed');
    }

    renumber() {
        this.listTarget.querySelectorAll('[data-collection-number]').forEach((el, i) => {
            el.textContent = String(i + 1);
        });
    }
}
