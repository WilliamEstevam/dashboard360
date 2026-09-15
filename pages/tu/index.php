<?php
$currentView = 'tu';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

function getN2Style($text) {
    $t = strtoupper(trim($text));
    if (strpos($t, 'CUSTO BENEF') !== false) return 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20';
    if (strpos($t, 'CANCELAMENTO') !== false || strpos($t, 'IMPRODUTIV') !== false) return 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-100 dark:border-red-500/20';
    if (strpos($t, 'TÉCNICO') !== false || strpos($t, 'TECNICO') !== false || strpos($t, 'PROBLEMA') !== false) return 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20';
    return 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-100 dark:border-slate-700';
}

function getN4Color($text) {
    $t = strtoupper(trim($text));
    if (strpos($t, 'RETIDO') !== false && strpos($t, 'NÃO') === false) return 'text-emerald-600 dark:text-emerald-400';
    if (strpos($t, 'CANCELADO') !== false || strpos($t, 'NÃO RETIDO') !== false) return 'text-red-500 dark:text-red-400';
    return 'text-indigo-600 dark:text-indigo-400';
}

$sqlData = "SELECT MAX(CAST(DATA_REFERENCIA AS DATE)) as ultima FROM consultoria.TBL_PERM_RELATORIO_TU";
$resData = @db_exec($db_conn, $sqlData);
$ultimaData = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d');

$filtroDataIni = isset($_GET['data_ini']) ? $_GET['data_ini'] : $ultimaData;
$filtroDataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $ultimaData;
if (!validar_data($filtroDataIni)) $filtroDataIni = $ultimaData;
if (!validar_data($filtroDataFim)) $filtroDataFim = $ultimaData;

$filtroCoord   = isset($_GET['coordenador']) ? dashboard_escape_sql($_GET['coordenador']) : '';
$filtroSuper   = isset($_GET['supervisor']) ? dashboard_escape_sql($_GET['supervisor']) : '';
$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');
$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');

if ($isCoordenador) {
    $filtroCoord = $userNomeOficial;
}
if ($isSupervisor) {
    $filtroSuper = $userNomeOficial;
    $filtroCoord = ''; 
}

$listaCoords = array();
if (!$isCoordenador && !$isSupervisor) {
    // Apenas carrega a lista de Coordenadores se for Admin/Gerente
    $resC = @db_exec($db_conn, "SELECT DISTINCT COORDENADOR FROM consultoria.TBL_PERM_RELATORIO_TU WHERE COORDENADOR IS NOT NULL AND COORDENADOR <> '' ORDER BY COORDENADOR");
    if ($resC) { while (db_fetch_row($resC)) { $listaCoords[] = dashboard_utf8_encode(db_result($resC, 'COORDENADOR')); } }
}

$listaSupers = array();
if (!$isSupervisor) {
    $sqlSupers = "SELECT DISTINCT SUPERVISOR_QUADRO FROM consultoria.TBL_PERM_RELATORIO_TU WHERE SUPERVISOR_QUADRO IS NOT NULL AND SUPERVISOR_QUADRO <> ''";
    if (!empty($filtroCoord)) {
        $sqlSupers .= " AND COORDENADOR = '$filtroCoord'";
    }
    $sqlSupers .= " ORDER BY SUPERVISOR_QUADRO";
    $resS = @db_exec($db_conn, $sqlSupers);
    if ($resS) { while (db_fetch_row($resS)) { $listaSupers[] = dashboard_utf8_encode(db_result($resS, 'SUPERVISOR_QUADRO')); } }
    if (!empty($filtroSuper) && !in_array($filtroSuper, $listaSupers)) {
        $filtroSuper = '';
    }
}

$whereClause = "CAST(DATA_REFERENCIA AS DATE) BETWEEN '$filtroDataIni' AND '$filtroDataFim'";
if (!empty($filtroCoord)) $whereClause .= " AND COORDENADOR = '$filtroCoord'";
if (!empty($filtroSuper)) $whereClause .= " AND SUPERVISOR_QUADRO = '$filtroSuper'";

