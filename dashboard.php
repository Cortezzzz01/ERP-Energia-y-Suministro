<?php
session_start();
include 'conexion.php';

// Verificamos quién está viendo la pantalla
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// Rescate de tasa del día
$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;
$tasa_sql = (float)$tasa_hoy;

$ingresos_nio = 0;
$egresos_nio = 0;

// --- CONSULTAS FINANCIERAS (SOLO PARA ADMINISTRADORES) ---
if ($es_admin) {
    // 1. Calcular Total de Ingresos (Facturas) - SOLO ACTIVAS
    // CORRECCIÓN: Se simplificó para sumar directamente la columna 'Total', eliminando la referencia a Total_NIO.
    $sql_ingresos = "SELECT ISNULL(SUM(Total), 0) as TotalNIO FROM FACTURA WHERE Estado = 'Activo'";
    $res_ingresos = sqlsrv_query($conn, $sql_ingresos);
    if ($res_ingresos) {
        $fila_ing = sqlsrv_fetch_array($res_ingresos, SQLSRV_FETCH_ASSOC);
        $ingresos_nio = $fila_ing['TotalNIO'] ?? 0;
    }

    // 2. Calcular Total de Egresos (Compras) - SOLO ACTIVAS
    $sql_egresos = "SELECT ISNULL(SUM(TotalProductos), 0) as TotalBase FROM COMPRA WHERE Estado = 'Activo'";
    $res_egresos = sqlsrv_query($conn, $sql_egresos);
    if ($res_egresos) {
        $fila_egr = sqlsrv_fetch_array($res_egresos, SQLSRV_FETCH_ASSOC);
        // Conversión a NIO directa en PHP
        $egresos_nio = ($fila_egr['TotalBase'] ?? 0) * $tasa_hoy;
    }
}

// --- CONSULTAS OPERATIVAS (PARA TODOS) ---
$sql_clientes = "SELECT COUNT(*) as Cantidad FROM CLIENTE";
$res_clientes = sqlsrv_query($conn, $sql_clientes);
$total_clientes = ($res_clientes) ? sqlsrv_fetch_array($res_clientes, SQLSRV_FETCH_ASSOC)['Cantidad'] : 0;

// CORRECCIÓN: Ahora solo cuenta las proformas que realmente están vigentes
$sql_proformas = "SELECT COUNT(*) as Cantidad FROM PROFORMA WHERE Estado = 'Vigente'";
$res_proformas = sqlsrv_query($conn, $sql_proformas);
$total_proformas = ($res_proformas) ? sqlsrv_fetch_array($res_proformas, SQLSRV_FETCH_ASSOC)['Cantidad'] : 0;

$sql_lista_alerta = "SELECT * FROM (
                        SELECT p.Nombre, 
                            (ISNULL((SELECT SUM(cp.Cantidad) FROM COMPRA_PRODUCTO cp INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra WHERE cp.Id_Producto = p.Id_Producto AND c.Estado != 'Anulado'), 0) -
                             ISNULL((SELECT SUM(fp.Cantidad) FROM FACTURA_PRODUCTO fp INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura WHERE fp.Id_Producto = p.Id_Producto AND f.Estado != 'Anulado'), 0)) AS Stock_Actual
                        FROM PRODUCTO p
                    ) as InventarioReal 
                    WHERE Stock_Actual < 10 
                    ORDER BY Stock_Actual ASC";
$res_lista = sqlsrv_query($conn, $sql_lista_alerta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: transparent; font-family: 'Segoe UI', Tahoma, sans-serif; padding: 40px 40px 40px 85px; color: var(--text-main); }
        .page-header { border-bottom: 2px solid var(--border-color); padding-bottom: 15px; margin-bottom: 35px; display: flex; justify-content: space-between; align-items: center; }
        .main-title { color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 1.6rem; margin: 0; }
        .welcome-badge { background-color: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem; color: #94a3b8; }
        
        .card-link-wrapper { text-decoration: none; display: block; height: 100%; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; border-top: 5px solid var(--primary); transition: all 0.3s ease; padding: 25px; height: 100%; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); position: relative; cursor: pointer; display: flex; flex-direction: column; justify-content: center;}
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.15); }
        .metric-label { color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display: block; letter-spacing: 0.5px;}
        .metric-value { color: var(--text-main); font-size: 2rem; font-weight: 800; margin: 0; }
        .metric-icon { color: var(--primary); opacity: 0.15; font-size: 2.5rem; position: absolute; right: 20px; bottom: 20px; }

        .table-container { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); }
        .table { color: var(--text-main); margin-bottom: 0; }
        .table-hover tbody tr:hover td { background-color: rgba(255, 255, 255, 0.05) !important; color: var(--text-main); }
        .table thead th { background-color: var(--primary); color: white; border: none; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; padding: 15px; }
        .table tbody td { padding: 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); vertical-align: middle; background-color: transparent; }

        .btn-reabastecer { border-radius: 20px; font-size: 0.75rem; font-weight: 700; padding: 6px 15px; transition: all 0.3s ease; text-decoration: none; display: inline-block; border: 1px solid var(--danger); color: var(--danger); background: transparent; }
        .btn-reabastecer:hover { transform: scale(1.05); background-color: var(--danger); color: white; box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3); }
        .text-danger { color: var(--danger) !important; }
        .text-success { color: var(--success) !important; }
    </style>
