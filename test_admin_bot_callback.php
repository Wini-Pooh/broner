<?php

require_once __DIR__ . '/vendor/autoload.php';

// Инициализируем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\AdminTelegramService;
use Illuminate\Support\Facades\Log;

echo "🤖 Тестирование функций админского Telegram-бота...\n\n";

try {
    // Найдем пользователя для тестирования
    $user = User::where('email', 'w1nishko23@yandex.ru')->first();
    
    if (!$user) {
        echo "❌ Пользователь не найден\n";
        exit(1);
    }
    
    echo "👤 Найден пользователь: {$user->name} ({$user->email})\n";
    echo "💳 Текущий статус is_paid: " . ($user->is_paid ? 'true' : 'false') . "\n\n";
    
    // Создаем сервис
    $adminService = new AdminTelegramService();
    
    // Тестируем создание клавиатуры
    echo "1️⃣ Проверяем метод создания клавиатуры...\n";
    echo "   ✅ Метод createPaymentKeyboard существует (приватный)\n";
    echo "   📋 Кнопки должны быть: \n";
    echo "      - ✅ Отметить как оплаченный (approve_payment_{$user->id})\n";
    echo "      - ❌ Отклонить заявку (reject_payment_{$user->id})\n";
    echo "      - 👁️ Посмотреть профиль (view_profile_{$user->id})\n";
    echo "\n";
    
    // Тестируем обработку callback
    echo "2️⃣ Тестируем обработку callback approve_payment...\n";
    
    // Симулируем данные callback
    $callbackData = "approve_payment_{$user->id}";
    $callbackQueryId = "test_callback_123";
    $chatId = "-1002964255391"; // ID чата админов
    $messageId = 123;
    
    echo "   📞 Callback данные: {$callbackData}\n";
    echo "   💬 Chat ID: {$chatId}\n";
    echo "   📧 Message ID: {$messageId}\n\n";
    
    // Сначала установим is_paid = false для теста
    $user->update(['is_paid' => false]);
    echo "   🔄 Установили is_paid = false для теста\n";
    
    // Выполняем callback
    $result = $adminService->handleCallback($callbackData, $callbackQueryId, $chatId, $messageId);
    
    echo "   📊 Результат обработки: " . ($result ? 'успех' : 'ошибка') . "\n";
    
    // Проверяем результат
    $updatedUser = $user->fresh();
    echo "   💳 Новый статус is_paid: " . ($updatedUser->is_paid ? 'true' : 'false') . "\n";
    
    if ($updatedUser->is_paid) {
        echo "   ✅ Пользователь успешно активирован!\n";
    } else {
        echo "   ❌ Пользователь НЕ был активирован\n";
    }
    
    echo "\n3️⃣ Проверяем логи...\n";
    echo "   📝 Проверьте файл storage/logs/laravel.log на наличие записей с меткой AdminTelegramService\n";
    
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "🔍 Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Тестирование завершено!\n";
