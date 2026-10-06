<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';
$alert = "";

$id_producto = $_GET['id'] ?? '';

if (empty($id_producto)) {
    header("Location: ver_productos.php");
    exit();
}

// 1. Procesar la actualización si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = $_POST['precio'];

    // Hacemos el UPDATE en lugar de INSERT
    $sql_update = "UPDATE PRODUCTO SET Nombre = ?, Descripcion = ?, Precio_Unitario = ? WHERE Id_Producto = ?";
    $params_update = array($nombre, $descripcion, $precio, $id_producto);
    $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

    if ($stmt_update) {
        // --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
        $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
        $accion_log = 'EDITAR PRODUCTO';
        $detalle_log = "Se modificaron los datos del producto ID #$id_producto (Nuevo Nombre: $nombre).";
        sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));
        // -------------------------------------------
        
        header("Location: ver_productos.php?mensaje=editado");
        exit();
    } else {
        $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>
                    <strong>⚠️ Error al actualizar:</strong><br>
                    <pre class='mt-2' style='color: var(--danger);'>" . print_r(sqlsrv_errors(), true) . "</pre>
                  </div>";
    }
}

// 2. Traer los datos actuales para rellenar el formulario
$sql = "SELECT Nombre, Descripcion, Precio_Unitario FROM PRODUCTO WHERE Id_Producto = ?";
$params = array($id_producto);
$consulta = sqlsrv_query($conn, $sql, $params);

if ($consulta === false || !sqlsrv_has_rows($consulta)) {
    die("<div class='container mt-4'><div class='alert alert-danger'>Producto no encontrado.</div></div>");
}

$producto = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 0; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.1); }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--warning), #d97706); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(245, 158, 11, 0.4); }
        .card-title-dark { color: var(--text-main); font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; }
        .form-label { font-weight: 500; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.4rem; }
        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-control:focus { background-color: var(--bg-dark); color: var(--text-main); border-color: var(--warning); box-shadow: none; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: var(--warning); box-shadow: 0 0 10px rgba(245, 158, 11, 0.2); }
        .btn-glow { background: linear-gradient(135deg, var(--warning), #d97706); border: none; font-weight: bold; letter-spacing: 1px; padding: 12px; border-radius: 8px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); transition: all 0.3s ease; color: white; }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245, 158, 11, 0.6); color: white; }
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: rgba(59, 130, 246, 0.05); }
        .alert-custom-error { background-color: #ef44441a; border: 1px solid #ef444466; color: var(--danger); }
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
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <h4 class="card-title-dark">EDITAR PRODUCTO #<?php echo $id_producto; ?></h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label">Nombre del Material</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-pen"></i></span>
                                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($producto['Nombre']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Descripción</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-file-lines"></i></span>
                                    <textarea name="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($producto['Descripcion']); ?></textarea>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label">Precio Unitario ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>
                                    <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo number_format($producto['Precio_Unitario'], 2, '.', ''); ?>" required>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR CAMBIOS
                                </button>
                                
                                <hr style="margin: 10px 0;">
                                
                                <div class="text-center">
                                    <a href="ver_productos.php" class="btn btn-outline-custom w-100 py-2">
                                        <i class="fa-solid fa-arrow-left me-2"></i>Cancelar y Volver
                                    </a>
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