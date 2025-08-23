<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CompanyDateException extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'exception_date',
        'exception_type',
        'reason',
        'work_start_time',
        'work_end_time',
    ];

    protected $casts = [
        'exception_date' => 'date',
    ];

    /**
     * Связь с компанией
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Проверяет, является ли исключение типа "разрешить"
     */
    public function isAllowException()
    {
        return $this->exception_type === 'allow';
    }

    /**
     * Проверяет, является ли исключение типа "заблокировать"
     */
    public function isBlockException()
    {
        return $this->exception_type === 'block';
    }

    /**
     * Получает рабочее время для исключения типа "разрешить"
     */
    public function getWorkTimeRange()
    {
        if (!$this->isAllowException()) {
            return null;
        }

        return [
            'start' => $this->work_start_time ?: '09:00',
            'end' => $this->work_end_time ?: '18:00',
        ];
    }

    /**
     * Скоуп для получения исключений по дате
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('exception_date', Carbon::parse($date)->format('Y-m-d'));
    }

    /**
     * Скоуп для получения исключений компании
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Скоуп для получения исключений типа "разрешить"
     */
    public function scopeAllowExceptions($query)
    {
        return $query->where('exception_type', 'allow');
    }

    /**
     * Скоуп для получения исключений типа "заблокировать"
     */
    public function scopeBlockExceptions($query)
    {
        return $query->where('exception_type', 'block');
    }
}
