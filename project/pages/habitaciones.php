<?php
require_once 'scripts/utils.php';

if(isset($_SESSION['role'])){
    $role = $_SESSION['role'];
}

$r = [];
$formValid = false;
$editMode = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    unset($_SESSION['uploaded_fotos']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit'])) {
        $is_update = isset($_POST['is_update']) && $_POST['is_update'] === '1';
        if($is_update){
            $editMode = true;
        }
        $current_numero = $is_update ? $_POST['current_numero'] : '';
        $r = checkParametersHabitaciones($_POST, $is_update, $current_numero);

        if(!empty($r) && empty($r['error'])){
            $formValid = true;
        }

        // si el formulario no tiene errores se comprueba si está en modo "Editor" o no, 
        // si no se añade una nueva habitacion a la tabla, en caso contrario se actualiza
        if($formValid && $_POST['submit'] == 'Confirmar'){
            if($is_update) {
                update_habitacion($r['id'], $r['numero'], $r['capacidad'], $r['precio'], $r['descripcion'], $_SESSION['uploaded_fotos'] ?? []);
                log_event("Usuario {$_SESSION['email']} modificó la habitación {$r['numero']}");
            } else {
                add_habitacion($r['numero'], $r['capacidad'], $r['precio'], $r['descripcion'], $_SESSION['uploaded_fotos'] ?? []);
                log_event("Usuario {$_SESSION['email']} creó la habitación {$r['numero']}");
            }
            unset($_SESSION['uploaded_fotos']);
            $_SESSION['success'] = $is_update ? '<p class="success">Habitación actualizada con éxito</p>' : '<p class="success">Habitación añadida con éxito</p>';
            page_redirect('habitaciones');
        }
        else {
            if (isset($_FILES['fotografia']) && !empty($_FILES['fotografia']['tmp_name'][0])) {
                if (!isset($_SESSION['uploaded_fotos'])) {
                    $_SESSION['uploaded_fotos'] = [];
                }
                foreach ($_FILES['fotografia']['tmp_name'] as $index => $tmpName) {
                    $imgData = file_get_contents($tmpName);
                    $imgBase64 = base64_encode($imgData);
                    $_SESSION['uploaded_fotos'][] = $imgBase64;
                }
            }
        }
    } 
    // eliminas una habitación de la lista
    elseif (isset($_POST['delete_confirm'])) {
        if($_POST['delete_confirm'] == 'Confirmar'){
            delete_habitacion($_POST['habitacion_id']);
            log_event("Usuario {$_SESSION['email']} eliminó la habitación {$_POST['habitacion_numero']}");
            $_SESSION['success'] = '<p class="success">Habitación eliminada con éxito</p>';
            page_redirect('habitaciones');

        }
        else if ($_POST['delete_confirm'] == 'Cancelar'){
            page_redirect('habitaciones');
        }
    } 
    // los datos de una reserva se guardan para precompletar el formulario en modo "Editar"
    else if (isset($_POST['edit'])){
        $editMode = true;
        $r['id'] = $_POST['id'];
        $r['numero'] = $_POST['numero'];
        $r['capacidad'] = $_POST['capacidad'];
        $r['precio'] = $_POST['precio'];
        $r['descripcion'] = $_POST['descripcion'];
    }
}

$habitaciones = get_habitaciones();
// Guardar los valores del formulario si existen
$valNumero = isset($r['numero']) ? 'value="' .$r['numero'].'"' : '';
$valCapacidad = isset($r['capacidad']) ? 'value="' .$r['capacidad'].'"' : '';
$valPrecio = isset($r['precio']) ? 'value="' .$r['precio'].'"' : '';
$valDescripcion = isset($r['descripcion']) ? $r['descripcion'] : '';
// Guardar los errores del formulario si existen
$errorMessages = '';
if (isset($r['error']) && !empty($r['error'])) {
    foreach ($r['error'] as $error) {
        $errorMessages .= '<p class="error">' . $error . '</p>';
    }
}
// Guardar los cambios para la pantalla de confirma de los datos
$submitButton = $formValid ? 'Confirmar' : ($editMode ? 'Actualizar habitación' : 'Añadir nueva habitación');
$readOnlyAttr = $formValid ? 'readonly' : '';

