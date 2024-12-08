<?php
require_once 'scripts/connect.php';
require_once 'scripts/utils.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Si el usuario inicia sesión en una página distinta a la página de inicio, no es redirigido a la página de inicio.
    $redirect_page = isset($_POST['page']) ? $_POST['page'] : 'home';

    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = check_login($email, $password);
        if ($role) {
            // Si el login va con exito guardamos los datos de login en variables de sesion
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['success_side'] = 'Inicio de sesión exitoso';
            log_event("Usuario $email inició sesión.");
        } else {
            $_SESSION['error_side'] = 'Correo electrónico o contraseña no válidos';
            log_event("Intento fallido de inicio de sesión para el usuario $email.");
        }
        page_redirect($redirect_page);
    } elseif (isset($_POST['logout'])) {
        // Si el usuario hace el logout eliminamos la sesión y sus variables
        $email = $_SESSION['email'];
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['success_side'] = 'Cierre de sesión exitoso';
        log_event("Usuario $email cerró sesión.");
        page_redirect($redirect_page);
    }
}

// Guardamos las variables del hotel para el menu lateral de derecha
$hotel_stats = get_hotel_stats();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Hotel Utopia</title>
    <meta charset="utf-8">
    <?php
        $page = isset($_GET['page']) ? $_GET['page'] : 'home';
        $allowed_pages = ['home', 'servicios', 'habitaciones', 'reservas', 'usuarios', 'registracion', 'datospersonales', 'logs', 'backup'];

        // Cada vez que hacemos una redirecion a una pagina de la aplicacion web se utiliza un fichero .css llamado "style" + nombre del fichero .php con la primera letra en mayúscula
        if (in_array($page, $allowed_pages)) {
            echo '<link rel="stylesheet" href="style/style' . ucfirst($page) . '.css">';
        } else {
            echo '<link rel="stylesheet" href="style/styleHome.css">';
        }
    ?>
</head>
<body>
    <div class="pagina">
        <header>
            <div class="logo-div">
                <section>
                    <h2>Hotel Utopia</h2>
                    <p>Montaña Sertoyova Chuka</p>
                </section>
                <aside>
                    <img id="hotel_logo" src="img_src/utopialogo.png" alt="Logo de Utopia">
                </aside>
            </div>
        </header>
        <nav>
            <ul>
                <!-- En el menu de navigacion algunas páginas solo son visibles por rol -->
                <li><a href="index.php?page=home">Home</a></li>
                <li><a href="index.php?page=servicios">Servicios</a></li>
                <li><a href="index.php?page=habitaciones">Habitaciones</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] != 'Administrador'): ?>
                    <li><a href="index.php?page=reservas">Reservas</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] != 'Cliente'): ?>
                    <li><a href="index.php?page=usuarios">Usuarios</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Administrador'): ?>
                    <li><a href="index.php?page=logs">Logs</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="main">
            <div class="login sidebar">
                <?php if (isset($_SESSION['email'])): ?>
                    <!-- Los mensajes de éxito/error se muestran aquí en caso de que el inicio de sesión sea exitoso o no -->
                    <?php if (isset($_SESSION['success_side'])): ?>
                        <p><?php echo $_SESSION['success_side']; unset($_SESSION['success_side']); ?></p>
                    <?php endif; ?>
                    <!-- Se muestra el cuadro con la imagen de perfil, a través del cual se puede acceder a la página de datos personales -->
                    <a href="index.php?page=datospersonales">
                        <div class="propic">
                            <img src="img_src/<?php echo get_profile_image($_SESSION['role']); ?>" alt="Profile Image" class="profile-img">
                        </div>
                    </a>
                    <p>Conectado como: <?php echo $_SESSION['email']; ?> (<?php echo $_SESSION['role']; ?>)</p>

                    <form action="index.php" method="post">
                        <input type="hidden" name="logout" value="1">
                        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : 'home'; ?>">
                        <input type="submit" value="logout">
                    </form>
                <?php else: ?>
                    <?php if (isset($_SESSION['error_side'])): ?>
                        <p><?php echo $_SESSION['error_side']; unset($_SESSION['error_side']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success_side'])): ?>
                        <p><?php echo $_SESSION['success_side']; unset($_SESSION['success_side']); ?></p>
                    <?php endif; ?>
                    <form action="index.php" method="post" novalidate>
                        <input type="email" name="email" placeholder="email" required>
                        <input type="password" name="password" placeholder="password" required>
                        <input type="hidden" name="login" value="1">
                        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : 'home'; ?>">
                        <input type="submit" value="login">
                    </form>
                    <a href="index.php?page=registracion">¿No tiene una cuenta? ¡Regístrate ahora!</a>
                <?php endif; ?>
            </div>
            <main>
                <!-- Dependiendo de la "page=" especificada en el URI, se incluye contenido diferente en el <main> -->
                <?php
                if (isset($_GET['page'])) {
                    $page = $_GET['page'];
                    switch ($page) {
                        case 'home':
                            include 'pages/home.php';
                            break;
                        case 'servicios':
                            include 'pages/servicios.php';
                            break;
                        case 'habitaciones':
                            include 'pages/habitaciones.php';
                            break;
                        case 'reservas':
                            include 'pages/reservas.php';
                            break;
                        case 'usuarios':
                            include 'pages/usuarios.php';
                            break;
                        case 'registracion':
                            include 'pages/registracion.php';
                            break;
                        case 'datospersonales':
                            include 'pages/datospersonales.php';
                            break;
                        case 'logs':
                            include 'pages/logs.php';
                            break;
                        case 'backup':
                            include 'pages/backup.php';
                            break;
                    }
                }
                ?>
            </main>
            <div class="description sidebar">
                <!-- Las estadísticas del hotel se muestran aquí. -->
                <ul>
                    <li>N° total de habitaciones del hotel: <span class="bold"><?php echo $hotel_stats['total_habitaciones']; ?></span></li>
                    <li>Capacidad total del hotel: <span class="bold"><?php echo $hotel_stats['total_capacidad']; ?></span></li>
                    <li>N° de huéspedes alojados en el hotel: <span class="bold"><?php echo $hotel_stats['total_personas']; ?></span></li>
                </ul> 
            </div>
        </div>
        <footer>
            <!-- en el pie de página puede acceder a la documentación y a la página de backup -->
            <section>
                <ul>
                    <li><h2>Hotel Utopia</h2></li>
                    <li>Giovanni Murgia</li>
                    <li><a href="documentacion.pdf">Documentación de la práctica</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Administrador'): ?>
                        <li><a href="index.php?page=backup">Restauración de la BBDD</a></li>
                    <?php endif; ?>
                </ul>
            </section>
        </footer>
    </div>
</body>
</html>
