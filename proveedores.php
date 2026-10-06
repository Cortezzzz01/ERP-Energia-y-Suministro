<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';
$alert = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // El '??' actúa como escudo si el navegador se queda pegado y no manda el dato
    $nombre    = $_POST['nombre'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $correo    = $_POST['correo'] ?? '';
    $telefono  = $_POST['telefono'] ?? '';

    // Evitamos enviar vacíos a SQL Server
    if (empty($nombre)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Falta el nombre de la empresa. (Presiona Ctrl + F5 para recargar bien la página).</div>";
    } else {
        // Quitamos el ID para que SQL Server lo genere solito
        $sql = "INSERT INTO PROVEEDOR (Nombre, Direccion, Correo, Telefono) VALUES (?, ?, ?, ?)";
        $params = array($nombre, $direccion, $correo, $telefono);
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $alert = "<div class='alert alert-custom-success shadow-sm mb-4'><i class='fa-solid fa-check-circle me-2'></i>¡Proveedor guardado exitosamente!</div>";
            
            // --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
            $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
            $accion_log = 'CREAR PROVEEDOR';
            $detalle_log = "Se registró el proveedor: " . $nombre;
            sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));
            // -------------------------------------------
            
        } else {
            $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>
                        <strong>⚠️ Error en base de datos:</strong><br>
                        <pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre>
                      </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Proveedores - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Fondo transparente/oscuro para encajar en el Dashboard */
        body { 
            background-color: #0f172a; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #cbd5e1;
            padding: 2rem 0;
        }

        /* Estilo de la Tarjeta Flotante */
        .dark-card { 
            background-color: #1e293b; 
            border: 1px solid #334155; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.1); 
        }

        /* Cabecera de la tarjeta */
        .card-header-dark {
            background: transparent;
            border-bottom: 1px solid #334155;
            padding: 1.5rem;
            display: flex;
            align-items: center;
        }

        .icon-box {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 1.5rem;
            margin-right: 15px;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .card-title-dark {
            color: #f8fafc;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
            font-size: 1.25rem;
            text-transform: uppercase;
        }

        /* Etiquetas de los campos */
        .form-label { 
            font-weight: 500; 
            color: #94a3b8; 
            font-size: 0.95rem; 
            margin-bottom: 0.4rem;
        }

        /* Estilos de los Inputs para que parezcan integrados */
        .input-group-text { 
            background-color: #0f172a; 
            border: 1px solid #334155; 
            color: #64748b; 
            border-right: none; 
        }
        .form-control { 
            background-color: #0f172a; 
            border: 1px solid #334155; 
            color: #f1f5f9; 
            border-left: none; 
        }
        
        /* Efecto de brillo al hacer clic en los inputs */
        .form-control:focus { 
            background-color: #0f172a; 
            color: #f1f5f9; 
            border-color: #3b82f6; 
            box-shadow: none; 
        }
        .input-group:focus-within .input-group-text, 
        .input-group:focus-within .form-control { 
            border-color: #3b82f6; 
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); 
        }

        .form-control::placeholder { color: #475569; }

        /* Botón de Guardar de Neón */
        .btn-glow { 
            background: linear-gradient(135deg, #2563eb, #3b82f6); 
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

        /* Botones secundarios */
        .btn-outline-custom { 
            border: 1px solid #334155; 
            color: #94a3b8; 
            transition: all 0.3s; 
            background: transparent; 
        }
        .btn-outline-custom:hover { 
            border-color: #3b82f6; 
            color: #3b82f6; 
            background: rgba(59, 130, 246, 0.05); 
        }

        /* Alertas personalizadas */
        .alert-custom-success { 
            background-color: rgba(16, 185, 129, 0.1); 
            border: 1px solid rgba(16, 185, 129, 0.4); 
            color: #34d399; 
        }
        .alert-custom-error { 
            background-color: rgba(239, 68, 68, 0.1); 
            border: 1px solid rgba(239, 68, 68, 0.4); 
            color: #f87171; 
        }
        .alert-custom-warning { 
            background-color: rgba(245, 158, 11, 0.1); 
            border: 1px solid rgba(245, 158, 11, 0.4); 
            color: #fbbf24; 
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
                            <i class="fa-solid fa-industry"></i>
                        </div>
                        <h4 class="card-title-dark">REGISTRAR PROVEEDOR</h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label">Nombre de la Empresa</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Distribuidora Eléctrica S.A." required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Dirección Físico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                                    <textarea name="direccion" class="form-control" rows="2" placeholder="Ubicación del proveedor..."></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Correo Electrónico</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" name="correo" class="form-control" placeholder="contacto@empresa.com">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-5">
                                    <label class="form-label">Teléfono</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                        <input type="text" name="telefono" class="form-control" placeholder="(+505) 0000-0000">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR PROVEEDOR
                                </button>
                                
                                <hr style="border-color: #334155; margin: 10px 0;">
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="ver_proveedores.php" class="btn btn-outline-custom w-100 py-2">
                                            <i class="fa-solid fa-truck-fast me-2"></i>Ver Proveedores
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="index.php" target="_parent" class="btn btn-outline-custom w-100 py-2">
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