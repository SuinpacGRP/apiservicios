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



function conectarBDSuinpac() {
    // Aquí iría tu lógica de conexión a la base de datos
    // Por ejemplo:
    $conexion = mysqli_connect("127.0.0.1", "aifa", "WGb358A8dyZF", "aifa_grp01");

    // Verificar si la conexión fue exitosa
    if (!$conexion) {
        die("Error al conectar a la base de datos: " . mysqli_connect_error());
    }

    return $conexion;
}


function conectarBDSuinpaco() {
    // Aquí iría tu lógica de conexión a la base de datos
    // Por ejemplo:
    $conexion = mysqli_connect("127.0.0.1", "piacza_suinpac", "UKX6KTeBLMmd", "suinpac_32");

    // Verificar si la conexión fue exitosa
    if (!$conexion) {
        die("Error al conectar a la base de datos: " . mysqli_connect_error());
    }

    return $conexion;
}


