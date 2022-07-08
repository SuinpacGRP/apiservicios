<?php

function server_path($ruta = "") : string {
    return base_path() . "/../$ruta";
}
