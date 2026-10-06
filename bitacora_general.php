<?php
session_start();
include 'conexion.php';

// 1. EL CANDADO MAYOR: Si no es Administrador, denegamos el acceso inmediatamente
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    die("<div style='background:var(--bg-dark); color:#f87171; padding:40px; text-align:center; font-family:sans-serif; height:100vh; display:flex; flex-direction:column; justify-content:center;'>
            <h1 style='font-size:4rem; margin-bottom:10px;'><i class='fa-solid fa-shield-halved'></i></h1>
            <h2>⚠️ ACCESO RESTRINGIDO</h2>
            <p>Esta pantalla es de uso exclusivo para la Administración.</p>
         </div>");
}

// 2. CONSULTA GLOBAL: Traemos todo el historial ordenado desde lo más reciente
$sql_bitacora = "SELECT Id_Bitacora, Usuario, Accion, Detalle, Fecha_Hora 
                 FROM BITACORA_AUDITORIA 
                 ORDER BY Fecha_Hora DESC";
$consulta = sqlsrv_query($conn, $sql_bitacora);

if ($consulta === false) {
    die("Error en la consulta de auditoría: " . print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora Global de Auditoría - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    <style>
        body { 
            background-color: var(--bg-dark); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: var(--text-main);
            padding: 2rem 1rem;
        }

        .dark-card { 
            background-color: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); 
            overflow: hidden; 
        }

        .card-header-dark {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-wrapper { display: flex; align-items: center; }

        .icon-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 1.5rem;
            margin-right: 15px;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .card-title-dark { color: #f8fafc; font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }

        /* --- ESTILO DE TABLA ESTANDARIZADO --- */
        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; --bs-table-bg: transparent; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); background-color: transparent !important; }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05) !important; }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent !important; border: none; color: #f8fafc !important; }

        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .user-name { color: #f8fafc !important; font-weight: 600; font-size: 1rem; }

        /* Badges de acciones dinámicas */
        .badge-accion { padding: 5px 10px; border-radius: 6px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .accion-crear { background-color: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .accion-editar { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .accion-eliminar { background-color: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .accion-seguridad { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        /* Buscador */
        .form-control-custom { background: var(--bg-dark); border: 1px solid var(--border-color); color: #f8fafc; padding: 12px 15px; border-radius: 8px; width: 100%; transition: all 0.3s; }
        .form-control-custom:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); background: var(--bg-dark); color: #f8fafc; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-user-secret"></i></div>
                    <div>
                        <h4 class="card-title-dark">Bitácora Global de Auditoría</h4>
                        <p class="mb-0 small text-muted" style="letter-spacing: 0.3px;">Historial centralizado de movimientos y seguridad del sistema</p>
                    </div>
                </div>
            </div>

            <div class="p-3 border-bottom border-secondary">
                <input type="text" id="buscador" class="form-control-custom" placeholder="Filtrar por usuario, acción o palabra clave en el detalle..." onkeyup="filtrarTabla()">
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom" id="tablaBitacora">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="20%">Usuario</th>
                            <th width="15%" class="text-center">Acción</th>
                            <th width="45%">Detalle del Movimiento</th>
                            <th width="15%">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($consulta !== false && sqlsrv_has_rows($consulta)): ?>
                            <?php while($row = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                                $accion = strtoupper($row['Accion']);
                                
                                // Clasificación inteligente de estilos según la acción ejecutada
                                if (str_contains($accion, 'CREAR') || str_contains($accion, 'REGISTRAR') || str_contains($accion, 'EMITIR')) {
                                    $clase_badge = 'accion-crear';
                                } elseif (str_contains($accion, 'EDITAR') || str_contains($accion, 'MODIFICAR') || str_contains($accion, 'ACTUALIZAR')) {
                                    $clase_badge = 'accion-editar';
                                } elseif (str_contains($accion, 'ELIMINAR') || str_contains($accion, 'ANULAR') || str_contains($accion, 'BLOQUEAR')) {
                                    $clase_badge = 'accion-eliminar';
                                } else {
                                    $clase_badge = 'accion-seguridad'; // Para accesos, cambios de contraseña, etc.
                                }

                                $fechaFormateada = ($row['Fecha_Hora'] instanceof DateTime) ? $row['Fecha_Hora']->format('d/m/Y h:i A') : '--';
                            ?>
                            <tr>
                                <td class="text-center text-muted font-monospace"><span class="id-badge">#<?php echo $row['Id_Bitacora']; ?></span></td>
                                <td class="user-name"><i class="fa-solid fa-user-circle me-2" style="color: var(--primary); opacity:0.6;"></i><?php echo htmlspecialchars($row['Usuario']); ?></td>
                                <td class="text-center"><span class="badge-accion <?php echo $clase_badge; ?>"><?php echo htmlspecialchars($row['Accion']); ?></span></td>
                                <td style="color: #cbd5e1 !important; font-size: 0.92rem;"><?php echo htmlspecialchars($row['Detalle']); ?></td>
                                <td class="font-monospace text-muted small"><?php echo $fechaFormateada; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-shield-halved fs-1 mb-3 opacity-30"></i>
                                    <p>No se registran eventos en la auditoría interna todavía.</p>
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
            let input = document.getElementById("buscador").value.toLowerCase();
            let tr = document.getElementById("tablaBitacora").getElementsByTagName("tr");
            
            for (let i = 1; i < tr.length; i++) {
                let rowText = tr[i].textContent.toLowerCase();
                tr[i].style.display = (rowText.indexOf(input) > -1) ? "" : "none";
            }
        }
    </script>
</body>
</html>