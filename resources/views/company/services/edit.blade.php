@extends('layouts.app')

@section('styles')
<link href="{{ asset('css/company-settings.css') }}" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        
                        <div class="mt-2">
                            <a href="{{ route('company.services.create', $company->slug) }}" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-arrow-left"></i> Назад к услугам
                            </a>
                        </div>
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

            <!-- Форма редактирования услуги -->
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Редактирование услуги</h4>
                    <div>
                        <button type="button" class="btn btn-danger me-2" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('company.services.update', [$company->slug, $service->id]) }}" enctype="multipart/form-data" id="editServiceForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="row g-3">
                            <!-- Текущее фото услуги -->
                            @if($service->photo)
                            <div class="col-12">
                                <label class="form-label">Текущее фото</label>
                                <div class="mb-3">
                                    <img src="{{ asset('storage/' . $service->photo) }}" 
                                         alt="Фото услуги" 
                                         class="img-thumbnail" 
                                         style="max-width: 200px; max-height: 200px;">
                                </div>
                            </div>
                            @endif
                            
                            <div class="col-md-6">
                                <label for="name" class="form-label">Название услуги</label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       required 
                                       maxlength="255" 
                                       value="{{ old('name', $service->name) }}">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6">
                                <label for="price" class="form-label">Цена (₽)</label>
                                <input type="number" 
                                       class="form-control @error('price') is-invalid @enderror" 
                                       id="price" 
                                       name="price" 
                                       min="0" 
                                       step="0.01" 
                                       value="{{ old('price', $service->price) }}">
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6">
                                <label for="duration_minutes" class="form-label">Длительность (минут)</label>
                                <input type="number" 
                                       class="form-control @error('duration_minutes') is-invalid @enderror" 
                                       id="duration_minutes" 
                                       name="duration_minutes" 
                                       min="5" 
                                       max="480" 
                                       value="{{ old('duration_minutes', $service->duration_minutes) }}" 
                                       required>
                                @error('duration_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type" class="form-label">Тип услуги</label>
                                <input type="text" 
                                       class="form-control @error('type') is-invalid @enderror" 
                                       id="type" 
                                       name="type" 
                                       list="serviceTypes" 
                                       placeholder="Введите или выберите тип" 
                                       value="{{ old('type', $service->type) }}" 
                                       maxlength="50">
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
                                          rows="4" 
                                          maxlength="1000" 
                                          placeholder="Краткое описание услуги">{{ old('description', $service->description) }}</textarea>
                                <div class="form-text">
                                    <span id="descriptionCounter">{{ strlen(old('description', $service->description ?? '')) }}</span>/1000 символов
                                </div>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-12">
                                <label for="photo" class="form-label">Изменить фото услуги</label>
                                <input type="file" 
                                       class="form-control @error('photo') is-invalid @enderror" 
                                       id="photo" 
                                       name="photo" 
                                       accept="image/jpeg,image/png,image/jpg,image/gif">
                                <div class="form-text">Поддерживаемые форматы: JPEG, PNG, JPG, GIF. Максимальный размер: 2MB</div>
                                @error('photo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input @error('is_active') is-invalid @enderror" 
                                           type="checkbox" 
                                           id="is_active" 
                                           name="is_active" 
                                           {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Услуга активна
                                    </label>
                                    @error('is_active')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-save"></i> Сохранить изменения
                                </button>
                                <a href="{{ route('company.services.create', $company->slug) }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Отмена
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Подтверждение удаления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить услугу <strong>"{{ $service->name }}"</strong>?</p>
                <p class="text-danger">Это действие нельзя отменить.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="{{ route('company.services.destroy', [$company->slug, $service->id]) }}" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Удалить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Валидация формы
    const form = document.getElementById('editServiceForm');
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
        
        // Проверяем описание
        if (descriptionField.value.length > 1000) {
            showError(descriptionField, 'Описание не может быть длиннее 1000 символов.');
            isValid = false;
        } else {
            clearError(descriptionField);
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

// Функция подтверждения удаления
function confirmDelete() {
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();
}
</script>
@endsection
