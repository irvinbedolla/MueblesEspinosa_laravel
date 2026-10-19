@extends('layouts.app')

@section('title', 'Kardex de Movimientos - Muebles Espinosa')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('articulos.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
        <i class="bi bi-arrow-left text-muted fs-5"></i>
    </a>
    <div>
        <h3 class="fw-bold text-dark m-0"><i class="bi bi-journal-text text-primary me-2"></i>Kardex de Auditoría</h3>
        <p class="text-muted small m-0">Historial inmutable de traspasos, entradas, ventas y devoluciones</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th class="ps-4">Fecha y Hora</th>
                    <th>Artículo</th>
                    <th>Tipo de Movimiento</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th class="text-center">Cantidad</th>
                    <th>Realizado Por</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)
                    <tr>
                        <td class="ps-4 small text-muted">{{ $m->created_at->format('d/m/Y h:i A') }}</td>
                        <td><strong class="text-dark">{{ $m->articulo->nombre ?? 'Eliminado' }}</strong></td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">
                                {{ str_replace('_', ' ', $m->tipo_movimiento) }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $m->origen ? $m->origen->username : 'Almacén' }}</td>
                        <td class="small text-muted">{{ $m->destino ? $m->destino->username : 'Almacén' }}</td>
                        <td class="text-center fw-bold fs-6">{{ $m->cantidad }}</td>
                        <td class="small text-dark">{{ $m->usuario->username ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay movimientos registrados en el Kardex.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($movimientos->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4">
            {{ $movimientos->links() }}
        </div>
    @endif
</div>
@endsection