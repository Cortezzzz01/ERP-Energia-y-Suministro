<?php
session_start();
include 'conexion.php';

// Capturar fechas del filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$filtro_sql = "";
$params = array();

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $filtro_sql = " WHERE CAST(p.Fecha AS DATE) BETWEEN ? AND ? ";
    $params = array($fecha_inicio, $fecha_fin);
}

$sql = "SELECT p.*, e.Nombre AS Nombre_Encargado 
        FROM PROFORMA p
        INNER JOIN ENCARGADO e ON p.Id_Encargado = e.Id_Encargado
        $filtro_sql
        ORDER BY p.Id_Proforma DESC";

$consulta = sqlsrv_query($conn, $sql, $params);

if ($consulta === false) {
    die("<div class='container mt-4'><div class='alert alert-danger'><strong>⚠️ Error en la base de datos:</strong><br><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Proformas - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">

    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); overflow: hidden; max-width: 1200px; margin: 0 auto; }
        
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(14, 165, 233, 0.3); }
        .card-title-dark { color: #f8fafc; font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4); color: white; }
        
        .btn-action { padding: 6px 10px; border-radius: 6px; font-size: 0.9rem; transition: all 0.2s; text-decoration: none; display: inline-block; margin: 0 2px; }
        .btn-view { border: 1px solid var(--border-color); color: #cbd5e1; background: rgba(255,255,255,0.02); }
        .btn-view:hover { border-color: var(--primary); color: var(--primary); background: rgba(14, 165, 233, 0.1); }
        .btn-pdf { border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; background: transparent; }
        .btn-pdf:hover { border-color: #ef4444; color: white; background: rgba(239, 68, 68, 0.1); }
        .btn-anular { border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24; background: transparent; }
        .btn-anular:hover { border-color: #f59e0b; color: white; background: rgba(245, 158, 11, 0.1); }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; vertical-align: middle; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { border-bottom: 1px solid var(--border-color); transition: background-color 0.2s; }
        .table-dark-custom tbody tr:hover { background-color: rgba(14, 165, 233, 0.05); }
        .table-dark-custom tbody td { padding: 15px; background-color: transparent; border: none; color: #f8fafc !important; font-weight: 500; }

        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .status-vigente { background-color: rgba(14, 165, 233, 0.1); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); }
        .status-convertida { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-anulado { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        .filter-box { padding: 15px 25px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; background-color: transparent; }
        .filter-label { color: #94a3b8; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; margin-right: 10px; display: flex; align-items: center; }
        
        .filter-input { background-color: #0f172a; border: 1px solid #1e293b; color: #e2e8f0; border-radius: 8px; padding: 8px 14px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; color-scheme: dark; }
        .filter-input:focus { border-color: var(--primary); }
        
        .btn-filter { background-color: rgba(59, 130, 246, 0.05); border: 1px solid var(--primary); color: var(--primary); border-radius: 8px; padding: 8px 20px; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; }
        .btn-filter:hover { background-color: var(--primary); color: white; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card">
            
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <h4 class="card-title-dark">Historial de Proformas (Cotizaciones)</h4>
                </div>
                <div>
                    <a href="nueva_proforma.php" class="btn-primary-custom">
                        <i class="fa-solid fa-file-circle-plus me-2"></i>Nueva Cotización
                    </a>
                </div>
            </div>

            <div class="filter-box">
                <form method="GET" class="d-flex align-items-center flex-wrap" style="gap: 25px;">
                    <div class="d-flex align-items-center">
                        <label class="filter-label"><i class="fa-regular fa-calendar-days me-2"></i> DESDE:</label>
                        <input type="date" name="fecha_inicio" class="filter-input" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                    </div>
                    <div class="d-flex align-items-center">
                        <label class="filter-label">HASTA:</label>
                        <input type="date" name="fecha_fin" class="filter-input" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn-filter">
                            <i class="fa-solid fa-filter me-2"></i> Filtrar
                        </button>
                        <?php if(!empty($fecha_inicio)): ?>
                            <a href="ver_proformas.php" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; padding: 8px 15px;">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead>
                        <tr>
                            <th width="8%" class="text-center">No.</th>
                            <th width="32%">Cliente / Datos del Proyecto</th>
                            <th width="20%">Encargado</th>
                            <th width="10%" class="text-center">Fecha</th>
                            <th width="10%" class="text-end">Total Est. (C$)</th>
                            <th width="10%" class="text-center">Estado</th>
                            <th width="10%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hay_proformas = false;
                        while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                            $hay_proformas = true;
                            
                            $fecha = is_object($fila['Fecha']) ? $fila['Fecha']->format('d/m/Y') : $fila['Fecha'];
                            
                            $clase_estado = 'status-vigente';
                            if ($fila['Estado'] == 'Convertida') { $clase_estado = 'status-convertida'; }
                            if ($fila['Estado'] == 'Anulado') { $clase_estado = 'status-anulado'; }
                            
                            // Toma el valor puro de la BD en Córdobas
                            $total_nio = $fila['Total'];
                        ?>
                        <tr>
                            <td class="text-center font-monospace opacity-75">#<?php echo $fila['Id_Proforma']; ?></td>
                            <td>
                                <i class="fa-regular fa-clipboard me-2" style="color: var(--primary);"></i><?php echo htmlspecialchars($fila['Datos']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($fila['Nombre_Encargado']); ?></td>
                            <td class="text-center font-monospace opacity-75"><?php echo $fecha; ?></td>
                            <td class="text-end font-monospace text-success fw-bold">C$ <?php echo number_format($total_nio, 2); ?></td>
                            <td class="text-center">
                                <span class="status-badge <?php echo $clase_estado; ?>">
                                    <?php echo htmlspecialchars($fila['Estado']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="ver_ticket_proforma.php?id=<?php echo $fila['Id_Proforma']; ?>" class="btn-action btn-view" title="Ver Detalles">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="generar_pdf_proforma.php?id=<?php echo $fila['Id_Proforma']; ?>" target="_blank" class="btn-action btn-pdf" title="Descargar PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                    
                                    <?php if($fila['Estado'] == 'Vigente'): ?>
                                    <a href="anular_proforma.php?id=<?php echo $fila['Id_Proforma']; ?>" class="btn-action btn-anular" title="Anular Proforma" onclick="return confirm('¿Estás seguro que deseas ANULAR esta proforma? Esta acción no se puede deshacer.');">
                                        <i class="fa-solid fa-ban"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if(!$hay_proformas): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color: #64748b !important;">
                                <i class="fa-solid fa-folder-minus d-block fs-1 mb-3 opacity-50"></i>
                                No hay proformas registradas en este rango de fechas.
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