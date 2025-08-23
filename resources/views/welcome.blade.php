<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Система бронирования') }}</title>
    
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- PWA manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1976d2">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #fafafa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .hero-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .hero-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .hero-subtitle {
            font-size: 1rem;
            margin-bottom: 2rem;
            color: #666;
            line-height: 1.6;
        }
        
        .auth-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .btn-hero {
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 4px;
            transition: all 0.2s ease;
            border: 1px solid #ddd;
            min-width: 120px;
            text-decoration: none;
        }
        
        .btn-hero-primary {
            background-color: #333;
            color: white;
            border-color: #333;
        }
        
        .btn-hero-primary:hover {
            background-color: #555;
            border-color: #555;
            color: white;
        }
        
        .btn-hero-outline {
            background-color: white;
            color: #333;
            border-color: #ddd;
        }
        
        .btn-hero-outline:hover {
            background-color: #f5f5f5;
            border-color: #ccc;
            color: #333;
        }
        
        .features {
            margin-top: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
        }
        
        .feature-card {
            background: #f9f9f9;
            border: 1px solid #e8e8e8;
            border-radius: 6px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: #666;
        }
        
        .feature-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .feature-description {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }
        
        .modal-content {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .modal-header {
            background-color: #f9f9f9;
            border-bottom: 1px solid #e8e8e8;
            border-radius: 8px 8px 0 0;
        }
        
        .modal-title {
            color: #333;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .form-label {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            border-color: #666;
            box-shadow: 0 0 0 0.2rem rgba(102, 102, 102, 0.25);
        }
        
        .btn-primary {
            background-color: #333;
            border-color: #333;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #555;
            border-color: #555;
        }
        
        .btn-secondary {
            background-color: #f5f5f5;
            border-color: #ddd;
            color: #666;
        }
        
        .btn-secondary:hover {
            background-color: #eee;
            border-color: #ccc;
            color: #555;
        }
        
        .alert-danger {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }
        
        .alert-info {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }
        
        .form-text {
            color: #888;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .hero-content {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .auth-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">
                <i class="fas fa-calendar-check me-2"></i>
                Система Бронирования
            </h1>
            <p class="hero-subtitle">
                Создайте свою онлайн-площадку для записи клиентов.<br>
                Управляйте расписанием, принимайте заявки и развивайте бизнес.
            </p>
            
            <div class="auth-buttons">
                <button type="button" class="btn btn-hero btn-hero-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Войти
                </button>
                <button type="button" class="btn btn-hero btn-hero-outline" data-bs-toggle="modal" data-bs-target="#registerModal">
                    <i class="fas fa-user-plus me-2"></i>
                    Регистрация
                </button>
            </div>
            
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="feature-title">24/7 Доступность</h3>
                    <p class="feature-description">
                        Клиенты могут записываться в любое время
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="feature-title">Мобильная версия</h3>
                    <p class="feature-description">
                        Удобный интерфейс для всех устройств
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3 class="feature-title">Уведомления</h3>
                    <p class="feature-description">
                        Автоматические напоминания и оповещения
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно входа -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">
                        <i class="fas fa-sign-in-alt me-2"></i>Вход в систему
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('auth.login') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if($errors->has('email') && !$errors->has('name'))
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                {{ $errors->first('email') }}
                            </div>
                        @endif
                        
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email
                            </label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="loginEmail" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">
                                <i class="fas fa-lock me-1"></i>Пароль
                            </label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="loginPassword" 
                                   name="password" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Запомнить меня
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Войти
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно регистрации -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Регистрация
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('auth.register') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if($errors->has('name'))
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Пожалуйста, исправьте ошибки в форме регистрации.
                            </div>
                        @endif
                        
                        <div class="mb-3">
                            <label for="registerName" class="form-label">
                                <i class="fas fa-user me-1"></i>Имя
                            </label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="registerName" 
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerEmail" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email
                            </label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="registerEmail" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerPassword" class="form-label">
                                <i class="fas fa-lock me-1"></i>Пароль
                            </label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="registerPassword" 
                                   name="password" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Минимум 6 символов</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerPasswordConfirm" class="form-label">
                                <i class="fas fa-lock me-1"></i>Подтверждение пароля
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="registerPasswordConfirm" 
                                   name="password_confirmation" 
                                   required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                После регистрации ваш профиль будет отправлен на модерацию. 
                                Мы свяжемся с вами для активации аккаунта.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Зарегистрироваться
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Если есть ошибки валидации, показываем соответствующую модалку
            @if($errors->has('name') || ($errors->has('email') && old('name')) || $errors->has('password'))
                var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            @elseif($errors->has('email') && !old('name'))
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            @endif
        });
    </script>
    <!-- PWA Install Banner -->
    <div id="pwa-install-banner" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);padding:16px;">
        <span style="margin-right:12px;">Установите наше приложение для быстрого доступа!</span>
        <button id="pwa-install-btn" class="btn btn-primary btn-sm">Установить</button>
        <button id="pwa-dismiss-btn" class="btn btn-link btn-sm">Позже</button>
    </div>
    <script>
        // Регистрация сервис-воркера
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js');
            });
        }
        // PWA установка
        let deferredPrompt;
        const banner = document.getElementById('pwa-install-banner');
        const installBtn = document.getElementById('pwa-install-btn');
        const dismissBtn = document.getElementById('pwa-dismiss-btn');
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            banner.style.display = 'block';
        });
        installBtn && installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    banner.style.display = 'none';
                }
                deferredPrompt = null;
            }
        });
        dismissBtn && dismissBtn.addEventListener('click', () => {
            banner.style.display = 'none';
        });
    </script>
</body>
</html>
