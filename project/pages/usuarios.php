<?php
require_once 'scripts/utils.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] == 'Cliente') {
    page_redirect('home');
}

unset($_SESSION['log_page']);

$role = $_SESSION['role'];
$users = get_users($role);

$r = [];
$formValid = false;
$editMode = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['submit'])) {
        $is_update = isset($_POST['is_update']) && $_POST['is_update'] === '1';
        if($is_update){
            $editMode = true;
        }
        $current_email = $is_update ? $_POST['current_email'] : '';
        $current_dni = $is_update ? $_POST['current_dni'] : '';
        $r = checkParameters($_POST, $is_update, $current_email, $current_dni);

        if (!empty($r) && empty($r['error'])) {
            $formValid = true;
        } 

        // si el formulario no tiene errores se comprueba si está en modo "Editor" o no, 
        // si no se añade un nuevo usuario a la tabla, en caso contrario se actualiza
        if ($formValid && $_POST['submit'] == 'Confirmar') {
            $db = connectDatabase();
            if ($is_update) {
                $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, dni = ?, email = ?, clave = ?, tarjeta = ?, rol = ? WHERE email = ?");
                $hashedClave = password_hash($r['clave'], PASSWORD_BCRYPT);
                $stmt->bind_param("ssssssss", $r['nombre'], $r['apellidos'], $r['dni'], $r['email'], $hashedClave, $r['tarjeta'], $r['rol'], $current_email);
            } else {
                $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellidos, dni, email, clave, tarjeta, rol) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $hashedClave = password_hash($r['clave'], PASSWORD_BCRYPT);
                $stmt->bind_param("sssssss", $r['nombre'], $r['apellidos'], $r['dni'], $r['email'], $hashedClave, $r['tarjeta'], $r['rol']);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = $is_update ? 'Usuario actualizado con éxito.' : 'Usuario añadido con éxito.';
                $log = $is_update ? "Usuario {$_SESSION['email']} modificó el usuario {$r['email']}" : "Usuario {$_SESSION['email']} creó el usuario {$r['email']}";
                log_event($log);
                page_redirect('usuarios');
            } else {
                $_SESSION['error'] = $is_update ? 'Se produjo un error al actualizar el usuario.' : 'Se produjo un error al añadir el usuario.';
                error_log('Error al ejecutar la consulta: ' . $stmt->error);
                error_log('Datos: ' . print_r($r, true));
            }
            $stmt->close();
            $db->close();
        }
    } 
    // eliminas un usuario de la lista
    elseif (isset($_POST['delete_confirm'])) {
        if ($_POST['delete_confirm'] == 'Confirmar') {
            $emailToDelete = $_POST['email'];
            $db = connectDatabase();
            $stmt = $db->prepare("DELETE FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $emailToDelete);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Usuario eliminado con éxito.';
                log_event("Usuario {$_SESSION['email']} eliminó el usuario $emailToDelete");
                page_redirect('usuarios');
            } else {
                $_SESSION['error'] = 'Se produjo un error al eliminar el usuario.';
                error_log('Error al ejecutar la consulta: ' . $stmt->error);
            }
            $stmt->close();
            $db->close();
        } elseif ($_POST['delete_confirm'] == 'Cancelar') {
            page_redirect('usuarios');
        }
    } 
    // los datos de una reserva se guardan para precompletar el formulario en modo "Editar"
    elseif (isset($_POST['edit'])) {
        $editMode = true;
        $r['nombre'] = $_POST['nombre'];
        $r['apellidos'] = $_POST['apellidos'];
        $r['dni'] = $_POST['dni'];
        $r['email'] = $_POST['email'];
        $r['tarjeta'] = $_POST['tarjeta'];
        $r['rol'] = $_POST['rol'];
    }
}

// Guardar los valores del formulario si existen 
$valNombre = isset($r['nombre']) ? 'value="' .$r['nombre'].'"' : '';
$valApellidos = isset($r['apellidos']) ? 'value="' .$r['apellidos'].'"' : '';
$valDNI = isset($r['dni']) ? 'value="' .$r['dni'].'"' : '';
$valEmail = isset($r['email']) ? 'value="' .$r['email'].'"' : '';
$valClave = isset($r['clave']) ? 'value="' .$r['clave'].'"' : '';
$valTarjeta = isset($r['tarjeta']) ? 'value="' .$r['tarjeta'].'"' : '';
$hiddenRol = $formValid ? "<input type='hidden' name='rol' value='{$r['rol']}'>" : '';
$selectedCliente = '';
$selectedRecepcionista = '';
$selectedAdministrador = '';
if (isset($r['rol'])) {
    $selectedCliente = ($r['rol'] == 'Cliente') ? 'selected' : '';
    $selectedRecepcionista = ($r['rol'] == 'Recepcionista') ? 'selected' : '';
    $selectedAdministrador = ($r['rol'] == 'Administrador') ? 'selected' : '';
}
// Guardar los errores del formulario si existen 
$errorMessages = '';
if (isset($r['error']) && !empty($r['error'])) {
    foreach ($r['error'] as $error) {
        $errorMessages .= '<p class="error">' . $error . '</p>';
    }
}
// Guardar los cambios para la pantalla de confirma de los datos
$submitButton = $formValid ? 'Confirmar' : ($editMode ? 'Actualizar usuario' : 'Añadir nuevo usuario');
$readOnlyAttr = $formValid ? 'readonly' : '';
$disabledAttr = $formValid ? 'disabled' : '';

