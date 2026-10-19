@extends('layouts.app')

@section('title', 'Gestión de Usuarios - Muebles Espinosa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark m-0"><i class="bi bi-people text-primary me-2"></i>Usuarios del Sistema</h3>
        <p class="text-muted small m-0">Administra las cuentas de acceso y sus roles asignados</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="bi bi-person-plus me-1"></i> Nuevo Usuario
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Usuario</th>
                    <th>Correo Electrónico</th>
                    <th class="text-center">Rol Asignado</th>
                    <th class="text-center">Estatus</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="ps-4">
                            <strong class="text-dark d-block fs-6">{{ $user->username }}</strong>
                        </td>
                        <td class="text-muted small">{{ $user->email }}</td>
                        <td class="text-center">
                            @forelse($user->roles as $role)
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                    {{ $role->display_name }}
                                </span>
                            @empty
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill">Sin Rol</span>
                            @endforelse
                        </td>
                        <td class="text-center">
                            @if($user->is_active)
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">Activo</span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-1 rounded-pill">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary rounded-circle me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($user->id !== auth()->id())
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection