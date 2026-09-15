<?php
$currentView = 'dashboard';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');

$travaRLS_TU = "";

if ($isCoordenador) {
    $nomeEscapado = dashboard_escape_sql($userNomeOficial);
    $travaRLS_TU = " AND COORDENADOR = '$nomeEscapado' ";
} else if ($isSupervisor) {
    $nomeEscapado = dashboard_escape_sql($userNomeOficial);
    $travaRLS_TU = " AND SUPERVISOR_QUADRO = '$nomeEscapado' ";
}

$sqlData = "SELECT MAX(CAST(DATA_REFERENCIA AS DATE)) as ultima FROM consultoria.TBL_PERM_RELATORIO_TU";
$resData = @db_exec($db_conn, $sqlData);
$ultimaData = ($resData && db_fetch_row($resData)) ? db_result($resData, 'ultima') : date('Y-m-d', strtotime('-1 day'));
$dataAnterior = date('Y-m-d', strtotime($ultimaData . ' -1 day'));

// Formatação visual da data
$dataVisualD1 = date('d/m/Y', strtotime($ultimaData));

$sqlKpis = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%CANCELADO%' OR UPPER(HIERARQUIA_N4) LIKE '%NÃO RETIDO%' THEN 1 ELSE 0 END) as cancelados
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE CAST(DATA_REFERENCIA AS DATE) = '$ultimaData' $travaRLS_TU";

$resKpis = @db_exec($db_conn, $sqlKpis);

$kpiTotal = 0;
$kpiRetencao = 0;
$kpiCancelamento = 0;

if ($resKpis && db_fetch_row($resKpis)) {
    $kpiTotal = (int)db_result($resKpis, 'total');
    $retidos = (int)db_result($resKpis, 'retidos');
    $cancelados = (int)db_result($resKpis, 'cancelados');
    
    if ($kpiTotal > 0) {
        $kpiRetencao = round(($retidos / $kpiTotal) * 100, 1);
        $kpiCancelamento = round(($cancelados / $kpiTotal) * 100, 1);
    }
}

// 2.1 Buscar TOP UF
$sqlUF = "SELECT TOP 1 UF, COUNT(*) as qtd 
          FROM consultoria.TBL_PERM_RELATORIO_TU 
          WHERE CAST(DATA_REFERENCIA AS DATE) = '$ultimaData' 
            AND UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' 
            AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' 
            $travaRLS_TU
          GROUP BY UF 
          ORDER BY qtd DESC";
$resUF = @db_exec($db_conn, $sqlUF);
$topUF = "N/A";
if ($resUF && db_fetch_row($resUF)) {
    $ufValue = db_result($resUF, 'UF');
    if (!empty($ufValue)) $topUF = strtoupper(trim($ufValue));
}

$sqlTrend = "SELECT TOP 15 
    CAST(DATA_REFERENCIA AS DATE) as data_ref,
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE 1=1 $travaRLS_TU
GROUP BY CAST(DATA_REFERENCIA AS DATE)
ORDER BY data_ref DESC";

$resTrend = @db_exec($db_conn, $sqlTrend);
$chartLabels = array();
$chartRetencao = array();
$chartMeta = array(); 

if ($resTrend) {
    while (db_fetch_row($resTrend)) {
        $tot = (int)db_result($resTrend, 'total');
        $ret = (int)db_result($resTrend, 'retidos');
        $pct = ($tot > 0) ? round(($ret / $tot) * 100, 1) : 0;
        
        $chartLabels[] = date('d/m', strtotime(db_result($resTrend, 'data_ref')));
        $chartRetencao[] = $pct;
        $chartMeta[] = 65.0; 
    }
}
// Inverter para ficar cronológico (da esquerda para a direita)
$chartLabels = array_reverse($chartLabels);
$chartRetencao = array_reverse($chartRetencao);

