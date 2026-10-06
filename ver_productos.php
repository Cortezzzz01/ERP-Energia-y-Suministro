<?php
session_start();
include 'conexion.php';

$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243; 

$sql = "SELECT 
            p.Id_Producto, 
            p.Nombre, 
            p.Descripcion, 
            p.Precio_Unitario,
            COALESCE( (SELECT SUM(cp.Cantidad) 
                       FROM COMPRA_PRODUCTO cp 
                       INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra 
                       WHERE cp.Id_Producto = p.Id_Producto AND (c.Estado != 'Anulado' OR c.Estado IS NULL)), 0) 
            - 
            COALESCE( (SELECT SUM(fp.Cantidad) 
                       FROM FACTURA_PRODUCTO fp 
                       INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura 
                       WHERE fp.Id_Producto = p.Id_Producto AND (f.Estado != 'Anulado' OR f.Estado IS NULL)), 0) 
            AS Stock_Actual
        FROM PRODUCTO p";

$consulta = sqlsrv_query($conn, $sql);

if ($consulta === false) {
    echo "<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>";
    echo "<strong>⚠️ Error en la consulta de Inventario:</strong><br>";
    die("<pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}

$mensaje = $_GET['mensaje'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); overflow: hidden; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .card-title-dark { color: var(--text-main); font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: #3b82f61a; }
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary-custom:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: var(--text-main); }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: var(--text-muted); border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
        .table-dark-custom tbody tr:hover { background-color: #3b82f60d; }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: var(--text-main); }

        .id-badge { background-color: var(--bg-dark); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; color: var(--text-muted); }
        .product-name { color: var(--text-main) !important; font-weight: 600; font-size: 1.05rem; }
        .product-desc { color: var(--text-muted) !important; }
        .price-tag { color: var(--success) !important; font-weight: bold; font-size: 1.15rem; letter-spacing: 0.5px; }
        
        .stock-tag { font-weight: bold; font-size: 1.2rem; background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 4px 12px; border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.3); }
        .stock-zero { background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.3); }

        .btn-action-edit { background: #3b82f61a; border: 1px solid #3b82f64d; color: var(--primary); padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-action-edit:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        .btn-action-delete { background: #ef44441a; border: 1px solid #ef44444d; color: var(--danger); padding: 6px 10px; border-radius: 6px; transition: all 0.3s; text-decoration: none; display: inline-block; cursor: pointer; border: none; }
        .btn-action-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }

        .alert-custom-info { background-color: #3b82f61a; border: 1px solid #3b82f64d; color: var(--primary); margin: 20px; border-radius: 10px; padding: 15px; }
        .alert-custom-success { background-color: #10b9811a; border: 1px solid #10b98166; color: var(--success); padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-custom-error { background-color: #ef44441a; border: 1px solid #ef444466; color: var(--danger); padding: 15px; border-radius: 10px; margin-bottom: 20px; }

        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-control:focus { border-color: var(--primary); box-shadow: none; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control { border-color: var(--primary); box-shadow: 0 0 10px #3b82f633; }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.5; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        
        <?php if($mensaje == 'editado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-check-circle me-2'></i>Producto actualizado correctamente.</div>
        <?php elseif($mensaje == 'eliminado'): ?>
            <div class='alert alert-custom-success shadow-sm mt-3'><i class='fa-solid fa-trash-can me-2'></i>Producto eliminado del inventario.</div>
        <?php elseif($mensaje == 'error'): ?>
            <div class='alert alert-custom-error shadow-sm mt-3'><i class='fa-solid fa-triangle-exclamation me-2'></i>Error al procesar la solicitud.</div>
        <?php endif; ?>

        <div class="dark-card mt-3">
            
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <h4 class="card-title-dark">Inventario de Materiales</h4>
                </div>
                <div>
                    <a href="productos.php" class="btn-primary-custom me-2">
                        <i class="fa-solid fa-plus me-1"></i>Nuevo
                    </a>
                    <a href="index.php" target="_parent" class="btn-outline-custom">
                        <i class="fa-solid fa-house me-1"></i>Menú
                    </a>
                </div>
            </div>

            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="buscador" class="form-control" placeholder="Buscar por ID, nombre o descripción..." onkeyup="filtrarTabla()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="miTabla">
                    <thead>
                        <tr>
                            <th width="8%">ID</th>
                            <th width="20%">Nombre del Producto</th>
                            <th width="30%">Descripción</th>
                            <th width="15%" class="text-center">Stock Actual</th> 
                            <th width="15%" class="text-end">Precio Unitario</th>
                            <th width="12%" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                            $stock = $fila['Stock_Actual'];
                            $clase_stock = ($stock <= 0) ? 'stock-zero' : '';
                            $precio_nio = $fila['Precio_Unitario'] * $tasa_hoy;
                        ?>
                        <tr>
                            <td><span class="id-badge">#<?php echo $fila['Id_Producto']; ?></span></td>
                            <td class="product-name"><?php echo htmlspecialchars($fila['Nombre']); ?></td>
                            <td class="product-desc small"><?php echo htmlspecialchars($fila['Descripcion']); ?></td>
                            <td class="text-center">
                                <span class="stock-tag <?php echo $clase_stock; ?>">
                                    <?php echo $stock; ?>
                                </span>
                            </td>
                            <td class="text-end price-tag">C$ <?php echo number_format($precio_nio, 2); ?></td>
                            <td class="text-center">
                                <a href="editar_producto.php?id=<?php echo $fila['Id_Producto']; ?>" class="btn-action-edit" title="Editar Producto">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button onclick="confirmarEliminacion(<?php echo $fila['Id_Producto']; ?>, '<?php echo addslashes(htmlspecialchars($fila['Nombre'])); ?>')" class="btn-action-delete" title="Eliminar Producto">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if(sqlsrv_has_rows($consulta) === false): ?>
                <div class="alert-custom-info text-center">
                    <i class="fa-solid fa-circle-info me-2"></i>No hay productos registrados en el inventario actualmente.
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
            if (confirm("¿Estás seguro de que deseas eliminar el producto: " + nombre + "?\n\nEsta acción no se puede deshacer y fallará si el producto ya fue comprado o facturado.")) {
                window.location.href = "eliminar_producto.php?id=" + id;
            }
        }
    </script>

</body>
</html>