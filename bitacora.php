<?php
// Si usas seguridad.php para validar sesión, descomenta la siguiente línea
// include 'seguridad.php';
include 'conexion.php';

// Consultamos todo el historial ordenado desde el evento más reciente
$sql_bitacora = "SELECT Id_Bitacora, Usuario, Accion, Detalle, Fecha_Hora 
                 FROM BITACORA_AUDITORIA 
                 ORDER BY Fecha_Hora DESC";
$consulta = sqlsrv_query($conn, $sql_bitacora);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de Auditoría - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background-color: transparent; /* Para que tome el fondo del iframe */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #cbd5e1; 
            padding: 2rem; 
        }
        .dark-card { 
            background-color: #1e293b; 
            border: 1px solid #334155; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); 
        }
        .card-header-dark { 
            border-bottom: 1px solid #334155; 
            padding: 1.5rem; 
            display: flex; 
            align-items: center; 
        }
        .icon-box { 
            background: linear-gradient(135deg, #8b5cf6, #6d28d9); 
            color: white; 
            padding: 12px 15px; 
            border-radius: 10px; 
            font-size: 1.5rem; 
            margin-right: 15px; 
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3); 
        }
        .card-title-dark { 
            color: #f8fafc; 
            font-weight: 600; 
            margin: 0; 
            font-size: 1.25rem; 
            text-transform: uppercase; 
        }
        .table-dark-custom { 
            width: 100%; 
            color: #e2e8f0; 
            margin-bottom: 0; 
        }
        .table-dark-custom thead th { 
            background-color: #0f172a; 
            color: #94a3b8; 
            border-bottom: 2px solid #8b5cf6; 
            text-transform: uppercase; 
            font-size: 0.85rem; 
            padding: 15px; 
            border-top: none; 
        }
        .table-dark-custom tbody tr { transition: background-color 0.2s; }
        .table-dark-custom tbody tr:hover { background-color: rgba(139, 92, 246, 0.05); }
        .table-dark-custom tbody td { 
            padding: 15px; 
            border-bottom: 1px solid #334155; 
            vertical-align: middle; 
        }
        .badge-accion {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        /* Colores dinámicos para las acciones */
        .accion-crear { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .accion-editar { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .accion-eliminar { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>

    <div class="container-fluid">
        <div class="dark-card">
            <div class="card-header-dark">
                <div class="icon-box"><i class="fa-solid fa-user-secret"></i></div>
                <div>
                    <h4 class="card-title-dark">Bitácora de Auditoría</h4>
                    <p class="mb-0" style="color: #94a3b8; font-size: 0.9rem;">Registro inmutable de actividades del sistema</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="15%">Usuario</th>
                            <th width="15%" class="text-center">Acción</th>
                            <th width="45%">Detalle del Movimiento</th>
                            <th width="20%">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($consulta !== false && sqlsrv_has_rows($consulta)): ?>
                            <?php while($row = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                                // Asignar clase CSS según el tipo de acción
                                $clase_badge = 'accion-editar'; // Por defecto
                                $accion = strtoupper($row['Accion']);
                                if(strpos($accion, 'CREAR') !== false || strpos($accion, 'REGISTRAR') !== false) $clase_badge = 'accion-crear';
                                if(strpos($accion, 'ELIMINAR') !== false || strpos($accion, 'ANULAR') !== false) $clase_badge = 'accion-eliminar';
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $row['Id_Bitacora']; ?></td>
                                <td class="fw-bold text-light"><i class="fa-solid fa-user-tie me-2" style="color: #64748b;"></i><?php echo htmlspecialchars($row['Usuario']); ?></td>
                                <td class="text-center"><span class="badge-accion <?php echo $clase_badge; ?>"><?php echo htmlspecialchars($row['Accion']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['Detalle']); ?></td>
                                <td class="font-monospace text-muted"><?php echo $row['Fecha_Hora']->format('d/m/Y h:i A'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-shield-halved fs-1 mb-3 opacity-50"></i>
                                    <p>No hay registros de auditoría todavía.</p>
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