$sqlN4 = "SELECT TOP 4 HIERARQUIA_N4, COUNT(*) as qtd 
          FROM consultoria.TBL_PERM_RELATORIO_TU 
          WHERE CAST(DATA_REFERENCIA AS DATE) = '$ultimaData' $travaRLS_TU
          GROUP BY HIERARQUIA_N4 
          ORDER BY qtd DESC";

$resN4 = @db_exec($db_conn, $sqlN4);
$chartN4Keys = array();
$chartN4Values = array();

if ($resN4) {
    while (db_fetch_row($resN4)) {
        $n4Label = trim(db_result($resN4, 'HIERARQUIA_N4'));
        $chartN4Keys[] = !empty($n4Label) ? dashboard_utf8_encode($n4Label) : 'Outros';
        $chartN4Values[] = (int)db_result($resN4, 'qtd');
    }
}

$sqlTopOp = "SELECT TOP 4 NOME_AGENTE, SUPERVISOR_QUADRO, 
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    COUNT(*) as total
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE CAST(DATA_REFERENCIA AS DATE) = '$ultimaData' $travaRLS_TU
GROUP BY NOME_AGENTE, SUPERVISOR_QUADRO
ORDER BY retidos DESC";

$resTopOp = @db_exec($db_conn, $sqlTopOp);
$topOperadores = array();

if ($resTopOp) {
    while (db_fetch_row($resTopOp)) {
        $topOperadores[] = array(
            'nome' => dashboard_utf8_encode(db_result($resTopOp, 'NOME_AGENTE')),
            'supervisor' => dashboard_utf8_encode(db_result($resTopOp, 'SUPERVISOR_QUADRO')),
            'score' => (int)db_result($resTopOp, 'retidos')
        );
    }
}

$sqlD2 = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE CAST(DATA_REFERENCIA AS DATE) = '$dataAnterior' $travaRLS_TU";

$resD2 = @db_exec($db_conn, $sqlD2);
$kpiRetencaoD2 = 0;
if ($resD2 && db_fetch_row($resD2)) {
    $totD2 = (int)db_result($resD2, 'total');
    $retD2 = (int)db_result($resD2, 'retidos');
    if ($totD2 > 0) $kpiRetencaoD2 = round(($retD2 / $totD2) * 100, 1);
}

$insights = array();
$delta = $kpiRetencao - $kpiRetencaoD2;
if ($delta > 0) {
    $insights[] = array('tipo' => 'success', 'titulo' => 'Crescimento de Retenção', 'texto' => "A retenção subiu {$delta}% em comparação ao dia anterior.");
} else if ($delta < 0) {
    $insights[] = array('tipo' => 'danger', 'titulo' => 'Alerta de Queda', 'texto' => "A retenção caiu " . abs($delta) . "% em comparação ao dia anterior.");
} else {
    $insights[] = array('tipo' => 'warning', 'titulo' => 'Estabilidade', 'texto' => "A retenção manteve-se inalterada em relação ao dia anterior.");
}

$insights[] = array('tipo' => 'success', 'titulo' => 'Destaque Regional', 'texto' => "A região de {$topUF} lidera as retenções do dia.");
?>

<!-- Injeção dos dados reais para o JS Automático -->
<script>
    window.DashboardDashboardData = {
        chartLabels: <?php echo json_encode($chartLabels); ?>,
        chartRetencao: <?php echo json_encode($chartRetencao); ?>,
        chartMeta: <?php echo json_encode($chartMeta); ?>,
        chartStatusKeys: <?php echo json_encode($chartN4Keys); ?>,
        chartStatusValues: <?php echo json_encode($chartN4Values); ?>
    };
