<?php

$currentView = 'cubo_tu';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

function getStatusAderenciaStyle($status) {
    if (strpos($status, 'Ok') !== false) return 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20';
    if (strpos($status, 'Crítico') !== false || strpos($status, 'Erro') !== false) return 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/20 shadow-[0_0_10px_rgba(239,68,68,0.2)]';
    if (strpos($status, 'Atenção') !== false) return 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/20';
    return 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700';
}

$sqlData = "SELECT MAX(CAST(DATA_REF AS DATE)) as ultima FROM consultoria.VW_PERM_COMPARATIVO_PERFILAMENTO";
$resData = @db_exec($db_conn, $sqlData);
$ultimaData = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d');

$filtroDataIni = isset($_GET['data_ini']) ? $_GET['data_ini'] : $ultimaData;
$filtroDataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $ultimaData;
// Validação de formato para prevenir SQL Injection via parâmetros de data
if (!validar_data($filtroDataIni)) $filtroDataIni = $ultimaData;
if (!validar_data($filtroDataFim)) $filtroDataFim = $ultimaData;

// Lemos o que vem do URL primeiro
$filtroCoord   = isset($_GET['coordenador']) ? dashboard_escape_sql($_GET['coordenador']) : '';
$filtroSuper   = isset($_GET['supervisor']) ? dashboard_escape_sql($_GET['supervisor']) : '';

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');

// Se for Coordenador, cravamos o filtro dele e ignoramos o que vier no URL
if ($isCoordenador) {
    $filtroCoord = $userNomeOficial;
}

// Se for Supervisor, cravamos o filtro do supervisor (e ignoramos coordenador para evitar conflitos)
if ($isSupervisor) {
    $filtroSuper = $userNomeOficial;
    $filtroCoord = ''; 
}

$listaCoords = array();
if (!$isCoordenador && !$isSupervisor) {
    $resC = @db_exec($db_conn, "SELECT DISTINCT COORDENADOR FROM consultoria.VW_PERM_COMPARATIVO_PERFILAMENTO WHERE COORDENADOR IS NOT NULL AND COORDENADOR <> '' ORDER BY COORDENADOR");
    if ($resC) { while (db_fetch_row($resC)) { $listaCoords[] = dashboard_utf8_encode(db_result($resC, 'COORDENADOR')); } }
}

$listaSupers = array();
if (!$isSupervisor) {
    $sqlSupers = "SELECT DISTINCT NOME_SUPERVISOR FROM consultoria.VW_PERM_COMPARATIVO_PERFILAMENTO WHERE NOME_SUPERVISOR IS NOT NULL AND NOME_SUPERVISOR <> ''";
    if (!empty($filtroCoord)) {
        $sqlSupers .= " AND COORDENADOR = '$filtroCoord'";
    }
    $sqlSupers .= " ORDER BY NOME_SUPERVISOR";

    $resS = @db_exec($db_conn, $sqlSupers);
    if ($resS) { while (db_fetch_row($resS)) { $listaSupers[] = dashboard_utf8_encode(db_result($resS, 'NOME_SUPERVISOR')); } }

    // Validação da Cascata
    if (!empty($filtroSuper) && !in_array($filtroSuper, $listaSupers)) {
        $filtroSuper = '';
    }
}

$whereClause = "CAST(DATA_REF AS DATE) BETWEEN '$filtroDataIni' AND '$filtroDataFim'";
if (!empty($filtroCoord)) $whereClause .= " AND COORDENADOR = '$filtroCoord'";
if (!empty($filtroSuper)) $whereClause .= " AND NOME_SUPERVISOR = '$filtroSuper'";

$sqlMain = "SELECT 
    DATA_REF, MATRICULA, NOME_OPERADOR, NOME_SUPERVISOR, 
    OFICIAL_Qtd_Atendidas, TU_Qtd_Total_Perfilada, 
    GAP_Diferenca, STATUS_ADERENCIA 
FROM consultoria.VW_PERM_COMPARATIVO_PERFILAMENTO 
WHERE $whereClause 
ORDER BY DATA_REF DESC, GAP_Diferenca DESC";

$resMain = @db_exec($db_conn, $sqlMain);

$tabela = array();
$kpiOficial = 0;
$kpiTu = 0;
$kpiGap = 0;
$kpiAlertas = 0;

if ($resMain) {
    while (db_fetch_row($resMain)) {
        $oficial = (int)db_result($resMain, 'OFICIAL_Qtd_Atendidas');
        $tu = (int)db_result($resMain, 'TU_Qtd_Total_Perfilada');
        $gap = (int)db_result($resMain, 'GAP_Diferenca');
        $status = dashboard_utf8_encode(db_result($resMain, 'STATUS_ADERENCIA'));

        $kpiOficial += $oficial;
        $kpiTu += $tu;
        $kpiGap += $gap;
        
        if (strpos($status, 'Ok') === false) {
            $kpiAlertas++;
        }

        $tabela[] = array(
            'data' => date('d/m/Y', strtotime(db_result($resMain, 'DATA_REF'))),
            'matricula' => trim(db_result($resMain, 'MATRICULA')),
            'operador' => dashboard_utf8_encode(db_result($resMain, 'NOME_OPERADOR')),
            'supervisor' => dashboard_utf8_encode(db_result($resMain, 'NOME_SUPERVISOR')),
            'oficial' => $oficial,
            'tu' => $tu,
            'gap' => $gap,
            'status' => $status
        );
    }
}

