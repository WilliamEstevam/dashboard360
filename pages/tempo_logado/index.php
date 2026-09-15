<?php
$currentView = 'tempo_logado';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';
$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');
$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');
$travaRLS = "";

if ($isCoordenador) {
    $travaRLS = " AND COORDENADOR = '" . dashboard_escape_sql($userNomeOficial) . "' ";
} else if ($isSupervisor) {
    $travaRLS = " AND SUPERVISOR = '" . dashboard_escape_sql($userNomeOficial) . "' ";
}

$sqlData = "SELECT MAX(CAST(DATA_REFERENCIA AS DATE)) as ultima FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO";
$resData = @db_exec($db_conn, $sqlData);
$ultimaData = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d', strtotime('-1 day'));

$filtroData  = isset($_GET['data_ref']) ? $_GET['data_ref'] : $ultimaData;
if (!validar_data($filtroData)) $filtroData = $ultimaData;
$filtroCoord = isset($_GET['coordenador']) ? dashboard_escape_sql($_GET['coordenador']) : '';
$filtroSuper = isset($_GET['supervisor']) ? dashboard_escape_sql($_GET['supervisor']) : '';
if ($isCoordenador) { $filtroCoord = $userNomeOficial; }
if ($isSupervisor) { $filtroSuper = $userNomeOficial; $filtroCoord = ''; }

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

$whereClause = "CAST(DATA_REFERENCIA AS DATE) = '$filtroData' $travaRLS";
if (!empty($filtroCoord)) $whereClause .= " AND COORDENADOR = '$filtroCoord'";
if (!empty($filtroSuper)) $whereClause .= " AND SUPERVISOR = '$filtroSuper'";

$sqlMain = "SELECT NOME_AGENTE, MATRICULA, SUPERVISOR, COORDENADOR, perc_logado, PERC_PAGO 
            FROM consultoria.TBL_PERM_TEMPO_PRODUTIVO 
            WHERE $whereClause 
            ORDER BY perc_logado DESC";

$resMain = @db_exec($db_conn, $sqlMain);

$dadosAgentes = array();
$sumLogado = 0;
$sumPago = 0;
$totalOperadores = 0;

if ($resMain) {
    while (db_fetch_row($resMain)) {
        $rawLogado = db_result($resMain, 'perc_logado');
        $rawPago   = db_result($resMain, 'PERC_PAGO');
        
        $valLogado = round((float)str_replace(',', '.', $rawLogado) * 100, 1);
        $valPago   = round((float)str_replace(',', '.', $rawPago) * 100, 1);
        
        $dadosAgentes[] = array(
            'nome'       => dashboard_utf8_encode(trim(db_result($resMain, 'NOME_AGENTE'))),
            'matricula'  => trim(db_result($resMain, 'MATRICULA')),
            'supervisor' => dashboard_utf8_encode(trim(db_result($resMain, 'SUPERVISOR'))),
            'logado'     => $valLogado,
            'pago'       => $valPago
        );
        
        $sumLogado += $valLogado;
        $sumPago += $valPago;
        $totalOperadores++;
    }
}

$mediaLogado = ($totalOperadores > 0) ? round($sumLogado / $totalOperadores, 1) : 0;
$mediaPago   = ($totalOperadores > 0) ? round($sumPago / $totalOperadores, 1) : 0;

