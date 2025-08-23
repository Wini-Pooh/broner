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
        Schema::create('company_date_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->date('exception_date');
            $table->enum('exception_type', ['allow', 'block']); // allow - разрешить в выходной, block - заблокировать в рабочий
            $table->string('reason')->nullable(); // причина исключения
            $table->string('work_start_time')->nullable(); // время начала работы для исключения (если allow)
            $table->string('work_end_time')->nullable(); // время окончания работы для исключения (если allow)
            $table->timestamps();
            
            // Уникальный индекс для предотвращения дублирования исключений на одну дату
            $table->unique(['company_id', 'exception_date']);
            
            // Индекс для быстрого поиска по дате
            $table->index(['company_id', 'exception_date', 'exception_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_date_exceptions');
    }
};
