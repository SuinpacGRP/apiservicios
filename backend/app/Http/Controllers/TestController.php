<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        // Datos de conexión a la base de datos
        $host = '127.0.0.1';
        $user = 'sairin';
        $pass = 'nV@1gF]9Y:tH';
        $db = 'sairin_grp01';

        // Crear una nueva conexión MySQLi
        $mysqli = new \mysqli($host, $user, $pass, $db);

        // Comprobar la conexión
        if ($mysqli->connect_error) {
            die("Conexión fallida: " . $mysqli->connect_error);
        }
        echo "Conexión exitosa.<br>";

        // Consulta de ejemplo
        $result = $mysqli->query('SELECT * FROM Asistencia_Jornada');

        while ($row = $result->fetch_assoc()) {
            echo $row['Nombre'] . "<br>";
        }

        // Cerrar la conexión
        $mysqli->close();
    }
}
