<?php

$currentView = 'banco_horas';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$successMsg = '';
$errorMsg = '';

@db_exec($db_conn, "
    IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[consultoria].[tbl_banco_horas_acoes]') AND type in (N'U'))
    BEGIN
        CREATE TABLE [consultoria].[tbl_banco_horas_acoes] (
            matricula VARCHAR(50) PRIMARY KEY,
            data_queima DATE,
            atualizado_por VARCHAR(100),
            data_atualizacao DATETIME
        )
    END
");

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isAdmin = ($userCargo === 'administrador');
$isGerente = ($userCargo === 'gerente');
$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');

$travaRLS = "";

if ($isGerente) {
    $travaRLS = " AND V.gerente = '" . dashboard_escape_sql($userNomeOficial) . "' ";
} else if ($isCoordenador) {
    $travaRLS = " AND V.coordenador = '" . dashboard_escape_sql($userNomeOficial) . "' ";
} else if ($isSupervisor) {
    $travaRLS = " AND V.supervisor = '" . dashboard_escape_sql($userNomeOficial) . "' ";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar_queima'])) {
    if (!dashboard_csrf_validate()) {
        $errorMsg = 'Token de segurança inválido. Recarregue a página.';
    } else {
    $mat        = trim($_POST['matricula_agente']);
    $dataQueima = trim($_POST['data_queima']);
    $autor      = $user['nome'];
    $agora      = date('Y-m-d H:i:s');

    if (!empty($dataQueima)) {
        if (!validar_data($dataQueima)) {
            $errorMsg = 'Data de queima inválida.';
        } else {
            // Verifica se a matrícula já existe (Prepared Statement)
            $stmtCheck = db_prepare($db_conn, "SELECT matricula FROM consultoria.tbl_banco_horas_acoes WHERE matricula = ?");
            $resCheck = db_execute($stmtCheck, array($mat));
            
            if ($resCheck && db_fetch_row($stmtCheck)) {
                $stmtUpd = db_prepare($db_conn, 
                    "UPDATE consultoria.tbl_banco_horas_acoes 
                     SET data_queima = ?, atualizado_por = ?, data_atualizacao = ? 
                     WHERE matricula = ?");
                db_execute($stmtUpd, array($dataQueima, $autor, $agora, $mat));
            } else {
                $stmtIns = db_prepare($db_conn, 
                    "INSERT INTO consultoria.tbl_banco_horas_acoes 
                    (matricula, data_queima, atualizado_por, data_atualizacao) 
                    VALUES (?, ?, ?, ?)");
                db_execute($stmtIns, array($mat, $dataQueima, $autor, $agora));
            }
            $successMsg = "Data de queima agendada com sucesso!";
        }
    }
    } // fecha else do CSRF
}

$filtroGerente = isset($_GET['gerente']) ? dashboard_escape_sql($_GET['gerente']) : '';
$filtroCoord = isset($_GET['coordenador']) ? dashboard_escape_sql($_GET['coordenador']) : '';
$filtroSuper = isset($_GET['supervisor']) ? dashboard_escape_sql($_GET['supervisor']) : '';

if ($isGerente) { $filtroGerente = $userNomeOficial; }
if ($isCoordenador) { $filtroCoord = $userNomeOficial; $filtroGerente = ''; }
if ($isSupervisor) { $filtroSuper = $userNomeOficial; $filtroCoord = ''; $filtroGerente = ''; }

$gerentesValidos = "'SANDRA DE BRITO GOMES CUNHA', 'PATRICIA FERREIRA PINTO DE ABREU'";

// Construção Dinâmica dos Dropdowns
$listaGerentes = array();
if ($isAdmin) {
    $resG = @db_exec($db_conn, "SELECT DISTINCT gerente FROM consultoria.banco_horas_import WHERE gerente IS NOT NULL AND UPPER(RTRIM(LTRIM(gerente))) IN ($gerentesValidos) ORDER BY gerente");
    if ($resG) { while (db_fetch_row($resG)) { $listaGerentes[] = dashboard_utf8_encode(trim(db_result($resG, 'gerente'))); } }
}

$listaCoords = array();
if ($isAdmin || $isGerente) {
    $sqlC = "SELECT DISTINCT coordenador FROM consultoria.banco_horas_import WHERE coordenador IS NOT NULL AND UPPER(RTRIM(LTRIM(gerente))) IN ($gerentesValidos)";
    if (!empty($filtroGerente)) $sqlC .= " AND gerente = '$filtroGerente'";
    $sqlC .= " ORDER BY coordenador";
    $resC = @db_exec($db_conn, $sqlC);
    if ($resC) { while (db_fetch_row($resC)) { $listaCoords[] = dashboard_utf8_encode(trim(db_result($resC, 'coordenador'))); } }
}

$listaSupers = array();
if (!$isSupervisor) {
    $sqlS = "SELECT DISTINCT supervisor FROM consultoria.banco_horas_import WHERE supervisor IS NOT NULL AND UPPER(RTRIM(LTRIM(gerente))) IN ($gerentesValidos)";
    if (!empty($filtroGerente)) $sqlS .= " AND gerente = '$filtroGerente'";
    if (!empty($filtroCoord)) $sqlS .= " AND coordenador = '$filtroCoord'";
    $sqlS .= " ORDER BY supervisor";
    $resS = @db_exec($db_conn, $sqlS);
    if ($resS) { while (db_fetch_row($resS)) { $listaSupers[] = dashboard_utf8_encode(trim(db_result($resS, 'supervisor'))); } }
}


$sqlData = "SELECT MAX(CAST(data_importacao AS DATE)) as ultima FROM consultoria.banco_horas_import";
$resData = @db_exec($db_conn, $sqlData);
$ultimaDataImport = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d');

$whereClause = " UPPER(RTRIM(LTRIM(V.gerente))) IN ($gerentesValidos) 
                 AND CAST(V.data_importacao AS DATE) = '$ultimaDataImport' 
                 $travaRLS ";

if (!empty($filtroGerente) && !$isGerente) $whereClause .= " AND V.gerente = '$filtroGerente'";
if (!empty($filtroCoord) && !$isCoordenador) $whereClause .= " AND V.coordenador = '$filtroCoord'";
if (!empty($filtroSuper) && !$isSupervisor) $whereClause .= " AND V.supervisor = '$filtroSuper'";

// Repare que agora buscamos saldo_m2 e limite_m2
$sqlMain = "SELECT 
                V.matricula, V.nome, V.supervisor, V.coordenador, V.gerente, V.status_op, V.tipo_saldo, 
                V.saldo_m2, V.limite_m2,
                A.data_queima
            FROM consultoria.banco_horas_import V
            LEFT JOIN consultoria.tbl_banco_horas_acoes A ON V.matricula = A.matricula
            WHERE $whereClause";

$resMain = @db_exec($db_conn, $sqlMain);

$dadosTabela = array();
$kpiHorasTotais = 0;
$kpiHorasAtivos = 0;
$kpiHorasInativos = 0;
$qtdAtivos = 0;
$qtdInativos = 0;

if ($resMain) {
    while (db_fetch_row($resMain)) {
        
        // 3.1: Captura dos dados do M2
        $saldoM2Str = trim(db_result($resMain, 'saldo_m2'));
        $limiteM2   = trim(db_result($resMain, 'limite_m2'));
        $tipoSaldo  = trim(db_result($resMain, 'tipo_saldo')); // POS ou NEG
        $statusOp   = strtoupper(trim(db_result($resMain, 'status_op')));
        
        $saldoHorasDec = 0;
        
        // Converte 'HH:MM:SS' em Decimal Dinamicamente
        if (!empty($saldoM2Str) && strpos($saldoM2Str, ':') !== false) {
            $partes = explode(':', $saldoM2Str);
            $h = isset($partes[0]) ? (int)$partes[0] : 0;
            $m = isset($partes[1]) ? (int)$partes[1] : 0;
            $s = isset($partes[2]) ? (int)$partes[2] : 0;
            $saldoHorasDec = $h + ($m / 60) + ($s / 3600);
        }
        
        $saldoHorasInteiro = floor($saldoHorasDec);
        $saldoMinutos = round(($saldoHorasDec - $saldoHorasInteiro) * 60);
        
        // Badge (Crédito vs Débito)
        $badgeSaldo = ($tipoSaldo == 'NEG') 
                      ? '<span class="text-[8px] px-1.5 py-0.5 bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 rounded border border-red-200 dark:border-red-500/30 ml-2 font-black uppercase tracking-widest align-middle">Débito</span>' 
                      : '<span class="text-[8px] px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded border border-emerald-200 dark:border-emerald-500/30 ml-2 font-black uppercase tracking-widest align-middle">Crédito</span>';
        
        $strTempo = "{$saldoHorasInteiro}h " . sprintf('%02d', $saldoMinutos) . "m " . $badgeSaldo;
        
        // Ajuste para Ordenação: Débitos ou saldos 0 vão para o fundo
        $horasSort = ($tipoSaldo == 'NEG') ? -$saldoHorasDec : $saldoHorasDec;
        
        // 3.2: Definição Exata de Status
        $isActive = false;
        if (strpos($statusOp, 'ATIVO') !== false) {
            $isActive = true;
        }
        $obs = !empty($statusOp) ? $statusOp : 'N/A';
        
        // 3.3: Alimentar KPIs Globais (Somando as linhas do M2 ativamente)
        $kpiHorasTotais += $saldoHorasDec;

        if ($isActive) {
            $kpiHorasAtivos += $saldoHorasDec;
            $qtdAtivos++;
        } else {
            $kpiHorasInativos += $saldoHorasDec;
            $qtdInativos++;
        }

        $rawQueima = db_result($resMain, 'data_queima');
        $dataQueimaStr = !empty($rawQueima) ? trim($rawQueima) : '';

        $dadosTabela[] = array(
            'matricula' => trim(db_result($resMain, 'matricula')),
            'nome' => dashboard_utf8_encode(trim(db_result($resMain, 'nome'))),
            'supervisor' => dashboard_utf8_encode(trim(db_result($resMain, 'supervisor'))),
            'coord' => dashboard_utf8_encode(trim(db_result($resMain, 'coordenador'))),
            'gerente' => dashboard_utf8_encode(trim(db_result($resMain, 'gerente'))),
            'obs' => $obs,
            'is_active' => $isActive,
            'tipo_saldo' => $tipoSaldo,
            'tempo_str' => $strTempo,
            'limite_m2' => dashboard_utf8_encode($limiteM2), // Nova Informação
            'horas_sort' => $horasSort,
            'data_queima' => $dataQueimaStr
        );
    }
}

// Ordenação: Maior saldo positivo no topo
if (!function_exists('sortPorHorasBH')) {
    function sortPorHorasBH($a, $b) {
        if ($a['horas_sort'] == $b['horas_sort']) return 0;
        return ($a['horas_sort'] < $b['horas_sort']) ? 1 : -1;
    }
}
usort($dadosTabela, 'sortPorHorasBH');

?>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Banco de <span class="text-indigo-600 dark:text-indigo-400">Horas</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                Visão Focada no Ciclo Atual (Mês 2)
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="px-4 py-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl text-[10px] font-black uppercase tracking-widest border border-indigo-200 dark:border-indigo-500/20 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Base de: <?php echo date('d/m/Y', strtotime($ultimaDataImport)); ?>
            </span>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (!empty($successMsg)): ?>
        <div class="mb-8 p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 rounded-3xl text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center shadow-sm animate-in slide-in-from-top">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            <?php echo $successMsg; ?>
        </div>
    <?php endif; ?>

    <!-- Barra de Filtros de 4 Níveis -->
    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-10 transition-colors duration-500">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end" id="formFiltrosBH">
            <input type="hidden" name="route" value="banco_horas">
            
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Gerente</label>
                <?php if (!$isAdmin): ?>
                    <input type="text" readonly value="<?php echo $isGerente ? $filtroGerente : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <?php if ($isGerente): ?><input type="hidden" name="gerente" value="<?php echo $filtroGerente; ?>"><?php endif; ?>
                <?php else: ?>
                    <select name="gerente" onchange="document.getElementById('formFiltrosBH').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaGerentes as $g): ?>
                            <option value="<?php echo $g; ?>" <?php if($filtroGerente == $g) echo 'selected'; ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?><input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>"><?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formFiltrosBH').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaCoords as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if($filtroCoord == $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Supervisor</label>
                <?php if ($isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="supervisor" onchange="document.getElementById('formFiltrosBH').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaSupers as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if($filtroSuper == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <button type="submit" class="w-full px-6 py-4 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filtrar Operação
                </button>
            </div>
        </form>
    </div>

    <!-- SEÇÃO MACRO (Gráfico e KPIs da Soma Dinâmica M2) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
        
        <!-- Gráfico de Rosca (Ativo vs Inativo) -->
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[3rem] border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col justify-center items-center relative overflow-hidden">
            <h3 class="text-sm font-black italic text-slate-800 dark:text-white uppercase tracking-tighter w-full text-left mb-6">Passivo <span class="text-indigo-600 dark:text-indigo-400">Mês Atual</span></h3>
            
            <div class="relative w-48 h-48 mb-4">
                <?php if ($kpiHorasTotais == 0): ?>
                    <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-xs font-bold uppercase">S/ Dados</div>
                <?php else: ?>
                    <canvas id="chartPassivoBH"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo number_format($kpiHorasTotais, 0, ',', '.'); ?>h</span>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total M2</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="flex space-x-6 w-full justify-center mt-2">
                <div class="text-center">
                    <div class="flex items-center space-x-2 text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span> Ativo/Normal</div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo number_format($kpiHorasAtivos, 0, ',', '.'); ?>h</p>
                </div>
                <div class="text-center">
                    <div class="flex items-center space-x-2 text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1"><span class="w-2 h-2 bg-rose-500 rounded-full"></span> Sem Ação</div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo number_format($kpiHorasInativos, 0, ',', '.'); ?>h</p>
                </div>
            </div>
        </div>

        <!-- KPIs Cards -->
        <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Card Ativos -->
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-[3rem] p-10 text-white shadow-xl shadow-emerald-500/20 relative overflow-hidden flex flex-col justify-between hover:scale-[1.02] transition-transform">
                <div class="relative z-10 flex justify-between items-start">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <span class="px-4 py-1.5 bg-white/20 backdrop-blur-md rounded-xl text-[10px] font-black uppercase tracking-widest border border-white/30 text-white">Equipa em Operação</span>
                </div>
                <div class="relative z-10 mt-8">
                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-100 mb-1">Volume do Mês Atual (M2)</p>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-6xl font-black tracking-tighter italic"><?php echo number_format($kpiHorasAtivos, 0, ',', '.'); ?></span>
                        <span class="text-xl font-bold opacity-80">Horas</span>
                    </div>
                    <p class="text-xs font-medium text-emerald-50 mt-2">Referente a <?php echo $qtdAtivos; ?> operadores ativos</p>
                </div>
                <div class="absolute -right-10 -bottom-10 opacity-10">
                    <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
            </div>

            <!-- Card Inativos -->
            <div class="bg-gradient-to-br from-slate-800 to-[#020617] dark:border dark:border-slate-800 rounded-[3rem] p-10 text-white shadow-xl relative overflow-hidden flex flex-col justify-between hover:scale-[1.02] transition-transform">
                <div class="relative z-10 flex justify-between items-start">
                    <div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center">
                        <svg class="w-7 h-7 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                    </div>
                    <span class="px-4 py-1.5 bg-rose-500/20 backdrop-blur-md rounded-xl text-[10px] font-black uppercase tracking-widest border border-rose-500/30 text-rose-300">Afastados/Desligados</span>
                </div>
                <div class="relative z-10 mt-8">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Horas Congeladas / Sem Ação</p>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-6xl font-black tracking-tighter italic text-rose-400"><?php echo number_format($kpiHorasInativos, 0, ',', '.'); ?></span>
                        <span class="text-xl font-bold opacity-50">Horas</span>
                    </div>
                    <p class="text-xs font-medium text-slate-500 mt-2">Referente a <?php echo $qtdInativos; ?> ex-operadores</p>
                </div>
            </div>
        </div>
    </div>

    <!-- TABELA DE DETALHES E PREVISÃO -->
    <div class="bg-white dark:bg-[#0f172a] rounded-[3rem] border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden transition-colors duration-500">
        <div class="p-8 border-b border-slate-50 dark:border-slate-800/50 flex items-center space-x-3 bg-slate-50/50 dark:bg-slate-800/20">
            <span class="text-2xl">🗓️</span>
            <h3 class="text-xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">Previsão de <span class="text-indigo-600 dark:text-indigo-400">Queima (M2)</span></h3>
            <span class="ml-4 px-3 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg text-[9px] font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20">Ação da Liderança</span>
        </div>

        <div class="overflow-x-auto max-h-[600px] custom-scroll">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-20">
                    <tr class="bg-slate-100 dark:bg-slate-800 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest border-b border-slate-200 dark:border-slate-700">
                        <th class="px-8 py-5">Operador</th>
                        <th class="px-8 py-5">Hierarquia</th>
                        <th class="px-8 py-5 text-center">Status Op.</th>
                        <th class="px-8 py-5 text-center">Saldo M2</th>
                        <th class="px-8 py-5 text-right w-[300px]">Previsão de Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                    <?php if(empty($dadosTabela)): ?>
                        <tr><td colspan="5" class="py-20 text-center text-slate-400 font-bold uppercase text-xs italic">Nenhum registo encontrado.</td></tr>
                    <?php else: foreach($dadosTabela as $row): 
                        $statusBadge = $row['is_active'] ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20' : 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20';
                    ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-8 py-5">
                                <p class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight truncate w-48"><?php echo $row['nome']; ?></p>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 truncate">Mat: <?php echo $row['matricula']; ?></p>
                            </td>
                            <td class="px-8 py-5">
                                <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest truncate w-48">Sup: <?php echo $row['supervisor']; ?></p>
                                <p class="text-[9px] font-bold text-slate-400 opacity-80 uppercase tracking-widest mt-0.5 truncate w-48">Coord: <?php echo $row['coord']; ?></p>
                                <?php if($isAdmin || $isGerente): ?>
                                    <p class="text-[8px] font-bold text-slate-400 opacity-50 uppercase tracking-widest mt-0.5 truncate w-48">Ger: <?php echo $row['gerente']; ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-sm <?php echo $statusBadge; ?>">
                                    <?php echo $row['obs']; ?>
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="inline-block text-center">
                                    <span class="text-lg font-black italic tracking-tighter <?php echo $row['horas_sort'] > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'; ?>">
                                        <?php echo $row['tempo_str']; ?>
                                    </span>
                                    <?php if(!empty($row['limite_m2'])): ?>
                                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1 bg-slate-50 dark:bg-slate-800/50 rounded-md py-0.5">Vence: <?php echo $row['limite_m2']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <?php if($row['is_active'] && $row['tipo_saldo'] !== 'NEG' && $row['horas_sort'] > 0): ?>
                                    <!-- Ação Visível (Só para Ativos com Saldo Positivo em Crédito) -->
                                    <form method="POST" action="index.php?route=banco_horas" class="flex items-center justify-end space-x-2">
                                        <input type="hidden" name="matricula_agente" value="<?php echo $row['matricula']; ?>">
                                        
                                        <!-- Se já houver data salva, mostra verde -->
                                        <div class="relative">
                                            <input type="date" name="data_queima" value="<?php echo $row['data_queima']; ?>" required
                                                class="bg-slate-50 dark:bg-slate-900 border <?php echo !empty($row['data_queima']) ? 'border-emerald-300 dark:border-emerald-500/50 text-emerald-700 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'; ?> rounded-xl px-4 py-2 text-xs font-bold outline-none focus:border-indigo-500 transition-colors">
                                            <?php if(!empty($row['data_queima'])): ?>
                                                <span class="absolute -top-2 -right-2 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white dark:border-[#0f172a]"></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="submit" name="agendar_queima" class="p-2.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 rounded-xl transition-all shadow-sm border border-indigo-100 dark:border-indigo-500/20" title="Salvar Agendamento">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-right">
                                        <span class="text-[9px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-widest">
                                            <?php echo $row['tipo_saldo'] === 'NEG' ? 'Em Débito' : 'Sem Ação'; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Lógica do Gráfico de Rosca -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    
    // Injectar Dados do PHP no JS
    const chartData = [<?php echo $kpiHorasAtivos; ?>, <?php echo $kpiHorasInativos; ?>];
    const ctx = document.getElementById('chartPassivoBH');
    
    if (ctx && chartData[0] + chartData[1] > 0) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Horas Ativas', 'Horas Sem Ação'],
                datasets: [{
                    data: chartData,
                    backgroundColor: ['#10b981', '#f43f5e'], // Emerald e Rose
                    borderWidth: isDark ? 4 : 2,
                    borderColor: isDark ? '#0f172a' : '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        titleColor: isDark ? '#ffffff' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + Math.round(context.raw) + 'h';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'shared/ui/footer.php'; ?>