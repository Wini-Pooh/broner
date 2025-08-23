<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasCalendarSettings
{
    /**
     * Получает настройки календаря с дефолтными значениями
     *
     * @return array
     */
    public function getCalendarSettings(): array
    {
        $settings = $this->settings ?? [];
        
        // Базовые настройки календаря с дефолтными значениями
        $calendarSettings = [
            'work_start_time' => $settings['work_start_time'] ?? '09:00',
            'work_end_time' => $settings['work_end_time'] ?? '18:00',
            'appointment_interval' => (int)($settings['appointment_interval'] ?? 30),
            'appointment_days_ahead' => (int)($settings['appointment_days_ahead'] ?? 14),
            'appointment_break_time' => (int)($settings['appointment_break_time'] ?? 0), // Новое поле для перерыва между записями
            'work_days' => $settings['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'email_notifications' => (bool)($settings['email_notifications'] ?? true),
            'require_confirmation' => (bool)($settings['require_confirmation'] ?? false),
            'holidays' => $settings['holidays'] ?? [],
            'break_times' => $settings['break_times'] ?? [],
            'max_appointments_per_slot' => (int)($settings['max_appointments_per_slot'] ?? 1),
        ];
        
        // Валидация настроек
        if (!is_array($calendarSettings['work_days']) || empty($calendarSettings['work_days'])) {
            $calendarSettings['work_days'] = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        }
        
        if (!is_array($calendarSettings['holidays'])) {
            $calendarSettings['holidays'] = [];
        }
        
        if (!is_array($calendarSettings['break_times'])) {
            $calendarSettings['break_times'] = [];
        }
        
        return $calendarSettings;
    }
    
    /**
     * Проверяет, является ли дата праздником
     *
     * @param Carbon $date
     * @return bool
     */
    public function isHoliday(Carbon $date): bool
    {
        $settings = $this->getCalendarSettings();
        $formattedDate = $date->format('Y-m-d');
        
        return in_array($formattedDate, $settings['holidays']);
    }
    
    /**
     * Проверяет, является ли дата рабочим днем с учетом исключений
     *
     * @param Carbon $date
     * @return bool
     */
    public function isWorkDay(Carbon $date): bool
    {
        // Проверяем наличие исключений для этой даты
        $exception = $this->dateExceptions()->forDate($date)->first();
        
        if ($exception) {
            // Если есть исключение типа "разрешить", то день рабочий независимо от настроек
            if ($exception->isAllowException()) {
                return true;
            }
            // Если есть исключение типа "заблокировать", то день нерабочий независимо от настроек
            if ($exception->isBlockException()) {
                return false;
            }
        }
        
        // Если исключений нет, используем стандартную логику
        $settings = $this->getCalendarSettings();
        $dayName = strtolower($date->englishDayOfWeek);
        
        return in_array($dayName, $settings['work_days']) && !$this->isHoliday($date);
    }
    
    /**
     * Проверяет, попадает ли время в перерыв
     *
     * @param string $time Формат 'HH:MM'
     * @return bool
     */
    public function isBreakTime(string $time): bool
    {
        $settings = $this->getCalendarSettings();
        $timeCarbon = Carbon::createFromFormat('H:i', $time);
        
        foreach ($settings['break_times'] as $breakTime) {
            $breakStart = Carbon::createFromFormat('H:i', $breakTime['start']);
            $breakEnd = Carbon::createFromFormat('H:i', $breakTime['end']);
            
            if ($timeCarbon->between($breakStart, $breakEnd, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Генерирует временные слоты для указанной даты с учетом гибкого перерыва и исключений
     *
     * @param Carbon $date
     * @return array
     */
    public function generateTimeSlots(Carbon $date): array
    {
        $settings = $this->getCalendarSettings();
        $slots = [];
        
        // Проверяем исключения для этой даты
        $exception = $this->dateExceptions()->forDate($date)->first();
        
        // Если не рабочий день и нет исключения "разрешить", возвращаем пустой массив
        if (!$this->isWorkDay($date)) {
            return $slots;
        }
        
        // Определяем время работы с учетом исключений
        $workTimeRange = null;
        if ($exception && $exception->isAllowException()) {
            $workTimeRange = $exception->getWorkTimeRange();
        }
        
        // Получаем время начала и окончания работы
        $startTime = Carbon::createFromFormat('H:i', $workTimeRange ? $workTimeRange['start'] : $settings['work_start_time']);
        $endTime = Carbon::createFromFormat('H:i', $workTimeRange ? $workTimeRange['end'] : $settings['work_end_time']);
        $interval = $settings['appointment_interval'];
        $breakTime = $settings['appointment_break_time']; // Время перерыва между записями
        
        // Получаем все существующие записи на эту дату, отсортированные по времени
        $existingAppointments = $this->appointments()
            ->where('appointment_date', $date->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_time')
            ->get();
        
        // Создаем массив занятых интервалов с учетом перерыва
        $occupiedIntervals = [];
        foreach ($existingAppointments as $appointment) {
            $appointmentTime = Carbon::createFromFormat('H:i', $appointment->appointment_time);
            $appointmentEnd = $appointmentTime->copy()->addMinutes($appointment->duration_minutes);
            
            // Если есть перерыв, добавляем его к концу записи
            if ($breakTime > 0) {
                $intervalEnd = $appointmentEnd->copy()->addMinutes($breakTime);
            } else {
                $intervalEnd = $appointmentEnd;
            }
            
            $occupiedIntervals[] = [
                'start' => $appointmentTime,
                'end' => $intervalEnd,
                'appointment' => $appointment
            ];
        }
        
        // Создаем временный объект для итерации
        $currentTime = $startTime->copy();
        
        // Получаем текущее время для проверки прошедших слотов
        $now = Carbon::now();
        $isToday = $date->isToday();
        
        // Генерируем слоты с учетом занятых интервалов
        while ($currentTime < $endTime) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем, не попадает ли в обеденный перерыв
            if (!$this->isBreakTime($timeString)) {
                $isPast = $isToday && $currentTime < $now;
                
                // Проверяем, не пересекается ли время с занятыми интервалами
                $isOccupied = false;
                $relatedAppointment = null;
                
                foreach ($occupiedIntervals as $interval) {
                    if ($currentTime->between($interval['start'], $interval['end'], false)) {
                        $isOccupied = true;
                        $relatedAppointment = $interval['appointment'];
                        break;
                    }
                }
                
                if (!$isOccupied) {
                    // Слот доступен
                    $slots[] = [
                        'time' => $timeString,
                        'isPast' => $isPast,
                        'available' => !$isPast,
                        'blocked' => false,
                        'exception_info' => $exception ? [
                            'type' => $exception->exception_type,
                            'reason' => $exception->reason
                        ] : null
                    ];
                }
                // Если слот занят, просто пропускаем его (не добавляем в массив)
            }
            
            // Увеличиваем время на интервал
            $currentTime->addMinutes($interval);
        }
        
        return $slots;
    }
}
