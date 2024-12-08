<?php
require_once 'scripts/utils.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] == 'Administrador') {
    page_redirect('home');
}

unset($_SESSION['log_page']);

$r = [];

$errorMessages = '';
$roomFound = false;
$editMode = false;

$client_filter = $_COOKIE['client_filter'] ?? '';
$comment_filter = $_COOKIE['comment_filter'] ?? '';
$date_start_filter = $_COOKIE['date_start_filter'] ?? '';
$date_end_filter = $_COOKIE['date_end_filter'] ?? '';
$order_by = $_COOKIE['order_by'] ?? 'marca_tiempo';
$order_direction = $_COOKIE['order_direction'] ?? 'ASC';

if($_SESSION['role'] == 'Recepcionista'){
    $clients = get_clients();
    $reservations = get_reservations();
}
else{
    $reservations = get_reservations($_SESSION['email']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit']) && $_POST['submit'] === 'Reservar') {
        $r = checkParametersReservas($_POST);

        if(!empty($r) && empty($r['error'])){
            $room = find_suitable_room($r['personas'], $r['dia_entrada'], $r['dia_salida']);

            // si se encuentra una habitación disponible, la reserva se crea con el estado "Pendiente"
            if ($room) {
                if($_SESSION['role'] == 'Cliente'){
                    $cliente_id = get_user_id($_SESSION['email']);
                }
                else if ($_SESSION['role'] == 'Recepcionista'){
                    $cliente_id = saneador($_POST['select-cliente']);
                }
                $habitacion_id = $room['id'];
    
                $habitacion_numero = $room['numero'];
                $habitacion_precio = $room['precio'];
                $habitacion_capacidad = $room['capacidad'];
                $habitacion_descripcion = $room['descripcion'];
    
                $roomFound = true;

                $reservation_id = create_reservation($cliente_id, $habitacion_id, $r['personas'], $r['comentarios'], $r['dia_entrada'], $r['dia_salida']);
                log_event("Usuario {$_SESSION['email']} creó una reserva para la habitación $habitacion_numero con estado 'Pendiente'");
                $_SESSION['reservation_id'] = $reservation_id;
                $_SESSION['success'] = '<p class="success">Habitación encontrada con éxito</p>';
                $_SESSION['habitacion_numero'] = $habitacion_numero;
             
            } else {
                $errorMessages .= '<p class="error">No hay habitaciones disponibles</p>';
            }
        }
    }
    // si la reserva está confirmada comprueba que no han pasado 30 segundos y cambia el estado a "confirmada", 
    // en caso contrario la reserva se elimina
    else if (isset($_POST['submit']) && $_POST['submit'] === 'Confirmar la reserva'){
        if(isset($_SESSION['reservation_id'])){
            $reservation_id = $_SESSION['reservation_id'];
            $reservation = get_reservation($reservation_id);
            $time_elapsed = time() - strtotime($reservation['marca_tiempo']);
            if ($time_elapsed > 30) {
                cancel_reservation($reservation_id);
                log_event("Usuario {$_SESSION['email']} dejó pasar demasiado tiempo y la reserva para la habitación {$_SESSION['habitacion_numero']} fue cancelada");
                $errorMessages .= '<p class="error">Tiempo expirado. Por favor, inicie una nueva reserva</p>';
            } else {
                confirm_reservation($reservation_id);
                log_event("Usuario {$_SESSION['email']} confirmó la reserva para la habitación {$_SESSION['habitacion_numero']}");
                $_SESSION['success'] = '<p class="success">Reserva confirmada con éxito</p>';
            }
        }
        unset($_SESSION['reservation_id']);
        unset($_SESSION['habitacion_numero']);
    }
    // si el usuario pulsa "Cancelar" la reserva se elimina
    else if (isset($_POST['submit']) && $_POST['submit'] === 'Cancelar la reserva'){
        if(isset($_SESSION['reservation_id'])){
            $reservation_id = $_SESSION['reservation_id'];
            $reservation = get_reservation($reservation_id);
            cancel_reservation($reservation_id);
            log_event("Usuario {$_SESSION['email']} no confirmó la reserva para la habitación {$_SESSION['habitacion_numero']} y fue cancelada");
            $_SESSION['success'] = '<p class="success">Reserva cancelada con éxito</p>';
        }
        unset($_SESSION['reservation_id']);
        unset($_SESSION['habitacion_numero']);
    }
    // eliminar una reserva del listado
    else if (isset($_POST['delete_confirm'])) {
        if ($_POST['delete_confirm'] == 'Confirmar') {
            cancel_reservation($_POST['reserva_id']);
            log_event("Usuario {$_SESSION['email']} eliminó la reserva para la habitación {$_POST['habitacion_numero']}");
            $_SESSION['success'] = '<p class="success">Reserva cancelada con éxito</p>';
            page_redirect('reservas');
        } elseif ($_POST['delete_confirm'] == 'Cancelar') {
            page_redirect('reservas');
        }
    }
    // los datos de una reserva se guardan para precompletar el formulario en modo "Editar"
    elseif (isset($_POST['edit'])) {
        $editMode = true;
        $r['personas'] = $_POST['num_personas'];
        $r['dia_entrada'] = $_POST['dia_entrada'];
        $r['dia_salida'] = $_POST['dia_salida'];
        $r['comentarios'] = $_POST['comentarios'];
        $r['cliente_id'] = $_POST['cliente_id'];
        $_SESSION['reservation_id'] = $_POST['reserva_id'];
        $_SESSION['habitacion_numero'] = $_POST['habitacion_numero'];
    }
    // Los comentarios de una reserva se actualizan.
    else if (isset($_POST['submit']) && $_POST['submit'] == 'Actualizar reserva'){
        $comentarios = saneador($_POST['comentarios']);
        $reserva_id = saneador($_SESSION['reservation_id']);
        $habitacion_numero = saneador($_SESSION['habitacion_numero']);

        unset($_SESSION['reservation_id']);
        unset($_SESSION['habitacion_numero']);

        if (update_reservation_comments($reserva_id, $comentarios)){
            log_event("Usuario {$_SESSION['email']} modificó los comentarios de la reserva para la habitación {$habitacion_numero}");
            $_SESSION['success'] = '<p class="success">Comentarios de la reserva actualizados con éxito</p>';
        }
        else{
            $errorMessages .= '<p class="error">No se pudieron actualizar los comentarios</p>';
        }

        page_redirect('reservas');
    }
    // todos los filtros enviados por el formulario se guardan como cookies y se envían 
    // a la función get_filtered_reservations() para que pueda devolver la lista con los filtros aplicados
    else if (isset($_POST['apply_filters'])) {
        $client_filter = $_POST['client_filter'];
        $comment_filter = $_POST['comment_filter'];
        $date_start_filter = $_POST['date_start_filter'];
        $date_end_filter = $_POST['date_end_filter'];
        $order_by = $_POST['order_by'];
        $order_direction = $_POST['order_direction'];

        setcookie('client_filter', $client_filter, time() + (86400 * 30), "/");
        setcookie('comment_filter', $comment_filter, time() + (86400 * 30), "/");
        setcookie('date_start_filter', $date_start_filter, time() + (86400 * 30), "/");
        setcookie('date_end_filter', $date_end_filter, time() + (86400 * 30), "/");
        setcookie('order_by', $order_by, time() + (86400 * 30), "/");
        setcookie('order_direction', $order_direction, time() + (86400 * 30), "/");

        $reservations = get_filtered_reservations($client_filter, $comment_filter, $date_start_filter, $date_end_filter, $order_by, $order_direction);
    }
}

