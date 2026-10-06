<?php
session_start();
include 'conexion.php';

$mensaje = $_GET['mensaje'] ?? '';

// Verificamos si es Administrador para mostrarle los datos financieros
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');

// --- CONSULTA NORMAL: Lista de proveedores ---
$sql = "SELECT Id_Proveedor, Nombre, Direccion, Correo, Telefono FROM PROVEEDOR ORDER BY Nombre ASC";
$consulta = sqlsrv_query($conn, $sql);

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta de Proveedores:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}

// --- CONSULTA BI (INTELIGENCIA DE NEGOCIOS): Top 3 Proveedores (Solo Admin) ---
$top_proveedores = [];
if ($es_admin) {
    // Sumamos el total comprado por proveedor, contamos cuántas compras y ordenamos de mayor a menor
    $sql_top = "SELECT TOP 3 
                    p.Nombre, 
                    COUNT(c.Id_Compra) as Total_Transacciones, 
                    SUM(c.TotalProductos) as Monto_Invertido,
                    MAX(c.Fecha) as Ultima_Compra
                FROM PROVEEDOR p
                INNER JOIN COMPRA c ON p.Id_Proveedor = c.Id_Proveedor
                WHERE c.Estado != 'Anulado'
                GROUP BY p.Id_Proveedor, p.Nombre
                ORDER BY Monto_Invertido DESC";
    $res_top = sqlsrv_query($conn, $sql_top);
    if ($res_top) {
        while ($row = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC)) {
            $top_proveedores[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Directorio de Proveedores - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #cbd5e1; padding: 2rem 1rem; }
        .dark-card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); overflow: hidden; }
        .card-header-dark { background: transparent; border-bottom: 1px solid #334155; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .card-title-dark { color: #f8fafc; font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .btn-outline-custom { border: 1px solid #334155; color: #94a3b8; transition: all 0.3s; background: transparent; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-outline-custom:hover { border-color: #3b82f6; color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
        .btn-primary-custom { background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); color: white; }
        
        /* Botón de Análisis (Dorado) */
        .btn-bi-custom {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none; color: white; padding: 8px 16px; border-radius: 8px;
            transition: all 0.3s; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
            text-decoration: none; display: inline-block; font-weight: 600;
        }
        .btn-bi-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4); }

        /* --- TABLA ESTANDARIZADA --- */
        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; }
        .table-dark-custom thead th { background-color: #0f172a; color: #94a3b8; border-bottom: 2px solid #3b82f6; border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid #334155; }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: #f8fafc !important; }
        
        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid #334155; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .proveedor-name { color: #f8fafc !important; font-weight: 600; font-size: 1.05rem; }
        .contact-text { font-size: 0.95rem; white-space: nowrap; display: inline-flex; align-items: center; color: #f8fafc !important; font-family: monospace; }
        .contact-text i { color: #3b82f6; opacity: 0.8; width: 18px; margin-right: 6px; }

        /* Buscador */
        .input-group-text { background-color: #0f172a; border: 1px solid #334155; color: #94a3b8; border-right: none; }
        .form-control { background-color: #0f172a; border: 1px solid #334155; color: #f8fafc; border-left: none; }
        .form-control:focus { border-color: #3b82f6; box-shadow: none; background-color: #0f172a; color: #f8fafc; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.5; }

        /* --- ESTILOS DEL MODAL CORPORATIVO (BI) --- */
        .modal-content-dark { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-header-dark { border-bottom: 1px solid var(--border-color); padding: 1.5rem; background-color: rgba(0,0,0,0.2); border-radius: 12px 12px 0 0; }
        .modal-footer-dark { border-top: 1px solid var(--border-color); padding: 1rem; background-color: rgba(0,0,0,0.2); border-radius: 0 0 12px 12px; }
        
        .bi-item { background-color: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; transition: border-color 0.2s; }
        .bi-item:hover { border-color: #3b82f6; }
        
        .bi-rank { font-size: 1.5rem; font-weight: 800; color: #64748b; width: 45px; text-align: center; font-family: monospace; border-right: 1px solid #334155; margin-right: 20px; padding-right: 10px; }
        .bi-rank-1 { color: #3b82f6; } /* El #1 se resalta sutilmente en azul corporativo */
        
        .bi-info { flex-grow: 1; padding-right: 20px; }
        .bi-name { font-size: 1.1rem; font-weight: 600; color: #f8fafc; margin-bottom: 8px; letter-spacing: 0.5px; }
        
        .bi-stats { font-size: 0.85rem; color: #94a3b8; display: flex; gap: 20px; }
        .bi-stats span i { color: #3b82f6; opacity: 0.7; margin-right: 5px; }
        
        .bi-amount-wrapper { text-align: right; min-width: 130px; }
        .bi-amount { font-size: 1.25rem; font-weight: 700; color: #10b981; font-family: monospace; letter-spacing: 0.5px; }
        .bi-amount-label { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        
        /* Barra medidora de volumen */
        .bi-progress-bg { background-color: #1e293b; height: 4px; border-radius: 2px; margin-top: 12px; overflow: hidden; width: 100%; }
        .bi-progress-bar { background-color: #3b82f6; height: 100%; border-radius: 2px; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card mt-3">
            
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-truck-fast"></i></div>
                    <h4 class="card-title-dark">Listado de Proveedores</h4>
                </div>
                <div>
                    <?php if ($es_admin && count($top_proveedores) > 0): ?>
                        <button type="button" class="btn-bi-custom me-2" data-bs-toggle="modal" data-bs-target="#modalTopProveedores">
                            <i class="fa-solid fa-chart-line me-1"></i> Análisis
                        </button>
                    <?php endif; ?>

                    <a href="proveedores.php" class="btn-primary-custom me-2"><i class="fa-solid fa-plus me-1"></i>Nuevo</a>
                    <a href="index.php" target="_parent" class="btn-outline-custom"><i class="fa-solid fa-house me-1"></i>Menú</a>
                </div>
            </div>

            <div class="p-3 border-bottom" style="border-color: #334155 !important;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="buscador" class="form-control" placeholder="Buscar por ID, nombre, dirección, correo o teléfono..." onkeyup="filtrarTabla()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="10%">ID</th>
                            <th width="25%">Nombre de Empresa</th>
                            <th width="30%">Dirección</th>
                            <th width="20%">Correo Electrónico</th>
                            <th width="15%">Teléfono</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><span class="id-badge">#<?php echo $fila['Id_Proveedor']; ?></span></td>
                            
                            <td class="proveedor-name">
                                <i class="fa-solid fa-building text-primary opacity-50 me-2" style="font-size: 0.9rem;"></i>
                                <?php echo htmlspecialchars($fila['Nombre']); ?>
                            </td>
                            
                            <td class="small" style="color: #cbd5e1;">
                                <?php if(!empty($fila['Direccion'])): ?>
                                    <i class="fa-solid fa-map-location-dot me-1" style="opacity: 0.5;"></i><?php echo htmlspecialchars($fila['Direccion']); ?>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if(!empty($fila['Correo'])): ?>
                                    <span class="contact-text"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($fila['Correo']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if(!empty($fila['Telefono'])): ?>
                                    <span class="contact-text"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($fila['Telefono']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if(sqlsrv_has_rows($consulta) === false): ?>
                <div class="p-5 text-center" style="color: #94a3b8;">
                    <i class="fa-solid fa-truck-slash fs-1 mb-3" style="opacity: 0.2;"></i>
                    <p>No hay proveedores registrados en la base de datos actualmente.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if ($es_admin): ?>
    <div class="modal fade" id="modalTopProveedores" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-dark">
                <div class="modal-header modal-header-dark">
                    <h5 class="modal-title" style="color: #f8fafc; font-weight: 600; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-chart-pie me-2" style="color: #3b82f6;"></i> Análisis de Socios Comerciales
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 25px;">
                        Métricas calculadas en base al volumen histórico de compras procesadas.
                    </p>
                    
                    <?php 
                    // Obtenemos el monto máximo para calcular el porcentaje de las barras visuales
                    $max_monto = 0;
                    if (count($top_proveedores) > 0) {
                        $max_monto = $top_proveedores[0]['Monto_Invertido'];
                    }

                    foreach ($top_proveedores as $index => $prov): 
                        $fecha_str = is_object($prov['Ultima_Compra']) ? $prov['Ultima_Compra']->format('d/m/Y') : date('d/m/Y', strtotime($prov['Ultima_Compra']));
                        
                        // Cálculo para la barra de progreso (El proveedor #1 siempre tendrá 100%)
                        $porcentaje = ($max_monto > 0) ? ($prov['Monto_Invertido'] / $max_monto) * 100 : 0;
                        $clase_rango = ($index == 0) ? 'bi-rank-1' : '';
                    ?>
                    <div class="bi-item">
                        <div class="bi-rank <?php echo $clase_rango; ?>">
                            0<?php echo $index + 1; ?>
                        </div>
                        <div class="bi-info">
                            <div class="bi-name"><?php echo htmlspecialchars($prov['Nombre']); ?></div>
                            <div class="bi-stats">
                                <span title="Transacciones facturadas"><i class="fa-solid fa-file-invoice"></i> <?php echo $prov['Total_Transacciones']; ?> Facturas</span>
                                <span title="Fecha del último movimiento"><i class="fa-regular fa-calendar-check"></i> Última op: <?php echo $fecha_str; ?></span>
                            </div>
                            <div class="bi-progress-bg">
                                <div class="bi-progress-bar" style="width: <?php echo $porcentaje; ?>%; <?php echo ($index == 0) ? 'background-color: #10b981;' : ''; ?>"></div>
                            </div>
                        </div>
                        <div class="bi-amount-wrapper">
                            <span class="bi-amount-label">Volumen Total</span>
                            <span class="bi-amount">$<?php echo number_format($prov['Monto_Invertido'], 2); ?></span>
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
        function filtrarTabla() {
            let input = document.getElementById("buscador");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("miTabla");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let row = tr[i];
                let textContent = row.textContent || row.innerText;
                if (textContent.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }
    </script>
</body>
</html>