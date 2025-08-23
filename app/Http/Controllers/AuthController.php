<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Company;
use App\Models\Service;
use App\Mail\UserRegistrationNotification;
use App\Services\AdminTelegramService;

class AuthController extends Controller
{
    /**
     * Показать главную страницу
     */
    public function welcome()
    {
        // Если пользователь авторизован
        if (Auth::check()) {
            $user = Auth::user();
            
            // Проверяем статус оплаты
            if (!$user->is_paid) {
                return redirect()->route('payment.pending');
            }
            
            // Если пользователь оплатил и у него есть компания, перенаправляем на неё
            if ($user->company) {
                return redirect()->route('company.show', $user->company->slug);
            }
            
            // Если компании почему-то нет, создаем её (страховка)
            $this->createDefaultCompany($user);
            return redirect()->route('company.show', $user->company->slug);
        }
        
        return view('welcome');
    }
    
    /**
     * Обработка входа
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'Email обязателен для заполнения',
            'email.email' => 'Введите корректный email',
            'password.required' => 'Пароль обязателен для заполнения',
            'password.min' => 'Пароль должен содержать минимум 6 символов',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // Проверяем статус оплаты
            if (!$user->is_paid) {
                return redirect()->route('payment.pending');
            }
            
            // Если у пользователя есть компания, перенаправляем на неё
            if ($user->company) {
                return redirect()->route('company.show', $user->company->slug);
            }
            
            // Если компании почему-то нет, создаем её (страховка)
            $this->createDefaultCompany($user);
            return redirect()->route('company.show', $user->company->slug);
        }

        return back()->withErrors([
            'email' => 'Неверный email или пароль.',
        ])->withInput();
    }
    
    /**
     * Обработка регистрации
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required' => 'Имя обязательно для заполнения',
            'name.max' => 'Имя не должно превышать 255 символов',
            'email.required' => 'Email обязателен для заполнения',
            'email.email' => 'Введите корректный email',
            'email.unique' => 'Пользователь с таким email уже существует',
            'password.required' => 'Пароль обязателен для заполнения',
            'password.min' => 'Пароль должен содержать минимум 6 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_paid' => false,
        ]);

        // Создаем компанию сразу при регистрации
        $this->createDefaultCompany($user);

        // Отправляем уведомление в Telegram
        $this->sendTelegramNotification($user);

        // Авторизуем пользователя
        Auth::login($user);

        return redirect()->route('payment.pending');
    }
    
    /**
     * Выход
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
    
    /**
     * Страница ожидания оплаты
     */
    public function paymentPending()
    {
        $user = Auth::user();
        
        // Если пользователь оплатил, перенаправляем его дальше
        if ($user && $user->is_paid) {
            // Если у пользователя есть компания, перенаправляем на неё
            if ($user->company) {
                return redirect()->route('company.show', $user->company->slug);
            }
            
            // Если компании почему-то нет, создаем её (страховка)
            $this->createDefaultCompany($user);
            return redirect()->route('company.show', $user->company->slug);
        }
        
        return view('auth.payment-pending');
    }
    
    /**
     * Создать компанию по умолчанию для нового пользователя
     */
    private function createDefaultCompany($user)
    {
        // Генерируем slug на основе имени пользователя
        $baseSlug = Str::slug($user->name);
        $slug = $baseSlug;
        $counter = 1;
        
        // Проверяем уникальность slug
        while (Company::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        // Создаем компанию с базовыми настройками
        $company = Company::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'slug' => $slug,
            'description' => 'Новая компания',
            'phone' => '+7 (000) 000-00-00',
            'is_active' => true,
            'settings' => [
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'slot_duration' => 60,
                'slots_count' => 1,
                'max_appointments_per_day' => 10,
                'break_start' => '13:00',
                'break_end' => '14:00',
                'holidays' => [],
            ],
        ]);
        
        // Создаем базовую услугу
        Service::create([
            'company_id' => $company->id,
            'name' => 'Консультация',
            'description' => 'Базовая консультация',
            'price' => 1000,
            'duration_minutes' => 60,
            'type' => 'consultation',
            'is_active' => true,
        ]);
        
        return $company;
    }
    
    /**
     * Отправляет уведомление в Telegram о новой регистрации
     */
    private function sendTelegramNotification(User $user)
    {
        try {
            $telegramService = new AdminTelegramService();
            $telegramService->sendRegistrationNotification($user);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки Telegram уведомления: ' . $e->getMessage());
        }
    }
}