// Guardar los valores del formulario si existen
$valPersonas = isset($r['personas']) ? 'value="' .$r['personas'].'"' : '';
$valDiaEntrada= isset($r['dia_entrada']) ? 'value="' .$r['dia_entrada'].'"' : '';
$valDiaSalida = isset($r['dia_salida']) ? 'value="' .$r['dia_salida'].'"' : '';
$valComentarios = isset($r['comentarios']) ? $r['comentarios'] : '';
$valClienteId = isset($r['cliente_id']) ? $r['cliente_id'] : '';
// Guardar los errores del formulario si existen
if (isset($r['error']) && !empty($r['error'])) {
    foreach ($r['error'] as $error) {
        $errorMessages .= '<p class="error">' . $error . '</p>';
    }
}
// Guardar los cambios para la pantalla de confirma de los datos
$submitButton = $roomFound ? "Confirmar la reserva" : ($editMode ? 'Actualizar reserva' : 'Reservar') ;
$readOnlyAttr = $roomFound ? 'readonly' : '';
$readOnlyEdit = $editMode ? 'readonly' : '';
$disabledAttr = $roomFound ? 'disabled' : '';
$disabledEdit = $editMode ? 'disabled' : '';


?>

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
    <form action="index.php?page=reservas" method="post" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="personas">Número de personas:</label>
            <input type="number" id="personas" name="personas" <?php echo $valPersonas ?> <?php echo $readOnlyAttr ?> <?php echo $readOnlyEdit ?> required>
        </div>
        <div class="form-group">
            <label for="dia_entrada">Día de entrada:</label>
            <input type="date" id="dia_entrada" name="dia_entrada" <?php echo $valDiaEntrada ?> <?php echo $readOnlyAttr ?> <?php echo $readOnlyEdit ?> required>
        </div>
        <div class="form-group">
            <label for="dia_salida">Día de salida:</label>
            <input type="date" id="dia_salida" name="dia_salida" <?php echo $valDiaSalida ?> <?php echo $readOnlyAttr ?> <?php echo $readOnlyEdit ?> required>
        </div>
        <div class="form-group">
            <label for="comentarios">Comentarios:</label>
            <textarea id="comentarios" name="comentarios" rows="1" <?php echo $readOnlyAttr ?> ><?php echo $valComentarios ?></textarea>
        </div>
        <?php if ($_SESSION['role'] == 'Recepcionista'): ?>
        <div class="form-group">
            <label for="select-cliente">Cliente:</label>
            <select id="select-cliente" name="select-cliente" <?php echo $disabledAttr ?> <?php echo $disabledEdit ?> required>
                <?php 
                    if (!empty($clients)) {
                        foreach ($clients as $client) {
                            $selected = ($client['id'] == $valClienteId) ? 'selected' : '';
                            echo '<option value="'.$client['id'].'" '.$selected.'>'.$client['email'].'</option>';
                        }                        
                    }
                ?>
            </select>
        </div>
        <?php endif; ?>
        <!-- si se encuentra una habitación disponible, se muestra toda la información al usuario para que pueda confirmar o cancelar la reserva -->
        <?php if ($roomFound): ?>
            <div class="room-profile">
                <?php 
                $fotos = get_fotos($habitacion_id);
                if (!empty($fotos)) {
                    echo '<img class="room-image" src="data:image/jpeg;base64,' . base64_encode($fotos[0]['foto']) . '" alt="' . $habitacion_numero . '">';
                } else {
                    echo '<img class="room-image" src="img_src/default_room.png" alt="Sin imagen">';
                }
                ?>
                <div class="habitacion">
                    <div class="room-columns">
                        <div class="room-column">
                            <label>Número de habitación:</label>
                            <input type="text" value="<?php echo saneador($habitacion_numero); ?>" disabled>
                            <label>Capacidad:</label>
                            <input type="number" value="<?php echo saneador($habitacion_capacidad); ?>" disabled>
                        </div>
                        <div class="room-column">
                            <label>Precio por noche:</label>
                            <input type="number" value="<?php echo saneador($habitacion_precio); ?>" disabled>
                            <label>Descripción:</label>
                            <textarea disabled rows="2"><?php echo saneador($habitacion_descripcion); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group form-submit">
            <input type="submit" name="submit" value="Cancelar la reserva">
            </div>
        <?php endif; ?>   
            <div class="form-group form-submit">
            <input type="submit" name="submit" value="<?php echo $submitButton ?>">
            </div>
    </form>
