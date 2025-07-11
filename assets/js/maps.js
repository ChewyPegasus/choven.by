document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('interactive-map');
    if (!mapElement) return;

    const routes = window.routesData || {};
    const map = L.map('interactive-map').setView([53.9, 27.57], 10);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const routeLayers = {};
    const routeMarkers = {};

    function createRoute(routeId, routeData) {
        if (routeLayers[routeId]) {
            map.removeLayer(routeLayers[routeId]);
        }
        if (routeMarkers[routeId]) {
            routeMarkers[routeId].forEach(marker => map.removeLayer(marker));
        }

        const polyline = L.polyline(routeData.coordinates, {
            color: '#ff6b00',
            weight: 4,
            opacity: 0.8,
            smoothFactor: 1
        }).addTo(map);

        const startMarker = L.marker(routeData.coordinates[0], {
            icon: L.divIcon({
                className: 'route-marker start-marker',
                html: '<i class="fas fa-play"></i>',
                iconSize: [30, 30]
            })
        }).addTo(map);

        const endMarker = L.marker(routeData.coordinates[routeData.coordinates.length - 1], {
            icon: L.divIcon({
                className: 'route-marker end-marker',
                html: '<i class="fas fa-flag-checkered"></i>',
                iconSize: [30, 30]
            })
        }).addTo(map);

        const popupContent = `
            <div class="route-popup">
                <h4>${routeData.name}</h4>
                <p>${routeData.description}</p>
                <div class="popup-info">
                    <span><i class="fas fa-clock"></i> ${routeData.duration}</span>
                    <span><i class="fas fa-route"></i> ${routeData.distance}</span>
                    <span><i class="fas fa-star"></i> ${routeData.difficulty}</span>
                </div>
            </div>
        `;

        polyline.bindPopup(popupContent);

        routeLayers[routeId] = polyline;
        routeMarkers[routeId] = [startMarker, endMarker];

        return polyline;
    }

    function showRoute(routeId) {
        if (!routes[routeId]) return;

        const routeData = routes[routeId];
        const polyline = createRoute(routeId, routeData);

        map.setView(routeData.center, routeData.zoom);

        setTimeout(() => {
            polyline.openPopup();
        }, 500);

        updateActiveCard(routeId);
    }

    function hideRoute(routeId) {
        if (routeLayers[routeId]) {
            map.removeLayer(routeLayers[routeId]);
            delete routeLayers[routeId];
        }
        if (routeMarkers[routeId]) {
            routeMarkers[routeId].forEach(marker => map.removeLayer(marker));
            delete routeMarkers[routeId];
        }
    }

    function showAllRoutes() {
        clearAllRoutes();

        Object.keys(routes).forEach(routeId => {
            createRoute(routeId, routes[routeId]);
        });

        const allCoordinates = [];
        Object.values(routes).forEach(route => {
            allCoordinates.push(...route.coordinates);
        });

        if (allCoordinates.length > 0) {
            const group = new L.featureGroup(Object.values(routeLayers));
            map.fitBounds(group.getBounds().pad(0.1));
        }

        document.querySelectorAll('.route-card').forEach(card => {
            card.classList.remove('active');
        });
    }

    function clearAllRoutes() {
        Object.keys(routeLayers).forEach(routeId => {
            hideRoute(routeId);
        });
    }

    function updateActiveCard(activeRouteId) {
        document.querySelectorAll('.route-card').forEach(card => {
            card.classList.remove('active');
        });

        const activeCard = document.querySelector(`[data-route="${activeRouteId}"]`);
        if (activeCard) {
            activeCard.classList.add('active');
        }
    }

    document.querySelectorAll('.route-card').forEach(card => {
        card.addEventListener('click', function() {
            const routeId = this.getAttribute('data-route');
            if (this.classList.contains('active')) {
                hideRoute(routeId);
                this.classList.remove('active');
                map.setView([53.9, 27.57], 10);
            } else {
                clearAllRoutes();
                showRoute(routeId);
            }
        });
    });

    document.querySelectorAll('.btn-show-route').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const routeId = this.getAttribute('data-route');
            clearAllRoutes();
            showRoute(routeId);
        });
    });

    document.getElementById('show-all-routes').addEventListener('click', showAllRoutes);

    document.getElementById('reset-map').addEventListener('click', function() {
        clearAllRoutes();
        map.setView([53.9, 27.57], 10);
        document.querySelectorAll('.route-card').forEach(card => {
            card.classList.remove('active');
        });
    });

    const firstRoute = Object.keys(routes)[0];
    if (firstRoute) {
        showRoute(firstRoute);
    }
});
