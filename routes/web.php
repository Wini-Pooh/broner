<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminTelegramController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Главная страница с проверкой авторизации
Route::get('/', [AuthController::class, 'welcome'])->name('welcome');

// Маршруты аутентификации
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

// Страница ожидания оплаты
Route::get('/payment-pending', [AuthController::class, 'paymentPending'])->name('payment.pending')->middleware('auth');

// Отключаем стандартные маршруты Laravel Auth, чтобы использовать наши
// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Публичные маршруты для компаний
Route::get('/company/{slug}', [CompanyController::class, 'show'])->name('company.show');
Route::get('/company/{slug}/appointments', [CompanyController::class, 'getAppointments'])->name('company.appointments');
Route::get('/company/{slug}/monthly-stats', [CompanyController::class, 'getMonthlyStats'])->name('company.monthly-stats');
Route::get('/company/{slug}/date-exception-info', [CompanyController::class, 'getDateExceptionInfo'])->name('company.date-exception-info');
Route::post('/company/{slug}/appointments', [CompanyController::class, 'createAppointment'])->name('company.appointments.create');

// Тестовый роут для проверки API как неавторизованный пользователь
Route::get('/test-api/{slug}', function($slug) {
    // Симулируем AJAX запрос к getAppointments как неавторизованный пользователь
    $url = url("/company/{$slug}/appointments?date=2025-08-31");
    
    try {
        $response = \Illuminate\Support\Facades\Http::get($url);
        return response()->json([
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->json(),
            'user_authenticated' => auth()->check(),
            'user_id' => auth()->id()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'url' => $url
        ], 500);
    }
})->name('test.api');

// Маршруты для управления записями (требуют аутентификации и оплаты)
Route::middleware(['auth', 'check.paid'])->group(function () {
    // Управление записями
    Route::put('/company/{slug}/appointments/{appointmentId}/update', [CompanyController::class, 'updateAppointment'])->name('company.appointments.update');
    Route::put('/company/{slug}/appointments/{appointmentId}/cancel', [CompanyController::class, 'cancelAppointment'])->name('company.appointments.cancel');
    Route::put('/company/{slug}/appointments/{appointmentId}/complete', [CompanyController::class, 'completeAppointment'])->name('company.appointments.complete');
    Route::put('/company/{slug}/appointments/{appointmentId}/reschedule', [CompanyController::class, 'rescheduleAppointment'])->name('company.appointments.reschedule');
    Route::put('/company/{slug}/appointments/{appointmentId}/update-contact', [CompanyController::class, 'updateAppointmentContact'])->name('company.appointments.update-contact');
    
    // Управление компанией
    Route::get('/company/create', [App\Http\Controllers\CompanyManagementController::class, 'create'])->name('company.create');
    Route::post('/company', [App\Http\Controllers\CompanyManagementController::class, 'store'])->name('company.store');
    Route::get('/company/{slug}/edit', [App\Http\Controllers\CompanyManagementController::class, 'edit'])->name('company.edit');
    Route::put('/company/{slug}', [App\Http\Controllers\CompanyManagementController::class, 'update'])->name('company.update');
    Route::get('/company/{slug}/settings', [App\Http\Controllers\CompanyManagementController::class, 'settings'])->name('company.settings');
    Route::put('/company/{slug}/settings', [App\Http\Controllers\CompanyManagementController::class, 'updateSettings'])->name('company.settings.update');
    Route::get('/company/{slug}/all-appointments', [App\Http\Controllers\CompanyManagementController::class, 'appointments'])->name('company.all-appointments');
    
    // Временный роут для отладки настроек
    Route::get('/company/{slug}/debug-settings', [App\Http\Controllers\CompanyManagementController::class, 'debugSettings'])->name('company.debug-settings');
    
    // Управление услугами
    Route::get('/company/{slug}/services', [App\Http\Controllers\ServiceController::class, 'index'])->name('company.services.index');
    Route::get('/company/{slug}/services/create', [App\Http\Controllers\ServiceController::class, 'create'])->name('company.services.create');
    Route::post('/company/{slug}/services', [App\Http\Controllers\ServiceController::class, 'store'])->name('company.services.store');
    Route::get('/company/{slug}/services/{serviceId}/edit', [App\Http\Controllers\ServiceController::class, 'edit'])->name('company.services.edit');
    Route::put('/company/{slug}/services/{serviceId}', [App\Http\Controllers\ServiceController::class, 'update'])->name('company.services.update');
    Route::delete('/company/{slug}/services/{serviceId}', [App\Http\Controllers\ServiceController::class, 'destroy'])->name('company.services.destroy');
    
    // Telegram настройки
    Route::put('/company/{slug}/telegram/settings', [TelegramController::class, 'updateSettings'])->name('company.telegram.settings');
    Route::post('/company/{slug}/telegram/test', [TelegramController::class, 'testConnection'])->name('company.telegram.test');
    Route::get('/company/{slug}/telegram/bot-info', [TelegramController::class, 'getBotInfo'])->name('company.telegram.bot-info');
    Route::post('/company/{slug}/telegram/webhook', [TelegramController::class, 'setWebhook'])->name('company.telegram.webhook');
    Route::get('/company/{slug}/telegram/webhook-info', [TelegramController::class, 'getWebhookInfo'])->name('company.telegram.webhook-info');
    
    // Управление исключениями календаря
    Route::get('/company/{slug}/date-exceptions', [App\Http\Controllers\CompanyDateExceptionController::class, 'index'])->name('company.date-exceptions.index');
    Route::post('/company/{slug}/date-exceptions', [App\Http\Controllers\CompanyDateExceptionController::class, 'store'])->name('company.date-exceptions.store');
    Route::delete('/company/{slug}/date-exceptions/{exceptionId}', [App\Http\Controllers\CompanyDateExceptionController::class, 'destroy'])->name('company.date-exceptions.destroy');
    Route::get('/company/{slug}/date-exceptions/by-date', [App\Http\Controllers\CompanyDateExceptionController::class, 'getByDate'])->name('company.date-exceptions.by-date');
});

// Админские маршруты для Telegram бота управления заявками
Route::post('/admin/telegram/webhook', [AdminTelegramController::class, 'webhook'])->name('admin.telegram.webhook');
Route::get('/admin/telegram/test', [AdminTelegramController::class, 'test'])->name('admin.telegram.test');
Route::post('/admin/telegram/set-webhook', [AdminTelegramController::class, 'setWebhook'])->name('admin.telegram.set-webhook');
Route::get('/admin/telegram/webhook-info', [AdminTelegramController::class, 'getWebhookInfo'])->name('admin.telegram.webhook-info');

if (app()->environment('production')) {
    URL::forceScheme('https');
}
// Публичный маршрут для Telegram webhook (без middleware auth)
Route::post('/telegram/webhook/{botToken}', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
