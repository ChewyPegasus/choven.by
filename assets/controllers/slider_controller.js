import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
/* Hero slider; Swiper is downloaded only on pages that have one. */
export default class extends Controller {
    static values = { autoplay: { type: Number, default: 6000 } };

    async connect() {
        const [{ default: Swiper }] = await Promise.all([
            import('swiper/bundle'),
            import('swiper/swiper-bundle.min.css'),
        ]);

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.swiper = new Swiper(this.element, {
            loop: true,
            speed: 700,
            effect: 'fade',
            fadeEffect: { crossFade: true },
            autoplay: reduceMotion ? false : { delay: this.autoplayValue, disableOnInteraction: false, pauseOnMouseEnter: true },
            keyboard: { enabled: true },
            a11y: { enabled: true },
            navigation: {
                nextEl: this.element.querySelector('.swiper-button-next'),
                prevEl: this.element.querySelector('.swiper-button-prev'),
            },
            pagination: { el: this.element.querySelector('.swiper-pagination'), clickable: true },
        });
    }

    disconnect() {
        this.swiper?.destroy(true, true);
        this.swiper = null;
    }
}
