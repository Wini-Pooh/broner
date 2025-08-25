<?php

require_once __DIR__ . '/vendor/autoload.php';

// Инициализируем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🤖 Настройка webhook для админского Telegram-бота...\n\n";

$botToken = '8257321025:AAF-knlnQ-Crn04WGblFq9Lft8wby8sTTH8';
$webhookUrl = 'https://tg.sticap.ru/admin/telegram/webhook';

// 1. Проверяем текущий статус webhook
echo "1️⃣ Проверяем текущий webhook...\n";
$response = file_get_contents("https://api.telegram.org/bot{$botToken}/getWebhookInfo");
$webhookInfo = json_decode($response, true);

if ($webhookInfo['ok']) {
    $result = $webhookInfo['result'];
    echo "   URL: " . ($result['url'] ?? 'не установлен') . "\n";
    echo "   Ошибок: " . ($result['pending_update_count'] ?? 0) . "\n";
    echo "   Последняя ошибка: " . ($result['last_error_message'] ?? 'нет') . "\n";
    echo "   Дата последней ошибки: " . (($result['last_error_date'] ?? null) ? date('Y-m-d H:i:s', $result['last_error_date']) : 'нет') . "\n\n";
} else {
    echo "   ❌ Ошибка получения информации о webhook\n\n";
}

// 2. Удаляем старый webhook
echo "2️⃣ Удаляем старый webhook...\n";
$deleteResponse = file_get_contents("https://api.telegram.org/bot{$botToken}/deleteWebhook");
$deleteResult = json_decode($deleteResponse, true);
echo "   " . ($deleteResult['ok'] ? "✅ Успешно удален" : "❌ Ошибка удаления") . "\n\n";

// 3. Устанавливаем новый webhook
echo "3️⃣ Устанавливаем новый webhook...\n";
echo "   URL: {$webhookUrl}\n";

$postData = http_build_query([
    'url' => $webhookUrl,
    'allowed_updates' => json_encode(['message', 'callback_query'])
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postData
    ]
]);

$setResponse = file_get_contents("https://api.telegram.org/bot{$botToken}/setWebhook", false, $context);
$setResult = json_decode($setResponse, true);

if ($setResult['ok']) {
    echo "   ✅ Webhook успешно установлен!\n\n";
} else {
    echo "   ❌ Ошибка установки webhook: " . $setResult['description'] . "\n\n";
}

// 4. Проверяем установленный webhook
echo "4️⃣ Проверяем установленный webhook...\n";
$response = file_get_contents("https://api.telegram.org/bot{$botToken}/getWebhookInfo");
$webhookInfo = json_decode($response, true);

if ($webhookInfo['ok']) {
    $result = $webhookInfo['result'];
    echo "   URL: " . ($result['url'] ?? 'не установлен') . "\n";
    echo "   Ошибок: " . ($result['pending_update_count'] ?? 0) . "\n";
    echo "   Последняя ошибка: " . ($result['last_error_message'] ?? 'нет') . "\n";
    echo "   Дата последней ошибки: " . (($result['last_error_date'] ?? null) ? date('Y-m-d H:i:s', $result['last_error_date']) : 'нет') . "\n\n";
} else {
    echo "   ❌ Ошибка получения информации о webhook\n\n";
}

// 5. Тестируем бота
echo "5️⃣ Тестируем бота...\n";
$botResponse = file_get_contents("https://api.telegram.org/bot{$botToken}/getMe");
$botInfo = json_decode($botResponse, true);

if ($botInfo['ok']) {
    $bot = $botInfo['result'];
    echo "   ✅ Бот работает: @{$bot['username']} ({$bot['first_name']})\n";
} else {
    echo "   ❌ Бот не отвечает\n";
}

echo "\n✅ Настройка завершена!\n";
echo "Теперь попробуйте нажать кнопку 'отметить как оплаченный' в боте.\n";
