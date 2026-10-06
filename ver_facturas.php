<?php
session_start();
include 'conexion.php';

// Verificamos si es Administrador para mostrarle el botón de BI
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');

if (!$conn) {
    die("<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>Error de conexión a la base de datos.</div></div>");
}

// 1. RECOGER FECHAS DEL FILTRO
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// 2. CONSTRUIR LA CONSULTA BLINDADA (Corregida para local)
$sql = "SELECT DISTINCT f.Id_Factura, f.Fecha, f.Datos, 
        f.Total, 
        e.Nombre AS Nombre_Encargado, 
        c.Nombre AS Nombre_Cliente,
        f.Estado, f.Motivo_Anulacion, f.Tipo_Pago, f.Estado_Pago, f.Fecha_Vencimiento 
        FROM FACTURA f
        INNER JOIN ENCARGADO e ON f.Id_Encargado = e.Id_Encargado
        LEFT JOIN CLIENTE c ON f.Id_Cliente = c.Id_Cliente"; 

$params_factura = array();

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql .= " WHERE f.Fecha BETWEEN ? AND ?";
    $params_factura[] = $fecha_inicio . ' 00:00:00';
    $params_factura[] = $fecha_fin . ' 23:59:59';
}

$sql .= " ORDER BY f.Id_Factura DESC";
$consulta = sqlsrv_query($conn, $sql, $params_factura);

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta de Facturas:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}

