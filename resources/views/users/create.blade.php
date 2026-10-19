@extends('layouts.app')

@section('title', 'Crear Usuario - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 950px;">
    
    <!-- CABECERA -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('users.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Volver">
                <i class="bi bi-arrow-left text-muted fs-5"></i>
            </a>
            <div>
                <h3 class="fw-bold text-dark m-0">Crear Nuevo Usuario</h3>
                <p class="text-muted small m-0">Completa la información para registrar una nueva cuenta de acceso</p>
            </div>
        </div>
    </div>

    <!-- TARJETA DEL FORMULARIO CENTRADA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Nombre de Usuario (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control bg-light border-start-0 ps-0" placeholder="Ej. Claudio" value="{{ old('username') }}" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Correo Electrónico (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control bg-light border-start-0 ps-0" placeholder="claudio@mueblesespinosa.com" value="{{ old('email') }}" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Contraseña (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control bg-light border-start-0 ps-0" placeholder="••••••••" required minlength="6">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Rol Asignado (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                            <select name="role_id" class="form-select bg-light border-start-0 ps-0" required>
                                <option value="" disabled selected>Selecciona un rol...</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Estatus de la Cuenta</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-toggle-on"></i></span>
                            <select name="is_active" class="form-select bg-light border-start-0 ps-0">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES -->
                <div class="d-flex justify-content-end align-items-center gap-3 mt-5 pt-3 border-top">
                    <a href="{{ route('users.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">Cancelar</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-person-check me-2"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection