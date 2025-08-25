<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AdminTelegramService
{
    private $botToken;
    private $chatId;

    public function __construct()
    {
        $this->botToken = '8257321025:AAF-knlnQ-Crn04WGblFq9Lft8wby8sTTH8';
        $this->chatId = '-1002964255391';
    }

    /**
     * Отправляет уведомление о новой регистрации
     */
    public function sendRegistrationNotification(User $user)
    {
        try {
            $message = $this->formatRegistrationMessage($user);
            $keyboard = $this->createPaymentKeyboard($user->id);

            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);

            if (!$response->successful()) {
                Log::error('Ошибка отправки Telegram уведомления: ' . $response->body());
                return false;
            }

            Log::info('Telegram уведомление отправлено для пользователя: ' . $user->email);
            return true;

        } catch (\Exception $e) {
            Log::error('Исключение при отправке Telegram уведомления: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Форматирует сообщение о регистрации
     */
    private function formatRegistrationMessage(User $user)
    {
        $company = $user->company;
        
        $message = "🆕 <b>Новая заявка на регистрацию</b>\n\n";
        $message .= "👤 <b>Пользователь:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "📅 <b>Дата регистрации:</b> " . $user->created_at->format('d.m.Y H:i') . "\n";
        
        if ($company) {
            $message .= "🏢 <b>Компания:</b> {$company->name}\n";
            $message .= "🔗 <b>Ссылка:</b> " . route('company.show', $company->slug) . "\n";
        }
        
        $message .= "\n💳 <b>Статус:</b> ";
        $message .= $user->is_paid ? "✅ Оплачено" : "❌ Ожидает оплаты";
        
        $message .= "\n\n📊 <b>ID пользователя:</b> {$user->id}";
        
        return $message;
    }

    /**
     * Создает клавиатуру с кнопками управления
     */
    private function createPaymentKeyboard($userId)
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Отметить как оплаченный',
                        'callback_data' => "approve_payment_{$userId}"
                    ]
                ],
                [
                    [
                        'text' => '❌ Отклонить заявку',
                        'callback_data' => "reject_payment_{$userId}"
                    ]
                ],
                [
                    [
                        'text' => '👁️ Посмотреть профиль',
                        'callback_data' => "view_profile_{$userId}"
                    ]
                ]
            ]
        ];
    }

    /**
     * Обрабатывает callback от кнопок
     */
    public function handleCallback($callbackData, $callbackQueryId, $chatId, $messageId)
    {
        try {
            Log::info('Получен callback от админского бота', [
                'callback_data' => $callbackData,
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);

            $parts = explode('_', $callbackData);
            $action = $parts[0] . '_' . $parts[1];
            $userId = $parts[2];

            Log::info('Разбор callback данных', [
                'action' => $action,
                'user_id' => $userId,
                'parts' => $parts
            ]);

            $user = User::find($userId);
            if (!$user) {
                Log::warning('Пользователь не найден для callback', ['user_id' => $userId]);
                $this->sendCallbackAnswer($callbackQueryId, "Пользователь не найден");
                return false;
            }

            switch ($action) {
                case 'approve_payment':
                    return $this->approvePayment($user, $messageId, $chatId, $callbackQueryId);
                    
                case 'reject_payment':
                    return $this->rejectPayment($user, $messageId, $chatId, $callbackQueryId);
                    
                case 'view_profile':
                    return $this->viewProfile($user, $messageId, $chatId, $callbackQueryId);
                    
                default:
                    $this->sendCallbackAnswer($callbackQueryId, "Неизвестная команда");
                    return false;
            }

        } catch (\Exception $e) {
            Log::error('Ошибка обработки callback: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Одобряет оплату пользователя
     */
    private function approvePayment(User $user, $messageId, $chatId, $callbackQueryId)
    {
        Log::info('Попытка активации пользователя', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'current_is_paid' => $user->is_paid
        ]);

        if ($user->is_paid) {
            Log::info('Пользователь уже оплачен', ['user_id' => $user->id]);
            $this->sendCallbackAnswer($callbackQueryId, "Пользователь уже имеет статус 'оплачено'");
            return false;
        }

        $result = $user->update(['is_paid' => true]);
        
        Log::info('Результат обновления пользователя', [
            'user_id' => $user->id,
            'update_result' => $result,
            'new_is_paid' => $user->fresh()->is_paid
        ]);
        
        $message = "✅ <b>Пользователь активирован!</b>\n\n";
        $message .= "👤 <b>Пользователь:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "💳 <b>Новый статус:</b> Оплачено\n";
        $message .= "🕒 <b>Время активации:</b> " . now()->format('d.m.Y H:i');

        if ($user->company) {
            $message .= "\n🔗 <b>Ссылка на компанию:</b> " . route('company.show', $user->company->slug);
        }

        $this->editMessage($chatId, $messageId, $message);
        $this->sendCallbackAnswer($callbackQueryId, "Пользователь успешно активирован!");
        
        Log::info("Пользователь {$user->email} активирован через Telegram бота");
        return true;
    }

    /**
     * Отклоняет заявку пользователя
     */
    private function rejectPayment(User $user, $messageId, $chatId, $callbackQueryId)
    {
        $message = "❌ <b>Заявка отклонена</b>\n\n";
        $message .= "👤 <b>Пользователь:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "💳 <b>Статус:</b> Заявка отклонена\n";
        $message .= "🕒 <b>Время отклонения:</b> " . now()->format('d.m.Y H:i');

        $this->editMessage($chatId, $messageId, $message);
        $this->sendCallbackAnswer($callbackQueryId, "Заявка отклонена");
        
        Log::info("Заявка пользователя {$user->email} отклонена через Telegram бота");
        return true;
    }

    /**
     * Показывает детальную информацию о пользователе
     */
    private function viewProfile(User $user, $messageId, $chatId, $callbackQueryId)
    {
        $company = $user->company;
        
        $message = "👁️ <b>Детальная информация</b>\n\n";
        $message .= "🆔 <b>ID:</b> {$user->id}\n";
        $message .= "👤 <b>Имя:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "📅 <b>Регистрация:</b> " . $user->created_at->format('d.m.Y H:i') . "\n";
        $message .= "💳 <b>Статус оплаты:</b> " . ($user->is_paid ? "✅ Оплачено" : "❌ Не оплачено") . "\n";
        
        if ($company) {
            $message .= "\n🏢 <b>Информация о компании:</b>\n";
            $message .= "📝 <b>Название:</b> {$company->name}\n";
            $message .= "📍 <b>Адрес:</b> " . ($company->address ?: 'Не указан') . "\n";
            $message .= "📞 <b>Телефон:</b> " . ($company->phone ?: 'Не указан') . "\n";
            $message .= "🔗 <b>Ссылка:</b> " . route('company.show', $company->slug) . "\n";
            $message .= "📊 <b>Услуг:</b> " . $company->services()->count() . "\n";
            $message .= "📅 <b>Записей:</b> " . $company->appointments()->count() . "\n";
        }

        // Отправляем как новое сообщение
        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);

        $this->sendCallbackAnswer($callbackQueryId, "Информация отправлена");
        return true;
    }

    /**
     * Редактирует сообщение
     */
    private function editMessage($chatId, $messageId, $text)
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Отправляет ответ на callback
     */
    private function sendCallbackAnswer($callbackQueryId, $text)
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false
        ]);
    }

    /**
     * Проверяет подключение к боту
     */
    public function testConnection()
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getMe");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'bot_info' => $data['result']
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Не удалось подключиться к боту'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