?>

<div class="form-container">
    <?php if (!empty($errorMessages)): ?>
        <div class="error-box">
            <?php echo $errorMessages; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-box">
            <p class="success" ><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
        </div>
    <?php endif; ?>
    <form action="index.php?page=usuarios" method="post" novalidate>
        <div class="form-group">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" <?php echo $valNombre ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" <?php echo $valApellidos ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="dni">DNI:</label>
            <input type="text" id="dni" name="dni" <?php echo $valDNI ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" <?php echo $valEmail ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="clave">Clave:</label>
            <input type="password" id="clave" name="clave" <?php echo $valClave ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="tarjeta">Número de tarjeta de crédito:</label>
            <input type="text" id="tarjeta" name="tarjeta" <?php echo $valTarjeta ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="rol">Rol:</label>
            <select id="rol" name="rol" <?php echo $disabledAttr ?> required>
                <?php if ($role == 'Recepcionista'): ?>
                    <option value="Cliente" <?php echo $selectedCliente ?>>Cliente</option>
                <?php else: ?>
                    <option value="Cliente" <?php echo $selectedCliente ?>>Cliente</option>
                    <option value="Recepcionista" <?php echo $selectedRecepcionista ?>>Recepcionista</option>
                    <option value="Administrador" <?php echo $selectedAdministrador ?>>Administrador</option>
                <?php endif; ?>
            </select>
            <?php echo $hiddenRol ?>
        </div>
        <?php if ($editMode): ?>
            <input type="hidden" name="is_update" value="1">
            <input type="hidden" name="current_email" value="<?php echo saneador($r['email']); ?>">
            <input type="hidden" name="current_dni" value="<?php echo saneador($r['dni']); ?>">
        <?php endif; ?>
        <div class="form-group form-submit">
            <input type="submit" name="submit" value="<?php echo $submitButton ?>">
        </div>
    </form>
</div>

<h2>Listado de usuarios</h2>
<ul class="users-list">
    <?php foreach ($users as $user): ?>
        <li id="user-<?php echo saneador($user['dni']); ?>">
            <div class="user-profile">
                <img src="img_src/<?php echo get_profile_image($user['rol']); ?>" alt="Profile Image" class="profile-img">
                <div class="user-info">
                    <div class="user-columns">
                        <div class="user-column">
                            <label>Nombre:</label>
                            <input type="text" value="<?php echo saneador($user['nombre']); ?>" disabled>
                            <label>Apellidos:</label>
                            <input type="text" value="<?php echo saneador($user['apellidos']); ?>" disabled>
                            <label>DNI:</label>
                            <input type="text" value="<?php echo saneador($user['dni']); ?>" disabled>
                        </div>
                        <div class="user-column">
                            <label>Email:</label>
                            <input type="email" value="<?php echo saneador($user['email']); ?>" disabled>
                            <label>Clave:</label>
                            <input type="password" placeholder="(ocultada)" disabled>
                            <label>Tarjeta:</label>
                            <input type="text" value="<?php echo saneador($user['tarjeta']); ?>" disabled>
                        </div>
                        <div class="user-actions">
                            <label>Rol:</label>
                            <select disabled>
                                <option value="Cliente" <?php if ($user['rol'] == 'Cliente') echo 'selected'; ?>>Cliente</option>
                                <option value="Recepcionista" <?php if ($user['rol'] == 'Recepcionista') echo 'selected'; ?>>Recepcionista</option>
                                <option value="Administrador" <?php if ($user['rol'] == 'Administrador') echo 'selected'; ?>>Administrador</option>
                            </select>
                            <?php if ($role != 'Recepcionista' || $user['rol'] == 'Cliente'): ?>
                                <form action="index.php?page=usuarios" method="post" novalidate>
                                    <input type="hidden" name="nombre" value="<?php echo saneador($user['nombre']); ?>">
                                    <input type="hidden" name="apellidos" value="<?php echo saneador($user['apellidos']); ?>">
                                    <input type="hidden" name="dni" value="<?php echo saneador($user['dni']); ?>">
                                    <input type="hidden" name="email" value="<?php echo saneador($user['email']); ?>">
                                    <input type="hidden" name="tarjeta" value="<?php echo saneador($user['tarjeta']); ?>">
                                    <input type="hidden" name="rol" value="<?php echo saneador($user['rol']); ?>">
                                    <input type="submit" name="edit" value="Editar">
                                </form>
                            <?php endif; ?>
                            <?php if (isset($_POST['delete']) && $_POST['email'] == $user['email'] && ($role != 'Recepcionista' || $user['rol'] == 'Cliente')): ?>
                                <form action="index.php?page=usuarios" method="post" novalidate>
                                    <input type="hidden" name="email" value="<?php echo saneador($user['email']); ?>">
                                    <p>¿Está seguro de que desea eliminar este usuario?</p>
                                    <input type="submit" name="delete_confirm" value="Confirmar">
                                    <input type="submit" name="delete_confirm" value="Cancelar">
                                </form>
                            <?php elseif ($role != 'Recepcionista' || $user['rol'] == 'Cliente'): ?>
                                <form action="index.php?page=usuarios#user-<?php echo saneador($user['dni']); ?>" method="post" novalidate>
                                    <input type="hidden" name="email" value="<?php echo saneador($user['email']); ?>">
                                    <input type="submit" name="delete" value="Borrar">
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </li>
    <?php endforeach; ?>
</ul>


