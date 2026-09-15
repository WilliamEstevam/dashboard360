<?php
$currentView = 'pausas_consolidadas';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$successMsg = '';
$errorMsg = '';

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');
$isGestao = in_array($userCargo, array('administrador', 'gerente', 'coordenador'));

$travaRLS = "";

if ($isCoordenador) {
    $travaRLS = " AND COORDENADOR = '" . dashboard_escape_sql($userNomeOficial) . "' ";
} else if ($isSupervisor) {
    $travaRLS = " AND SUPERVISOR = '" . dashboard_escape_sql($userNomeOficial) . "' ";
}

$viewModeDefault = $isSupervisor ? 'supervisor' : 'gestao';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_justificativa'])) {

    if (!dashboard_csrf_validate()) {
        $errorMsg = 'Token de segurança inválido. Recarregue a página.';
    } else {

    $mat           = trim($_POST['mat_agente']);
    $dataRef       = trim($_POST['data_ref_agente']);
    $atividade     = trim($_POST['atividade_agente']);
    $motivo        = trim($_POST['motivo']);
    $obs           = trim($_POST['observacao']);
    $autor         = $user['nome'];
    $dataHoraAtual = date('Y-m-d H:i:s');

    if (!validar_data($dataRef)) {
        $errorMsg = 'Data de referência inválida.';
    } else {
        $checkTable = @db_exec($db_conn, 'SELECT 1 FROM consultoria.tbl_justificativas_pausas WHERE 1=0');
        if ($checkTable !== false) {
            $stmtDel = db_prepare($db_conn,
                'DELETE FROM consultoria.tbl_justificativas_pausas
                 WHERE matricula = ? AND data_ref = ? AND atividade = ?');
            db_execute($stmtDel, array($mat, $dataRef, $atividade));

            $stmtIns = db_prepare($db_conn,
                'INSERT INTO consultoria.tbl_justificativas_pausas
                    (matricula, data_ref, atividade, motivo, observacao, justificado_por, data_justificativa)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            if (db_execute($stmtIns, array($mat, $dataRef, $atividade, $motivo, $obs, $autor, $dataHoraAtual))) {
                $successMsg = 'A pausa foi justificada com sucesso!';
            } else {
                $errorMsg = log_erro('PausasConsolidadas INSERT', db_errormsg($db_conn));
            }
        } else {
            $errorMsg = "Atenção (TI): A tabela 'consultoria.tbl_justificativas_pausas' não existe na base de dados. Por favor, crie-a para habilitar esta função.";
        }
    }
    }
}
$sqlData = "SELECT MAX(CAST(DATA_REFERENCIA AS DATE)) as ultima FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO";
$resData = @db_exec($db_conn, $sqlData);
$ultimaData = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d', strtotime('-1 day'));

$filtroData  = isset($_GET['data_ref']) ? $_GET['data_ref'] : $ultimaData;
if (!validar_data($filtroData)) $filtroData = $ultimaData;
$filtroCoord     = isset($_GET['coordenador'])  ? dashboard_escape_sql($_GET['coordenador'])  : '';
$filtroSuper     = isset($_GET['supervisor'])   ? dashboard_escape_sql($_GET['supervisor'])   : '';
$filtroTipoPausa = isset($_GET['tipo_pausa'])   ? dashboard_escape_sql($_GET['tipo_pausa'])   : '';
$filtroStatus    = isset($_GET['status_pausa']) ? $_GET['status_pausa']  : 'TODAS';
$filtroJust      = isset($_GET['justificativa'])? $_GET['justificativa'] : 'TODAS';

if ($isCoordenador) { $filtroCoord = $userNomeOficial; }
if ($isSupervisor) { $filtroSuper = $userNomeOficial; $filtroCoord = ''; }

$lixoStr = "'TOTAL GERAL', 'LOGADO', 'TEMPO LOGADO', 'PRODUTIVO', 'TEMPO PRODUTIVO', 'ATENDIMENTO', 'DISPONIVEL', 'DISPONÍVEL', 'OCUPADO', 'FALANDO', 'ESPERA'";

$listaCoords = array();
if (!$isCoordenador && !$isSupervisor) {
    $sqlC = "SELECT DISTINCT COORDENADOR FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO WHERE COORDENADOR IS NOT NULL ORDER BY COORDENADOR";
    $resC = @db_exec($db_conn, $sqlC);
    if ($resC) { while (db_fetch_row($resC)) { $listaCoords[] = dashboard_utf8_encode(trim(db_result($resC, 'COORDENADOR'))); } }
}

$listaSupers = array();
if (!$isSupervisor) {
    $sqlS = "SELECT DISTINCT SUPERVISOR FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO WHERE SUPERVISOR IS NOT NULL";
    if (!empty($filtroCoord)) $sqlS .= " AND COORDENADOR = '$filtroCoord'";
    $sqlS .= " ORDER BY SUPERVISOR";
    $resS = @db_exec($db_conn, $sqlS);
    if ($resS) { while (db_fetch_row($resS)) { $listaSupers[] = dashboard_utf8_encode(trim(db_result($resS, 'SUPERVISOR'))); } }
    if (!empty($filtroSuper) && !in_array($filtroSuper, $listaSupers)) { $filtroSuper = ''; }
}

$listaTiposPausa = array();
$sqlTipos = "SELECT DISTINCT ATIVIDADE FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO WHERE ATIVIDADE IS NOT NULL AND UPPER(RTRIM(LTRIM(ATIVIDADE))) NOT IN ($lixoStr) ORDER BY ATIVIDADE";
$resTipos = @db_exec($db_conn, $sqlTipos);
if ($resTipos) {
    while (db_fetch_row($resTipos)) {
        $listaTiposPausa[] = dashboard_utf8_encode(trim(db_result($resTipos, 'ATIVIDADE')));
    }
}

$limites = array('LANCHE' => 20, 'BANHEIRO' => 10, 'DESCANSO' => 10, 'REFEICAO' => 60, 'FEEDBACK' => 30, 'DEFEITO' => 30);
$sqlLims = "SELECT chave, valor FROM consultoria.TBL_TEMP_parametros_metas WHERE categoria = 'pausas' OR chave LIKE 'limite_%'";
$resLims = @db_exec($db_conn, $sqlLims);
if ($resLims) {
    while (db_fetch_row($resLims)) {
        $ch = strtolower(trim(db_result($resLims, 'chave')));
        $vl = (int)db_result($resLims, 'valor');
        if ($ch === 'limite_lanche') $limites['LANCHE'] = $vl;
        if ($ch === 'limite_banheiro') $limites['BANHEIRO'] = $vl;
        if ($ch === 'limite_descanso') $limites['DESCANSO'] = $vl;
        if ($ch === 'limite_refeicao') $limites['REFEICAO'] = $vl;
    }
}

$justificativasSalvas = array();
$sqlJust = "SELECT matricula, atividade, motivo, observacao, justificado_por, data_justificativa 
            FROM consultoria.tbl_justificativas_pausas WHERE data_ref = '$filtroData'";
$resJust = @db_exec($db_conn, $sqlJust);
if ($resJust !== false) {
    while (db_fetch_row($resJust)) {
        $key = trim(db_result($resJust, 'matricula')) . '_' . strtoupper(trim(db_result($resJust, 'atividade')));
        $justificativasSalvas[$key] = array(
            'motivo' => dashboard_utf8_encode(trim(db_result($resJust, 'motivo'))),
            'obs' => dashboard_utf8_encode(trim(db_result($resJust, 'observacao'))),
            'autor' => dashboard_utf8_encode(trim(db_result($resJust, 'justificado_por'))),
            'data' => date('d/m/Y H:i', strtotime(db_result($resJust, 'data_justificativa')))
        );
    }
}

$whereClause = "CAST(DATA_REFERENCIA AS DATE) = '$filtroData' $travaRLS";
if (!empty($filtroCoord)) $whereClause .= " AND COORDENADOR = '$filtroCoord'";
if (!empty($filtroSuper)) $whereClause .= " AND SUPERVISOR = '$filtroSuper'";
if (!empty($filtroTipoPausa)) $whereClause .= " AND UPPER(RTRIM(LTRIM(ATIVIDADE))) = UPPER('$filtroTipoPausa') ";

$whereClause .= " AND UPPER(RTRIM(LTRIM(ATIVIDADE))) NOT IN ($lixoStr) AND TEMPO_SEGUNDOS > 0 ";

$sqlMain = "SELECT MATRICULA, NOME_AGENTE, SUPERVISOR, ATIVIDADE, TEMPO_SEGUNDOS 
            FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO 
            WHERE $whereClause 
            ORDER BY TEMPO_SEGUNDOS DESC";

$resMain = @db_exec($db_conn, $sqlMain);

$pausasConsolidadas = array();
$kpis = array(
    'total_pausas' => 0,
    'tempo_total_segundos' => 0,
    'estouros_totais' => 0,
    'pendentes' => 0,
    'justificadas' => 0
);

if ($resMain) {
    while (db_fetch_row($resMain)) {
        $matricula = trim(db_result($resMain, 'MATRICULA'));
        $atividade = strtoupper(trim(db_result($resMain, 'ATIVIDADE')));
        
        $segundos = (int)db_result($resMain, 'TEMPO_SEGUNDOS');
        
        $kpis['total_pausas']++;
        $kpis['tempo_total_segundos'] += $segundos;

        $limiteSegundos = isset($limites[$atividade]) ? ($limites[$atividade] * 60) : 3600; // Fallback 60m
        $isEstouro = ($segundos > $limiteSegundos);
        $excessoSegundos = $isEstouro ? ($segundos - $limiteSegundos) : 0;

        $keyJust = $matricula . '_' . $atividade;
        $isJustificado = isset($justificativasSalvas[$keyJust]);
        $dadosJust = $isJustificado ? $justificativasSalvas[$keyJust] : null;

        if ($isEstouro) {
            $kpis['estouros_totais']++;
            if ($isJustificado) {
                $kpis['justificadas']++;
            } else {
                $kpis['pendentes']++;
            }
        }

        $passouStatus = true;
        if ($filtroStatus == 'EXCEDIDAS' && !$isEstouro) $passouStatus = false;
        if ($filtroStatus == 'NORMAL' && $isEstouro) $passouStatus = false;

        $passouJust = true;
        if ($filtroJust == 'PENDENTES' && (!$isEstouro || $isJustificado)) $passouJust = false;
        if ($filtroJust == 'JUSTIFICADAS' && (!$isEstouro || !$isJustificado)) $passouJust = false;

        if ($passouStatus && $passouJust) {
            $pausasConsolidadas[] = array(
                'matricula' => $matricula,
                'nome' => dashboard_utf8_encode(trim(db_result($resMain, 'NOME_AGENTE'))),
                'supervisor' => dashboard_utf8_encode(trim(db_result($resMain, 'SUPERVISOR'))),
                'atividade' => $atividade,
                'segundos' => $segundos,
                'limite_segundos' => $limiteSegundos,
                'is_estouro' => $isEstouro,
                'excesso_segundos' => $excessoSegundos,
                'justificativa' => $dadosJust
            );
        }
    }
}

$hTotal = floor($kpis['tempo_total_segundos'] / 3600);
$mTotal = floor(($kpis['tempo_total_segundos'] % 3600) / 60);
$tempoTotalFmt = sprintf('%02dh %02dm', $hTotal, $mTotal);

function fmtSegundos($segundos) {
    $m = floor($segundos / 60);
    $s = $segundos % 60;
    return sprintf('%dm %02ds', $m, $s);
}

function getStyleCard($isEstouro, $isJustificado) {
    if (!$isEstouro) return array('border' => 'border-slate-100 dark:border-slate-800', 'stripe' => 'bg-indigo-500', 'badge' => 'bg-slate-50 text-slate-500 border-slate-200');
    if ($isJustificado) return array('border' => 'border-emerald-100 dark:border-emerald-500/30', 'stripe' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-600 border-emerald-200');
    return array('border' => 'border-red-200 dark:border-red-500/50 shadow-[0_5px_20px_rgba(239,68,68,0.15)]', 'stripe' => 'bg-red-500', 'badge' => 'bg-red-50 text-red-600 border-red-200 animate-pulse');
}
?>

<style>
    .expandable-content { max-height: 0; opacity: 0; overflow: hidden; transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
    .expandable-content.open { max-height: 500px; opacity: 1; margin-top: 1.5rem; }
</style>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Gestão de <span class="text-indigo-600 dark:text-indigo-400">Pausas</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                Pausas Consolidadas • Justificativas Oficiais
            </p>
        </div>
        <div class="flex items-center space-x-4 bg-white dark:bg-[#0f172a] p-2 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
            <button onclick="switchView('supervisor')" id="btn-view-sup" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest <?php echo $viewModeDefault == 'supervisor' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'; ?> transition-all">
                Visão Supervisor
            </button>
            <button onclick="switchView('gestao')" id="btn-view-ges" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest <?php echo $viewModeDefault == 'gestao' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'; ?> transition-all">
                Visão Gestão
            </button>
        </div>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div class="mb-8 p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 rounded-3xl text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center shadow-sm animate-in slide-in-from-top">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            <?php echo $successMsg; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-8 p-6 bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 rounded-3xl text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-widest flex items-center shadow-sm animate-in slide-in-from-top">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <?php echo $errorMsg; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-10 transition-colors duration-500">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-4 items-end" id="formFiltrosJustificativa">
            <input type="hidden" name="route" value="pausas_consolidadas">
            
            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Data</label>
                <input type="date" name="data_ref" value="<?php echo $filtroData; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500">
            </div>

            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Tipo</label>
                <select name="tipo_pausa" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                    <option value="">TODAS</option>
                    <?php foreach($listaTiposPausa as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $filtroTipoPausa == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Status</label>
                <select name="status_pausa" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                    <option value="TODAS" <?php echo $filtroStatus == 'TODAS' ? 'selected' : ''; ?>>Todas as Pausas</option>
                    <option value="EXCEDIDAS" <?php echo $filtroStatus == 'EXCEDIDAS' ? 'selected' : ''; ?>>Excedidas (Estouro)</option>
                    <option value="NORMAL" <?php echo $filtroStatus == 'NORMAL' ? 'selected' : ''; ?>>Dentro do Normal</option>
                </select>
            </div>

            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Justificativa</label>
                <select name="justificativa" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                    <option value="TODAS" <?php echo $filtroJust == 'TODAS' ? 'selected' : ''; ?>>Todas</option>
                    <option value="PENDENTES" <?php echo $filtroJust == 'PENDENTES' ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="JUSTIFICADAS" <?php echo $filtroJust == 'JUSTIFICADAS' ? 'selected' : ''; ?>>Justificadas</option>
                </select>
            </div>

            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?><input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>"><?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formFiltrosJustificativa').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaCoords as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if($filtroCoord == $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Supervisor</label>
                <?php if ($isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="supervisor" onchange="document.getElementById('formFiltrosJustificativa').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaSupers as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if($filtroSuper == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <button type="submit" class="w-full px-6 py-4 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-6 mb-10">
        <div class="bg-white dark:bg-[#0f172a] p-6 rounded-[2rem] border border-slate-100 dark:border-slate-800 shadow-sm">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Vol. Pausas</p>
            <p class="text-3xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $kpis['total_pausas']; ?></p>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-6 rounded-[2rem] border border-slate-100 dark:border-slate-800 shadow-sm bg-gradient-to-br from-white to-slate-50 dark:from-[#0f172a] dark:to-[#020617]">
            <p class="text-[9px] font-black text-indigo-500 uppercase tracking-widest mb-1">Tempo Total Perdido</p>
            <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 tracking-tighter italic"><?php echo $tempoTotalFmt; ?></p>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-6 rounded-[2rem] border border-red-100 dark:border-red-500/20 shadow-sm relative overflow-hidden">
            <div class="relative z-10">
                <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1">Estouros Totais</p>
                <p class="text-3xl font-black text-red-600 dark:text-red-400 tracking-tighter"><?php echo $kpis['estouros_totais']; ?></p>
            </div>
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-500/10 rounded-full blur-xl pointer-events-none"></div>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-6 rounded-[2rem] border border-amber-100 dark:border-amber-500/20 shadow-sm relative overflow-hidden">
            <div class="relative z-10">
                <p class="text-[9px] font-black text-amber-500 uppercase tracking-widest mb-1">Pendentes</p>
                <p class="text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tighter"><?php echo $kpis['pendentes']; ?></p>
            </div>
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full blur-xl pointer-events-none"></div>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-6 rounded-[2rem] border border-emerald-100 dark:border-emerald-500/20 shadow-sm relative overflow-hidden col-span-2 xl:col-span-1">
            <div class="relative z-10">
                <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mb-1">Justificadas</p>
                <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tighter"><?php echo $kpis['justificadas']; ?></p>
            </div>
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
        </div>
    </div>

    <div class="space-y-6">
        <?php if (empty($pausasConsolidadas)): ?>
            <div class="col-span-full py-24 text-center bg-white dark:bg-[#0f172a] rounded-[3.5rem] border border-slate-100 dark:border-slate-800 border-dashed">
                <p class="text-slate-400 dark:text-slate-600 font-bold uppercase tracking-widest text-[11px] italic">Nenhum registo encontrado para os filtros aplicados.</p>
            </div>
        <?php else: foreach ($pausasConsolidadas as $idx => $p): 
            $style = getStyleCard($p['is_estouro'], $p['justificativa'] !== null);
            $expId = "exp_" . $idx;
        ?>
            <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] border <?php echo $style['border']; ?> shadow-sm transition-all duration-500 relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-2 <?php echo $style['stripe']; ?>"></div>
                
                <div class="p-8 pl-10 flex flex-col xl:flex-row xl:items-center justify-between gap-8 relative z-10">
                    
                    <div class="flex items-center space-x-5 min-w-[300px]">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-xl bg-slate-100 dark:bg-slate-800 text-slate-500">
                            <?php echo substr($p['nome'], 0, 1); ?>
                        </div>
                        <div class="overflow-hidden">
                            <h4 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight truncate w-48" title="<?php echo $p['nome']; ?>"><?php echo $p['nome']; ?></h4>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 truncate">Mat: <?php echo $p['matricula']; ?> • Sup: <?php echo $p['supervisor']; ?></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-10 flex-grow justify-center <?php echo ($p['is_estouro'] && $p['justificativa'] !== null) ? 'opacity-60' : ''; ?>">
                        <div class="text-center w-28">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Atividade</p>
                            <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase border <?php echo $style['badge']; ?> truncate w-full block" title="<?php echo $p['atividade']; ?>">
                                <?php echo $p['atividade']; ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center space-x-6">
                            <div class="text-right">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Limite</p>
                                <p class="text-lg font-bold text-slate-500 dark:text-slate-400"><?php echo fmtSegundos($p['limite_segundos']); ?></p>
                            </div>
                            <div class="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
                            <div class="text-left">
                                <p class="text-[9px] font-black <?php echo $p['is_estouro'] ? 'text-red-500' : 'text-slate-400'; ?> uppercase tracking-widest mb-1">Realizado</p>
                                <p class="text-lg font-black <?php echo $p['is_estouro'] ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300'; ?>"><?php echo fmtSegundos($p['segundos']); ?></p>
                            </div>
                        </div>

                        <div class="text-center w-24">
                            <p class="text-[9px] font-black <?php echo $p['is_estouro'] ? 'text-red-500' : 'text-slate-400'; ?> uppercase tracking-widest mb-1"><?php echo $p['is_estouro'] ? 'Excesso' : 'Saldo'; ?></p>
                            <p class="text-base font-black <?php echo $p['is_estouro'] ? 'text-red-600 dark:text-red-400 italic' : 'text-emerald-500'; ?>">
                                <?php echo $p['is_estouro'] ? '+' . fmtSegundos($p['excesso_segundos']) : '-'; ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($p['is_estouro']): ?>
                        
                        <div class="min-w-[220px] text-right view-supervisor-only <?php echo $viewModeDefault == 'gestao' ? 'hidden' : ''; ?>">
                            <?php if ($p['justificativa'] === null): ?>
                                <button onclick="toggleExpand('<?php echo $expId; ?>')" class="px-6 py-4 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-red-200 dark:border-red-500/20 transition-all flex items-center justify-center w-full group">
                                    <span>Justificar Excesso</span>
                                    <svg class="w-4 h-4 ml-2 transform group-hover:translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            <?php else: ?>
                                <span class="inline-flex items-center justify-center w-full px-4 py-4 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-emerald-200 dark:border-emerald-500/20">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    Resolvido (Justificado)
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="min-w-[220px] text-right view-gestao-only <?php echo $viewModeDefault == 'supervisor' ? 'hidden' : ''; ?>">
                            <?php if ($p['justificativa'] === null): ?>
                                <span class="inline-flex items-center justify-center w-full px-4 py-4 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-amber-200 dark:border-amber-500/20">
                                    <svg class="w-4 h-4 mr-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Pendente de Ação
                                </span>
                            <?php else: ?>
                                <button onclick="toggleExpand('<?php echo $expId; ?>')" class="px-6 py-4 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center w-full group">
                                    <span>Ver Justificativa</span>
                                    <svg class="w-4 h-4 ml-2 transform group-hover:translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="min-w-[220px] text-right">
                            <span class="inline-flex items-center justify-center w-full px-4 py-4 bg-slate-50 dark:bg-slate-800/50 text-slate-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-slate-100 dark:border-slate-800">
                                Dentro do Limite
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($p['is_estouro']): ?>
                    <div id="<?php echo $expId; ?>" class="expandable-content bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800">
                        
                        <?php if ($p['justificativa'] === null): ?>
                            <form method="POST" action="index.php?route=pausas_consolidadas&data_ref=<?php echo $filtroData; ?>" class="p-8 pl-10">
                                <input type="hidden" name="mat_agente" value="<?php echo $p['matricula']; ?>">
                                <input type="hidden" name="atividade_agente" value="<?php echo $p['atividade']; ?>">
                                <input type="hidden" name="data_ref_agente" value="<?php echo $filtroData; ?>">
                                <input type="hidden" name="motivo" id="motivo_<?php echo $expId; ?>" value="Outros (Detalhado na Observação)">

                                <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest mb-4">Selecione o Motivo do Estouro</h4>
                                
                                <div class="flex flex-wrap gap-3 mb-6">
                                    <button type="button" onclick="selectTag(this, '<?php echo $expId; ?>', 'Problema Sistêmico (TI)')" class="tag-btn px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0f172a] text-slate-500 text-[10px] font-bold uppercase tracking-widest hover:border-indigo-400 hover:text-indigo-500 transition-colors">Problema Sistêmico (TI)</button>
                                    <button type="button" onclick="selectTag(this, '<?php echo $expId; ?>', 'Passagem no RH / Médico')" class="tag-btn px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0f172a] text-slate-500 text-[10px] font-bold uppercase tracking-widest hover:border-indigo-400 hover:text-indigo-500 transition-colors">Passagem no RH / Médico</button>
                                    <button type="button" onclick="selectTag(this, '<?php echo $expId; ?>', 'Fila Excesso (Refeitório)')" class="tag-btn px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0f172a] text-slate-500 text-[10px] font-bold uppercase tracking-widest hover:border-indigo-400 hover:text-indigo-500 transition-colors">Fila Excesso (Refeitório)</button>
                                    <button type="button" onclick="selectTag(this, '<?php echo $expId; ?>', 'Feedback / Coaching')" class="tag-btn px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0f172a] text-slate-500 text-[10px] font-bold uppercase tracking-widest hover:border-indigo-400 hover:text-indigo-500 transition-colors">Feedback / Coaching</button>
                                </div>

                                <textarea name="observacao" class="w-full bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-700 rounded-2xl p-5 text-sm text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-all resize-none mb-4" rows="2" placeholder="Adicione uma observação detalhada (opcional)..."></textarea>
                                
                                <div class="flex justify-end space-x-3">
                                    <button type="button" onclick="toggleExpand('<?php echo $expId; ?>')" class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors">Cancelar</button>
                                    <button type="submit" name="salvar_justificativa" class="px-8 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest bg-indigo-600 text-white shadow-md shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-colors active:scale-95">Salvar Justificativa</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="p-8 pl-10 flex items-start space-x-6">
                                <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="px-3 py-1 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 rounded-lg text-[9px] font-black uppercase tracking-widest border border-indigo-200 dark:border-indigo-500/30">
                                            <?php echo $p['justificativa']['motivo']; ?>
                                        </span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                            Justificado por: <span class="text-slate-700 dark:text-slate-300"><?php echo $p['justificativa']['autor']; ?></span> em <?php echo $p['justificativa']['data']; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($p['justificativa']['obs'])): ?>
                                    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-700 rounded-2xl p-5 relative mt-3 shadow-sm">
                                        <div class="absolute -top-2 left-6 w-4 h-4 bg-white dark:bg-[#0f172a] border-l border-t border-slate-200 dark:border-slate-700 transform rotate-45"></div>
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300 relative z-10 italic">
                                            "<?php echo $p['justificativa']['obs']; ?>"
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
    function toggleExpand(id) {
        const content = document.getElementById(id);
        if (!content) return;
        if (content.classList.contains('open')) {
            content.classList.remove('open');
        } else {
            document.querySelectorAll('.expandable-content').forEach(el => el.classList.remove('open'));
            content.classList.add('open');
        }
    }

    function selectTag(btn, expId, motivoStr) {
        const tags = btn.parentElement.querySelectorAll('.tag-btn');
        tags.forEach(t => {
            t.classList.remove('bg-indigo-50', 'dark:bg-indigo-500/20', 'border-indigo-400', 'text-indigo-600', 'dark:text-indigo-400');
            t.classList.add('bg-white', 'dark:bg-[#0f172a]', 'border-slate-200', 'dark:border-slate-700', 'text-slate-500');
        });
        btn.classList.remove('bg-white', 'dark:bg-[#0f172a]', 'border-slate-200', 'dark:border-slate-700', 'text-slate-500');
        btn.classList.add('bg-indigo-50', 'dark:bg-indigo-500/20', 'border-indigo-400', 'text-indigo-600', 'dark:text-indigo-400');
    
        const inputMotivo = document.getElementById('motivo_' + expId);
        if (inputMotivo) inputMotivo.value = motivoStr;
    }

    function switchView(view) {
        const btnSup = document.getElementById('btn-view-sup');
        const btnGes = document.getElementById('btn-view-ges');
        const elementsSup = document.querySelectorAll('.view-supervisor-only');
        const elementsGes = document.querySelectorAll('.view-gestao-only');

        document.querySelectorAll('.expandable-content').forEach(el => el.classList.remove('open'));

        if (view === 'supervisor') {
            btnSup.classList.add('bg-indigo-600', 'text-white', 'shadow-md');
            btnSup.classList.remove('text-slate-500', 'hover:text-slate-800', 'dark:hover:text-white', 'bg-transparent');
            
            btnGes.classList.remove('bg-indigo-600', 'text-white', 'shadow-md');
            btnGes.classList.add('text-slate-500', 'hover:text-slate-800', 'dark:hover:text-white', 'bg-transparent');

            elementsSup.forEach(el => el.classList.remove('hidden'));
            elementsGes.forEach(el => el.classList.add('hidden'));
        } else {
            btnGes.classList.add('bg-indigo-600', 'text-white', 'shadow-md');
            btnGes.classList.remove('text-slate-500', 'hover:text-slate-800', 'dark:hover:text-white', 'bg-transparent');
            
            btnSup.classList.remove('bg-indigo-600', 'text-white', 'shadow-md');
            btnSup.classList.add('text-slate-500', 'hover:text-slate-800', 'dark:hover:text-white', 'bg-transparent');

            elementsSup.forEach(el => el.classList.add('hidden'));
            elementsGes.forEach(el => el.classList.remove('hidden'));
        }
    }
</script>

<?php include 'shared/ui/footer.php'; ?>