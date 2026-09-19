<?php

function getURL (){
    $server = rtrim ($_SERVER['HTTP_HOST'], "/");
    if (!empty (Config::PARAMS["proxy_port"])){
         $server .= ":" . Config::PARAMS["proxy_port"] . "/";
    }
    else{
         $server .= "/";
    }
    return (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
         $server . Config::PARAMS["proxy_path"];
}
