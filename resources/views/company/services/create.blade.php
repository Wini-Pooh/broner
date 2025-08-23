@extends('layouts.app')

@section('styles')
<link href="{{ asset('css/company-settings.css') }}" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.clickable-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,.125);
}

.clickable-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    border-color: #007bff;
}

.clickable-card .card-title {
    transition: color 0.3s ease;
}

.clickable-card:hover .card-title {
    color: #007bff !important;
}

.service-card {
    border-radius: 10px;
    overflow: hidden;
}

.service-card .card-footer {
    border-top: 1px solid rgba(0,0,0,.125);
}
</style>
@endsection

@section('content')
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            <!-- Профиль компании -->
            <div class="company-profile">
                <div class="d-flex align-items-center">
                    @if($company->avatar)
                        <img src="{{ e($company->avatar) }}" 
                             alt="Аватар компании {{ e($company->name) }}" 
                             class="company-avatar me-3">
                    @else
                        <div class="company-avatar-placeholder me-3 d-flex align-items-center justify-content-center">
                            {{ strtoupper(substr(e($company->name), 0, 2)) }}
                        </div>
                    @endif
                    
                    <div class="company-info flex-grow-1">
                        <h2 class="company-name">{{ e($company->name) }}</h2>
                        
                        @if(Auth::check() && $company->user_id === Auth::id())
                        <div class="mt-2">
                            <a href="{{ route('company.edit', $company->slug) }}" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <a href="{{ route('company.settings', $company->slug) }}" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-cog"></i> Настройки
                            </a>
                            <a href="{{ route('company.services.create', $company->slug) }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-plus"></i> Услуги
                            </a><a href=""> <form action="{{ route('auth.logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Выйти
                </button>
            </form></a>
                        </div>
                        @endif
                        
                    </div>
                </div>
                
                @if($company->description)
                <div class="company-description mt-3">
                    <p class="mb-0">{{ e($company->description) }}</p>
                </div>
                @endif
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Кнопка добавления услуги -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Управление услугами</h4>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fa fa-plus"></i> Добавить услугу
                    </button>
                </div>
            </div>

            <!-- Список услуг в виде карточек -->
            <div class="row">
                @forelse($company->services as $service)
                    <div class="col-md-4 mb-4">
                        <a href="{{ route('company.services.edit', [$company->slug, $service->id]) }}" class="text-decoration-none">
                            <div class="card h-100 service-card clickable-card">
                                @if($service->photo)
                                    <img src="{{ asset('storage/' . $service->photo) }}" class="card-img-top" alt="Фото услуги" style="height: 180px; object-fit: cover;">
                                @else
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 180px;">
                                        <span class="fa fa-image fa-2x text-muted"></span>
                                    </div>
                                @endif
                                <div class="card-body">
                                    <h5 class="card-title text-dark">{{ $service->name }}</h5>
                                    @if($service->description)
                                        <p class="card-text text-muted small">{{ Str::limit($service->description, 100) }}</p>
                                    @endif
                                    <p class="card-text fw-bold text-primary">{{ $service->formatted_price }}</p>
                                    <p class="card-text small text-muted">
                                        <i class="fas fa-clock"></i> {{ $service->formatted_duration }}
                                        @if($service->type)
                                            | <i class="fas fa-tag"></i> {{ $service->type }}
                                        @endif
                                    </p>
                                    @if(!$service->is_active)
                                        <span class="badge bg-secondary">Неактивна</span>
                                    @else
                                        <span class="badge bg-success">Активна</span>
                                    @endif
                                </div>
                                <div class="card-footer bg-transparent">
                                    <small class="text-muted">
                                        <i class="fas fa-edit"></i> Нажмите для редактирования
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">Услуги пока не добавлены.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления услуги -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addServiceModalLabel">Добавить новую услугу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('company.services.store', $company->slug) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Название услуги</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required maxlength="255" value="{{ old('name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Цена (₽)</label>
                            <input type="number" class="form-control @error('price') is-invalid @enderror" id="price" name="price" min="0" step="0.01" value="{{ old('price') }}">
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="duration_minutes" class="form-label">Длительность (минут)</label>
                            <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" id="duration_minutes" name="duration_minutes" min="5" max="480" value="{{ old('duration_minutes', 60) }}" required>
                            @error('duration_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Тип услуги</label>
                            <input type="text" class="form-control @error('type') is-invalid @enderror" id="type" name="type" list="serviceTypes" placeholder="Введите или выберите тип" value="{{ old('type') }}" maxlength="50">
                            <datalist id="serviceTypes">
                                @foreach($company->services->pluck('type')->unique()->filter() as $existingType)
                                    <option value="{{ $existingType }}">
                                @endforeach
                                <option value="консультация">
                                <option value="лечение">
                                <option value="диагностика">
                                <option value="процедура">
                            </datalist>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label for="description" class="form-label">Описание услуги</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3" 
                                      maxlength="1000" 
                                      placeholder="Краткое описание услуги">{{ old('description') }}</textarea>
                            <div class="form-text">
                                <span id="descriptionCounter">0</span>/1000 символов
                            </div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label for="photo" class="form-label">Фото услуги</label>
                            <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" accept="image/jpeg,image/png,image/jpg,image/gif">
                            <div class="form-text">Поддерживаемые форматы: JPEG, PNG, JPG, GIF. Максимальный размер: 2MB</div>
                            @error('photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input @error('is_active') is-invalid @enderror" type="checkbox" id="is_active" name="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Услуга активна
                                </label>
                                @error('is_active')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Добавить услугу</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Если есть ошибки валидации, открываем модалку
    @if($errors->any())
        var addServiceModal = new bootstrap.Modal(document.getElementById('addServiceModal'));
        addServiceModal.show();
    @endif
    
    // Валидация формы
    const form = document.querySelector('#addServiceModal form');
    const photoInput = document.getElementById('photo');
    const descriptionField = document.getElementById('description');
    const descriptionCounter = document.getElementById('descriptionCounter');
    
    // Счетчик символов для описания
    if (descriptionField && descriptionCounter) {
        descriptionField.addEventListener('input', function() {
            const currentLength = this.value.length;
            descriptionCounter.textContent = currentLength;
            
            if (currentLength > 1000) {
                descriptionCounter.style.color = '#dc3545';
                this.classList.add('is-invalid');
            } else {
                descriptionCounter.style.color = '#6c757d';
                this.classList.remove('is-invalid');
            }
        });
        
        // Инициализируем счетчик
        descriptionCounter.textContent = descriptionField.value.length;
    }
    
    // Валидация фото при выборе файла
    photoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            // Проверяем тип файла
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                showError(this, 'Фото должно быть файлом типа: jpeg, png, jpg, gif.');
                this.value = '';
                return;
            }
            
            // Проверяем размер файла (2MB = 2097152 bytes)
            if (file.size > 2097152) {
                showError(this, 'Размер фото не должен превышать 2MB.');
                this.value = '';
                return;
            }
            
            clearError(this);
        }
    });
    
    // Функция показа ошибки
    function showError(element, message) {
        element.classList.add('is-invalid');
        let feedback = element.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            element.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }
    
    // Функция очистки ошибки
    function clearError(element) {
        element.classList.remove('is-invalid');
        const feedback = element.parentNode.querySelector('.invalid-feedback');
        if (feedback && !feedback.hasAttribute('data-server-error')) {
            feedback.remove();
        }
    }
    
    // Валидация при отправке формы
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Проверяем название
        const name = document.getElementById('name');
        if (!name.value.trim()) {
            showError(name, 'Название услуги обязательно для заполнения.');
            isValid = false;
        } else if (name.value.length > 255) {
            showError(name, 'Название услуги не может быть длиннее 255 символов.');
            isValid = false;
        } else {
            clearError(name);
        }
        
        // Проверяем длительность
        const duration = document.getElementById('duration_minutes');
        const durationValue = parseInt(duration.value);
        if (!duration.value || durationValue < 5 || durationValue > 480) {
            showError(duration, 'Длительность должна быть от 5 до 480 минут.');
            isValid = false;
        } else {
            clearError(duration);
        }
        
        // Проверяем цену
        const price = document.getElementById('price');
        if (price.value && parseFloat(price.value) < 0) {
            showError(price, 'Цена не может быть отрицательной.');
            isValid = false;
        } else {
            clearError(price);
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>
@endsection
