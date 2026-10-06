<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';
$alert = "";

// --- MOTOR DE REGISTRO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recogemos los campos y limpiamos espacios accidentales
    $nombre        = trim($_POST['nombre'] ?? '');
    $direccion     = trim($_POST['direccion'] ?? '');
    $telefono      = trim($_POST['telefono'] ?? '');
    $correo        = trim($_POST['correo'] ?? '');
    $tipo_servicio = $_POST['tipo_servicio'] ?? '';

    if (empty($nombre)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Falta el nombre del cliente.</div>";
    } else {
        // 1. VALIDACIÓN ANTI-DUPLICADOS
        $sql_check = "SELECT COUNT(*) AS existe FROM CLIENTE WHERE Nombre = ?";
        $params_check = array($nombre);
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        $fila_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if ($fila_check['existe'] > 0) {
            // Si ya existe, bloqueamos y avisamos
            $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>
                        <i class='fa-solid fa-triangle-exclamation me-2'></i>El cliente <strong>" . htmlspecialchars($nombre) . "</strong> ya está registrado en la cartera.
                      </div>";
        } else {
            // 2. Si no existe, guardamos
            $sql = "INSERT INTO CLIENTE (Nombre, Direccion, TipoServicio, Telefono, Correo) VALUES (?, ?, ?, ?, ?)";
            $params = array($nombre, $direccion, $tipo_servicio, $telefono, $correo);
            
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                $alert = "<div class='alert alert-custom-success shadow-sm mb-4'><i class='fa-solid fa-check-circle me-2'></i>¡Cliente registrado exitosamente!</div>";
                
                // --- REGISTRO INVISIBLE EN BITÁCORA ---
                $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
                $accion_log = 'CREAR CLIENTE';
                $detalle_log = "Se registró el nuevo cliente/empresa: " . $nombre;

                $sql_bitacora = "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)";
                sqlsrv_query($conn, $sql_bitacora, array($usuario_actual, $accion_log, $detalle_log));
                // ---------------------------------------
                
            } else {
                $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>
                            <strong>⚠️ Error al guardar:</strong><br>
                            <pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre>
                          </div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Clientes - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { 
            background-color: var(--bg-dark); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: var(--text-main);
            padding: 2rem 0;
        }

        .dark-card { 
            background-color: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.1); 
        }

        .card-header-dark {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            align-items: center;
        }

        .icon-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 1.5rem;
            margin-right: 15px;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .card-title-dark {
            color: var(--text-main);
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
            font-size: 1.25rem;
            text-transform: uppercase;
        }

        .form-label { 
            font-weight: 500; 
            color: var(--text-muted); 
            font-size: 0.95rem; 
            margin-bottom: 0.4rem;
        }

        .input-group-text { 
            background-color: var(--bg-dark); 
            border: 1px solid var(--border-color); 
            color: var(--text-muted); 
            border-right: none; 
        }
        
        .form-control, .form-select { 
            background-color: var(--bg-dark); 
            border: 1px solid var(--border-color); 
            color: var(--text-main); 
            border-left: none; 
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        .form-control:focus, .form-select:focus { 
            background-color: var(--bg-dark); 
            color: var(--text-main); 
            border-color: var(--primary); 
            box-shadow: none; 
        }
        .input-group:focus-within .input-group-text, 
        .input-group:focus-within .form-control,
        .input-group:focus-within .form-select { 
            border-color: var(--primary); 
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); 
        }

        .form-control::placeholder { color: var(--border-color); }

        .btn-glow { 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
            border: none; 
            font-weight: bold; 
            letter-spacing: 1px; 
            padding: 12px; 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); 
            transition: all 0.3s ease; 
            color: white; 
        }
        .btn-glow:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6); 
            color: white; 
        }

        .btn-outline-custom { 
            border: 1px solid var(--border-color); 
            color: var(--text-muted); 
            transition: all 0.3s; 
            background: transparent; 
        }
        .btn-outline-custom:hover { 
            border-color: var(--primary); 
            color: var(--primary); 
            background: rgba(59, 130, 246, 0.05); 
        }

        .alert-custom-success { 
            background-color: #10b9811a; 
            border: 1px solid #10b98166; 
            color: var(--success); 
        }
        .alert-custom-error { 
            background-color: #ef44441a; 
            border: 1px solid #ef444466; 
            color: var(--danger); 
        }
        .alert-custom-warning { 
            background-color: #f59e0b1a; 
            border: 1px solid #f59e0b66; 
            color: var(--warning); 
        }
        
        hr { border-color: var(--border-color) !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                
                <?php echo $alert; ?>

                <div class="card dark-card">
                    <div class="card-header-dark">
                        <div class="icon-box">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <h4 class="card-title-dark">REGISTRAR NUEVO CLIENTE</h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label">Nombre Completo / Empresa</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-address-card"></i></span>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez o Empresa S.A." required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Dirección</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                                    <textarea name="direccion" class="form-control" rows="2" placeholder="Ubicación del cliente o proyecto"></textarea>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Teléfono / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                    <input type="text" name="telefono" class="form-control" placeholder="Ej: +505 8888 8888" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Correo Electrónico (Opcional)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" name="correo" class="form-control" placeholder="Ej: cliente@empresa.com" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label">Tipo de Servicio</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-bolt"></i></span>
                                    <select name="tipo_servicio" class="form-select" required>
                                        <option value="" selected disabled>Seleccione una opción...</option>
                                        <option value="Suministro Eléctrico">Suministro Eléctrico</option>
                                        <option value="Mantenimiento">Mantenimiento</option>
                                        <option value="Instalación">Instalación</option>
                                        <option value="Consultoría">Consultoría</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR CLIENTE
                                </button>
                                
                                <hr style="margin: 10px 0;">
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="ver_clientes.php" class="btn btn-outline-custom w-100 py-2 text-center">
                                            <i class="fa-solid fa-users-viewfinder me-2"></i>Ver Cartera
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="index.php" target="_parent" class="btn btn-outline-custom w-100 py-2 text-center">
                                            <i class="fa-solid fa-house me-2"></i>Menú Principal
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>