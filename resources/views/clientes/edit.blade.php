@extends('layouts.app')

@section('title', 'Editar Cliente - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 950px;">
    
    <!-- CABECERA -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('clientes.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Volver">
                <i class="bi bi-arrow-left text-muted fs-5"></i>
            </a>
            <div>
                <h3 class="fw-bold text-dark m-0">Editar Cliente</h3>
                <p class="text-muted small m-0">Actualiza la información personal, datos de contacto o ubicación de <strong>{{ $cliente->nombre }}</strong></p>
            </div>
        </div>
    </div>

    <!-- TARJETA DEL FORMULARIO CENTRADA -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('clientes.update', $cliente->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    <!-- Nombre Completo -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-secondary small">Nombre Completo (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="nombre" class="form-control bg-light border-start-0 ps-0" value="{{ old('nombre', $cliente->nombre) }}" placeholder="Ej. JUAN PÉREZ GÓMEZ" required>
                        </div>
                    </div>
                    
                    <!-- Teléfono -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary small">Teléfono de Contacto</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="telefono" class="form-control bg-light border-start-0 ps-0" value="{{ old('telefono', $cliente->telefono) }}" placeholder="4431234567">
                        </div>
                    </div>

                    <!-- Correo Electrónico -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Correo Electrónico</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control bg-light border-start-0 ps-0" value="{{ old('email', $cliente->email) }}" placeholder="cliente@correo.com">
                        </div>
                    </div>

                    <!-- INE / Identificación -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">INE / Identificación Oficial</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-card-heading"></i></span>
                            <input type="text" name="ine" class="form-control bg-light border-start-0 ps-0" value="{{ old('ine', $cliente->ine) }}" placeholder="Número o Clave de Elector">
                        </div>
                    </div>

                    <!-- Calle y Número -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Calle y Número</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-house-door"></i></span>
                            <input type="text" name="calle" class="form-control bg-light border-start-0 ps-0" value="{{ old('calle', $cliente->calle) }}" placeholder="Av. Madero Poniente #123">
                        </div>
                    </div>

                    <!-- Colonia -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Colonia / Fraccionamiento</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-signpost-split"></i></span>
                            <input type="text" name="colonia" class="form-control bg-light border-start-0 ps-0" value="{{ old('colonia', $cliente->colonia) }}" placeholder="Col. Centro">
                        </div>
                    </div>

                    <!-- Ciudad -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Ciudad / Municipio</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-building"></i></span>
                            <input type="text" name="ciudad" class="form-control bg-light border-start-0 ps-0" value="{{ old('ciudad', $cliente->ciudad ?? 'Morelia') }}">
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Estado</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-map"></i></span>
                            <input type="text" name="estado" class="form-control bg-light border-start-0 ps-0" value="{{ old('estado', $cliente->estado ?? 'Michoacán') }}">
                        </div>
                    </div>

                    <!-- Descripción del Domicilio / Referencias -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-secondary small">Descripción del Domicilio / Referencias de Cobro</label>
                        <textarea name="descripcion" class="form-control bg-light rounded-3" rows="2" placeholder="Casa color azul, portón blanco, entre calle X y calle Y">{{ old('descripcion', $cliente->descripcion) }}</textarea>
                    </div>

                    <!-- Enlace Ubicación (Google Maps) + Captura GPS -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-secondary small">Ubicación GPS (Google Maps)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-geo-alt"></i></span>
                            <input type="url" id="ubicacion_input" name="ubicacion" class="form-control bg-light border-start-0 border-end-0 ps-0" value="{{ old('ubicacion', $cliente->ubicacion) }}" placeholder="https://maps.google.com/?q=...">
                            <button type="button" class="btn btn-outline-info px-3" onclick="obtenerUbicacionGPS()" title="Capturar posición GPS del dispositivo">
                                <i class="bi bi-crosshair me-1"></i> Mi Posición GPS
                            </button>
                        </div>
                        <small class="text-muted extra-small ms-1">Puedes pegar un link directo de Google Maps o dar clic en "Mi Posición GPS".</small>
                    </div>

                    <!-- Estatus del Cliente -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary small">Estatus del Cliente (*)</label>
                        <div class="input-group shadow-sm rounded-3">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                            <select name="status" class="form-select bg-light border-start-0 ps-0" required>
                                <option value="Activo" {{ old('status', $cliente->status) === 'Activo' ? 'selected' : '' }}>Activo</option>
                                <option value="Lista_Negra" {{ old('status', $cliente->status) === 'Lista_Negra' ? 'selected' : '' }}>Lista Negra</option>
                                <option value="Inactivo" {{ old('status', $cliente->status) === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES -->
                <div class="d-flex justify-content-end align-items-center gap-3 mt-5 pt-3 border-top">
                    <a href="{{ route('clientes.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">Cancelar</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-arrow-clockwise me-2"></i> Actualizar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT GPS -->
<script>
function obtenerUbicacionGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            document.getElementById('ubicacion_input').value = `https://maps.google.com/?q=${lat},${lng}`;
            alert('📍 Coordenadas GPS actualizadas con éxito.');
        }, function(error) {
            alert('⚠️ No se pudo acceder a la ubicación. Asegúrate de permitir el acceso al GPS.');
        });
    } else {
        alert('⚠️ Su navegador no soporta geolocalización.');
    }
}
</script>
@endsection