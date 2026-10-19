<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Muebles Espinosa')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --bg-navy: #031b33;
        }
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--bg-navy);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        #sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 8px;
            margin: 2px 10px;
        }
        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        #content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s;
        }
        .top-navbar {
            background-color: white;
            border-bottom: 1px solid #e3e8ee;
        }
        .user-dropdown-btn {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .user-dropdown-btn:hover {
            background-color: rgba(13, 110, 253, 0.2) !important;
        }
    </style>
</head>
<body>

    <!-- INCLUIR SIDEBAR SEPARADO -->
    @include('layouts.sidebar')

    <!-- CONTENT RIGHT -->
    <div id="content">
        <header class="top-navbar px-4 py-3 d-flex justify-content-between align-items-center shadow-sm">
            <h6 class="m-0 text-muted fw-semibold">Sistema de Control Integral</h6>
            <div class="d-flex align-items-center gap-3">
                
                <!-- DROPDOWN EN EL NOMBRE DE USUARIO -->
                <div class="dropdown">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill user-dropdown-btn dropdown-toggle" 
                          id="userMenuDropdown" 
                          data-bs-toggle="dropdown" 
                          aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i> {{ auth()->user()->name ?? auth()->user()->username }}
                    </span>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userMenuDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <span class="d-block text-dark fw-bold small">{{ auth()->user()->email }}</span>
                            <small class="text-muted extra-small">Sesión activa</small>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item py-2 text-dark" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                <i class="bi bi-key me-2 text-primary"></i> Cambiar Contraseña
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item py-2 text-danger fw-semibold">
                                    <i class="bi bi-box-arrow-left me-2"></i> Cerrar Sesión
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>

                <span class="small text-muted fw-bold"><i class="bi bi-calendar3 me-1"></i> {{ date('d-m-Y') }}</span>
            </div>
        </header>

        <main class="p-4">
            <!-- MENSAJES DE NOTIFICACIÓN GLOBAL -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->has('current_password') || $errors->has('new_password'))
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Ocurrió un error al intentar cambiar la contraseña. Por favor verifica los campos.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- MODAL DE CAMBIO DE CONTRASEÑA -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold text-dark">
                        <i class="bi bi-shield-lock text-primary me-2"></i>Cambiar Contraseña
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body py-3">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Contraseña Actual</label>
                            <input type="password" name="current_password" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Nueva Contraseña</label>
                            <input type="password" name="new_password" class="form-control rounded-3" required minlength="6">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Confirmar Nueva Contraseña</label>
                            <input type="password" name="new_password_confirmation" class="form-control rounded-3" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill btn-sm px-3 fw-bold">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>