<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, maximum-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Тест мобильного интерфейса - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 16px;
            /* Предотвращает зум на iOS */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .test-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .test-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .test-element {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }

        .test-element:hover {
            background: rgba(0, 123, 255, 0.1);
            border-color: #007bff;
        }

        .test-element:active {
            transform: scale(0.98);
        }

        .test-element.long-press-active {
            transform: scale(1.05);
            background-color: #e3f2fd !important;
            border-color: #007bff !important;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .test-element.owner-view::after {
            content: "📱";
            position: absolute;
            bottom: 5px;
            right: 5px;
            font-size: 12px;
            opacity: 0.6;
        }

        .feedback {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }

        .device-info {
            font-size: 14px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .log-area {
            background: #343a40;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }

        @media (hover: none) and (pointer: coarse) {
            .mobile-hint {
                display: block !important;
                font-size: 14px;
                color: #6c757d;
                text-align: center;
                margin: 10px 0;
                font-style: italic;
            }
        }

        .mobile-hint {
            display: none;
        }
    </style>
</head>

<body>
    <div class="test-container">
        <div class="test-card">
            <h2 class="text-center mb-4">🧪 Тест мобильного интерфейса</h2>

            <div class="device-info">
                <h5>📱 Информация об устройстве:</h5>
                <div id="device-details"></div>
            </div>

            <div class="mobile-hint">
                💡 Совет: Для тестирования долгого нажатия нажмите и удерживайте элементы ниже
            </div>
        </div>

        <div class="test-card">
            <h4>📅 Тест календарных дней (имитация)</h4>
            <p class="text-muted">Протестируйте долгое нажатие на этих элементах:</p>

            <div class="row">
                <div class="col-3">
                    <div class="test-element owner-view" data-test="day1">
                        <strong>15</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="test-element owner-view" data-test="day2">
                        <strong>16</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="test-element owner-view" data-test="day3">
                        <strong>17</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="test-element owner-view" data-test="day4">
                        <strong>18</strong>
                    </div>
                </div>
            </div>

            <div class="feedback" id="calendar-feedback"></div>
        </div>

        <div class="test-card">
            <h4>🎯 Тест различных событий</h4>

            <div class="test-element" id="click-test" style="background: #fff3cd; border-color: #ffeaa7;">
                <i class="fas fa-mouse-pointer me-2"></i> Обычный клик
            </div>

            <div class="test-element owner-view" id="right-click-test"
                style="background: #d1ecf1; border-color: #bee5eb;">
                <i class="fas fa-hand-pointer me-2"></i> Правый клик / Долгое нажатие
            </div>

            <div class="test-element" id="double-click-test" style="background: #f8d7da; border-color: #f5c6cb;">
                <i class="fas fa-hand-paper me-2"></i> Двойной клик
            </div>

            <div class="feedback" id="events-feedback"></div>
        </div>

        <div class="test-card">
            <h4>📊 Лог событий</h4>
            <button class="btn btn-sm btn-secondary" onclick="clearLog()">Очистить лог</button>
            <div class="log-area" id="event-log"></div>
        </div>

        <div class="test-card">
            <h4>🔗 Навигация</h4>
            <a href="{{ route('welcome') }}" class="btn btn-primary">← Назад на главную</a>
            @if (request()->has('company'))
                <a href="{{ route('company.show', request('company')) }}" class="btn btn-success">Перейти к календарю
                    компании</a>
            @endif
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Утилиты для работы с мобильными устройствами (копия из основного приложения)
        const MobileUtils = {
            isMobile() {
                return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            },

            isIOS() {
                return /iPad|iPhone|iPod/.test(navigator.userAgent);
            },

            isTouchDevice() {
                return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
            },

            createLongPressHandler(element, callback, delay = 500) {
                let pressTimer = null;
                let isLongPress = false;
                let startX = 0;
                let startY = 0;

                const start = (e) => {
                    const coord = e.touches ? e.touches[0] : e;
                    startX = coord.clientX;
                    startY = coord.clientY;
                    isLongPress = false;

                    element.style.userSelect = 'none';
                    element.style.webkitUserSelect = 'none';
                    element.style.msUserSelect = 'none';

                    pressTimer = setTimeout(() => {
                        isLongPress = true;

                        if (navigator.vibrate) {
                            navigator.vibrate(50);
                        }

                        element.classList.add('long-press-active');

                        callback(e);

                        setTimeout(() => {
                            element.classList.remove('long-press-active');
                        }, 150);
                    }, delay);

                    if (e.type === 'touchstart') {
                        e.preventDefault();
                    }
                };

                const move = (e) => {
                    const coord = e.touches ? e.touches[0] : e;
                    const moveX = Math.abs(coord.clientX - startX);
                    const moveY = Math.abs(coord.clientY - startY);

                    if (moveX > 10 || moveY > 10) {
                        if (pressTimer) {
                            clearTimeout(pressTimer);
                            pressTimer = null;
                        }
                        this.restoreUserSelect(element);
                    }
                };

                const end = (e) => {
                    if (pressTimer) {
                        clearTimeout(pressTimer);
                        pressTimer = null;
                    }

                    this.restoreUserSelect(element);

                    if (isLongPress) {
                        e.preventDefault();
                        e.stopPropagation();
                        isLongPress = false;
                        return false;
                    }
                };

                const cancel = (e) => {
                    if (pressTimer) {
                        clearTimeout(pressTimer);
                        pressTimer = null;
                    }
                    this.restoreUserSelect(element);
                    isLongPress = false;
                };

                if (this.isTouchDevice()) {
                    element.addEventListener('touchstart', start, {
                        passive: false
                    });
                    element.addEventListener('touchmove', move, {
                        passive: true
                    });
                    element.addEventListener('touchend', end, {
                        passive: false
                    });
                    element.addEventListener('touchcancel', cancel, {
                        passive: true
                    });
                }

                if (window.PointerEvent) {
                    element.addEventListener('pointerdown', (e) => {
                        if (e.pointerType === 'touch') {
                            start(e);
                        }
                    });
                    element.addEventListener('pointermove', (e) => {
                        if (e.pointerType === 'touch') {
                            move(e);
                        }
                    });
                    element.addEventListener('pointerup', (e) => {
                        if (e.pointerType === 'touch') {
                            end(e);
                        }
                    });
                    element.addEventListener('pointercancel', (e) => {
                        if (e.pointerType === 'touch') {
                            cancel(e);
                        }
                    });
                }

                return {
                    start,
                    move,
                    end,
                    cancel
                };
            },

            restoreUserSelect(element) {
                element.style.userSelect = '';
                element.style.webkitUserSelect = '';
                element.style.msUserSelect = '';
            }
        };

        // Функция логирования
        function log(message) {
            const logArea = document.getElementById('event-log');
            const time = new Date().toLocaleTimeString();
            logArea.innerHTML += `[${time}] ${message}\n`;
            logArea.scrollTop = logArea.scrollHeight;
            console.log(message);
        }

        // Очистка лога
        function clearLog() {
            document.getElementById('event-log').innerHTML = '';
        }

        // Показ обратной связи
        function showFeedback(elementId, message, type = 'success') {
            const feedback = document.getElementById(elementId);
            feedback.innerHTML =
                `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>${message}`;
            feedback.className = `feedback alert alert-${type === 'success' ? 'success' : 'warning'}`;
            feedback.style.display = 'block';

            setTimeout(() => {
                feedback.style.display = 'none';
            }, 3000);
        }

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 Инициализация тестовой страницы');

            // Показываем информацию об устройстве
            const deviceDetails = document.getElementById('device-details');
            deviceDetails.innerHTML = `
                <strong>User Agent:</strong> ${navigator.userAgent}<br>
                <strong>Мобильное устройство:</strong> ${MobileUtils.isMobile() ? '✅ Да' : '❌ Нет'}<br>
                <strong>iOS устройство:</strong> ${MobileUtils.isIOS() ? '✅ Да' : '❌ Нет'}<br>
                <strong>Touch поддержка:</strong> ${MobileUtils.isTouchDevice() ? '✅ Да' : '❌ Нет'}<br>
                <strong>Поддержка вибрации:</strong> ${navigator.vibrate ? '✅ Да' : '❌ Нет'}<br>
                <strong>Pointer Events:</strong> ${window.PointerEvent ? '✅ Да' : '❌ Нет'}<br>
                <strong>Размер экрана:</strong> ${screen.width}x${screen.height}<br>
                <strong>Viewport:</strong> ${window.innerWidth}x${window.innerHeight}
            `;

            // Настраиваем тесты календарных дней
            document.querySelectorAll('[data-test]').forEach(element => {
                const testId = element.getAttribute('data-test');

                element.addEventListener('click', () => {
                    log(`📅 Обычный клик на день ${testId}`);
                    showFeedback('calendar-feedback', `Обычный клик на день ${testId}`);
                });

                element.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    log(`📅 Правый клик на день ${testId}`);
                    showFeedback('calendar-feedback',
                        `Правый клик на день ${testId} - модальное окно должно открыться`);
                });

                element.addEventListener('dblclick', (e) => {
                    e.preventDefault();
                    log(`📅 Двойной клик на день ${testId}`);
                    showFeedback('calendar-feedback',
                        `Двойной клик на день ${testId} - модальное окно должно открыться`);
                });

                if (MobileUtils.isTouchDevice()) {
                    MobileUtils.createLongPressHandler(element, (e) => {
                        log(`📅 Долгое нажатие на день ${testId}`);
                        showFeedback('calendar-feedback',
                            `Долгое нажатие на день ${testId} - модальное окно должно открыться`
                            );
                    }, 500);
                }
            });

            // Настраиваем тесты событий
            document.getElementById('click-test').addEventListener('click', () => {
                log('🖱️ Обычный клик');
                showFeedback('events-feedback', 'Обычный клик сработал!');
            });

            const rightClickTest = document.getElementById('right-click-test');
            rightClickTest.addEventListener('click', () => {
                log('🖱️ Клик на элементе с долгим нажатием');
            });

            rightClickTest.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                log('🖱️ Правый клик');
                showFeedback('events-feedback', 'Правый клик сработал!');
            });

            if (MobileUtils.isTouchDevice()) {
                MobileUtils.createLongPressHandler(rightClickTest, (e) => {
                    log('🖱️ Долгое нажатие');
                    showFeedback('events-feedback', 'Долгое нажатие сработало!');
                }, 500);
            }

            document.getElementById('double-click-test').addEventListener('dblclick', () => {
                log('🖱️ Двойной клик');
                showFeedback('events-feedback', 'Двойной клик сработал!');
            });

            log('✅ Инициализация завершена');
        });
    </script>
</body>

</html>
