<?php
/**
 * Arquivo: db_wrapper.php
 * Descrição: Camada de abstração do banco de dados (ODBC). 
 * Se USE_MOCK_DATA estiver ativado, retorna dados fictícios em vez de conectar ao SQL Server.
 */

// Armazenar os resultados mockados para o fetch
$GLOBALS['mock_results'] = array();
$GLOBALS['mock_pointer'] = 0;

function is_mock_mode() {
    return defined('USE_MOCK_DATA') && USE_MOCK_DATA;
}

function db_connect($string, $user, $pass) {
    if (is_mock_mode()) {
        return 'mock_connection';
    }
    return @odbc_connect($string, $user, $pass);
}

function db_exec($conn, $sql) {
    if (is_mock_mode()) {
        return _mock_query_handler($sql);
    }
    return @odbc_exec($conn, $sql);
}

function db_prepare($conn, $sql) {
    if (is_mock_mode()) {
        // Retorna o SQL como string para que db_execute() possa processá-lo
        return 'MOCK_STMT|' . $sql;
    }
    return @odbc_prepare($conn, $sql);
}

function db_execute($stmt, $params = array()) {
    if (is_mock_mode()) {
        if (strpos($stmt, 'MOCK_STMT|') === 0) {
            $sql = substr($stmt, 10);
            $GLOBALS['mock_current_result'] = _mock_query_handler($sql, $params);
            return true;
        }
        return true;
    }
    return @odbc_execute($stmt, $params);
}

function db_fetch_row($res) {
    if (is_mock_mode()) {
        $data = is_array($res) ? $res : (isset($GLOBALS['mock_current_result']) ? $GLOBALS['mock_current_result'] : array());
        if (is_array($data) && $GLOBALS['mock_pointer'] < count($data)) {
            $GLOBALS['current_mock_row'] = $data[$GLOBALS['mock_pointer']];
            $GLOBALS['mock_pointer']++;
            return true;
        }
        return false;
    }
    return @odbc_fetch_row($res);
}

function db_result($res, $field) {
    if (is_mock_mode()) {
        if (isset($GLOBALS['current_mock_row'][$field])) {
            return $GLOBALS['current_mock_row'][$field];
        }
        // Tentativa de lowercase / uppercase dependendo da query
        $lowerField = strtolower($field);
        if (isset($GLOBALS['current_mock_row'][$lowerField])) {
            return $GLOBALS['current_mock_row'][$lowerField];
        }
        return null;
    }
    return @odbc_result($res, $field);
}

function db_errormsg($conn = null) {
    if (is_mock_mode()) {
        return '';
    }
    return $conn ? @odbc_errormsg($conn) : @odbc_errormsg();
}

/**
 * Função interna para interceptar consultas e retornar dados falsos (Mock).
 * Esta é uma implementação básica. Para estender, adicione os padrões de string SQL aqui.
 */
