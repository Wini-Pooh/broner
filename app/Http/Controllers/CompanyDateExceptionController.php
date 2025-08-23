<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Company;
use App\Models\CompanyDateException;
use Carbon\Carbon;

class CompanyDateExceptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Получает список исключений для компании
     */
    public function index($slug, Request $request)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $month = $request->get('month', now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $exceptions = $company->dateExceptions()
            ->whereBetween('exception_date', [$startDate, $endDate])
            ->orderBy('exception_date')
            ->get();

        return response()->json([
            'exceptions' => $exceptions,
            'month' => $month
        ]);
    }

    /**
     * Создает новое исключение
     */
    public function store($slug, Request $request)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validator = Validator::make($request->all(), [
            'exception_date' => 'required|date|after_or_equal:today',
            'exception_type' => 'required|in:allow,block',
            'reason' => 'nullable|string|max:255',
            'work_start_time' => 'nullable|date_format:H:i|required_if:exception_type,allow',
            'work_end_time' => 'nullable|date_format:H:i|required_if:exception_type,allow|after:work_start_time',
        ], [
            'exception_date.required' => 'Дата обязательна для заполнения',
            'exception_date.after_or_equal' => 'Дата не может быть в прошлом',
            'exception_type.required' => 'Тип исключения обязателен',
            'exception_type.in' => 'Неверный тип исключения',
            'work_start_time.required_if' => 'Время начала работы обязательно для разрешающих исключений',
            'work_end_time.required_if' => 'Время окончания работы обязательно для разрешающих исключений',
            'work_end_time.after' => 'Время окончания должно быть после времени начала',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exception = CompanyDateException::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'exception_date' => $request->exception_date
                ],
                [
                    'exception_type' => $request->exception_type,
                    'reason' => $request->reason,
                    'work_start_time' => $request->exception_type === 'allow' ? $request->work_start_time : null,
                    'work_end_time' => $request->exception_type === 'allow' ? $request->work_end_time : null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Исключение календаря сохранено',
                'exception' => $exception
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ошибка при сохранении исключения',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаляет исключение
     */
    public function destroy($slug, $exceptionId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $exception = $company->dateExceptions()->findOrFail($exceptionId);
        $exception->delete();

        return response()->json([
            'success' => true,
            'message' => 'Исключение календаря удалено'
        ]);
    }

    /**
     * Получает информацию об исключении для конкретной даты
     */
    public function getByDate($slug, Request $request)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Неверная дата'
            ], 422);
        }

        $exception = $company->dateExceptions()
            ->forDate($request->date)
            ->first();

        return response()->json([
            'exception' => $exception,
            'has_exception' => (bool) $exception
        ]);
    }
}
