<?php
include 'conexion.php';

if (!$conn) {
    die("<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>Error de conexión a la base de datos.</div></div>");
}

// 1. RECOGER FECHAS DEL FILTRO
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// 2. CONSTRUIR LA CONSULTA BLINDADA (Corregida para local)
$sql = "SELECT DISTINCT c.Id_Compra, c.Fecha, p.Nombre AS Nombre_Proveedor, 
        (SELECT ISNULL(SUM(cp.Cantidad * pr.Precio_Unitario), 0) * 1.15 
         FROM COMPRA_PRODUCTO cp 
         INNER JOIN PRODUCTO pr ON cp.Id_Producto = pr.Id_Producto 
         WHERE cp.Id_Compra = c.Id_Compra) AS Total_Calculado, 
        c.Estado, c.Motivo_Anulacion, c.Tipo_Pago, c.Estado_Pago, c.Fecha_Vencimiento 
        FROM COMPRA c
        INNER JOIN PROVEEDOR p ON c.Id_Proveedor = p.Id_Proveedor";

$params_compra = array();

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql .= " WHERE c.Fecha BETWEEN ? AND ?";
    $params_compra[] = $fecha_inicio . ' 00:00:00';
    $params_compra[] = $fecha_fin . ' 23:59:59';
}

$sql .= " ORDER BY c.Id_Compra DESC";
$consulta = sqlsrv_query($conn, $sql, $params_compra); 

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta de Compras:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Compras - Energía y Suministros S.A.</title>
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
        
        .filter-bar { background-color: transparent; border-bottom: 1px solid var(--border-color); padding: 15px 24px; display: flex; align-items: center; gap: 25px; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .filter-label { color: #94a3b8; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; display: flex; align-items: center; text-transform: uppercase; }
        .date-input { background-color: #0f172a; border: 1px solid #1e293b; color: #e2e8f0; border-radius: 8px; padding: 8px 14px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; color-scheme: dark; }
        .date-input:focus { border-color: var(--primary); }
        .btn-filter { background-color: rgba(59, 130, 246, 0.05); border: 1px solid var(--primary); color: var(--primary); border-radius: 8px; padding: 8px 20px; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; }
        .btn-filter:hover { background-color: var(--primary); color: white; }
        .btn-clear { background-color: transparent; border: 1px solid var(--border-color); color: #94a3b8; padding: 8px 15px; border-radius: 8px; transition: all 0.3s; text-decoration: none; font-size: 0.9rem; }
        .btn-clear:hover { border-color: var(--primary); color: var(--primary); background: rgba(59,130,246,0.1); }

        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: none; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: var(--primary); box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: #f8fafc !important; }
        
        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .supplier-name { color: #f8fafc !important; font-weight: 500; font-size: 1rem; }
        
        .price-tag { color: var(--success) !important; font-weight: bold; font-size: 1.15rem; letter-spacing: 0.5px; font-family: monospace; }
        
        .date-text { color: #f8fafc !important; font-size: 0.95rem; font-family: monospace; white-space: nowrap; display: inline-flex; align-items: center; }
        .date-text i { color: var(--primary); opacity: 0.9; }

        .badge-pagado { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-pendiente { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .vencimiento-text { font-size: 0.75rem; color: #94a3b8; display: block; margin-top: 4px; }
        
        .btn-action-custom { background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.4); color: var(--primary); padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block; margin-right: 4px; }
        .btn-action-custom:hover { background: var(--primary); color: white; box-shadow: 0 0 12px rgba(59, 130, 246, 0.4); transform: translateY(-2px); }

        .btn-pagar { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block; cursor: pointer; margin-right: 4px;}
        .btn-pagar:hover { background: #10b981; color: white; box-shadow: 0 0 12px rgba(16, 185, 129, 0.4); transform: translateY(-2px); }

        .btn-anular { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; cursor: pointer; font-size: 0.85rem; font-weight: bold; display: inline-block;}
        .btn-anular:hover { background: #ef4444; color: white; transform: translateY(-2px); }

        .fila-anulada { opacity: 0.6; background-color: rgba(239, 68, 68, 0.05) !important; }
        .fila-anulada .price-tag { color: #94a3b8 !important; text-decoration: line-through; }
        .badge-anulado { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-top: 5px; display: block; text-align: center; width: fit-content; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-receipt"></i></div>
                    <h4 class="card-title-dark">Historial de Compras</h4>
                </div>
                <div>
                    <a href="compras.php" class="btn-primary-custom me-2"><i class="fa-solid fa-cart-plus me-1"></i>Nueva</a>
                    <a href="index.php" target="_parent" class="btn-outline-custom"><i class="fa-solid fa-house me-1"></i>Menú</a>
                </div>
            </div>

            <div class="filter-bar">
                <form action="ver_compras.php" method="GET" class="d-flex align-items-center gap-3 w-100 flex-wrap">
                    <div class="filter-group">
                        <span class="filter-label"><i class="fa-regular fa-calendar-days me-2"></i> Desde:</span>
                        <input type="date" name="fecha_inicio" class="date-input" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-label">Hasta:</span>
                        <input type="date" name="fecha_fin" class="date-input" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                    </div>

                    <button type="submit" class="btn-filter">
                        <i class="fa-solid fa-filter me-2"></i> Filtrar
                    </button>
                    
                    <?php if(!empty($fecha_inicio) || !empty($fecha_fin)): ?>
                        <a href="ver_compras.php" class="btn-clear">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="buscador" class="form-control" placeholder="Buscar por Nº compra o nombre de proveedor..." onkeyup="filtrarTabla()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="10%">Compra</th>
                            <th width="12%">Fecha</th>
                            <th width="25%">Proveedor</th>
                            <th width="16%" class="text-center">Términos</th>
                            <th width="15%" class="text-end">Total (C$)</th>
                            <th width="22%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hay_registros = false;

                        while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                            $hay_registros = true;
                            $esAnulada = ($fila['Estado'] == 'Anulado');

                            $fecha_str = "Sin fecha";
                            if (!empty($fila['Fecha'])) {
                                if (is_object($fila['Fecha'])) {
                                    $fecha_str = $fila['Fecha']->format('d/m/Y');
                                } else {
                                    $fecha_str = date('d/m/Y', strtotime($fila['Fecha']));
                                }
                            }

                            $fecha_venc_str = "";
                            if (!empty($fila['Fecha_Vencimiento'])) {
                                if (is_object($fila['Fecha_Vencimiento'])) {
                                    $fecha_venc_str = $fila['Fecha_Vencimiento']->format('d/m/Y');
                                } else {
                                    $fecha_venc_str = date('d/m/Y', strtotime($fila['Fecha_Vencimiento']));
                                }
                            }

                            $total_nio = $fila['Total_Calculado'] ?? 0;
                        ?>
                        <tr class="<?php echo $esAnulada ? 'fila-anulada' : ''; ?>">
                            <td>
                                <span class="id-badge">#<?php echo $fila['Id_Compra']; ?></span>
                                <?php if($esAnulada) echo '<span class="badge-anulado mt-2" title="'.htmlspecialchars($fila['Motivo_Anulacion']).'">Anulada</span>'; ?>
                            </td>
                            <td class="date-text">
                                <i class="fa-regular fa-calendar me-2"></i><?php echo $fecha_str; ?>
                            </td>
                            <td class="supplier-name">
                                <i class="fa-solid fa-industry me-2" style="color: var(--primary); opacity: 0.7;"></i>
                                <?php echo htmlspecialchars($fila['Nombre_Proveedor']); ?>
                            </td>

                            <td class="text-center">
                                <?php if($fila['Estado_Pago'] == 'Pagado'): ?>
                                    <span class="badge-pagado"><i class="fa-solid fa-check me-1"></i>Pagado</span>
                                <?php else: ?>
                                    <span class="badge-pendiente"><i class="fa-solid fa-clock me-1"></i>Crédito</span>
                                    <span class="vencimiento-text">Vence: <?php echo $fecha_venc_str; ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <span class="price-tag">C$ <?php echo number_format($total_nio, 2); ?></span>
                            </td>
                            
                            <td class="text-center">
                                <a href="ver_ticket.php?id=<?php echo $fila['Id_Compra']; ?>" class="btn-action-custom" title="Ver Detalle">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                
                                <?php if(!$esAnulada): ?>
                                    <?php if($fila['Estado_Pago'] == 'Pendiente'): ?>
                                        <button onclick="marcarComoPagado(<?php echo $fila['Id_Compra']; ?>)" class="btn-pagar" title="Registrar Pago a Proveedor">
                                            <i class="fa-solid fa-hand-holding-dollar"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button onclick="solicitarAnulacion(<?php echo $fila['Id_Compra']; ?>, 'compra')" class="btn-anular" title="Anular Compra">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if(!$hay_registros): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open mb-3" style="font-size: 2.5rem; opacity: 0.5;"></i><br>
                                No se encontraron compras en el rango de fechas seleccionado.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filtrarTabla() {
            let input = document.getElementById("buscador");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("miTabla");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { 
                if (tr[i].getElementsByTagName("td").length === 1) continue;

                let row = tr[i];
                let textContent = row.textContent || row.innerText;
                if (textContent.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = ""; 
                } else {
                    row.style.display = "none"; 
                }
            }
        }

        function marcarComoPagado(id) {
            if(confirm("¿Confirmas que se ha realizado el pago total de esta compra al proveedor?")) {
                window.location.href = `procesar_pago.php?id=${id}&tipo=compra`; 
            }
        }

        function solicitarAnulacion(id, tipo) {
            let motivo = prompt("Por favor, ingresa el motivo de la anulación:");
            if (motivo != null && motivo.trim() !== "") {
                window.location.href = `anular_operacion.php?id=${id}&tipo=${tipo}&motivo=${encodeURIComponent(motivo)}`;
            } else if (motivo === "") {
                alert("Debes indicar un motivo para anular.");
            }
        }
    </script>

</body>
</html>