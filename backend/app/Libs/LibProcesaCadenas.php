<?php

function TokenArreglo($cadena, $token){
    $con = 0;
    $tok = strtok($cadena, $token);
    $arreglo[$con] = $tok;
    while ($tok !== false) {
        $con++;
        $tok = strtok($token);
        $arreglo[$con] = $tok;
    }

    return $arreglo;
}