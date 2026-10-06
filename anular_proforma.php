<?php
session_start(); // (Ya lo tenías, excelente)
include 'conexion.php';

$id_proforma = $_GET['id'] ?? '';

if (empty($id_proforma)) {
    die("Error: No se especificó el ID de la proforma.");
}

// Cambiamos el estado a Anulado y le ponemos un motivo estándar
$sql = "UPDATE PROFORMA 
        SET Estado = 'Anulado', Motivo_Anulacion = 'Anulada manualmente desde el historial' 
        WHERE Id_Proforma = ?";

$stmt = sqlsrv_query($conn, $sql, array($id_proforma));

if ($stmt === false) {
    die("Error al anular la proforma: " . print_r(sqlsrv_errors(), true));
}

// --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
$usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
$detalle_log = "Se anuló manualmente la proforma #$id_proforma desde el historial.";
sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'ANULAR PROFORMA', ?)", array($usuario_actual, $detalle_log));
// -------------------------------------------

// Redireccionamos de vuelta al historial
header("Location: ver_proformas.php");
exit();
?>