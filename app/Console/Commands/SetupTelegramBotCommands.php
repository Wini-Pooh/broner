<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Services\TelegramBotService;

class SetupTelegramBotCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-commands {--company-id= : ID компании для настройки команд}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Настраивает команды для Telegram-ботов компаний';

    protected $botService;

    public function __construct(TelegramBotService $botService)
    {
        parent::__construct();
        $this->botService = $botService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companyId = $this->option('company-id');

        if ($companyId) {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("Компания с ID {$companyId} не найдена");
                return 1;
            }
            $companies = collect([$company]);
        } else {
            $companies = Company::whereNotNull('telegram_bot_token')->get();
        }

        if ($companies->isEmpty()) {
            $this->info('Нет компаний с настроенными Telegram-ботами');
            return 0;
        }

        $successCount = 0;
        $totalCount = $companies->count();

        $this->info("Настройка команд для {$totalCount} компани(й)...");

        foreach ($companies as $company) {
            $this->line("Настройка команд для: {$company->name}");
            
            if ($this->botService->setCommands($company)) {
                $this->info("✅ Команды успешно настроены для {$company->name}");
                $successCount++;
            } else {
                $this->error("❌ Ошибка настройки команд для {$company->name}");
            }
        }

        $this->info("\nЗавершено. Успешно: {$successCount}/{$totalCount}");
        
        if ($successCount > 0) {
            $this->line("\n💡 Команды теперь доступны пользователям через меню команд в Telegram");
            $this->line("   (кнопка с тремя линиями рядом с полем ввода сообщения)");
        }

        return 0;
    }
}
