<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Models\Service;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Показывает список услуг компании
     *
     * @param string $slug
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для просмотра услуг этой компании');
        }
        
        $services = $company->services()->orderBy('name')->get();
        
        return view('company.services.index', compact('company', 'services'));
    }
    
    /**
     * Показывает форму создания услуги
     *
     * @param string $slug
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для создания услуг этой компании');
        }
        
        return view('company.services.create', compact('company'));
    }
    
    /**
     * Сохраняет новую услугу
     *
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для создания услуг этой компании');
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:on',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'name.required' => 'Название услуги обязательно для заполнения.',
            'name.max' => 'Название услуги не может быть длиннее 255 символов.',
            'description.max' => 'Описание услуги не может быть длиннее 1000 символов.',
            'price.numeric' => 'Цена должна быть числом.',
            'price.min' => 'Цена не может быть отрицательной.',
            'duration_minutes.required' => 'Длительность услуги обязательна для заполнения.',
            'duration_minutes.integer' => 'Длительность должна быть целым числом.',
            'duration_minutes.min' => 'Минимальная длительность услуги 5 минут.',
            'duration_minutes.max' => 'Максимальная длительность услуги 480 минут (8 часов).',
            'type.max' => 'Тип услуги не может быть длиннее 50 символов.',
            'photo.image' => 'Файл должен быть изображением.',
            'photo.mimes' => 'Фото должно быть файлом типа: jpeg, png, jpg, gif.',
            'photo.max' => 'Размер фото не должен превышать 2MB.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Обработка загрузки фото
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('services', 'public');
        }
        
        // Создаем услугу
        Service::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'duration_minutes' => $request->duration_minutes,
            'type' => $request->type ?? 'default',
            'photo' => $photoPath,
            'is_active' => $request->has('is_active'),
        ]);
        
        return redirect()->route('company.services.create', $company->slug)
            ->with('success', 'Услуга успешно создана!');
    }
    
    /**
     * Показывает форму редактирования услуги
     *
     * @param string $slug
     * @param int $serviceId
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($slug, $serviceId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для редактирования услуг этой компании');
        }
        
        $service = Service::where('id', $serviceId)
            ->where('company_id', $company->id)
            ->firstOrFail();
        
        return view('company.services.edit', compact('company', 'service'));
    }
    
    /**
     * Обновляет данные услуги
     *
     * @param Request $request
     * @param string $slug
     * @param int $serviceId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $slug, $serviceId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для редактирования услуг этой компании');
        }
        
        $service = Service::where('id', $serviceId)
            ->where('company_id', $company->id)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'type' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|in:on',
        ], [
            'name.required' => 'Название услуги обязательно для заполнения.',
            'name.max' => 'Название услуги не может быть длиннее 255 символов.',
            'description.max' => 'Описание услуги не может быть длиннее 1000 символов.',
            'price.numeric' => 'Цена должна быть числом.',
            'price.min' => 'Цена не может быть отрицательной.',
            'duration_minutes.required' => 'Длительность услуги обязательна для заполнения.',
            'duration_minutes.integer' => 'Длительность должна быть целым числом.',
            'duration_minutes.min' => 'Минимальная длительность услуги 5 минут.',
            'duration_minutes.max' => 'Максимальная длительность услуги 480 минут (8 часов).',
            'type.max' => 'Тип услуги не может быть длиннее 50 символов.',
            'photo.image' => 'Файл должен быть изображением.',
            'photo.mimes' => 'Фото должно быть файлом типа: jpeg, png, jpg, gif.',
            'photo.max' => 'Размер фото не должен превышать 2MB.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Обработка загрузки нового фото
        if ($request->hasFile('photo')) {
            // Удаляем старое фото если оно есть
            if ($service->photo && Storage::disk('public')->exists($service->photo)) {
                Storage::disk('public')->delete($service->photo);
            }
            
            // Сохраняем новое фото
            $service->photo = $request->file('photo')->store('services', 'public');
        }
        
        // Обновляем услугу
        $service->name = $request->name;
        $service->description = $request->description;
        $service->price = $request->price;
        $service->duration_minutes = $request->duration_minutes;
        $service->type = $request->type ?? 'default';
        $service->is_active = $request->has('is_active');
        $service->save();
        
        return redirect()->route('company.services.edit', [$company->slug, $service->id])
            ->with('success', 'Услуга успешно обновлена!');
    }
    
    /**
     * Удаляет услугу
     *
     * @param string $slug
     * @param int $serviceId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($slug, $serviceId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для удаления услуг этой компании');
        }
        
        $service = Service::where('id', $serviceId)
            ->where('company_id', $company->id)
            ->firstOrFail();
        
        // Проверяем, есть ли связанные записи
        $hasAppointments = $service->appointments()->exists();
        
        if ($hasAppointments) {
            // Если есть записи, просто деактивируем услугу
            $service->is_active = false;
            $service->save();
            
            return redirect()->route('company.services.index', $company->slug)
                ->with('warning', 'Услуга деактивирована, так как у нее есть связанные записи. Полное удаление невозможно.');
        }
        
        // Если нет записей, можно удалить услугу
        $service->delete();
        
        return redirect()->route('company.services.index', $company->slug)
            ->with('success', 'Услуга успешно удалена!');
    }
}
