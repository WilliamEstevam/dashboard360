<?php

$currentView = 'banheiro';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$limiteMinutos = 10;

$sqlLimit = "SELECT valor FROM consultoria.TBL_TEMP_parametros_metas WHERE chave = 'limite_banheiro'";
$resLimit = @db_exec($db_conn, $sqlLimit);

if ($resLimit && db_fetch_row($resLimit)) {
    $val = (int)db_result($resLimit, 'valor');
    if ($val > 0) $limiteMinutos = $val;
}

$limiteSegundos = $limiteMinutos * 60; 

$filtroCoord = isset($_POST['coord_banheiro']) ? dashboard_escape_sql($_POST['coord_banheiro']) : '';
$filtroSuper = isset($_POST['super_banheiro']) ? dashboard_escape_sql($_POST['super_banheiro']) : '';

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

$whereClause = "RTRIM(LTRIM(T.status_real)) = 'BANHEIRO'";
if (!empty($filtroCoord)) {
    $whereClause .= " AND T.coordenador = '$filtroCoord'";
}
if (!empty($filtroSuper)) {
    $whereClause .= " AND T.supervisor = '$filtroSuper'";
}

$listaCoords = array();
if (!$isCoordenador && !$isSupervisor) {
    $sqlC = "SELECT DISTINCT coordenador FROM consultoria.tbl_temp_estado_atual WHERE coordenador IS NOT NULL AND RTRIM(LTRIM(status_real)) = 'BANHEIRO' ORDER BY coordenador";
    $resC = @db_exec($db_conn, $sqlC);
    if ($resC) { 
        while (db_fetch_row($resC)) { 
            $listaCoords[] = dashboard_utf8_encode(db_result($resC, 'coordenador')); 
        } 
    }
}

$listaSupers = array();
if (!$isSupervisor) {
    $sqlS = "SELECT DISTINCT supervisor FROM consultoria.tbl_temp_estado_atual WHERE supervisor IS NOT NULL AND RTRIM(LTRIM(status_real)) = 'BANHEIRO'";
    if (!empty($filtroCoord)) $sqlS .= " AND coordenador = '$filtroCoord'";
    $sqlS .= " ORDER BY supervisor";
    
    $resS = @db_exec($db_conn, $sqlS);
    if ($resS) { 
        while (db_fetch_row($resS)) { 
            $listaSupers[] = dashboard_utf8_encode(db_result($resS, 'supervisor')); 
        } 
    }
}

$dados = array();
$kpis = array('quantidade' => 0, 'tempo_total' => '00:00:00', 'alertas' => 0);
$segTotal = 0;

$sqlMain = "SELECT T.matricula, T.nome_operador, T.supervisor, T.coordenador, T.estado_setor, T.uf_setor, T.tempo_status, U.foto_path
            FROM consultoria.tbl_temp_estado_atual T
            LEFT JOIN consultoria.tbl_usuarios_v2 U ON T.matricula = U.matricula
            WHERE $whereClause 
            ORDER BY T.tempo_status DESC";

$resMain = @db_exec($db_conn, $sqlMain);

if ($resMain) {
    while (db_fetch_row($resMain)) {
        $tempoStr = trim(db_result($resMain, 'tempo_status'));
        
        $minutosDecorridos = 0;
        $segundosCard = 0;

        if (!empty($tempoStr)) {
            $t = explode(':', $tempoStr);
            if (count($t) >= 3) {
                $minutosDecorridos = ((int)$t[0] * 60) + (int)$t[1];
                $segundosCard = ((int)$t[0] * 3600) + ((int)$t[1] * 60) + (int)$t[2];
                $segTotal += $segundosCard;
            }
        }

        $isFora = ($minutosDecorridos >= $limiteMinutos);
        
        $dados[] = array(
            'data'       => date('d/m/Y'),
            'matricula'  => trim(db_result($resMain, 'matricula')),
            'agente'     => dashboard_utf8_encode(db_result($resMain, 'nome_operador')),
            'supervisor' => dashboard_utf8_encode(db_result($resMain, 'supervisor')),
            'estado'     => dashboard_utf8_encode(db_result($resMain, 'estado_setor')),
            'uf'         => trim(db_result($resMain, 'uf_setor')),
            'segundos'   => $segundosCard,
            'tempo_fmt'  => empty($tempoStr) ? '00:00:00' : $tempoStr,
            'status_ref' => $isFora ? 'FORA DO NORMAL' : 'DENTRO DO NORMAL',
            'foto'       => trim(db_result($resMain, 'foto_path'))
        );

        $kpis['quantidade']++;
        if ($isFora) $kpis['alertas']++;
    }
}

