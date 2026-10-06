<?php
include 'conexion.php';

$id = $_GET['id'] ?? '';

if (!empty($id)) {
    // Ejecutamos el DELETE
    $sql = "DELETE FROM ENCARGADO WHERE Id_Encargado = ?";
    $params = array($id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Éxito: volvemos a la tabla con el mensaje verde
        header("Location: ver_encargados.php?mensaje=eliminado");
        exit();
    } else {
        // Error: Probablemente el encargado ya tiene facturas amarradas
        header("Location: ver_encargados.php?mensaje=error");
        exit();
    }
} else {
    // Si entran sin ID, los devolvemos a la tabla
    header("Location: ver_encargados.php");
    exit();
}
?>