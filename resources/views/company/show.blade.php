@extends('layouts.app')
@section('styles')
<style>
.service-selection-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.service-selection-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-color: #007bff;
}

.service-selection-card.selected {
    border-color: #28a745;
    background-color: #f8fff9;
}

.service-selection-card.selected .service-select-btn {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.appointment-step {
    min-height: 300px;
}

.appointment-summary {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
}

.appointment-summary .row {
    border-bottom: 1px solid #e9ecef;
    padding: 0.5rem 0;
}

.appointment-summary .row:last-child {
    border-bottom: none;
}
    border: 1px solid #ffeaa7;
    border-radius: 0.375rem;
    padding: 0.5rem;
    text-align: center;
    font-size: 0.875rem;
}

.time-content.blocked .unavailable-slot i {
    margin-right: 0.25rem;
    opacity: 0.7;
}

.time-content.insufficient-time {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 0.375rem;
    padding: 0.5rem;
    text-align: center;
    font-size: 0.875rem;
}

.time-content.insufficient-time .unavailable-slot {
    color: #856404;
}

.time-content.insufficient-time .unavailable-slot i {
    margin-right: 0.25rem;
    opacity: 0.7;
}

/* Стили для владельца компании */
.calendar-day.owner-past-date {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    cursor: pointer;
    position: relative;
}

.calendar-day.owner-past-date:hover {
    background-color: #ffeeba;
    border-color: #ffd700;
}

.calendar-day.owner-past-date::after {
    content: "👑";
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 10px;
    opacity: 0.7;
}

.calendar-day.owner-restricted {
    background-color: #e2e3e5;
    border: 1px solid #c6c8ca;
    color: #6c757d;
    cursor: pointer;
    position: relative;
}

