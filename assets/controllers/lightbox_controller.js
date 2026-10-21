import { Controller } from '@hotwired/stimulus';

/* Photo gallery viewer built on <dialog>: arrows, keyboard and swipe navigation. */
export default class extends Controller {
    static targets = ['link', 'dialog', 'image'];

    open(event) {
        event.preventDefault();
        this.index = this.linkTargets.indexOf(event.currentTarget);
        this.show();
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    next() {
        this.index = (this.index + 1) % this.linkTargets.length;
        this.show();
    }

    previous() {
        this.index = (this.index - 1 + this.linkTargets.length) % this.linkTargets.length;
        this.show();
    }

    keydown(event) {
        if (event.key === 'ArrowRight') this.next();
        if (event.key === 'ArrowLeft') this.previous();
    }

    touchstart(event) {
        this.touchX = event.changedTouches[0].clientX;
    }

    touchend(event) {
        const dx = event.changedTouches[0].clientX - this.touchX;
        if (Math.abs(dx) > 50) {
            dx < 0 ? this.next() : this.previous();
        }
    }

    show() {
        const link = this.linkTargets[this.index];
        this.imageTarget.src = link.href;
        this.imageTarget.alt = link.querySelector('img')?.alt ?? '';
    }
}
