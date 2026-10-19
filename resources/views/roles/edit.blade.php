@extends('layouts.app')

@section('title', 'Editar Rol - Muebles Espinosa')

@section('content')
<div class="mb-4">
    <a href="{{ route('roles.index') }}" class="btn btn-sm btn-light border rounded-pill px-3 me-2">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <h3 class="fw-bold text-dark d-inline-block align-middle m-0">Editar Rol: {{ $role->display_name }}</h3>
</div>

<form action="{{ route('roles.update', $role->id) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold text-muted">Nombre del Rol (*)</label>
                <input type="text" name="display_name" class="form-control rounded-3" value="{{ $role->display_name }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-muted">Descripción</label>
                <input type="text" name="description" class="form-control rounded-3" value="{{ $role->description }}">
            </div>
        </div>
    </div>

    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Asignación de Permisos por Módulo</h5>

    <div class="row g-3 mb-4">
        @foreach($permissionsByModule as $module => $permissions)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-header bg-light border-0 fw-bold text-primary py-3">
                        <i class="bi bi-folder2-open me-1"></i> {{ $module }}
                    </div>
                    <div class="card-body">
                        @foreach($permissions as $perm)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}"
                                    {{ in_array($perm->id, $rolePermissions) ? 'checked' : '' }}>
                                <label class="form-check-label small text-dark fw-medium" for="perm_{{ $perm->id }}">
                                    {{ $perm->display_name }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Rol y Permisos
    </button>
</form>
@endsection