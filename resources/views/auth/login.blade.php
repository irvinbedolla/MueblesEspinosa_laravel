<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muebles Espinosa - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #eef2f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card { max-width: 420px; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        .login-header { background-color: #031b33; color: white; padding: 30px 20px; text-align: center; }
        .btn-custom { background-color: #031b33; color: white; font-weight: 600; padding: 12px; border-radius: 10px; }
        .btn-custom:hover { background-color: #052a50; color: white; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="card border-0 login-card w-100 m-3">
        <div class="login-header">
            <i class="bi bi-house-door-fill fs-1 text-info d-block mb-2"></i>
            <h4 class="fw-bold m-0">MUEBLES <span class="text-info">ESPINOSA</span></h4>
            <small class="text-white-50">Sistema de Control Integral</small>
        </div>
        <div class="card-body p-4 bg-white">
            
            @if ($errors->any())
                <div class="alert alert-danger small rounded-3 py-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control bg-light border-start-0 ps-0" placeholder="usuario@mueblesespinosa.com" value="{{ old('email') }}" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-muted">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control bg-light border-start-0 ps-0" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-custom w-100 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Iniciar Sesión
                </button>
            </form>
        </div>
    </div>

</body>
</html>