?>

<?php if (isset($role) && $role=='Recepcionista'): ?>
<div class="form-container">
    <?php if (!empty($errorMessages)): ?>
        <div class="error-box">
            <?php echo $errorMessages; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-box">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <form action="index.php?page=habitaciones" method="post" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="numero">Número de habitación:</label>
            <input type="text" id="numero" name="numero" <?php echo $valNumero ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="capacidad">Capacidad:</label>
            <input type="number" id="capacidad" name="capacidad" <?php echo $valCapacidad ?> <?php echo $readOnlyAttr ?> required>
        </div>
        <div class="form-group">
            <label for="precio">Precio por noche:</label>
            <input type="number" id="precio" name="precio" <?php echo $valPrecio ?> <?php echo $readOnlyAttr ?> required step="0.01">
        </div>
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="1" <?php echo $readOnlyAttr ?> required><?php echo $valDescripcion ?></textarea>
        </div>
        <div class="form-group">
            <label for="fotografia">Fotografía:</label>
            <?php if (($formValid && !empty($_SESSION['uploaded_fotos'])) || (isset($_SESSION['uploaded_fotos']) && !empty($_SESSION['uploaded_fotos']))): ?>
                <div class="foto-preview">
                    <?php foreach ($_SESSION['uploaded_fotos'] as $foto): ?>
                        <img src="data:image/jpeg;base64,<?php echo $foto; ?>" class="foto-thumb" />
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <input type="file" id="fotografia" name="fotografia[]" accept="image/*" multiple>
            <?php endif; ?>
        </div>
        <?php if ($editMode): ?>
            <input type="hidden" name="is_update" value="1">
            <input type="hidden" name="id" value="<?php echo saneador($r['id']); ?>">
            <input type="hidden" name="current_numero" value="<?php echo saneador($r['numero']); ?>">
        <?php endif; ?>
        <div class="form-group form-submit">
            <input type="submit" name="submit" value="<?php echo $submitButton ?>">
        </div>
    </form>
</div>
<?php endif; ?>

