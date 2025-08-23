<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Company;
use App\Services\TelegramBotService;
use Carbon\Carbon;

// Инициализируем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Тестируем календарную логику для Telegram бота
echo "🤖 Тестирование логики календаря для Telegram-бота...\n\n";

try {
    // Получаем первую компанию для тестирования
    $company = Company::first();
    
    if (!$company) {
        echo "❌ Компания не найдена в базе данных\n";
        exit(1);
    }
    
    echo "🏢 Тестируем компанию: {$company->name} (ID: {$company->id})\n";
    
    // Создаем сервис
    $telegramService = new TelegramBotService();
    
    // Тестируем на сегодня
    $today = Carbon::now()->format('Y-m-d');
    echo "📅 Проверяем слоты на сегодня ({$today}):\n";
    
    $todaySlots = $telegramService->getAvailableTimeSlots($company, $today);
    echo "   Доступно слотов: " . count($todaySlots) . "\n";
    
    if (count($todaySlots) > 0) {
        echo "   Первые 3 слота:\n";
        foreach (array_slice($todaySlots, 0, 3) as $slot) {
            echo "   - {$slot['time']} (доступно: {$slot['available_slots']})\n";
        }
    }
    
    // Тестируем на завтра
    $tomorrow = Carbon::tomorrow()->format('Y-m-d');
    echo "\n📅 Проверяем слоты на завтра ({$tomorrow}):\n";
    
    $tomorrowSlots = $telegramService->getAvailableTimeSlots($company, $tomorrow);
    echo "   Доступно слотов: " . count($tomorrowSlots) . "\n";
    
    if (count($tomorrowSlots) > 0) {
        echo "   Первые 3 слота:\n";
        foreach (array_slice($tomorrowSlots, 0, 3) as $slot) {
            echo "   - {$slot['time']} (доступно: {$slot['available_slots']})\n";
        }
    }
    
    // Проверяем настройки календаря
    echo "\n⚙️ Настройки календаря:\n";
    $settings = $company->getCalendarSettings();
    echo "   Рабочие дни: " . implode(', ', $settings['work_days']) . "\n";
    echo "   Время работы: {$settings['work_start_time']} - {$settings['work_end_time']}\n";
    echo "   Интервал записи: {$settings['appointment_interval']} мин\n";
    echo "   Перерыв между записями: {$settings['appointment_break_time']} мин\n";
    echo "   Максимум записей на слот: {$settings['max_appointments_per_slot']}\n";
    
    // Проверяем исключения календаря
    echo "\n📝 Проверяем исключения календаря:\n";
    $exceptions = $company->dateExceptions()
        ->where('exception_date', '>=', Carbon::now()->format('Y-m-d'))
        ->orderBy('exception_date')
        ->limit(5)
        ->get();
        
    if ($exceptions->count() > 0) {
        echo "   Найдено исключений: {$exceptions->count()}\n";
        foreach ($exceptions as $exception) {
            $date = $exception->exception_date->format('d.m.Y');
            $type = $exception->exception_type === 'allow' ? 'Разрешить' : 'Заблокировать';
            echo "   - {$date}: {$type}";
            if ($exception->reason) {
                echo " ({$exception->reason})";
            }
            echo "\n";
        }
    } else {
        echo "   Исключений не найдено\n";
    }
    
    // Тестируем клавиатуру дат
    echo "\n🎹 Тестируем создание клавиатуры дат:\n";
    $dateKeyboard = $telegramService->createDateKeyboard($company, 7);
    echo "   Создано кнопок дат: " . count($dateKeyboard) . " рядов\n";
    
    if (count($dateKeyboard) > 0) {
        echo "   Первый ряд кнопок:\n";
        foreach ($dateKeyboard[0] as $button) {
            echo "   - {$button['text']}\n";
        }
    }
    
    echo "\n✅ Тестирование завершено успешно!\n";
    
} catch (\Exception $e) {
    echo "❌ Ошибка при тестировании: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📋 Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
