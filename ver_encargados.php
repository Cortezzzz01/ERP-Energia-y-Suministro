<?php
include 'conexion.php';

// Consulta actualizada con los nuevos campos
$sql = "SELECT Id_Encargado, Nombre, Cargo, Telefono, Cedula FROM ENCARGADO ORDER BY Nombre ASC";
$consulta = sqlsrv_query($conn, $sql);

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta del Personal:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}

$mensaje = $_GET['mensaje'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Personal - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { 
            background-color: var(--bg-dark); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: var(--text-main);
            padding: 2rem 1rem;
        }

        .dark-card { 
            background-color: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); 
            overflow: hidden; 
        }

        .card-header-dark {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-wrapper {
            display: flex;
            align-items: center;
        }

        .icon-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 1.5rem;
            margin-right: 15px;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .card-title-dark {
            color: #f8fafc;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
            font-size: 1.25rem;
            text-transform: uppercase;
        }

        .btn-outline-custom { 
            border: 1px solid var(--border-color); 
            color: var(--text-muted); 
            transition: all 0.3s; 
            background: transparent; 
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-custom:hover { 
            border-color: var(--primary); 
            color: var(--primary); 
            background: #3b82f61a; 
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        /* --- TABLA ESTANDARIZADA --- */
        .table-dark-custom {
            width: 100%;
            margin-bottom: 0;
            color: #e2e8f0; 
        }
        
        .table-dark-custom thead th {
            background-color: var(--bg-dark); 
            color: #94a3b8; 
            border-bottom: 2px solid var(--primary); 
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 15px;
        }

        .table-dark-custom tbody tr {
            transition: background-color 0.2s;
            border-bottom: 1px solid var(--border-color);
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05); 
        }

        .table-dark-custom tbody td {
            padding: 15px;
            vertical-align: middle;
            background-color: transparent;
            border: none;
            color: #f8fafc !important; 
        }

        .id-badge {
            background-color: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #94a3b8; 
            font-family: monospace;
        }

        .staff-name {
            color: #f8fafc !important;
            font-weight: 600;
            font-size: 1.05rem;
        }
        
        .cargo-badge {
            background-color: rgba(59, 130, 246, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .contact-text {
            font-size: 0.95rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            font-family: monospace;
        }

        .contact-text i {
            color: var(--primary);
            opacity: 0.8;
            width: 18px;
            margin-right: 6px;
        }

        /* Botones de Acción CRUD */
        .btn-action-edit {
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: var(--primary);
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action-edit:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
        }

        .btn-action-delete {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }

        .btn-action-delete:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
        }

        /* Alertas Semánticas */
        .alert-custom-info { background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: var(--primary); margin: 20px; border-radius: 10px; padding: 15px; }
        .alert-custom-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-custom-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 15px; border-radius: 10px; margin-bottom: 20px; }

        /* Buscador */
        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: none; background-color: var(--bg-dark); color: #f8fafc;}
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: var(--primary); box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.5; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        
        <?php if($mensaje == 'editado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-check-circle me-2'></i>Personal actualizado correctamente.</div>
        <?php elseif($mensaje == 'eliminado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-trash-can me-2'></i>Personal eliminado del sistema.</div>
        <?php elseif($mensaje == 'error'): ?>
            <div class='alert alert-custom-error shadow-sm mt-3'><i class='fa-solid fa-triangle-exclamation me-2'></i>Error al procesar la solicitud (el empleado puede tener proformas/facturas asociadas).</div>
        <?php endif; ?>

        <div class="dark-card mt-3">
            
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box">
                        <i class="fa-solid fa-helmet-safety"></i>
                    </div>
                    <h4 class="card-title-dark">Plantilla de Personal</h4>
                </div>
                <div>
                    <a href="encargados.php" class="btn-primary-custom me-2">
                        <i class="fa-solid fa-user-plus me-1"></i>Nuevo
                    </a>
                    <a href="index.php" target="_parent" class="btn-outline-custom">
                        <i class="fa-solid fa-house me-1"></i>Menú
                    </a>
                </div>
            </div>

            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="buscador" class="form-control" placeholder="Buscar por ID, nombre, cargo, cédula o teléfono..." onkeyup="filtrarTabla()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="8%" class="text-center">ID</th>
                            <th width="25%">Nombre Completo</th>
                            <th width="18%">Cargo / Especialidad</th>
                            <th width="18%">Cédula de Identidad</th>
                            <th width="16%">Teléfono</th>
                            <th width="15%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td class="text-center"><span class="id-badge">#<?php echo $fila['Id_Encargado']; ?></span></td>
                            
                            <td class="staff-name">
                                <i class="fa-solid fa-user-tie text-primary opacity-50 me-2"></i><?php echo htmlspecialchars($fila['Nombre']); ?>
                            </td>
                            
                            <td>
                                <span class="cargo-badge">
                                    <?php echo htmlspecialchars($fila['Cargo']); ?>
                                </span>
                            </td>
                            
                            <td>
                                <?php if(!empty($fila['Cedula'])): ?>
                                    <span class="contact-text"><i class="fa-regular fa-id-badge"></i> <?php echo htmlspecialchars($fila['Cedula']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if(!empty($fila['Telefono'])): ?>
                                    <span class="contact-text"><i class="fa-solid fa-mobile-screen"></i> <?php echo htmlspecialchars($fila['Telefono']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">--</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <a href="editar_encargado.php?id=<?php echo $fila['Id_Encargado']; ?>" class="btn-action-edit" title="Editar Personal">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button onclick="confirmarEliminacion(<?php echo $fila['Id_Encargado']; ?>, '<?php echo addslashes(htmlspecialchars($fila['Nombre'])); ?>')" class="btn-action-delete" title="Eliminar Personal">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if(sqlsrv_has_rows($consulta) === false): ?>
                <div class="alert-custom-info text-center m-4">
                    <i class="fa-solid fa-users-slash fs-4 mb-2 d-block"></i>
                    No hay personal registrado en el sistema.
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function filtrarTabla() {
            let input = document.getElementById("buscador");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("miTabla");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { 
                let row = tr[i];
                let textContent = row.textContent || row.innerText;
                
                if (textContent.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = ""; 
                } else {
                    row.style.display = "none"; 
                }
            }
        }

        function confirmarEliminacion(id, nombre) {
            if (confirm("¿Estás seguro de que deseas eliminar a: " + nombre + "?\n\nEsta acción fallará si el empleado ya tiene trabajos asociados a su nombre.")) {
                window.location.href = "eliminar_encargado.php?id=" + id;
            }
        }
    </script>

</body>
</html>