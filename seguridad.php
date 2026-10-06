<?php
session_start();

// Verificamos si no existe la llave en la sesión
if (!isset($_SESSION['usuario_id'])) {
    // Si no está logueado, lo expulsamos al login
    header("Location: login.php");
    exit();
}
?>