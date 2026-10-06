<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';

$id = $_GET['id'] ?? '';
$tipo = $_GET['tipo'] ?? ''; // 'compra' o 'factura'
$motivo = $_GET['motivo'] ?? 'Sin motivo especificado';

if (empty($id) || empty($tipo)) {
    die("Faltan parámetros.");
}

// Iniciamos una transacción para que si algo falla, no se arruine el inventario
if (sqlsrv_begin_transaction($conn) === false) { die(print_r(sqlsrv_errors(), true)); }

try {
    if ($tipo === 'compra') {
        // 1. Obtener productos de esta compra
        $sql_items = "SELECT Id_Producto, Cantidad FROM COMPRA_PRODUCTO WHERE Id_Compra = ?";
        $params_items = array($id);
        $res_items = sqlsrv_query($conn, $sql_items, $params_items);

        while ($item = sqlsrv_fetch_array($res_items, SQLSRV_FETCH_ASSOC)) {
            // REVERSIÓN DE COMPRA: Restamos del stock
            $sql_stock = "UPDATE PRODUCTO SET Stock = Stock - ? WHERE Id_Producto = ?";
            sqlsrv_query($conn, $sql_stock, array($item['Cantidad'], $item['Id_Producto']));
        }

        // 2. Marcar compra como anulada
        $sql_upd = "UPDATE COMPRA SET Estado = 'Anulado', Motivo_Anulacion = ? WHERE Id_Compra = ?";
        sqlsrv_query($conn, $sql_upd, array($motivo, $id));
        $redirect = "ver_compras.php";

    } else if ($tipo === 'factura') {
        // 1. Obtener productos de esta factura
        $sql_items = "SELECT Id_Producto, Cantidad FROM FACTURA_PRODUCTO WHERE Id_Factura = ?";
        $params_items = array($id);
        $res_items = sqlsrv_query($conn, $sql_items, $params_items);

        while ($item = sqlsrv_fetch_array($res_items, SQLSRV_FETCH_ASSOC)) {
            // REVERSIÓN DE FACTURA: Sumamos de vuelta al stock
            $sql_stock = "UPDATE PRODUCTO SET Stock = Stock + ? WHERE Id_Producto = ?";
            sqlsrv_query($conn, $sql_stock, array($item['Cantidad'], $item['Id_Producto']));
        }

        // 2. Marcar factura como anulada
        $sql_upd = "UPDATE FACTURA SET Estado = 'Anulado', Motivo_Anulacion = ? WHERE Id_Factura = ?";
        sqlsrv_query($conn, $sql_upd, array($motivo, $id));
        $redirect = "ver_facturas.php";
    }

    // --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
    $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
    $accion_log = strtoupper("ANULAR " . $tipo);
    $detalle_log = "Se anuló la $tipo #$id y se revirtió el inventario. Motivo: $motivo";
    sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));
    // -------------------------------------------

    sqlsrv_commit($conn);
    header("Location: $redirect");

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    die("Error al anular: " . $e->getMessage());
}
?>