<?php
session_start();
include 'conexion.php';

// Validar que solo el administrador pueda ver los reportes financieros
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');
if (!$es_admin) {
    die("<div style='padding:2rem; text-align:center; color:#f87171; font-family:sans-serif;'><h2>Acceso Denegado</h2><p>Solo los administradores pueden ver los reportes financieros.</p></div>");
}

$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;
$tasa_sql = (float)$tasa_hoy;

// --- LÓGICA DE FILTROS DE FECHA ---
$rango = $_GET['rango'] ?? 'mes'; 
$fecha_inicio = '';
$fecha_fin = '';

if ($rango === 'semana') {
    $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
    $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
} elseif ($rango === 'mes') {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
} elseif ($rango === 'ano') {
    $fecha_inicio = date('Y-01-01');
    $fecha_fin = date('Y-12-31');
} elseif ($rango === 'custom') {
    $fecha_inicio = $_GET['inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fin'] ?? date('Y-m-t');
} else {
    $rango = 'todo';
    $fecha_inicio = '2020-01-01';
    $fecha_fin = '2099-12-31';
}

$param_fechas = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];

// --- URL PARA EL PDF ---
$url_pdf = "generar_pdf_reporte.php?rango=" . $rango;
if ($rango === 'custom') {
    $url_pdf .= "&inicio=" . $fecha_inicio . "&fin=" . $fecha_fin;
}

// --- 1. TOTALES GLOBALES DEL PERIODO ---
$sql_ventas = "SELECT ISNULL(SUM(CASE WHEN ISNULL(Total_NIO, 0) > 0 THEN Total_NIO ELSE Total * $tasa_sql END), 0) AS Ingresos 
               FROM FACTURA WHERE Estado != 'Anulado' AND Fecha BETWEEN ? AND ?";
$res_ventas = sqlsrv_query($conn, $sql_ventas, $param_fechas);
$total_ventas = ($res_ventas) ? sqlsrv_fetch_array($res_ventas, SQLSRV_FETCH_ASSOC)['Ingresos'] : 0;

$sql_compras = "SELECT ISNULL(SUM(Total_NIO), 0) AS Inversion FROM COMPRA WHERE Estado != 'Anulado' AND Fecha BETWEEN ? AND ?";
$res_compras = sqlsrv_query($conn, $sql_compras, $param_fechas);
$total_compras = ($res_compras) ? sqlsrv_fetch_array($res_compras, SQLSRV_FETCH_ASSOC)['Inversion'] : 0;

$flujo_neto = $total_ventas - $total_compras;

// --- 2. TOP 10 MATERIALES FACTURADOS ---
$sql_top_ventas = "SELECT TOP 10 p.Nombre, SUM(fp.Cantidad) as CantidadVendida, 
                   SUM(fp.Cantidad * ISNULL(fp.Precio_Unitario_NIO, p.Precio_Unitario * $tasa_sql)) as Generado
                   FROM FACTURA_PRODUCTO fp
                   INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura
                   INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto
                   WHERE f.Estado != 'Anulado' AND f.Fecha BETWEEN ? AND ?
                   GROUP BY p.Id_Producto, p.Nombre
                   ORDER BY Generado DESC";
$res_top_ventas = sqlsrv_query($conn, $sql_top_ventas, $param_fechas);

// --- 3. TOP 10 MATERIALES COMPRADOS ---
$sql_top_compras = "SELECT TOP 10 p.Nombre, SUM(cp.Cantidad) as CantidadComprada, 
                    SUM(cp.Cantidad * ISNULL(cp.Precio_Unitario_NIO, p.Precio_Unitario * $tasa_sql)) as Invertido
                    FROM COMPRA_PRODUCTO cp
                    INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra
                    INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto
                    WHERE c.Estado != 'Anulado' AND c.Fecha BETWEEN ? AND ?
                    GROUP BY p.Id_Producto, p.Nombre
                    ORDER BY Invertido DESC";
$res_top_compras = sqlsrv_query($conn, $sql_top_compras, $param_fechas);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Financieros - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: transparent; font-family: 'Segoe UI', Tahoma, sans-serif; padding: 40px 40px 40px 85px; color: #f8fafc; }
        .page-header { border-bottom: 2px solid #334155; padding-bottom: 15px; margin-bottom: 35px; display: flex; justify-content: space-between; align-items: flex-start; }
        .main-title { color: #3b82f6; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 1.6rem; margin: 0; }
        
        .filter-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-filter { border: 1px solid #334155; background: #1e293b; color: #94a3b8; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; cursor: pointer; }
        .btn-filter:hover { background: #334155; color: white; }
        .btn-filter.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        
        .btn-pdf-report { background: linear-gradient(135deg, #ef4444, #b91c1c); border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2); }
        .btn-pdf-report:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }

        .stat-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 25px; height: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
        .card-ingresos::before { background-color: #10b981; }
        .card-egresos::before { background-color: #f59e0b; }
        .card-flujo::before { background-color: #8b5cf6; }
        
        .metric-label { color: #94a3b8; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .metric-value { font-size: 2.2rem; font-weight: 800; margin: 0; font-family: monospace; }
        
        .table-container { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3); height: 100%; }
        .table-title { color: #f8fafc; font-size: 1.1rem; font-weight: bold; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #334155; display: flex; align-items: center; }
        
        .table-custom { width: 100%; color: #e2e8f0; }
        .table-custom th { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; padding: 10px; border-bottom: 1px solid #334155; }
        .table-custom td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; font-size: 0.95rem; }
        .qty-badge { background: rgba(59, 130, 246, 0.1); color: #38bdf8; border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.8rem; }
        
        .date-range-indicator { font-size: 0.85rem; color: #64748b; margin-top: 4px; display: block; font-weight: normal; text-transform: none; font-family: monospace; }
        .custom-date-box { background: rgba(0,0,0,0.2); border: 1px dashed #334155; padding: 12px 15px; border-radius: 8px; margin-top: 15px; display: inline-block; }
        .date-input { background-color: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 6px; padding: 5px 10px; font-size: 0.85rem; outline: none; color-scheme: dark; }
    </style>
</head>
<body>

    <div class="page-header">
        <div>
            <h2 class="main-title"><i class="fa-solid fa-chart-line me-2"></i>Reportes Financieros</h2>
            <span class="date-range-indicator">Periodo Evaluado: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></span>
            
            <div class="custom-date-box">
                <form action="reportes.php" method="GET" class="d-flex align-items-center gap-3 flex-wrap m-0">
                    <input type="hidden" name="rango" value="custom">
                    <div class="d-flex align-items-center gap-2">
                        <label style="color: #94a3b8; font-size: 0.85rem; font-weight: 600;">Desde:</label>
                        <input type="date" name="inicio" class="date-input" value="<?php echo ($rango == 'custom') ? htmlspecialchars($fecha_inicio) : ''; ?>" required>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label style="color: #94a3b8; font-size: 0.85rem; font-weight: 600;">Hasta:</label>
                        <input type="date" name="fin" class="date-input" value="<?php echo ($rango == 'custom') ? htmlspecialchars($fecha_fin) : ''; ?>" required>
                    </div>
                    <button type="submit" class="btn-filter" style="border-color: var(--primary); color: var(--primary);"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
                </form>
            </div>
        </div>
        
        <div class="d-flex flex-column align-items-end gap-3">
            <a href="<?php echo $url_pdf; ?>" target="_blank" class="btn-pdf-report">
                <i class="fa-solid fa-file-pdf"></i> Exportar a PDF
            </a>

            <div class="filter-group justify-content-end">
                <a href="reportes.php?rango=semana" class="btn-filter <?php echo $rango == 'semana' ? 'active' : ''; ?>">Esta Semana</a>
                <a href="reportes.php?rango=mes" class="btn-filter <?php echo $rango == 'mes' ? 'active' : ''; ?>">Este Mes</a>
                <a href="reportes.php?rango=ano" class="btn-filter <?php echo $rango == 'ano' ? 'active' : ''; ?>">Este Año</a>
                <a href="reportes.php?rango=todo" class="btn-filter <?php echo $rango == 'todo' ? 'active' : ''; ?>">Histórico</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card card-ingresos">
                <span class="metric-label">Ingresos por Ventas</span>
                <h2 class="metric-value text-success">C$ <?php echo number_format($total_ventas, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card-egresos">
                <span class="metric-label">Inversión en Compras</span>
                <h2 class="metric-value text-warning">C$ <?php echo number_format($total_compras, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card-flujo">
                <span class="metric-label">Flujo Neto del Periodo</span>
                <h2 class="metric-value" style="color: #c084fc;">C$ <?php echo number_format($flujo_neto, 2); ?></h2>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="table-container">
                <div class="table-title">
                    <i class="fa-solid fa-arrow-trend-up text-success me-2"></i> Top Materiales Vendidos (Facturados)
                </div>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th width="50%">Producto / Material</th>
                                <th width="20%" class="text-center">Cant.</th>
                                <th width="30%" class="text-end">Ingreso (C$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($res_top_ventas && sqlsrv_has_rows($res_top_ventas)): ?>
                                <?php while($v = sqlsrv_fetch_array($res_top_ventas, SQLSRV_FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="fw-bold"><i class="fa-solid fa-box text-success opacity-50 me-2"></i><?php echo htmlspecialchars($v['Nombre']); ?></td>
                                    <td class="text-center"><span class="qty-badge"><?php echo $v['CantidadVendida']; ?></span></td>
                                    <td class="text-end font-monospace fw-bold text-success">C$ <?php echo number_format($v['Generado'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No se registraron ventas en este periodo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="table-container">
                <div class="table-title">
                    <i class="fa-solid fa-cart-shopping text-warning me-2"></i> Top Materiales Comprados (Inversión)
                </div>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th width="50%">Producto / Material</th>
                                <th width="20%" class="text-center">Cant.</th>
                                <th width="30%" class="text-end">Costo (C$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($res_top_compras && sqlsrv_has_rows($res_top_compras)): ?>
                                <?php while($c = sqlsrv_fetch_array($res_top_compras, SQLSRV_FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="fw-bold"><i class="fa-solid fa-box-open text-warning opacity-50 me-2"></i><?php echo htmlspecialchars($c['Nombre']); ?></td>
                                    <td class="text-center"><span class="qty-badge"><?php echo $c['CantidadComprada']; ?></span></td>
                                    <td class="text-end font-monospace fw-bold text-warning">C$ <?php echo number_format($c['Invertido'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No se registraron compras en este periodo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>