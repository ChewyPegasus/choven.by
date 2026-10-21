import { Controller } from '@hotwired/stimulus';

/* Tabs with ARIA roles; the active tab is kept in the URL hash. */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        const fromHash = this.tabTargets.find((tab) => `#${tab.getAttribute('aria-controls')}` === window.location.hash);
        this.activate(fromHash ?? this.tabTargets.find((t) => t.getAttribute('aria-selected') === 'true') ?? this.tabTargets[0], false);
    }

    select(event) {
        event.preventDefault();
        this.activate(event.currentTarget, true);
    }

    activate(tab, updateHash) {
        if (!tab) {
            return;
        }
        this.tabTargets.forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.id !== tab.getAttribute('aria-controls');
        });
        if (updateHash) {
            history.replaceState(history.state, '', `#${tab.getAttribute('aria-controls')}`);
        }
    }
}
