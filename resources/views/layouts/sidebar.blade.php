<!-- SIDEBAR LEFT -->
<nav id="sidebar" class="d-flex flex-column justify-content-between p-2">
    <div>
        <div class="px-3 py-4 text-center border-bottom border-secondary border-opacity-25 mb-3">
            <i class="bi bi-house-door-fill fs-2 text-info"></i>
            <h5 class="fw-bold m-0 mt-1">MUEBLES <span class="text-info">ESPINOSA</span></h5>
        </div>

        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 me-2"></i> Inicio
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('ventas.pos') }}" class="nav-link {{ request()->routeIs('ventas.pos') ? 'active' : '' }}">
                    <i class="bi bi-cart-plus me-2 text-success"></i> Nueva Venta
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('ventas.index') }}" class="nav-link {{ request()->routeIs('ventas.index') || request()->routeIs('ventas.show') ? 'active' : '' }}">
                    <i class="bi bi-receipt me-2"></i> Consulta de Ventas
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('clientes.index') }}" class="nav-link {{ request()->is('clientes*') ? 'active' : '' }}">
                    <i class="bi bi-people me-2"></i> Clientes
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link"><i class="bi bi-cash-stack me-2"></i> Abonos / Cobranza</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('articulos.index') }}" class="nav-link {{ request()->is('articulos*') || request()->is('inventario*') ? 'active' : '' }}">
                    <i class="bi bi-boxes me-2"></i> Inventario
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link"><i class="bi bi-journal-text me-2"></i> Diario / Cierre</a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link"><i class="bi bi-bar-chart-line me-2"></i> Reportes</a>
            </li>

            <li class="nav-item">
                <a href="{{ route('roles.index') }}" class="nav-link {{ request()->is('roles*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock me-2"></i> Roles y Permisos
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->is('users*') ? 'active' : '' }}">
                    <i class="bi bi-people me-2"></i> Usuarios
                </a>
            </li>
        </ul>
    </div>

</nav>