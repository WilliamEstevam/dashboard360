<?php
/**
 * Arquivo: config.local.example.php
 * Descrição: TEMPLATE de credenciais do banco de dados.
 *
 * INSTRUÇÕES:
 *   1. Copie este arquivo para config.local.php na mesma pasta.
 *   2. Preencha os valores reais em config.local.php.
 *   3. NUNCA commite config.local.php no repositório Git.
 *      O .gitignore já está configurado para ignorá-lo.
 */

define('DB_SERVER', 'SEU_SERVIDOR,SUA_PORTA');  // ex: SQLPW52, 1440
define('DB_USER',   'SEU_USUARIO_SQL');
define('DB_PASS',   'SUA_SENHA_SQL');
define('DB_NAME',   'SEU_BANCO_DE_DADOS');

// Pepper criptográfico para hash de senhas — altere para um valor único e secreto
define('DASHBOARD_PEPPER', 'ALTERE_ESTE_VALOR_PARA_UM_PEPPER_SECRETO');
?>
