<?php
// Тестовый скрипт для проверки статуса пользователя
// Запустить: php test_user_status.php

require_once 'vendor/autoload.php';

use App\Models\User;

// Получаем конфигурацию Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Проверка статуса пользователей ===\n";

$users = User::all();

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Имя: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Статус оплаты: " . ($user->is_paid ? "✅ Оплачено" : "❌ Не оплачено") . "\n";
    echo "Есть компания: " . ($user->company ? "✅ Да ({$user->company->name})" : "❌ Нет") . "\n";
    echo "---\n";
}

echo "\n=== Команды для изменения статуса ===\n";
echo "Активировать пользователя (заменить EMAIL):\n";
echo "UPDATE users SET is_paid = 1 WHERE email = 'user@example.com';\n";
echo "\nДеактивировать пользователя:\n";
echo "UPDATE users SET is_paid = 0 WHERE email = 'user@example.com';\n";
