<?php

function encrypt (#[\SensitiveParameter]string $data, #[\SensitiveParameter]string $key){
    $method = "AES-256-ECB"; //Using ECB is secure as $key is random.
    $encrypted = openssl_encrypt ($data, $method, $key);
    if ($encrypted === false){
        throw new Exception("Error when encrypting: " . openssl_error_string());
        return false;
    }
    return $encrypted;
}

function decrypt (#[\SensitiveParameter]string $encrypted, #[\SensitiveParameter]string $key){
    $method = "AES-256-ECB"; //Using ECB is secure as $key is random.
    $data = openssl_decrypt ($encrypted, $method, $key);
    if ($data === false){
        throw new Exception("Error when decrypting: " . openssl_error_string());
        return false;
    }
    return $data;
}

function url_base64_encode (#[\SensitiveParameter] string $data):string {
    $encoded = strtr(base64_encode($data), '+=/', '-_.');
    //return rtrim($encoded, '=');
    return $encoded;
}

function url_base64_decode(#[\SensitiveParameter] string $data): string {
    $decoded = base64_decode(strtr($data, '-_.', '+=/'), true);
    if (false === $decoded) {
        throw new InvalidArgumentException('url_base64 mal formado.');
    }
    return $decoded;
}