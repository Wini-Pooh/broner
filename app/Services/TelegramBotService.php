<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Appointment;
use Carbon\Carbon;

class TelegramBotService
{
    /**
     * Отправляет сообщение через Telegram-бот
     */
    public function sendMessage($company, $chatId, $message, $options = [])
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/sendMessage";
        
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ], $options);

        try {
            $response = Http::post($url, $data);
            
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Ошибка отправки Telegram сообщения', [
                    'response' => $response->body(),
                    'data' => $data
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Исключение при отправке Telegram сообщения', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Редактирует существующее сообщение
     */
    public function editMessage($company, $chatId, $messageId, $message, $options = [])
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/editMessageText";
        
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ], $options);

        try {
            $response = Http::post($url, $data);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка редактирования Telegram сообщения', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отвечает на callback query
     */
    public function answerCallbackQuery($company, $callbackQueryId, $text = null)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/answerCallbackQuery";
        
        $data = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $data['text'] = $text;
        }

        try {
            Http::post($url, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Ошибка ответа на callback query', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Создает клавиатуру с датами
     */
    public function createDateKeyboard($company, $daysAhead = null)
    {
        $settings = $company->getCalendarSettings();
        $daysAhead = $daysAhead ?? $settings['appointment_days_ahead'];
        
        $keyboard = [];
        $today = Carbon::now();
        $row = [];
        
        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $today->copy()->addDays($i);
            
            // Используем метод компании для проверки рабочего дня с учетом исключений
            if (!$company->isWorkDay($date)) {
                continue;
            }
            
            $dateString = $date->format('Y-m-d');
            $formattedDate = $date->format('d.m');
            $dayName = $this->getDayName($date);
            
            // Добавляем индикатор исключения календаря
            $dateException = $company->dateExceptions()->forDate($date)->first();
            $indicator = '';
            if ($dateException) {
                $indicator = $dateException->isAllowException() ? ' ✅' : ' ⚠️';
            }
            
            $row[] = [
                'text' => "{$formattedDate} ({$dayName}){$indicator}",
                'callback_data' => "select_date:{$dateString}"
            ];
            
            // По 2 кнопки в ряд
            if (count($row) == 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        // Добавляем последний ряд если есть
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        return $keyboard;
    }

    /**
     * Создает клавиатуру с временными слотами
     */
    public function createTimeKeyboard($date, $slots)
    {
        $keyboard = [];
        $row = [];
        
        foreach ($slots as $slot) {
            $row[] = [
                'text' => $slot['time'],
                'callback_data' => "select_time:{$date}:{$slot['time']}"
            ];
            
            // По 3 кнопки в ряд
            if (count($row) == 3) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        // Добавляем последний ряд если есть
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        // Кнопка "Назад"
        $keyboard[] = [
            ['text' => '← Выбрать другую дату', 'callback_data' => 'select_date_back']
        ];
        
        return $keyboard;
    }

    /**
     * Создает клавиатуру с услугами
     */
    public function createServiceKeyboard($date, $time, $services)
    {
        $keyboard = [];
        
        foreach ($services as $service) {
            $text = $service->name;
            if ($service->price > 0) {
                $text .= " - {$service->formatted_price}";
            }
            
            $keyboard[] = [
                [
                    'text' => $text,
                    'callback_data' => "select_service:{$date}:{$time}:{$service->id}"
                ]
            ];
        }
        
        // Кнопка "Назад"
        $keyboard[] = [
            ['text' => '← Выбрать другое время', 'callback_data' => "select_date:{$date}"]
        ];
        
        return $keyboard;
    }

    /**
     * Создает основную клавиатуру с кнопками интерфейса
     */
    public function createMainKeyboard()
    {
        $keyboard = [
            [
                ['text' => '📅 Записаться'],
                ['text' => '📋 Мои записи']
            ],
            [
                ['text' => '💼 Услуги'],
                ['text' => '🕐 Режим работы']
            ],
            [
                ['text' => '📍 Контакты'],
                ['text' => '❓ Помощь']
            ]
        ];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'persistent' => true
        ];
    }

    /**
     * Создает клавиатуру для процесса записи
     */
    public function createBookingKeyboard()
    {
        $keyboard = [
            [
                ['text' => '❌ Отменить запись'],
                ['text' => '🏠 Главное меню']
            ]
        ];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
    }

    /**
     * Удаляет клавиатуру
     */
    public function removeKeyboard()
    {
        return [
            'remove_keyboard' => true
        ];
    }

    /**
     * Получает доступные временные слоты для даты с полной логикой календаря
     */
    public function getAvailableTimeSlots($company, $date)
    {
        // Получаем записи на эту дату
        $appointments = $company->getAppointmentsForDate($date);
        
        // Генерируем слоты с помощью той же логики, что и в веб-интерфейсе
        $slots = $this->generateTimeSlots($appointments, $date, false, $company);
        
        // Фильтруем только доступные слоты для бота
        $availableSlots = collect($slots)->filter(function($slot) {
            return $slot['available'] && !$slot['isPast'];
        })->map(function($slot) {
            return [
                'time' => $slot['time'],
                'available_slots' => $slot['max_appointments'] - $slot['appointment_count']
            ];
        })->values()->toArray();
        
        Log::info('Telegram бот: сгенерированы слоты', [
            'date' => $date,
            'company_id' => $company->id,
            'total_slots' => count($slots),
            'available_slots' => count($availableSlots)
        ]);
        
        return $availableSlots;
    }

    /**
     * Генерирует временные слоты с полной логикой календаря (аналогично CompanyController)
     */
    private function generateTimeSlots($appointments, $date, $isOwner = false, $company = null)
    {
        $slots = [];
        
        if (!$company) {
            return $slots;
        }

        // Получаем настройки компании
        $settings = $company->getCalendarSettings();
        
        // Проверяем исключения календаря для этой даты
        $dateException = $company->dateExceptions()->forDate($date)->first();
        
        // Получаем активные услуги компании для расчета минимального времени
        $activeServices = $company->services()->where('is_active', true)->get();
        
        // Находим минимальную длительность услуги
        $minServiceDuration = $activeServices->min('duration_minutes') ?? 30;
        
        // Определяем время работы с учетом исключений
        $workTimeRange = null;
        if ($dateException && $dateException->isAllowException()) {
            $workTimeRange = $dateException->getWorkTimeRange();
        }
        
        // Преобразуем время начала и окончания работы
        $startTime = Carbon::parse($workTimeRange ? $workTimeRange['start'] : $settings['work_start_time']);
        $endTime = Carbon::parse($workTimeRange ? $workTimeRange['end'] : $settings['work_end_time']);
        $slotDuration = $settings['appointment_interval'];
        $appointmentBreakTime = $settings['appointment_break_time'] ?? 0;
        $workDays = $settings['work_days'];
        $maxAppointmentsPerSlot = $settings['max_appointments_per_slot'] ?? 1;
        
        // Используем Carbon для работы с датами
        $now = Carbon::now();
        $selectedDate = Carbon::parse($date);
        $isToday = $selectedDate->isSameDay($now);
        
        // Проверяем рабочий день с учетом исключений
        $dayOfWeek = strtolower($selectedDate->format('l'));
        $isWorkDay = in_array($dayOfWeek, $workDays);

        // Проверяем, не праздник ли это
        $isHoliday = $this->isHoliday($selectedDate, $settings['holidays']);

        // Применяем логику исключений календаря
        $finalIsWorkDay = $isWorkDay;
        if ($dateException) {
            if ($dateException->isAllowException()) {
                $finalIsWorkDay = true;
            } elseif ($dateException->isBlockException()) {
                $finalIsWorkDay = false;
            }
        }

        // Если день не рабочий или праздник, возвращаем пустой массив
        if (!$isOwner && (!$finalIsWorkDay || $isHoliday)) {
            return [];
        }

        // Проверяем, не слишком ли далеко в будущем
        if (!$isOwner) {
            $maxDate = $now->copy()->addDays($settings['appointment_days_ahead']);
            if ($selectedDate->greaterThan($maxDate)) {
                return [];
            }
        }

        // Создаем массив занятых интервалов с учетом перерыва между записями
        $occupiedIntervals = [];
        foreach ($appointments as $appointment) {
            if ($appointment->status === 'cancelled') {
                continue;
            }
            
            $appointmentTime = Carbon::parse($appointment->appointment_time);
            $duration = $appointment->duration_minutes ?? $slotDuration;
            $appointmentEnd = $appointmentTime->copy()->addMinutes($duration);
            
            // Если есть перерыв между записями, добавляем его к концу
            if ($appointmentBreakTime > 0) {
                $intervalEnd = $appointmentEnd->copy()->addMinutes($appointmentBreakTime);
            } else {
                $intervalEnd = $appointmentEnd;
            }
            
            $occupiedIntervals[] = [
                'start' => $appointmentTime,
                'end' => $intervalEnd,
                'appointment' => $appointment,
                'duration' => $duration
            ];
        }

        $currentTime = $startTime->copy();
        
        while ($currentTime->lessThan($endTime)) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем, не время ли перерыва
            if ($this->isBreakTime($timeString, $settings['break_times'])) {
                $currentTime->addMinutes($slotDuration);
                continue;
            }
            
            // Создаем полную дату и время для сравнения
            $slotDateTime = Carbon::parse($date . ' ' . $timeString);
            $isPast = $slotDateTime->lessThan($now);
            
            // Проверяем, не пересекается ли текущий слот с занятыми интервалами
            $isBlocked = false;
            $blockingAppointment = null;
            
            foreach ($occupiedIntervals as $interval) {
                if ($currentTime->between($interval['start'], $interval['end'], false)) {
                    $isBlocked = true;
                    $blockingAppointment = $interval['appointment'];
                    break;
                }
            }
            
            // Ищем записи на это конкретное время
            $slotAppointments = $appointments->filter(function($apt) use ($timeString) {
                if ($apt->status === 'cancelled') {
                    return false;
                }
                
                $aptTime = Carbon::parse($apt->appointment_time)->format('H:i');
                return $aptTime === $timeString;
            });
            
            $shouldIncludeSlot = false;
            
            if ($isOwner) {
                $shouldIncludeSlot = !$isBlocked || $slotAppointments->count() > 0;
            } else {
                $shouldIncludeSlot = !$isBlocked;
            }
            
            if ($shouldIncludeSlot) {
                $appointmentCount = $slotAppointments->count();
                $isFullyBooked = $appointmentCount >= $maxAppointmentsPerSlot;
                
                // Проверяем, достаточно ли времени для самой короткой услуги + перерыв
                $hasEnoughTime = $this->hasEnoughTimeForService($currentTime, $endTime, $minServiceDuration, $appointmentBreakTime, $occupiedIntervals);
                
                $slot = [
                    'time' => $timeString,
                    'appointments' => [],
                    'available' => $isOwner ? (!$isFullyBooked && !$isBlocked) : (!$isFullyBooked && !$isPast && $finalIsWorkDay && !$isHoliday && $hasEnoughTime && !$isBlocked),
                    'isPast' => $isPast,
                    'isOwner' => $isOwner,
                    'isWorkDay' => $finalIsWorkDay,
                    'isHoliday' => $isHoliday,
                    'isBlocked' => $isBlocked,
                    'appointment_count' => $appointmentCount,
                    'max_appointments' => $maxAppointmentsPerSlot,
                    'multiple_bookings_enabled' => $maxAppointmentsPerSlot > 1,
                    'has_enough_time' => $hasEnoughTime,
                    'required_time' => $minServiceDuration + $appointmentBreakTime,
                    'exception_info' => $dateException ? [
                        'type' => $dateException->exception_type,
                        'reason' => $dateException->reason,
                        'work_start_time' => $dateException->work_start_time,
                        'work_end_time' => $dateException->work_end_time,
                    ] : null
                ];

                // Добавляем информацию о записях для владельца
                if ($isOwner && $slotAppointments->count() > 0) {
                    $slot['appointments'] = $slotAppointments->map(function($appointment) {
                        return [
                            'id' => $appointment->id,
                            'title' => $appointment->service->name ?? 'Услуга',
                            'client_name' => $appointment->client_name,
                            'client_phone' => $appointment->client_phone,
                            'client_email' => $appointment->client_email,
                            'status' => $appointment->status,
                            'duration' => $appointment->service->duration_minutes ?? 30,
                            'price' => $appointment->service->price ?? 0
                        ];
                    })->values()->toArray();
                }

                $slots[] = $slot;
            }
            
            $currentTime->addMinutes($slotDuration);
        }

        return $slots;
    }

    /**
     * Проверяет, является ли дата праздником
     */
    private function isHoliday($date, $holidays)
    {
        $dateString = $date->format('Y-m-d');
        return in_array($dateString, $holidays);
    }

    /**
     * Проверяет, попадает ли время в перерыв
     */
    private function isBreakTime($time, $breakTimes)
    {
        $timeCarbon = Carbon::createFromFormat('H:i', $time);
        
        foreach ($breakTimes as $breakTime) {
            $breakStart = Carbon::createFromFormat('H:i', $breakTime['start']);
            $breakEnd = Carbon::createFromFormat('H:i', $breakTime['end']);
            
            if ($timeCarbon->between($breakStart, $breakEnd, false)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Проверяет, достаточно ли времени в слоте для выполнения услуги
     */
    private function hasEnoughTimeForService($slotTime, $workEndTime, $serviceDuration, $breakTime, $occupiedIntervals)
    {
        // Время, необходимое для услуги + перерыв
        $requiredTime = $serviceDuration + $breakTime;
        
        // Время окончания услуги с учетом перерыва
        $serviceEndTime = $slotTime->copy()->addMinutes($requiredTime);
        
        // Проверяем, не выходит ли за рабочий день
        if ($serviceEndTime->greaterThan($workEndTime)) {
            return false;
        }
        
        // Проверяем пересечения с занятыми интервалами
        foreach ($occupiedIntervals as $interval) {
            // Если новая запись пересекается с существующей
            if ($slotTime->lessThan($interval['end']) && $serviceEndTime->greaterThan($interval['start'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Устанавливает webhook для бота
     */
    public function setWebhook($company, $webhookUrl)
    {
        if (!$company->telegram_bot_token) {
            Log::error('Попытка установить webhook без токена', [
                'company_id' => $company->id
            ]);
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/setWebhook";
        
        Log::info('Установка webhook', [
            'company_id' => $company->id,
            'webhook_url' => $webhookUrl,
            'api_url' => $url
        ]);
        
        try {
            $response = Http::post($url, [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query']
            ]);
            
            $result = $response->json();
            
            Log::info('Ответ от Telegram API при установке webhook', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response' => $result
            ]);
            
            return $response->successful() ? $result : false;
        } catch (\Exception $e) {
            Log::error('Ошибка установки webhook', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl
            ]);
            return false;
        }
    }

    /**
     * Удаляет webhook для бота
     */
    public function deleteWebhook($company)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/deleteWebhook";
        
        try {
            $response = Http::post($url);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка удаления webhook', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Получает информацию о webhook
     */
    public function getWebhookInfo($company)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/getWebhookInfo";
        
        try {
            $response = Http::get($url);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации webhook', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Возвращает название дня недели на русском
     */
    private function getDayName($date)
    {
        $days = [
            'Monday' => 'Пн',
            'Tuesday' => 'Вт', 
            'Wednesday' => 'Ср',
            'Thursday' => 'Чт',
            'Friday' => 'Пт',
            'Saturday' => 'Сб',
            'Sunday' => 'Вс'
        ];
        
        return $days[$date->format('l')] ?? '';
    }

    /**
     * Отправляет уведомление о новой записи
     */
    public function sendNewAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "🔔 <b>Новая запись!</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📞 <b>Телефон:</b> {$appointment->client_phone}\n";
        if ($appointment->client_email) {
            $message .= "📧 <b>Email:</b> {$appointment->client_email}\n";
        }
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        if ($appointment->notes) {
            $message .= "📝 <b>Примечания:</b> {$appointment->notes}\n";
        }
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }

    /**
     * Отправляет уведомление об отмене записи
     */
    public function sendCancelledAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "❌ <b>Запись отменена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }

    /**
     * Отправляет уведомление о переносе записи
     */
    public function sendRescheduledAppointmentNotification(Appointment $appointment, $oldDate, $oldTime)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "🔄 <b>Запись перенесена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📅 <b>Старая дата:</b> {$oldDate} в {$oldTime}\n";
        $message .= "📅 <b>Новая дата:</b> {$appointment->formatted_date} в {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }

    /**
     * Устанавливает команды для бота
     */
    public function setCommands($company)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/setMyCommands";
        
        $commands = [
            [
                'command' => 'start',
                'description' => 'Главное меню'
            ],
            [
                'command' => 'book',
                'description' => 'Записаться на прием'
            ],
            [
                'command' => 'appointments',
                'description' => 'Мои записи'
            ],
            [
                'command' => 'services',
                'description' => 'Список услуг'
            ],
            [
                'command' => 'schedule',
                'description' => 'Режим работы'
            ],
            [
                'command' => 'contacts',
                'description' => 'Контакты'
            ],
            [
                'command' => 'help',
                'description' => 'Справка'
            ],
            [
                'command' => 'cancel',
                'description' => 'Отменить операцию'
            ]
        ];

        $data = [
            'commands' => json_encode($commands)
        ];

        try {
            $response = Http::post($url, $data);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Ошибка установки команд бота', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
