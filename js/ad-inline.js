(function() {
    'use strict';
    
    // Сохраняем ссылку на текущий скрипт сразу при загрузке
    var currentScript = document.currentScript || (function() {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();
    
    // Конфигурация по умолчанию
    var defaultConfig = {
        width: '300px',
        height: '250px',
        theme: 'light',
        position: 'inline',
        showTitle: true,
        showDescription: true,
        showImage: true,
        category: 'all',
        limit: 1,
        autoRotate: false,
        rotateInterval: 5000,
        apiUrl: window.location.protocol + '//' + window.location.hostname + '/wp-json/custom/v1/ads',
        debug: false,
        siteId: window.adSiteId || 'default' // Добавлен параметр siteId
    };
    
    // Функция логирования
    function log(message, data) {
        var config = getConfig();
        if (config.debug) {
            console.log('[AdScript] ' + message, data || '');
        }
    }
    
    // Получение конфигурации из атрибутов скрипта
    function getConfig() {
        var config = {};
        
        // Копируем значения по умолчанию
        for (var key in defaultConfig) {
            config[key] = defaultConfig[key];
        }
        
        // Читаем параметры из data-атрибутов
        if (currentScript && currentScript.dataset) {
            for (var attr in currentScript.dataset) {
                if (currentScript.dataset.hasOwnProperty(attr)) {
                    var value = currentScript.dataset[attr];
                    
                    // Преобразуем строковые значения в нужные типы
                    if (value === 'true') value = true;
                    else if (value === 'false') value = false;
                    else if (!isNaN(value) && value !== '') value = Number(value);
                    
                    config[attr] = value;
                }
            }
        }
        
        return config;
    }
    
    // Создание контейнера для рекламы
    function createAdContainer(config) {
        var container = document.createElement('div');
        container.id = 'sexrel-ad-' + Math.random().toString(36).substr(2, 9);
        container.className = 'sexrel-ad-container';
        
        // Проверяем тип позиционирования
        var isFixed = (config.position && 
                      (config.position === 'fixed' || 
                       config.position.indexOf('top-') === 0 || 
                       config.position.indexOf('bottom-') === 0));
        
        log('Creating container with position: ' + config.position + ', isFixed: ' + isFixed);
        
        if (isFixed) {
            // Фиксированное позиционирование
            container.style.cssText = `
                position: fixed !important;
                z-index: 9999 !important;
                width: ${config.width};
                height: ${config.height};
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                font-family: Arial, sans-serif;
                background: ${config.theme === 'dark' ? '#333' : '#fff'};
                color: ${config.theme === 'dark' ? '#fff' : '#333'};
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            `;
            
            // Устанавливаем позицию
            switch (config.position) {
                case 'top-left':
                    container.style.top = '20px';
                    container.style.left = '20px';
                    break;
                case 'top-right':
                    container.style.top = '20px';
                    container.style.right = '20px';
                    break;
                case 'bottom-left':
                    container.style.bottom = '20px';
                    container.style.left = '20px';
                    break;
                case 'bottom-right':
                case 'fixed':
                default:
                    container.style.bottom = '20px';
                    container.style.right = '20px';
                    break;
            }
        } else {
            // Inline позиционирование (по умолчанию)
            container.style.cssText = `
                position: relative !important;
                display: block !important;
                width: ${config.width === '100%' ? '100%' : config.width};
                height: ${config.height === 'auto' ? 'auto' : config.height};
                min-height: ${config.height === 'auto' ? '150px' : 'auto'};
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                font-family: Arial, sans-serif;
                background: ${config.theme === 'dark' ? '#333' : '#fff'};
                color: ${config.theme === 'dark' ? '#fff' : '#333'};
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin: 15px auto !important;
                max-width: 100%;
                box-sizing: border-box;
            `;
        }
        
        return container;
    }
    
    // Загрузка данных с API
    function loadAdData(config, callback) {
        log('Loading ad data from API: ' + config.apiUrl);
        
        var xhr = new XMLHttpRequest();
        // Добавлен параметр site_id в запрос
        var url = config.apiUrl + '?category=' + encodeURIComponent(config.category) + 
                  '&limit=' + config.limit +
                  '&site_id=' + encodeURIComponent(config.siteId);
        
        xhr.open('GET', url, true);
        xhr.timeout = 10000; // 10 секунд таймаут
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        log('API data received', data);
                        callback(null, data);
                    } catch (e) {
                        log('JSON parse error: ' + e.message);
                        callback('Ошибка парсинга данных');
                    }
                } else {
                    log('API request failed with status: ' + xhr.status);
                    callback('Ошибка загрузки данных (статус: ' + xhr.status + ')');
                }
            }
        };
        
        xhr.ontimeout = function() {
            log('API request timeout');
            callback('Таймаут запроса');
        };
        
        xhr.onerror = function() {
            log('API request error');
            callback('Ошибка сети');
        };
        
        try {
            xhr.send();
        } catch (e) {
            log('XHR send error: ' + e.message);
            // Fallback на тестовые данные при ошибке
            setTimeout(function() {
                callback(null, [createTestAd()]);
            }, 100);
        }
    }
    
    // Создание тестового контента
    function createTestAd() {
        return {
            id: Math.floor(Math.random() * 1000),
            title: 'Тестовая реклама',
            description: 'Это тестовое описание рекламного материала. Проверяем корректность встраивания в контент страницы.',
            link: 'https://example.com',
            image: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjNENBRjUwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0id2hpdGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5UZXN0IEFkPC90ZXh0Pjwvc3ZnPg=='
        };
    }
    
    // Рендеринг рекламного материала
    function renderAd(container, adData, config) {
        log('Rendering ad', adData);
        
        var isAutoHeight = config.height === 'auto';
        var contentStyle = isAutoHeight ? 
            'padding: 15px; box-sizing: border-box; display: flex; flex-direction: column;' :
            'padding: 15px; height: 100%; box-sizing: border-box; display: flex; flex-direction: column;';
        
        var html = '<div class="sexrel-ad-content" style="' + contentStyle + '">';
        
        if (config.showImage && adData.image) {
            var imageHeight = isAutoHeight ? 'auto' : '100px';
            html += `<div style="text-align: center; margin-bottom: 10px; flex-shrink: 0;">
                        <a href="${adData.link}" target="_blank" rel="noopener" style="display: block;">
                    <img src="${adData.image}" alt="${adData.title}" 
                         style="max-width: 100%; height: auto; border-radius: 4px; max-height: ${imageHeight}; object-fit: cover;">
                </a>
                     </div>`;
        }
        
        if (config.showTitle && adData.title) {
            html += `<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold; line-height: 1.2; flex-shrink: 0;">
                        <a href="${adData.link}" target="_blank" rel="noopener" 
                           style="color: ${config.theme === 'dark' ? '#4CAF50' : '#2196F3'}; text-decoration: none;">
                           ${escapeHtml(adData.title)}
                        </a>
                     </h3>`;
        }
        
        if (config.showDescription && adData.description) {
            html += `<p style="margin: 0; font-size: 14px; line-height: 1.4; color: ${config.theme === 'dark' ? '#ccc' : '#666'}; ${isAutoHeight ? '' : 'flex-grow: 1;'}">
                        ${escapeHtml(adData.description)}
                     </p>`;
        }
        
        html += '</div>';
        
        // Добавляем кнопку закрытия для фиксированных блоков
        var isFixed = (config.position && 
                      (config.position === 'fixed' || 
                       config.position.indexOf('top-') === 0 || 
                       config.position.indexOf('bottom-') === 0));
        
        if (isFixed) {
            html += `<div style="position: absolute; top: 5px; right: 5px; cursor: pointer; font-size: 18px; line-height: 1; color: #999; z-index: 1;"
                          onclick="this.parentElement.style.display='none';" title="Закрыть">×</div>`;
        }
        
				           // Добавляем ссылку на источник
        html += `<div style="position: absolute; bottom: 5px; right: 5px; font-size: 10px; opacity: 0.7;">
                    <a href="https://sexandrelationships.ru" target="_blank" style="color: inherit; text-decoration: none;">
                        Powered by SexAndRelationships©
                    </a>
                 </div>`;
		
        container.innerHTML = html;
		
		
        // Добавляем обработчик клика для аналитики
        container.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.closest('a')) {
                log('Ad clicked', adData.id);
                sendAnalytics('click', adData.id, config);
            }
        });
        
        // Отправляем статистику показа
        sendAnalytics('impression', adData.id, config);
        
        log('Ad rendered successfully');
    }
    
    // Экранирование HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Отправка аналитики
    function sendAnalytics(action, adId, config) {
        if (!config.apiUrl) return;
        
        log('Sending analytics: ' + action + ' for ad ' + adId);
        
        var analyticsUrl = config.apiUrl.replace('/ads', '/analytics');
        var xhr = new XMLHttpRequest();
        
        xhr.open('POST', analyticsUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.timeout = 5000;
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    log('Analytics sent successfully');
                } else {
                    log('Analytics failed with status: ' + xhr.status);
                }
            }
        };
        
        xhr.ontimeout = function() {
            log('Analytics timeout');
        };
        
        xhr.onerror = function() {
            log('Analytics error');
        };
        
        try {
            xhr.send(JSON.stringify({
                action: action,
                ad_id: adId,
                referrer: document.referrer,
                url: window.location.href,
                user_agent: navigator.userAgent,
                timestamp: new Date().toISOString(),
                site_id: config.siteId // Добавлен site_id
            }));
        } catch (e) {
            log('Analytics send error: ' + e.message);
        }
    }
    
    // Автоматическая ротация рекламы
    function setupAutoRotation(container, adsData, config) {
        if (!config.autoRotate || !adsData || adsData.length <= 1) {
            log('Auto rotation disabled or insufficient ads');
            return;
        }
        
        log('Setting up auto rotation with ' + adsData.length + ' ads, interval: ' + config.rotateInterval + 'ms');
        
        var currentIndex = 0;
        
        var rotationInterval = setInterval(function() {
            currentIndex = (currentIndex + 1) % adsData.length;
            log('Rotating to ad index: ' + currentIndex);
            
            // Проверяем, что контейнер все еще существует в DOM
            if (!document.body.contains(container)) {
                log('Container removed from DOM, stopping rotation');
                clearInterval(rotationInterval);
                return;
            }
            
            // Рендерим следующую рекламу
            renderAd(container, adsData[currentIndex], config);
        }, parseInt(config.rotateInterval));
        
        // Сохраняем ссылку на интервал для возможной очистки
        container._rotationInterval = rotationInterval;
        
        log('Auto rotation started');
        return rotationInterval;
    }
    
    // Обработка ошибок загрузки
    function handleLoadError(container, error, config) {
        log('Handling load error: ' + error);
        
        var errorHtml = `
            <div style="padding: 15px; text-align: center; color: #999; font-size: 14px;">
                <div style="margin-bottom: 10px;">⚠️</div>
                <div>Реклама временно недоступна</div>
                ${config.debug ? '<div style="font-size: 12px; margin-top: 5px; color: #666;">(' + error + ')</div>' : ''}
            </div>
        `;
        
        container.innerHTML = errorHtml;
    }
    
    // Инициализация
    function init() {
        var config = getConfig();
        
        log('=== AD SCRIPT INITIALIZATION ===');
        log('Config', config);
        log('Current script', currentScript);
        
        var container = createAdContainer(config);
        
        // Определяем способ вставки
        var isFixed = (config.position && 
                      (config.position === 'fixed' || 
                       config.position.indexOf('top-') === 0 || 
                       config.position.indexOf('bottom-') === 0));
        
        if (isFixed) {
            log('Using fixed positioning');
            document.body.appendChild(container);
        } else {
            log('Using inline positioning');
            
            if (currentScript && currentScript.parentNode) {
                // Создаем placeholder сразу после скрипта
                var placeholder = document.createElement('div');
                placeholder.style.cssText = 'width: 100%; margin: 0; padding: 0;';
                
                // Вставляем placeholder
                if (currentScript.nextSibling) {
                    currentScript.parentNode.insertBefore(placeholder, currentScript.nextSibling);
                } else {
                    currentScript.parentNode.appendChild(placeholder);
                }
                
                // Вставляем контейнер в placeholder
                placeholder.appendChild(container);
                
                log('Container inserted inline via placeholder');
            } else {
                log('Fallback: appending to body');
                document.body.appendChild(container);
            }
        }
        
        // Показываем индикатор загрузки
        container.innerHTML = '<div style="padding: 15px; text-align: center; color: #999; font-size: 14px;">Загрузка рекламы...</div>';
        
        // Загружаем и отображаем рекламу
        loadAdData(config, function(error, data) {
            if (error) {
                handleLoadError(container, error, config);
                return;
            }
            
            if (data && data.length > 0) {
                log('Rendering first ad from ' + data.length + ' available');
                renderAd(container, data[0], config);
                
                // Настраиваем автоматическую ротацию
                setupAutoRotation(container, data, config);
            } else {
                handleLoadError(container, 'Нет доступных рекламных материалов', config);
            }
        });
        
        log('=== INITIALIZATION COMPLETE ===');
    }
    
    // Запускаем инициализацию немедленно
    init();
})();
