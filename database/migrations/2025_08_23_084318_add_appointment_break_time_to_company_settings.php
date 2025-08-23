<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Это миграция только для документации, так как appointment_break_time будет храниться в JSON поле settings
        // Никаких изменений в структуре таблицы не требуется
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Никаких изменений в структуре таблицы не было
    }
};
