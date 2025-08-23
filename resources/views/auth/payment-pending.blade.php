@extends('layouts.app')

@section('styles')
<style>
    body {
        font-family: 'Nunito', sans-serif;
        background-color: #fafafa;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }
    
    .pending-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }
    
    .pending-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 3rem;
        max-width: 600px;
        width: 100%;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .pending-icon {
        font-size: 3rem;
        color: #666;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .pending-title {
        color: #333;
        font-size: 1.75rem;
        font-weight: 600;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .pending-subtitle {
        color: #666;
        font-size: 1rem;
        margin-bottom: 2rem;
        text-align: center;
        line-height: 1.6;
    }
    
    .status-badge {
        background-color: #f5f5f5;
        color: #666;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        font-weight: 500;
        display: inline-block;
        margin-bottom: 2rem;
        font-size: 0.9rem;
        border: 1px solid #e0e0e0;
    }
    
    .info-section {
        background: #f9f9f9;
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        padding: 2rem;
        margin: 2rem 0;
    }
    
    .info-section h5 {
        color: #333;
        margin-bottom: 1.5rem;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .info-list li {
        padding: 0.75rem 0;
        font-size: 0.95rem;
        color: #555;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .info-list li:last-child {
        border-bottom: none;
    }
    
    .info-list i {
        color: #888;
        margin-right: 0.75rem;
        width: 20px;
        text-align: center;
    }
    
    .contact-info {
        background: #f9f9f9;
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        padding: 1.5rem;
        margin-top: 2rem;
    }
    
    .contact-info h5 {
        color: #333;
        margin-bottom: 1rem;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .contact-info p {
        margin: 0.5rem 0;
        color: #555;
        font-size: 0.9rem;
    }
    
    .contact-info i {
        color: #888;
        margin-right: 0.5rem;
        width: 16px;
        text-align: center;
    }
    
    .btn-logout {
        background-color: #f5f5f5;
        color: #666;
        border: 1px solid #ddd;
        padding: 10px 24px;
        border-radius: 4px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-top: 2rem;
        font-size: 0.9rem;
    }
    
    .btn-logout:hover {
        background-color: #eee;
        color: #555;
        border-color: #ccc;
    }
    
    .text-center {
        text-align: center;
    }
    
    .d-flex {
        display: flex;
    }
    
    .justify-content-center {
        justify-content: center;
    }
    
    .d-inline {
        display: inline;
    }
    
    @media (max-width: 768px) {
        .pending-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }
        
        .pending-title {
            font-size: 1.5rem;
        }
        
        .pending-icon {
            font-size: 2.5rem;
        }
    }
</style>
@endsection

@section('content')
<div class="pending-container">
    <div class="pending-card">
        <div class="pending-icon">
            <i class="fas fa-clock"></i>
        </div>
        
        <h1 class="pending-title">
            Профиль на модерации
        </h1>
        
        <div class="text-center">
            <span class="status-badge">
                <i class="fas fa-hourglass-half"></i>
                Ожидает активации
            </span>
        </div>
        
        <p class="pending-subtitle">
            Спасибо за регистрацию, <strong>{{ auth()->user()->name }}</strong>.<br>
            Ваш профиль отправлен на проверку и ожидает активации.
        </p>
        
        <div class="info-section">
            <h5>Что происходит дальше:</h5>
            <ul class="info-list">
                <li>
                    <i class="fas fa-phone"></i>
                    Наш менеджер свяжется с вами в течение 24 часов
                </li>
                <li>
                    <i class="fas fa-handshake"></i>
                    Обсудим условия сотрудничества и тарифы
                </li>
                <li>
                    <i class="fas fa-check"></i>
                    После оплаты активируем ваш аккаунт
                </li>
            </ul>
        </div>
        
        <div class="contact-info">
            <h5>
                <i class="fas fa-envelope"></i>
                Контактная информация
            </h5>
            <p>
                <i class="fas fa-envelope"></i>
                Email: support@example.com
            </p>
            <p>
                <i class="fas fa-phone"></i>
                Телефон: +7 (xxx) xxx-xx-xx
            </p>
            <p>
                <i class="fas fa-clock"></i>
                Время работы: пн-пт 9:00-18:00
            </p>
        </div>

        <div class="text-center">
            <form action="{{ route('auth.logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Выйти
                </button>
            </form>
        </div>
        
        @if(config('app.debug'))
        <!-- Отладочная информация (только в режиме разработки) -->
        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.8rem; color: #6c757d;">
            <strong>Отладочная информация:</strong><br>
            User ID: {{ auth()->user()->id }}<br>
            Email: {{ auth()->user()->email }}<br>
            Статус оплаты: {{ auth()->user()->is_paid ? 'Оплачено (1)' : 'Не оплачено (0)' }}<br>
            Есть компания: {{ auth()->user()->company ? 'Да (' . auth()->user()->company->name . ')' : 'Нет' }}<br>
            <small>Для активации выполните: UPDATE users SET is_paid = 1 WHERE id = {{ auth()->user()->id }};</small>
        </div>
        @endif
    </div>
</div>
@endsection
