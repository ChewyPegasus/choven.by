import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
/*
 * Admin route editor: live map preview of the points typed in the form.
 * Click on the map to append a new point; drag a marker to move it.
 */
export default class extends Controller {
    static targets = ['map', 'color'];

    async connect() {
        const [leaflet] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.min.css')]);
        this.L = leaflet.default ?? leaflet;
        if (!this.element.isConnected) {
            return;
        }

        this.map = this.L.map(this.mapTarget, { scrollWheelZoom: true });
        this.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap',
        }).addTo(this.map);
        this.layer = this.L.layerGroup().addTo(this.map);

        this.map.on('click', (event) => this.addPoint(event.latlng));
        this.onInput = () => this.redraw();
        this.element.addEventListener('input', this.onInput);
        this.element.addEventListener('form-collection:removed', this.onInput);

        this.redraw(true);
    }

    disconnect() {
        this.element.removeEventListener('input', this.onInput);
        this.element.removeEventListener('form-collection:removed', this.onInput);
        this.map?.remove();
    }

    points() {
        return [...this.element.querySelectorAll('[data-collection-item]')].map((item) => ({
            item,
            lat: parseFloat(item.querySelector('[data-route-editor-target="lat"]')?.value),
            lng: parseFloat(item.querySelector('[data-route-editor-target="lng"]')?.value),
        })).filter((p) => !Number.isNaN(p.lat) && !Number.isNaN(p.lng));
    }

    redraw(fit = false) {
        if (!this.map) {
            return;
        }
        this.layer.clearLayers();
        const color = this.hasColorTarget ? this.colorTarget.value : '#ff6b00';
        const points = this.points();

        if (points.length > 1) {
            this.L.polyline(points.map((p) => [p.lat, p.lng]), { color, weight: 5 }).addTo(this.layer);
        }
        points.forEach((p, index) => {
            const marker = this.L.marker([p.lat, p.lng], { draggable: true, title: String(index + 1) }).addTo(this.layer);
            marker.bindTooltip(String(index + 1), { permanent: true, direction: 'top', offset: [0, -10] });
            marker.on('dragend', () => {
                const { lat, lng } = marker.getLatLng();
                p.item.querySelector('[data-route-editor-target="lat"]').value = lat.toFixed(6);
                p.item.querySelector('[data-route-editor-target="lng"]').value = lng.toFixed(6);
                this.redraw();
            });
        });

        if (fit) {
            if (points.length) {
                this.map.fitBounds(this.L.latLngBounds(points.map((p) => [p.lat, p.lng])), { padding: [30, 30], maxZoom: 14 });
            } else {
                this.map.setView([53.9, 27.56], 8);
            }
        }
    }

    addPoint(latlng) {
        const collection = this.application.getControllerForElementAndIdentifier(
            this.element.querySelector('[data-controller~="form-collection"]'),
            'form-collection',
        );
        const item = collection?.add();
        if (!item) {
            return;
        }
        item.querySelector('[data-route-editor-target="lat"]').value = latlng.lat.toFixed(6);
        item.querySelector('[data-route-editor-target="lng"]').value = latlng.lng.toFixed(6);
        this.redraw();
    }
}