function _mock_query_handler($sql, $params = array()) {
    $GLOBALS['mock_pointer'] = 0;
    $sql_lower = strtolower(trim($sql));

    // Mock para Login e Perfil (Tabela de Usuários)
    if (strpos($sql_lower, 'tbl_usuarios_v2') !== false && strpos($sql_lower, 'select') !== false && (strpos($sql_lower, 'where email =') !== false || strpos($sql_lower, 'where id =') !== false)) {
        $identificador = isset($params[0]) ? $params[0] : 'admin@dashboard.com';
        // Se a query for por ID, o identificador vai ser o ID (no caso, 1), ignoramos e devolvemos o mock fixo
        return array(
            array(
                'id' => 1,
                'email' => 'admin@dashboard.com',
                'nome_completo' => 'Admin Mockado',
                'senha' => '123456', 
                'senha_hash' => '', 
                'salt' => '',
                'cargo' => 'supervisor',
                'precisa_mudar_senha' => 0,
                'foto_path' => '',
                'matricula' => '123456'
            )
        );
    }
    
    // Mock para Monitor WFM (Pausas) e Banheiro e Absenteísmo
    if (strpos($sql_lower, 'tbl_temp_estado_atual') !== false && strpos($sql_lower, 'select') !== false) {
        return array(
            array('matricula' => '1001', 'nome_operador' => 'João Silva', 'supervisor' => 'Carlos M.', 'status_real' => 'LANCHE', 'tempo_status' => '00:15:20', 'foto_path' => '', 'estado_setor' => 'Operações', 'uf_setor' => 'SP', 'status_planejado' => 'LOGADO'),
            array('matricula' => '1002', 'nome_operador' => 'Maria Souza', 'supervisor' => 'Ana P.', 'status_real' => 'BANHEIRO', 'tempo_status' => '00:11:05', 'foto_path' => '', 'estado_setor' => 'Operações', 'uf_setor' => 'RJ', 'status_planejado' => 'LOGADO'),
            array('matricula' => '1003', 'nome_operador' => 'Pedro Santos', 'supervisor' => 'Carlos M.', 'status_real' => 'LANCHE', 'tempo_status' => '00:32:10', 'foto_path' => '', 'estado_setor' => 'Operações', 'uf_setor' => 'MG', 'status_planejado' => 'LOGADO') // Excedida
        );
    }
    
    // Mock para Dashboard - Data
    if (strpos($sql_lower, 'select max(cast(data_referencia') !== false) {
        return array(array('ultima' => date('Y-m-d', strtotime('-1 day'))));
    }
    
    // Mock para Tempo Logado e Pausas Consolidadas
    if (strpos($sql_lower, 'tbl_perm_tempo_produtivo') !== false && strpos($sql_lower, 'select') !== false && strpos($sql_lower, 'max(') === false && strpos($sql_lower, 'distinct') === false) {
        return array(
            array('NOME_AGENTE' => 'Ana Paula Duchini', 'MATRICULA' => '2001', 'SUPERVISOR' => 'Rodrigo Miranda', 'COORDENADOR' => 'Geraldo', 'perc_logado' => '1,00', 'PERC_PAGO' => '1,00', 'ATIVIDADE' => 'LANCHE', 'TEMPO_SEGUNDOS' => 1500),
            array('NOME_AGENTE' => 'Dayane Farias', 'MATRICULA' => '2002', 'SUPERVISOR' => 'Pablo Matheus', 'COORDENADOR' => 'Geraldo', 'perc_logado' => '1,00', 'PERC_PAGO' => '0,95', 'ATIVIDADE' => 'BANHEIRO', 'TEMPO_SEGUNDOS' => 700),
            array('NOME_AGENTE' => 'Lucas Santos', 'MATRICULA' => '2003', 'SUPERVISOR' => 'Rodrigo Miranda', 'COORDENADOR' => 'Geraldo', 'perc_logado' => '0,65', 'PERC_PAGO' => '0,70', 'ATIVIDADE' => 'LANCHE', 'TEMPO_SEGUNDOS' => 2000), // Estourou lanche (limite 1800s)
            array('NOME_AGENTE' => 'Mariana Oliveira', 'MATRICULA' => '2004', 'SUPERVISOR' => 'Pablo Matheus', 'COORDENADOR' => 'Geraldo', 'perc_logado' => '0,40', 'PERC_PAGO' => '0,45', 'ATIVIDADE' => 'FEEDBACK', 'TEMPO_SEGUNDOS' => 2100), // Estourou feedback (limite 1800s)
            array('NOME_AGENTE' => 'João Silva', 'MATRICULA' => '1001', 'SUPERVISOR' => 'Carlos M.', 'COORDENADOR' => 'Geraldo', 'perc_logado' => '0,85', 'PERC_PAGO' => '0,90', 'ATIVIDADE' => 'LANCHE', 'TEMPO_SEGUNDOS' => 1800)
        );
    }
    
    // Mock para Relatório TU - Ranking
    if (strpos($sql_lower, 'group by matricula, nome_agente, supervisor_quadro') !== false) {
        return array(
            array('MATRICULA' => '1001', 'NOME_AGENTE' => 'João Silva', 'SUPERVISOR_QUADRO' => 'Carlos M.', 'total' => 120, 'retidos' => 80, 'cancelados' => 15),
            array('MATRICULA' => '1002', 'NOME_AGENTE' => 'Maria Souza', 'SUPERVISOR_QUADRO' => 'Ana P.', 'total' => 110, 'retidos' => 75, 'cancelados' => 20),
            array('MATRICULA' => '1003', 'NOME_AGENTE' => 'Pedro Santos', 'SUPERVISOR_QUADRO' => 'Carlos M.', 'total' => 100, 'retidos' => 60, 'cancelados' => 25)
        );
    }

    // Mock para Dashboard - KPIs Principais
    if (strpos($sql_lower, 'count(*) as total') !== false && strpos($sql_lower, 'retidos') !== false && strpos($sql_lower, 'cancelados') !== false && strpos($sql_lower, 'group by') === false) {
        return array(array('total' => 1250, 'retidos' => 850, 'cancelados' => 150));
    }
    
    // Mock para Dashboard - KPI D-2
    if (strpos($sql_lower, 'count(*) as total') !== false && strpos($sql_lower, 'retidos') !== false && strpos($sql_lower, 'cancelados') === false && strpos($sql_lower, 'group by') === false) {
        return array(array('total' => 1100, 'retidos' => 700));
    }
    
    // Mock para Dashboard - TOP UF
    if (strpos($sql_lower, 'top 1 uf') !== false) {
        return array(array('UF' => 'SP', 'qtd' => 450));
    }
    
    // Mock para Dashboard - Evolução Diária (Trend)
    if (strpos($sql_lower, 'top 15') !== false && strpos($sql_lower, 'group by cast(data_referencia') !== false) {
        $trend = array();
        for ($i=0; $i<15; $i++) {
            $trend[] = array(
                'data_ref' => date('Y-m-d', strtotime("-".($i+1)." days")),
                'total' => rand(1000, 1500),
                'retidos' => rand(600, 900)
            );
        }
        return $trend;
    }
    
    // Mock para Dashboard - Desfechos N4
    if (strpos($sql_lower, 'top 4 hierarquia_n4') !== false) {
        return array(
            array('HIERARQUIA_N4' => 'Retido Reversão', 'qtd' => 500),
            array('HIERARQUIA_N4' => 'Cancelamento Desistência', 'qtd' => 200),
            array('HIERARQUIA_N4' => 'Retido Negociação', 'qtd' => 350),
            array('HIERARQUIA_N4' => 'Outros', 'qtd' => 200)
        );
    }
    
    // Mock para Dashboard - Top Operadores
    if (strpos($sql_lower, 'top 4 nome_agente') !== false) {
        return array(
            array('NOME_AGENTE' => 'João Silva', 'SUPERVISOR_QUADRO' => 'Carlos M.', 'retidos' => 45, 'total' => 50),
            array('NOME_AGENTE' => 'Maria Souza', 'SUPERVISOR_QUADRO' => 'Ana P.', 'retidos' => 42, 'total' => 48),
            array('NOME_AGENTE' => 'Pedro Santos', 'SUPERVISOR_QUADRO' => 'Carlos M.', 'retidos' => 38, 'total' => 40),
            array('NOME_AGENTE' => 'Lucas Lima', 'SUPERVISOR_QUADRO' => 'Ana P.', 'retidos' => 35, 'total' => 45)
        );
    }

    // Mock genérico para outras consultas
    return array();
}
?>
