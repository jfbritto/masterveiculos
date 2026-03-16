<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Master Veículos')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: #1a1a2e; }
        .sidebar a { color: #a0a0b0; text-decoration: none; padding: 12px 20px; display: block; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #16213e; }
        .stat-card { border-left: 4px solid; }
        .stat-card.blue { border-color: #0d6efd; }
        .stat-card.green { border-color: #198754; }
        .stat-card.yellow { border-color: #ffc107; }
        .stat-card.red { border-color: #dc3545; }
        .badge-online { background: #198754; }
        .badge-offline { background: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <div class="d-flex">
        <div class="sidebar d-flex flex-column" style="width: 250px;">
            <div class="p-3 text-white fw-bold fs-5 border-bottom border-secondary">
                <i class="bi bi-speedometer2"></i> Master Veículos
            </div>
            <nav class="mt-2">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="{{ route('tenants.index') }}" class="{{ request()->routeIs('tenants.*') ? 'active' : '' }}">
                    <i class="bi bi-building"></i> Tenants
                </a>
                <a href="{{ route('payments.index') }}" class="{{ request()->routeIs('payments.*') ? 'active' : '' }}">
                    <i class="bi bi-credit-card"></i> Pagamentos
                </a>
            </nav>
            <div class="mt-auto p-3 border-top border-secondary">
                <div class="text-light small mb-2">
                    <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light w-100">
                        <i class="bi bi-box-arrow-left"></i> Sair
                    </button>
                </form>
            </div>
        </div>

        <div class="flex-grow-1">
            <nav class="navbar navbar-light bg-white shadow-sm px-4">
                <span class="navbar-text fw-semibold">@yield('title', 'Dashboard')</span>
            </nav>

            <div class="p-4">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmAction(formId, title, text, confirmText, icon) {
        icon = icon || 'warning';
        confirmText = confirmText || 'Sim, confirmar';
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById(formId).submit();
            }
        });
    }
    </script>
</body>
</html>