// --- CONSULTA BI (INTELIGENCIA DE NEGOCIOS): Análisis de Servicios ---
$rendimiento_servicios = [];
if ($es_admin) {
    $sql_bi = "SELECT 
                    c.TipoServicio, 
                    COUNT(DISTINCT f.Id_Factura) as Cantidad_Trabajos, 
                    SUM(ISNULL(f.Total, 0)) as Ingresos_Totales_NIO
                FROM FACTURA f
                INNER JOIN CLIENTE c ON f.Id_Cliente = c.Id_Cliente
                WHERE f.Estado != 'Anulado'
                GROUP BY c.TipoServicio
                ORDER BY Ingresos_Totales_NIO DESC";
    $res_bi = sqlsrv_query($conn, $sql_bi);
    if ($res_bi) {
        while ($row = sqlsrv_fetch_array($res_bi, SQLSRV_FETCH_ASSOC)) {
            if (empty($row['TipoServicio'])) {
                $row['TipoServicio'] = 'No Especificado';
            }
            $rendimiento_servicios[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Facturas - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); overflow: hidden; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .card-title-dark { color: #f8fafc; font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: #3b82f61a; }
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); }
        
        .btn-bi-custom { background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-bi-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4); }

        .filter-bar { background-color: transparent; border-bottom: 1px solid var(--border-color); padding: 15px 24px; display: flex; align-items: center; gap: 25px; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .filter-label { color: #94a3b8; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; display: flex; align-items: center; text-transform: uppercase; }
        .date-input { background-color: #0f172a; border: 1px solid #1e293b; color: #e2e8f0; border-radius: 8px; padding: 8px 14px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; color-scheme: dark; }
        .date-input:focus { border-color: var(--primary); }
        .btn-filter { background-color: rgba(59, 130, 246, 0.05); border: 1px solid var(--primary); color: var(--primary); border-radius: 8px; padding: 8px 20px; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; }
        .btn-filter:hover { background-color: var(--primary); color: white; }
        .btn-clear { background-color: transparent; border: 1px solid var(--border-color); color: #94a3b8; padding: 8px 15px; border-radius: 8px; transition: all 0.3s; text-decoration: none; font-size: 0.9rem; }
        .btn-clear:hover { border-color: var(--primary); color: var(--primary); background: rgba(59,130,246,0.1); }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: #f8fafc !important; }
        
        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .encargado-name { color: #f8fafc !important; font-weight: 500; }
        .cliente-name { color: #f8fafc !important; font-weight: bold; font-size: 0.95rem; }
        
        .price-tag { color: var(--success) !important; font-weight: bold; font-size: 1.15rem; letter-spacing: 0.5px; font-family: monospace; }

        .date-text { color: #f8fafc !important; font-size: 0.95rem; font-family: monospace; white-space: nowrap; display: inline-flex; align-items: center; }
        .date-text i { color: var(--primary); opacity: 0.9; }
        
        .badge-pagado { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; display: inline-block;}
        .badge-pendiente { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; display: inline-block;}
        .badge-vencido { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; animation: blink 2s infinite; display: inline-block;}
        @keyframes blink { 50% { opacity: 0.7; } }
        .vencimiento-text { font-size: 0.75rem; color: #94a3b8; display: block; margin-top: 4px; }
        
        .btn-action-custom { background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.4); color: var(--primary); padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block; margin-right: 4px;}
        .btn-action-custom:hover { background: var(--primary); color: white; box-shadow: 0 0 12px rgba(59, 130, 246, 0.4); transform: translateY(-2px); }
        
        .btn-pagar { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block; cursor: pointer; margin-right: 4px;}
        .btn-pagar:hover { background: #10b981; color: white; box-shadow: 0 0 12px rgba(16, 185, 129, 0.4); transform: translateY(-2px); }

        .btn-anular { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; cursor: pointer; font-size: 0.85rem; font-weight: bold; display: inline-block;}
        .btn-anular:hover { background: #ef4444; color: white; transform: translateY(-2px); }

        .fila-anulada { opacity: 0.6; background-color: rgba(239, 68, 68, 0.05) !important; }
        .fila-anulada .price-tag { color: #94a3b8 !important; text-decoration: line-through; }

        /* BI Modal */
        .modal-content-dark { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-header-dark { border-bottom: 1px solid var(--border-color); padding: 1.5rem; background-color: rgba(0,0,0,0.2); border-radius: 12px 12px 0 0; }
        .modal-footer-dark { border-top: 1px solid var(--border-color); padding: 1rem; background-color: rgba(0,0,0,0.2); border-radius: 0 0 12px 12px; }
        
        .bi-item { background-color: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; transition: border-color 0.2s; }
        .bi-item:hover { border-color: #10b981; }
        
        .bi-rank { font-size: 1.5rem; font-weight: 800; color: #64748b; width: 45px; text-align: center; font-family: monospace; border-right: 1px solid #334155; margin-right: 20px; padding-right: 10px; }
        .bi-rank-1 { color: #10b981; } 
        
        .bi-info { flex-grow: 1; padding-right: 20px; }
        .bi-name { font-size: 1.1rem; font-weight: 600; color: #f8fafc; margin-bottom: 8px; letter-spacing: 0.5px; }
        .bi-stats { font-size: 0.85rem; color: #94a3b8; display: flex; gap: 20px; }
        .bi-stats span i { color: #10b981; opacity: 0.7; margin-right: 5px; }
        
        .bi-amount-wrapper { text-align: right; min-width: 130px; }
        .bi-amount { font-size: 1.25rem; font-weight: 700; color: #10b981; font-family: monospace; letter-spacing: 0.5px; }
        .bi-amount-label { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        
        .bi-progress-bg { background-color: #1e293b; height: 4px; border-radius: 2px; margin-top: 12px; overflow: hidden; width: 100%; }
        .bi-progress-bar { background-color: #10b981; height: 100%; border-radius: 2px; opacity: 0.8; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <h4 class="card-title-dark">Control de Ingresos (Facturas)</h4>
                </div>
                <div>
                    <?php if ($es_admin && count($rendimiento_servicios) > 0): ?>
                        <button type="button" class="btn-bi-custom me-2" data-bs-toggle="modal" data-bs-target="#modalAnalisisServicios">
                            <i class="fa-solid fa-chart-pie me-1"></i> Análisis
                        </button>
                    <?php endif; ?>
                    
                    <a href="facturas.php" class="btn-primary-custom me-2"><i class="fa-solid fa-plus me-1"></i>Nueva</a>
                    <a href="index.php" target="_parent" class="btn-outline-custom"><i class="fa-solid fa-house me-1"></i>Menú</a>
                </div>
            </div>

            <div class="filter-bar">
                <form action="ver_facturas.php" method="GET" class="d-flex align-items-center gap-3 w-100 flex-wrap">
                    <div class="filter-group">
                        <span class="filter-label"><i class="fa-regular fa-calendar-days me-2"></i> Desde:</span>
                        <input type="date" name="fecha_inicio" class="date-input" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                    </div>
                    <div class="filter-group">
                        <span class="filter-label">Hasta:</span>
                        <input type="date" name="fecha_fin" class="date-input" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                    </div>
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter me-2"></i> Filtrar</button>
                    <?php if(!empty($fecha_inicio) || !empty($fecha_fin)): ?>
                        <a href="ver_facturas.php" class="btn-clear">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="8%">Factura</th> 
                            <th width="10%">Fecha</th> 
                            <th width="18%">Cliente</th> 
                            <th width="18%">Concepto</th> 
                            <th width="12%">Encargado</th> 
                            <th width="10%" class="text-center">Términos</th> 
                            <th width="12%" class="text-end">Total (C$)</th> 
                            <th width="12%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hay_registros = false;
                        $fecha_hoy = date('Y-m-d');

                        while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                             $hay_registros = true;
                             $esAnulada = ($fila['Estado'] == 'Anulado');
                             
                             $fecha_str = !empty($fila['Fecha']) ? (is_object($fila['Fecha']) ? $fila['Fecha']->format('d/m/Y') : date('d/m/Y', strtotime($fila['Fecha']))) : "Sin fecha";
                             
                             $fecha_venc_str = "";
                             $esVencido = false;
                             if (!empty($fila['Fecha_Vencimiento'])) {
                                 $fecha_venc_obj = is_object($fila['Fecha_Vencimiento']) ? $fila['Fecha_Vencimiento'] : new DateTime($fila['Fecha_Vencimiento']);
                                 $fecha_venc_str = $fecha_venc_obj->format('d/m/Y');
                                 if($fila['Estado_Pago'] == 'Pendiente' && $fecha_venc_obj->format('Y-m-d') < $fecha_hoy) {
                                     $esVencido = true;
                                 }
                             }

                            $total_nio = $fila['Total'] ?? 0;
                        ?>
                        <tr class="<?php echo $esAnulada ? 'fila-anulada' : ''; ?>">
                            <td><span class="id-badge">#<?php echo $fila['Id_Factura']; ?></span></td>
                            <td class="date-text"><i class="fa-regular fa-calendar me-2"></i><?php echo $fecha_str; ?></td>
                            
                            <td class="cliente-name">
                                <i class="fa-solid fa-user-tie me-2" style="color: var(--primary); opacity: 0.7;"></i>
                                <?php echo htmlspecialchars($fila['Nombre_Cliente'] ?? 'Sin Cliente'); ?>
                            </td>

                            <td class="data-desc small"><?php echo htmlspecialchars($fila['Datos']); ?></td>
                            <td class="encargado-name"><?php echo htmlspecialchars($fila['Nombre_Encargado']); ?></td>
                            
                            <td class="text-center">
                                <?php if($fila['Estado_Pago'] == 'Pagado'): ?>
                                    <span class="badge-pagado"><i class="fa-solid fa-check me-1"></i>Pagado</span>
                                <?php elseif($esVencido): ?>
                                    <span class="badge-vencido"><i class="fa-solid fa-triangle-exclamation me-1"></i>Vencido</span>
                                    <span class="vencimiento-text text-danger"><?php echo $fecha_venc_str; ?></span>
                                <?php else: ?>
                                    <span class="badge-pendiente"><i class="fa-solid fa-clock me-1"></i>Crédito</span>
                                    <span class="vencimiento-text">Vence: <?php echo $fecha_venc_str; ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <span class="price-tag">C$ <?php echo number_format($total_nio, 2); ?></span>
                            </td>

                            <td class="text-center">
                                <a href="ver_ticket_factura.php?id=<?php echo $fila['Id_Factura']; ?>" class="btn-action-custom"><i class="fa-solid fa-eye me-1"></i> Ver</a>
                                <?php if(!$esAnulada && $fila['Estado_Pago'] == 'Pendiente'): ?>
                                    <button onclick="marcarComoPagada(<?php echo $fila['Id_Factura']; ?>)" class="btn-pagar" title="Marcar como Pagada"><i class="fa-solid fa-hand-holding-dollar"></i></button>
                                <?php endif; ?>
                                <?php if(!$esAnulada): ?>
                                    <button onclick="solicitarAnulacion(<?php echo $fila['Id_Factura']; ?>, 'factura')" class="btn-anular" title="Anular Factura"><i class="fa-solid fa-ban"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($es_admin): ?>
    <div class="modal fade" id="modalAnalisisServicios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-dark">
                <div class="modal-header modal-header-dark">
                    <h5 class="modal-title" style="color: #f8fafc; font-weight: 600; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-bolt me-2" style="color: #10b981;"></i> Rendimiento por Tipo de Servicio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 25px;">
                        Análisis de ingresos generados según la categoría del servicio brindado (excluye facturas anuladas).
                    </p>
                    
                    <?php 
                    $max_ingreso = 0;
                    if (count($rendimiento_servicios) > 0) {
                        $max_ingreso = $rendimiento_servicios[0]['Ingresos_Totales_NIO'];
                    }

                    foreach ($rendimiento_servicios as $index => $servicio): 
                        $porcentaje = ($max_ingreso > 0) ? ($servicio['Ingresos_Totales_NIO'] / $max_ingreso) * 100 : 0;
                        $clase_rango = ($index == 0) ? 'bi-rank-1' : '';
                    ?>
                    <div class="bi-item">
                        <div class="bi-rank <?php echo $clase_rango; ?>">
                            0<?php echo $index + 1; ?>
                        </div>
                        <div class="bi-info">
                            <div class="bi-name"><?php echo htmlspecialchars($servicio['TipoServicio']); ?></div>
                            <div class="bi-stats">
                                <span title="Trabajos facturados"><i class="fa-solid fa-briefcase"></i> <?php echo $servicio['Cantidad_Trabajos']; ?> Proyectos ejecutados</span>
                            </div>
                            <div class="bi-progress-bg">
                                <div class="bi-progress-bar" style="width: <?php echo $porcentaje; ?>%; <?php echo ($index == 0) ? 'background-color: #10b981; opacity: 1;' : ''; ?>"></div>
                            </div>
                        </div>
                        <div class="bi-amount-wrapper">
                            <span class="bi-amount-label">Ingresos Generados</span>
                            <span class="bi-amount">C$ <?php echo number_format($servicio['Ingresos_Totales_NIO'], 2); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                </div>
                <div class="modal-footer modal-footer-dark">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cerrar Reporte</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function marcarComoPagada(id) {
            if(confirm("¿Confirmas que el cliente ha saldado esta cuenta?")) {
                window.location.href = `procesar_pago.php?id=${id}&tipo=factura`; 
            }
        }
        function solicitarAnulacion(id, tipo) {
            let motivo = prompt("Motivo de anulación:");
            if (motivo) window.location.href = `anular_operacion.php?id=${id}&tipo=${tipo}&motivo=${encodeURIComponent(motivo)}`;
        }
    </script>
</body>
</html>