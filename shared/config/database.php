<?php
require_once dirname(__FILE__) . '/helpers.php';
require_once dirname(__FILE__) . '/db_wrapper.php';
$_config_local = dirname(dirname(dirname(__FILE__))) . '/config.local.php';
if (!file_exists($_config_local)) {
    die('<h2>Erro Crítico: Arquivo config.local.php não encontrado.</h2>'
      . '<p>Copie <strong>config.local.example.php</strong> para <strong>config.local.php</strong> '
      . 'e preencha as credenciais do banco de dados.</p>');
}
require_once $_config_local;
$stringConexao = "DRIVER={SQL Server};SERVER=" . DB_SERVER
               . ";DATABASE=" . DB_NAME
               . ";UID=" . DB_USER
               . ";PWD=" . DB_PASS . ";";

$db_conn = @db_connect($stringConexao, DB_USER, DB_PASS);

if (!$db_conn) {
    error_log('[DASHBOARD360] Falha na conexão ODBC: ' . db_errormsg());
    die('<h2>Erro Crítico: Não foi possível ligar à Base de Dados da Operação.</h2>'
      . '<p>Por favor, contacte o administrador do sistema.</p>');
}
?>