</script>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <!-- Título Principal -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">Painel de <span class="text-indigo-600 dark:text-indigo-400">Controle</span></h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                Inteligência TU • Base Fechada
            </p>
        </div>
        <div class="flex space-x-3">
            <span class="px-4 py-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl text-[10px] font-black uppercase tracking-widest border border-indigo-200 dark:border-indigo-500/20 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Dados D-1 (<?php echo $dataVisualD1; ?>)
            </span>
        </div>
    </div>

    <!-- 1. Row: KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white dark:bg-[#0f172a] rounded-[2rem] p-6 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                </div>
                <span class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Nº Atendimentos</span>
            </div>
            <div class="relative z-10">
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo number_format($kpiTotal, 0, ',', '.'); ?></p>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-500/5 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] rounded-[2rem] p-6 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <span class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">% Retenção</span>
            </div>
            <div class="relative z-10">
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $kpiRetencao; ?>%</p>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] rounded-[2rem] p-6 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                <span class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">% Cancelamento</span>
            </div>
            <div class="relative z-10">
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $kpiCancelamento; ?>%</p>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-red-500/5 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] rounded-[2rem] p-6 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Top UF (Retenção)</span>
            </div>
            <div class="relative z-10">
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $topUF; ?></p>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>
    </div>

    <!-- 2. Row: Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Evolução <span class="text-indigo-600 dark:text-indigo-400">Diária</span></h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Acompanhamento dos últimos 15 dias úteis</p>
            </div>
            <div class="h-[300px] w-full"><canvas id="mockLineChart"></canvas></div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col">
            <div class="mb-6">
                <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Desfechos <span class="text-cyan-500">N4</span></h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tipos de Atendimentos no Dia</p>
            </div>
            <div class="flex-grow flex items-center justify-center relative">
                <?php if (empty($chartN4Keys)): ?>
                    <p class="text-slate-400 dark:text-slate-600 text-xs font-bold uppercase tracking-widest italic">Sem dados D-1</p>
                <?php else: ?>
                    <div class="h-[240px] w-full relative"><canvas id="mockDoughnutChart"></canvas></div>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo number_format($kpiTotal, 0, ',', '.'); ?></span>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Row: Insights e Top Operadores -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 shadow-sm">
            <div class="flex items-center mb-6">
                <span class="w-2 h-6 bg-indigo-500 rounded-full mr-4"></span>
                <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Live Insights <span class="text-indigo-600 dark:text-indigo-400">D-1</span></h3>
            </div>
            <div class="space-y-4">
                <?php foreach($insights as $in): 
                    $theme = $in['tipo'] == 'success' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20' : 
                            ($in['tipo'] == 'warning' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20' : 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-100 dark:border-red-500/20');
                ?>
                <div class="p-4 rounded-2xl border flex items-start <?php echo $theme; ?>">
                    <div class="mr-4 mt-0.5"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                    <div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest mb-1"><?php echo $in['titulo']; ?></h4>
                        <p class="text-sm font-medium opacity-90"><?php echo $in['texto']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 shadow-sm">
            <div class="flex items-center mb-6">
                <span class="w-2 h-6 bg-cyan-500 rounded-full mr-4"></span>
                <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Top Operadores <span class="text-cyan-500">D-1</span></h3>
            </div>
            <div class="space-y-3">
                <?php if (empty($topOperadores)): ?>
                    <p class="text-slate-400 dark:text-slate-600 text-xs font-bold uppercase tracking-widest italic py-4 text-center">Nenhum operador para exibir.</p>
                <?php else: foreach($topOperadores as $idx => $op): ?>
                <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-black text-slate-500 dark:text-slate-400">#<?php echo $idx + 1; ?></div>
                        <div class="overflow-hidden">
                            <p class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight truncate w-32 md:w-48"><?php echo $op['nome']; ?></p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest truncate w-32 md:w-48">Sup: <?php echo $op['supervisor']; ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-black italic tracking-tighter text-cyan-500"><?php echo $op['score']; ?></p>
                        <p class="text-[8px] font-bold uppercase text-slate-400">Retenções</p>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="shared/assets/js/dashboard.js"></script>

<?php include 'shared/ui/footer.php'; ?>