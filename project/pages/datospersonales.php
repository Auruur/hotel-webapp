<?php
require_once 'scripts/utils.php';

if (isset($_SESSION['role'])) {
    $user_data = get_user_data($_SESSION['email']);
} else {
    page_redirect('home');
}

unset($_SESSION['log_page']);

$r = [];
$formValid = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $r = checkParameters($_POST, true, $_SESSION['email'], $user_data['dni']);

    if (!empty($r) && empty($r['error'])) {
        $formValid = true;
    } 

    if ($formValid && isset($_POST['submit']) && $_POST['submit'] == 'Confirmar') {
        $db = connectDatabase();

        // si el usuario es recepcionista o administrador puede modificar todos los campos
        if ($_SESSION['role'] == 'Recepcionista' || $_SESSION['role'] == 'Administrador') {
            $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, dni = ?, email = ?, clave = ?, tarjeta = ? WHERE email = ?");
            $stmt->bind_param("sssssss", $r['nombre'], $r['apellidos'], $r['dni'], $r['email'], password_hash($r['clave'], PASSWORD_BCRYPT), $r['tarjeta'], $_SESSION['email']);
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET email = ?, clave = ?, tarjeta = ? WHERE email = ?");
            $stmt->bind_param("ssss", $r['email'], password_hash($r['clave'], PASSWORD_BCRYPT), $r['tarjeta'], $_SESSION['email']);
        }

        if ($stmt->execute()) {
            $_SESSION['email'] = $r['email']; 
            $_SESSION['success'] = 'Actualización completada con éxito';
            log_event("El usuario {$_SESSION['email']} ha cambiado sus datos personales");
            page_redirect('datospersonales');
        } else {
            $_SESSION['error'] = '<p class="error">Se produjo un error al actualizar el usuario</p>';
            error_log('Error al ejecutar la consulta: ' . $stmt->error);
            error_log('Datos: ' . print_r($r, true));
        }
        $stmt->close();
        $db->close();
    }
}

// Guardar los valores del formulario si existen 
$valNombre = isset($r['nombre']) ? 'value="' .$r['nombre'].'"' : 'value="' .$user_data['nombre'].'"';
$valApellidos = isset($r['apellidos']) ? 'value="' .$r['apellidos'].'"' : 'value="' .$user_data['apellidos'].'"';
$valDNI = isset($r['dni']) ? 'value="' .$r['dni'].'"' : 'value="' .$user_data['dni'].'"';
$valEmail = isset($r['email']) ? 'value="' .$r['email'].'"' : 'value="' .$user_data['email'].'"';
$valClave = isset($r['clave']) ? 'value="' .$r['clave'].'"' : '';
$valTarjeta = isset($r['tarjeta']) ? 'value="' .$r['tarjeta'].'"' : 'value="' .$user_data['tarjeta'].'"';
// Guardar los errores del formulario si existen 
$errorMessages = '';
if (isset($r['error']) && !empty($r['error'])) {
    foreach ($r['error'] as $error) {
        $errorMessages .= '<p class="error">' . $error . '</p>';
    }
}
// Guardar los cambios para la pantalla de confirma de los datos
$submitButton = $formValid ? 'Confirmar' : 'Actualizar';
$readOnlyAttr = $formValid ? 'readonly' : '';
$editAttr = ($_SESSION['role'] == 'Recepcionista' || $_SESSION['role'] == 'Administrador') ? '' : 'readonly';
?> 

<div class="profile-box">
<div class="profile-img-container">
    <img src="img_src/<?php echo get_profile_image($_SESSION['role']); ?>" alt="Profile Image" class="profile-img">
</div>
<h2>Datos Personales</h2>
<?php if (!empty($errorMessages)): ?>
    <div class="error-box">
        <?php echo $errorMessages; ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
    <div class="success-box">
        <p class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
    </div>
<?php endif; ?>
<form action="index.php?page=datospersonales" method="post">
    <div class="form-row">
        <div class="form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $user_data['nombre']; ?>" <?php echo $editAttr ?>>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" <?php echo $valEmail ?> <?php echo $readOnlyAttr ?>>
        </div>
    </div>
    <div class="form-row">
    <div class="form-group">
            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" value="<?php echo $user_data['apellidos']; ?>" <?php echo $editAttr ?>>
        </div>
        <div class="form-group">
            <label for="clave">Contraseña:</label>
            <input type="password" id="clave" name="clave" <?php echo $valClave ?> <?php echo $readOnlyAttr ?>>
        </div>
    </div>
    <div class="form-row">
    <div class="form-group">
            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" value="<?php echo $user_data['dni']; ?>" <?php echo $editAttr ?>>
        </div>
        <div class="form-group">
            <label for="tarjeta">Número de Tarjeta de Crédito:</label>
            <input type="text" id="tarjeta" name="tarjeta" <?php echo $valTarjeta ?> <?php echo $readOnlyAttr ?> >
        </div>
    </div>
    <div class="form-group form-submit">
        <input type="submit" name="submit" value="<?php echo $submitButton?>">
    </div>
</form>
</div>