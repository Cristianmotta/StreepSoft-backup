// hash para incriptar las contraseñas de la base de datos temporalmente
<?php

$contrasena = "admin123";

$hash = password_hash($contrasena, PASSWORD_BCRYPT);

echo $hash;



?>