</head>
<body>

    <div class="page-header">
        <h2 class="main-title"><i class="fa-solid fa-layer-group me-2"></i>Centro de Mando</h2>
        <div class="welcome-badge">
            <i class="fa-solid fa-user me-2" style="color: var(--primary);"></i> Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
        </div>
    </div>

    <div class="row g-4 mb-5">
        
        <?php if($es_admin): ?>
            <div class="col-md-3">
                <a href="ver_facturas.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #10b981;">
                        <span class="metric-label">Ingresos Totales</span>
                        <h2 class="metric-value" style="color: #10b981;">C$ <?php echo number_format($ingresos_nio, 2); ?></h2>
                        <i class="fa-solid fa-wallet metric-icon" style="color: #10b981;"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="ver_compras.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #f59e0b;">
                        <span class="metric-label">Inversión en Compras</span>
                        <h2 class="metric-value" style="color: #f59e0b;">C$ <?php echo number_format($egresos_nio, 2); ?></h2>
                        <i class="fa-solid fa-cart-shopping metric-icon" style="color: #f59e0b;"></i>
                    </div>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="ver_clientes.php" class="card-link-wrapper">
                    <div class="card stat-card">
                        <span class="metric-label">Cartera de Clientes</span>
                        <h2 class="metric-value"><?php echo $total_clientes; ?></h2>
                        <i class="fa-solid fa-users-gear metric-icon"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="ver_proformas.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #8b5cf6;">
                        <span class="metric-label">Proformas Activas</span>
                        <h2 class="metric-value"><?php echo $total_proformas; ?></h2>
                        <i class="fa-solid fa-file-signature metric-icon" style="color: #8b5cf6;"></i>
                    </div>
                </a>
            </div>

        <?php else: ?>
            <div class="col-md-3">
                <a href="ver_clientes.php" class="card-link-wrapper">
                    <div class="card stat-card">
                        <span class="metric-label">Cartera de Clientes</span>
                        <h2 class="metric-value"><?php echo $total_clientes; ?></h2>
                        <i class="fa-solid fa-users-gear metric-icon"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="ver_proformas.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #8b5cf6;">
                        <span class="metric-label">Proformas Activas</span>
                        <h2 class="metric-value"><?php echo $total_proformas; ?></h2>
                        <i class="fa-solid fa-file-signature metric-icon" style="color: #8b5cf6;"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="facturas.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #10b981;">
                        <span class="metric-label" style="color: #10b981;">Acceso Rápido</span>
                        <h2 class="metric-value" style="font-size: 1.3rem; margin-top: 5px;">Nueva Factura</h2>
                        <i class="fa-solid fa-plus metric-icon" style="color: #10b981;"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="ver_productos.php" class="card-link-wrapper">
                    <div class="card stat-card" style="border-top-color: #f59e0b;">
                        <span class="metric-label" style="color: #f59e0b;">Acceso Rápido</span>
                        <h2 class="metric-value" style="font-size: 1.3rem; margin-top: 5px;">Inventario</h2>
                        <i class="fa-solid fa-boxes-stacked metric-icon" style="color: #f59e0b;"></i>
                    </div>
                </a>
            </div>
        <?php endif; ?>

    </div>

    <div class="row">
        <div class="col-md-9">
            <div class="table-container">
                <h5 class="fw-bold mb-4" style="color: var(--primary);">
                    <i class="fa-solid fa-boxes-stacked me-2"></i>Monitoreo de Stock Crítico
                </h5>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th width="50%">Descripción del Producto</th>
                            <th width="25%" class="text-center">Existencias</th>
                            <th width="25%" class="text-center">Estado / Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (sqlsrv_has_rows($res_lista)): ?>
                            <?php while($item = sqlsrv_fetch_array($res_lista, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($item['Nombre']); ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo ($item['Stock_Actual'] <= 0) ? 'text-danger' : ''; ?>" style="font-size: 1.1rem;">
                                        <?php echo $item['Stock_Actual']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if($es_admin): ?>
                                        <a href="compras.php" class="btn-reabastecer">
                                            <i class="fa-solid fa-cart-plus me-1"></i> REABASTECER
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger p-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> STOCK CRÍTICO
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted small" style="border-bottom: none;">
                                    <i class="fa-solid fa-check-circle me-1 text-success fs-4 mb-2 d-block"></i> Todos los productos tienen stock suficiente.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>