$h = floor($segTotal / 3600);
$m = floor(($segTotal % 3600) / 60);
$s = $segTotal % 60;
$kpis['tempo_total'] = sprintf('%02d:%02d:%02d', $h, $m, $s);
?>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <!-- Header e Filtros Adaptativos -->
    <div class="mb-10 flex flex-col lg:flex-row lg:items-end justify-between gap-8">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-slate-100 uppercase">Gestão <span class="text-indigo-600 dark:text-indigo-400">Banheiro</span></h2>
            <p class="text-slate-400 dark:text-slate-500 text-sm font-medium mt-1 uppercase tracking-widest italic text-xs">
                Monitoramento Ao Vivo (Limite atual: <span class="text-indigo-500 font-black"><?php echo $limiteMinutos; ?>min</span>)
            </p>
        </div>

        <form method="POST" action="index.php?route=banheiro" class="flex flex-wrap items-center gap-4 bg-white dark:bg-[#0f172a] p-4 rounded-[2rem] shadow-sm border border-slate-100 dark:border-slate-800 transition-colors duration-500">
            
            <div class="flex flex-col px-4 border-r border-slate-100 dark:border-slate-800">
                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Status Base</label>
                <div class="flex items-center mt-1">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Em Tempo Real</span>
                </div>
            </div>

            <!-- Controle de Visibilidade: Coordenador -->
            <div class="flex flex-col px-4 border-r border-slate-100 dark:border-slate-800 min-w-[180px]">
                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="text-xs font-bold text-slate-500 dark:text-slate-600 outline-none border-none p-0 bg-transparent cursor-not-allowed">
                    <?php if ($isCoordenador): ?>
                        <input type="hidden" name="coord_banheiro" value="<?php echo $filtroCoord; ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <select name="coord_banheiro" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 dark:text-slate-300 outline-none border-none p-0 bg-transparent cursor-pointer">
                        <option value="">TODOS</option>
                        <?php foreach($listaCoords as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo ($filtroCoord == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Controle de Visibilidade: Supervisor -->
            <div class="flex flex-col px-4 min-w-[200px]">
                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Supervisor</label>
                <?php if ($isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $filtroSuper; ?>" class="text-xs font-bold text-slate-500 dark:text-slate-600 outline-none border-none p-0 bg-transparent cursor-not-allowed">
                    <input type="hidden" name="super_banheiro" value="<?php echo $filtroSuper; ?>">
                <?php else: ?>
                    <select name="super_banheiro" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 dark:text-slate-300 outline-none border-none p-0 bg-transparent cursor-pointer">
                        <option value="">TODOS</option>
                        <?php foreach($listaSupers as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($filtroSuper == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <button type="submit" class="bg-indigo-600 text-white p-4 rounded-2xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 dark:shadow-none transition-all active:scale-95 ml-2" title="Atualizar Agora">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </form>
    </div>

    <!-- KPIs de Visão Geral -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-lg dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total de Operadores</p>
                <p class="text-4xl font-black text-slate-800 dark:text-slate-100 tracking-tighter"><?php echo $kpis['quantidade']; ?></p>
            </div>
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-2xl flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-lg dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tempo Total Acumulado</p>
                <p class="text-4xl font-black text-indigo-600 dark:text-indigo-400 tracking-tighter italic"><?php echo $kpis['tempo_total']; ?></p>
            </div>
            <div class="w-14 h-14 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500 hover:shadow-lg dark:hover:shadow-indigo-500/10">
            <div>
                <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Fora do Normal (><?php echo $limiteMinutos; ?>min)</p>
                <p class="text-4xl font-black text-red-600 tracking-tighter <?php if($kpis['alertas'] > 0) echo 'animate-pulse'; ?>"><?php echo $kpis['alertas']; ?></p>
            </div>
            <div class="w-14 h-14 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-2xl flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Grid de Agentes Adaptativo -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        <?php if (empty($dados)): ?>
            <div class="col-span-full py-20 text-center bg-white dark:bg-[#0f172a] rounded-[3rem] border border-dashed border-slate-200 dark:border-slate-800">
                <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest text-xs italic">Nenhum operador no banheiro no momento.</p>
            </div>
        <?php else: foreach ($dados as $d): 
            $isFora = ($d['status_ref'] == 'FORA DO NORMAL');
        ?>
            <div class="bg-white dark:bg-[#0f172a] rounded-[2.5rem] p-8 border <?php echo $isFora ? 'border-red-200 dark:border-red-500/50 shadow-[0_10px_30px_-10px_rgba(239,68,68,0.3)]' : 'border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-xl'; ?> transition-all duration-500 group relative overflow-hidden">
                
                <!-- Cálculo percentual dinâmico -->
                <div class="absolute top-8 right-8 w-14 h-14 rounded-full flex items-center justify-center border-2 <?php echo $isFora ? 'border-red-100 dark:border-red-500/20 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400' : 'border-emerald-100 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'; ?> font-black text-xs">
                    <?php echo min(round(($d['segundos'] / ($limiteSegundos > 0 ? $limiteSegundos : 1)) * 100, 0), 999); ?>%
                </div>

                <div class="flex items-center space-x-5 mb-8">
                    <div class="w-16 h-16 rounded-[1.5rem] flex items-center justify-center font-black text-xl shadow-lg border-2 <?php echo $isFora ? 'border-red-400 bg-red-500 text-white' : 'border-slate-100 dark:border-slate-700 bg-indigo-500 text-white'; ?> overflow-hidden">
                        <?php if (!empty($d['foto'])): ?>
                            <img src="<?php echo $d['foto']; ?>" alt="<?php echo $d['agente']; ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span style="display:none;"><?php echo substr($d['agente'], 0, 1); ?></span>
                        <?php else: ?>
                            <span><?php echo substr($d['agente'], 0, 1); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-hidden">
                        <h4 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase truncate w-full tracking-tighter leading-none mb-1" title="<?php echo $d['agente']; ?>"><?php echo $d['agente']; ?></h4>
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Matrícula: <?php echo $d['matricula']; ?></p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mb-8">
                    <span class="px-4 py-1.5 bg-slate-50 dark:bg-slate-900 text-slate-500 rounded-xl text-[9px] font-black uppercase border border-slate-100 dark:border-slate-800 italic"><?php echo $d['uf']; ?> - <?php echo $d['estado']; ?></span>
                    <span class="px-4 py-1.5 bg-slate-50 dark:bg-slate-900 text-slate-500 rounded-xl text-[9px] font-black uppercase border border-slate-100 dark:border-slate-800">Sup: <?php echo $d['supervisor']; ?></span>
                </div>

                <div class="pt-6 border-t border-slate-50 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-[9px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-widest mb-1">Tempo Decorrido</p>
                        <p class="text-2xl font-black italic tracking-tighter <?php echo $isFora ? 'text-red-600 dark:text-red-400 animate-pulse' : 'text-indigo-600 dark:text-indigo-400'; ?>">
                            <?php echo $d['tempo_fmt']; ?>
                        </p>
                    </div>

                    <div class="flex flex-col items-end">
                         <span class="flex items-center px-3 py-1 rounded-lg text-[9px] font-black uppercase <?php echo $isFora ? 'text-red-600 bg-red-50 dark:bg-red-500/10' : 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10'; ?>">
                            <?php echo $d['status_ref']; ?>
                         </span>
                         <p class="text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase mt-2 truncate w-32 text-right" title="<?php echo $d['supervisor']; ?>">SUP: <?php echo $d['supervisor']; ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
setTimeout(function() {
    window.location.reload();
}, 60000);
</script>

<?php include 'shared/ui/footer.php'; ?>