$sqlKpis = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%CANCELADO%' OR UPPER(HIERARQUIA_N4) LIKE '%NÃO RETIDO%' THEN 1 ELSE 0 END) as cancelados
FROM consultoria.TBL_PERM_RELATORIO_TU WHERE $whereClause";

$resKpis = @db_exec($db_conn, $sqlKpis);
$kpiTotal = 0; $kpiRetencao = 0; $kpiCancelamento = 0;

if ($resKpis && db_fetch_row($resKpis)) {
    $kpiTotal = (int)db_result($resKpis, 'total');
    $ret = (int)db_result($resKpis, 'retidos');
    $can = (int)db_result($resKpis, 'cancelados');
    if ($kpiTotal > 0) {
        $kpiRetencao = round(($ret / $kpiTotal) * 100, 1);
        $kpiCancelamento = round(($can / $kpiTotal) * 100, 1);
    }
}

$ranking = array();
$sqlRank = "SELECT 
    MATRICULA, NOME_AGENTE, SUPERVISOR_QUADRO,
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%CANCELADO%' OR UPPER(HIERARQUIA_N4) LIKE '%NÃO RETIDO%' THEN 1 ELSE 0 END) as cancelados
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE $whereClause
GROUP BY MATRICULA, NOME_AGENTE, SUPERVISOR_QUADRO
ORDER BY retidos DESC, total DESC";

$resRank = @db_exec($db_conn, $sqlRank);
if ($resRank) {
    while (db_fetch_row($resRank)) {
        $tot = (int)db_result($resRank, 'total');
        $ret = (int)db_result($resRank, 'retidos');
        $can = (int)db_result($resRank, 'cancelados');
        $pct = ($tot > 0) ? round(($ret / $tot) * 100, 1) : 0;
        
        $ranking[] = array(
            'matricula' => trim(db_result($resRank, 'MATRICULA')),
            'operador' => dashboard_utf8_encode(db_result($resRank, 'NOME_AGENTE')),
            'supervisor' => dashboard_utf8_encode(db_result($resRank, 'SUPERVISOR_QUADRO')),
            'atendidas' => $tot,
            'retido' => $ret,
            'cancelados' => $can,
            'pct_total_retencao' => $pct
        );
    }
}

$modalMatricula = isset($_GET['modal_mat']) ? dashboard_escape_sql($_GET['modal_mat']) : '';
$modalNome = isset($_GET['modal_nome']) ? $_GET['modal_nome'] : '';
$detalhesModal = array();

