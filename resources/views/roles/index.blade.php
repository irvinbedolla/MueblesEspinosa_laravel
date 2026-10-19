@extends('layouts.app')

@section('title', 'Gestión de Roles - Muebles Espinosa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark m-0"><i class="bi bi-shield-lock text-primary me-2"></i>Roles y Permisos</h3>
        <p class="text-muted small m-0">Administra los roles del sistema y sus accesos por módulo</p>
    </div>
    <a href="{{ route('roles.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Rol
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Rol</th>
                    <th>Descripción</th>
                    <th class="text-center">Permisos Asignados</th>
                    <th class="text-center">Usuarios</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td class="ps-4">
                            <strong class="text-dark d-block fs-6">{{ $role->display_name }}</strong>
                            <small class="text-muted">{{ $role->name }}</small>
                        </td>
                        <td class="text-muted small">{{ $role->description ?? 'Sin descripción' }}</td>
                        <td class="text-center">
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                {{ $role->permissions_count }} Permisos
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">
                                {{ $role->users_count }} Usuarios
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-sm btn-outline-primary rounded-circle me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este rol?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay roles registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection