<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Appointment;
use App\Services\TelegramBotService;
use Carbon\Carbon;

class TelegramWebhookController extends Controller
{
    protected $botService;

    public function __construct(TelegramBotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Обработчик webhook от Telegram
     */
    public function handle(Request $request, $botToken)
    {
        try {
            // Найти компанию по токену бота
            $company = Company::where('telegram_bot_token', $botToken)->first();
            
            if (!$company) {
                Log::warning('Получен webhook для неизвестного бота', ['token' => $botToken]);
                return response('OK', 200);
            }

            $update = $request->all();
            Log::info('Получен Telegram webhook', [
                'company_id' => $company->id,
                'update' => $update
            ]);

            // Обработка callback query (нажатие кнопок)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($company, $update['callback_query']);
            }
            
            // Обработка обычных сообщений
            if (isset($update['message'])) {
                $this->handleMessage($company, $update['message']);
            }

            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Ошибка обработки Telegram webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Обработка callback query (нажатие кнопок)
     */
    private function handleCallbackQuery($company, $callbackQuery)
    {
        $chatId = $callbackQuery['from']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $data = $callbackQuery['data'];

        // Парсим данные callback
        $parts = explode(':', $data);
        $action = $parts[0];

        switch ($action) {
            case 'quick_book':
                $this->showDateSelection($company, $chatId, $messageId);
                break;
                
            case 'my_appointments':
                $this->showUserAppointments($company, $chatId, $messageId);
                break;
                
            case 'work_schedule':
                $this->showWorkSchedule($company, $chatId, $messageId);
                break;
                
            case 'show_services':
                $this->showAllServices($company, $chatId, $messageId);
                break;
                
            case 'show_contacts':
                $this->showCompanyContacts($company, $chatId, $messageId);
                break;
                
            case 'show_help':
                $this->showHelpInline($company, $chatId, $messageId);
                break;
                
            case 'back_to_main':
                $this->showWelcomeMessage($company, $chatId);
                break;
                
            case 'select_date_back':
                $this->showDateSelection($company, $chatId, $messageId);
                break;
                
            case 'select_date':
                $date = $parts[1];
                $this->showTimeSlots($company, $chatId, $messageId, $date);
                break;
                
            case 'select_time':
                $date = $parts[1];
                $time = $parts[2];
                $this->showServiceSelection($company, $chatId, $messageId, $date, $time);
                break;
                
            case 'select_service':
                $date = $parts[1];
                $time = $parts[2];
                $serviceId = $parts[3];
                $this->showContactForm($company, $chatId, $messageId, $date, $time, $serviceId);
                break;
                
            case 'confirm_booking':
                $this->processBooking($company, $callbackQuery);
                break;
                
            case 'cancel_booking':
                $this->cancelBooking($company, $chatId, $messageId);
                break;
                
            case 'cancel_appointment':
                $appointmentId = $parts[1];
                $this->showCancelConfirmation($company, $chatId, $messageId, $appointmentId);
                break;
                
            case 'confirm_cancel':
                $appointmentId = $parts[1];
                $this->cancelUserAppointment($company, $chatId, $messageId, $appointmentId);
                break;
                
            case 'reschedule_appointment':
                $appointmentId = $parts[1];
                $this->showRescheduleDateSelection($company, $chatId, $messageId, $appointmentId);
                break;
                
            case 'reschedule_select_date':
                $appointmentId = $parts[1];
                $newDate = $parts[2];
                $this->showRescheduleTimeSlots($company, $chatId, $messageId, $appointmentId, $newDate);
                break;
                
            case 'reschedule_select_time':
                $appointmentId = $parts[1];
                $newDate = $parts[2];
                $newTime = $parts[3];
                $this->confirmReschedule($company, $chatId, $messageId, $appointmentId, $newDate, $newTime);
                break;
                
            case 'confirm_reschedule':
                $appointmentId = $parts[1];
                $newDate = $parts[2];
                $newTime = $parts[3];
                $this->processReschedule($company, $chatId, $messageId, $appointmentId, $newDate, $newTime);
                break;
                
            case 'leave_review':
                $this->showReviewForm($company, $chatId, $messageId);
                break;
                
            case 'notifications':
                $this->showNotificationSettings($company, $chatId, $messageId);
                break;
                
            case 'toggle_notifications':
                $this->toggleNotifications($company, $chatId, $messageId);
                break;
        }

        // Отвечаем на callback query
        $this->botService->answerCallbackQuery($company, $callbackQuery['id']);
    }

    /**
     * Обработка обычных сообщений
     */
    private function handleMessage($company, $message)
    {
        $chatId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        Log::info('Обработка сообщения от пользователя', [
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'text' => $text
        ]);

        // Проверяем, ожидается ли ввод номера телефона
        if (cache()->get("waiting_phone_{$chatId}")) {
            $this->handlePhoneInput($company, $chatId, $text);
            return;
        }

        // Проверяем, ожидается ли ввод отзыва
        if (cache()->get("waiting_review_{$chatId}")) {
            $this->handleReviewInput($company, $chatId, $text);
            return;
        }

        // Обрабатываем команды
        if ($text === '/start' || $text === '/book') {
            $this->showWelcomeMessage($company, $chatId);
        } elseif ($text === '/help') {
            $this->showHelpMessage($company, $chatId);
        } elseif ($text === '/cancel') {
            $this->showCancelOptions($company, $chatId);
        } elseif ($text === '/appointments' || $text === '/my') {
            // Быстрая команда для просмотра записей
            $this->showMyAppointmentsQuick($company, $chatId);
        } elseif ($text === '/services') {
            // Быстрая команда для просмотра услуг
            $this->showServicesQuick($company, $chatId);
        } elseif ($text === '/contacts') {
            // Быстрая команда для просмотра контактов
            $this->showContactsQuick($company, $chatId);
        } elseif ($text === '/schedule') {
            // Быстрая команда для просмотра расписания
            $this->showScheduleQuick($company, $chatId);
        }
        // Обрабатываем нажатия кнопок интерфейса
        elseif ($text === '📅 Записаться') {
            $this->handleKeyboardBooking($company, $chatId);
        } elseif ($text === '📋 Мои записи') {
            $this->handleKeyboardMyAppointments($company, $chatId);
        } elseif ($text === '💼 Услуги') {
            $this->handleKeyboardServices($company, $chatId);
        } elseif ($text === '🕐 Режим работы') {
            $this->handleKeyboardSchedule($company, $chatId);
        } elseif ($text === '📍 Контакты') {
            $this->handleKeyboardContacts($company, $chatId);
        } elseif ($text === '❓ Помощь') {
            $this->handleKeyboardHelp($company, $chatId);
        } elseif ($text === '❌ Отменить запись') {
            $this->handleKeyboardCancelBooking($company, $chatId);
        } elseif ($text === '🏠 Главное меню') {
            $this->showWelcomeMessage($company, $chatId);
        } else {
            // Проверяем, ожидается ли ввод контактных данных
            $this->handleContactInput($company, $chatId, $text);
        }
    }

    /**
     * Обрабатывает ввод номера телефона
     */
    private function handlePhoneInput($company, $chatId, $text)
    {
        // Простая валидация номера телефона
        if (preg_match('/\+?\d[\d\s\(\)\-]{8,}/', $text)) {
            // Нормализуем номер
            $phone = preg_replace('/[^\d+]/', '', $text);
            
            // Сохраняем номер телефона пользователя
            cache()->put("user_phone_{$chatId}", $phone, 86400); // 24 часа
            cache()->forget("waiting_phone_{$chatId}");
            
            // Показываем записи пользователя
            $this->showUserAppointmentsByPhone($company, $chatId, $phone);
        } else {
            $this->botService->sendMessage($company, $chatId, 
                "❌ Неверный формат номера телефона.\nПожалуйста, введите номер в формате: +7 (XXX) XXX-XX-XX");
        }
    }

    /**
     * Показывает записи пользователя по номеру телефона
     */
    private function showUserAppointmentsByPhone($company, $chatId, $phone)
    {
        $appointments = $company->appointments()
            ->where('client_phone', $phone)
            ->where('appointment_date', '>=', now()->format('Y-m-d'))
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();
            
        $message = "📋 Ваши записи:\n\n";
        
        if ($appointments->count() > 0) {
            foreach ($appointments as $appointment) {
                $status = $this->getStatusEmoji($appointment->status);
                $message .= "{$status} {$appointment->formatted_date} в {$appointment->formatted_time}\n";
                $message .= "💼 {$appointment->service->name}\n";
                $message .= "👤 {$appointment->client_name}\n\n";
            }
            
            $keyboard = [];
            foreach ($appointments as $appointment) {
                if ($appointment->status === 'pending' || $appointment->status === 'confirmed') {
                    $keyboard[] = [
                        ['text' => "🔄 Перенести запись на {$appointment->formatted_date}", 
                         'callback_data' => "reschedule_appointment:{$appointment->id}"],
                        ['text' => "❌ Отменить запись на {$appointment->formatted_date}", 
                         'callback_data' => "cancel_appointment:{$appointment->id}"]
                    ];
                }
            }
            $keyboard[] = [
                ['text' => '📅 Записаться ещё', 'callback_data' => 'quick_book'],
                ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
            ];
        } else {
            $message .= "У вас пока нет записей.\n\n";
            $keyboard = [
                [
                    ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
                ],
                [
                    ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
                ]
            ];
        }
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает приветственное сообщение с быстрыми действиями
     */
    private function showWelcomeMessage($company, $chatId)
    {
        $message = "🏢 Добро пожаловать в {$company->name}!\n\n";
        $message .= "Выберите действие из меню ниже или используйте кнопки:";

        // Inline кнопки для дополнительных действий
        $inlineKeyboard = [
            [
                ['text' => '� Быстрая запись', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '� Оставить отзыв', 'callback_data' => 'leave_review'],
                ['text' => '� Уведомления', 'callback_data' => 'notifications']
            ]
        ];

        // Основная клавиатура интерфейса
        $mainKeyboard = $this->botService->createMainKeyboard();
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode($mainKeyboard),
            // Также добавляем inline кнопки
            'parse_mode' => 'HTML'
        ]);

        // Отправляем дополнительное сообщение с inline кнопками
        $extraMessage = "💡 Дополнительные возможности:";
        $this->botService->sendMessage($company, $chatId, $extraMessage, [
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    /**
     * Показывает выбор даты для записи
     */
    private function showDateSelection($company, $chatId, $messageId)
    {
        $message = "📅 Выберите удобную дату для записи:";
        $keyboard = $this->botService->createDateKeyboard($company);
        
        // Добавляем кнопку "Назад"
        $keyboard[] = [
            ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает записи пользователя
     */
    private function showUserAppointments($company, $chatId, $messageId)
    {
        // Ищем записи по chat_id (можно добавить поле telegram_chat_id в таблицу appointments)
        // Пока покажем последние записи по номеру телефона из кэша или запросим у пользователя
        
        $message = "📋 Ваши записи:\n\n";
        
        // Попробуем найти записи через кэш или предыдущие взаимодействия
        $userPhone = cache()->get("user_phone_{$chatId}");
        
        if ($userPhone) {
            $appointments = $company->appointments()
                ->where('client_phone', $userPhone)
                ->where('appointment_date', '>=', now()->format('Y-m-d'))
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get();
                
            if ($appointments->count() > 0) {
                foreach ($appointments as $appointment) {
                    $status = $this->getStatusEmoji($appointment->status);
                    $message .= "{$status} {$appointment->formatted_date} в {$appointment->formatted_time}\n";
                    $message .= "💼 {$appointment->service->name}\n";
                    $message .= "📞 {$appointment->client_phone}\n\n";
                }
                
                $keyboard = [];
                foreach ($appointments as $appointment) {
                    if ($appointment->status === 'pending' || $appointment->status === 'confirmed') {
                        $keyboard[] = [
                            ['text' => "🔄 Перенести запись на {$appointment->formatted_date}", 
                             'callback_data' => "reschedule_appointment:{$appointment->id}"],
                            ['text' => "❌ Отменить запись на {$appointment->formatted_date}", 
                             'callback_data' => "cancel_appointment:{$appointment->id}"]
                        ];
                    }
                }
                $keyboard[] = [
                    ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
                ];
            } else {
                $message .= "У вас пока нет записей.\n\n";
                $keyboard = [
                    [
                        ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
                    ],
                    [
                        ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
                    ]
                ];
            }
        } else {
            $message .= "Для просмотра записей укажите ваш номер телефона:\n";
            $message .= "Отправьте сообщение в формате: +7 (XXX) XXX-XX-XX";
            
            // Сохраняем состояние ожидания телефона
            cache()->put("waiting_phone_{$chatId}", true, 600);
            
            $keyboard = [
                [
                    ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
                ]
            ];
        }
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает режим работы
     */
    private function showWorkSchedule($company, $chatId, $messageId)
    {
        $settings = $company->getCalendarSettings();
        
        $message = "🕐 Режим работы {$company->name}:\n\n";
        
        $daysRu = [
            'monday' => 'Понедельник',
            'tuesday' => 'Вторник', 
            'wednesday' => 'Среда',
            'thursday' => 'Четверг',
            'friday' => 'Пятница',
            'saturday' => 'Суббота',
            'sunday' => 'Воскресенье'
        ];
        
        foreach ($daysRu as $day => $dayRu) {
            if (in_array($day, $settings['work_days'])) {
                $message .= "✅ {$dayRu}: {$settings['work_start_time']} - {$settings['work_end_time']}\n";
            } else {
                $message .= "❌ {$dayRu}: Выходной\n";
            }
        }
        
        if (!empty($settings['break_times'])) {
            $message .= "\n🍽 Перерывы:\n";
            foreach ($settings['break_times'] as $break) {
                $message .= "• {$break['start']} - {$break['end']}\n";
            }
        }
        
        // Показываем ближайшие исключения календаря
        $upcomingExceptions = $company->dateExceptions()
            ->where('exception_date', '>=', now()->format('Y-m-d'))
            ->orderBy('exception_date')
            ->limit(5)
            ->get();
            
        if ($upcomingExceptions->count() > 0) {
            $message .= "\n📅 Особые дни:\n";
            foreach ($upcomingExceptions as $exception) {
                $date = $exception->exception_date->format('d.m.Y');
                if ($exception->isAllowException()) {
                    $workTime = $exception->getWorkTimeRange();
                    $message .= "✅ {$date}: Работаем {$workTime['start']} - {$workTime['end']}";
                    if ($exception->reason) {
                        $message .= " ({$exception->reason})";
                    }
                    $message .= "\n";
                } else {
                    $message .= "❌ {$date}: Не работаем";
                    if ($exception->reason) {
                        $message .= " ({$exception->reason})";
                    }
                    $message .= "\n";
                }
            }
        }
        
        $message .= "\n💡 Интервал записи: {$settings['appointment_interval']} минут";
        $message .= "\n📆 Записываться можно на {$settings['appointment_days_ahead']} дней вперед";
        
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает все услуги компании
     */
    private function showAllServices($company, $chatId, $messageId)
    {
        $services = $company->services()->where('is_active', true)->get();
        
        $message = "💼 Наши услуги:\n\n";
        
        foreach ($services as $service) {
            $message .= "• {$service->name}\n";
            if ($service->description) {
                $message .= "  {$service->description}\n";
            }
            $message .= "  💰 {$service->formatted_price}\n";
            $message .= "  ⏱ {$service->duration_minutes} мин\n\n";
        }
        
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает контакты компании
     */
    private function showCompanyContacts($company, $chatId, $messageId)
    {
        $message = "📍 Контакты {$company->name}:\n\n";
        
        if ($company->phone) {
            $message .= "📞 Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "📧 Email: {$company->email}\n";
        }
        if ($company->address) {
            $message .= "📍 Адрес: {$company->address}\n";
        }
        if ($company->website) {
            $message .= "🌐 Сайт: {$company->website}\n";
        }
        
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает справку через inline кнопки
     */
    private function showHelpInline($company, $chatId, $messageId)
    {
        $message = "❓ Справка по боту {$company->name}\n\n";
        $message .= "Доступные функции:\n";
        $message .= "📅 Записаться на прием - выбрать дату и время\n";
        $message .= "📋 Мои записи - просмотр и отмена записей\n";
        $message .= "🕐 Режим работы - часы работы компании\n";
        $message .= "💼 Наши услуги - список всех услуг\n";
        $message .= "📍 Контакты - контактная информация\n\n";
        $message .= "Также доступны команды:\n";
        $message .= "/start - Главное меню\n";
        $message .= "/book - Быстрая запись\n";
        $message .= "/help - Эта справка\n";
        $message .= "/cancel - Отменить текущую операцию";
        
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает подтверждение отмены записи
     */
    private function showCancelConfirmation($company, $chatId, $messageId, $appointmentId)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }
        
        $message = "❓ Вы уверены, что хотите отменить запись?\n\n";
        $message .= "📅 Дата: {$appointment->formatted_date}\n";
        $message .= "🕐 Время: {$appointment->formatted_time}\n";
        $message .= "💼 Услуга: {$appointment->service->name}";
        
        $keyboard = [
            [
                ['text' => '✅ Да, отменить', 'callback_data' => "confirm_cancel:{$appointmentId}"],
                ['text' => '❌ Нет, оставить', 'callback_data' => 'my_appointments']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Отменяет запись пользователя
     */
    private function cancelUserAppointment($company, $chatId, $messageId, $appointmentId)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }
        
        $appointment->update(['status' => 'cancelled']);
        
        $message = "✅ Запись успешно отменена!\n\n";
        $message .= "📅 Отменённая запись:\n";
        $message .= "Дата: {$appointment->formatted_date}\n";
        $message .= "Время: {$appointment->formatted_time}\n";
        $message .= "Услуга: {$appointment->service->name}";
        
        $keyboard = [
            [
                ['text' => '📅 Записаться снова', 'callback_data' => 'quick_book']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
        
        // Уведомляем владельца
        if ($company->telegram_notifications_enabled && $company->telegram_chat_id) {
            $ownerMessage = "❌ Отмена записи через Telegram-бот!\n\n";
            $ownerMessage .= "👤 Клиент: {$appointment->client_name}\n";
            $ownerMessage .= "📞 Телефон: {$appointment->client_phone}\n";
            $ownerMessage .= "📅 Дата: {$appointment->formatted_date}\n";
            $ownerMessage .= "🕐 Время: {$appointment->formatted_time}\n";
            $ownerMessage .= "💼 Услуга: {$appointment->service->name}";

            $this->botService->sendMessage($company, $company->telegram_chat_id, $ownerMessage);
        }
    }

    /**
     * Возвращает эмодзи для статуса записи
     */
    private function getStatusEmoji($status)
    {
        return match($status) {
            'pending' => '⏳',
            'confirmed' => '✅',
            'completed' => '🏁',
            'cancelled' => '❌',
            'no_show' => '👻',
            default => '❓'
        };
    }
    private function showTimeSlots($company, $chatId, $messageId, $date)
    {
        $slots = $this->botService->getAvailableTimeSlots($company, $date);
        
        if (empty($slots)) {
            $message = "❌ На выбранную дату ({$date}) нет свободного времени.\n\nВыберите другую дату:";
            $keyboard = $this->botService->createDateKeyboard($company);
            // Добавляем кнопку "Назад в меню"
            $keyboard[] = [
                ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
            ];
        } else {
            $message = "🕐 Выберите удобное время на {$date}:";
            $keyboard = $this->botService->createTimeKeyboard($date, $slots);
            // Добавляем кнопку "Назад в меню" в существующую клавиатуру
            $keyboard[] = [
                ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
            ];
        }

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает выбор услуги
     */
    private function showServiceSelection($company, $chatId, $messageId, $date, $time)
    {
        $services = $company->services()->where('is_active', true)->get();
        
        $message = "💼 Выберите услугу на {$date} в {$time}:";
        $keyboard = $this->botService->createServiceKeyboard($date, $time, $services);
        
        // Добавляем кнопку "Назад в меню"
        $keyboard[] = [
            ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
        ];

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает форму для ввода контактов
     */
    private function showContactForm($company, $chatId, $messageId, $date, $time, $serviceId)
    {
        $service = $company->services()->find($serviceId);
        
        $message = "✍️ Данные записи:\n\n";
        $message .= "📅 Дата: {$date}\n";
        $message .= "🕐 Время: {$time}\n";
        $message .= "💼 Услуга: {$service->name}\n";
        $message .= "💰 Стоимость: {$service->formatted_price}\n\n";
        $message .= "Пожалуйста, отправьте ваши контактные данные в формате:\n";
        $message .= "Имя Фамилия\n+7 (XXX) XXX-XX-XX\nemail@example.com (необязательно)";

        // Нормализуем время в формат HH:MM для корректного сохранения в БД
        $normalizedTime = strlen($time) <= 2 ? $time . ':00' : $time;

        // Сохраняем данные в сессии (можно использовать Redis или БД)
        cache()->put("booking_data_{$chatId}", [
            'date' => $date,
            'time' => $normalizedTime,
            'service_id' => $serviceId,
            'step' => 'waiting_contact'
        ], 1800); // 30 минут

        $this->botService->editMessage($company, $chatId, $messageId, $message);
    }

    /**
     * Обрабатывает ввод контактных данных
     */
    private function handleContactInput($company, $chatId, $text)
    {
        $bookingData = cache()->get("booking_data_{$chatId}");
        
        if (!$bookingData || $bookingData['step'] !== 'waiting_contact') {
            return;
        }

        // Парсим контактные данные
        $lines = explode("\n", trim($text));
        $name = $lines[0] ?? '';
        $phone = $lines[1] ?? '';
        $email = $lines[2] ?? '';

        // Валидация
        if (empty($name) || empty($phone)) {
            $this->botService->sendMessage($company, $chatId, 
                "❌ Пожалуйста, укажите имя и телефон в правильном формате.");
            return;
        }

        // Сохраняем контактные данные
        $bookingData['name'] = $name;
        $bookingData['phone'] = $phone;
        $bookingData['email'] = $email;
        $bookingData['step'] = 'confirm';
        
        cache()->put("booking_data_{$chatId}", $bookingData, 1800);

        // Показываем подтверждение
        $this->showBookingConfirmation($company, $chatId, $bookingData);
    }

    /**
     * Показывает подтверждение записи
     */
    private function showBookingConfirmation($company, $chatId, $bookingData)
    {
        $service = $company->services()->find($bookingData['service_id']);
        
        $message = "✅ Подтвердите запись:\n\n";
        $message .= "👤 Клиент: {$bookingData['name']}\n";
        $message .= "📞 Телефон: {$bookingData['phone']}\n";
        if (!empty($bookingData['email'])) {
            $message .= "📧 Email: {$bookingData['email']}\n";
        }
        $message .= "📅 Дата: {$bookingData['date']}\n";
        $message .= "🕐 Время: {$bookingData['time']}\n";
        $message .= "💼 Услуга: {$service->name}\n";
        $message .= "💰 Стоимость: {$service->formatted_price}";

        $keyboard = [
            [
                ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_booking'],
                ['text' => '❌ Отменить', 'callback_data' => 'cancel_booking']
            ],
            [
                ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
            ]
        ];

        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает подтверждение записи
     */
    private function processBooking($company, $callbackQuery)
    {
        $chatId = $callbackQuery['from']['id'];
        $bookingData = cache()->get("booking_data_{$chatId}");
        
        if (!$bookingData) {
            $this->botService->sendMessage($company, $chatId, 
                "❌ Данные записи устарели. Пожалуйста, начните заново с /start");
            return;
        }

        try {
            // Дополнительная проверка доступности слота
            $selectedDate = Carbon::parse($bookingData['date']);
            $service = $company->services()->find($bookingData['service_id']);
            
            if (!$service) {
                $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], 
                    "❌ Выбранная услуга больше недоступна.\n\nПожалуйста, начните заново с /start");
                return;
            }
            
            // Проверяем, что дата все еще рабочая (с учетом исключений)
            if (!$company->isWorkDay($selectedDate)) {
                $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], 
                    "❌ К сожалению, выбранная дата больше недоступна для записи.\n\nПожалуйста, выберите другую дату.");
                return;
            }
            
            // Проверяем, что временной слот все еще доступен
            $availableSlots = $this->botService->getAvailableTimeSlots($company, $bookingData['date']);
            $timeAvailable = collect($availableSlots)->contains('time', $bookingData['time']);
            
            if (!$timeAvailable) {
                $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], 
                    "❌ К сожалению, выбранное время уже занято.\n\nПожалуйста, выберите другое время или дату.");
                return;
            }

            // Создаем запись
            $appointment = Appointment::create([
                'company_id' => $company->id,
                'service_id' => $bookingData['service_id'],
                'client_name' => $bookingData['name'],
                'client_phone' => $bookingData['phone'],
                'client_email' => $bookingData['email'],
                'appointment_date' => $bookingData['date'],
                'appointment_time' => $bookingData['time'],
                'duration_minutes' => $service->duration_minutes,
                'status' => 'pending',
                'notes' => 'Запись через Telegram-бот'
            ]);

            // Очищаем кэш
            cache()->forget("booking_data_{$chatId}");

            $message = "🎉 Запись успешно создана!\n\n";
            $message .= "📋 Номер записи: #{$appointment->id}\n";
            $message .= "📅 Дата: {$appointment->formatted_date}\n";
            $message .= "🕐 Время: {$appointment->formatted_time}\n";
            $message .= "💼 Услуга: {$service->name}\n";
            $message .= "💰 Стоимость: {$service->formatted_price}\n\n";
            $message .= "📞 Мы свяжемся с вами для подтверждения.\n\n";
            $message .= "Для новой записи отправьте /start";

            $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], $message);

            // Отправляем уведомление владельцу
            if ($company->telegram_notifications_enabled && $company->telegram_chat_id) {
                $ownerMessage = "🔔 Новая запись через Telegram-бот!\n\n";
                $ownerMessage .= "👤 Клиент: {$appointment->client_name}\n";
                $ownerMessage .= "📞 Телефон: {$appointment->client_phone}\n";
                if ($appointment->client_email) {
                    $ownerMessage .= "📧 Email: {$appointment->client_email}\n";
                }
                $ownerMessage .= "📅 Дата: {$appointment->formatted_date}\n";
                $ownerMessage .= "🕐 Время: {$appointment->formatted_time}\n";
                $ownerMessage .= "💼 Услуга: {$appointment->service->name}\n";
                $ownerMessage .= "💰 Стоимость: {$service->formatted_price}";

                $this->botService->sendMessage($company, $company->telegram_chat_id, $ownerMessage);
            }

        } catch (\Exception $e) {
            Log::error('Ошибка создания записи через Telegram', [
                'error' => $e->getMessage(),
                'booking_data' => $bookingData,
                'company_id' => $company->id
            ]);

            $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], 
                "❌ Произошла ошибка при создании записи. Пожалуйста, попробуйте позже или свяжитесь с нами напрямую.");
        }
    }

    /**
     * Отменяет процесс записи
     */
    private function cancelBooking($company, $chatId, $messageId)
    {
        cache()->forget("booking_data_{$chatId}");
        
        $message = "❌ Запись отменена.\n\nВы вернулись в главное меню.";
        
        // Возвращаем основную клавиатуру
        $mainKeyboard = $this->botService->createMainKeyboard();
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode($mainKeyboard)
        ]);
    }

    /**
     * Показывает справочную информацию
     */
    private function showHelpMessage($company, $chatId)
    {
        $message = "ℹ️ Справка по боту {$company->name}\n\n";
        $message .= "📋 Основные команды:\n";
        $message .= "/start - Главное меню с быстрыми действиями\n";
        $message .= "/book - Записаться на прием\n";
        $message .= "/help - Показать эту справку\n\n";
        $message .= "🚀 Быстрые команды:\n";
        $message .= "/appointments или /my - Мои записи\n";
        $message .= "/services - Список услуг\n";
        $message .= "/contacts - Контактная информация\n";
        $message .= "/schedule - Режим работы\n";
        $message .= "/cancel - Отменить текущую операцию\n\n";
        $message .= "💡 Совет: Используйте /start для доступа к интерактивному меню с кнопками!\n\n";
        $message .= "📞 Контакты:\n";
        if ($company->phone) {
            $message .= "Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "Email: {$company->email}\n";
        }
        if ($company->address) {
            $message .= "Адрес: {$company->address}\n";
        }

        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Показывает опции отмены записи
     */
    private function showCancelOptions($company, $chatId)
    {
        // Здесь можно добавить функционал отмены существующих записей
        $message = "Для отмены записи, пожалуйста, свяжитесь с нами:\n\n";
        if ($company->phone) {
            $message .= "📞 Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "📧 Email: {$company->email}";
        }

        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Быстрая команда для просмотра записей
     */
    private function showMyAppointmentsQuick($company, $chatId)
    {
        $userPhone = cache()->get("user_phone_{$chatId}");
        
        if ($userPhone) {
            $this->showUserAppointmentsByPhone($company, $chatId, $userPhone);
        } else {
            $message = "📋 Для просмотра записей укажите ваш номер телефона:\n";
            $message .= "Отправьте сообщение в формате: +7 (XXX) XXX-XX-XX\n\n";
            $message .= "Или используйте главное меню: /start";
            
            cache()->put("waiting_phone_{$chatId}", true, 600);
            $this->botService->sendMessage($company, $chatId, $message);
        }
    }

    /**
     * Быстрая команда для просмотра услуг
     */
    private function showServicesQuick($company, $chatId)
    {
        $services = $company->services()->where('is_active', true)->get();
        
        $message = "💼 Наши услуги:\n\n";
        
        foreach ($services as $service) {
            $message .= "• {$service->name}\n";
            if ($service->description) {
                $message .= "  {$service->description}\n";
            }
            $message .= "  💰 {$service->formatted_price}\n";
            $message .= "  ⏱ {$service->duration_minutes} мин\n\n";
        }
        
        $message .= "Для записи: /book";
        
        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Быстрая команда для просмотра контактов
     */
    private function showContactsQuick($company, $chatId)
    {
        $message = "📍 Контакты {$company->name}:\n\n";
        
        if ($company->phone) {
            $message .= "📞 Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "📧 Email: {$company->email}\n";
        }
        if ($company->address) {
            $message .= "📍 Адрес: {$company->address}\n";
        }
        if ($company->website) {
            $message .= "🌐 Сайт: {$company->website}\n";
        }
        
        $message .= "\nДля записи: /book";
        
        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Быстрая команда для просмотра расписания
     */
    private function showScheduleQuick($company, $chatId)
    {
        $settings = $company->getCalendarSettings();
        
        $message = "🕐 Режим работы {$company->name}:\n\n";
        
        $daysRu = [
            'monday' => 'Понедельник',
            'tuesday' => 'Вторник', 
            'wednesday' => 'Среда',
            'thursday' => 'Четверг',
            'friday' => 'Пятница',
            'saturday' => 'Суббота',
            'sunday' => 'Воскресенье'
        ];
        
        foreach ($daysRu as $day => $dayRu) {
            if (in_array($day, $settings['work_days'])) {
                $message .= "✅ {$dayRu}: {$settings['work_start_time']} - {$settings['work_end_time']}\n";
            } else {
                $message .= "❌ {$dayRu}: Выходной\n";
            }
        }
        
        if (!empty($settings['break_times'])) {
            $message .= "\n🍽 Перерывы:\n";
            foreach ($settings['break_times'] as $break) {
                $message .= "• {$break['start']} - {$break['end']}\n";
            }
        }
        
        $message .= "\nДля записи: /book";
        
        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Обрабатывает нажатие кнопки "📅 Записаться"
     */
    private function handleKeyboardBooking($company, $chatId)
    {
        $message = "📅 Выберите удобную дату для записи:";
        $keyboard = $this->botService->createDateKeyboard($company);
        
        // Добавляем кнопку "Назад"
        $keyboard[] = [
            ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
        ];
        
        // Меняем клавиатуру на клавиатуру процесса записи
        $bookingKeyboard = $this->botService->createBookingKeyboard();
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode($bookingKeyboard),
            'parse_mode' => 'HTML'
        ]);

        // Отправляем inline кнопки с датами
        $this->botService->sendMessage($company, $chatId, "📅 Доступные даты:", [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает нажатие кнопки "📋 Мои записи"
     */
    private function handleKeyboardMyAppointments($company, $chatId)
    {
        $userPhone = cache()->get("user_phone_{$chatId}");
        
        if ($userPhone) {
            $this->showUserAppointmentsByPhone($company, $chatId, $userPhone);
        } else {
            $message = "📋 Для просмотра записей укажите ваш номер телефона:\n";
            $message .= "Отправьте сообщение в формате: +7 (XXX) XXX-XX-XX";
            
            cache()->put("waiting_phone_{$chatId}", true, 600);
            $this->botService->sendMessage($company, $chatId, $message);
        }
    }

    /**
     * Обрабатывает нажатие кнопки "💼 Услуги"
     */
    private function handleKeyboardServices($company, $chatId)
    {
        $services = $company->services()->where('is_active', true)->get();
        
        $message = "💼 Наши услуги:\n\n";
        
        foreach ($services as $service) {
            $message .= "• {$service->name}\n";
            if ($service->description) {
                $message .= "  {$service->description}\n";
            }
            $message .= "  💰 {$service->formatted_price}\n";
            $message .= "  ⏱ {$service->duration_minutes} мин\n\n";
        }
        
        // Добавляем inline кнопку для записи
        $keyboard = [
            [
                ['text' => '📅 Записаться на услугу', 'callback_data' => 'quick_book']
            ]
        ];
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает нажатие кнопки "🕐 Режим работы"
     */
    private function handleKeyboardSchedule($company, $chatId)
    {
        $settings = $company->getCalendarSettings();
        
        $message = "🕐 Режим работы {$company->name}:\n\n";
        
        $daysRu = [
            'monday' => 'Понедельник',
            'tuesday' => 'Вторник', 
            'wednesday' => 'Среда',
            'thursday' => 'Четверг',
            'friday' => 'Пятница',
            'saturday' => 'Суббота',
            'sunday' => 'Воскресенье'
        ];
        
        foreach ($daysRu as $day => $dayRu) {
            if (in_array($day, $settings['work_days'])) {
                $message .= "✅ {$dayRu}: {$settings['work_start_time']} - {$settings['work_end_time']}\n";
            } else {
                $message .= "❌ {$dayRu}: Выходной\n";
            }
        }
        
        if (!empty($settings['break_times'])) {
            $message .= "\n🍽 Перерывы:\n";
            foreach ($settings['break_times'] as $break) {
                $message .= "• {$break['start']} - {$break['end']}\n";
            }
        }
        
        // Добавляем inline кнопку для записи
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book']
            ]
        ];
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает нажатие кнопки "📍 Контакты"
     */
    private function handleKeyboardContacts($company, $chatId)
    {
        $message = "📍 Контакты {$company->name}:\n\n";
        
        if ($company->phone) {
            $message .= "📞 Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "📧 Email: {$company->email}\n";
        }
        if ($company->address) {
            $message .= "📍 Адрес: {$company->address}\n";
        }
        if ($company->website) {
            $message .= "🌐 Сайт: {$company->website}\n";
        }
        
        // Добавляем inline кнопки для действий
        $keyboard = [
            [
                ['text' => '📅 Записаться', 'callback_data' => 'quick_book'],
                ['text' => '🕐 Режим работы', 'callback_data' => 'work_schedule']
            ]
        ];
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает нажатие кнопки "❓ Помощь"
     */
    private function handleKeyboardHelp($company, $chatId)
    {
        $message = "❓ Справка по боту {$company->name}\n\n";
        $message .= "🎯 Используйте кнопки меню внизу экрана:\n\n";
        $message .= "📅 Записаться - выбрать дату и время\n";
        $message .= "📋 Мои записи - просмотр и отмена записей\n";
        $message .= "💼 Услуги - список всех услуг\n";
        $message .= "🕐 Режим работы - часы работы\n";
        $message .= "📍 Контакты - наша контактная информация\n";
        $message .= "❓ Помощь - эта справка\n\n";
        $message .= "📝 Команды:\n";
        $message .= "/start - Главное меню\n";
        $message .= "/book - Быстрая запись\n";
        $message .= "/appointments - Мои записи\n";
        $message .= "/help - Справка\n\n";
        $message .= "💡 Совет: Все основные функции доступны через кнопки меню!";
        
        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Обрабатывает нажатие кнопки "❌ Отменить запись"
     */
    private function handleKeyboardCancelBooking($company, $chatId)
    {
        // Очищаем данные записи
        cache()->forget("booking_data_{$chatId}");
        cache()->forget("waiting_phone_{$chatId}");
        
        $message = "❌ Процесс записи отменен.\n\nВы вернулись в главное меню.";
        
        // Возвращаем основную клавиатуру
        $mainKeyboard = $this->botService->createMainKeyboard();
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode($mainKeyboard)
        ]);
    }

    /**
     * Показывает форму для оставления отзыва
     */
    private function showReviewForm($company, $chatId, $messageId)
    {
        $message = "⭐ Оставить отзыв о {$company->name}\n\n";
        $message .= "Мы будем благодарны за ваш отзыв!\n";
        $message .= "Напишите ваше мнение о качестве обслуживания.";
        
        cache()->put("waiting_review_{$chatId}", true, 1800); // 30 минут
        
        $keyboard = [
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает настройки уведомлений
     */
    private function showNotificationSettings($company, $chatId, $messageId)
    {
        // Проверяем, включены ли уведомления для пользователя
        $notificationsEnabled = cache()->get("notifications_{$chatId}", false);
        
        $message = "🔔 Настройки уведомлений\n\n";
        $message .= "Получайте уведомления о:\n";
        $message .= "• Подтверждении записи\n";
        $message .= "• Напоминании за час до приема\n";
        $message .= "• Изменениях в расписании\n\n";
        $message .= "Текущий статус: " . ($notificationsEnabled ? "✅ Включены" : "❌ Отключены");
        
        $keyboard = [
            [
                ['text' => $notificationsEnabled ? '🔕 Отключить' : '🔔 Включить', 
                 'callback_data' => 'toggle_notifications']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Переключает настройки уведомлений
     */
    private function toggleNotifications($company, $chatId, $messageId)
    {
        $notificationsEnabled = cache()->get("notifications_{$chatId}", false);
        $newStatus = !$notificationsEnabled;
        
        cache()->put("notifications_{$chatId}", $newStatus, 86400 * 365); // 1 год
        
        $message = "🔔 Настройки уведомлений обновлены!\n\n";
        $message .= "Уведомления: " . ($newStatus ? "✅ Включены" : "❌ Отключены") . "\n\n";
        
        if ($newStatus) {
            $message .= "Теперь вы будете получать уведомления о записях и напоминания.";
        } else {
            $message .= "Уведомления отключены. Вы можете включить их в любое время.";
        }
        
        $keyboard = [
            [
                ['text' => $newStatus ? '🔕 Отключить' : '🔔 Включить', 
                 'callback_data' => 'toggle_notifications']
            ],
            [
                ['text' => '← Назад в меню', 'callback_data' => 'back_to_main']
            ]
        ];
        
        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает ввод отзыва
     */
    private function handleReviewInput($company, $chatId, $text)
    {
        cache()->forget("waiting_review_{$chatId}");
        
        // Здесь можно сохранить отзыв в базу данных
        // Пока просто отправим уведомление владельцу
        
        $message = "⭐ Спасибо за ваш отзыв!\n\n";
        $message .= "Ваше мнение очень важно для нас. Мы обязательно учтем ваши пожелания для улучшения качества обслуживания.";
        
        // Возвращаем основную клавиатуру
        $mainKeyboard = $this->botService->createMainKeyboard();
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode($mainKeyboard)
        ]);
        
        // Отправляем отзыв владельцу
        if ($company->telegram_notifications_enabled && $company->telegram_chat_id) {
            $ownerMessage = "⭐ Новый отзыв через Telegram-бот!\n\n";
            $ownerMessage .= "👤 От пользователя: {$chatId}\n";
            $ownerMessage .= "💬 Отзыв: {$text}";

            $this->botService->sendMessage($company, $company->telegram_chat_id, $ownerMessage);
        }
    }

    /**
     * Показывает опции для переноса записи
     */
    private function showRescheduleOptions($company, $chatId, $messageId, $appointmentId)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment || $appointment->status === 'cancelled') {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена или уже отменена.");
            return;
        }

        $message = "🔄 Перенос записи:\n\n";
        $message .= "📅 Текущая дата: {$appointment->formatted_date}\n";
        $message .= "🕐 Текущее время: {$appointment->formatted_time}\n";
        $message .= "💼 Услуга: {$appointment->service->name}\n";
        $message .= "👤 Клиент: {$appointment->client_name}\n\n";
        $message .= "Выберите действие:";

        $keyboard = [
            [
                ['text' => '📅 Выбрать новую дату', 'callback_data' => "reschedule_date:{$appointmentId}"]
            ],
            [
                ['text' => '← Назад к записям', 'callback_data' => 'my_appointments'],
                ['text' => '🏠 Главное меню', 'callback_data' => 'back_to_main']
            ]
        ];

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает выбор новой даты для переноса
     */
    private function showRescheduleDateSelection($company, $chatId, $messageId, $appointmentId)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }

        $message = "📅 Выберите новую дату для записи:";
        $keyboard = $this->createRescheduleDateKeyboard($company, $appointmentId);
        
        // Добавляем кнопку "Назад"
        $keyboard[] = [
            ['text' => '← Назад', 'callback_data' => "reschedule_appointment:{$appointmentId}"]
        ];

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Создает клавиатуру с датами для переноса (исключает текущую дату записи)
     */
    private function createRescheduleDateKeyboard($company, $appointmentId)
    {
        $appointment = $company->appointments()->find($appointmentId);
        $currentDate = $appointment->appointment_date->format('Y-m-d');
        
        $settings = $company->getCalendarSettings();
        $daysAhead = $settings['appointment_days_ahead'];
        
        $keyboard = [];
        $today = Carbon::now();
        $row = [];
        
        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $today->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');
            
            // Пропускаем текущую дату записи
            if ($dateString === $currentDate) {
                continue;
            }

            // Используем метод компании для проверки рабочего дня с учетом исключений
            if (!$company->isWorkDay($date)) {
                continue;
            }
            
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
                'callback_data' => "reschedule_select_date:{$appointmentId}:{$dateString}"
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
     * Показывает доступные временные слоты для новой даты
     */
    private function showRescheduleTimeSlots($company, $chatId, $messageId, $appointmentId, $newDate)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }

        $slots = $this->botService->getAvailableTimeSlots($company, $newDate);
        
        if (empty($slots)) {
            $message = "❌ На выбранную дату ({$newDate}) нет свободного времени.\n\nВыберите другую дату:";
            $keyboard = $this->createRescheduleDateKeyboard($company, $appointmentId);
            $keyboard[] = [
                ['text' => '← Назад', 'callback_data' => "reschedule_appointment:{$appointmentId}"]
            ];
        } else {
            $message = "🕐 Выберите новое время на {$newDate}:";
            $keyboard = $this->createRescheduleTimeKeyboard($appointmentId, $newDate, $slots);
            $keyboard[] = [
                ['text' => '← Назад к датам', 'callback_data' => "reschedule_date:{$appointmentId}"]
            ];
        }

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Создает клавиатуру с временными слотами для переноса
     */
    private function createRescheduleTimeKeyboard($appointmentId, $date, $slots)
    {
        $keyboard = [];
        $row = [];
        
        foreach ($slots as $slot) {
            $row[] = [
                'text' => $slot['time'],
                'callback_data' => "reschedule_select_time:{$appointmentId}:{$date}:{$slot['time']}"
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
        
        return $keyboard;
    }

    /**
     * Показывает подтверждение переноса
     */
    private function confirmReschedule($company, $chatId, $messageId, $appointmentId, $newDate, $newTime)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }

        $message = "✅ Подтвердите перенос записи:\n\n";
        $message .= "📋 Номер записи: #{$appointment->id}\n";
        $message .= "💼 Услуга: {$appointment->service->name}\n";
        $message .= "👤 Клиент: {$appointment->client_name}\n\n";
        $message .= "🔄 ИЗМЕНЕНИЯ:\n";
        $message .= "📅 С даты: {$appointment->formatted_date} → " . Carbon::parse($newDate)->format('d.m.Y') . "\n";
        $message .= "🕐 Со времени: {$appointment->formatted_time} → {$newTime}\n\n";
        $message .= "Подтверждаете перенос?";

        $keyboard = [
            [
                ['text' => '✅ Да, перенести', 'callback_data' => "confirm_reschedule:{$appointmentId}:{$newDate}:{$newTime}"],
                ['text' => '❌ Отмена', 'callback_data' => "reschedule_appointment:{$appointmentId}"]
            ]
        ];

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает перенос записи
     */
    private function processReschedule($company, $chatId, $messageId, $appointmentId, $newDate, $newTime)
    {
        $appointment = $company->appointments()->find($appointmentId);
        
        if (!$appointment) {
            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Запись не найдена.");
            return;
        }

        try {
            // Проверяем, что новое время все еще доступно
            $availableSlots = $this->botService->getAvailableTimeSlots($company, $newDate);
            $timeAvailable = collect($availableSlots)->contains('time', $newTime);
            
            if (!$timeAvailable) {
                $this->botService->editMessage($company, $chatId, $messageId, 
                    "❌ К сожалению, выбранное время уже занято.\n\nПожалуйста, выберите другое время.");
                return;
            }

            $oldDate = $appointment->formatted_date;
            $oldTime = $appointment->formatted_time;

            // Обновляем запись
            $appointment->update([
                'appointment_date' => $newDate,
                'appointment_time' => $newTime
            ]);

            $message = "🎉 Запись успешно перенесена!\n\n";
            $message .= "📋 Номер записи: #{$appointment->id}\n";
            $message .= "📅 Новая дата: {$appointment->fresh()->formatted_date}\n";
            $message .= "🕐 Новое время: {$appointment->fresh()->formatted_time}\n";
            $message .= "💼 Услуга: {$appointment->service->name}\n\n";
            $message .= "📞 При необходимости мы свяжемся с вами для подтверждения.";

            $this->botService->editMessage($company, $chatId, $messageId, $message);

            // Отправляем уведомление владельцу
            if ($company->telegram_notifications_enabled && $company->telegram_chat_id) {
                $ownerMessage = "🔄 Запись перенесена через Telegram-бот!\n\n";
                $ownerMessage .= "👤 Клиент: {$appointment->client_name}\n";
                $ownerMessage .= "📞 Телефон: {$appointment->client_phone}\n";
                $ownerMessage .= "💼 Услуга: {$appointment->service->name}\n\n";
                $ownerMessage .= "🔄 ИЗМЕНЕНИЯ:\n";
                $ownerMessage .= "📅 С {$oldDate} на {$appointment->fresh()->formatted_date}\n";
                $ownerMessage .= "🕐 С {$oldTime} на {$appointment->fresh()->formatted_time}";

                $this->botService->sendMessage($company, $company->telegram_chat_id, $ownerMessage);
            }

        } catch (\Exception $e) {
            Log::error('Ошибка переноса записи через Telegram', [
                'error' => $e->getMessage(),
                'appointment_id' => $appointmentId,
                'new_date' => $newDate,
                'new_time' => $newTime
            ]);

            $this->botService->editMessage($company, $chatId, $messageId, 
                "❌ Произошла ошибка при переносе записи. Пожалуйста, попробуйте позже или свяжитесь с нами напрямую.");
        }
    }

    /**
     * Получает название дня недели на русском языке (сокращенно)
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
        
        return $days[$date->format('l')] ?? $date->format('D');
    }
}