</div>

<div class="form-container">
    <h2>Filtrar y Ordenar Reservas</h2>
    <form action="index.php?page=reservas" method="post">
        <div class="form-group">
            <label for="client_filter">Cliente:</label>
            <select id="client_filter" name="client_filter">
                <option value="">Todos</option>
                <?php if (!empty($clients)) {
                    foreach ($clients as $client) {
                        $selected = ($client['email'] == $client_filter) ? 'selected' : '';
                        echo '<option value="' . $client['email'] . '" ' . $selected . '>' . $client['email'] . '</option>';
                    }
                } ?>
            </select>
        </div>
        <div class="form-group">
            <label for="comment_filter">Comentarios contiene:</label>
            <input type="text" id="comment_filter" name="comment_filter" value="<?php echo $comment_filter; ?>">
        </div>
        <div class="form-group">
            <label for="date_start_filter">Fecha de inicio:</label>
            <input type="date" id="date_start_filter" name="date_start_filter" value="<?php echo $date_start_filter; ?>">
        </div>
        <div class="form-group">
            <label for="date_end_filter">Fecha de fin:</label>
            <input type="date" id="date_end_filter" name="date_end_filter" value="<?php echo $date_end_filter; ?>">
        </div>
        <div class="form-group">
            <label for="order_by">Ordenar por:</label>
            <select id="order_by" name="order_by">
                <option value="marca_tiempo" <?php if ($order_by == 'marca_tiempo') echo 'selected'; ?>>Antigüedad</option>
                <option value="num_dias" <?php if ($order_by == 'num_dias') echo 'selected'; ?>>Número de días</option>
            </select>
        </div>
        <div class="form-group">
            <label for="order_direction">Dirección:</label>
            <select id="order_direction" name="order_direction">
                <option value="ASC" <?php if ($order_direction == 'ASC') echo 'selected'; ?>>Ascendente</option>
                <option value="DESC" <?php if ($order_direction == 'DESC') echo 'selected'; ?>>Descendente</option>
            </select>
        </div>
        <div class="form-group form-submit">
            <input type="submit" name="apply_filters" value="Aplicar filtros y orden">
        </div>
    </form>
