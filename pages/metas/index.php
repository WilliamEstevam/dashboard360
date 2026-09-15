<?php

$currentView = 'metas';
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

$metaRetencao = 60.0;
$metaCancelamento = 20.0;

$sqlParams = "SELECT chave, valor FROM consultoria.TBL_TEMP_parametros_metas WHERE chave IN ('meta_retencao', 'meta_cancelamento')";
$resParams = @db_exec($db_conn, $sqlParams);

if ($resParams) {
    while (db_fetch_row($resParams)) {
        $chave = trim(db_result($resParams, 'chave'));
        $valor = (float)db_result($resParams, 'valor');
        if ($chave === 'meta_retencao') $metaRetencao = $valor;
        if ($chave === 'meta_cancelamento') $metaCancelamento = $valor;
    }
}

$sqlUltima = "SELECT MAX(CAST(DATA_REFERENCIA AS DATE)) as ultima FROM consultoria.TBL_PERM_RELATORIO_TU";
$resUltima = @db_exec($db_conn, $sqlUltima);
$ultimaData = ($resUltima && db_fetch_row($resUltima)) ? db_result($resUltima, 'ultima') : date('Y-m-d');

$mesRef = date('m', strtotime($ultimaData));
$anoRef = date('Y', strtotime($ultimaData));

$mesesPt = array('01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro');
$mesDisplay = $mesesPt[$mesRef] . ' de ' . $anoRef;

$sqlMes = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%CANCELADO%' OR UPPER(HIERARQUIA_N4) LIKE '%NÃO RETIDO%' THEN 1 ELSE 0 END) as cancelados
FROM consultoria.TBL_PERM_RELATORIO_TU 
WHERE MONTH(DATA_REFERENCIA) = $mesRef AND YEAR(DATA_REFERENCIA) = $anoRef $travaRLS_TU";

$resMes = @db_exec($db_conn, $sqlMes);

$mesTotal = 0; $mesRetencao = 0; $mesCancelamento = 0;
if ($resMes && db_fetch_row($resMes)) {
    $mesTotal = (int)db_result($resMes, 'total');
    if ($mesTotal > 0) {
        $mesRetencao = round(((int)db_result($resMes, 'retidos') / $mesTotal) * 100, 1);
        $mesCancelamento = round(((int)db_result($resMes, 'cancelados') / $mesTotal) * 100, 1);
    }
}

$sqlTrend = "SELECT TOP 15 
    CAST(DATA_REFERENCIA AS DATE) as data_ref,
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%RETIDO%' AND UPPER(HIERARQUIA_N4) NOT LIKE '%NÃO%' THEN 1 ELSE 0 END) as retidos,
    SUM(CASE WHEN UPPER(HIERARQUIA_N4) LIKE '%CANCELADO%' OR UPPER(HIERARQUIA_N4) LIKE '%NÃO RETIDO%' THEN 1 ELSE 0 END) as cancelados
FROM consultoria.TBL_PERM_RELATORIO_TU
WHERE 1=1 $travaRLS_TU
GROUP BY CAST(DATA_REFERENCIA AS DATE)
ORDER BY data_ref DESC";

$resTrend = @db_exec($db_conn, $sqlTrend);

$chartLabels = array();
$chartRetencao = array();
$chartMeta = array();
$historicoDias = array();

if ($resTrend) {
    while (db_fetch_row($resTrend)) {
        $t = (int)db_result($resTrend, 'total');
        $r = (int)db_result($resTrend, 'retidos');
        $c = (int)db_result($resTrend, 'cancelados');
        $dt = db_result($resTrend, 'data_ref');
        
        $pctRet = ($t > 0) ? round(($r / $t) * 100, 1) : 0;
        $pctCan = ($t > 0) ? round(($c / $t) * 100, 1) : 0;
        
        $chartLabels[] = date('d/m', strtotime($dt));
        $chartRetencao[] = $pctRet;
        $chartMeta[] = $metaRetencao;
        
        $historicoDias[] = array(
            'data' => date('d/m/Y', strtotime($dt)),
            'retencao' => $pctRet,
            'cancelamento' => $pctCan,
            'total' => $t
        );
    }
}

