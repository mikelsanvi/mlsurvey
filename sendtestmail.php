<?php
require_once 'include/config.php';
require_once 'include/mlmailer.php';

if ($argc < 2){
    echo "Sintaxis: sendtestmail.php 'to''\n";
    return 1;
}

$to = $argv[1];
$mailer = new MLMailer ();
$mailer->configure ();
$mailer->sendTest ($to);