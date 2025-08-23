<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AdminTelegramService;

class AdminTelegramController extends Controller
{
    private $telegramService;

    public function __construct(AdminTelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Обрабатывает webhook от Telegram бота
     */
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('Получен webhook от Telegram:', $data);

            // Обрабатываем callback query (нажатия на кнопки)
            if (isset($data['callback_query'])) {
                $callbackQuery = $data['callback_query'];
                $callbackData = $callbackQuery['data'];
                $messageId = $callbackQuery['message']['message_id'];
                $chatId = $callbackQuery['message']['chat']['id'];
                $callbackQueryId = $callbackQuery['id'];

                $this->telegramService->handleCallback($callbackData, $callbackQueryId, $chatId, $messageId);
                
                return response()->json(['ok' => true]);
            }

            // Обрабатываем обычные сообщения
            if (isset($data['message'])) {
                $message = $data['message'];
                $chatId = $message['chat']['id'];
                $text = $message['text'] ?? '';

                // Команда для проверки статуса бота
                if ($text === '/status') {
                    $this->sendStatusMessage($chatId);
                }

                // Команда помощи
                if ($text === '/help' || $text === '/start') {
                    $this->sendHelpMessage($chatId);
                }
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Ошибка обработки webhook: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Отправляет сообщение со статусом бота
     */
    private function sendStatusMessage($chatId)
    {
        $message = "🤖 <b>Статус бота управления заявками</b>\n\n";
        $message .= "✅ Бот активен и работает\n";
        $message .= "📝 Функции:\n";
        $message .= "• Уведомления о новых регистрациях\n";
        $message .= "• Активация пользователей\n";
        $message .= "• Отклонение заявок\n";
        $message .= "• Просмотр профилей\n\n";
        $message .= "🕒 Время: " . now()->format('d.m.Y H:i:s');

        $this->sendMessage($chatId, $message);
    }

    /**
     * Отправляет справочное сообщение
     */
    private function sendHelpMessage($chatId)
    {
        $message = "📋 <b>Справка по боту управления заявками</b>\n\n";
        $message .= "🔹 Бот автоматически отправляет уведомления о новых регистрациях\n";
        $message .= "🔹 Используйте кнопки под сообщениями для управления:\n\n";
        $message .= "✅ <b>Отметить как оплаченный</b> - активирует пользователя\n";
        $message .= "❌ <b>Отклонить заявку</b> - отклоняет заявку\n";
        $message .= "👁️ <b>Посмотреть профиль</b> - показывает детальную информацию\n\n";
        $message .= "📝 <b>Команды:</b>\n";
        $message .= "/status - статус бота\n";
        $message .= "/help - эта справка\n\n";
        $message .= "⚠️ <b>Важно:</b> Кнопки работают только для админов этого чата";

        $this->sendMessage($chatId, $message);
    }

    /**
     * Отправляет сообщение
     */
    private function sendMessage($chatId, $text)
    {
        $botToken = '8257321025:AAF-knlnQ-Crn04WGblFq9Lft8wby8sTTH8';
        
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Тестирует подключение к боту
     */
    public function test()
    {
        $result = $this->telegramService->testConnection();
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Подключение к боту успешно!',
                'bot_info' => $result['bot_info']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ошибка подключения к боту: ' . $result['error']
        ], 400);
    }

    /**
     * Устанавливает webhook для бота
     */
    public function setWebhook()
    {
        $botToken = '8257321025:AAF-knlnQ-Crn04WGblFq9Lft8wby8sTTH8';
        $webhookUrl = route('admin.telegram.webhook');
        
        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query']
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook успешно установлен',
                    'webhook_url' => $webhookUrl,
                    'response' => $data
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Ошибка установки webhook',
                'error' => $response->body()
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Исключение при установке webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает информацию о webhook
     */
    public function getWebhookInfo()
    {
        $botToken = '8257321025:AAF-knlnQ-Crn04WGblFq9Lft8wby8sTTH8';
        
        try {
            $response = Http::get("https://api.telegram.org/bot{$botToken}/getWebhookInfo");
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'webhook_info' => $response->json()['result']
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $response->body()
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
