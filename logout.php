<?php
session_start();
session_unset(); // Borra las variables
session_destroy(); // Destruye la sesión por completo
header("Location: login.php"); // Lo manda de regreso al inicio
exit();
?>