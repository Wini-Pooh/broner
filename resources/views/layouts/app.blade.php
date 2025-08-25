<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#007bff">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <!-- jQuery Mask Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    

    <!-- Scripts -->
    @vite(['resources/css/app.css','resources/css/company-settings.css','resources/css/calendar-appointments.css', 'resources/js/app.js'])
    
    <!-- CSRF Token Auto-Refresh Script -->
    <script>
    // Глобальная система обновления CSRF токена
    window.CSRFManager = {
        token: '{{ csrf_token() }}',
        lastUpdate: Date.now(),
        isRefreshing: false,
        
        // Проверяет актуальность токена (обновляем каждые 110 минут)
        isTokenExpired() {
            return (Date.now() - this.lastUpdate) > (110 * 60 * 1000);
        },
        
        // Получает новый токен с сервера
        async refreshToken() {
            if (this.isRefreshing) {
                // Ждем завершения текущего обновления
                while (this.isRefreshing) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
                return true;
            }
            
            this.isRefreshing = true;
            
            try {
                const response = await fetch('/csrf-token', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.updateToken(data.token);
                    return true;
                } else {
                    console.warn('Ошибка получения CSRF токена:', response.status);
                }
            } catch (error) {
                console.warn('Не удалось обновить CSRF токен:', error);
            } finally {
                this.isRefreshing = false;
            }
            return false;
        },
        
        // Обновляет токен во всех местах
        updateToken(newToken) {
            this.token = newToken;
            this.lastUpdate = Date.now();
            
            // Обновляем meta тег
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                metaToken.setAttribute('content', newToken);
            }
            
            // Обновляем все скрытые поля _token в формах
            document.querySelectorAll('input[name="_token"]').forEach(input => {
                input.value = newToken;
            });
            
            // Обновляем jQuery AJAX setup если есть
            if (window.jQuery && jQuery.ajaxSetup) {
                jQuery.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': newToken
                    }
                });
            }
            
            console.log('CSRF токен обновлен');
        },
        
        // Проверяет и обновляет токен если нужно
        async ensureValidToken() {
            if (this.isTokenExpired()) {
                await this.refreshToken();
            }
        },
        
        // Обрабатывает ошибку 419
        async handle419Error() {
            console.warn('Получена ошибка 419 - токен просрочен, обновляем...');
            return await this.refreshToken();
        }
    };
    
    // Автоматическое обновление токена перед отправкой любой формы
    document.addEventListener('DOMContentLoaded', function() {
        // Перехватываем все отправки форм
        document.addEventListener('submit', async function(e) {
            const form = e.target;
            
            // Пропускаем формы без CSRF токена
            const tokenInput = form.querySelector('input[name="_token"]');
            if (!tokenInput) return;
            
            // Проверяем и обновляем токен если нужно
            if (window.CSRFManager.isTokenExpired()) {
                e.preventDefault(); // Останавливаем отправку
                
                const refreshed = await window.CSRFManager.refreshToken();
                if (refreshed) {
                    // Перезапускаем отправку формы с новым токеном
                    form.submit();
                } else {
                    alert('Не удалось обновить токен безопасности. Попробуйте обновить страницу.');
                }
            }
        });
        
        // Перехватываем AJAX запросы (для jQuery)
        if (window.jQuery) {
            // Перед отправкой проверяем токен
            $(document).ajaxSend(async function(event, xhr, settings) {
                if (window.CSRFManager.isTokenExpired()) {
                    await window.CSRFManager.refreshToken();
                    // Обновляем заголовок для текущего запроса
                    xhr.setRequestHeader('X-CSRF-TOKEN', window.CSRFManager.token);
                }
            });
            
            // Обрабатываем ошибки 419
            $(document).ajaxError(async function(event, xhr, settings) {
                if (xhr.status === 419) {
                    const refreshed = await window.CSRFManager.handle419Error();
                    if (refreshed) {
                        // Повторяем запрос с новым токеном
                        $.ajax(settings);
                    } else {
                        console.error('Не удалось обновить CSRF токен после ошибки 419');
                        alert('Произошла ошибка безопасности. Пожалуйста, обновите страницу.');
                    }
                }
            });
        }
        
        // Настраиваем CSRF токен для всех AJAX запросов
        if (window.jQuery) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        }
        
        // Обработка fetch запросов
        const originalFetch = window.fetch;
        window.fetch = async function(url, options = {}) {
            // Проверяем токен перед отправкой POST/PUT/DELETE запросов
            if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
                await window.CSRFManager.ensureValidToken();
                
                // Обновляем заголовки
                options.headers = options.headers || {};
                if (typeof options.headers.append === 'function') {
                    options.headers.append('X-CSRF-TOKEN', window.CSRFManager.token);
                } else {
                    options.headers['X-CSRF-TOKEN'] = window.CSRFManager.token;
                }
            }
            
            try {
                const response = await originalFetch(url, options);
                
                // Если получили 419, обновляем токен и повторяем запрос
                if (response.status === 419) {
                    const refreshed = await window.CSRFManager.handle419Error();
                    if (refreshed) {
                        // Обновляем заголовки и повторяем запрос
                        if (options.headers && typeof options.headers.set === 'function') {
                            options.headers.set('X-CSRF-TOKEN', window.CSRFManager.token);
                        } else if (options.headers) {
                            options.headers['X-CSRF-TOKEN'] = window.CSRFManager.token;
                        }
                        return await originalFetch(url, options);
                    }
                }
                
                return response;
            } catch (error) {
                throw error;
            }
        };
        
        // Периодическая проверка токена (каждые 100 минут)
        setInterval(function() {
            window.CSRFManager.ensureValidToken();
        }, 100 * 60 * 1000);
    });
    </script>
    
    <!-- Дополнительные стили -->
    @yield('styles')
</head>
<body>
    <div id="app">
      
        <main class="py-4">
            @yield('content')
        </main>
    </div>
    
    <!-- Дополнительные скрипты -->
    @yield('scripts')
</body>
</html>
