<?php
include 'conexion.php';

$id = $_GET['id'] ?? '';

if (!empty($id)) {
    // Intentamos eliminar el producto
    $sql = "DELETE FROM PRODUCTO WHERE Id_Producto = ?";
    $params = array($id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Se eliminó correctamente
        header("Location: ver_productos.php?mensaje=eliminado");
        exit();
    } else {
        // Falló (Probablemente porque el producto ya está siendo usado en un Ticket o Factura)
        header("Location: ver_productos.php?mensaje=error");
        exit();
    }
} else {
    // Si no enviaron ID, regresamos a la tabla
    header("Location: ver_productos.php");
    exit();
}
?>