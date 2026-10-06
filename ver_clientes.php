<?php
session_start();
include 'conexion.php';

// Verificamos si es Administrador para mostrarle el botón de BI
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');

// Rescatamos la tasa de cambio
$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;
$tasa_sql = (float)$tasa_hoy;

$sql = "SELECT Id_Cliente, Nombre, Direccion, TipoServicio, Telefono, Correo FROM CLIENTE ORDER BY Nombre ASC";
$consulta = sqlsrv_query($conn, $sql);

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta de Clientes:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}

$mensaje = $_GET['mensaje'] ?? '';

// --- CONSULTA BI (INTELIGENCIA DE NEGOCIOS): Top 3 Clientes (Multimoneda) ---
$top_clientes = [];
if ($es_admin) {
    $sql_bi = "SELECT TOP 3 
                    c.Nombre, 
                    COUNT(f.Id_Factura) as Total_Proyectos, 
                    SUM(f.Total) as Monto_Generado_USD,
                    SUM(CASE WHEN ISNULL(f.Total_NIO, 0) > 0 THEN f.Total_NIO ELSE f.Total * $tasa_sql END) as Monto_Generado_NIO,
                    MAX(f.Fecha) as Ultima_Factura
                FROM CLIENTE c
                INNER JOIN FACTURA f ON c.Id_Cliente = f.Id_Cliente
                WHERE f.Estado != 'Anulado'
                GROUP BY c.Id_Cliente, c.Nombre
                ORDER BY Monto_Generado_NIO DESC";
    $res_bi = sqlsrv_query($conn, $sql_bi);
    if ($res_bi) {
        while ($row = sqlsrv_fetch_array($res_bi, SQLSRV_FETCH_ASSOC)) {
            $top_clientes[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cartera de Clientes - Energía y Suministros S.A.</title>
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
        .btn-primary-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); }

        /* Botón de Análisis (Dorado) */
        .btn-bi-custom { background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-bi-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4); }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: #f8fafc !important; }
        
        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .client-name { color: #f8fafc !important; font-weight: 600; font-size: 1.05rem; }
        .data-desc { color: #cbd5e1 !important; }
        .contact-text { font-size: 0.95rem; white-space: nowrap; display: inline-flex; align-items: center; color: #f8fafc !important; font-family: monospace; }
        .contact-text i { color: var(--primary); opacity: 0.8; width: 18px; margin-right: 6px; }

        .service-badge { background-color: rgba(59, 130, 246, 0.1); color: #38bdf8; border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; display: inline-block; }

        .btn-action-edit { background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.4); color: var(--primary); padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-action-edit:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(59, 130, 246, 0.4); }
        .btn-action-delete { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; cursor: pointer; border: none;}
        .btn-action-delete:hover { background: #ef4444; color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(239, 68, 68, 0.4); }
        .btn-action-bitacora { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-action-bitacora:hover { background: #10b981; color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(16, 185, 129, 0.4); }

        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: none; background-color: var(--bg-dark); color: #f8fafc; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: var(--primary); box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.5; }

        /* --- ESTILOS DEL MODAL CORPORATIVO (BI) --- */
        .modal-content-dark { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-header-dark { border-bottom: 1px solid var(--border-color); padding: 1.5rem; background-color: rgba(0,0,0,0.2); border-radius: 12px 12px 0 0; }
        .modal-footer-dark { border-top: 1px solid var(--border-color); padding: 1rem; background-color: rgba(0,0,0,0.2); border-radius: 0 0 12px 12px; }
        
        .bi-item { background-color: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; transition: border-color 0.2s; }
        .bi-item:hover { border-color: #3b82f6; }
        
        .bi-rank { font-size: 1.5rem; font-weight: 800; color: #64748b; width: 45px; text-align: center; font-family: monospace; border-right: 1px solid #334155; margin-right: 20px; padding-right: 10px; }
        .bi-rank-1 { color: #3b82f6; }
        
        .bi-info { flex-grow: 1; padding-right: 20px; }
        .bi-name { font-size: 1.1rem; font-weight: 600; color: #f8fafc; margin-bottom: 8px; letter-spacing: 0.5px; }
        
        .bi-stats { font-size: 0.85rem; color: #94a3b8; display: flex; gap: 20px; }
        .bi-stats span i { color: #3b82f6; opacity: 0.7; margin-right: 5px; }
        
        .bi-amount-wrapper { text-align: right; min-width: 140px; }
        .bi-amount { font-size: 1.25rem; font-weight: 700; color: #10b981; font-family: monospace; letter-spacing: 0.5px; display: block;}
        .bi-amount-label { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        
        .bi-progress-bg { background-color: #1e293b; height: 4px; border-radius: 2px; margin-top: 12px; overflow: hidden; width: 100%; }
        .bi-progress-bar { background-color: #3b82f6; height: 100%; border-radius: 2px; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        
        <?php if($mensaje == 'editado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3' style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); padding: 15px; border-radius: 10px;"><i class='fa-solid fa-check-circle me-2'></i>Cliente actualizado correctamente.</div>
        <?php elseif($mensaje == 'eliminado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3' style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); padding: 15px; border-radius: 10px;"><i class='fa-solid fa-trash-can me-2'></i>Cliente eliminado de la cartera.</div>
        <?php elseif($mensaje == 'error'): ?>
            <div class='alert alert-custom-error shadow-sm mt-3' style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 15px; border-radius: 10px;"><i class='fa-solid fa-triangle-exclamation me-2'></i>Error al procesar la solicitud (el cliente puede tener facturas asociadas).</div>
        <?php endif; ?>

        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box">
                        <i class="fa-solid fa-address-book"></i>
                    </div>
                    <h4 class="card-title-dark">Listado de Clientes / Proyectos</h4>
                </div>
                <div>
                    <?php if ($es_admin && count($top_clientes) > 0): ?>
                        <button type="button" class="btn-bi-custom me-2" data-bs-toggle="modal" data-bs-target="#modalTopClientes">
                            <i class="fa-solid fa-chart-pie me-1"></i> Análisis
                        </button>
                    <?php endif; ?>

                    <a href="clientes.php" class="btn-primary-custom me-2">
                        <i class="fa-solid fa-user-plus me-1"></i>Nuevo
                    </a>
                    <a href="index.php" target="_parent" class="btn-outline-custom">
                        <i class="fa-solid fa-house me-1"></i>Menú
                    </a>
                </div>
            </div>

            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="buscador" class="form-control" placeholder="Buscar cliente por nombre, teléfono, correo o servicio..." onkeyup="filtrarTabla()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">ID</th>
                            <th width="20%">Nombre / Empresa</th>
                            <th width="13%">Teléfono</th>
                            <th width="18%">Correo Electrónico</th>
                            <th width="14%">Dirección</th>
                            <th width="12%">Servicio</th>
                            <th width="8%" class="text-center">Bitácora</th>
                            <th width="10%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td class="text-center"><span class="id-badge">#<?php echo $fila['Id_Cliente']; ?></span></td>
                            
                            <td class="client-name">
                                <i class="fa-regular fa-building text-primary opacity-50 me-2" style="font-size: 0.9rem;"></i>
                                <?php echo htmlspecialchars($fila['Nombre']); ?>
                            </td>
                            
                            <td>
                                <?php if(!empty($fila['Telefono'])): ?>
                                    <span class="contact-text"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($fila['Telefono']); ?></span>
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

                            <td class="data-desc small">
                                <?php if(!empty($fila['Direccion'])): ?>
                                    <i class="fa-solid fa-location-dot me-1" style="opacity: 0.5;"></i><?php echo htmlspecialchars($fila['Direccion']); ?>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="service-badge">
                                    <i class="fa-solid fa-gears me-1"></i><?php echo htmlspecialchars($fila['TipoServicio'] ?? 'N/A'); ?>
                                </span>
                            </td>

                            <td class="text-center">
                                <a href="bitacora_cliente.php?id=<?php echo $fila['Id_Cliente']; ?>" class="btn-action-bitacora" title="Ver Bitácora">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </a>
                            </td>
                            
                            <td class="text-center">
                                <a href="editar_cliente.php?id=<?php echo $fila['Id_Cliente']; ?>" class="btn-action-edit" title="Editar Cliente">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button onclick="confirmarEliminacion(<?php echo $fila['Id_Cliente']; ?>, '<?php echo addslashes(htmlspecialchars($fila['Nombre'])); ?>')" class="btn-action-delete" title="Eliminar Cliente">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if(sqlsrv_has_rows($consulta) === false): ?>
                <div class="text-center m-4" style="background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: var(--primary); border-radius: 10px; padding: 15px;">
                    <i class="fa-solid fa-users-slash fs-4 mb-2 d-block"></i>
                    No hay clientes registrados en la base de datos actualmente.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($es_admin): ?>
    <div class="modal fade" id="modalTopClientes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-dark">
                <div class="modal-header modal-header-dark">
                    <h5 class="modal-title" style="color: #f8fafc; font-weight: 600; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-chart-pie me-2" style="color: #3b82f6;"></i> Análisis de Socios Comerciales (Clientes)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 25px;">
                        Métricas calculadas en base al volumen histórico de proyectos facturados.
                    </p>
                    
                    <?php 
                    $max_monto = 0;
                    if (count($top_clientes) > 0) {
                        $max_monto = $top_clientes[0]['Monto_Generado_NIO'];
                    }

                    foreach ($top_clientes as $index => $cliente): 
                        $fecha_str = is_object($cliente['Ultima_Factura']) ? $cliente['Ultima_Factura']->format('d/m/Y') : date('d/m/Y', strtotime($cliente['Ultima_Factura']));
                        $porcentaje = ($max_monto > 0) ? ($cliente['Monto_Generado_NIO'] / $max_monto) * 100 : 0;
                        $clase_rango = ($index == 0) ? 'bi-rank-1' : '';
                    ?>
                    <div class="bi-item">
                        <div class="bi-rank <?php echo $clase_rango; ?>">
                            0<?php echo $index + 1; ?>
                        </div>
                        <div class="bi-info">
                            <div class="bi-name"><?php echo htmlspecialchars($cliente['Nombre']); ?></div>
                            <div class="bi-stats">
                                <span title="Proyectos facturados"><i class="fa-solid fa-file-invoice-dollar"></i> <?php echo $cliente['Total_Proyectos']; ?> Facturas</span>
                                <span title="Fecha de última facturación"><i class="fa-regular fa-calendar-check"></i> Última op: <?php echo $fecha_str; ?></span>
                            </div>
                            <div class="bi-progress-bg">
                                <div class="bi-progress-bar" style="width: <?php echo $porcentaje; ?>%; <?php echo ($index == 0) ? 'background-color: #10b981;' : ''; ?>"></div>
                            </div>
                        </div>
                        <div class="bi-amount-wrapper">
                            <span class="bi-amount-label">Volumen Facturado</span>
                            <span class="bi-amount">C$ <?php echo number_format($cliente['Monto_Generado_NIO'], 2); ?></span>
                            <span style="font-size: 0.75rem; color:#64748b; font-family:monospace; display:block; margin-top:2px;">Ref: $<?php echo number_format($cliente['Monto_Generado_USD'], 2); ?></span>
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

        function confirmarEliminacion(id, nombre) {
            if (confirm("¿Estás completamente seguro de que deseas eliminar al cliente: " + nombre + "?\n\nEsta acción no se puede deshacer y fallará si el cliente ya tiene facturas registradas.")) {
                window.location.href = "eliminar_cliente.php?id=" + id;
            }
        }
    </script>
</body>
</html>