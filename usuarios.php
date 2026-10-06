<?php
session_start();
include 'conexion.php';

// 1. EL CANDADO MAYOR: Si no es Administrador, lo bloqueamos en seco.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    die("<div style='background:var(--bg-dark); color:#f87171; padding:40px; text-align:center; font-family:sans-serif; height:100vh; display:flex; flex-direction:column; justify-content:center;'>
            <h1 style='font-size:4rem; margin-bottom:10px;'><i class='fa-solid fa-shield-halved'></i></h1>
            <h2>⚠️ ACCESO RESTRINGIDO</h2>
            <p>Esta pantalla es de uso exclusivo para la Administración.</p>
         </div>");
}

$mensaje = '';
$usuario_actual = $_SESSION['nombre'] ?? 'Administrador'; // Para la bitácora

// 2. PROCESAMIENTO DE FORMULARIOS (CREAR, EDITAR ESTADO, CAMBIAR CLAVE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // CREAR NUEVO USUARIO
    if ($accion === 'crear') {
        $nombre = $_POST['nombre'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'Vendedor';

        // Insertamos con Estado = 1 (Activo) por defecto
        $sqlInsert = "INSERT INTO USUARIO (Nombre_Completo, Correo, Password_Hash, Rol, Estado) VALUES (?, ?, ?, ?, 1)";
        $stmt = sqlsrv_query($conn, $sqlInsert, array($nombre, $correo, $password, $rol));

        if ($stmt) {
            $mensaje = "<div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-check-circle me-2'></i>Usuario creado exitosamente.</div>";
            
            // 🕵️‍♂️ BITÁCORA
            $detalle_log = "Se creó un nuevo usuario: $nombre con rol de $rol.";
            sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'CREAR USUARIO', ?)", array($usuario_actual, $detalle_log));
        } else {
            $mensaje = "<div class='alert alert-custom-error shadow-sm mt-3'><i class='fa-solid fa-triangle-exclamation me-2'></i>Error al crear usuario. Revisa que el correo no esté duplicado.</div>";
        }
    }

    // CAMBIAR ESTADO (Activo / Inactivo)
    if ($accion === 'toggle_estado') {
        $id_usuario = $_POST['id_usuario'];
        $nuevo_estado = $_POST['nuevo_estado']; // 1 o 0
        
        // --- PROTECCIÓN EXTRA EN BACKEND ---
        if ($id_usuario == $_SESSION['usuario_id'] || $id_usuario == 1) {
             $mensaje = "<div class='alert alert-custom-error shadow-sm mt-3'><i class='fa-solid fa-shield me-2'></i>No puedes alterar el estado del Administrador Principal ni tu propia cuenta activa.</div>";
        } else {
            $sqlUpdate = "UPDATE USUARIO SET Estado = ? WHERE Id_Usuario = ?";
            $stmt = sqlsrv_query($conn, $sqlUpdate, array($nuevo_estado, $id_usuario));
            
            if ($stmt) {
                $mensaje = "<div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-power-off me-2'></i>Estado actualizado correctamente.</div>";
                
                // 🕵️‍♂️ BITÁCORA
                $estado_txt = ($nuevo_estado == 1) ? "REACTIVÓ" : "BLOQUEÓ";
                $detalle_log = "Se $estado_txt el acceso al usuario con ID: $id_usuario.";
                sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'ESTADO USUARIO', ?)", array($usuario_actual, $detalle_log));
            }
        }
    }

    // CAMBIAR CONTRASEÑA
    if ($accion === 'cambiar_clave') {
        $id_usuario = $_POST['id_usuario'];
        $nueva_clave = $_POST['nueva_clave'];

        $sqlUpdateClave = "UPDATE USUARIO SET Password_Hash = ? WHERE Id_Usuario = ?";
        $stmt = sqlsrv_query($conn, $sqlUpdateClave, array($nueva_clave, $id_usuario));
        
        if ($stmt) {
            $mensaje = "<div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-key me-2'></i>Contraseña actualizada correctamente.</div>";
            
            // 🕵️‍♂️ BITÁCORA
            $detalle_log = "Se restableció la contraseña del usuario con ID: $id_usuario.";
            sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'CAMBIAR CONTRASEÑA', ?)", array($usuario_actual, $detalle_log));
        }
    }
}

