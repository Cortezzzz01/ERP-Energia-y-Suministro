<?php 
// 1. Cargamos la conexión
include 'conexion.php'; 
include 'seguridad.php'; 

// --- CONTROL DE ACCESO BASADO EN ROLES ---
$rol_usuario = $_SESSION['rol'] ?? 'Vendedor';
$es_admin = ($rol_usuario === 'Administrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema ERP - Energía y Suministros S.A.</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-dark); 
            overflow: hidden; 
        }

        .sidebar {
            width: 260px;
            min-width: 260px;
            height: 100vh;
            background-color: var(--bg-dark); 
            color: var(--text-main);
            padding-top: 15px;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid var(--border-color);
            z-index: 1050;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .sidebar.collapsed { width: 75px; min-width: 75px; }
        .sidebar.collapsed .nav-text, 
        .sidebar.collapsed .menu-category,
        .sidebar.collapsed .user-profile-section { display: none; }
        .sidebar.collapsed .logo-img { opacity: 0; height: 0; margin: 0; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: 12px 0; margin: 4px 10px; }
        .sidebar.collapsed .nav-link i { margin-right: 0; font-size: 1.3rem; }

        .sidebar-header { text-align: center; padding: 10px 15px 20px; border-bottom: 1px solid var(--border-color); margin-bottom: 15px; transition: all 0.3s; }
        .logo-img { max-width: 85%; height: auto; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.4)); transition: all 0.3s ease; }
        .logo-img:hover { transform: scale(1.02); }

        .nav-link { color: var(--text-muted); padding: 12px 18px; margin: 4px 15px; border-radius: 10px; transition: all 0.3s ease; display: flex; align-items: center; font-size: 0.95rem; white-space: nowrap; font-weight: 500; text-decoration: none; }
        
        .nav-link:hover { background-color: rgba(59, 130, 246, 0.1); color: var(--primary); transform: translateX(5px); }
        .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; font-weight: bold; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); transform: translateX(5px); }
        .nav-link i { width: 30px; text-align: center; margin-right: 12px; font-size: 1.2rem; transition: all 0.3s; }

        .menu-category { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 800; padding: 20px 20px 8px; letter-spacing: 1.5px; white-space: nowrap; opacity: 0.7; }

        .main-content {
            flex-grow: 1;
            height: 100vh;
            padding: 0;
            background-color: var(--bg-dark);
            position: relative; 
        }

        .toggle-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background-color: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        .toggle-btn:hover { background-color: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

        .frame-layer {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
            background-color: var(--bg-dark); 
        }

        .frame-active { z-index: 10; visibility: visible; }
        .frame-inactive { z-index: 1; visibility: hidden; }

        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--bg-dark); }
        .sidebar::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }

        .badge-profile { background-color: rgba(59, 130, 246, 0.1); color: var(--primary); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; font-size: 0.85rem; padding: 0.5rem; width: 100%; margin-bottom: 0.5rem; }
        
        .btn-logout { border: 1px solid var(--danger); color: var(--danger); background: transparent; border-radius: 8px; transition: all 0.3s ease; width: 100%; padding: 0.25rem 0.5rem; font-size: 0.875rem; text-decoration: none; display: inline-block; text-align: center; }
        .btn-logout:hover { background-color: var(--danger); color: white; }
        
        .rol-badge { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; display: block; margin-top: 3px; font-weight: normal; }

        /* Sombra oscura cuando el menú está abierto en celular */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.6);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }

        /* --- MEDIA QUERIES PARA CELULARES (Menú Flotante) --- */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px; /* Escondido por defecto en celular */
                box-shadow: 5px 0 25px rgba(0,0,0,0.8);
            }
            .sidebar.mobile-open {
                left: 0; /* Mostrar en celular */
            }
            .mobile-overlay.show {
                display: block;
            }
            .toggle-btn {
                top: 15px;
                left: 15px;
            }
        }
    </style>
</head>
<body class="index-body">

