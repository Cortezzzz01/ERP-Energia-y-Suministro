<?php
session_start(); 
include 'conexion.php';
$alert = "";

// Obtener proveedores para el select
$sql_proveedores = "SELECT Id_Proveedor, Nombre FROM PROVEEDOR";
$consulta_prov = sqlsrv_query($conn, $sql_proveedores);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha           = $_POST['fecha'] ?? '';
    $id_proveedor    = $_POST['id_proveedor'] ?? '';
    $tipo_pago       = $_POST['tipo_pago'] ?? 'Contado';

    $tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;

    $fecha_hoy = date('Y-m-d');
    $fecha_limite_atras = date('Y-m-d', strtotime('-30 days'));

    if (strtotime($fecha) > strtotime($fecha_hoy)) {
        $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>🚫 ¡Error! No puedes registrar compras en fechas futuras.</div>";
    } 
    elseif (strtotime($fecha) < strtotime($fecha_limite_atras)) {
        $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>🚫 ¡Error! Fecha fuera de rango. Solo puedes registrar compras de los últimos 30 días.</div>";
    }
    elseif (empty($fecha) || empty($id_proveedor)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Completa los campos obligatorios.</div>";
    } else {
        $estado_pago = ($tipo_pago === 'Crédito') ? 'Pendiente' : 'Pagado';
        $fecha_vencimiento = ($tipo_pago === 'Crédito') ? date('Y-m-d', strtotime($fecha . ' + 30 days')) : null;

        $sql = "INSERT INTO COMPRA (Fecha, TotalProductos, Id_Proveedor, Tipo_Pago, Estado_Pago, Fecha_Vencimiento, Tasa_Cambio, Total_NIO) OUTPUT INSERTED.Id_Compra VALUES (?, 0, ?, ?, ?, ?, ?, 0)";
        $params = array($fecha, $id_proveedor, $tipo_pago, $estado_pago, $fecha_vencimiento, $tasa_hoy);
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            sqlsrv_fetch($stmt);
            $id_generado = sqlsrv_get_field($stmt, 0);

            $alert = "<div class='alert alert-custom-success shadow-sm mb-4' style='border-left: 4px solid #10b981;'>
                        <h5 class='mb-2 fw-bold'><i class='fa-solid fa-circle-check me-2'></i>¡Ticket #$id_generado creado ($estado_pago)!</h5>
                        <p class='mb-3 small' style='color: #cbd5e1;'>El total en Córdobas se actualizará solo al ingresar los materiales.</p>
                        <a href='detalle_compra.php?ticket=$id_generado' class='btn btn-warning fw-bold text-dark w-100' style='box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); border: none; padding: 10px; border-radius: 8px;'>
                            <i class='fa-solid fa-arrow-right me-2'></i>Continuar al Paso 2: Añadir Materiales
                        </a>
                      </div>";
            
            $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
            $accion_log = 'CREAR COMPRA';
            $detalle_log = "Se creó el ticket de compra #$id_generado (Paso 1).";
            sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));
            
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
    <title>Registrar Compra - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #cbd5e1; padding: 2rem 0; }
        .dark-card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.1); }
        .card-header-dark { background: transparent; border-bottom: 1px solid #334155; padding: 1.5rem; display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.4); }
        .card-title-dark { color: #f8fafc; font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        .form-label { font-weight: 500; color: #94a3b8; font-size: 0.95rem; margin-bottom: 0.4rem; }
        .input-group-text { background-color: #0f172a; border: 1px solid #334155; color: #64748b; border-right: none; }
        .form-control, .form-select { background-color: #0f172a; border: 1px solid #334155; color: #f1f5f9; border-left: none; color-scheme: dark; }
        .form-control:focus, .form-select:focus { background-color: #0f172a; color: #f1f5f9; border-color: #3b82f6; box-shadow: none; }
        .btn-glow { background: linear-gradient(135deg, #2563eb, #3b82f6); border: none; font-weight: bold; letter-spacing: 1px; padding: 12px; border-radius: 8px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); transition: all 0.3s ease; color: white; }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6); color: white; }
        .btn-outline-custom { border: 1px solid #334155; color: #94a3b8; transition: all 0.3s; background: transparent; }
        .btn-outline-custom:hover { border-color: #3b82f6; color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
        .alert-custom-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; }
        .alert-custom-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; }
        .alert-custom-warning { background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24; }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                
                <?php echo $alert; ?>

                <div class="card dark-card">
                    <div class="card-header-dark">
                        <div class="icon-box"><i class="fa-solid fa-cart-shopping"></i></div>
                        <h4 class="card-title-dark">PASO 1: CREAR TICKET</h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label">Fecha de Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                                    <input type="date" name="fecha" class="form-control" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Proveedor</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-truck"></i></span>
                                    <select name="id_proveedor" class="form-select" required>
                                        <option value="" selected disabled>Seleccione proveedor...</option>
                                        <?php 
                                        if ($consulta_prov !== false) {
                                            while($prov = sqlsrv_fetch_array($consulta_prov, SQLSRV_FETCH_ASSOC)) {
                                                echo "<option value='".$prov['Id_Proveedor']."'>".$prov['Nombre']."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label">Términos de Pago</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-handshake"></i></span>
                                    <select name="tipo_pago" class="form-select" required>
                                        <option value="Contado" selected>Pago de Contado (Inmediato)</option>
                                        <option value="Crédito">Crédito Neto a 30 Días</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR TICKET
                                </button>
                                <div class="row g-3">
                                    <div class="col-6"><a href="ver_compras.php" class="btn btn-outline-custom w-100 py-2">Ver Historial</a></div>
                                    <div class="col-6"><a href="index.php" target="_parent" class="btn btn-outline-custom w-100 py-2">Menú</a></div>
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