<div class="habitaciones-lista">
    <h2>Listado de habitaciones</h2>
    <ul class="room-list">
    <?php foreach ($habitaciones as $habitacion): ?>
        <li id="habitacion-<?php echo saneador($habitacion['numero']); ?>">
            <div class="room-profile">
            <?php 
            $fotos = get_fotos($habitacion['id']);
            if (!empty($fotos)) {
                echo '<img class="room-image" src="data:image/jpeg;base64,' . base64_encode($fotos[0]['foto']) . '" alt="' . $habitacion['numero'] . '">';
            } else {
                echo '<img class="room-image" src="img_src/default_room.png" alt="Sin imagen">';
            }
            ?>
            <div class="habitacion">
                    <div class="room-columns">
                        <div class="room-column">
                            <label>Número de habitación:</label>
                            <input type="text" value="<?php echo saneador($habitacion['numero']); ?>" disabled>
                            <label>Capacidad:</label>
                            <input type="number" value="<?php echo saneador($habitacion['capacidad']); ?>" disabled>
                        </div>
                        <div class="room-column">
                            <label>Precio por noche:</label>
                            <input type="number" value="<?php echo saneador($habitacion['precio']); ?>" disabled>
                            <label>Descripción:</label>
                            <textarea disabled rows="2"><?php echo saneador($habitacion['descripcion']); ?></textarea>
                        </div>
                        <div class="room-actions">
                            <?php if (isset($role) && $role == 'Recepcionista'): ?>
                                <form action="index.php?page=habitaciones" method="post" novalidate>
                                    <input type="hidden" name="id" value="<?php echo saneador($habitacion['id']); ?>">
                                    <input type="hidden" name="numero" value="<?php echo saneador($habitacion['numero']); ?>">
                                    <input type="hidden" name="capacidad" value="<?php echo saneador($habitacion['capacidad']); ?>">
                                    <input type="hidden" name="precio" value="<?php echo saneador($habitacion['precio']); ?>">
                                    <input type="hidden" name="descripcion" value="<?php echo saneador($habitacion['descripcion']); ?>">
                                    <input type="submit" class="action-button" name="edit" value="Editar">
                                </form>
                            <?php endif; ?>
                            <?php if (isset($_POST['delete']) && $_POST['habitacion_id'] == $habitacion['id'] && isset($role) && $role == 'Recepcionista'): ?>
                                <form action="index.php?page=habitaciones" method="post" novalidate>
                                    <input type="hidden" name="habitacion_id" value="<?php echo saneador($habitacion['id']); ?>">
                                    <input type="hidden" name="habitacion_numero" value="<?php echo saneador($habitacion['numero']); ?>">
                                    <p>¿Está seguro de que desea eliminar esta habitacion?</p>
                                    <input class="action-button" type="submit" name="delete_confirm" value="Confirmar">
                                    <input class="action-button" type="submit" name="delete_confirm" value="Cancelar">
                                </form>
                            <?php elseif (isset($role) && $role == 'Recepcionista'):?>
                                <form action="index.php?page=habitaciones#habitacion-<?php echo $habitacion['numero']; ?>" method="post">
                                    <input type="hidden" name="habitacion_id" value="<?php echo $habitacion['id']; ?>">
                                    <input type="submit" name="delete" value="Borrar" class="action-button">
                                </form>
                            <?php endif; ?>
                            <!-- Cuando presionas este botón, se ejecuta el código javascript para mostrar todas las fotos de la habitación. -->
                            <button type="button" class="action-button" onclick="toggleFotos(<?php echo $habitacion['id']; ?>)">Ver fotografías</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="fotos-container" id="fotos-<?php echo $habitacion['id']; ?>" style="display:none;">
                <button type="button" onclick="prevFoto(<?php echo $habitacion['id']; ?>)" class="action-button">Anterior</button>
                <img id="foto-<?php echo $habitacion['id']; ?>" src="" alt="Foto">
                <button type="button" onclick="nextFoto(<?php echo $habitacion['id']; ?>)" class="action-button">Siguiente</button>
                <p class="empty-msg" >No hay fotografías disponibles para esta habitación.</p>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
</div>


<script>
const fotos = <?php 
    $fotosData = [];
    foreach ($habitaciones as $habitacion) {
        $fotosData[$habitacion['id']] = [];
        $fotos = get_fotos($habitacion['id']);
        foreach ($fotos as $foto) {
            $fotosData[$habitacion['id']][] = 'data:image/jpeg;base64,' . base64_encode($foto['foto']);
        }
    }
    echo json_encode($fotosData);
?>;

function toggleFotos(habitacionNumero) {
    const fotosContainer = document.getElementById('fotos-' + habitacionNumero);
    if (fotosContainer.style.display === 'none') {
        if (fotos[habitacionNumero].length > 0) {
            fotosContainer.style.display = 'block';
            showFoto(habitacionNumero, 0);
        } else {
            fotosContainer.style.display = 'block';
            fotosContainer.innerHTML = '<p>No hay fotografías disponibles para esta habitación.</p>';
        }
    } else {
        fotosContainer.style.display = 'none';
    }
}

function showFoto(habitacionNumero, index) {
    const fotoElement = document.getElementById('foto-' + habitacionNumero);
    if (fotos[habitacionNumero].length > 0) {
        fotoElement.src = fotos[habitacionNumero][index];
        fotoElement.dataset.index = index;
    } else {
        fotoElement.src = '';
    }
}

function nextFoto(habitacionNumero) {
    const fotoElement = document.getElementById('foto-' + habitacionNumero);
    let index = parseInt(fotoElement.dataset.index);
    index = (index + 1) % fotos[habitacionNumero].length;
    showFoto(habitacionNumero, index);
}

function prevFoto(habitacionNumero) {
    const fotoElement = document.getElementById('foto-' + habitacionNumero);
    let index = parseInt(fotoElement.dataset.index);
    index = (index - 1 + fotos[habitacionNumero].length) % fotos[habitacionNumero].length;
    showFoto(habitacionNumero, index);
}
</script>