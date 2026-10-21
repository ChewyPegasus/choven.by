import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
/*
 * Interactive map of rafting routes (Leaflet + OpenStreetMap tiles).
 *
 *   <div data-controller="map" data-map-routes-value="[…]" data-map-labels-value="{…}">
 *       <button data-map-target="item" data-route-id="isloch" data-action="map#select">…</button>
 *       <div data-map-target="canvas"></div>
 *   </div>
 *
 * Leaflet is loaded only when the map scrolls into view.
 */
export default class extends Controller {
    static targets = ['canvas', 'item'];
    static values = {
        routes: Array,
        labels: Object,
        interactive: { type: Boolean, default: true },
    };

    connect() {
        if (!('IntersectionObserver' in window)) {
            this.init();
            return;
        }
        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.observer.disconnect();
                this.init();
            }
        }, { rootMargin: '300px' });
        this.observer.observe(this.canvasTarget);
    }

    disconnect() {
        this.observer?.disconnect();
        this.map?.remove();
        this.map = null;
    }

    async init() {
        const [leaflet] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.min.css')]);
        this.L = leaflet.default ?? leaflet;
        if (!this.element.isConnected) {
            return;
        }

        this.canvasTarget.querySelector('.map-loading')?.remove();
        const el = document.createElement('div');
        el.className = 'map-el';
        this.canvasTarget.appendChild(el);

        this.map = this.L.map(el, {
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true,
        });
        this.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

        // Enable wheel zoom only after the user interacts with the map, so the page scrolls normally.
        this.map.on('focus click', () => this.map.scrollWheelZoom.enable());
        this.map.on('blur', () => this.map.scrollWheelZoom.disable());

        this.layers = new Map();
        this.routesValue.forEach((route) => this.layers.set(route.id, this.buildRoute(route)));
        this.showAll();
    }

    buildRoute(route) {
        const L = this.L;
        const latLngs = route.points.map((p) => [p.lat, p.lng]);
        const group = L.featureGroup();

        L.polyline(latLngs, { color: route.color, weight: 5, opacity: 0.85, lineJoin: 'round' })
            .on('click', () => this.focus(route.id))
            .addTo(group);

        route.points.forEach((point, index) => {
            const label = point.type === 'start' ? 'A' : point.type === 'end' ? 'B' : String(index);
            const icon = L.divIcon({
                className: '',
                html: `<div class="map-pin is-${point.type}" style="--pin:${route.color}"><span class="map-pin-label">${label}</span></div>`,
                iconSize: point.type === 'point' ? [28, 28] : [34, 34],
                iconAnchor: point.type === 'point' ? [14, 28] : [17, 34],
                popupAnchor: [0, -30],
            });
            L.marker([point.lat, point.lng], { icon, title: point.name, keyboard: true })
                .bindPopup(`<h4>${this.escape(point.name)}</h4>${point.description ? `<p>${this.escape(point.description)}</p>` : ''}<p><small>${this.escape(route.name)}</small></p>`)
                .addTo(group);
        });

        return group;
    }

    select(event) {
        this.focus(event.currentTarget.dataset.routeId);
    }

    focus(id) {
        if (!this.map) {
            return;
        }
        this.layers.forEach((layer, routeId) => {
            if (routeId === id) {
                layer.addTo(this.map);
            } else {
                layer.remove();
            }
        });
        const layer = this.layers.get(id);
        if (layer) {
            this.map.fitBounds(layer.getBounds(), { padding: [40, 40] });
        }
        this.itemTargets.forEach((item) => item.classList.toggle('is-active', item.dataset.routeId === id));
    }

    showAll() {
        if (!this.map) {
            return;
        }
        const bounds = this.L.latLngBounds([]);
        this.layers.forEach((layer) => {
            layer.addTo(this.map);
            bounds.extend(layer.getBounds());
        });
        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [40, 40] });
        } else {
            this.map.setView([53.9, 27.56], 8); // Minsk
        }
        this.itemTargets.forEach((item) => item.classList.remove('is-active'));
    }

    escape(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }
}