<div id="mobile-overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<div class="d-flex w-100 h-100">
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="energia.png" alt="Energía y Suministros S.A." class="logo-img">
        </div>

        <div class="user-profile-section">
            <div class="px-3 mb-3 text-center">
                <div class="badge-profile">
                    <i class="fa-solid fa-user-shield me-2"></i> <?php echo $_SESSION['nombre'] ?? 'Usuario'; ?>
                    <span class="rol-badge"><?php echo htmlspecialchars($rol_usuario); ?></span>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Salir
                </a>
            </div>
            <hr style="border-color: var(--border-color); margin-bottom: 15px; margin-top: 0;">
        </div>

        <a href="#" class="nav-link active" id="btn-inicio" onclick="loadPage('dashboard.php', this)">
            <i class="fa-solid fa-house"></i> <span class="nav-text">Inicio</span>
        </a>

        <div class="menu-category">Ingreso de Datos</div>
        <a href="#" class="nav-link" onclick="loadPage('productos.php', this)">
            <i class="fa-solid fa-boxes-stacked"></i> <span class="nav-text">Productos</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('proveedores.php', this)">
            <i class="fa-solid fa-industry"></i> <span class="nav-text">Proveedores</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('clientes.php', this)">
            <i class="fa-solid fa-users"></i> <span class="nav-text">Clientes</span>
        </a>
        
        <?php if($es_admin): ?>
        <a href="#" class="nav-link" onclick="loadPage('encargados.php', this)">
            <i class="fa-solid fa-user-tie"></i> <span class="nav-text">Personal</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('usuarios.php', this)">
            <i class="fa-solid fa-user-gear" style="color: #a855f7;"></i> <span class="nav-text">Usuarios del Sistema</span>
        </a>
        <?php endif; ?>

        <div class="menu-category">Operaciones</div>
        <a href="#" class="nav-link" onclick="loadPage('compras.php', this)">
            <i class="fa-solid fa-cart-shopping"></i> <span class="nav-text">Registrar Compra</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('detalle_compra.php', this)">
            <i class="fa-solid fa-list-check" style="color: var(--warning);"></i> <span class="nav-text">Buscar Compra</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('facturas.php', this)">
            <i class="fa-solid fa-file-invoice-dollar" style="color: var(--success);"></i> <span class="nav-text">Registrar Factura</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('detalle_factura.php', this)">
            <i class="fa-solid fa-dolly" style="color: var(--success);"></i> <span class="nav-text">Buscar Factura</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('nueva_proforma.php', this)">
            <i class="fa-solid fa-file-signature" style="color: #60a5fa;"></i> <span class="nav-text">Registrar Proforma</span>
        </a>

        <div class="menu-category">Reportes y Consultas</div>
        
        <?php if($es_admin): ?>
        <a href="#" class="nav-link" onclick="loadPage('reportes.php', this)">
            <i class="fa-solid fa-chart-pie" style="color: #10b981;"></i> <span class="nav-text">Reportes Financieros</span>
        </a>
        <?php endif; ?>
        
        <a href="#" class="nav-link" onclick="loadPage('ver_productos.php', this)">
            <i class="fa-solid fa-warehouse"></i> <span class="nav-text">Inv. Productos</span>
        </a>
        
        <?php if($es_admin): ?>
        <a href="#" class="nav-link" onclick="loadPage('ver_compras.php', this)">
            <i class="fa-solid fa-receipt"></i> <span class="nav-text">Historial Compras</span>
        </a>
        <?php endif; ?>

        <a href="#" class="nav-link" onclick="loadPage('ver_facturas.php', this)">
            <i class="fa-solid fa-file-circle-check"></i> <span class="nav-text">Historial Facturas</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('ver_proformas.php', this)">
            <i class="fa-solid fa-folder-open" style="color: #60a5fa;"></i> <span class="nav-text">Historial Proformas</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('ver_clientes.php', this)">
            <i class="fa-solid fa-users-viewfinder"></i> <span class="nav-text">Listado Clientes</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('ver_proveedores.php', this)">
            <i class="fa-solid fa-truck-field"></i> <span class="nav-text">Listado Proveedores</span>
        </a>
        
        <?php if($es_admin): ?>
        <a href="#" class="nav-link" onclick="loadPage('ver_encargados.php', this)">
            <i class="fa-solid fa-id-card-clip"></i> <span class="nav-text">Listado Personal</span>
        </a>
        <a href="#" class="nav-link" onclick="loadPage('bitacora_general.php', this)">
            <i class="fa-solid fa-user-secret" style="color: #10b981;"></i> <span class="nav-text">Bitácora Global</span>
        </a>
        <?php endif; ?>

        <div class="menu-category">Soporte</div>
        <a href="#" class="nav-link" onclick="loadPage('ayuda.php', this)">
            <i class="fa-solid fa-circle-question" style="color: #60a5fa;"></i> <span class="nav-text">Manual de Ayuda</span>
        </a>
    </div>

    <div class="main-content">
        <button id="toggle-btn" class="toggle-btn" title="Alternar menú" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>

        <iframe id="frame1" class="frame-layer frame-active" src="dashboard.php"></iframe>
        <iframe id="frame2" class="frame-layer frame-inactive" src=""></iframe>
    </div>
</div>

<script>
    let activeFrameId = 1;

    function loadPage(url, element) {
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        element.classList.add('active');

        const currentFrame = document.getElementById(`frame${activeFrameId}`);
        const nextFrameId = activeFrameId === 1 ? 2 : 1;
        const nextFrame = document.getElementById(`frame${nextFrameId}`);

        nextFrame.src = url;

        nextFrame.onload = function() {
            setTimeout(() => {
                nextFrame.classList.remove('frame-inactive');
                nextFrame.classList.add('frame-active');

                currentFrame.classList.remove('frame-active');
                currentFrame.classList.add('frame-inactive');

                nextFrame.onload = null;
                activeFrameId = nextFrameId;
            }, 50);
        };

        // Si estamos en un celular, cerrar el menú al tocar una opción
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('mobile-overlay').classList.remove('show');
        }
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        
        if (window.innerWidth <= 768) {
            // Comportamiento Celular (Off-Canvas)
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
        } else {
            // Comportamiento PC (Colapsar)
            sidebar.classList.toggle('collapsed');
        }
    }
</script>

</body>
</html>