<?php

require_once dirname(__FILE__) . '/security.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function dashboard_utf8_encode($str) {
    if (is_string($str) && @preg_match('//u', $str)) {
        return $str;
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
    return @\utf8_encode($str);
}

// DASHBOARD_PEPPER deve ser definido em config.local.php
// Fallback de compatibilidade se não estiver definido
if (!defined('DASHBOARD_PEPPER')) {
    define('DASHBOARD_PEPPER', 'T4hto360_secure_pepper_2026');
}

function dashboard_gerar_salt() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 16);
}

function dashboard_hash($salt, $senha) {
    return hash('sha256', DASHBOARD_PEPPER . $salt . $senha);
}

function dashboard_verify($senha, $hash_armazenado, $salt) {
    $hash_calculado = dashboard_hash($salt, $senha);
    if (strlen($hash_calculado) !== strlen($hash_armazenado)) {
        return false;
    }
    $diff = 0;
    for ($i = 0; $i < strlen($hash_calculado); $i++) {
        $diff |= ord($hash_calculado[$i]) ^ ord($hash_armazenado[$i]);
    }
    return $diff === 0;
}

function validar_data($data) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return false;
    }
    $partes = explode('-', $data);
    return checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0]);
}

function log_erro($contexto, $detalhe) {
    error_log('[DASHBOARD360][ERRO] ' . $contexto . ': ' . $detalhe);
    return 'Ocorreu um erro interno. Por favor, tente novamente ou contacte o suporte.';
}
?>