</div>


<h2>Listado de reservas</h2>
<ul class="reservations-list">
    <?php foreach ($reservations as $reservation): ?>
        <li id="reserva-<?php echo saneador($reservation['habitacion_numero']); echo saneador($reservation['dia_entrada']) ?>">
            <div class="reservation-profile">
                <div class="reservation-info">
                    <div class="reservation-columns">
                        <div class="reservation-column">
                            <label>Numero de habitación:</label>
                            <input type="text" value="<?php echo saneador($reservation['habitacion_numero']); ?>" disabled>
                            <label>Cliente:</label>
                            <input type="email" value="<?php echo saneador($reservation['cliente_email']); ?>" disabled>
                            <label>Numero de personas:</label>
                            <input type="number" value="<?php echo saneador($reservation['num_personas']); ?>" disabled>
                        </div>
                        <div class="reservation-column">
                            <label>Día de entrada:</label>
                            <input type="date" value="<?php echo saneador($reservation['dia_entrada']); ?>" disabled>
                            <label>Día de salida:</label>
                            <input type="date" value="<?php echo saneador($reservation['dia_salida']); ?>" disabled>
                            <label>Estado:</label>
                            <input type="text" value="<?php echo saneador($reservation['estado']); ?>" disabled>
                        </div>
                        <div class="reservation-column">
                            <label>Comentarios:</label>
                            <textarea disabled row="4"><?php echo saneador($reservation['comentarios']); ?></textarea>
                            <div class="reservation-actions">
                            <form action="index.php?page=reservas" method="post" novalidate>
                                    <input type="hidden" name="num_personas" value="<?php echo saneador($reservation['num_personas']); ?>">
                                    <input type="hidden" name="dia_entrada" value="<?php echo saneador($reservation['dia_entrada']); ?>">
                                    <input type="hidden" name="dia_salida" value="<?php echo saneador($reservation['dia_salida']); ?>">
                                    <input type="hidden" name="comentarios" value="<?php echo saneador($reservation['comentarios']); ?>">
                                    <input type="hidden" name="cliente_id" value="<?php echo saneador($reservation['cliente_id']); ?>">
                                    <input type="hidden" name="reserva_id" value="<?php echo saneador($reservation['id']); ?>">
                                    <input type="hidden" name="habitacion_numero" value="<?php echo saneador($reservation['habitacion_numero']); ?>">
                                    <input type="submit" name="edit" value="Editar">
                            </form>
                            <?php if (isset($_POST['delete']) && $_POST['reserva_id'] == $reservation['id']): ?>
                                <form action="index.php?page=reservas" method="post" novalidate>
                                    <input type="hidden" name="reserva_id" value="<?php echo saneador($reservation['id']); ?>">
                                    <input type="hidden" name="habitacion_numero" value="<?php echo saneador($reservation['habitacion_numero']); ?>">
                                    <p>¿Está seguro de que desea eliminar esta reserva?</p>
                                    <input type="submit" name="delete_confirm" value="Confirmar">
                                    <input type="submit" name="delete_confirm" value="Cancelar">
                                </form>
                            <?php else: ?>
                                <form action="index.php?page=reservas#reserva-<?php echo saneador($reservation['habitacion_numero']); echo saneador($reservation['dia_entrada']); ?>" method="post" novalidate>
                                    <input type="hidden" name="reserva_id" value="<?php echo saneador($reservation['id']); ?>">
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