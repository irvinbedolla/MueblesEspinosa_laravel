<!-- SIDEBAR LEFT CON SCROLL HABILITADO -->
<nav id="sidebar" class="d-flex flex-column justify-content-between p-2 overflow-y-auto">
    <div>
        <div class="px-3 py-4 text-center border-bottom border-secondary border-opacity-25 mb-3">
            <i class="bi bi-house-door-fill fs-2 text-info"></i>
            <h5 class="fw-bold m-0 mt-1">MUEBLES <span class="text-info">ESPINOSA</span></h5>
        </div>

        <ul class="nav nav-pills flex-column pb-4">
            <ul class="nav nav-pills flex-column pb-4">
                @if(auth()->user()->tienePermiso('dashboard.view'))
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-speedometer2 me-2"></i> Inicio
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('pos.access'))
                    <li class="nav-item">
                        <a href="{{ route('ventas.pos') }}" class="nav-link {{ request()->routeIs('ventas.pos') ? 'active' : '' }}">
                            <i class="bi bi-cart-plus me-2 text-success"></i> Nueva Venta
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('ventas.view'))
                    <li class="nav-item">
                        <a href="{{ route('ventas.index') }}" class="nav-link {{ request()->routeIs('ventas.index') || request()->routeIs('ventas.show') ? 'active' : '' }}">
                            <i class="bi bi-receipt me-2"></i> Consulta de Ventas
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('clientes.view'))
                    <li class="nav-item">
                        <a href="{{ route('clientes.index') }}" class="nav-link {{ request()->is('clientes*') ? 'active' : '' }}">
                            <i class="bi bi-people me-2"></i> Clientes
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('rutas.view'))
                    <li class="nav-item">
                        <a href="{{ route('rutas.index') }}" class="nav-link {{ request()->routeIs('rutas.*') ? 'active bg-primary' : '' }}">
                            <i class="bi bi-map me-2"></i> Agenda de Rutas
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('cobranza.movil'))
                    <li class="nav-item">
                        <a href="{{ route('cobrador.mi_ruta') }}" class="nav-link {{ request()->routeIs('cobrador.*') ? 'active' : '' }}">
                            <i class="bi bi-cash-stack me-2"></i> Cobranza Móvil
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('gastos.view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('gastos.*') ? 'active bg-primary' : '' }}" href="{{ route('gastos.index') }}">
                            <i class="bi bi-wallet2 me-2"></i> Gastos
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('cobranza.access'))
                    <li class="nav-item">
                        <a href="{{ route('abonos.index') }}" class="nav-link {{ request()->routeIs('abonos.*') ? 'active fw-bold' : '' }}">
                            <i class="bi bi-wallet2 me-2"></i> <span>Abonos</span>
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('inventory.view'))
                    <li class="nav-item">
                        <a href="{{ route('articulos.index') }}" class="nav-link {{ request()->is('articulos*') || request()->is('inventario*') ? 'active' : '' }}">
                            <i class="bi bi-boxes me-2"></i> Inventario
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('cierres.view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('cierres.*') ? 'active bg-primary' : '' }}" href="{{ route('cierres.index') }}">
                            <i class="bi bi-cash-stack me-2"></i> Cierre
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('reportes.view'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reportes.*') ? 'active bg-primary' : '' }}" href="{{ route('reportes.index') }}">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i> Reportes
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('roles.manage'))
                    <li class="nav-item">
                        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->is('roles*') ? 'active' : '' }}">
                            <i class="bi bi-shield-lock me-2"></i> Roles y Permisos
                        </a>
                    </li>
                @endif

                @if(auth()->user()->tienePermiso('users.manage'))
                    <li class="nav-item">
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->is('users*') ? 'active' : '' }}">
                            <i class="bi bi-people me-2"></i> Usuarios
                        </a>
                    </li>
                @endif
            </ul>
        </ul>
    </div>
</nav>