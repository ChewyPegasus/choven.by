// assets/js/maps.js - добавляем поддержку переводов

document.addEventListener('DOMContentLoaded', function() {
    console.log('Инициализация карты...');
    
    const mapElement = document.getElementById('interactive-map');
    if (!mapElement) {
        console.error('Элемент карты не найден!');
        return;
    }

    // Получаем данные маршрутов и переводы
    const routes = window.routesData || {};
    const labels = window.mapLabels || {};
    
    console.log('Загружены маршруты:', Object.keys(routes));
    console.log('Загружены переводы:', labels);

    // Создаем карту
    const map = L.map('interactive-map').setView([53.9, 27.56], 8);

    // Добавляем тайлы
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Хранилище для слоев маршрутов
    const routeLayers = {};

    // Определяем текущую локаль
    const currentLocale = document.documentElement.lang || 'ru';

    // Переводы для различных элементов интерфейса
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

    // Создание иконок для маркеров
    function createMarkerIcon(type, index) {
        const config = {
            start: { 
                html: '<i class="fas fa-play"></i>', 
                className: 'marker-start',
                size: [35, 35]
            },
            end: { 
                html: '<i class="fas fa-flag-checkered"></i>', 
                className: 'marker-end',
                size: [35, 35]
            },
            point: { 
                html: `<span class="waypoint-number">${index}</span>`, 
                className: 'marker-waypoint',
                size: [25, 25]
            }
        };

        const markerConfig = config[type];
        return L.divIcon({
            className: markerConfig.className,
            html: markerConfig.html,
            iconSize: markerConfig.size,
            iconAnchor: [markerConfig.size[0]/2, markerConfig.size[1]]
        });
    }

    // Создание контента для попапа точки
    function createPointPopup(point) {
        return `
            <div class="point-popup">
                <h5>${point.name}</h5>
                <p>${point.description}</p>
            </div>
        `;
    }

    // Создание маршрута на карте
    function createRoute(routeId, routeData) {
        console.log(`Создаем маршрут: ${routeId}`, routeData);
        
        // Удаляем существующий маршрут если есть
        if (routeLayers[routeId]) {
            map.removeLayer(routeLayers[routeId]);
            delete routeLayers[routeId];
        }

        const allLayers = [];

        // Извлекаем координаты точек для ломаной линии
        const polylineCoords = routeData.points.map(point => point.coordinates);
        console.log('Координаты ломаной:', polylineCoords);

        // Создаем ломаную линию (соединяет точки прямыми отрезками)
        const polyline = L.polyline(polylineCoords, {
            color: routeData.color,
            weight: 4,
            opacity: 0.8,
            smoothFactor: 0, // Отключаем сглаживание для четких углов
            dashArray: '8,4' // Пунктирная линия для лучшей видимости
        });

        // Добавляем попап к линии маршрута с переводами
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
        allLayers.push(polyline);

        // Создаем маркеры для всех точек
        let waypointCounter = 1;
        routeData.points.forEach((point, index) => {
            console.log(`Создаем маркер ${index + 1}:`, point);
            
            let markerIndex = index + 1;
            if (point.type === 'point') {
                markerIndex = waypointCounter;
                waypointCounter++;
            }

            // Создаем маркер
            const marker = L.marker(point.coordinates, {
                icon: createMarkerIcon(point.type, markerIndex)
            });

            // Добавляем попап к маркеру
            marker.bindPopup(createPointPopup(point));
            allLayers.push(marker);
        });

        // Группируем все элементы маршрута
        const routeGroup = L.layerGroup(allLayers);
        routeLayers[routeId] = routeGroup;

        console.log(`Маршрут ${routeId} создан, слоев: ${allLayers.length}`);
        return routeGroup;
    }

    // Показать конкретный маршрут
    function showRoute(routeId) {
        console.log(`Показываем маршрут: ${routeId}`);
        
        if (!routes[routeId]) {
            console.error(`Маршрут ${routeId} не найден!`);
            return;
        }

        // Очищаем все маршруты
        clearAllRoutes();

        // Создаем и показываем маршрут
        const routeGroup = createRoute(routeId, routes[routeId]);
        routeGroup.addTo(map);

        // Центрируем карту на маршруте
        const coordinates = routes[routeId].points.map(point => point.coordinates);
        const bounds = L.latLngBounds(coordinates);
        map.fitBounds(bounds, { padding: [20, 20] });

        // Обновляем активную карточку
        updateActiveCard(routeId);

        console.log(`Маршрут ${routeId} отображен на карте`);
    }

    // Показать все маршруты
    function showAllRoutes() {
        console.log('Показываем все маршруты');
        
        clearAllRoutes();

        // Создаем все маршруты
        Object.keys(routes).forEach(routeId => {
            const routeGroup = createRoute(routeId, routes[routeId]);
            routeGroup.addTo(map);
        });

        // Центрируем на всех маршрутах
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

    // Очистить все маршруты
    function clearAllRoutes() {
        console.log('Очищаем все маршруты');
        
        Object.keys(routeLayers).forEach(routeId => {
            map.removeLayer(routeLayers[routeId]);
            delete routeLayers[routeId];
        });
    }

    // Обновить активную карточку
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

    // Обработчики событий для карточек маршрутов
    document.querySelectorAll('.route-card').forEach(card => {
        card.addEventListener('click', function() {
            const routeId = this.getAttribute('data-route');
            console.log(`Клик по карточке: ${routeId}`);

            if (this.classList.contains('active')) {
                // Если карточка активна - сбрасываем карту
                clearAllRoutes();
                updateActiveCard(null);
                map.setView([53.9, 27.56], 8);
            } else {
                // Показываем выбранный маршрут
                showRoute(routeId);
            }
        });
    });

    // Обработчики для кнопок
    document.querySelectorAll('.btn-show-route').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const routeId = this.getAttribute('data-route');
            showRoute(routeId);
        });
    });

    // Кнопка "Все маршруты"
    const showAllBtn = document.getElementById('show-all-routes');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', showAllRoutes);
    }

    // Кнопка "Сбросить"
    const resetBtn = document.getElementById('reset-map');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            clearAllRoutes();
            updateActiveCard(null);
            map.setView([53.9, 27.56], 8);
        });
    }

    // Автоматический показ первого маршрута при загрузке
    const routeIds = Object.keys(routes);
    if (routeIds.length > 0) {
        setTimeout(() => {
            console.log('Автопоказ первого маршрута');
            showRoute(routeIds[0]);
        }, 1000);
    }

    console.log('Карта инициализирована');
});