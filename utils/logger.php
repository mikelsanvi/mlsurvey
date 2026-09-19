<?php
require_once 'include/config.php';
//0: error, 1: warn, 2: info, 3: debug
const LOGGER_ERROR = 0;
const LOGGER_WARN = 1;
const LOGGER_INFO = 2;
const LOGGER_DEBUG = 3;
const LEVEL_DESC = ['ERROR: ', 'WARN: ', 'INFO: ', 'DEBUG: '];

function logMessage ($severity, $message){
    $level = 0;
    if (isset (Config::PARAMS["log_level"]))
        $level = Config::PARAMS["log_level"];
    
    if ($level < $severity)
        return;

    error_log (LEVEL_DESC[$severity] . $message);
    return;
}