<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Master Veículos')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #334155;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #f1f5f9;
            --sidebar-width: 260px;
            --accent: #6366f1;
            --accent-light: #818cf8;
        }

        * { font-family: 'Inter', sans-serif; }

        body { background: #f1f5f9; }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: var(--sidebar-bg);
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-brand-icon {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
        }

        .sidebar-brand-text {
            color: #f8fafc;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: -0.01em;
        }

        .sidebar-nav { padding: 0.75rem 0.75rem; flex: 1; }

        .sidebar-nav .nav-label {
            color: #475569;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 1rem 0.75rem 0.5rem;
        }

        .sidebar-nav a {
            color: var(--sidebar-text);
            text-decoration: none;
            padding: 0.6rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 2px;
        }

        .sidebar-nav a i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-nav a:hover {
            color: var(--sidebar-text-active);
            background: var(--sidebar-hover);
        }

        .sidebar-nav a.active {
            color: #fff;
            background: var(--accent);
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            background: var(--sidebar-hover);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sidebar-text);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .sidebar-user-name {
            color: var(--sidebar-text-active);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .sidebar-user-role {
            color: var(--sidebar-text);
            font-size: 0.7rem;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.75rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-navbar .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .content-area { padding: 1.5rem 1.75rem; }

        /* Cards */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem 1.25rem;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h6 {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .card-body { padding: 1.25rem; }

        /* Stat cards */
        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            border-left: 4px solid;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-card.blue { border-left-color: #6366f1; }
        .stat-card.blue .stat-icon { background: #eef2ff; color: #6366f1; }

        .stat-card.green { border-left-color: #22c55e; }
        .stat-card.green .stat-icon { background: #f0fdf4; color: #22c55e; }

        .stat-card.yellow { border-left-color: #f59e0b; }
        .stat-card.yellow .stat-icon { background: #fffbeb; color: #f59e0b; }

        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.red .stat-icon { background: #fef2f2; color: #ef4444; }

        .stat-card.info { border-left-color: #06b6d4; }
        .stat-card.info .stat-icon { background: #ecfeff; color: #06b6d4; }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.25rem;
        }

        /* Tables */
        .table { font-size: 0.875rem; }
        .table th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom-width: 1px;
        }
        .table td { vertical-align: middle; color: #334155; }
        .table-hover tbody tr:hover { background: #f8fafc; }

        /* Badges */
        .badge {
            font-weight: 500;
            font-size: 0.7rem;
            padding: 0.35em 0.65em;
            border-radius: 6px;
        }

        .badge-online { background: #dcfce7; color: #166534; }
        .badge-offline { background: #f1f5f9; color: #64748b; }

        /* Buttons */
        .btn { font-size: 0.875rem; font-weight: 500; border-radius: 8px; }
        .btn-sm { font-size: 0.8rem; padding: 0.35rem 0.75rem; }
        .btn-primary { background: var(--accent); border-color: var(--accent); }
        .btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }

        /* Alerts */
        .alert { border-radius: 10px; font-size: 0.875rem; border: none; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger, .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.35rem;
        }

        /* Misc */
        .text-muted { color: #64748b !important; }
        a { color: var(--accent); }
        a:hover { color: #4f46e5; }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        @stack('styles')
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-speedometer2"></i>
            </div>
            <span class="sidebar-brand-text">HelpFlux Veículos</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="{{ route('tenants.index') }}" class="{{ request()->routeIs('tenants.*') ? 'active' : '' }}">
                <i class="bi bi-buildings"></i> Tenants
            </a>
            <a href="{{ route('payments.index') }}" class="{{ request()->routeIs('payments.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> Pagamentos
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
                <div>
                    <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                    <div class="sidebar-user-role">Administrador</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm w-100" style="background: var(--sidebar-hover); color: var(--sidebar-text); border: none;">
                    <i class="bi bi-box-arrow-left"></i> Sair
                </button>
            </form>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <span class="page-title">@yield('title', 'Dashboard')</span>
            @yield('actions')
        </div>

        <div class="content-area">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-3">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-3">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
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
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'rounded-3',
                confirmButton: 'rounded-2',
                cancelButton: 'rounded-2'
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById(formId).submit();
            }
        });
    }
    </script>
    @stack('scripts')
</body>
</html>