function getMetricaStatus($perc) {
    if ($perc >= 95) {
        return array('label' => 'EXCELENTE', 'card' => 'hover:shadow-indigo-500/10 border-slate-100 dark:border-slate-800', 'badge' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20', 'icon' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-100 dark:border-indigo-500/20', 'bar' => 'from-indigo-500 to-cyan-400', 'bar2' => 'from-emerald-400 to-teal-500', 'text' => 'text-indigo-600 dark:text-indigo-400', 'text2' => 'text-emerald-600 dark:text-emerald-400', 'glow' => 'bg-indigo-500/5');
    } else if ($perc >= 85) {
        return array('label' => 'ATENÇÃO', 'card' => 'hover:shadow-amber-500/10 border-amber-100/50 dark:border-amber-500/20', 'badge' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20', 'icon' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20', 'bar' => 'from-amber-400 to-orange-500', 'bar2' => 'from-indigo-400 to-cyan-500', 'text' => 'text-amber-500', 'text2' => 'text-indigo-600 dark:text-indigo-400', 'glow' => 'bg-amber-500/5');
    } else {
        return array('label' => 'CRÍTICO', 'card' => 'hover:shadow-red-500/10 border-red-100 dark:border-red-500/30', 'badge' => 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-100 dark:border-red-500/20 animate-pulse', 'icon' => 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-100 dark:border-red-500/20', 'bar' => 'from-red-500 to-rose-500', 'bar2' => 'from-red-400 to-pink-500', 'text' => 'text-red-500', 'text2' => 'text-red-500', 'glow' => 'bg-red-500/5');
    }
}
?>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Tempo <span class="text-indigo-600 dark:text-indigo-400">Logado</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                Pausas Consolidadas • Visão Oficial
            </p>
        </div>
        <div class="flex items-center space-x-4">
        </div>
    </div>

    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-10 transition-colors duration-500">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end" id="formTempoLogado">
            <input type="hidden" name="route" value="tempo_logado">
            
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Data Referência</label>
                <input type="date" name="data_ref" value="<?php echo $filtroData; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?>
                        <input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formTempoLogado').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500 transition-colors">
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
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 dark:text-slate-600 outline-none cursor-not-allowed">
                    <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="supervisor" onchange="document.getElementById('formTempoLogado').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500 transition-colors">
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
                    Aplicar
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-xl dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Média % Logado</p>
                <p class="text-4xl font-black text-indigo-600 dark:text-indigo-400 tracking-tighter"><?php echo $mediaLogado; ?>%</p>
            </div>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-xl dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Média % Paga</p>
                <p class="text-4xl font-black text-emerald-600 dark:text-emerald-400 tracking-tighter"><?php echo $mediaPago; ?>%</p>
            </div>
        </div>
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-xl dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Operadores Avaliados</p>
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $totalOperadores; ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        <?php if (empty($dadosAgentes)): ?>
            <div class="col-span-full py-24 text-center bg-white dark:bg-[#0f172a] rounded-[3.5rem] border border-slate-100 dark:border-slate-800 border-dashed">
                <p class="text-slate-400 dark:text-slate-600 font-bold uppercase tracking-widest text-[11px] italic">Nenhum registo consolidado encontrado para os filtros aplicados.</p>
            </div>
        <?php else: foreach ($dadosAgentes as $ag): 
            $status = getMetricaStatus($ag['logado']);
        ?>
            <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border shadow-sm transition-all duration-500 group relative overflow-hidden <?php echo $status['card']; ?>">
                
                <div class="flex items-center justify-between mb-8 relative z-10">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-xl border <?php echo $status['icon']; ?>">
                            <?php echo substr($ag['nome'], 0, 1); ?>
                        </div>
                        <div class="overflow-hidden">
                            <h4 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight truncate w-40" title="<?php echo h($ag['nome']); ?>"><?php echo h($ag['nome']); ?></h4>
                            <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mt-1 truncate w-40" title="<?php echo h($ag['supervisor']); ?>">Sup: <?php echo h($ag['supervisor']); ?></p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase border shadow-sm <?php echo $status['badge']; ?>">
                        <?php echo $status['label']; ?>
                    </span>
                </div>

                <div class="space-y-6 relative z-10">
                    <div>
                        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                            <span class="text-slate-500 dark:text-slate-400">% Logado</span>
                            <span class="<?php echo $status['text']; ?>"><?php echo $ag['logado']; ?>%</span>
                        </div>
                        <div class="w-full h-3 bg-slate-50 dark:bg-slate-800 rounded-full overflow-hidden border border-slate-100 dark:border-slate-700">
                            <div class="h-full bg-gradient-to-r rounded-full <?php echo $status['bar']; ?>" style="width: <?php echo min($ag['logado'], 100); ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                            <span class="text-slate-500 dark:text-slate-400">% Paga</span>
                            <span class="<?php echo $status['text2']; ?>"><?php echo $ag['pago']; ?>%</span>
                        </div>
                        <div class="w-full h-3 bg-slate-50 dark:bg-slate-800 rounded-full overflow-hidden border border-slate-100 dark:border-slate-700">
                            <div class="h-full bg-gradient-to-r rounded-full <?php echo $status['bar2']; ?>" style="width: <?php echo min($ag['pago'], 100); ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-10 -bottom-10 w-40 h-40 rounded-full blur-3xl pointer-events-none group-hover:scale-150 transition-transform duration-700 <?php echo $status['glow']; ?>"></div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include 'shared/ui/footer.php'; ?>