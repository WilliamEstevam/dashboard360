<?php
/**
 * Arquivo: security.php
 * Camada: Shared / Config
 * Descrição: Funções centralizadas de segurança — compatível com PHP 5.2+
 */

/**
 * Escaping seguro para uso em queries SQL concatenadas (SQL Server / ODBC).
 * Remove null bytes, caracteres de controle e faz escaping de aspas simples.
 * Para INSERT/UPDATE/DELETE, prefira sempre db_prepare() + db_execute().
 *
 * @param string $str
 * @return string
 */
function dashboard_escape_sql($str) {
    if ($str === null || $str === '') {
        return '';
    }
    // Remove null bytes que podem truncar strings
    $str = str_replace(chr(0), '', $str);
    // Remove caracteres de controle (0x01-0x1F exceto tab/newline)
    $cleaned = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($str[$i]);
        if ($ord >= 32 || $ord == 9 || $ord == 10 || $ord == 13) {
            $cleaned .= $str[$i];
        }
    }
    // Escaping de aspas simples (padrão SQL Server)
    $cleaned = str_replace("'", "''", $cleaned);
    return $cleaned;
}

/**
 * Inicia sessão com configurações de segurança (PHP 5.2 compatível).
 */
function dashboard_secure_session_start() {
    if (headers_sent()) {
        return;
    }
    // Cookies apenas via HTTP (não acessíveis via JavaScript)
    ini_set('session.cookie_httponly', 1);
    // Usar apenas cookies para propagação de sessão (sem URL)
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    // Se estiver em HTTPS, marcar cookie como secure
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Envia headers de segurança HTTP.
 * Deve ser chamada antes de qualquer saída HTML.
 */
function dashboard_security_headers() {
    if (headers_sent()) {
        return;
    }
    // Impede que o browser faça "sniffing" de MIME type
    header('X-Content-Type-Options: nosniff');
    // Impede que a página seja carregada em iframe (proteção contra clickjacking)
    header('X-Frame-Options: SAMEORIGIN');
    // Ativa filtro XSS do navegador
    header('X-XSS-Protection: 1; mode=block');
    // Cache control para páginas autenticadas
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/**
 * Gera um token CSRF e armazena na sessão.
 * Compatível com PHP 5.2 (sem random_bytes).
 *
 * @return string Token CSRF
 */
function dashboard_csrf_token() {
    if (!isset($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = md5(uniqid(mt_rand(), true) . microtime(true) . serialize($_SERVER));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Retorna um campo HTML hidden com o token CSRF.
 *
 * @return string HTML input hidden
 */
function dashboard_csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . dashboard_csrf_token() . '">';
}

/**
 * Valida o token CSRF enviado via POST.
 * Usa comparação em tempo constante para prevenir timing attacks.
 *
 * @return bool
 */
function dashboard_csrf_validate() {
    if (!isset($_POST['_csrf_token']) || !isset($_SESSION['_csrf_token'])) {
        return false;
    }
    $submitted = $_POST['_csrf_token'];
    $stored = $_SESSION['_csrf_token'];
    if (strlen($submitted) !== strlen($stored)) {
        return false;
    }
    $diff = 0;
    for ($i = 0; $i < strlen($submitted); $i++) {
        $diff |= ord($submitted[$i]) ^ ord($stored[$i]);
    }
    // Regenera token após validação (single-use)
    unset($_SESSION['_csrf_token']);
    return $diff === 0;
}

/**
 * Valida um arquivo de upload.
 *
 * @param array  $file         Elemento de $_FILES (ex: $_FILES['foto'])
 * @param int    $maxSizeBytes Tamanho máximo em bytes
 * @param array  $allowedExts  Array de extensões permitidas (sem ponto)
 * @return array Array com 'valid' (bool) e 'error' (string)
 */
function dashboard_validate_upload($file, $maxSizeBytes, $allowedExts) {
    $result = array('valid' => false, 'error' => '');

    // Verificar se houve erro no upload
    if (!isset($file['error']) || $file['error'] !== 0) {
        $result['error'] = 'Erro no upload do arquivo.';
        return $result;
    }

    // Verificar tamanho
    if ($file['size'] > $maxSizeBytes) {
        $sizeMB = round($maxSizeBytes / (1024 * 1024), 1);
        $result['error'] = 'O arquivo excede o tamanho máximo permitido (' . $sizeMB . 'MB).';
        return $result;
    }

    // Verificar extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $result['error'] = 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', $allowedExts) . '.';
        return $result;
    }

    // Verificar MIME type real via getimagesize (para imagens)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $result['error'] = 'O arquivo enviado não é uma imagem válida.';
        return $result;
    }

    // Verificar se o MIME type é de imagem conhecida
    $allowedMimes = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    );
    if (!in_array($imageInfo['mime'], $allowedMimes)) {
        $result['error'] = 'Tipo MIME do arquivo não é permitido.';
        return $result;
    }

    $result['valid'] = true;
    return $result;
}

/**
 * Gera um nome de arquivo seguro e aleatório para uploads.
 *
 * @param string $prefix  Prefixo do nome
 * @param string $ext     Extensão (sem ponto)
 * @return string Nome do arquivo
 */
function dashboard_safe_filename($prefix, $ext) {
    return $prefix . '_' . md5(uniqid(mt_rand(), true)) . '.' . $ext;
}

/**
 * Executa o logout de forma segura.
 */
function dashboard_secure_logout() {
    // Limpar todas as variáveis de sessão
    $_SESSION = array();

    // Destruir o cookie de sessão
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destruir a sessão
    session_destroy();
}
?>
