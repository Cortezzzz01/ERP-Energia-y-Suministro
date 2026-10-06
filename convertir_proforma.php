<?php
session_start(); // (Ya lo tenías, excelente)
include 'conexion.php';

// Ahora recibimos los parámetros de forma segura por POST desde el Modal
$id_proforma = $_POST['id_proforma'] ?? '';
$id_cliente = $_POST['id_cliente'] ?? '';

if (empty($id_proforma) || empty($id_cliente)) { 
    die("Error: Parámetros incompletos para procesar la facturación."); 
}

// Iniciar transacción en SQL Server
sqlsrv_begin_transaction($conn);

// 1. Verificar disponibilidad de la proforma
$sql_prof = "SELECT * FROM PROFORMA WHERE Id_Proforma = ? AND Estado = 'Vigente'";
$res_prof = sqlsrv_query($conn, $sql_prof, array($id_proforma));
$proforma = sqlsrv_fetch_array($res_prof, SQLSRV_FETCH_ASSOC);

if (!$proforma) {
    sqlsrv_rollback($conn);
    die("Error: La proforma no está disponible para ser facturada o ya fue procesada anteriormente.");
}

// 2. Insertar en la tabla FACTURA vinculando al Id_Cliente real
$sql_factura = "INSERT INTO FACTURA (Id_Encargado, Id_Cliente, Datos, Estado, Total) 
                OUTPUT INSERTED.Id_Factura 
                VALUES (?, ?, ?, 'Activo', ?)";
                
$params_factura = array(
    $proforma['Id_Encargado'], 
    $id_cliente, 
    $proforma['Datos'], // Mantenemos el texto descriptivo del proyecto
    $proforma['Total']
);

$stmt_factura = sqlsrv_query($conn, $sql_factura, $params_factura);

if ($stmt_factura === false) {
    sqlsrv_rollback($conn);
    die("Error al clonar cabecera hacia Factura: " . print_r(sqlsrv_errors(), true));
}

$row_factura = sqlsrv_fetch_array($stmt_factura, SQLSRV_FETCH_ASSOC);
$id_nueva_factura = $row_factura['Id_Factura'];

// 3. Clonar el listado completo de materiales
$sql_clonar_productos = "INSERT INTO FACTURA_PRODUCTO (Id_Factura, Id_Producto, Cantidad)
                         SELECT ?, Id_Producto, Cantidad 
                         FROM PROFORMA_PRODUCTO 
                         WHERE Id_Proforma = ?";
$stmt_productos = sqlsrv_query($conn, $sql_clonar_productos, array($id_nueva_factura, $id_proforma));

if ($stmt_productos === false) {
    sqlsrv_rollback($conn);
    die("Error al migrar los materiales de la proforma: " . print_r(sqlsrv_errors(), true));
}

// 4. Quemar las naves: Cambiar estado a 'Convertida' para que nadie la vuelva a facturar
$sql_update_prof = "UPDATE PROFORMA SET Estado = 'Convertida' WHERE Id_Proforma = ?";
$stmt_update = sqlsrv_query($conn, $sql_update_prof, array($id_proforma));

if ($stmt_update === false) {
    sqlsrv_rollback($conn);
    die("Error al actualizar el estado de la proforma: " . print_r(sqlsrv_errors(), true));
}

// --- 🕵️‍♂️ REGISTRO INVISIBLE EN BITÁCORA ---
$usuario_actual = $_SESSION['nombre'] ?? 'Administrador';
$detalle_log = "Se convirtió exitosamente la Proforma #$id_proforma en la Factura Definitiva #$id_nueva_factura vinculada al Cliente ID #$id_cliente.";
sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, 'CONVERTIR PROFORMA', ?)", array($usuario_actual, $detalle_log));
// -------------------------------------------

// ¡Todo un éxito! Guardamos definitivamente en la base de datos
sqlsrv_commit($conn);

// Redireccionamos directo a la vista del ticket de la nueva factura
header("Location: ver_ticket_factura.php?id=" . $id_nueva_factura);
exit();
?>