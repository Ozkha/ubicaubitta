<?php
define('DB_HOST', 'db-mysql-sfo3-90325-do-user-14167904-0.b.db.ondigitalocean.com'); 
define('DB_USER', 'escom');    
define('DB_PASS', '3Bqv5Vsw2W3I');
define('DB_NAME', 'ubicaupiita'); 
define('DB_PORT', 25060);

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($mysqli->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Error de conexión a la base de datos.']));
}

$mysqli->set_charset('utf8mb4');
?>