<?php
// Тестовый скрипт для проверки Telegram бота
// Запустить: php test_telegram_bot.php

require_once 'vendor/autoload.php';

use App\Services\AdminTelegramService;
use App\Models\User;

// Получаем конфигурацию Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тестирование Telegram бота ===\n\n";

// Создаем экземпляр сервиса
$telegramService = new AdminTelegramService();

// Тест 1: Проверка подключения к боту
echo "1. Проверка подключения к боту...\n";
$connectionTest = $telegramService->testConnection();

if ($connectionTest['success']) {
    echo "✅ Подключение успешно!\n";
    echo "Информация о боте:\n";
    $botInfo = $connectionTest['bot_info'];
    echo "- ID: {$botInfo['id']}\n";
    echo "- Имя: {$botInfo['first_name']}\n";
    echo "- Username: @{$botInfo['username']}\n";
    echo "- Может присоединяться к группам: " . ($botInfo['can_join_groups'] ? 'Да' : 'Нет') . "\n";
    echo "- Может читать все сообщения: " . ($botInfo['can_read_all_group_messages'] ? 'Да' : 'Нет') . "\n";
    echo "- Поддерживает inline: " . ($botInfo['supports_inline_queries'] ? 'Да' : 'Нет') . "\n";
} else {
    echo "❌ Ошибка подключения: {$connectionTest['error']}\n";
    exit(1);
}

echo "\n" . str_repeat("-", 50) . "\n";

// Тест 2: Отправка тестового уведомления
echo "2. Тестирование отправки уведомления...\n";

// Найдем любого пользователя для теста или создадим тестового
$testUser = User::where('email', 'test@example.com')->first();

if (!$testUser) {
    echo "Создаем тестового пользователя...\n";
    $testUser = User::create([
        'name' => 'Тестовый пользователь',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'is_paid' => false,
    ]);
    
    // Создаем компанию для тестового пользователя
    $company = \App\Models\Company::create([
        'user_id' => $testUser->id,
        'name' => 'Тестовая компания',
        'slug' => 'test-company-' . time(),
        'description' => 'Тестовая компания для проверки бота',
        'phone' => '+7 (999) 999-99-99',
        'is_active' => true,
        'settings' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'work_start' => '09:00',
            'work_end' => '18:00',
            'slot_duration' => 60,
            'slots_count' => 1,
            'max_appointments_per_day' => 10,
        ],
    ]);
    
    echo "✅ Тестовый пользователь создан (ID: {$testUser->id})\n";
} else {
    echo "✅ Используем существующего тестового пользователя (ID: {$testUser->id})\n";
}

// Отправляем уведомление
echo "Отправляем уведомление в Telegram...\n";
$notificationResult = $telegramService->sendRegistrationNotification($testUser);

if ($notificationResult) {
    echo "✅ Уведомление отправлено успешно!\n";
    echo "Проверьте чат -1002964255391 на наличие сообщения с кнопками управления.\n";
} else {
    echo "❌ Ошибка отправки уведомления. Проверьте логи системы.\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Информация для дальнейших действий
echo "3. Дальнейшие действия:\n";
echo "- Установите webhook: http://your-domain.com/admin/telegram/webhook\n";
echo "- Проверьте админскую панель: http://your-domain.com/admin-bot.html\n";
echo "- Chat ID для уведомлений: -1002964255391\n";
echo "- Команды бота в чате:\n";
echo "  /start - справка\n";
echo "  /status - статус бота\n";
echo "  /help - помощь\n";

echo "\n" . str_repeat("-", 50) . "\n";

// Тест 4: Список всех пользователей и их статусы
echo "4. Текущие пользователи системы:\n";
$users = User::with('company')->get();

foreach ($users as $user) {
    echo "ID: {$user->id} | {$user->name} ({$user->email}) | ";
    echo "Оплата: " . ($user->is_paid ? "✅" : "❌") . " | ";
    echo "Компания: " . ($user->company ? $user->company->name : "Нет") . "\n";
}

echo "\n=== Тестирование завершено ===\n";
echo "Время: " . date('Y-m-d H:i:s') . "\n";