.calendar-day.owner-restricted:hover {
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.calendar-day.owner-restricted::after {
    content: "👑";
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 10px;
    opacity: 0.7;
}

/* Стили для прошедших временных слотов владельца */
.time-content.owner-past .owner-past-slot {
    background-color: #fff3cd;
    border: 2px dashed #ffc107;
    color: #856404;
    padding: 8px 12px;
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 500;
}

.time-content.owner-past .owner-past-slot:hover {
    background-color: #ffeeba;
    border-color: #ffd700;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
}

.time-content.owner-past .owner-past-slot i {
    margin-right: 6px;
    color: #ffc107;
}

/* Стили для выходных дней владельца */
.calendar-day.owner-weekend {
    background-color: #e8f4f8;
    border: 1px solid #bee5eb;
    color: #0c5460;
    cursor: pointer;
    position: relative;
}

.calendar-day.owner-weekend:hover {
    background-color: #d1ecf1;
    border-color: #b3d7dd;
}

.calendar-day.owner-weekend::after {
    content: "👑";
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 10px;
    opacity: 0.7;
}

/* Стили для праздников владельца */
.calendar-day.owner-holiday {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    cursor: pointer;
    position: relative;
}

.calendar-day.owner-holiday:hover {
    background-color: #f5c6cb;
    border-color: #f1b0b7;
}

.calendar-day.owner-holiday::after {
    content: "👑";
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 10px;
    opacity: 0.7;
}

/* Упрощенные стили для слотов владельца */
.time-content.owner-flexible {
    transition: all 0.3s ease;
}

.time-content.owner-available .available-slot {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.time-content.owner-past-available .available-slot {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.time-content.owner-weekend-available .available-slot {
    background-color: #e8f4f8;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.time-content.owner-holiday-available .available-slot {
    background-color: #fce4ec;
    border: 1px solid #f8bbd9;
    color: #880e4f;
}

.time-content.owner-flexible:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.time-content.owner-flexible .available-slot {
    padding: 8px 12px;
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
}

.time-content.owner-flexible .available-slot i {
    margin-right: 6px;
}

/* Стили для дней с исключениями календаря */
.calendar-day.has-exception {
    position: relative;
}

.calendar-day.has-exception::before {
    content: "";
    position: absolute;
    top: 3px;
    left: 3px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    z-index: 1;
}

.calendar-day.exception-allow::before {
    background-color: #28a745;
    box-shadow: 0 0 0 1px white;
}

.calendar-day.exception-block::before {
    background-color: #dc3545;
    box-shadow: 0 0 0 1px white;
}

.calendar-day.owner-view {
    cursor: pointer;
}

.calendar-day.owner-view:hover {
    background-color: rgba(0, 123, 255, 0.1);
    border-color: #007bff;
}

/* Контекстное меню для владельца */
.calendar-day-menu {
    position: absolute;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 8px 0;
    z-index: 1000;
    min-width: 200px;
}

.calendar-day-menu-item {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    cursor: pointer;
    transition: background-color 0.2s;
    font-size: 0.875rem;
}

.calendar-day-menu-item:hover {
    background-color: #f8f9fa;
}

.calendar-day-menu-item i {
    width: 16px;
    margin-right: 8px;
    text-align: center;
}

.calendar-day-menu-separator {
    height: 1px;
    background-color: #dee2e6;
    margin: 4px 0;
}

/* Стили для мобильных устройств и touch интерфейса */
@media (hover: none) and (pointer: coarse) {
    /* Для touch устройств */
    .calendar-day.owner-view {
        -webkit-tap-highlight-color: rgba(0, 123, 255, 0.1);
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        position: relative;
    }
    
    .calendar-day.owner-view::after {
        content: "📱";
        position: absolute;
        bottom: 2px;
        right: 2px;
        font-size: 8px;
        opacity: 0.6;
        pointer-events: none;
    }
    
    /* Визуальная обратная связь при долгом нажатии */
    .calendar-day.owner-view.long-press-active {
        transform: scale(1.05);
        background-color: #e3f2fd !important;
        border-color: #007bff !important;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
    }
    
    /* Запрещаем выделение текста для всех календарных дней */
    .calendar-day {
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    /* Улучшаем читаемость на мобильных */
    .calendar-day {
        line-height: 1.4;
        padding: 8px 4px;
    }
    
    /* Увеличиваем области нажатия для кнопок */
    .btn {
        min-height: 44px;
        padding: 12px 16px;
    }
}

/* Дополнительные стили для iOS Safari и WebKit браузеров */
.calendar-day.owner-view {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
}

/* Убираем стандартную подсветку iOS */
.calendar-day.owner-view * {
    -webkit-tap-highlight-color: transparent;
}

/* Стили для устройств с touch поддержкой */
@media (pointer: coarse) {
    .calendar-day.owner-view {
        min-height: 44px; /* Минимальный размер для удобного нажатия */
        min-width: 44px;
    }
    
    /* Увеличиваем размер для лучшей доступности */
    .calendar-day {
        font-size: 16px; /* Предотвращает зум на iOS */
        line-height: 1.2;
    }
}

/* Анимации и переходы для лучшего UX */
.calendar-day.owner-view {
    transition: all 0.15s ease;
}

.calendar-day.owner-view:active {
    transform: scale(0.98);
}

/* Инструкция для пользователя (показывается только на touch устройствах) */
@media (hover: none) and (pointer: coarse) {
    .calendar-header::after {
        content: "💡 Совет: Для управления днем нажмите и удерживайте дату";
        display: block;
        font-size: 0.75rem;
        color: #6c757d;
        text-align: center;
        margin-top: 0.5rem;
        font-style: italic;
    }
}
</style>
@endsection
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ e(session('success')) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <!-- Профиль компании -->
            <div class="company-profile">
                <div class="d-flex align-items-center">
                    @if($company->avatar)
                        <img src="{{ e($company->avatar) }}" 
                             alt="Аватар компании {{ e($company->name) }}" 
                             class="company-avatar me-3">
                    @else
                        <div class="company-avatar-placeholder me-3 d-flex align-items-center justify-content-center">
                            {{ strtoupper(substr(e($company->name), 0, 2)) }}
                        </div>
                    @endif
                    
                    <div class="company-info flex-grow-1">
                        <h2 class="company-name">{{ e($company->name) }}</h2>
                        
                        @if(Auth::check() && $company->user_id === Auth::id())
                        <div class="mt-2">
                            <a href="{{ route('company.edit', $company->slug) }}" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <a href="{{ route('company.settings', $company->slug) }}" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-cog"></i> Настройки
                            </a>
                            <a href="{{ route('company.services.create', $company->slug) }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-plus"></i> Услуги
                            </a>
                            <a href=""> <form action="{{ route('auth.logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Выйти
                </button>
            </form></a>
                        </div>
                        
                        @endif
                        
                    </div>
                </div>
                
                @if($company->description)
                <div class="company-description mt-3">
                    <p class="mb-0">{{ e($company->description) }}</p>
                </div>
                @endif
            </div>

            <!-- Календарь для записи -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Выберите дату и время</h4>
                </div>
                <div class="card-body">
                
                    @if($isOwner)
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-crown me-2"></i>
                        <strong>Режим владельца</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="btn btn-outline-primary btn-sm" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h5 class="calendar-title mb-0" id="calendarTitle"></h5>
                            <button class="btn btn-outline-primary btn-sm" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="calendar-grid">
                            <div class="calendar-days-header">
                                <div class="calendar-day-name">Пн</div>
                                <div class="calendar-day-name">Вт</div>
                                <div class="calendar-day-name">Ср</div>
                                <div class="calendar-day-name">Чт</div>
                                <div class="calendar-day-name">Пт</div>
                                <div class="calendar-day-name weekend-header">Сб</div>
                                <div class="calendar-day-name weekend-header">Вс</div>
                            </div>
                            <div class="calendar-days" id="calendarDays">
                                <!-- Дни календаря будут генерироваться JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Детализированный вид дня -->
            <div class="card shadow mt-4" id="dayViewContainer" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0" id="dayViewTitle">Расписание на день</h5>
                    <button class="btn btn-outline-secondary btn-sm" id="closeDayView">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="card-body p-3">
                    @if($isOwner)
                    <div class="day-settings-info mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Рабочее время: {{ e($calendarSettings['work_start_time'] ?? '09:00') }} - {{ e($calendarSettings['work_end_time'] ?? '18:00') }}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    @if(!empty($calendarSettings['break_times']))
                                        <i class="fas fa-coffee"></i> Перерыв: {{ e($calendarSettings['break_times'][0]['start'] ?? '') }} - {{ e($calendarSettings['break_times'][0]['end'] ?? '') }}
                                    @else
                                        <i class="fas fa-coffee"></i> Перерыв не установлен
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="day-schedule-container">
                        <div class="time-slots" id="timeSlots">
                            <!-- Временные слоты будут генерироваться JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для записи -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Запись на прием</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="appointmentForm" action="{{ route('company.appointments.create', $company->slug) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Шаг 1: Основная информация -->
                    <div id="step1" class="appointment-step">
                        <!-- Скрытое поле для даты -->
                        <input type="hidden" id="modal_appointment_date" name="appointment_date">
                        
                        <!-- Показываем выбранную дату пользователю -->
                        <div class="mb-3">
                            <label class="form-label">Выбранная дата</label>
                            <div class="form-control-plaintext" id="selected_date_display">-</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_appointment_time" class="form-label">Время</label>
                            <input type="time" class="form-control" id="modal_appointment_time" name="appointment_time" required readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_client_name" class="form-label">Ваше имя</label>
                            <input type="text" class="form-control" id="modal_client_name" name="client_name" required maxlength="50">
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_client_phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="modal_client_phone" name="client_phone" placeholder="+7 (999) 123-45-67" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_notes" class="form-label">Комментарий</label>
                            <textarea class="form-control" id="modal_notes" name="notes" rows="3" maxlength="500"></textarea>
                            <div class="form-text">Осталось символов: <span id="notesCounter">500</span></div>
                        </div>
                    </div>

                    <!-- Шаг 2: Выбор услуги -->
                    <div id="step2" class="appointment-step" style="display: none;">
                        <h6 class="mb-3">Выберите услугу:</h6>
                        
                        <!-- Скрытое поле для ID услуги -->
                        <input type="hidden" id="modal_service_id" name="service_id">
                        
                        <div class="row g-3" id="servicesContainer">
                            @foreach($services as $service)
                            <div class="col-md-6 col-lg-4">
                                <div class="card service-selection-card h-100" 
                                     data-service-id="{{ $service->id }}" 
                                     data-service-name="{{ $service->name }}"
                                     data-service-duration="{{ $service->duration_minutes }}"
                                     data-service-price="{{ $service->price }}">
                                    @if($service->photo)
                                        <img src="{{ asset('storage/' . $service->photo) }}" 
                                             class="card-img-top" 
                                             alt="Фото услуги" 
                                             style="height: 120px; object-fit: cover;">
                                    @else
                                        <div class="card-img-top d-flex align-items-center justify-content-center bg-light" 
                                             style="height: 120px;">
                                            <span class="fa fa-briefcase fa-2x text-muted"></span>
                                        </div>
                                    @endif
                                    <div class="card-body p-3">
                                        <h6 class="card-title">{{ $service->name }}</h6>
                                        @if($service->description)
                                            <p class="card-text small text-muted">{{ Str::limit($service->description, 80) }}</p>
                                        @endif
                                        <p class="card-text mb-1">
                                            <strong class="text-primary">{{ $service->formatted_price }}</strong>
                                        </p>
                                        <p class="card-text small text-muted">
                                            <i class="fas fa-clock"></i> {{ $service->formatted_duration }}
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent text-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm service-select-btn">
                                            <i class="fas fa-check"></i> Выбрать
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        @if($services->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> У компании пока нет доступных услуг.
                        </div>
                        @endif
                    </div>

                    <!-- Шаг 3: Подтверждение -->
                    <div id="step3" class="appointment-step" style="display: none;">
                        <h6 class="mb-3">Подтверждение записи:</h6>
                        
                        <div class="appointment-summary">
                            <div class="row mb-2">
                                <div class="col-4"><strong>Дата:</strong></div>
                                <div class="col-8" id="summary_date">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4"><strong>Время:</strong></div>
                                <div class="col-8" id="summary_time">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4"><strong>Услуга:</strong></div>
                                <div class="col-8" id="summary_service">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4"><strong>Стоимость:</strong></div>
                                <div class="col-8" id="summary_price">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4"><strong>Длительность:</strong></div>
                                <div class="col-8" id="summary_duration">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4"><strong>Клиент:</strong></div>
                                <div class="col-8" id="summary_client">-</div>
                            </div>
                            <div class="row mb-2" id="summary_notes_row" style="display: none;">
                                <div class="col-4"><strong>Комментарий:</strong></div>
                                <div class="col-8" id="summary_notes">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-outline-secondary" id="prevStepBtn" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Назад
                    </button>
                    <button type="button" class="btn btn-primary" id="nextStepBtn">
                        Далее <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                        <i class="fas fa-check"></i> Записаться
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования записи (только для владельца) -->
@if($isOwner)
<div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAppointmentModalLabel">Управление записью</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Информация о записи -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Информация о клиенте</h6>
                        <p class="mb-1"><strong>Имя:</strong> <span id="edit_client_name">-</span></p>
                        <p class="mb-1"><strong>Телефон:</strong> <span id="edit_client_phone">-</span></p>
                        <p class="mb-1"><strong>Статус:</strong> <span id="edit_status" class="badge">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Детали записи</h6>
                        <p class="mb-1"><strong>Дата:</strong> <span id="edit_appointment_date">-</span></p>
                        <p class="mb-1"><strong>Время:</strong> <span id="edit_appointment_time">-</span></p>
                        <p class="mb-1"><strong>Создана:</strong> <span id="edit_created_at">-</span></p>
                    </div>
                </div>

                <!-- Комментарий клиента -->
                <div class="mb-4" id="client_notes_section" style="display: none;">
                    <h6 class="text-muted mb-2">Комментарий клиента</h6>
                    <div class="alert alert-light" id="edit_client_notes">-</div>
                </div>

                <!-- Форма редактирования -->
                <form id="editAppointmentForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_appointment_id" name="appointment_id">
                    
                    <!-- Изменение даты и времени -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Изменить дату и время</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_new_date" class="form-label">Новая дата</label>
                                    <input type="date" class="form-control" id="edit_new_date" name="new_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_new_time" class="form-label">Новое время</label>
                                    <select class="form-select" id="edit_new_time" name="new_time">
                                        <option value="">Выберите время</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Изменение контактных данных клиента -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Контактная информация клиента</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_client_name_field" class="form-label">Имя клиента</label>
                                    <input type="text" class="form-control" id="edit_client_name_field" name="client_name" maxlength="50">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_client_phone_field" class="form-label">Телефон клиента</label>
                                    <input type="tel" class="form-control" id="edit_client_phone_field" name="client_phone" placeholder="+7 (999) 123-45-67">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Добавление заметок владельца -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Заметки владельца</h6>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" id="edit_owner_notes" name="owner_notes" rows="3" placeholder="Добавить внутренние заметки..." maxlength="500"></textarea>
                            <div class="form-text">Осталось символов: <span id="ownerNotesCounter">500</span></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left"></i> Обратно
                </button>
                <button type="button" class="btn btn-success" id="updateBtn">
                    <i class="fas fa-save"></i> Обновить
                </button>
                <button type="button" class="btn btn-danger" id="cancelAppointmentBtn">
                    <i class="fas fa-times"></i> Отменить бронь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для управления исключениями календаря (только для владельца) -->
<div class="modal fade" id="dateExceptionModal" tabindex="-1" aria-labelledby="dateExceptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dateExceptionModalLabel">
                    <i class="fas fa-calendar-edit"></i> Управление доступностью дня
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form id="dateExceptionForm">
                <div class="modal-body">
                    <div id="exceptionAlert" class="alert" style="display: none;"></div>
                    
                    <div class="mb-3">
                        <label for="exception_date" class="form-label">Дата</label>
                        <input type="date" class="form-control" id="exception_date" name="exception_date" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Тип исключения</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exception_type" id="exception_type_allow" value="allow">
                            <label class="form-check-label" for="exception_type_allow">
                                <i class="fas fa-check-circle text-success"></i> Разрешить работу в этот день
                                <small class="d-block text-muted">Сделать день доступным для записей, даже если он выходной по настройкам</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exception_type" id="exception_type_block" value="block">
                            <label class="form-check-label" for="exception_type_block">
                                <i class="fas fa-ban text-danger"></i> Заблокировать работу в этот день
                                <small class="d-block text-muted">Сделать день недоступным для записей, даже если он рабочий по настройкам</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exception_type" id="exception_type_none" value="">
                            <label class="form-check-label" for="exception_type_none">
                                <i class="fas fa-undo text-secondary"></i> Использовать стандартные настройки
                                <small class="d-block text-muted">Убрать исключение и использовать обычные настройки компании</small>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Время работы для исключения "разрешить" -->
                    <div id="work_time_section" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="work_start_time" class="form-label">Время начала работы</label>
                                <input type="time" class="form-control" id="work_start_time" name="work_start_time">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="work_end_time" class="form-label">Время окончания работы</label>
                                <input type="time" class="form-control" id="work_end_time" name="work_end_time">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exception_reason" class="form-label">Причина исключения (необязательно)</label>
                        <input type="text" class="form-control" id="exception_reason" name="reason" placeholder="Например: Работа в выходной, Отпуск, Больничный..." maxlength="255">
                        <div class="form-text">Максимум 255 символов</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveDateExceptionBtn">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                    <button type="button" class="btn btn-danger" id="deleteDateExceptionBtn" style="display: none;">
                        <i class="fas fa-trash"></i> Удалить исключение
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@include('company.show_script')
@endsection
