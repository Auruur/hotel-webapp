<?php
function connectDatabase(){
    // las credenciales para acceder a la base de datos se cargan desde un archivo .json, 
    // esta función será llamada cada vez que se realicen operaciones en la base de datos para realizar la conexión.
    $credenciales = json_decode(file_get_contents('users/credenciales.json'), true);

    $db = new mysqli($credenciales['DBHOST'], $credenciales['DBUSER'], $credenciales['DBPASSWORD'], $credenciales['DBDATABASE']);
    if($db){
        $msgconex = "Conexión con éxito";
        mysqli_set_charset($db,'utf8');
    }
    else{
        $msgconex = 'Error de conexion ('.mysqli_connect_error().'): '.mysqli_connect_error();
    }
    return $db;
}
?>
