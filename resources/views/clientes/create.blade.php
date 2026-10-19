@extends('layouts.app')

@section('title', 'Nuevo Cliente - Muebles Espinosa')

@section('content')
<div class="container-fluid px-0" style="max-width: 950px;">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('clientes.index') }}" class="btn btn-light border rounded-circle shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-arrow-left text-muted fs-5"></i>
            </a>
            <div>
                <h3 class="fw-bold text-dark m-0">Registrar Nuevo Cliente</h3>
                <p class="text-muted small m-0">Ingresa los datos del domicilio y contacto del cliente</p>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('clientes.store') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-secondary small">Nombre Completo (*)</label>
                        <input type="text" name="nombre" class="form-control bg-light rounded-3" placeholder="Ej. JUAN PÉREZ GÓMEZ" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary small">Teléfono</label>
                        <input type="text" name="telefono" class="form-control bg-light rounded-3" placeholder="4431234567">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control bg-light rounded-3" placeholder="cliente@correo.com">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">INE / Identificación</label>
                        <input type="text" name="ine" class="form-control bg-light rounded-3" placeholder="Número o Clave de Elector">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Calle y Número</label>
                        <input type="text" name="calle" class="form-control bg-light rounded-3" placeholder="Av. Madero Poniente #123">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Colonia</label>
                        <input type="text" name="colonia" class="form-control bg-light rounded-3" placeholder="Col. Centro">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control bg-light rounded-3" value="Morelia">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Estado</label>
                        <input type="text" name="estado" class="form-control bg-light rounded-3" value="Michoacán">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-secondary small">Descripción del Domicilio / Referencias</label>
                        <textarea name="descripcion" class="form-control bg-light rounded-3" rows="2" placeholder="Casa color azul, portón blanco, entre calle X y calle Y"></textarea>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-secondary small">Enlace Ubicación (Google Maps)</label>
                        <input type="url" name="ubicacion" class="form-control bg-light rounded-3" placeholder="https://maps.google.com/?q=...">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-secondary small">Estatus del Cliente</label>
                        <select name="status" class="form-select bg-light rounded-3">
                            <option value="Activo" selected>Activo</option>
                            <option value="Lista_Negra">Lista Negra</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end align-items-center gap-3 mt-5 pt-3 border-top">
                    <a href="{{ route('clientes.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold text-muted">Cancelar</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection