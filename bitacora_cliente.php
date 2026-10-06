<?php
session_start();
include 'conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: ver_clientes.php");
    exit;
}

$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;
$tasa_sql = (float)$tasa_hoy;

$sqlCliente = "SELECT * FROM CLIENTE WHERE Id_Cliente = ?";
$cliente = sqlsrv_query($conn, $sqlCliente, [$id]);
if (!$cliente || !($c = sqlsrv_fetch_array($cliente, SQLSRV_FETCH_ASSOC))) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Cliente no encontrado.</div></div>");
}

$sqlFacturas = "
    SELECT 
        F.Id_Factura, F.Datos, F.Total AS TotalUSD, F.Total_NIO, F.Tasa_Cambio, 
        F.Estado, F.Motivo_Anulacion,
        F.Fecha, F.Tipo_Pago, F.Estado_Pago, F.Fecha_Vencimiento, F.Mano_Obra,
        E.Nombre AS Encargado
    FROM FACTURA F
    LEFT JOIN ENCARGADO E ON F.Id_Encargado = E.Id_Encargado
    WHERE F.Id_Cliente = ?
    ORDER BY F.Fecha DESC
";
$facturas = sqlsrv_query($conn, $sqlFacturas, [$id]);

$sqlStats = "
SELECT
    COUNT(CASE WHEN Estado NOT LIKE 'Anulad%' THEN 1 END) AS TotalFacturas,
    ISNULL(SUM(CASE WHEN Estado NOT LIKE 'Anulad%' THEN ISNULL(NULLIF(Total_NIO, 0), ISNULL(Total,0) * $tasa_sql) ELSE 0 END), 0) AS TotalFacturadoNIO,
    ISNULL(SUM(CASE WHEN Estado_Pago = 'Pagado' AND Estado NOT LIKE 'Anulad%' THEN ISNULL(NULLIF(Total_NIO, 0), ISNULL(Total,0) * $tasa_sql) ELSE 0 END), 0) AS TotalPagadoNIO,
    COUNT(CASE WHEN Estado NOT LIKE 'Anulad%' AND (Estado_Pago IS NULL OR Estado_Pago != 'Pagado') THEN 1 END) AS PendientePago,
    COUNT(CASE WHEN Estado LIKE 'Anulad%' THEN 1 END) AS TotalAnuladas
FROM FACTURA
WHERE Id_Cliente = ?
";
$statsQ = sqlsrv_query($conn, $sqlStats, [$id]);
$stats = sqlsrv_fetch_array($statsQ, SQLSRV_FETCH_ASSOC);

$saldo_nio = $stats['TotalFacturadoNIO'] - $stats['TotalPagadoNIO'];

$sqlBitacora = "
    SELECT TOP 50 Id_Bitacora, Usuario, Accion, Detalle, Fecha_Hora
    FROM BITACORA_AUDITORIA
    WHERE Detalle LIKE ? OR Detalle LIKE ?
    ORDER BY Fecha_Hora DESC
";
$clienteNombre = '%' . $c['Nombre'] . '%';
$clienteId     = '%Id_Cliente=' . $id . '%';
$bitacora = sqlsrv_query($conn, $sqlBitacora, [$clienteNombre, $clienteId]);

if (!$bitacora || sqlsrv_has_rows($bitacora) === false) {
    $sqlBitacoraGen = "SELECT TOP 30 Id_Bitacora, Usuario, Accion, Detalle, Fecha_Hora FROM BITACORA_AUDITORIA ORDER BY Fecha_Hora DESC";
    $bitacora = sqlsrv_query($conn, $sqlBitacoraGen);
}

function getProductosFactura($conn, $idFactura) {
    $sql = "
        SELECT P.Nombre AS Producto, FP.Cantidad, P.Precio_Unitario, FP.Precio_Unitario_NIO
        FROM FACTURA_PRODUCTO FP
        JOIN PRODUCTO P ON FP.Id_Producto = P.Id_Producto
        WHERE FP.Id_Factura = ?
    ";
    return sqlsrv_query($conn, $sql, [$idFactura]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora — <?php echo htmlspecialchars($c['Nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .client-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f2042 100%); border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .client-hero::before { content: ''; position: absolute; top: -60px; right: -60px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%); border-radius: 50%; }
        .client-avatar { width: 70px; height: 70px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: white; box-shadow: 0 0 25px rgba(59,130,246,0.35); flex-shrink: 0; }
        .client-name-hero { font-size: 1.6rem; font-weight: 700; color: #f8fafc; margin: 0 0 4px 0; }
        .client-meta span { color: #94a3b8; font-size: 0.88rem; margin-right: 1.2rem; }
        .client-meta i { color: var(--primary); opacity: 0.7; margin-right: 4px; }
        .service-pill { background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.35); color: #38bdf8; padding: 5px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.5px; }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; height: 100%; display: flex; flex-direction: column; justify-content: center;}
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
        .stat-number { font-size: 1.8rem; font-weight: 700; line-height: 1; margin-bottom: 8px; }
        .stat-label { font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        
        .stat-blue   .stat-number { color: #3b82f6; }
        .stat-green  .stat-number { color: #10b981; }
        .stat-yellow .stat-number { color: #f59e0b; }
        .stat-red    .stat-number { color: #ef4444; }
        .stat-purple .stat-number { color: #a855f7; }

        .section-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 1.5rem; overflow: hidden; }
        .table-bit { width: 100%; margin: 0; color: #e2e8f0; }
        .table-bit thead th { background: #0f172a; color: #64748b; border-bottom: 1px solid var(--border-color); border-top: none; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px; }
        .table-bit tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; }
        .table-bit tbody tr:hover { background: rgba(59,130,246,0.04); }
        .table-bit tbody td { padding: 15px; vertical-align: middle; background: transparent; border: none; color: #e2e8f0; font-size: 0.9rem; }

        .price-tag { color: var(--success); font-weight: bold; font-size: 1.1rem; letter-spacing: 0.5px; font-family: monospace; }

        .badge-estado { padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.3px; display: inline-block; text-transform: uppercase; }
        .badge-pagada    { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .badge-pendiente { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .badge-anulada   { background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
        .badge-activa    { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .badge-vencida   { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); animation: blink 2s infinite; }
        @keyframes blink { 50% { opacity: 0.7; } }

        .productos-collapse { background: #0f172a; border-top: 1px solid rgba(255,255,255,0.05); }
        .productos-collapse td { padding: 0 !important; }
        .productos-inner { padding: 15px 20px 15px 40px; }
        .productos-inner table { width: 100%; font-size: 0.85rem; }
        .productos-inner table th { color: #475569; font-size: 0.75rem; text-transform: uppercase; padding: 6px 10px; border-bottom: 1px solid rgba(255,255,255,0.05);}
        .productos-inner table td { color: #94a3b8; padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.02);}
        .toggle-productos { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: #3b82f6; font-size: 0.8rem; font-weight: bold; cursor: pointer; padding: 4px 10px; border-radius: 6px; transition: all 0.2s; margin-top: 8px; }
        .toggle-productos:hover { background: #3b82f6; color: white; }

        .btn-back { border: 1px solid var(--border-color); color: #94a3b8; background: transparent; padding: 8px 16px; border-radius: 8px; text-decoration: none; transition: all 0.2s; font-size: 0.9rem; }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); background: rgba(59,130,246,0.08); }
        .btn-edit-client { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; transition: all 0.2s; font-size: 0.9rem; font-weight: 600; }
        .btn-edit-client:hover { color: white; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(59,130,246,0.35); }

        .factura-id { font-family: monospace; font-size: 0.9rem; font-weight: bold; color: #94a3b8; background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border-color); }
        .fecha-text  { color: #f8fafc; font-size: 0.9rem; font-family: monospace; white-space: nowrap; }
        .encargado-text { color: #60a5fa; font-size: 0.85rem; font-weight: 500;}
        .anulacion-row td { opacity: 0.5; background-color: rgba(239, 68, 68, 0.03) !important; }
        .anulacion-row .price-tag { color: #64748b !important; text-decoration: line-through; }

        .nav-tabs-custom { border-bottom: 1px solid var(--border-color); padding: 0 1.5rem; display: flex; gap: 4px; background: rgba(0,0,0,0.2); }
        .nav-tab-btn { background: none; border: none; color: #64748b; padding: 15px 20px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-tab-btn:hover { color: #e2e8f0; }
        .nav-tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-pane { display: none; padding: 1rem 0; }
        .tab-pane.active { display: block; }
        
        .timeline-list { padding: 1.5rem; }
        .timeline-item { display: flex; gap: 14px; padding-bottom: 1.2rem; position: relative; }
        .timeline-item:not(:last-child)::after { content: ''; position: absolute; left: 15px; top: 30px; width: 2px; height: calc(100% - 10px); background: rgba(255,255,255,0.06); }
        .timeline-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0; z-index: 1; }
        .dot-insert { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .dot-update { background: rgba(59,130,246,0.2); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .dot-delete { background: rgba(239,68,68,0.2);  color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .dot-other  { background: rgba(245,158,11,0.2); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .timeline-content { flex: 1; }
        .timeline-action { font-weight: 700; font-size: 0.88rem; color: #f8fafc; margin-bottom: 2px; }
        .timeline-detail { font-size: 0.82rem; color: #64748b; margin-bottom: 3px; line-height: 1.4; }
        .timeline-meta { font-size: 0.75rem; color: #475569; }
        .timeline-meta strong { color: #94a3b8; }
    </style>
</head>
<body>

<div class="container-fluid px-lg-5">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-2" style="color:#475569; font-size:0.85rem;">
            <a href="ver_clientes.php" style="color:#475569; text-decoration:none;"><i class="fa-solid fa-users me-1"></i>Clientes</a>
            <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
            <span style="color:#94a3b8;"><?php echo htmlspecialchars($c['Nombre']); ?></span>
            <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
            <span style="color:#3b82f6; font-weight: bold;">Bitácora Financiera</span>
        </div>
        <div class="d-flex gap-2">
            <a href="editar_cliente.php?id=<?php echo $id; ?>" class="btn-edit-client"><i class="fa-solid fa-pen-to-square me-1"></i>Editar Cliente</a>
            <a href="ver_clientes.php" class="btn-back"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
    </div>

    <div class="client-hero shadow">
        <div class="d-flex align-items-start gap-4 flex-wrap">
            <div class="client-avatar">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                    <h2 class="client-name-hero"><?php echo htmlspecialchars($c['Nombre']); ?></h2>
                    <span class="service-pill"><i class="fa-solid fa-gears me-1"></i><?php echo htmlspecialchars($c['TipoServicio'] ?? 'Servicio General'); ?></span>
                    <span style="background:rgba(0,0,0,0.3);border:1px solid var(--border-color);padding:4px 10px;border-radius:6px;font-size:0.85rem;color:#94a3b8;font-family:monospace;">
                        ID: #<?php echo $c['Id_Cliente']; ?>
                    </span>
                </div>
                <div class="client-meta d-flex flex-wrap gap-3 mt-3">
                    <?php if(!empty($c['Telefono'])): ?><span><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($c['Telefono']); ?></span><?php endif; ?>
                    <?php if(!empty($c['Correo'])): ?><span><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($c['Correo']); ?></span><?php endif; ?>
                    <?php if(!empty($c['Direccion'])): ?><span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($c['Direccion']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card stat-blue">
                <div class="stat-number"><?php echo $stats['TotalFacturas']; ?></div>
                <div class="stat-label">Total de Facturas</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-green">
                <div class="stat-number">C$ <?php echo number_format($stats['TotalFacturadoNIO'], 2); ?></div>
                <div class="stat-label">Total Facturado</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-green">
                <div class="stat-number">C$ <?php echo number_format($stats['TotalPagadoNIO'], 2); ?></div>
                <div class="stat-label">Ingreso Real Cobrado</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-yellow">
                <div class="stat-number">C$ <?php echo number_format($saldo_nio, 2); ?></div>
                <div class="stat-label">Deuda o Saldo Pendiente</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-purple">
                <div class="stat-number"><?php echo $stats['PendientePago']; ?></div>
                <div class="stat-label">Facturas Pendientes</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-red">
                <div class="stat-number"><?php echo $stats['TotalAnuladas']; ?></div>
                <div class="stat-label">Facturas Anuladas</div>
            </div>
        </div>
    </div>

    <div class="section-card shadow">
        <div class="nav-tabs-custom">
            <button class="nav-tab-btn active" onclick="switchTab('tab-facturas', this)"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Historial de Facturas</button>
            <button class="nav-tab-btn" onclick="switchTab('tab-bitacora', this)"><i class="fa-solid fa-clock-rotate-left me-2"></i>Auditoría de Actividad</button>
        </div>

        <div id="tab-facturas" class="tab-pane active">
            <div class="table-responsive">
                <table class="table-bit" id="tablaFacturas">
                    <thead>
                        <tr>
                            <th width="8%" class="text-center">ID Factura</th>
                            <th width="10%">Fecha</th>
                            <th width="25%">Descripción del Proyecto</th>
                            <th width="12%" class="text-end">Mano Obra</th>
                            <th width="15%" class="text-end">Total Facturado</th>
                            <th width="10%" class="text-center">Condición</th>
                            <th width="10%" class="text-center">Estado Pago</th>
                            <th width="10%">Encargado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $countFacturas = 0;
                    if ($facturas && sqlsrv_has_rows($facturas)):
                        while ($f = sqlsrv_fetch_array($facturas, SQLSRV_FETCH_ASSOC)):
                            $countFacturas++;
                            
                            $esAnulada = (strpos(strtolower($f['Estado'] ?? ''), 'anulad') !== false);
                            $estadoPago = empty($f['Estado_Pago']) ? 'Pendiente' : $f['Estado_Pago'];

                            $badgeEstado = $esAnulada ? 'badge-anulada' : 'badge-activa';

                            $today = new DateTime();
                            $venc  = $f['Fecha_Vencimiento'] instanceof DateTime ? $f['Fecha_Vencimiento'] : null;
                            
                            if (strpos(strtolower($estadoPago), 'pagad') !== false || $estadoPago === 'Pagado') {
                                $badgePago = 'badge-pagada';
                                $estadoPago = 'Pagada';
                            } elseif ($venc && $venc < $today && !$esAnulada) {
                                $badgePago = 'badge-vencida';
                                $estadoPago = 'Vencida';
                            } else {
                                $badgePago = 'badge-pendiente';
                                $estadoPago = 'Pendiente';
                            }

                            $fechaStr = ($f['Fecha'] instanceof DateTime) ? $f['Fecha']->format('d/m/Y') : ($f['Fecha'] ?? '--');
                            
                            $tasa_aplicada = ($f['Tasa_Cambio'] > 0) ? $f['Tasa_Cambio'] : $tasa_hoy;
                            $mo_usd = $f['Mano_Obra'] ?? 0;
                            $mo_nio = $mo_usd * $tasa_aplicada;
                            $total_usd = $f['TotalUSD'] ?? 0;
                            $total_nio = ($f['Total_NIO'] > 0) ? $f['Total_NIO'] : ($total_usd * $tasa_aplicada);
                    ?>
                        <tr class="<?php echo $esAnulada ? 'anulacion-row' : ''; ?>">
                            <td class="text-center"><span class="factura-id">#<?php echo $f['Id_Factura']; ?></span></td>
                            <td><i class="fa-regular fa-calendar me-2 text-muted"></i><span class="fecha-text"><?php echo $fechaStr; ?></span></td>
                            
                            <td>
                                <strong style="color: #e2e8f0; font-size: 0.95rem;"><?php echo htmlspecialchars($f['Datos'] ?? '--'); ?></strong>
                                <?php if($esAnulada && !empty($f['Motivo_Anulacion'])): ?>
                                    <br><span style="color:#ef4444; font-size:0.8rem; font-weight: 600;"><i class="fa-solid fa-ban me-1"></i><?php echo htmlspecialchars($f['Motivo_Anulacion']); ?></span>
                                <?php endif; ?>
                                <br>
                                <button class="toggle-productos" onclick="toggleProductos(<?php echo $f['Id_Factura']; ?>)">
                                    <i class="fa-solid fa-boxes-stacked me-1"></i> Desglose de Materiales
                                </button>
                            </td>

                            <td class="text-end">
                                <span class="price-tag" style="color: #94a3b8;">C$ <?php echo number_format($mo_nio, 2); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="price-tag">C$ <?php echo number_format($total_nio, 2); ?></span>
                            </td>
                            
                            <td class="text-center"><span style="color:#94a3b8; font-weight: bold; font-size:0.85rem; text-transform: uppercase;"><?php echo htmlspecialchars($f['Tipo_Pago'] ?? '--'); ?></span></td>
                            <td class="text-center"><span class="badge-estado <?php echo $badgePago; ?>"><?php echo $estadoPago; ?></span></td>
                            <td><i class="fa-solid fa-user-tie me-2 text-muted"></i><span class="encargado-text"><?php echo htmlspecialchars($f['Encargado'] ?? '--'); ?></span></td>
                        </tr>
                        
                        <tr id="productos-<?php echo $f['Id_Factura']; ?>" style="display:none;" class="productos-collapse">
                            <td colspan="8">
                                <div class="productos-inner">
                                    <?php
                                    $prods = getProductosFactura($conn, $f['Id_Factura']);
                                    if ($prods && sqlsrv_has_rows($prods)):
                                    ?>
                                    <table>
                                        <thead><tr>
                                            <th width="40%">Material Suministrado</th>
                                            <th width="15%" class="text-center">Cant.</th>
                                            <th width="20%" class="text-end">Precio Unit. (C$)</th>
                                            <th width="25%" class="text-end">Subtotal Línea (C$)</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php 
                                        while ($p = sqlsrv_fetch_array($prods, SQLSRV_FETCH_ASSOC)): 
                                            $precio_unit_usd = $p['Precio_Unitario'] ?? 0;
                                            $precio_unit_nio = ($p['Precio_Unitario_NIO'] > 0) ? $p['Precio_Unitario_NIO'] : ($precio_unit_usd * $tasa_aplicada);
                                            $sub_linea_nio = $p['Cantidad'] * $precio_unit_nio;
                                        ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #e2e8f0;"><i class="fa-solid fa-circle-check me-2" style="color: var(--success); opacity: 0.7;"></i><?php echo htmlspecialchars($p['Producto']); ?></td>
                                                <td class="text-center" style="font-weight: bold; font-size: 1rem;"><?php echo $p['Cantidad']; ?></td>
                                                <td class="text-end font-monospace">C$ <?php echo number_format($precio_unit_nio, 2); ?></td>
                                                <td class="text-end font-monospace" style="color:#10b981; font-weight: bold;">C$ <?php echo number_format($sub_linea_nio, 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                        <div style="color:#64748b; font-size:0.9rem; text-align: center; padding: 10px;"><i class="fa-solid fa-box-open me-2"></i>Factura de solo servicio. No se registraron salidas de materiales.</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8">
                            <div style="text-align: center; padding: 4rem; color: #475569;">
                                <i class="fa-solid fa-file-circle-xmark mb-3" style="font-size: 3rem; opacity: 0.5;"></i><br>
                                Este cliente aún no tiene facturas registradas en su historial.
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-bitacora" class="tab-pane">
            <div class="timeline-list">
                <?php
                if ($bitacora && sqlsrv_has_rows($bitacora)):
                    while ($b = sqlsrv_fetch_array($bitacora, SQLSRV_FETCH_ASSOC)):
                        $accion = strtoupper($b['Accion'] ?? '');

                        if (strpos($accion, 'INSERT') !== false || strpos($accion, 'CREO') !== false || strpos($accion, 'NUEVO') !== false) {
                            $dotClass = 'dot-insert'; $icon = 'fa-plus';
                        } elseif (strpos($accion, 'UPDATE') !== false || strpos($accion, 'EDIT') !== false || strpos($accion, 'MODIF') !== false) {
                            $dotClass = 'dot-update'; $icon = 'fa-pen';
                        } elseif (strpos($accion, 'DELETE') !== false || strpos($accion, 'ELIM') !== false || strpos($accion, 'ANUL') !== false) {
                            $dotClass = 'dot-delete'; $icon = 'fa-trash';
                        } else {
                            $dotClass = 'dot-other'; $icon = 'fa-circle-info';
                        }

                        $fechaHora = ($b['Fecha_Hora'] instanceof DateTime)
                            ? $b['Fecha_Hora']->format('d/m/Y H:i')
                            : ($b['Fecha_Hora'] ?? '--');
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo $dotClass; ?>">
                        <i class="fa-solid <?php echo $icon; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-action"><?php echo htmlspecialchars($b['Accion']); ?></div>
                        <div class="timeline-detail"><?php echo htmlspecialchars($b['Detalle']); ?></div>
                        <div class="timeline-meta">
                            <i class="fa-regular fa-clock me-1"></i><?php echo $fechaHora; ?>
                            &nbsp;·&nbsp;
                            <strong><i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($b['Usuario']); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="empty-state" style="text-align: center; padding: 2.5rem; color: #475569; font-size: 0.9rem;">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>
                        No se encontraron registros de auditoría para este cliente.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
    }

    function toggleProductos(idFactura) {
        const row = document.getElementById('productos-' + idFactura);
        if (!row) return;
        row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
    }
</script>

</body>
</html>