// 3. CONSULTAR TODOS LOS USUARIOS
$sqlUsuarios = "SELECT Id_Usuario, Nombre_Completo, Correo, Rol, Estado FROM USUARIO ORDER BY Rol ASC, Nombre_Completo ASC";
$consulta = sqlsrv_query($conn, $sqlUsuarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Sistema - Energía y Suministros S.A.</title>
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

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        .btn-primary-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); }

        /* --- TABLA EXACTAMENTE IGUAL AL ERP --- */
        .table-dark-custom { width: 100%; margin-bottom: 0; color: #e2e8f0; --bs-table-bg: transparent; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); background-color: transparent !important; }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05) !important; }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent !important; border: none; color: #f8fafc !important; }

        .id-badge { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: #94a3b8; font-family: monospace; }
        .client-name { color: #f8fafc !important; font-weight: 600; font-size: 1.05rem; }

        .badge-rol { font-size: 0.8rem; padding: 5px 10px; border-radius: 6px; font-weight: 600; letter-spacing: 0.5px; }
        .rol-admin { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .rol-vendedor { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }

        .badge-estado { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; font-weight: bold; letter-spacing: 0.5px; }
        .estado-activo { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .estado-inactivo { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

        .btn-action-key { background: rgba(245, 158, 11, 0.05); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: all 0.3s; }
        .btn-action-key:hover { background: #f59e0b; color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(245, 158, 11, 0.4); }
        
        .btn-action-toggle { background: rgba(239, 68, 68, 0.05); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: all 0.3s; }
        .btn-action-toggle.activar { background: rgba(16, 185, 129, 0.05); color: #10b981; border-color: rgba(16, 185, 129, 0.4); }
        .btn-action-toggle:hover { background: #ef4444; color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(239, 68, 68, 0.4); }
        .btn-action-toggle.activar:hover { background: #10b981; color: white; transform: translateY(-2px); box-shadow: 0 0 12px rgba(16, 185, 129, 0.4); }

        /* Estilo para el botón deshabilitado del Admin */
        .btn-action-disabled { background: rgba(148, 163, 184, 0.1); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.3); padding: 6px 10px; border-radius: 6px; cursor: not-allowed; opacity: 0.6; }

        .alert-custom-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-custom-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 15px; border-radius: 10px; margin-bottom: 20px; }

        /* Estilos Modal Oscuro adaptado al ERP */
        .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 16px; box-shadow: 0 15px 40px rgba(0,0,0,0.6); }
        .modal-header { border-bottom: 1px solid var(--border-color); padding: 1.5rem; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border-color); padding: 1.2rem; }
        .form-control-custom, .form-select-custom { background: var(--bg-dark); border: 1px solid var(--border-color); color: #f8fafc; padding: 12px 15px; border-radius: 8px; width: 100%; transition: all 0.3s; }
        .form-control-custom:focus, .form-select-custom:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); background: var(--bg-dark); color: #f8fafc; }
        .form-label-custom { color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; display: block; letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        
        <?php echo $mensaje; ?>

        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-users-gear"></i></div>
                    <h4 class="card-title-dark">Gestor de Usuarios y Accesos</h4>
                </div>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                    <i class="fa-solid fa-user-plus me-2"></i>Nuevo Usuario
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">ID</th>
                            <th width="25%">Nombre Completo</th>
                            <th width="25%">Correo Electrónico</th>
                            <th width="15%">Rol de Acceso</th>
                            <th width="10%">Estado</th>
                            <th width="20%" class="text-center">Controles de Seguridad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                            $esActivo = (int)$u['Estado'] === 1;
                            $claseEstado = $esActivo ? 'estado-activo' : 'estado-inactivo';
                            $textoEstado = $esActivo ? 'ACTIVO' : 'BLOQUEADO';
                            
                            $claseRol = ($u['Rol'] === 'Administrador') ? 'rol-admin' : 'rol-vendedor';
                        ?>
                        <tr>
                            <td class="text-center"><span class="id-badge">#<?php echo $u['Id_Usuario']; ?></span></td>
                            <td class="client-name"><i class="fa-solid fa-user-tie me-2" style="color: var(--primary); opacity: 0.7;"></i><?php echo htmlspecialchars($u['Nombre_Completo']); ?></td>
                            <td style="color: #cbd5e1 !important; font-family: monospace;"><i class="fa-regular fa-envelope me-2" style="opacity:0.5;"></i><?php echo htmlspecialchars($u['Correo']); ?></td>
                            <td><span class="badge-rol <?php echo $claseRol; ?>"><?php echo htmlspecialchars($u['Rol']); ?></span></td>
                            <td><span class="badge-estado <?php echo $claseEstado; ?>"><?php echo $textoEstado; ?></span></td>
                            
                            <td class="text-center">
                                <button type="button" class="btn-action-key me-2" title="Cambiar Contraseña" 
                                    onclick="abrirModalClave(<?php echo $u['Id_Usuario']; ?>, '<?php echo htmlspecialchars($u['Nombre_Completo'], ENT_QUOTES); ?>')">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                
                                <?php if($u['Id_Usuario'] == $_SESSION['usuario_id'] || $u['Id_Usuario'] == 1): ?>
                                    <button type="button" class="btn-action-disabled" title="No puedes alterar el estado del Administrador Principal" disabled>
                                        <i class="fa-solid fa-shield"></i>
                                    </button>
                                <?php else: ?>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="accion" value="toggle_estado">
                                        <input type="hidden" name="id_usuario" value="<?php echo $u['Id_Usuario']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?php echo $esActivo ? 0 : 1; ?>">
                                        
                                        <?php if($esActivo): ?>
                                            <button type="submit" class="btn-action-toggle" title="Bloquear Acceso" onclick="return confirm('¿Estás seguro de bloquear el acceso a este usuario?');">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-action-toggle activar" title="Reactivar Acceso" onclick="return confirm('¿Estás seguro de reactivar el acceso a este usuario?');">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear">
                    <div class="modal-header">
                        <h5 class="modal-title card-title-dark"><i class="fa-solid fa-user-plus me-2" style="color: var(--primary);"></i>Crear Nuevo Usuario</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-custom">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control-custom" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Correo de Acceso</label>
                            <input type="text" name="correo" class="form-control-custom" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Contraseña Inicial</label>
                            <input type="text" name="password" class="form-control-custom" placeholder="Ej: Vendedor123" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Nivel de Privilegios</label>
                            <select name="rol" class="form-select-custom" required>
                                <option value="Vendedor" selected>Vendedor (Acceso Limitado)</option>
                                <option value="Administrador">Administrador (Acceso Total)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                        <button type="submit" class="btn-primary-custom">Guardar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCambiarClave" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="cambiar_clave">
                    <input type="hidden" name="id_usuario" id="inputIdUsuarioClave">
                    
                    <div class="modal-header">
                        <h5 class="modal-title card-title-dark"><i class="fa-solid fa-key me-2" style="color: #f59e0b;"></i>Restablecer Contraseña</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p style="color: #cbd5e1; font-size: 0.95rem; margin-bottom: 20px;">Ingresa la nueva contraseña para el usuario:<br><strong id="nombreUsuarioClave" style="color: #f8fafc; font-size: 1.1rem;"></strong></p>
                        <div class="mb-3">
                            <label class="form-label-custom">Nueva Contraseña</label>
                            <input type="text" name="nueva_clave" class="form-control-custom" required autocomplete="off" placeholder="Escribe la nueva clave">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                        <button type="submit" class="btn btn-warning fw-bold" style="border-radius: 8px; color: #78350f;">Actualizar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalClave(id, nombre) {
            document.getElementById('inputIdUsuarioClave').value = id;
            document.getElementById('nombreUsuarioClave').innerText = nombre;
            new bootstrap.Modal(document.getElementById('modalCambiarClave')).show();
        }
    </script>
</body>
</html>