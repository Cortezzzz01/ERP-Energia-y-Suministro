<?php
include 'conexion.php';
$alert = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiamos los espacios accidentales
    $nombre   = trim($_POST['nombre'] ?? '');
    $cargo    = $_POST['cargo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? ''); 
    $cedula   = trim($_POST['cedula'] ?? '');   

    if (empty($nombre) || empty($cargo)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Por favor, llena el nombre y el cargo.</div>";
    } else {
        // 1. VALIDACIÓN ANTI-DUPLICADOS (Por Nombre de empleado)
        $sql_check = "SELECT COUNT(*) AS existe FROM ENCARGADO WHERE Nombre = ?";
        $params_check = array($nombre);
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        $fila_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if ($fila_check['existe'] > 0) {
            $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>
                        <i class='fa-solid fa-triangle-exclamation me-2'></i>El empleado <strong>" . htmlspecialchars($nombre) . "</strong> ya está registrado en el sistema.
                      </div>";
        } else {
            // 2. Si no existe, guardamos como recurso humano
            $sql = "INSERT INTO ENCARGADO (Nombre, Cargo, Telefono, Cedula) VALUES (?, ?, ?, ?)";
            $params = array($nombre, $cargo, $telefono, $cedula);
            
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                $alert = "<div class='alert alert-custom-success shadow-sm mb-4'><i class='fa-solid fa-check-circle me-2'></i>¡Personal registrado exitosamente!</div>";
            } else {
                $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>
                            <strong>⚠️ Error en base de datos:</strong><br>
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
    <title>Registro de Personal - Energía y Suministros S.A.</title>
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

        .form-control::placeholder { 
            color: var(--border-color); 
        }

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
            padding: 15px; 
            border-radius: 10px;
        }

        .alert-custom-error { 
            background-color: #ef44441a; 
            border: 1px solid #ef444466; 
            color: var(--danger); 
            padding: 15px; 
            border-radius: 10px;
        }

        .alert-custom-warning { 
            background-color: #f59e0b1a; 
            border: 1px solid #f59e0b66; 
            color: var(--warning); 
            padding: 15px; 
            border-radius: 10px;
        }
        
        hr { 
            border-color: var(--border-color) !important; 
        }
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
                            <i class="fa-solid fa-hard-hat"></i>
                        </div>
                        <h4 class="card-title-dark">AÑADIR PERSONAL DE CAMPO</h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label">Nombre Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Cargo / Especialidad</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                                    <select name="cargo" class="form-select" required>
                                        <option value="" selected disabled>Seleccione un cargo...</option>
                                        <option value="Ingeniero Eléctrico">Ingeniero Eléctrico</option>
                                        <option value="Técnico Especialista">Técnico Especialista</option>
                                        <option value="Supervisor de Obra">Supervisor de Obra</option>
                                        <option value="Ayudante General">Ayudante General</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Cédula de Identidad</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-regular fa-id-badge"></i></span>
                                    <input type="text" name="cedula" class="form-control" placeholder="Ej: 001-000000-0000A" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label">Teléfono / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-mobile-screen"></i></span>
                                    <input type="text" name="telefono" class="form-control" placeholder="Ej: +505 8888 8888" autocomplete="off">
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR PERSONAL
                                </button>
                                
                                <hr style="margin: 10px 0;">
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="ver_encargados.php" class="btn btn-outline-custom w-100 py-2 text-center">
                                            <i class="fa-solid fa-users-gear me-2"></i>Ver Plantilla
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