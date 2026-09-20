<?php
require_once "config/config.php";

class Config {
    public const PARAMS = CONFIG;
    private const MANDATORY = [
        "db_host",
        "db_name",
        "db_user",
        "db_pass",
        "email_method"
    ];
}