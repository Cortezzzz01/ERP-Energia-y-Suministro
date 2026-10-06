<?php
// Configuración del servidor SQL Server Local
$serverName = "CORTEZZZ\SQLEXPRESS"; 

// Datos de conexión local
$connectionInfo = array(
    "Database"               => "Energia_y_Suministro", // <--- ¡AQUÍ ESTABA EL DETALLE!
    "CharacterSet"           => "UTF-8",
    "Encrypt"                => true,
    "TrustServerCertificate" => true 
);

// Intentamos establecer la conexión
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) {
    echo "<div style='background:#fee2e2;color:#991b1b;padding:20px;border:1px solid #f87171;font-family:sans-serif;border-radius:8px;margin:20px;'>";
    echo "<h2 style='margin-top:0;'><i class='fa-solid fa-triangle-exclamation'></i> Error de Conexión Local</h2>";
    echo "<p>No se pudo conectar con el servidor <b>" . htmlspecialchars($serverName) . "</b>.</p>";
    echo "<hr style='border:0;border-top:1px solid #f87171;'>";
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
    echo "</pre>";
    echo "</div>";
}
?>