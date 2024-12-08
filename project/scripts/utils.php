<?php

// Función para verificar datos de inicio de sesión, 
// tener en cuente que se realiza una comparación entre la contraseña normal y su hash en la base de datos
function check_login($email, $password) {
    $db = connectDatabase();
    $stmt = $db->prepare('SELECT rol, clave FROM usuarios WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($role, $hashed_password);
    $stmt->fetch();
    $stmt->close();
    $db->close();

    if ($hashed_password && password_verify($password, $hashed_password)) {
        return $role;
    }
    else {
        return false;
    }
}

// Función para obtener todos los datos de un usuario desde su correo electrónico.
function get_user_data($email){
    $db = connectDatabase();
    $stmt = $db->prepare('SELECT nombre, email, apellidos, clave, dni, tarjeta FROM usuarios WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($nombre, $email, $apellidos, $clave, $dni, $tarjeta);
    $stmt->fetch();

    $userdata = [
        'nombre' => $nombre,
        'email' => $email,
        'apellidos' => $apellidos,
        'clave' => $clave,
        'dni' => $dni,
        'tarjeta' => $tarjeta
    ];

    $stmt->close();
    $db->close();

    return $userdata;
}

// Función para obtener el ID de un usuario desde su correo electrónico.
function get_user_id($email){
    $db = connectDatabase();
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id);
    $stmt->fetch();

    $stmt->close();
    $db->close();

    return $id;
}

// función para obtener datos de la tabla de usuario
function get_users($role) {
    $db = connectDatabase();

    if ($role != 'Cliente') {
        $stmt = $db->prepare('SELECT nombre, apellidos, dni, clave, tarjeta, email, rol FROM usuarios');
    } else {
        return [];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $db->close();

    return $users;
}

// función para obtener solo usuarios con rol de "cliente"
function get_clients(){
    $db = connectDatabase();

    $stmt = $db->prepare("SELECT id, nombre, apellidos, dni, clave, tarjeta, email, rol FROM usuarios WHERE rol = 'Cliente'");

    $stmt->execute();
    $result = $stmt->get_result();
    $clients = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $db->close();

    return $clients;
}

// función para configurar la imagen de perfil del usuario según su función
function get_profile_image($role) {
    switch ($role) {
        case 'Cliente':
            return 'client_profile.png';
        case 'Recepcionista':
            return 'recepcionist_profile.png';
        case 'Administrador':
            return 'administrator_profile.png';
        default:
            return 'client_profile.png';
    }
}

// función para desinfectar los datos de entrada y evitar ataques de inyección HTML
function saneador($data){
    $data = htmlspecialchars($data);
    $data = strip_tags($data);
    return $data;
}

// función para validar las fechas
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// función para validar datos de formularios de usuarios
function checkParameters($p, $is_update=false, $current_email = '', $current_dni = '') {
    $res = [];
    $res['error'] = [];

    if (!empty($p)){
        // Validar nombre
        if (isset($p['nombre']) && !empty($p['nombre'])){
            $res['nombre'] = saneador($p['nombre']);
        }
        else{
            $res['error']['nombre'] = 'Debe escribir su nombre';
        }
        // Validar apellidos
        if (isset($p['apellidos']) && !empty($p['apellidos'])){
            $res['apellidos'] = saneador($p['apellidos']);
        }
        else{
            $res['error']['apellidos'] = 'Debe escribir su apellidos';
        }
        // Validar DNI
        if (isset($p['dni']) && !empty($p['dni']) && preg_match("/^[0-9]{8}[A-Z]$/", ($p['dni']))){
            if(!$is_update || ($is_update && $p['dni'] != $current_dni)){
                $db = connectDatabase();
                $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE dni = ?");
                $stmt->bind_param("s", $p['dni']);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                if ($count > 0) {
                    $res['error']['dni'] = 'El DNI ya está registrado';
                }
                else{
                    $res['dni'] = saneador($p['dni']);
                }
            }
            else{
                $res['dni'] = $current_dni;
            }
        }
        else{
            $res['error']['dni'] = 'El DNI no es válido';
        }
        // Validar email
        if (isset($p['email']) && !empty($p['email'])){
            $correo = $p["email"];
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $res['error']['email'] = 'El email no es válido';
            }
            else if (!$is_update || ($is_update && $p['email'] != $current_email)){
                $db = connectDatabase();
                $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
                $stmt->bind_param("s", $p['email']);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                if ($count > 0) {
                    $res['error']['email'] = 'El email ya está registrado';
                }
                else{
                    $res['email'] = saneador($p['email']);
                }
            }
            else{
                $res['email'] = $current_email;
            }
        }
        else{
            $res['error']['email'] = 'Debe indicar un email de contacto';
        }
        // Validar clave
        if (strlen($p['clave'] >= 5) || ctype_alnum($p['clave'])){
            $res['clave'] = saneador($p['clave']);
        }
        else{
            $res['error']['clave'] = 'La clave debe contener al menos 5 caracteres alfanuméricos.';
        }
        // Validar tarjeta
        if (!preg_match('/^\d{16}$/', $p['tarjeta'])) {
            $res['error']['tarjeta'] = 'El formato del número de tarjeta no es correcto.';
        } else {
            // Algoritmo de Luhn
            $sum = 0;
            $length = strlen($p['tarjeta']);
            for ($i = 0; $i < $length; $i++) {
                $digit = $p['tarjeta'][$length - $i - 1];
                if ($i % 2 == 1) {
                    $digit *= 2;
                }
                $sum += $digit > 9 ? $digit - 9 : $digit;
            }
            if ($sum % 10 !== 0) {
                $res['error']['tarjeta'] = 'El número de tarjeta no es válido.';
            }
            else{
                $res['tarjeta'] = saneador($p['tarjeta']);
            }
        }
        // Validar rol
        if (isset($p['rol']) && in_array($p['rol'], ['Cliente', 'Recepcionista', 'Administrador'])) {
            $res['rol'] = saneador($p['rol']);
        } else if (isset($p['rol']) && !in_array($p['rol'], ['Cliente', 'Recepcionista', 'Administrador'])){
            $res['error']['rol'] = 'El rol no es válido';
        }
    }
    else{
        // Si no se han recibido datos: se devuelve array vacío
        return [];
    }

    return $res;
}

// función para validar datos de formularios de habitaciones
function checkParametersHabitaciones($p, $is_update=false, $current_numero=''){
    $res = [];
    $res['error'] = [] ;

    if(!empty($p)){
        // Validar numero
        if (isset($p['numero']) && (!empty($p['numero']))){
            if(!$is_update || ($is_update && $p['numero'] != $current_numero)){
                $db = connectDatabase();
                $stmt = $db->prepare("SELECT COUNT(*) FROM habitaciones WHERE numero = ?");
                $stmt->bind_param("s", $p['numero']);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                if ($count > 0) {
                    $res['error']['numero'] = 'El numero de la habitación ya está registrado';
                }
                else{
                    $res['numero'] = saneador($p['numero']);
                }
            }
            else{
                $res['numero'] = saneador($current_numero);
            }
        }
        else{
            $res['error']['numero'] = 'Debe escribir el número de la habitación';
        }

        // Validar capacidad
        if (isset($p['capacidad']) && (!empty($p['capacidad'])) && (is_numeric($p['capacidad']))){
            $res['capacidad'] = saneador($p['capacidad']);
        }
        else if (isset($p['capacidad']) && (!empty($p['capacidad'])) && (!is_numeric($p['capacidad']))){
            $res['error']['capacidad'] = 'La capacidad debe ser un número';
        }
        else{
            $res['error']['capacidad'] = 'Debe escribir la capacidad de la habitación';
        }

        // Validar precio
        if (isset($p['precio']) && (!empty($p['precio'])) && (is_numeric($p['precio']))){
            $res['precio'] = saneador($p['precio']);
        }
        else if (isset($p['precio']) && (!empty($p['precio'])) && (!is_numeric($p['precio']))){
            $res['error']['precio'] = 'El precio debe ser un número';
        }
        else{
            $res['error']['precio'] = 'Debe escribir el precio por noche de la habitación';
        }

        // Validar descripcion
        if (isset($p['descripcion']) && (!empty($p['descripcion']))){
            $res['descripcion'] = saneador($p['descripcion']);
        }
        else{
            $res['error']['descripcion'] = 'Debe escribir la descripción de la habitación';
        }

        if ($is_update && isset($p['id'])) {
            $res['id'] = $p['id'];
        }
    }
    else{
        return [];
    }

    return $res;
}

// función para validar datos de formularios de reservas
function checkParametersReservas($p){
    $res = [];
    $res['error'] = [];

    if(!empty($p)){
        // Validar numero personas
        if (isset($p['personas']) && (!empty($p['personas']) && is_numeric($p['personas']))){
            $res['personas'] = saneador($p['personas']);
        }
        else if (isset($p['personas']) && (!empty($p['personas'])) && (!is_numeric($p['personas']))){
            $res['error']['personas'] = 'El número de personas debe ser un número';
        }
        else{
            $res['error']['personas'] = 'Debe escribir el numero de personas para la reserva';
        }

        // Validar dia entrada
        if (isset($p['dia_entrada']) && !empty($p['dia_entrada']) && validateDate($p['dia_entrada'])){
            $res['dia_entrada'] = saneador($p['dia_entrada']);
        }
        else{
            $res['error']['dia_entrada'] = 'Indique el día de entrada';
        }

        // Validar dia salida
        if (isset($p['dia_salida']) && !empty($p['dia_salida']) && validateDate($p['dia_salida'])){
            if(isset($res['dia_entrada'])){
                $diaEntrada = new DateTime($res['dia_entrada']);
                $diaSalida = new DateTime(saneador($p['dia_salida']));
            }
            if ($diaSalida < $diaEntrada) {
                $res['error']['dia_salida'] = 'El día de salida no puede ser anterior al día de entrada';
            }
            else{
                $res['dia_salida'] = saneador($p['dia_salida']);
            }
        }
        else{
            $res['error']['dia_salida'] = 'Indique el día de salida';
        }

        // Validar descripcion
        $res['comentarios'] = saneador($p['comentarios']);
    }
    else{
        return [];
    }

    return $res;
}

// función para obtener estadísticas del hotel
function get_hotel_stats() {
    $db = connectDatabase();

    $stats = [
        'total_habitaciones' => 0,
        'total_capacidad' => 0,
        'total_personas' => 0
    ];

    $query_total_habitaciones = "SELECT COUNT(*) AS total_habitaciones FROM habitaciones";
    if ($result = $db->query($query_total_habitaciones)) {
        $row = $result->fetch_assoc();
        $stats['total_habitaciones'] = $row['total_habitaciones'];
        $result->free();
    }

    $query_total_capacidad = "SELECT SUM(capacidad) AS total_capacidad FROM habitaciones";
    if ($result = $db->query($query_total_capacidad)) {
        $row = $result->fetch_assoc();
        $stats['total_capacidad'] = $row['total_capacidad'];
        $result->free();
    }

    $query_total_personas = "SELECT SUM(num_personas) AS total_personas FROM reservas WHERE estado = 'Confirmada'";
    if ($result = $db->query($query_total_personas)) {
        $row = $result->fetch_assoc();
        $stats['total_personas'] = $row['total_personas'];
        $result->free();
    }

    $db->close();

    return $stats;
}

// Función para obtener todos los datos de la habitación.
function get_habitaciones() {
    $db = connectDatabase();
    $result = $db->query("SELECT * FROM habitaciones");
    $habitaciones = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $db->close();
    return $habitaciones;
}

// función para agregar una habitación
function add_habitacion($numero, $capacidad, $precio, $descripcion, $fotos) {
    $db = connectDatabase();
    $stmt = $db->prepare("INSERT INTO habitaciones (numero, capacidad, precio, descripcion) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sids", $numero, $capacidad, $precio, $descripcion);
    if ($stmt->execute()) {
        $habitacion_id = $stmt->insert_id;
        foreach ($fotos as $foto) {
            $fotoData = base64_decode($foto);
            $stmtFoto = $db->prepare("INSERT INTO fotos (habitacion_id, foto) VALUES (?, ?)");
            $stmtFoto->bind_param("ib", $habitacion_id, $fotoData);
            $stmtFoto->send_long_data(1, $fotoData);
            $stmtFoto->execute();
            $stmtFoto->close();
        }
    }
    $stmt->close();
    $db->close();
}

// Función para actualizar los datos de una habitación.
function update_habitacion($id, $numero, $capacidad, $precio, $descripcion, $fotos) {
    $db = connectDatabase();
    $stmt = $db->prepare("UPDATE habitaciones SET numero = ?, capacidad = ?, precio = ?, descripcion = ? WHERE id = ?");
    $stmt->bind_param("sidsi", $numero, $capacidad, $precio, $descripcion, $id);
    if ($stmt->execute()) {
        // Si se han subido nuevas fotos, se actualizan
        if (!empty($fotos)) {
            $stmtDelete = $db->prepare("DELETE FROM fotos WHERE habitacion_id = ?");
            $stmtDelete->bind_param("i", $id);
            $stmtDelete->execute();
            $stmtDelete->close();
            
            foreach ($fotos as $foto) {
                $fotoData = base64_decode($foto);
                $stmtFoto = $db->prepare("INSERT INTO fotos (habitacion_id, foto) VALUES (?, ?)");
                $stmtFoto->bind_param("ib", $id, $fotoData);
                $stmtFoto->send_long_data(1, $fotoData);
                $stmtFoto->execute();
                $stmtFoto->close();
            }
        }
    }
    $stmt->close();
    $db->close();
}

// función para eliminar reservas de una habitación que se está eliminando
function delete_reservas_by_habitacion($habitacion_id) {
    $db = connectDatabase();
    $stmt = $db->prepare("DELETE FROM reservas WHERE habitacion_id = ?");
    $stmt->bind_param("i", $habitacion_id);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

// función para eliminar una habitación 
function delete_habitacion($id) {
    delete_reservas_by_habitacion($id);

    $db = connectDatabase();
    $stmt = $db->prepare("DELETE FROM habitaciones WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

// función para obtener fotos de una habitación
function get_fotos($habitacion_id) {
    $db = connectDatabase();
    $stmt = $db->prepare("SELECT foto FROM fotos WHERE habitacion_id = ?");
    $stmt->bind_param("i", $habitacion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fotos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
    return $fotos;
}

// función para eliminar reservas con el estado "Pendiente" realizadas más de 30 segundos antes
function cleanup_expired_reservations($time_limit = 30) {
    $db = connectDatabase();
    $stmt = $db->prepare("DELETE FROM reservas WHERE estado = 'Pendiente' AND TIMESTAMPDIFF(SECOND, marca_tiempo, NOW()) > ?");
    $stmt->bind_param("i", $time_limit);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

// función para buscar una habitación libre en función del número de personas y las fechas solicitadas
function find_suitable_room($num_personas, $dia_entrada, $dia_salida) {
    cleanup_expired_reservations();
    $db = connectDatabase();
    $stmt = $db->prepare("
        SELECT id, numero, precio, capacidad, descripcion FROM habitaciones 
        WHERE capacidad >= ?
        AND id NOT IN (
            SELECT habitacion_id FROM reservas 
            WHERE estado IN ('Pendiente', 'Confirmada') 
            AND (dia_entrada <= ? AND dia_salida >= ?)
        )
        ORDER BY capacidad ASC LIMIT 1
    ");
    $stmt->bind_param("iss", $num_personas, $dia_salida, $dia_entrada);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    $db->close();
    return $room;
}

// función para crear una reserva "pendiente" si se encuentra una habitación adecuada
function create_reservation($cliente_id, $habitacion_id, $num_personas, $comentarios, $dia_entrada, $dia_salida) {
    $db = connectDatabase();
    $stmt = $db->prepare("INSERT INTO reservas (cliente_id, habitacion_id, num_personas, comentarios, dia_entrada, dia_salida) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisss", $cliente_id, $habitacion_id, $num_personas, $comentarios, $dia_entrada, $dia_salida);
    $stmt->execute();
    $reservation_id = $stmt->insert_id;
    $stmt->close();
    $db->close();
    return $reservation_id;
}

// Función para cambiar el estado de una reserva a "Confirmada" si el usuario acepta la habitación.
function confirm_reservation($reservation_id) {
    $db = connectDatabase();
    
    $stmt_reservation = $db->prepare("UPDATE reservas SET estado = 'Confirmada' WHERE id = ? AND estado = 'Pendiente'");
    $stmt_reservation->bind_param("i", $reservation_id);
    $stmt_reservation->execute();
    $stmt_reservation->close();
    
    $stmt_get_room_id = $db->prepare("SELECT habitacion_id FROM reservas WHERE id = ?");
    $stmt_get_room_id->bind_param("i", $reservation_id);
    $stmt_get_room_id->execute();
    $result = $stmt_get_room_id->get_result();
    $room_id = $result->fetch_assoc()['habitacion_id'];
    $stmt_get_room_id->close();
    
    $db->close();
}

// función para eliminar una reserva
function cancel_reservation($reservation_id) {
    $db = connectDatabase();
    
    $stmt_get_room_id = $db->prepare("SELECT habitacion_id FROM reservas WHERE id = ?");
    $stmt_get_room_id->bind_param("i", $reservation_id);
    $stmt_get_room_id->execute();
    $result = $stmt_get_room_id->get_result();
    $room_id = $result->fetch_assoc()['habitacion_id'];
    $stmt_get_room_id->close();
    
    $stmt_reservation = $db->prepare("DELETE FROM reservas WHERE id = ?");
    $stmt_reservation->bind_param("i", $reservation_id);
    $stmt_reservation->execute();
    $stmt_reservation->close();
    
    $db->close();
}

// función para obtener los datos de una reserva en función de su id
function get_reservation($reservation_id) {
    $db = connectDatabase();
    $stmt = $db->prepare("SELECT * FROM reservas WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    $stmt->close();
    $db->close();
    return $reservation;
}

// Función para editar los comentarios de una reserva
function update_reservation_comments($reservation_id, $comentarios) {
    $db = connectDatabase();
    $stmt = $db->prepare("UPDATE reservas SET comentarios = ? WHERE id = ?");
    $stmt->bind_param("si", $comentarios, $reservation_id);
    if ($stmt->execute()) {
        $success = true;
    } else {
        $success = false;
    }
    $stmt->close();
    $db->close();
    return $success;
}

// función para obtener datos de reserva, se puede filtrar en función de un solo usuario
function get_reservations($email=''){
    $db = connectDatabase();

    if(isset($email) && !empty($email)){
        $stmt = $db->prepare('SELECT r.*, h.numero AS habitacion_numero, u.email AS cliente_email FROM reservas r JOIN habitaciones h ON r.habitacion_id = h.id JOIN usuarios u ON r.cliente_id = u.id WHERE u.email = ?');
        $stmt->bind_param('s', $email);
    }
    else{
        $stmt = $db->prepare('SELECT r.*, h.numero AS habitacion_numero, u.email AS cliente_email FROM reservas r JOIN habitaciones h ON r.habitacion_id = h.id JOIN usuarios u ON r.cliente_id = u.id');
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $db->close();

    return $reservations;
}

// Función para obtener reservas con filtros aplicados.
function get_filtered_reservations($client_email = '', $comment_filter = '', $date_start_filter = '', $date_end_filter = '', $order_by = 'dia_entrada', $order_direction = 'ASC') {
    $db = connectDatabase();

    $sql = "SELECT reservas.*, habitaciones.numero AS habitacion_numero, usuarios.email AS cliente_email,
            DATEDIFF(dia_salida, dia_entrada) AS num_dias
            FROM reservas
            JOIN habitaciones ON reservas.habitacion_id = habitaciones.id
            JOIN usuarios ON reservas.cliente_id = usuarios.id
            WHERE 1=1";

    if (!empty($client_email)) {
        $sql .= " AND usuarios.email = ?";
    }
    if (!empty($comment_filter)) {
        $sql .= " AND reservas.comentarios LIKE ?";
    }
    if (!empty($date_start_filter)) {
        $sql .= " AND reservas.dia_entrada >= ?";
    }
    if (!empty($date_end_filter)) {
        $sql .= " AND reservas.dia_entrada <= ?";
    }

    if ($order_by == 'num_dias') {
        $sql .= " ORDER BY num_dias $order_direction";
    } else {
        $sql .= " ORDER BY reservas.$order_by $order_direction";
    }

    $stmt = $db->prepare($sql);

    $params = [];
    $types = '';
    if (!empty($client_email)) {
        $params[] = $client_email;
        $types .= 's';
    }
    if (!empty($comment_filter)) {
        $params[] = '%' . $comment_filter . '%';
        $types .= 's';
    }
    if (!empty($date_start_filter)) {
        $params[] = $date_start_filter;
        $types .= 's';
    }
    if (!empty($date_end_filter)) {
        $params[] = $date_end_filter;
        $types .= 's';
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $db->close();

    return $reservations;
}

// función para crear un nuevo log
function log_event($descripcion) {
    $db = connectDatabase();
    $stmt = $db->prepare("INSERT INTO logs (descripcion) VALUES (?)");
    $stmt->bind_param("s", $descripcion);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

// función para obtener la lista de logs
function get_logs($limit, $offset) {
    $db = connectDatabase();
    $stmt = $db->prepare("SELECT fecha, descripcion FROM logs ORDER BY fecha DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
    return $logs;
}

// función para obtener el número de registros, utilizada para la paginación
function get_logs_count() {
    $db = connectDatabase();
    $result = $db->query("SELECT COUNT(*) AS count FROM logs");
    $row = $result->fetch_assoc();
    $db->close();
    return $row['count'];
}

// función para realizar redireccionamientos entre varias páginas y evitar el uso de header()
function page_redirect($page) {
    echo '<script type="text/javascript">window.location.href = "index.php?page=' . $page . '";</script>';
    exit();
}

?>