// Cálculo de Aderência Geral (%)
$aderenciaPct = 0;
if ($kpiOficial > 0) {
    $aderenciaPct = round(($kpiTu / $kpiOficial) * 100, 1);
}
?>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <!-- Título e Ações -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Cubo <span class="text-slate-400 font-light mx-2">x</span> <span class="text-indigo-600 dark:text-indigo-400">TU</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">Comparativo de Aderência e Perfilamento</p>
        </div>
        
        <div class="flex items-center space-x-4">
            <button class="flex items-center space-x-3 px-6 py-4 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-emerald-200 dark:border-emerald-500/20 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Baixar Relatório</span>
            </button>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-8 transition-colors duration-500">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end" id="formFiltrosCuboTu">
            <input type="hidden" name="route" value="cubo_tu">
            
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Data Inicial</label>
                <input type="date" name="data_ini" value="<?php echo $filtroDataIni; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Data Final</label>
                <input type="date" name="data_fim" value="<?php echo $filtroDataFim; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            
            <!-- Controle de Visibilidade: Coordenador -->
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <!-- Campo Bloqueado para RLS -->
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?>
                        <input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formFiltrosCuboTu').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                        <option value="">TODOS</option>
                        <?php foreach($listaCoords as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if($filtroCoord == $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Controle de Visibilidade: Supervisor -->
            <div>
                <label class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 block ml-1">Supervisor</label>
                <?php if ($isSupervisor): ?>
                    <!-- Campo Bloqueado para RLS -->
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="supervisor" onchange="document.getElementById('formFiltrosCuboTu').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors cursor-pointer">
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

    <!-- Cards KPI -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm text-center hover:shadow-lg dark:hover:shadow-indigo-500/10 transition-all duration-500">
            <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2">Atendidas (Oficial)</p>
            <p class="text-3xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo number_format($kpiOficial, 0, ',', '.'); ?></p>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm text-center hover:shadow-lg dark:hover:shadow-indigo-500/10 transition-all duration-500">
            <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-2">Tabuladas (TU)</p>
            <p class="text-3xl font-black text-indigo-600 tracking-tighter"><?php echo number_format($kpiTu, 0, ',', '.'); ?></p>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm text-center hover:shadow-lg dark:hover:shadow-indigo-500/10 transition-all duration-500 relative overflow-hidden">
            <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-2 relative z-10">GAP (Diferença)</p>
            <p class="text-3xl font-black text-amber-600 tracking-tighter relative z-10"><?php echo number_format($kpiGap, 0, ',', '.'); ?></p>
            <?php if($kpiGap > 0): ?>
                <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-500/10 rounded-full blur-xl pointer-events-none"></div>
            <?php endif; ?>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border <?php echo $aderenciaPct >= 90 ? 'border-emerald-100 dark:border-emerald-500/20' : 'border-red-100 dark:border-red-500/20'; ?> shadow-sm text-center hover:shadow-lg transition-all duration-500">
            <p class="text-[10px] font-black <?php echo $aderenciaPct >= 90 ? 'text-emerald-500' : 'text-red-500'; ?> uppercase tracking-widest mb-2">Aderência</p>
            <p class="text-3xl font-black <?php echo $aderenciaPct >= 90 ? 'text-emerald-600' : 'text-red-600'; ?> tracking-tighter"><?php echo $aderenciaPct; ?>%</p>
        </div>
    </div>

    <!-- Tabela Comparativa -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 rounded-[2.5rem] shadow-sm overflow-hidden transition-colors duration-500">
        <div class="p-8 border-b border-slate-50 dark:border-slate-800/50 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/20">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">⚖️</span>
                <h3 class="text-xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">Comparativo por Operador</h3>
            </div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                <span class="text-red-500 mr-1"><?php echo $kpiAlertas; ?></span> Alertas Críticos
            </div>
        </div>
        
        <?php if (empty($tabela)): ?>
            <div class="p-16 text-center text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest text-xs">
                Nenhum dado encontrado no período.
            </div>
        <?php else: ?>
        <div class="overflow-x-auto max-h-[600px]">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-10 shadow-sm">
                    <tr class="bg-slate-100 dark:bg-slate-800 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                        <th class="px-8 py-5">Data</th>
                        <th class="px-8 py-5">Operador</th>
                        <th class="px-8 py-5 text-center">Atendidas (Oficial)</th>
                        <th class="px-8 py-5 text-center">Tabuladas (TU)</th>
                        <th class="px-8 py-5 text-center">GAP</th>
                        <th class="px-8 py-5 text-right">Diagnóstico</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                    <?php foreach ($tabela as $r): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors group">
                            <td class="px-8 py-5 text-[11px] font-black text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                <?php echo $r['data']; ?>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center overflow-hidden border border-slate-200/50 dark:border-indigo-500/20 text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase flex-shrink-0">
                                        <?php echo substr($r['operador'], 0, 1); ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-tight truncate w-48" title="<?php echo $r['operador']; ?>"><?php echo $r['operador']; ?></p>
                                        <p class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider truncate w-48" title="<?php echo $r['supervisor']; ?>">Sup: <?php echo $r['supervisor']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-center font-black text-slate-600 dark:text-slate-300 text-lg"><?php echo $r['oficial']; ?></td>
                            <td class="px-8 py-5 text-center font-black text-indigo-600 dark:text-indigo-400 text-lg"><?php echo $r['tu']; ?></td>
                            <td class="px-8 py-5 text-center font-black <?php echo $r['gap'] > 0 ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500'; ?> text-lg">
                                <?php echo $r['gap'] > 0 ? '+'.$r['gap'] : $r['gap']; ?>
                            </td>
                            <td class="px-8 py-5 text-right whitespace-nowrap">
                                <span class="px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border <?php echo getStatusAderenciaStyle($r['status']); ?>">
                                    <?php echo $r['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'shared/ui/footer.php'; ?>