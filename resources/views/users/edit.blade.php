@extends('layouts.app')

@section('title', 'Editar Usuario - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 950px;">
    <div class="mb-4">
        <a href="{{ route('users.index') }}" class="btn btn-sm btn-light border rounded-pill px-3 me-2">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <h3 class="fw-bold text-dark d-inline-block align-middle m-0">Editar Usuario: {{ $user->username }}</h3>
    </div>

    {{-- ALERTAS DE ERROR DE VALIDACIÓN --}}
    @if ($errors->any())
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Por favor corrige los siguientes errores:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4" style="max-width: 800px;">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Nombre de Usuario (*)</label>
                    <input type="text" name="username" class="form-control rounded-3" value="{{ old('username', $user->username) }}" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Correo Electrónico (*)</label>
                    <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', $user->email) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Nueva Contraseña <small class="text-muted fw-normal">(Opcional)</small></label>
                    <input type="password" name="password" class="form-control rounded-3" placeholder="Dejar en blanco para conservar la actual">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Rol Asignado (*)</label>
                    <select name="role_id" class="form-select rounded-3" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ (string)$userRoleId === (string)$role->id ? 'selected' : '' }}>
                                {{ $role->display_name ?? $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Estatus de la Cuenta</label>
                    <select name="is_active" class="form-select rounded-3">
                        <option value="1" {{ $user->is_active ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ !$user->is_active ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Usuario
                </button>
            </div>
        </div>
    </form>
</div>
@endsection