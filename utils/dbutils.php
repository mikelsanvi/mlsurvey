<?php
require_once 'include/mlpdo.php';

/**
 * Devuelve la conexion a la base de datos.
 *
 * Pool de conexiones: PHP no comparte memoria entre peticiones, asi que el pool
 * lo forman los workers de Apache (mpm_prefork). Cada worker mantiene abierta
 * una conexion persistente (PDO::ATTR_PERSISTENT) que reutilizan todas las
 * peticiones que atiende, y el tamaño del pool es MaxRequestWorkers (ver
 * docker/apache-mlsurvey.conf). Asi un pico de peticiones no paga el coste de
 * abrir y autenticar una conexion nueva por cada llamada.
 *
 * Dentro de una misma peticion se devuelve siempre el mismo objeto, de modo que
 * varias llamadas a dbConn() no abren conexiones extra.
 */
function dbConn (){
    static $dbconn = null;
    if ($dbconn !== null){
        return $dbconn;
    }
    $port = 3306;
    if (isset (Config::PARAMS["db_port"]))
        $port = Config::PARAMS["db_port"];
    
    $dbconn = new MLPDO ('mysql:dbname=' . Config::PARAMS["db_name"] . 
        ';host=' . Config::PARAMS["db_host"] . ';port=' . $port,
        Config::PARAMS["db_user"], Config::PARAMS["db_pass"], array(
            MLPDO::ATTR_ERRMODE => MLPDO::ERRMODE_EXCEPTION,
            MLPDO::ATTR_PERSISTENT => true,
            // Si la base de datos no responde, fallar pronto en vez de acumular workers bloqueados.
            MLPDO::ATTR_TIMEOUT => 5,
        ));

    // Una conexion persistente puede venir de una peticion que murio a mitad de transaccion.
    if ($dbconn->inTransaction ()){
        $dbconn->rollBack ();
    }
    if (isset (Config::PARAMS["db_prefix"]))
        $dbconn->setPrefix (Config::PARAMS["db_prefix"]);
    else
        $dbconn->setPrefix ("");
    return $dbconn;
}