if (!empty($modalMatricula)) {
    $sqlDet = "SELECT DATA_REFERENCIA, HIERARQUIA_N2, HIERARQUIA_N4, ID_ATENDIMENTO 
               FROM consultoria.TBL_PERM_RELATORIO_TU 
               WHERE $whereClause AND MATRICULA = '$modalMatricula'
               ORDER BY DATA_REFERENCIA DESC";
    $resDet = @db_exec($db_conn, $sqlDet);
    if ($resDet) {
        while (db_fetch_row($resDet)) {
            $detalhesModal[] = array(
                'data' => date('d/m/Y', strtotime(db_result($resDet, 'DATA_REFERENCIA'))),
                'n2' => dashboard_utf8_encode(db_result($resDet, 'HIERARQUIA_N2')),
                'n4' => dashboard_utf8_encode(db_result($resDet, 'HIERARQUIA_N4')),
                'id_atendimento' => trim(db_result($resDet, 'ID_ATENDIMENTO'))
            );
        }
    }
}
?>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Inteligência <span class="text-indigo-600 dark:text-indigo-400">TU</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">Performance por Operador e Detalhamento de Chamadas</p>
        </div>
        
        <div class="flex items-center space-x-4">
            <button class="flex items-center space-x-3 px-6 py-4 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-emerald-200 dark:border-emerald-500/20 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Baixar Base Excel</span>
            </button>
        </div>
    </div>
    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-8 transition-colors duration-500">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end" id="formFiltrosTU">
            <input type="hidden" name="route" value="tu">
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Data Inicial</label>
                <input type="date" name="data_ini" value="<?php echo $filtroDataIni; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Data Final</label>
                <input type="date" name="data_fim" value="<?php echo $filtroDataFim; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?>
                        <input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formFiltrosTU').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                        <option value="">TODOS</option>
                        <?php foreach($listaCoords as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if($filtroCoord == $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Supervisor</label>
                <?php if ($isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="supervisor" onchange="document.getElementById('formFiltrosTU').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                        <option value="">TODOS</option>
                        <?php foreach($listaSupers as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if($filtroSuper == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div>
                <button type="submit" class="w-full px-6 py-4 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all active:scale-95 flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Aplicar
                </button>
            </div>
        </form>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php
        $kpis = array(
            array('label' => 'Total Atendimentos', 'val' => number_format($kpiTotal, 0, ',', '.'), 'color' => 'slate'),
            array('label' => 'Taxa de Retenção', 'val' => $kpiRetencao.'%', 'color' => 'indigo'),
            array('label' => 'Taxa de Cancelamento', 'val' => $kpiCancelamento.'%', 'color' => 'red')
        );
        foreach($kpis as $k): ?>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm text-center hover:shadow-lg dark:hover:shadow-indigo-500/10 transition-all duration-500">
            <p class="text-[10px] font-black text-<?php echo h($k['color'] === 'slate' ? 'slate-400 dark:text-slate-500' : $k['color'].'-500 dark:text-'.$k['color'].'-400'); ?> uppercase tracking-widest mb-2"><?php echo h($k['label']); ?></p>
            <p class="text-4xl font-black text-<?php echo h($k['color'] === 'slate' ? 'slate-800 dark:text-white' : $k['color'].'-600 dark:text-'.$k['color'].'-400'); ?> tracking-tighter"><?php echo h($k['val']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 rounded-[2.5rem] shadow-sm overflow-hidden transition-colors duration-500">
        <div class="p-8 border-b border-slate-50 dark:border-slate-800/50 flex items-center space-x-3 bg-slate-50/50 dark:bg-slate-800/20">
            <span class="text-2xl">📋</span>
            <h3 class="text-xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">Ranking de Performance</h3>
        </div>
        
        <?php if (empty($ranking)): ?>
            <div class="p-16 text-center text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest text-xs">
                Nenhum dado encontrado para os filtros selecionados.
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800/50">
                        <th class="px-8 py-6 text-center w-16">Pos</th>
                        <th class="px-8 py-6">Operador</th>
                        <th class="px-8 py-6 text-center">Atendidas</th>
                        <th class="px-8 py-6 text-center">Cancelado</th>
                        <th class="px-8 py-6 text-center">Retido</th>
                        <th class="px-8 py-6 text-center">Retenção (%)</th>
                        <th class="px-8 py-6 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                    <?php $pos = 1; foreach ($ranking as $r): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors group">
                            <td class="px-8 py-5 text-center font-black text-slate-400 dark:text-slate-500">
                                <?php echo $pos <= 3 ? ($pos == 1 ? '🥇' : ($pos == 2 ? '🥈' : '🥉')) : '<span class="text-[11px]">'.$pos.'º</span>'; ?>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center overflow-hidden border border-slate-200/50 dark:border-indigo-500/20">
                                        <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase"><?php echo substr($r['operador'], 0, 1); ?></span>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-tight truncate w-48"><?php echo h($r['operador']); ?></p>
                                        <p class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider truncate w-48"><?php echo h($r['supervisor']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-center font-bold text-slate-600 dark:text-slate-300"><?php echo $r['atendidas']; ?></td>
                            <td class="px-8 py-5 text-center font-bold text-red-500/70 dark:text-red-400/80"><?php echo $r['cancelados']; ?></td>
                            <td class="px-8 py-5 text-center font-bold text-indigo-600 dark:text-indigo-400"><?php echo $r['retido']; ?></td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-3 py-1 rounded-lg text-[11px] font-black <?php echo $r['pct_total_retencao'] >= 65 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10' : 'text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800'; ?>">
                                    <?php echo $r['pct_total_retencao']; ?>%
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <a href="index.php?route=tu&data_ini=<?php echo $filtroDataIni; ?>&data_fim=<?php echo $filtroDataFim; ?>&coordenador=<?php echo urlencode($filtroCoord); ?>&supervisor=<?php echo urlencode($filtroSuper); ?>&modal_mat=<?php echo $r['matricula']; ?>&modal_nome=<?php echo urlencode($r['operador']); ?>" 
                                   class="inline-block p-2.5 text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl transition-all" title="Ver Detalhes">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                            </td>
                        </tr>
                    <?php $pos++; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($modalMatricula)): ?>
<div id="detalhes-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm animate-in fade-in duration-300">
    <div class="bg-white dark:bg-[#0f172a] w-full max-w-5xl h-[80vh] rounded-[3.5rem] shadow-2xl flex flex-col overflow-hidden border border-slate-100 dark:border-slate-800 relative transition-colors duration-500">
        
        <div class="p-10 border-b border-slate-50 dark:border-slate-800/50 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/20">
            <div class="flex items-center space-x-6">
                <div class="w-16 h-16 bg-indigo-600 dark:bg-indigo-500 rounded-3xl flex items-center justify-center text-white text-2xl font-black italic shadow-lg shadow-indigo-100 dark:shadow-none border-4 border-white dark:border-[#0f172a]">
                    <span><?php echo substr($modalNome, 0, 1); ?></span>
                </div>
                <div>
                    <h4 class="text-2xl font-black text-slate-800 dark:text-white tracking-tighter uppercase italic"><?php echo h($modalNome); ?></h4>
                    <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Matrícula: <span class="text-indigo-500 dark:text-indigo-400"><?php echo h($modalMatricula); ?></span> • Histórico de Chamadas</p>
                </div>
            </div>
            <a href="index.php?route=tu&data_ini=<?php echo $filtroDataIni; ?>&data_fim=<?php echo $filtroDataFim; ?>&coordenador=<?php echo urlencode($filtroCoord); ?>&supervisor=<?php echo urlencode($filtroSuper); ?>" 
               class="p-4 bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-700 hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 rounded-2xl transition-all shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>
        <div class="flex-grow overflow-y-auto p-10 bg-white dark:bg-[#0f172a]">
            <?php if (empty($detalhesModal)): ?>
                <p class="text-center text-slate-400 uppercase font-bold text-xs mt-10">Nenhum detalhe encontrado para este operador no período.</p>
            <?php else: ?>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-slate-800/50 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        <th class="px-8 py-5">Data Ref.</th>
                        <th class="px-8 py-5">Hierarquia N2 (Motivo)</th>
                        <th class="px-8 py-5">Hierarquia N4 (Desfecho)</th>
                        <th class="px-8 py-5 text-center">ID Atendimento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                    <?php foreach ($detalhesModal as $d): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-8 py-5 text-[11px] font-black text-slate-700 dark:text-slate-300"><?php echo $d['data']; ?></td>
                            <td class="px-8 py-5">
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase border shadow-sm <?php echo getN2Style($d['n2']); ?>">
                                    <?php echo !empty($d['n2']) ? $d['n2'] : 'N/A'; ?>
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-[10px] font-bold <?php echo getN4Color($d['n4']); ?> uppercase italic">
                                    <?php echo !empty($d['n4']) ? $d['n4'] : 'N/A'; ?>
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500">#<?php echo !empty($d['id_atendimento']) ? $d['id_atendimento'] : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('detalhes-modal');
        if (modal) {
            document.body.appendChild(modal);
        }
    });
</script>
<?php endif; ?>

<?php include 'shared/ui/footer.php'; ?>