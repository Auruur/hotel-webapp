<?php
require_once 'scripts/connect.php';
require_once 'scripts/utils.php';

// si el usuario no ha iniciado sesión o no es administrador no puede acceder a la página
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Administrador') {
    page_redirect('home');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // si el usuario presiona el botón de copia de seguridad, se descarga un dump de la base de datos actual
    if (isset($_POST['backup'])) {
        $conn = connectDatabase();

        $tables = [];
        $result = $conn->query('SHOW TABLES');
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $sql = 'SET FOREIGN_KEY_CHECKS = 0;' . "\n\n";
        foreach ($tables as $table) {
            $result = $conn->query("SELECT * FROM $table");
            $num_fields = $result->field_count;

            $sql .= "DROP TABLE IF EXISTS `$table`;";
            $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
            $sql .= "\n\n" . $row2[1] . ";\n\n";

            for ($i = 0; $i < $num_fields; $i++) {
                while ($row = $result->fetch_row()) {
                    $sql .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $sql .= '"' . $row[$j] . '"';
                        } else {
                            $sql .= '""';
                        }
                        if ($j < ($num_fields - 1)) {
                            $sql .= ',';
                        }
                    }
                    $sql .= ");\n";
                }
            }
            $sql .= "\n\n\n";
        }
        $sql .= 'SET FOREIGN_KEY_CHECKS = 1;' . "\n\n";

        ob_end_clean();

        $conn->close();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=backup_' . date('Y-m-d_H-i-s') . '.sql');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit();
    }

    // si el usuario presiona el botón restaurar tiene la posibilidad de cargar un archivo .sql, sobre el cual se realizarán multiquery para repoblar la base de datos.
    if (isset($_POST['restore']) && isset($_FILES['backup_file'])) {
        if ($_FILES['backup_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
            $conn = connectDatabase();
            
            $backup_file = $_FILES['backup_file']['tmp_name'];
            $sql = file_get_contents($backup_file);

            $conn->multi_query($sql);
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());

            $_SESSION['success'] = 'Restauración de la BBDD completada con éxito.';
        } else {
            $error_message = 'Error al cargar el archivo de backup: ';
            switch ($_FILES['backup_file']['error']) {
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message .= 'El archivo es demasiado grande.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message .= 'El archivo se cargó solo parcialmente.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message .= 'Ningún archivo fue cargado.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message .= 'Falta la carpeta temporal.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message .= 'Error al escribir el archivo en el disco.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message .= 'Una extensión de PHP detuvo la carga del archivo.';
                    break;
                default:
                    $error_message .= 'Error desconocido.';
                    break;
            }
            $conn->close();

            $_SESSION['error'] = $error_message;
        }
        
        page_redirect('backup');
    }

    // si el usuario presiona el botón de reinicio las filas de la base de datos se cargan con un dump con 
    // las tablas de la base de datos vacía, a excepción de los dos usuarios "administrador"
    if (isset($_POST['reset_confirm']) && $_POST['reset_confirm'] == 'Confirmar') {
        $conn = connectDatabase();

        $sql = file_get_contents('backups/initial_db_setup.sql');

        $conn->multi_query($sql);
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        $conn->close();

        $_SESSION['success'] = 'Reinicio de la BBDD completado con éxito.';

        page_redirect('backup');
    }
    else if (isset($_POST['reset_confirm']) && $_POST['reset_confirm'] == 'Cancelar') {
        page_redirect('backup');
    }

    // si el usuario presiona el botón de repoblar, la base de datos se 
    // vuelve a llenar con un dump realizado con los datos de prueba especificados
    // en los requisitos del proyecto.
    if (isset($_POST['prueba_confirm']) && $_POST['prueba_confirm'] == 'Confirmar') {
        $conn = connectDatabase();

        $sql = file_get_contents('backups/db_prueba.sql');

        $conn->multi_query($sql);
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        $conn->close();

        $_SESSION['success'] = 'Repoblación de la BBDD completado con éxito.';

        page_redirect('backup');
    }
    else if (isset($_POST['prueba_confirm']) && $_POST['prueba_confirm'] == 'Cancelar') {
        page_redirect('backup');
    }
}
?>

<div class="form-container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-box">
        <p class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-box">
        <p class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
        </div>
    <?php endif; ?>
    <form action="index.php?page=backup" method="post">
        <div class="form-group">
        <input type="submit" name="backup" value="Obtener una copia de seguridad de la BBDD">
        </div>
    </form>
    <form action="index.php?page=backup" method="post" enctype="multipart/form-data">
        <div class="form-group">
        <input type="file" name="backup_file" required>
        <input type="submit" name="restore" value="Restaurar la BBDD">
        </div>
    </form>
    
    <!-- Se solicita confirmación para los formularios de reinicio y repoblación con datos de prueba -->
    <div class="form-group">
    <?php if (isset($_POST['reset'])): ?>
    <form action="index.php?page=backup" method="post" novalidate>
        <p>¿Está seguro de que desea Reiniciar la BBDD?</p>
        <input type="submit" name="reset_confirm" value="Confirmar">
        <input type="submit" name="reset_confirm" value="Cancelar">
    </form>
    <?php else: ?>
    <form action="index.php?page=backup" method="post">
        <input type="submit" name="reset" value="Reiniciar la BBDD">
    </form>
    <?php endif; ?>
    
    <div class="form-group">
    <?php if (isset($_POST['prueba'])): ?>
    <form action="index.php?page=backup" method="post" novalidate>
        <p>¿Está seguro de que desea Popular la BBDD con datos de prueba?</p>
        <input type="submit" name="prueba_confirm" value="Confirmar">
        <input type="submit" name="prueba_confirm" value="Cancelar">
    </form>
    <?php else: ?>
    <form action="index.php?page=backup" method="post">
        <input type="submit" name="prueba" value="Popular la BBDD con datos de prueba">
    </form>
    <?php endif; ?>
    </div>

</div>


