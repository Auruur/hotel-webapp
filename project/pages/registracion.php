<?php
require_once 'scripts/connect.php';
require_once 'scripts/utils.php';

if (isset($_SESSION['role'])) {
    page_redirect('home');
}

unset($_SESSION['log_page']);

$r = [];
$formValid = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $r = checkParameters($_POST);

    if (!empty($r) && empty($r['error'])) {
        $formValid = true;
    } 

    if ($formValid && isset($_POST['submit']) && $_POST['submit'] == 'Confirmar') {
        $db = connectDatabase();
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellidos, dni, email, clave, tarjeta, rol) VALUES (?, ?, ?, ?, ?, ?, 'Cliente')");
        $hashedClave = password_hash($r['clave'], PASSWORD_BCRYPT);
        $stmt->bind_param("ssssss", $r['nombre'], $r['apellidos'], $r['dni'], $r['email'], $hashedClave, $r['tarjeta']);
        if ($stmt->execute()) {
            $_SESSION['success_side'] = 'Registro completado con éxito.';
            unset($_SESSION['form_data']);
            page_redirect('habitaciones');
        } else {
            $_SESSION['error'] = 'Se produjo un error al registrar el usuario.';
            error_log('Error al ejecutar la consulta: ' . $stmt->error);
            error_log('Datos: ' . print_r($r, true));
        }
        $stmt->close();
        $db->close();
    }
}

// Guardar los valores del formulario si existen 
$valNombre = isset($r['nombre']) ? 'value="' .$r['nombre'].'"' : '';
$valApellidos = isset($r['apellidos']) ? 'value="' .$r['apellidos'].'"' : '';
$valDNI = isset($r['dni']) ? 'value="' .$r['dni'].'"' : '';
$valEmail = isset($r['email']) ? 'value="' .$r['email'].'"' : '';
$valClave = isset($r['clave']) ? 'value="' .$r['clave'].'"' : '';
$valTarjeta = isset($r['tarjeta']) ? 'value="' .$r['tarjeta'].'"' : '';
// Guardar los errores del formulario si existen 
$errorMessages = '';
if (isset($r['error']) && !empty($r['error'])) {
    foreach ($r['error'] as $error) {
        $errorMessages .= '<p class="error">' . $error . '</p>';
    }
}
// Guardar los cambios para la pantalla de confirma de los datos
$submitButton = $formValid ? 'Confirmar' : 'Registrar';
$readOnlyAttr = $formValid ? 'readonly' : '';

?>

<div class="form-container">
    <h2>Registro de usuario</h2>
    <?php if (!empty($errorMessages)): ?>
        <div class="error-box">
            <?php echo $errorMessages; ?>
        </div>
    <?php endif; ?>
    <form action="index.php?page=registracion" method="post" novalidate>
        <div class="column">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" <?php echo $valNombre?> <?php echo $readOnlyAttr?> required>

            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" <?php echo $valApellidos?> <?php echo $readOnlyAttr?> required>
            
            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" <?php echo $valDNI?> <?php echo $readOnlyAttr?> required>
        </div>
        <div class="column">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" <?php echo $valEmail?> <?php echo $readOnlyAttr?> required>
            
            <label for="clave">Clave:</label>
            <input type="password" id="clave" name="clave" <?php echo $valClave?> <?php echo $readOnlyAttr?> required>
            
            <label for="tarjeta">Número de tarjeta de crédito:</label>
            <input type="text" id="tarjeta" name="tarjeta" <?php echo $valTarjeta?> <?php echo $readOnlyAttr?> required>
        </div>
        <div class="submit-container">
            <input type="submit" name="submit" value="<?php echo $submitButton?>" >
        </div>
    </form>
</div>
