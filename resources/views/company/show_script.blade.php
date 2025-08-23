<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== УТИЛИТЫ КАЛЕНДАРЯ (встроено вместо js/calendar-utils.js) =====
    class CalendarUtils {
        constructor(settings = {}, dateExceptions = {}) {
            this.settings = Object.assign({
                work_start_time: '09:00',
                work_end_time: '18:00',
                appointment_interval: 30,
                appointment_days_ahead: 14,
                work_days: ['monday','tuesday','wednesday','thursday','friday'],
                holidays: [], // 'YYYY-MM-DD' или 'MM-DD'
                break_times: [] // [{start:'12:00', end:'13:00'}]
            }, settings || {});
            this.dateExceptions = dateExceptions || {};
        }

        formatDateForServer(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        isWorkDay(date) {
            const weekday = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][date.getDay()];
            return Array.isArray(this.settings.work_days) && this.settings.work_days.includes(weekday);
        }

        isHoliday(date) {
            const ymd = this.formatDateForServer(date);
            const md = ymd.slice(5);
            const list = this.settings.holidays || [];
            return list.some(h => (String(h).length === 10 ? h === ymd : h === md));
        }

        getDateException(date) {
            const dateKey = this.formatDateForServer(date);
            return this.dateExceptions[dateKey] || null;
        }

        isDateAvailable(date, isOwner = false) {
            // Владелец компании может просматривать любые даты без ограничений
            if (isOwner) {
                return true;
            }
            
            const today = new Date();
            today.setHours(0,0,0,0);
            const target = new Date(date);
            target.setHours(0,0,0,0);
            const diffDays = Math.floor((target - today) / (24*60*60*1000));
            if (diffDays < 0) return false;
            if (this.settings.appointment_days_ahead > 0 && diffDays > this.settings.appointment_days_ahead) return false;
            
            // Проверяем исключения календаря
            const exception = this.getDateException(target);
            if (exception) {
                if (exception.exception_type === 'block') {
                    // Дата заблокирована исключением
                    return false;
                } else if (exception.exception_type === 'allow') {
                    // Дата разрешена исключением (даже если это выходной)
                    return true;
                }
            }
            
            if (!this.isWorkDay(target)) return false;
            if (this.isHoliday(target)) return false;
            return true;
        }

        isBreakTime(timeStr) {
            if (!timeStr) return false;
            const toMinutes = t => {
                const [hh, mm] = String(t).split(':');
                return parseInt(hh,10) * 60 + parseInt(mm||'0',10);
            };
            const t = toMinutes(timeStr);
            const breaks = this.settings.break_times || [];
            return breaks.some(b => toMinutes(b.start) <= t && t < toMinutes(b.end));
        }
    }
    // ===== КОНФИГУРАЦИЯ =====
    const config = {
        isOwner: @json($isOwner ?? false),
        calendarSettings: @json($calendarSettings ?? []),
        dateExceptions: @json($dateExceptions ?? []),
        companySlug: '{{ $company->slug }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        months: [
            'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
            'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
        ]
    };

    // ===== СОСТОЯНИЕ ПРИЛОЖЕНИЯ =====
    const state = {
        currentDate: new Date(),
        selectedDate: null,
        currentViewDate: null,
        dayViewVisible: false,
        monthlyStats: {},
        calendarUtils: null
    };

    // ===== DOM ЭЛЕМЕНТЫ =====
    const elements = {
        calendarTitle: document.getElementById('calendarTitle'),
        calendarDays: document.getElementById('calendarDays'),
        prevMonth: document.getElementById('prevMonth'),
        nextMonth: document.getElementById('nextMonth'),
        dayViewContainer: document.getElementById('dayViewContainer'),
        dayViewTitle: document.getElementById('dayViewTitle'),
        closeDayView: document.getElementById('closeDayView'),
        timeSlots: document.getElementById('timeSlots'),
        appointmentForm: document.getElementById('appointmentForm')
    };

    // ===== МОДАЛЬНЫЕ ОКНА =====
    const modals = {
        appointment: new bootstrap.Modal(document.getElementById('appointmentModal')),
        edit: config.isOwner ? new bootstrap.Modal(document.getElementById('editAppointmentModal')) : null
    };

    // ===== ИНИЦИАЛИЗАЦИЯ =====
    function init() {
        // Создаем утилиты календаря
        state.calendarUtils = new CalendarUtils(config.calendarSettings, config.dateExceptions);
        
        // Настраиваем AJAX
        setupAjax();
        
        // Инициализируем обработчики
        initEventHandlers();
        
        // Инициализируем маски ввода
        initInputMasks();
        
        // Инициализируем модальное окно исключений (только для владельца)
        if (config.isOwner) {
            initDateExceptionModal();
        }
        
        // Загружаем начальные данные
        loadMonthlyStats();
    }

    // ===== НАСТРОЙКА AJAX =====
    function setupAjax() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': config.csrfToken
            }
        });
    }

    // ===== ОБРАБОТЧИКИ СОБЫТИЙ =====
    function initEventHandlers() {
        // Навигация по месяцам
        elements.prevMonth?.addEventListener('click', () => {
            state.currentDate.setMonth(state.currentDate.getMonth() - 1);
            loadMonthlyStats();
        });

        elements.nextMonth?.addEventListener('click', () => {
            state.currentDate.setMonth(state.currentDate.getMonth() + 1);
            loadMonthlyStats();
        });

        // Закрытие детального вида
        elements.closeDayView?.addEventListener('click', hideDayView);

        // Форма записи
        elements.appointmentForm?.addEventListener('submit', handleAppointmentSubmit);

        // Счетчики символов
        initCharacterCounters();

        // Инициализация многошагового процесса записи
        initAppointmentSteps();

        // Обработчики для владельца
        if (config.isOwner) {
            initOwnerHandlers();
        }
    }

    // ===== КАЛЕНДАРЬ =====
    function loadMonthlyStats() {
        const year = state.currentDate.getFullYear();
        const month = String(state.currentDate.getMonth() + 1).padStart(2, '0');
        
        fetch(`{{ route('company.monthly-stats', $company->slug) }}?month=${year}-${month}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            state.monthlyStats = data.stats || {};
            renderCalendar();
        })
        .catch(error => {
            console.error('Ошибка загрузки статистики:', error);
            renderCalendar();
        });
    }

    function renderCalendar() {
        const year = state.currentDate.getFullYear();
        const month = state.currentDate.getMonth();
        
        // Обновляем заголовок
        if (elements.calendarTitle) {
            elements.calendarTitle.textContent = `${config.months[month]} ${year}`;
        }
        if (elements.calendarDays) {
            elements.calendarDays.innerHTML = '';
        }
        
        // Расчет дней
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        let startingDayOfWeek = firstDay.getDay();
        startingDayOfWeek = startingDayOfWeek === 0 ? 6 : startingDayOfWeek - 1;
        
        // Дни предыдущего месяца
        const prevMonth = new Date(year, month, 0);
        const daysInPrevMonth = prevMonth.getDate();
        
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            elements.calendarDays?.appendChild(
                createDayElement(daysInPrevMonth - i, true, month - 1, year)
            );
        }
        
        // Дни текущего месяца
        for (let day = 1; day <= daysInMonth; day++) {
            elements.calendarDays?.appendChild(
                createDayElement(day, false, month, year)
            );
        }
        
        // Дни следующего месяца
        const totalCells = elements.calendarDays.children.length;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        
        for (let day = 1; day <= remainingCells; day++) {
            elements.calendarDays?.appendChild(
                createDayElement(day, true, month + 1, year)
            );
        }
    }

    function createDayElement(day, isOtherMonth, month, year) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        
        if (isOtherMonth) {
            dayElement.classList.add('other-month');
        }
        
        // Корректируем месяц и год для других месяцев
        let actualMonth = month;
        let actualYear = year;
        
        if (actualMonth < 0) {
            actualMonth = 11;
            actualYear--;
        } else if (actualMonth > 11) {
            actualMonth = 0;
            actualYear++;
        }
        
        const date = new Date(actualYear, actualMonth, day, 12, 0, 0);
        const today = new Date();
        today.setHours(12, 0, 0, 0);
        
        // Добавляем классы
        if (!isOtherMonth) {
            if (!state.calendarUtils.isWorkDay(date)) {
                if (config.isOwner) {
                    dayElement.classList.add('owner-weekend');
                } else {
                    dayElement.classList.add('weekend');
                }
            }
            
            if (state.calendarUtils.isHoliday(date)) {
                if (config.isOwner) {
                    dayElement.classList.add('owner-holiday');
                } else {
                    dayElement.classList.add('holiday');
                }
            }
            
            if (!state.calendarUtils.isDateAvailable(date, config.isOwner)) {
                if (config.isOwner) {
                    // Для владельца добавляем специальный класс для прошедших дат
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    if (date < today) {
                        dayElement.classList.add('owner-past-date');
                    } else {
                        dayElement.classList.add('owner-restricted');
                    }
                } else {
                    dayElement.classList.add('disabled');
                }
            }
            
            if (date.toDateString() === today.toDateString()) {
                dayElement.classList.add('today');
            }
        }
        
        // Контент дня
        const dayContent = document.createElement('div');
        dayContent.className = 'day-content';
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayContent.appendChild(dayNumber);
        
        // Счетчик записей и исключения календаря
        if (!isOtherMonth) {
            const dateKey = state.calendarUtils.formatDateForServer(date);
            const count = state.monthlyStats[dateKey] || 0;
            
            if (count > 0) {
                const badge = document.createElement('div');
                badge.className = 'appointment-count';
                badge.textContent = count;
                dayContent.appendChild(badge);
            }
            
            // Загружаем информацию об исключениях для владельца
            if (config.isOwner) {
                loadDateExceptionInfo(dateKey).then(exception => {
                    if (exception) {
                        dayElement.classList.add('has-exception');
                        dayElement.classList.add(`exception-${exception.exception_type}`);
                        dayElement.setAttribute('data-exception-type', exception.exception_type);
                        dayElement.setAttribute('data-exception-reason', exception.reason || '');
                    }
                });
            }
        }
        
        dayElement.appendChild(dayContent);
        
        // Обработчики событий
        if (!isOtherMonth && (state.calendarUtils.isDateAvailable(date, config.isOwner) || config.isOwner)) {
            // Левый клик - выбор даты
            dayElement.addEventListener('click', () => selectDate(date, dayElement));
            
            // Правый клик для владельца - контекстное меню
            if (config.isOwner) {
                dayElement.classList.add('owner-view');
                dayElement.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    openDateExceptionModal(date);
                });
                
                // Также добавляем двойной клик для открытия модального окна
                dayElement.addEventListener('dblclick', (e) => {
                    e.preventDefault();
                    openDateExceptionModal(date);
                });
            }
        }
        
        return dayElement;
    }

    function selectDate(date, element) {
        // Убираем предыдущее выделение
        document.querySelectorAll('.calendar-day.selected').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Выделяем новый день
        element.classList.add('selected');
        state.selectedDate = date;
        
        // Показываем детальный вид
        showDayView(date);
    }

    // ===== ДЕТАЛЬНЫЙ ВИД ДНЯ =====
    function showDayView(date) {
        state.dayViewVisible = true;
        state.currentViewDate = date;
        
        elements.dayViewContainer.style.display = 'block';
        elements.dayViewTitle.textContent = `Расписание на ${formatDateForDisplay(date)}`;
        
        loadDaySchedule(date);
        
        elements.dayViewContainer.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }

    function hideDayView() {
        state.dayViewVisible = false;
        state.currentViewDate = null;
        elements.dayViewContainer.style.display = 'none';
    }

    function loadDaySchedule(date) {
        const dateString = state.calendarUtils.formatDateForServer(date);
        
        // Загружаем расписание
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            renderTimeSlots(data.timeSlots || []);
            
            // Для владельца также загружаем и отображаем информацию об исключениях
            if (config.isOwner) {
                loadDateExceptionInfo(dateString).then(exception => {
                    displayExceptionInfo(exception);
                });
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки расписания:', error);
            if (elements.timeSlots) {
                elements.timeSlots.innerHTML = '<div class="alert alert-danger">Ошибка загрузки расписания</div>';
            }
        });
    }
    
    // Отображение информации об исключении в детальном виде
    function displayExceptionInfo(exception) {
        // Удаляем предыдущую информацию об исключении
        const existingInfo = document.querySelector('.exception-info-block');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        if (!exception) return;
        
        const infoBlock = document.createElement('div');
        infoBlock.className = 'exception-info-block alert mb-3';
        
        let content = '<div class="d-flex justify-content-between align-items-center">';
        content += '<div>';
        
        if (exception.exception_type === 'allow') {
            infoBlock.classList.add('alert-success');
            content += '<i class="fas fa-check-circle me-2"></i>';
            content += '<strong>Разрешена работа в выходной день</strong>';
            if (exception.work_start_time && exception.work_end_time) {
                content += `<br><small>Время работы: ${exception.work_start_time} - ${exception.work_end_time}</small>`;
            }
        } else if (exception.exception_type === 'block') {
            infoBlock.classList.add('alert-danger');
            content += '<i class="fas fa-ban me-2"></i>';
            content += '<strong>День заблокирован для записей</strong>';
        }
        
        if (exception.reason) {
            content += `<br><small>Причина: ${exception.reason}</small>`;
        }
        
        content += '</div>';
        content += '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openDateExceptionModal(state.currentViewDate)">';
        content += '<i class="fas fa-edit"></i> Изменить';
        content += '</button>';
        content += '</div>';
        
        infoBlock.innerHTML = content;
        
        // Вставляем блок перед списком временных слотов
        elements.timeSlots.parentNode.insertBefore(infoBlock, elements.timeSlots);
    }

    function renderTimeSlots(slots) {
        elements.timeSlots.innerHTML = '';
        
        slots.forEach(slot => {
            // Для клиентов не показываем слоты с недостаточным временем
            if (!config.isOwner && !slot.has_enough_time && !slot.available) {
                return; // Пропускаем этот слот
            }
            
            const slotElement = createTimeSlot(slot);
            elements.timeSlots.appendChild(slotElement);
        });
    }

    function createTimeSlot(slot) {
        const slotDiv = document.createElement('div');
        slotDiv.className = 'time-slot';
        
        if (slot.isPast) {
            slotDiv.classList.add('past');
        }
        
        // Время
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-label';
        timeLabel.textContent = slot.time;
        
        // Контент
        const timeContent = document.createElement('div');
        timeContent.className = 'time-content';
        
        // Логика отображения
        if (config.isOwner) {
            // Для владельца - упрощенная логика с максимальной гибкостью
            if (slot.appointments && slot.appointments.length > 0) {
                // Показываем записи
                renderOwnerAppointments(timeContent, slot);
            } else {
                // Доступный слот для записи
                renderOwnerAvailableSlot(timeContent, slot);
            }
        } else {
            // Для обычных пользователей - стандартная логика
            if (slot.appointments && slot.appointments.length > 0) {
                renderOwnerAppointments(timeContent, slot);
            } else if (slot.available) {
                renderAvailableSlot(timeContent, slot);
            } else if (slot.isPast) {
                timeContent.classList.add('unavailable', 'past');
                timeContent.innerHTML = '<div class="unavailable-slot"><i class="fas fa-clock"></i> Прошло</div>';
            } else if (!slot.has_enough_time) {
                timeContent.classList.add('unavailable', 'insufficient-time');
                timeContent.innerHTML = '<div class="unavailable-slot"><i class="fas fa-hourglass-half"></i> Недостаточно времени</div>';
            } else if (state.calendarUtils.isBreakTime(slot.time)) {
                timeContent.classList.add('break');
                timeContent.innerHTML = '<div class="break-slot"><i class="fas fa-coffee"></i> Перерыв</div>';
            } else {
                timeContent.classList.add('occupied');
                timeContent.innerHTML = '<div class="occupied-slot"><i class="fas fa-ban"></i> Занято</div>';
            }
        }
        
        slotDiv.appendChild(timeLabel);
        slotDiv.appendChild(timeContent);
        
        return slotDiv;
    }

    function renderOwnerAppointments(container, slot) {
        const appointments = Array.isArray(slot.appointments) ? 
            slot.appointments : Object.values(slot.appointments);
        
        appointments.forEach((appointment, index) => {
            const card = createAppointmentCard(appointment, index, appointments.length);
            container.appendChild(card);
        });
        
        // Кнопка добавления если еще есть места
        if (slot.available) {
            const addButton = createAddButton(slot);
            container.appendChild(addButton);
        }
    }

    function createAppointmentCard(appointment, index, total) {
        const card = document.createElement('div');
        card.className = 'appointment-card owner-view';
        
        if (total > 1) {
            card.classList.add('multiple-booking');
        }
        
        card.innerHTML = `
            ${total > 1 ? `<div class="booking-number">Запись ${index + 1}/${total}</div>` : ''}
            <div class="appointment-client">
                <i class="fas fa-user"></i> ${appointment.client_name}
            </div>
            ${appointment.client_phone ? `
                <div class="appointment-phone">
                    <i class="fas fa-phone"></i> ${appointment.client_phone}
                </div>
            ` : ''}
            <div class="appointment-service">
                <i class="fas fa-briefcase"></i> ${appointment.title || 'Услуга'}
            </div>
            <div class="appointment-duration">
                <i class="fas fa-clock"></i> ${appointment.duration || '30 мин'}
            </div>
            ${appointment.price ? `
                <div class="appointment-price">
                    <i class="fas fa-ruble-sign"></i> ${appointment.price}
                </div>
            ` : ''}
            <div class="appointment-status status-${appointment.status || 'pending'}">
                ${getStatusText(appointment.status || 'pending')}
            </div>
            <div class="edit-hint">
                <i class="fas fa-edit"></i> Нажмите для редактирования
            </div>
        `;
        
        card.addEventListener('click', () => openEditModal(appointment));
        
        return card;
    }

    function renderAvailableSlot(container, slot) {
        container.classList.add('available');
        
        const text = slot.multiple_bookings_enabled ? 
            `Свободно (${slot.appointment_count || 0}/${slot.max_appointments})` : 
            'Свободно';
        
        container.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> ${text}
            </div>
        `;
        
        container.addEventListener('click', () => openAppointmentModal(slot));
    }

    function createAddButton(slot) {
        const button = document.createElement('div');
        button.className = 'add-more-slot';
        button.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> Добавить ещё (${slot.appointment_count || 0}/${slot.max_appointments || 1})
            </div>
        `;
        
        button.addEventListener('click', () => openAppointmentModal(slot));
        
        return button;
    }

    function renderOwnerSlot(container, slot) {
        // Если есть записи, показываем их
        if (slot.appointments && slot.appointments.length > 0) {
            const appointments = Array.isArray(slot.appointments) ? 
                slot.appointments : Object.values(slot.appointments);
            
            appointments.forEach((appointment, index) => {
                const card = createAppointmentCard(appointment, index, appointments.length);
                container.appendChild(card);
            });
        }
        
        // Показываем статус слота для владельца
        const statusDiv = document.createElement('div');
        statusDiv.className = 'owner-slot-status';
        
        let statusClass = '';
        let statusIcon = '';
        let statusText = '';
        let canBook = false;
        
        if (slot.available) {
            statusClass = 'available';
            statusIcon = 'fas fa-plus-circle';
            statusText = 'Доступен для записи';
            canBook = true;
        } else {
            // Показываем причину недоступности
            if (slot.owner_info && slot.owner_info.warning_reason) {
                statusText = slot.owner_info.warning_reason;
                
                if (slot.owner_info.is_blocked) {
                    statusClass = 'blocked';
                    statusIcon = 'fas fa-ban';
                } else if (!slot.owner_info.has_enough_time) {
                    statusClass = 'insufficient-time';
                    statusIcon = 'fas fa-hourglass-half';
                    statusText += ` (требуется ${slot.required_time} мин, доступно ${slot.owner_info.remaining_time_until_end} мин)`;
                } else if (slot.owner_info.is_past) {
                    statusClass = 'past-time';
                    statusIcon = 'fas fa-clock';
                    canBook = true; // Владелец может записывать на прошедшее время
                } else if (!slot.owner_info.is_work_day) {
                    statusClass = 'non-work-day';
                    statusIcon = 'fas fa-calendar-times';
                    canBook = true; // Владелец может записывать на выходные
                } else if (slot.owner_info.is_holiday) {
                    statusClass = 'holiday';
                    statusIcon = 'fas fa-calendar-alt';
                    canBook = true; // Владелец может записывать на праздники
                } else {
                    statusClass = 'unavailable';
                    statusIcon = 'fas fa-times-circle';
                }
            } else {
                statusClass = 'unavailable';
                statusIcon = 'fas fa-times-circle';
                statusText = 'Недоступен';
            }
        }
        
        statusDiv.className = `owner-slot-status ${statusClass}`;
        statusDiv.innerHTML = `
            <div class="status-info">
                <i class="${statusIcon}"></i>
                <span>${statusText}</span>
            </div>
        `;
        
        // Добавляем кнопку записи если можно записывать
        if (canBook && (!slot.appointments || slot.appointments.length < slot.max_appointments)) {
            statusDiv.classList.add('clickable');
            statusDiv.addEventListener('click', () => openAppointmentModal(slot));
            
            const bookButton = document.createElement('div');
            bookButton.className = 'book-button';
            bookButton.innerHTML = '<i class="fas fa-plus"></i> Записать';
            statusDiv.appendChild(bookButton);
        }
        
        container.appendChild(statusDiv);
        
        // Показываем детальную информацию
        if (slot.owner_info) {
            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'owner-slot-details';
            detailsDiv.innerHTML = `
                <small class="text-muted">
                    До конца дня: ${slot.owner_info.remaining_time_until_end} мин |
                    Мин. услуга: ${slot.owner_info.min_service_duration} мин |
                    Перерыв: ${slot.owner_info.break_time} мин
                </small>
            `;
            container.appendChild(detailsDiv);
        }
    }

    function renderOwnerAvailableSlot(container, slot) {
        container.classList.add('available', 'owner-flexible');
        
        let statusText = 'Свободно';
        let statusIcon = 'fas fa-plus-circle';
        let statusClass = 'owner-available';
        
        // Добавляем подсказки для особых случаев
        if (slot.owner_info) {
            if (slot.owner_info.is_past) {
                statusText = 'Свободно (прошедшее время)';
                statusIcon = 'fas fa-crown';
                statusClass = 'owner-past-available';
            } else if (!slot.owner_info.is_work_day) {
                statusText = 'Свободно (выходной)';
                statusIcon = 'fas fa-crown';
                statusClass = 'owner-weekend-available';
            } else if (slot.owner_info.is_holiday) {
                statusText = 'Свободно (праздник)';
                statusIcon = 'fas fa-crown';
                statusClass = 'owner-holiday-available';
            }
        }
        
        container.className = `time-content available ${statusClass}`;
        container.innerHTML = `
            <div class="available-slot">
                <i class="${statusIcon}"></i> ${statusText}
            </div>
        `;
        
        container.addEventListener('click', () => openAppointmentModal(slot));
    }

    // ===== МОДАЛЬНЫЕ ОКНА =====
    function openAppointmentModal(slot) {
        const date = state.currentViewDate || state.selectedDate;
        
        if (!date) {
            showAlert('Не выбрана дата', 'danger');
            return;
        }
        
        // Для владельца пропускаем проверку доступности
        if (config.isOwner) {
            document.getElementById('modal_appointment_date').value = state.calendarUtils.formatDateForServer(date);
            document.getElementById('modal_appointment_time').value = slot.time || slot;
            document.getElementById('selected_date_display').textContent = formatDateForDisplay(date);
            
            // Добавляем подсказку для владельца
            const modalBody = document.querySelector('#appointmentModal .modal-body');
            let ownerNotice = modalBody.querySelector('.owner-booking-notice');
            if (!ownerNotice) {
                ownerNotice = document.createElement('div');
                ownerNotice.className = 'alert alert-warning owner-booking-notice';
                ownerNotice.innerHTML = '<i class="fas fa-crown me-2"></i><strong>Режим владельца:</strong> Вы можете создавать записи на любое время без ограничений.';
                modalBody.insertBefore(ownerNotice, modalBody.firstChild);
            }
            
            // Сбрасываем и показываем первый шаг
            resetAppointmentSteps();
            
            modals.appointment.show();
            return;
        }
        
        // Проверяем доступность для обычных пользователей
        checkSlotAvailability(date, slot.time, () => {
            document.getElementById('modal_appointment_date').value = state.calendarUtils.formatDateForServer(date);
            document.getElementById('modal_appointment_time').value = slot.time;
            document.getElementById('selected_date_display').textContent = formatDateForDisplay(date);
            
            // Сбрасываем и показываем первый шаг
            resetAppointmentSteps();
            
            modals.appointment.show();
        });
    }

    function checkSlotAvailability(date, time, callback) {
        const dateString = state.calendarUtils.formatDateForServer(date);
        
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            const slot = data.timeSlots.find(s => s.time === time);
            
            if (!slot || !slot.available) {
                showAlert('Это время уже занято', 'warning');
                if (state.dayViewVisible) {
                    renderTimeSlots(data.timeSlots);
                }
                return;
            }
            
            callback();
        })
        .catch(error => {
            console.error('Ошибка проверки доступности:', error);
            callback(); // Продолжаем в случае ошибки
        });
    }

    // ===== МНОГОШАГОВЫЙ ПРОЦЕСС ЗАПИСИ =====
    let currentStep = 1;
    let selectedService = null;

    function initAppointmentSteps() {
        // Кнопки навигации
        const nextBtn = document.getElementById('nextStepBtn');
        const prevBtn = document.getElementById('prevStepBtn');
        const submitBtn = document.getElementById('submitBtn');

        // Обработчики кнопок
        nextBtn?.addEventListener('click', nextStep);
        prevBtn?.addEventListener('click', prevStep);

        // Обработчики выбора услуг
        document.querySelectorAll('.service-selection-card').forEach(card => {
            card.addEventListener('click', function() {
                selectService(this);
            });
            
            // Обработчик для кнопки "Выбрать"
            const selectBtn = card.querySelector('.service-select-btn');
            if (selectBtn) {
                selectBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectService(card);
                });
            }
        });

        // Сброс при закрытии модального окна
        document.getElementById('appointmentModal')?.addEventListener('hidden.bs.modal', resetAppointmentSteps);
    }

    function nextStep() {
        if (currentStep === 1) {
            // Валидация первого шага
            if (!validateStep1()) {
                return;
            }
            showStep(2);
        } else if (currentStep === 2) {
            // Проверяем выбор услуги
            if (!selectedService) {
                showAlert('Пожалуйста, выберите услугу', 'warning');
                return;
            }
            updateSummary();
            showStep(3);
        }
    }

    function prevStep() {
        if (currentStep === 2) {
            showStep(1);
        } else if (currentStep === 3) {
            showStep(2);
        }
    }

    function showStep(step) {
        // Скрываем все шаги
        document.querySelectorAll('.appointment-step').forEach(stepDiv => {
            stepDiv.style.display = 'none';
        });

        // Показываем нужный шаг
        document.getElementById(`step${step}`).style.display = 'block';

        // Обновляем кнопки
        const nextBtn = document.getElementById('nextStepBtn');
        const prevBtn = document.getElementById('prevStepBtn');
        const submitBtn = document.getElementById('submitBtn');

        prevBtn.style.display = step > 1 ? 'inline-block' : 'none';
        
        if (step === 3) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-block';
        } else {
            nextBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
        }

        currentStep = step;
    }

    function validateStep1() {
        const name = document.getElementById('modal_client_name').value.trim();
        const phone = document.getElementById('modal_client_phone').value.trim();

        if (!name) {
            showAlert('Пожалуйста, введите ваше имя', 'warning');
            document.getElementById('modal_client_name').focus();
            return false;
        }

        if (!phone) {
            showAlert('Пожалуйста, введите ваш телефон', 'warning');
            document.getElementById('modal_client_phone').focus();
            return false;
        }

        return true;
    }

    function selectService(card) {
        // Убираем выделение с других карточек
        document.querySelectorAll('.service-selection-card').forEach(c => {
            c.classList.remove('selected');
        });

        // Выделяем выбранную карточку
        card.classList.add('selected');

        // Сохраняем данные услуги
        selectedService = {
            id: card.dataset.serviceId,
            name: card.dataset.serviceName,
            duration: card.dataset.serviceDuration,
            price: card.dataset.servicePrice
        };

        // Устанавливаем ID услуги в форму
        document.getElementById('modal_service_id').value = selectedService.id;
    }

    function updateSummary() {
        const date = document.getElementById('selected_date_display').textContent;
        const time = document.getElementById('modal_appointment_time').value;
        const clientName = document.getElementById('modal_client_name').value;
        const clientPhone = document.getElementById('modal_client_phone').value;
        const notes = document.getElementById('modal_notes').value;

        document.getElementById('summary_date').textContent = date;
        document.getElementById('summary_time').textContent = time;
        document.getElementById('summary_service').textContent = selectedService.name;
        document.getElementById('summary_price').textContent = formatPrice(selectedService.price);
        document.getElementById('summary_duration').textContent = formatDuration(selectedService.duration);
        document.getElementById('summary_client').textContent = `${clientName} (${clientPhone})`;

        if (notes.trim()) {
            document.getElementById('summary_notes').textContent = notes;
            document.getElementById('summary_notes_row').style.display = 'flex';
        } else {
            document.getElementById('summary_notes_row').style.display = 'none';
        }
    }

    function resetAppointmentSteps() {
        currentStep = 1;
        selectedService = null;
        
        // Сброс выделения услуг
        document.querySelectorAll('.service-selection-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Показываем первый шаг
        showStep(1);

        // Очищаем форму
        document.getElementById('modal_service_id').value = '';
    }

    function formatPrice(price) {
        return price ? parseFloat(price).toLocaleString('ru-RU') + ' ₽' : 'Бесплатно';
    }

    function formatDuration(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        
        let result = [];
        if (hours > 0) result.push(hours + ' ч');
        if (mins > 0) result.push(mins + ' мин');
        
        return result.join(' ');
    }

    function handleAppointmentSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(elements.appointmentForm);
        
        // Валидация
        if (!validateAppointmentForm(formData)) {
            return;
        }
        
        // Отправка
        fetch(`{{ route('company.appointments.create', $company->slug) }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json().then(data => ({
            ok: response.ok,
            status: response.status,
            data
        })))
        .then(result => {
            if (result.ok) {
                showAlert('Запись успешно создана!', 'success');
                modals.appointment.hide();
                elements.appointmentForm.reset();
                resetAppointmentSteps();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                if (result.status === 422 && result.data.errors) {
                    showValidationErrors(result.data.errors);
                } else {
                    showAlert(result.data.message || 'Ошибка при создании записи', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Произошла ошибка при создании записи', 'danger');
        });
    }

    function validateAppointmentForm(formData) {
        const date = formData.get('appointment_date');
        const time = formData.get('appointment_time');
        const serviceId = formData.get('service_id');
        
        if (!serviceId) {
            showAlert('Пожалуйста, выберите услугу', 'danger');
            return false;
        }
        
        const appointmentDateTime = new Date(`${date}T${time}`);
        const now = new Date();
        
        if (appointmentDateTime < now) {
            showAlert('Нельзя записаться на прошедшее время', 'danger');
            return false;
        }
        
        return true;
    }

    // ===== ФУНКЦИИ ДЛЯ ВЛАДЕЛЬЦА =====
    function initOwnerHandlers() {
        if (!config.isOwner) return;
        
        // Обработчики для модального окна редактирования
        const editDateField = document.getElementById('edit_new_date');
        if (editDateField) {
            editDateField.addEventListener('change', handleEditDateChange);
        }
        
        const updateBtn = document.getElementById('updateBtn');
        if (updateBtn) {
            updateBtn.addEventListener('click', handleAppointmentUpdate);
        }
        
        const cancelBtn = document.getElementById('cancelAppointmentBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', handleAppointmentCancel);
        }
    }

    function openEditModal(appointment) {
        if (!config.isOwner || !modals.edit) return;
        
        // Заполняем данные
        fillEditModalData(appointment);
        
        // Загружаем доступные слоты
        loadEditModalTimeSlots(appointment.appointment_date, appointment.appointment_time);
        
        // Показываем модальное окно
        modals.edit.show();
    }

    function fillEditModalData(appointment) {
        document.getElementById('edit_appointment_id').value = appointment.id;
        document.getElementById('edit_client_name').textContent = appointment.client_name || '-';
        document.getElementById('edit_client_phone').textContent = appointment.client_phone || '-';
        document.getElementById('edit_appointment_date').textContent = appointment.appointment_date || '-';
        document.getElementById('edit_appointment_time').textContent = appointment.appointment_time || '-';
        document.getElementById('edit_created_at').textContent = appointment.created_at || '-';
        
        // Статус
        const statusEl = document.getElementById('edit_status');
        statusEl.textContent = getStatusText(appointment.status);
        statusEl.className = `badge bg-${getStatusColor(appointment.status)}`;
        
        // Комментарий клиента
        const notesSection = document.getElementById('client_notes_section');
        const notesEl = document.getElementById('edit_client_notes');
        
        if (appointment.notes) {
            notesEl.textContent = appointment.notes;
            notesSection.style.display = 'block';
        } else {
            notesSection.style.display = 'none';
        }
        
        // Поля редактирования
        document.getElementById('edit_client_name_field').value = appointment.client_name || '';
        document.getElementById('edit_client_phone_field').value = appointment.client_phone || '';
        document.getElementById('edit_owner_notes').value = appointment.owner_notes || '';
        
        // Дата для редактирования
        const dateForInput = convertDateToInputFormat(appointment.appointment_date);
        document.getElementById('edit_new_date').value = dateForInput;
        
        // Сохраняем данные для последующего использования
        document.getElementById('editAppointmentForm').dataset.appointment = JSON.stringify(appointment);
    }

    function loadEditModalTimeSlots(date, currentTime) {
        const serverDate = convertDateToServerFormat(date);
        const timeSelect = document.getElementById('edit_new_time');
        
        timeSelect.innerHTML = '<option value="">Загрузка...</option>';
        
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${serverDate}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '<option value="">Выберите время</option>';
            
            if (data.timeSlots) {
                data.timeSlots.forEach(slot => {
                    if (!slot.isPast) {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.textContent = slot.time;
                        
                        if (slot.time === currentTime) {
                            option.selected = true;
                            option.textContent += ' (текущее)';
                        } else if (!slot.available) {
                            option.textContent += ' (занято)';
                        }
                        
                        timeSelect.appendChild(option);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки слотов:', error);
            timeSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
        });
    }

    function handleEditDateChange(e) {
        const newDate = e.target.value;
        if (newDate) {
            loadEditModalTimeSlots(newDate, null);
        }
    }

    function handleAppointmentUpdate() {
        const form = document.getElementById('editAppointmentForm');
        const appointment = JSON.parse(form.dataset.appointment || '{}');
        
        const updateData = {};
        let hasChanges = false;
        
        // Проверяем изменения
        const newDate = document.getElementById('edit_new_date').value;
        const newTime = document.getElementById('edit_new_time').value;
        const newName = document.getElementById('edit_client_name_field').value.trim();
        const newPhone = document.getElementById('edit_client_phone_field').value;
        const ownerNotes = document.getElementById('edit_owner_notes').value;
        
        if (newDate && newTime) {
            const originalDate = convertDateToInputFormat(appointment.appointment_date);
            if (newDate !== originalDate || newTime !== appointment.appointment_time) {
                updateData.appointment_date = newDate;
                updateData.appointment_time = newTime;
                hasChanges = true;
            }
        }
        
        if (newName && newName !== appointment.client_name) {
            updateData.client_name = newName;
            hasChanges = true;
        }
        
        if (newPhone !== appointment.client_phone) {
            updateData.client_phone = newPhone;
            hasChanges = true;
        }
        
        if (ownerNotes !== (appointment.owner_notes || '')) {
            updateData.owner_notes = ownerNotes;
            hasChanges = true;
        }
        
        if (!hasChanges) {
            showAlert('Внесите изменения перед обновлением', 'warning');
            return;
        }
        
        // Отправка обновления
        updateAppointment(appointment.id, updateData);
    }

    function handleAppointmentCancel() {
        const form = document.getElementById('editAppointmentForm');
        const appointment = JSON.parse(form.dataset.appointment || '{}');
        
        if (!appointment.id) return;
        
        if (confirm('Отменить эту запись? Это действие нельзя отменить.')) {
            cancelAppointment(appointment.id);
        }
    }

    function updateAppointment(id, data) {
        fetch(`{{ url('/company/'.$company->slug) }}/appointments/${id}/update`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert('Запись успешно обновлена!', 'success');
                modals.edit.hide();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                showAlert(result.error || 'Ошибка при обновлении записи', 'danger');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка при обновлении записи', 'danger');
        });
    }

    function cancelAppointment(id) {
        fetch(`{{ url('/company/'.$company->slug) }}/appointments/${id}/cancel`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert('Запись отменена!', 'success');
                modals.edit.hide();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                showAlert(result.error || 'Ошибка при отмене записи', 'danger');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка при отмене записи', 'danger');
        });
    }

    // ===== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =====
    function formatDateForDisplay(date) {
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();
        return `${day}.${month}.${year}`;
    }

    function convertDateToInputFormat(dateStr) {
        if (!dateStr) return '';
        
        // Если формат dd.mm.yyyy
        if (dateStr.includes('.')) {
            const parts = dateStr.split('.');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        
        return dateStr;
    }

    function convertDateToServerFormat(dateStr) {
        if (!dateStr) return '';
        
        // Если формат dd.mm.yyyy
        if (dateStr.includes('.')) {
            const parts = dateStr.split('.');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        
        return dateStr;
    }

    function getStatusText(status) {
        const statusMap = {
            'pending': 'Ожидает',
            'confirmed': 'Подтверждена',
            'cancelled': 'Отменена',
            'completed': 'Выполнена'
        };
        return statusMap[status] || status;
    }

    function getStatusColor(status) {
        const colorMap = {
            'pending': 'warning',
            'confirmed': 'success',
            'cancelled': 'danger',
            'completed': 'info'
        };
        return colorMap[status] || 'secondary';
    }

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        const container = document.querySelector('.container') || document.body;
        container.prepend(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.remove();
        }, 5000);
    }

    // Маски ввода (телефон)
    function initInputMasks() {
        try {
            if (window.jQuery && typeof jQuery.fn.mask === 'function') {
                const $ = window.jQuery;
                $("#modal_client_phone, #edit_client_phone_field").mask('+0 (000) 000-00-00');
            }
        } catch (e) { /* ignore */ }
    }

    // Счетчики символов для текстовых полей
    function initCharacterCounters() {
        const notes = document.getElementById('modal_notes');
        const notesCounter = document.getElementById('notesCounter');
        if (notes && notesCounter) {
            const max = parseInt(notes.getAttribute('maxlength') || '500', 10);
            const update = () => notesCounter.textContent = String(Math.max(0, max - notes.value.length));
            notes.addEventListener('input', update);
            update();
        }

        const ownerNotes = document.getElementById('edit_owner_notes');
        const ownerCounter = document.getElementById('ownerNotesCounter');
        if (ownerNotes && ownerCounter) {
            const max2 = parseInt(ownerNotes.getAttribute('maxlength') || '500', 10);
            const update2 = () => ownerCounter.textContent = String(Math.max(0, max2 - ownerNotes.value.length));
            ownerNotes.addEventListener('input', update2);
            update2();
        }
    }

    // Показ валидационных ошибок бэкенда
    function showValidationErrors(errors) {
        try {
            const messages = [];
            Object.keys(errors || {}).forEach(key => {
                const arr = errors[key];
                if (Array.isArray(arr)) {
                    arr.forEach(msg => messages.push(String(msg)));
                } else if (arr) {
                    messages.push(String(arr));
                }
            });
            if (messages.length) {
                showAlert(messages.join('<br>'), 'danger');
            }
        } catch (e) {
            showAlert('Проверьте корректность введенных данных', 'danger');
        }
    }

    // ===== УПРАВЛЕНИЕ ИСКЛЮЧЕНИЯМИ КАЛЕНДАРЯ (только для владельца) =====
    
    let currentExceptionData = null;
    
    // Инициализация модального окна исключений
    function initDateExceptionModal() {
        if (!config.isOwner) return;
        
        const modal = document.getElementById('dateExceptionModal');
        const form = document.getElementById('dateExceptionForm');
        const workTimeSection = document.getElementById('work_time_section');
        const allowRadio = document.getElementById('exception_type_allow');
        const blockRadio = document.getElementById('exception_type_block');
        const noneRadio = document.getElementById('exception_type_none');
        const deleteBtn = document.getElementById('deleteDateExceptionBtn');
        
        if (!modal || !form) return;
        
        // Показ/скрытие секции времени работы
        [allowRadio, blockRadio, noneRadio].forEach(radio => {
            if (radio) {
                radio.addEventListener('change', function() {
                    if (workTimeSection) {
                        workTimeSection.style.display = this.value === 'allow' ? 'block' : 'none';
                    }
                });
            }
        });
        
        // Обработка отправки формы
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            await saveDateException();
        });
        
        // Обработка удаления исключения
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async function() {
                if (confirm('Вы уверены, что хотите удалить это исключение?')) {
                    await deleteDateException();
                }
            });
        }
    }
    
    // Открытие модального окна управления исключением
    function openDateExceptionModal(date) {
        if (!config.isOwner) return;
        
        const modal = new bootstrap.Modal(document.getElementById('dateExceptionModal'));
        const form = document.getElementById('dateExceptionForm');
        const dateInput = document.getElementById('exception_date');
        const workStartInput = document.getElementById('work_start_time');
        const workEndInput = document.getElementById('work_end_time');
        const reasonInput = document.getElementById('exception_reason');
        const deleteBtn = document.getElementById('deleteDateExceptionBtn');
        const alertDiv = document.getElementById('exceptionAlert');
        
        if (!form) return;
        
        // Скрываем алерт
        if (alertDiv) {
            alertDiv.style.display = 'none';
        }
        
        // Устанавливаем дату
        const dateString = state.calendarUtils.formatDateForServer(date);
        if (dateInput) {
            dateInput.value = dateString;
        }
        
        // Загружаем существующее исключение
        loadDateException(dateString).then(exception => {
            currentExceptionData = exception;
            
            // Заполняем форму
            if (exception) {
                // Есть исключение
                document.querySelector(`input[name="exception_type"][value="${exception.exception_type}"]`).checked = true;
                
                if (exception.exception_type === 'allow') {
                    if (workStartInput) workStartInput.value = exception.work_start_time || config.calendarSettings.work_start_time;
                    if (workEndInput) workEndInput.value = exception.work_end_time || config.calendarSettings.work_end_time;
                    document.getElementById('work_time_section').style.display = 'block';
                }
                
                if (reasonInput) reasonInput.value = exception.reason || '';
                if (deleteBtn) deleteBtn.style.display = 'inline-block';
            } else {
                // Нет исключения - устанавливаем значения по умолчанию
                document.getElementById('exception_type_none').checked = true;
                if (workStartInput) workStartInput.value = config.calendarSettings.work_start_time;
                if (workEndInput) workEndInput.value = config.calendarSettings.work_end_time;
                if (reasonInput) reasonInput.value = '';
                if (deleteBtn) deleteBtn.style.display = 'none';
                document.getElementById('work_time_section').style.display = 'none';
            }
            
            modal.show();
        });
    }
    
    // Загрузка исключения для даты
    async function loadDateException(date) {
        try {
            const response = await fetch(`/company/${config.companySlug}/date-exceptions/by-date?date=${date}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                return data.exception;
            }
        } catch (error) {
            console.error('Ошибка загрузки исключения:', error);
        }
        return null;
    }
    
    // Быстрая загрузка информации об исключении (для отображения в календаре)
    async function loadDateExceptionInfo(date) {
        try {
            let url;
            if (config.isOwner) {
                // Для владельца используем приватный API
                url = `/company/${config.companySlug}/date-exceptions/by-date?date=${date}`;
            } else {
                // Для клиентов используем публичный API
                url = `/company/${config.companySlug}/date-exception-info?date=${date}`;
            }
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                return data.exception;
            }
        } catch (error) {
            // Тихо игнорируем ошибки для отображения календаря
        }
        return null;
    }
    
    // Сохранение исключения
    async function saveDateException() {
        const form = document.getElementById('dateExceptionForm');
        const formData = new FormData(form);
        const alertDiv = document.getElementById('exceptionAlert');
        const saveBtn = document.getElementById('saveDateExceptionBtn');
        
        // Проверяем, выбран ли тип "удалить исключение"
        const exceptionType = formData.get('exception_type');
        if (!exceptionType && currentExceptionData) {
            // Удаляем существующее исключение
            await deleteDateException();
            return;
        }
        
        if (!exceptionType) {
            // Ничего не делаем, если нет исключения и выбрано "стандартные настройки"
            const modal = bootstrap.Modal.getInstance(document.getElementById('dateExceptionModal'));
            modal.hide();
            return;
        }
        
        const originalText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
        
        try {
            const response = await fetch(`/company/${config.companySlug}/date-exceptions`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showExceptionAlert('Исключение календаря сохранено', 'success');
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('dateExceptionModal'));
                    modal.hide();
                    // Обновляем календарь
                    renderCalendar();
                    // Если открыт детальный вид, обновляем его
                    if (state.dayViewVisible && state.currentViewDate) {
                        loadDaySchedule(state.currentViewDate);
                    }
                }, 1500);
            } else {
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat().join('<br>');
                    showExceptionAlert(errorMessages, 'danger');
                } else {
                    showExceptionAlert(data.error || 'Ошибка при сохранении', 'danger');
                }
            }
        } catch (error) {
            console.error('Ошибка сохранения исключения:', error);
            showExceptionAlert('Ошибка сети. Попробуйте еще раз.', 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    }
    
    // Удаление исключения
    async function deleteDateException() {
        if (!currentExceptionData) return;
        
        const deleteBtn = document.getElementById('deleteDateExceptionBtn');
        const originalText = deleteBtn.textContent;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Удаление...';
        
        try {
            const response = await fetch(`/company/${config.companySlug}/date-exceptions/${currentExceptionData.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showExceptionAlert('Исключение календаря удалено', 'success');
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('dateExceptionModal'));
                    modal.hide();
                    // Обновляем календарь
                    renderCalendar();
                    // Если открыт детальный вид, обновляем его
                    if (state.dayViewVisible && state.currentViewDate) {
                        loadDaySchedule(state.currentViewDate);
                    }
                }, 1500);
            } else {
                showExceptionAlert(data.error || 'Ошибка при удалении', 'danger');
            }
        } catch (error) {
            console.error('Ошибка удаления исключения:', error);
            showExceptionAlert('Ошибка сети. Попробуйте еще раз.', 'danger');
        } finally {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
        }
    }
    
    // Показ алерта в модальном окне исключений
    function showExceptionAlert(message, type) {
        const alertDiv = document.getElementById('exceptionAlert');
        if (!alertDiv) return;
        
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = message;
        alertDiv.style.display = 'block';
        
        // Автоматически скрываем через 5 секунд для успешных сообщений
        if (type === 'success') {
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }
    }

    // Старт приложения
    init();
});
</script>