@extends('layouts.app')

@section('title', 'Crear Rol - Muebles Espinosa')

@section('content')
<div class="mb-4">
    <a href="{{ route('roles.index') }}" class="btn btn-sm btn-light border rounded-pill px-3 me-2">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <h3 class="fw-bold text-dark d-inline-block align-middle m-0">Crear Nuevo Rol</h3>
</div>

<form action="{{ route('roles.store') }}" method="POST">
    @csrf
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold text-muted">Nombre del Rol (*)</label>
                <input type="text" name="display_name" class="form-control rounded-3" placeholder="Ej. Cobrador Campo" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-muted">Descripción</label>
                <input type="text" name="description" class="form-control rounded-3" placeholder="Descripción breve de los alcances de este rol">
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
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}">
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
        <i class="bi bi-save me-1"></i> Guardar Rol
    </button>
</form>
@endsection