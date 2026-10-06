<?php
session_start(); // <-- SESIÓN INICIADA PARA LA BITÁCORA
include 'conexion.php';

$id = $_GET['id'] ?? '';

if (!empty($id)) {
    // 1. OBTENEMOS EL NOMBRE DEL CLIENTE ANTES DE BORRARLO (Para la bitácora)
    $sql_nombre = "SELECT Nombre FROM CLIENTE WHERE Id_Cliente = ?";
    $res_nombre = sqlsrv_query($conn, $sql_nombre, array($id));
    $nombre_cliente = "Cliente #$id"; // Valor por defecto
    if ($res_nombre && sqlsrv_has_rows($res_nombre)) {
        $row = sqlsrv_fetch_array($res_nombre, SQLSRV_FETCH_ASSOC);
        $nombre_cliente = $row['Nombre'];
    }

    // Ejecutamos el DELETE
    $sql = "DELETE FROM CLIENTE WHERE Id_Cliente = ?";
    $params = array($id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
        $usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
        $accion_log = 'ELIMINAR CLIENTE';
        $detalle_log = "Se eliminó definitivamente de la base de datos al cliente: " . $nombre_cliente;
        sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));
        // -------------------------------------------
        
        // Éxito: volvemos a la tabla con el mensaje verde
        header("Location: ver_clientes.php?mensaje=eliminado");
        exit();
    } else {
        // Error: Probablemente el cliente ya tiene facturas amarradas y SQL Server bloquea el borrado por seguridad
        header("Location: ver_clientes.php?mensaje=error");
        exit();
    }
} else {
    // Si alguien entra sin ID, lo devolvemos a la tabla
    header("Location: ver_clientes.php");
    exit();
}
?>