$chartLabels = array_reverse($chartLabels);
$chartRetencao = array_reverse($chartRetencao);
$chartMeta = array_reverse($chartMeta);
?>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Metas & <span class="text-indigo-600 dark:text-indigo-400">Performance</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                <?php echo ($isSupervisor || $isCoordenador) ? 'Acompanhamento Consolidado da Equipe' : 'Acompanhamento Consolidado da Operação'; ?>
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            <div class="bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 px-6 py-3 rounded-2xl shadow-sm text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest flex items-center">
                <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Mês de Referência: <span class="text-indigo-600 dark:text-indigo-400 ml-1"><?php echo $mesDisplay; ?></span>
            </div>
            
            <?php if (isset($user['cargo']) && in_array(strtolower($user['cargo']), array('administrador', 'gerente'))): ?>
            <a href="index.php?route=ajustes_pausa" class="px-6 py-3 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all active:scale-95 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Ajustar Metas
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
        
        <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:shadow-xl dark:hover:shadow-indigo-500/10 transition-all">
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Atendimentos no Mês</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                            <?php echo ($isSupervisor || $isCoordenador) ? 'Volume Acumulado da Equipe' : 'Volume Acumulado Geral'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="relative z-10 mb-2">
                <div class="flex items-baseline space-x-2">
                    <span class="text-5xl font-black italic tracking-tighter text-slate-800 dark:text-white"><?php echo number_format($mesTotal, 0, ',', '.'); ?></span>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-500/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <?php 
            $progressoRet = min(($mesRetencao / ($metaRetencao > 0 ? $metaRetencao : 1)) * 100, 100);
            $atingiuRet = ($mesRetencao >= $metaRetencao);
            $corRet = $atingiuRet ? 'emerald' : 'amber';
        ?>
        <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:shadow-xl dark:hover:shadow-indigo-500/10 transition-all">
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Retenção Mês</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Meta Mínima: <?php echo $metaRetencao; ?>%</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-<?php echo $corRet; ?>-50 dark:bg-<?php echo $corRet; ?>-500/10 text-<?php echo $corRet; ?>-600 dark:text-<?php echo $corRet; ?>-400 border-<?php echo $corRet; ?>-200 dark:border-<?php echo $corRet; ?>-500/20">
                    <?php echo $atingiuRet ? 'Meta Atingida' : 'Abaixo do Esperado'; ?>
                </span>
            </div>
            <div class="relative z-10 mb-6">
                <div class="flex items-baseline space-x-2">
                    <span class="text-5xl font-black italic tracking-tighter text-slate-800 dark:text-white"><?php echo $mesRetencao; ?></span>
                    <span class="text-xl font-black text-slate-400">%</span>
                </div>
            </div>
            <div class="relative z-10">
                <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">
                    <span>Progresso</span>
                    <span><?php echo round($progressoRet); ?>% da meta</span>
                </div>
                <div class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 bg-indigo-500 dark:bg-indigo-400" style="width: <?php echo $progressoRet; ?>%;"></div>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <?php 
            $progressoCan = min(($mesCancelamento / ($metaCancelamento > 0 ? $metaCancelamento : 1)) * 100, 100);
            $atingiuCan = ($mesCancelamento <= $metaCancelamento);
            $corCan = $atingiuCan ? 'emerald' : 'red';
        ?>
        <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:shadow-xl dark:hover:shadow-indigo-500/10 transition-all">
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Cancelamento Mês</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Meta Máxima: <?php echo $metaCancelamento; ?>%</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-<?php echo $corCan; ?>-50 dark:bg-<?php echo $corCan; ?>-500/10 text-<?php echo $corCan; ?>-600 dark:text-<?php echo $corCan; ?>-400 border-<?php echo $corCan; ?>-200 dark:border-<?php echo $corCan; ?>-500/20">
                    <?php echo $atingiuCan ? 'Sob Controle' : 'Alerta Excedido'; ?>
                </span>
            </div>
            <div class="relative z-10 mb-6">
                <div class="flex items-baseline space-x-2">
                    <span class="text-5xl font-black italic tracking-tighter text-slate-800 dark:text-white"><?php echo $mesCancelamento; ?></span>
                    <span class="text-xl font-black text-slate-400">%</span>
                </div>
            </div>
            <div class="relative z-10">
                <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">
                    <span>Ocupação da Meta</span>
                    <span><?php echo round($progressoCan); ?>%</span>
                </div>
                <div class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 bg-<?php echo $corCan; ?>-500 dark:bg-<?php echo $corCan; ?>-400" style="width: <?php echo $progressoCan; ?>%;"></div>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-rose-500/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col">
            <div class="mb-6">
                <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Tendência de <span class="text-indigo-600 dark:text-indigo-400">Retenção</span></h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Evolução dos últimos 15 dias vs Meta (<?php echo $metaRetencao; ?>%)</p>
            </div>
            <div class="flex-grow w-full min-h-[300px]">
                <canvas id="chartTendenciaReal"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black italic text-slate-800 dark:text-white uppercase tracking-tighter">Diário <span class="text-emerald-500">Consolidado</span></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecho dos últimos dias letivos</p>
                </div>
            </div>
            
            <div class="overflow-y-auto flex-grow max-h-[300px] pr-2 custom-scroll">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">
                            <th class="py-4 sticky top-0 bg-white dark:bg-[#0f172a]">Data Ref.</th>
                            <th class="py-4 sticky top-0 bg-white dark:bg-[#0f172a] text-center">Atendimentos</th>
                            <th class="py-4 sticky top-0 bg-white dark:bg-[#0f172a] text-center">Retenção</th>
                            <th class="py-4 sticky top-0 bg-white dark:bg-[#0f172a] text-center">Cancelamento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                        <?php foreach ($historicoDias as $h): 
                            $atingiu = ($h['retencao'] >= $metaRetencao);
                        ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="py-5">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-2 h-2 rounded-full <?php echo $atingiu ? 'bg-emerald-500' : 'bg-amber-500'; ?>"></div>
                                        <span class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase"><?php echo $h['data']; ?></span>
                                    </div>
                                </td>
                                <td class="py-5 text-center font-bold text-slate-600 dark:text-slate-400"><?php echo $h['total']; ?></td>
                                <td class="py-5 text-center font-black <?php echo $h['retencao'] >= $metaRetencao ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 dark:text-amber-400'; ?>"><?php echo $h['retencao']; ?>%</td>
                                <td class="py-5 text-center font-black <?php echo $h['cancelamento'] <= $metaCancelamento ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'; ?>"><?php echo $h['cancelamento']; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#1e293b' : '#f1f5f9';

    const ctx = document.getElementById('chartTendenciaReal');
    if (ctx) {
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.2)'); // Indigo
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        type: 'line',
                        label: 'Meta',
                        data: <?php echo json_encode($chartMeta); ?>,
                        borderColor: isDark ? '#475569' : '#cbd5e1',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0
                    },
                    {
                        type: 'bar',
                        label: 'Retenção Atingida',
                        data: <?php echo json_encode($chartRetencao); ?>,
                        backgroundColor: isDark ? '#6366f1' : '#4f46e5', 
                        borderRadius: 8,
                        barPercentage: 0.5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        min: 30, max: 100,
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: textColor, font: { size: 10, weight: 'bold' }, callback: v => v + '%' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 10, weight: 'bold' } }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'shared/ui/footer.php'; ?>