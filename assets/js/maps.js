document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('interactive-map');
    if (!mapElement) {
        return;
    }

    const routes = window.routesData || {};
    const labels = window.mapLabels || {};

    const map = L.map('interactive-map').setView([53.9, 27.56], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const routeLayers = {};

    const currentLocale = document.documentElement.lang || 'ru';

    function getInterfaceText(key) {
        const texts = {
            ru: {
                points_count: 'Точек маршрута:',
                click_for_details: 'Нажмите на точки маршрута для подробной информации'
            },
            be: {
                points_count: 'Кропак маршруту:',
                click_for_details: 'Націсніце на кропкі маршруту для падрабязнай інфармацыі'
            },
            en: {
                points_count: 'Route points:',
                click_for_details: 'Click on route points for detailed information'
            }
        };
        return texts[currentLocale]?.[key] || texts['ru'][key] || key;
    }

    function createMarkerIcon(type, index) {
        const config = {
            start: { 
                html: '<i class="fas fa-play"></i>', 
                className: 'marker-start',
                size: [40, 40]
            },
            end: { 
                html: '<i class="fas fa-flag-checkered"></i>', 
                className: 'marker-end',
                size: [40, 40]
            },
            point: { 
                html: `<span class="waypoint-number">${index}</span>`, 
                className: 'marker-waypoint',
                size: [30, 30]
            }
        };
        const markerConfig = config[type];
        return L.divIcon({
            className: markerConfig.className,
            html: markerConfig.html,
            iconSize: markerConfig.size,
            iconAnchor: [markerConfig.size[0] / 2, markerConfig.size[1] / 2], 
            popupAnchor: [0, -markerConfig.size[1] / 2]
        });
    }

    function createPointPopup(point) {
        return `
            <div class="point-popup">
                <h5>${point.name}</h5>
                <p>${point.description}</p>
            </div>
        `;
    }

    function createRoute(routeId, routeData) {
        if (routeLayers[routeId]) {
            map.removeLayer(routeLayers[routeId]);
            delete routeLayers[routeId];
        }

        const allLayers = [];
        const polylineCoords = routeData.points.map(point => point.coordinates);

        const polyline = L.polyline(polylineCoords, {
            color: routeData.color,
            weight: 5,
            opacity: 0.8,
            smoothFactor: 1,
            dashArray: null
        });

        const shadowLine = L.polyline(polylineCoords, {
            color: '#000000',
            weight: 7,
            opacity: 0.3,
            smoothFactor: 1
        });

        const routePopup = `
            <div class="route-popup">
                <h4>${routeData.name}</h4>
                <p>${routeData.description}</p>
                <div class="route-stats">
                    <div class="stat">
                        <i class="fas fa-clock"></i> 
                        <span>${routeData.duration}</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-route"></i> 
                        <span>${routeData.distance}</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-star"></i> 
                        <span>${routeData.difficulty}</span>
                    </div>
                </div>
                <div class="points-count">
                    <i class="fas fa-map-marker-alt"></i> 
                    ${getInterfaceText('points_count')} ${routeData.points.length}
                </div>
            </div>
        `;
        polyline.bindPopup(routePopup);

        allLayers.push(shadowLine);
        allLayers.push(polyline);

        let waypointCounter = 1;
        routeData.points.forEach((point, index) => {
            let markerIcon;
            let currentType = point.type;
            if (!currentType) {
                currentType = 'point';
            }
            switch (currentType) {
                case 'start':
                    markerIcon = createMarkerIcon('start', null);
                    break;
                case 'end':
                    markerIcon = createMarkerIcon('end', null);
                    break;
                case 'point':
                    markerIcon = createMarkerIcon('point', waypointCounter);
                    waypointCounter++;
                    break;
                default:
                    markerIcon = createMarkerIcon('point', waypointCounter);
                    waypointCounter++;
                    break;
            }
            const marker = L.marker([point.coordinates[0], point.coordinates[1]], {
                icon: markerIcon
            });
            marker.bindPopup(createPointPopup(point));
            allLayers.push(marker);
        });

        const routeGroup = L.layerGroup(allLayers);
        routeLayers[routeId] = routeGroup;
        return routeGroup;
    }

    function showRoute(routeId) {
        if (!routes[routeId]) {
            return;
        }
        clearAllRoutes();
        const routeGroup = createRoute(routeId, routes[routeId]);
        routeGroup.addTo(map);
        const coordinates = routes[routeId].points.map(point => point.coordinates);
        const bounds = L.latLngBounds(coordinates);
        map.fitBounds(bounds, { padding: [20, 20] });
        updateActiveCard(routeId);
    }

    function showAllRoutes() {
        clearAllRoutes();
        Object.keys(routes).forEach(routeId => {
            const routeGroup = createRoute(routeId, routes[routeId]);
            routeGroup.addTo(map);
        });
        const allCoords = [];
        Object.values(routes).forEach(route => {
            route.points.forEach(point => allCoords.push(point.coordinates));
        });
        if (allCoords.length > 0) {
            const bounds = L.latLngBounds(allCoords);
            map.fitBounds(bounds, { padding: [30, 30] });
        }
        updateActiveCard(null);
    }

    function clearAllRoutes() {
        Object.keys(routeLayers).forEach(routeId => {
            map.removeLayer(routeLayers[routeId]);
            delete routeLayers[routeId];
        });
    }

    function updateActiveCard(activeRouteId) {
        document.querySelectorAll('.route-card').forEach(card => {
            card.classList.remove('active');
        });
        if (activeRouteId) {
            const activeCard = document.querySelector(`[data-route="${activeRouteId}"]`);
            if (activeCard) {
                activeCard.classList.add('active');
            }
        }
    }

    document.querySelectorAll('.route-card').forEach(card => {
        card.addEventListener('click', function() {
            const routeId = this.getAttribute('data-route');
            if (this.classList.contains('active')) {
                clearAllRoutes();
                updateActiveCard(null);
                map.setView([53.9, 27.56], 8);
            } else {
                showRoute(routeId);
            }
        });
    });

    document.querySelectorAll('.btn-show-route').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const routeId = this.getAttribute('data-route');
            showRoute(routeId);
        });
    });

    const showAllBtn = document.getElementById('show-all-routes');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', showAllRoutes);
    }

    const resetBtn = document.getElementById('reset-map');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            clearAllRoutes();
            updateActiveCard(null);
            map.setView([53.9, 27.56], 8);
        });
    }

    const routeIds = Object.keys(routes);
    if (routeIds.length > 0) {
        setTimeout(() => {
            showRoute(routeIds[0]);
        }, 1000);
    }
});
