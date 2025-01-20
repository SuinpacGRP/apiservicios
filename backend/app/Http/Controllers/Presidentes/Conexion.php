<?php

function conectarBD() {
    // Aquí iría tu lógica de conexión a la base de datos
    // Por ejemplo:
    $conexion = mysqli_connect("127.0.0.1", "sairin", "nV@1gF]9Y:tH", "sairin_grp01");

    // Verificar si la conexión fue exitosa
    if (!$conexion) {
        die("Error al conectar a la base de datos: " . mysqli_connect_error());
    }

    return $conexion;
}

