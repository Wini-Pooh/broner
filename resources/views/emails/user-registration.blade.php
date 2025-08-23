<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Новая регистрация пользователя</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }
        .info-block {
            background-color: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .label {
            font-weight: bold;
            color: #495057;
        }
        .value {
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Новая регистрация пользователя</h1>
    </div>
    
    <div class="content">
        <p>Добро пожаловать! На вашем сайте зарегистрировался новый пользователь.</p>
        
        <div class="info-block">
            <div class="label">👤 Имя пользователя:</div>
            <div class="value">{{ $user->name }}</div>
        </div>
        
        <div class="info-block">
            <div class="label">📧 Email:</div>
            <div class="value">{{ $user->email }}</div>
        </div>
        
        <div class="info-block">
            <div class="label">📅 Дата регистрации:</div>
            <div class="value">{{ $user->created_at->format('d.m.Y H:i') }}</div>
        </div>
        
        <div class="info-block">
            <div class="label">💳 Статус оплаты:</div>
            <div class="value">
                @if($user->is_paid)
                    ✅ Оплачено
                @else
                    ❌ Ожидает оплаты
                @endif
            </div>
        </div>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #dee2e6;">
        
        <p><strong>Действия:</strong></p>
        <ul>
            <li>Свяжитесь с пользователем для уточнения деталей</li>
            <li>После получения оплаты активируйте доступ в админ-панели</li>
            <li>Пользователь будет уведомлен о активации автоматически</li>
        </ul>
        
        <p style="color: #6c757d; font-size: 14px; margin-top: 30px;">
            Это автоматическое уведомление от системы бронирования.
        </p>
    </div>
</body>
</html>
