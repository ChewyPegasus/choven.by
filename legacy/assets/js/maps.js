(function() {
    // 1. Move "state" outside of functions
    // These variables will persist between site navigations.
    let map = null;
    let routeLayers = {};
    // Array to track all our event handlers so we can remove them.
    let eventListeners = [];

    // --- Main Turbo lifecycle functions ---

    /**
     * Initializes everything on the page. Called when entering the page.
     */
    function initializePage() {
        // If there is no map container on the page, do nothing.
        if (!document.getElementById('interactive-map')) {
            return;
        }
        
        // Sequentially run initialization
        initMap();
        setupEventListeners();
        loadInitialRoute();
    }

    /**
     * Cleans up everything before leaving the page.
     */
    function cleanupPage() {
        // Remove all event handlers we added
        eventListeners.forEach(({ element, type, handler }) => {
            element.removeEventListener(type, handler);
        });
        eventListeners = []; // Clear the array

        // Completely destroy the map instance if it exists
        if (map) {
            map.remove();
            map = null;
        }

        // Reset layer state
        routeLayers = {};
    }

    // Bind our functions to Turbo events
    document.addEventListener('turbo:load', initializePage);
    document.addEventListener('turbo:before-cache', cleanupPage);


    // --- Separate initialization functions ---

    /**
     * Creates a map instance, but only once.
     */
    function initMap() {
        // Guard: if the map is already created, exit.
        if (map) {
            return;
        }
        
        map = L.map('interactive-map').setView([53.9, 27.56], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
    }

    /**
     * Sets up all event handlers (clicks on buttons, cards, etc.).
     */
    function setupEventListeners() {
        // Helper function to add a listener and track it immediately
        const addTrackedListener = (element, type, handler) => {
            if (element) {
                element.addEventListener(type, handler);
                eventListeners.push({ element, type, handler });
            }
        };
        
        document.querySelectorAll('.route-card').forEach(card => addTrackedListener(card, 'click', handleRouteCardClick));
        document.querySelectorAll('.btn-show-route').forEach(btn => addTrackedListener(btn, 'click', handleShowRouteClick));
        addTrackedListener(document.getElementById('show-all-routes'), 'click', showAllRoutes);
        addTrackedListener(document.getElementById('reset-map'), 'click', handleResetMapClick);
    }

    /**
     * Loads and displays the first route from the list on initial load.
     */
    function loadInitialRoute() {
        const routes = window.routesData || {};
        const routeIds = Object.keys(routes);
        if (routeIds.length > 0) {
            // setTimeout is no longer needed, Turbo correctly manages load timing
            showRoute(routeIds[0]);
        }
    }


    // --- Event handlers (instead of anonymous functions) ---

    function handleRouteCardClick() {
        const routeId = this.getAttribute('data-route');
        if (this.classList.contains('active')) {
            handleResetMapClick();
        } else {
            showRoute(routeId);
        }
    }

    function handleShowRouteClick(e) {
        e.stopPropagation();
        const routeId = this.getAttribute('data-route');
        showRoute(routeId);
    }

    function handleResetMapClick() {
        clearAllRoutes();
        updateActiveCard(null);
        if (map) {
            map.setView([53.9, 27.56], 8);
        }
    }

    // --- Your helper functions (almost unchanged) ---
    // They now simply use the global variables `map` and `routeLayers`

    function showRoute(routeId) {
        const routes = window.routesData || {};
        if (!map || !routes[routeId]) {
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
        const routes = window.routesData || {};
        if (!map) return;
        
        clearAllRoutes();
        Object.keys(routes).forEach(routeId => {
            const routeGroup = createRoute(routeId, routes[routeId]);
            routeGroup.addTo(map);
        });

        const allCoords = Object.values(routes).flatMap(route => route.points.map(point => point.coordinates));
        
        if (allCoords.length > 0) {
            const bounds = L.latLngBounds(allCoords);
            map.fitBounds(bounds, { padding: [30, 30] });
        }
        updateActiveCard(null);
    }

    function clearAllRoutes() {
        if (!map) return;
        Object.keys(routeLayers).forEach(routeId => {
            if (routeLayers[routeId]) {
                map.removeLayer(routeLayers[routeId]);
            }
        });
        routeLayers = {};
    }

    function createRoute(routeId, routeData) {
        // All your route creation logic stays here...
        // Copying your functions unchanged, as they are well written
        const allLayers = [];
        const polylineCoords = routeData.points.map(point => point.coordinates);

        const polyline = L.polyline(polylineCoords, { color: routeData.color, weight: 5, opacity: 0.8, smoothFactor: 1, dashArray: null });
        const shadowLine = L.polyline(polylineCoords, { color: '#000000', weight: 7, opacity: 0.3, smoothFactor: 1 });

        const routePopup = `<div class="route-popup"><h4>${routeData.name}</h4><p>${routeData.description}</p><div class="route-stats"><div class="stat"><i class="fas fa-clock"></i> <span>${routeData.duration}</span></div><div class="stat"><i class="fas fa-route"></i> <span>${routeData.distance}</span></div><div class="stat"><i class="fas fa-star"></i> <span>${routeData.difficulty}</span></div></div><div class="points-count"><i class="fas fa-map-marker-alt"></i> ${getInterfaceText('points_count')} ${routeData.points.length}</div></div>`;
        polyline.bindPopup(routePopup);

        allLayers.push(shadowLine, polyline);

        let waypointCounter = 1;
        routeData.points.forEach((point) => {
            let currentType = point.type || 'point';
            let markerIcon;
            if (currentType === 'start' || currentType === 'end') {
                markerIcon = createMarkerIcon(currentType);
            } else {
                markerIcon = createMarkerIcon('point', waypointCounter++);
            }
            const marker = L.marker(point.coordinates, { icon: markerIcon }).bindPopup(createPointPopup(point));
            allLayers.push(marker);
        });

        const routeGroup = L.layerGroup(allLayers);
        routeLayers[routeId] = routeGroup;
        return routeGroup;
    }

    function updateActiveCard(activeRouteId) {
        document.querySelectorAll('.route-card').forEach(card => card.classList.remove('active'));
        if (activeRouteId) {
            const activeCard = document.querySelector(`[data-route="${activeRouteId}"]`);
            if (activeCard) {
                activeCard.classList.add('active');
            }
        }
    }

    // Utility functions that do not depend on state
    function getInterfaceText(key) {
        const currentLocale = document.documentElement.lang || 'ru';
        const texts = {
            ru: { points_count: 'Точек маршрута:', click_for_details: 'Нажмите на точки маршрута для подробной информации' },
            be: { points_count: 'Кропак маршруту:', click_for_details: 'Націсніце на кропкі маршруту для падрабязнай інфармацыі' },
            en: { points_count: 'Route points:', click_for_details: 'Click on route points for detailed information' }
        };
        return texts[currentLocale]?.[key] || texts['ru'][key] || key;
    }

    function createMarkerIcon(type, index) {
        const config = {
            start: { html: '<i class="fas fa-play"></i>', className: 'marker-start', size: [40, 40] },
            end: { html: '<i class="fas fa-flag-checkered"></i>', className: 'marker-end', size: [40, 40] },
            point: { html: `<span class="waypoint-number">${index}</span>`, className: 'marker-waypoint', size: [30, 30] }
        };
        const markerConfig = config[type];
        return L.divIcon({
            className: markerConfig.className,
            html: markerConfig.html,
            iconSize: markerConfig.size,
            iconAnchor: [markerConfig.size[0] / 2, markerConfig.size[1]],
            popupAnchor: [0, -markerConfig.size[1]]
        });
    }

    function createPointPopup(point) {
        return `<div class="point-popup"><h5>${point.name}</h5><p>${point.description}</p></div>`;
    }
})();