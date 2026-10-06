<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';

$id = $_GET['id'] ?? '';
$tipo = $_GET['tipo'] ?? ''; // 'factura' o 'compra'
$usuario_actual = $_SESSION['nombre'] ?? 'Administrador'; // Para la bitácora

if (empty($id) || empty($tipo)) {
    die("Error: Faltan parámetros para procesar el pago.");
}

if ($tipo === 'factura') {
    // Cobramos la factura al cliente
    $sql = "UPDATE FACTURA SET Estado_Pago = 'Pagado' WHERE Id_Factura = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    
    if ($stmt) {
        // 🕵️‍♂️ BITÁCORA
        sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'COBRAR FACTURA', ?)", array($usuario_actual, "Se registró el pago en su totalidad de la factura de venta #$id."));
        
        header("Location: ver_facturas.php?mensaje=cobrado");
        exit();
    } else {
        header("Location: ver_facturas.php?mensaje=error_cobro");
        exit();
    }
} 
elseif ($tipo === 'compra') {
    // Le pagamos la compra al proveedor
    $sql = "UPDATE COMPRA SET Estado_Pago = 'Pagado' WHERE Id_Compra = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    
    if ($stmt) {
        // 🕵️‍♂️ BITÁCORA
        sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'PAGAR COMPRA', ?)", array($usuario_actual, "Se liquidó la deuda con el proveedor por la compra #$id."));
        
        header("Location: ver_compras.php?mensaje=pagado");
        exit();
    } else {
        header("Location: ver_compras.php?mensaje=error_pago");
        exit();
    }
} 
else {
    die("Tipo de operación no válida.");
}
?>