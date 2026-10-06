<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_encargado = $_POST['id_encargado'] ?? '';
    $datos_cliente = $_POST['datos_cliente'] ?? '';
    $productos = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];

    if (empty($id_encargado) || empty($datos_cliente) || empty($productos)) {
        die("Error: Datos incompletos del formulario.");
    }

    // Iniciar Transacción en SQL Server para asegurar integridad
    sqlsrv_begin_transaction($conn);

    // 1. Insertar la Cabecera de la Proforma con monto inicial en 0
    $sql_cabecera = "INSERT INTO PROFORMA (Id_Encargado, Datos, Estado, Total) OUTPUT INSERTED.Id_Proforma VALUES (?, ?, 'Vigente', 0.00)";
    $stmt_cabecera = sqlsrv_query($conn, $sql_cabecera, array($id_encargado, $datos_cliente));
    
    if ($stmt_cabecera === false) {
        sqlsrv_rollback($conn);
        die("Error al guardar la cabecera: " . print_r(sqlsrv_errors(), true));
    }

    $row_proforma = sqlsrv_fetch_array($stmt_cabecera, SQLSRV_FETCH_ASSOC);
    $id_proforma = $row_proforma['Id_Proforma'];

    $subtotal_general = 0;

    // 2. Insertar el Detalle de Productos y acumular la matemática
    for ($i = 0; $i < count($productos); $i++) {
        $id_prod = $productos[$i];
        $cantidad = intval($cantidades[$i]);

        if (!empty($id_prod) && $cantidad > 0) {
            // Buscamos el precio actual del producto para el cálculo del total de la proforma
            $sql_p = "SELECT Precio_Unitario FROM PRODUCTO WHERE Id_Producto = ?";
            $res_p = sqlsrv_query($conn, $sql_p, array($id_prod));
            $prod = sqlsrv_fetch_array($res_p, SQLSRV_FETCH_ASSOC);
            
            $subtotal_general += ($prod['Precio_Unitario'] * $cantidad);

            // Insertar en la tabla puente
            $sql_detalle = "INSERT INTO PROFORMA_PRODUCTO (Id_Proforma, Id_Producto, Cantidad) VALUES (?, ?, ?)";
            $stmt_detalle = sqlsrv_query($conn, $sql_detalle, array($id_proforma, $id_prod, $cantidad));

            if ($stmt_detalle === false) {
                sqlsrv_rollback($conn);
                die("Error al guardar el detalle de materiales: " . print_r(sqlsrv_errors(), true));
            }
        }
    }

    // 3. Calcular Total con el IVA (15%) y actualizar la cabecera
    $total_con_iva = $subtotal_general * 1.15;
    $sql_update = "UPDATE PROFORMA SET Total = ? WHERE Id_Proforma = ?";
    $stmt_update = sqlsrv_query($conn, $sql_update, array($total_con_iva, $id_proforma));

    if ($stmt_update === false) {
        sqlsrv_rollback($conn);
        die("Error al actualizar el monto total: " . print_r(sqlsrv_errors(), true));
    }

    // --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
    $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
    $accion_log = 'CREAR PROFORMA';
    $detalle_log = "Se generó la cotización #" . $id_proforma . " para el proyecto/cliente: " . $datos_cliente;
    
    $sql_bitacora = "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)";
    $stmt_bitacora = sqlsrv_query($conn, $sql_bitacora, array($usuario_actual, $accion_log, $detalle_log));
    
    if ($stmt_bitacora === false) {
        sqlsrv_rollback($conn);
        die("Error al registrar en bitácora: " . print_r(sqlsrv_errors(), true));
    }
    // -------------------------------------------

    // Si todo salió bien, guardamos definitivamente en el servidor
    sqlsrv_commit($conn);

    // Redireccionar al historial de proformas
    header("Location: ver_proformas.php");
    exit();
}
?>