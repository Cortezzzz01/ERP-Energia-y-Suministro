<?php
session_start();
include 'conexion.php';

// Si el usuario ya inició sesión, lo pateamos directo al index para que no vea el login de nuevo
if(isset($_SESSION['usuario_id'])){
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($usuario) && !empty($password)) {
        $sql = "SELECT Id_Usuario, Nombre_Completo, Rol FROM USUARIO WHERE Correo = ? AND Password_Hash = ? AND Estado = 1";
        $params = array($usuario, $password);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            $_SESSION['usuario_id'] = $row['Id_Usuario'];
            $_SESSION['nombre'] = $row['Nombre_Completo'];
            $_SESSION['rol'] = $row['Rol'];

            header("Location: index.php");
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos, o cuenta inactiva.";
        }
    } else {
        $error = "Por favor, ingresa tus credenciales.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Energía y Suministros S.A.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }

        body {
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-card) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow: hidden;
            position: relative;
        }

        body::before, body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            animation: float 10s infinite alternate ease-in-out;
        }
        body::before { background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%); top: -100px; left: -100px; }
        body::after { background: radial-gradient(circle, rgba(2, 132, 199, 0.1) 0%, transparent 70%); bottom: -150px; right: -100px; animation-delay: -5s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        .login-wrapper { position: relative; z-index: 1; width: 100%; max-width: 500px; padding: 20px; }
        
        .login-card {
            background: rgba(30, 41, 59, 0.75); /* Mantenemos la opacidad para el glassmorphism */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 50px 45px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .header-box { text-align: center; margin-bottom: 35px; }
        .logo-login {
            max-width: 140px; 
            margin-bottom: 15px;
            filter: drop-shadow(0px 4px 8px rgba(0,0,0,0.5)); 
            transition: transform 0.3s ease;
        }
        .logo-login:hover { transform: scale(1.05); }
        .company-name { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; font-weight: 600; }
        .title { font-size: 1.6rem; font-weight: 700; color: var(--text-main); }

        /* Alerta adaptada a las variables globales semánticas */
        .alert {
            background-color: #ef44441a; 
            border: 1px solid #ef444466; 
            color: var(--danger);
            padding: 12px 15px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 25px;
            display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease forwards;
        }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .input-group { position: relative; margin-bottom: 22px; }
        .input-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: color 0.3s ease; }
        .form-control {
            width: 100%; background: var(--bg-dark); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 15px 15px 15px 45px; color: var(--text-main); font-size: 1rem; transition: all 0.3s ease; outline: none;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); background: var(--bg-card); }
        .form-control:focus + .input-icon, .input-group:focus-within .input-icon { color: var(--primary); }
        
        .btn-toggle { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 5px; transition: color 0.3s ease; }
        .btn-toggle:hover { color: var(--text-main); }

        .options-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; font-size: 0.9rem; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; color: var(--text-muted); cursor: pointer; user-select: none; }
        .checkbox-label input[type="checkbox"] {
            appearance: none; width: 18px; height: 18px; border: 1px solid var(--border-color); border-radius: 4px; background: transparent; cursor: pointer; position: relative; transition: all 0.2s ease;
        }
        .checkbox-label input[type="checkbox"]:checked { background: var(--primary); border-color: var(--primary); }
        .checkbox-label input[type="checkbox"]:checked::after { content: '✔'; position: absolute; color: white; font-size: 12px; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        
        .forgot-link { color: var(--primary); text-decoration: none; transition: color 0.3s ease; }
        .forgot-link:hover { color: var(--text-main); text-decoration: underline; }

        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none;
            padding: 15px; border-radius: 10px; font-weight: 600; font-size: 1.05rem; letter-spacing: 1px; cursor: pointer;
            transition: all 0.3s ease; position: relative; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(14, 165, 233, 0.4); filter: brightness(1.1); }
        .btn-text { transition: opacity 0.2s ease; }
        .spinner { display: none; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; position: absolute; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-submit.loading .btn-text { opacity: 0; }
        .btn-submit.loading .spinner { display: block; }

        .divider { display: flex; align-items: center; text-align: center; margin: 30px 0; color: var(--text-muted); font-size: 0.85rem; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border-color); }
        .divider span { padding: 0 10px; }

        .footer-contact { text-align: center; font-size: 0.95rem; color: var(--text-muted); line-height: 1.8; background: var(--bg-dark); padding: 15px; border-radius: 10px; border: 1px solid var(--border-color); }
        .footer-contact strong { color: var(--text-main); font-weight: 600; }
        .footer-contact a { color: var(--primary); text-decoration: none; transition: color 0.3s ease;}
        .footer-contact a:hover { color: var(--text-main); text-decoration: underline; }

        @media (max-width: 550px) { .login-card { padding: 35px 25px; } .title { font-size: 1.4rem; } .options-row { flex-direction: column; align-items: flex-start; gap: 15px; } }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="header-box">
                <img src="energia.png" alt="Logo" class="logo-login">
                <div class="company-name">Energía y Suministros S.A.</div>
                <h1 class="title">Acceso al Sistema</h1>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert">
                    <i class="fa-solid fa-circle-exclamation"></i> 
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="">
                <div class="input-group">
                    <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Correo / Usuario" required autocomplete="off">
                    <i class="fa-solid fa-user input-icon"></i>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Contraseña" required>
                    <i class="fa-solid fa-lock input-icon"></i>
                    <button type="button" class="btn-toggle" id="btnTogglePass" tabindex="-1" title="Mostrar contraseña">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>

                <div class="options-row">
                    <label class="checkbox-label" title="Guarda tu usuario en este equipo">
                        <input type="checkbox" id="rememberMe"> Recuérdame
                    </label>
                    <a href="https://wa.me/50584306985?text=Hola,%20necesito%20restablecer%20mi%20contraseña%20en%20el%20sistema%20ERP" target="_blank" class="forgot-link">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <span class="btn-text">INGRESAR</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="divider">
                <span>¿Problemas de acceso?</span>
            </div>

            <div class="footer-contact">
                Contacta al Administrador:<br>
                <i class="fa-brands fa-whatsapp text-success me-1" style="color: var(--success) !important;"></i> <strong>+505 8430 6985</strong><br>
                <i class="fa-regular fa-envelope me-1"></i> <a href="mailto:engel.tellez@energiasuministros.com.ni?subject=Soporte%20ERP">Ing. Engel Telles</a>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('loginForm');
            const userInput = document.getElementById('usuario');
            const passInput = document.getElementById('password');
            const btnToggle = document.getElementById('btnTogglePass');
            const eyeIcon = document.getElementById('eyeIcon');
            const rememberChk = document.getElementById('rememberMe');
            const btnSubmit = document.getElementById('btnSubmit');

            btnToggle.addEventListener('click', () => {
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    passInput.type = 'password';
                    eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });

            const savedUser = localStorage.getItem('eys_saved_user');
            if (savedUser) {
                userInput.value = savedUser;
                rememberChk.checked = true;
                passInput.focus(); 
            } else {
                userInput.focus();
            }

            form.addEventListener('submit', () => {
                btnSubmit.classList.add('loading');
                btnSubmit.disabled = true; 
                
                if (rememberChk.checked) {
                    localStorage.setItem('eys_saved_user', userInput.value);
                } else {
                    localStorage.removeItem('eys_saved_user');
                }
            });
        });
    </script>
</body>
</html>