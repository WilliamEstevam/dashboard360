<?php
$currentView = 'pausas';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$sqlHealth = "SELECT 
                MAX(ultima_atualizacao) as last_update,
                CASE WHEN MAX(ultima_atualizacao) >= DATEADD(minute, -5, GETDATE()) THEN 1 ELSE 0 END as is_online
              FROM consultoria.tbl_temp_estado_atual";
$resHealth = @db_exec($db_conn, $sqlHealth);

$isBotOnline = true; 
$lastUpdateStr = 'Desconhecido';

if ($resHealth && db_fetch_row($resHealth)) {
    $isBotOnline = (int)db_result($resHealth, 'is_online') === 1;
    $rawDate = db_result($resHealth, 'last_update');
    if (!empty($rawDate)) {
        $lastUpdateStr = date('H:i:s', strtotime($rawDate));
    }
}

$limitesPausa = array(
    'LANCHE'      => 30,
    'BANHEIRO'    => 10,
    'DESCANSO'    => 10,
    'REFEICAO'    => 60,
    'BACK OFFICE' => 60,
    'DEFEITO'     => 30,
    'FEEDBACK'    => 30
);

$sqlParams = "SELECT chave, valor FROM consultoria.TBL_TEMP_parametros_metas WHERE categoria = 'pausas'";
$resParams = @db_exec($db_conn, $sqlParams);

if ($resParams) {
    while (db_fetch_row($resParams)) {
        $chave = strtolower(trim(db_result($resParams, 'chave')));
        $valor = (int)db_result($resParams, 'valor');
        
        if ($chave === 'limite_lanche')   $limitesPausa['LANCHE'] = $valor;
        if ($chave === 'limite_banheiro') $limitesPausa['BANHEIRO'] = $valor;
        if ($chave === 'limite_descanso') $limitesPausa['DESCANSO'] = $valor;
        if ($chave === 'limite_refeicao') $limitesPausa['REFEICAO'] = $valor;
    }
}

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');

$travaRLS = "";
if ($isCoordenador) {
    $travaRLS = " AND T.coordenador = '" . dashboard_escape_sql($userNomeOficial) . "' ";
} else if ($isSupervisor) {
    $travaRLS = " AND T.supervisor = '" . dashboard_escape_sql($userNomeOficial) . "' ";
}

$statusFiltro = "'LANCHE', 'BANHEIRO', 'DESCANSO', 'REFEICAO', 'BACK OFFICE', 'DEFEITO', 'FEEDBACK'";

$sql = "SELECT T.matricula, T.nome_operador, T.supervisor, T.status_real, T.tempo_status, U.foto_path
        FROM consultoria.tbl_temp_estado_atual T
        LEFT JOIN consultoria.tbl_usuarios_v2 U ON T.matricula = U.matricula
        WHERE RTRIM(LTRIM(T.status_real)) IN ($statusFiltro)
        AND T.ultima_atualizacao >= DATEADD(minute, -5, GETDATE()) 
        $travaRLS
        ORDER BY T.tempo_status DESC";

$res = @db_exec($db_conn, $sql);

$pausasData = array();
$resumoPausas = array('total' => 0, 'excedidas' => 0, 'tempo_total' => '00:00:00');
$kpisPausas = array();
$segTotal = 0;

if ($res) {
    while (db_fetch_row($res)) {
        $status = strtoupper(trim(db_result($res, 'status_real')));
        $tempoStr = trim(db_result($res, 'tempo_status'));
        
        $minutosDecorridos = 0;
        if (!empty($tempoStr)) {
            $t = explode(':', $tempoStr);
            if (count($t) >= 3) {
                $minutosDecorridos = ((int)$t[0] * 60) + (int)$t[1];
                $segTotal += ((int)$t[0] * 3600) + ((int)$t[1] * 60) + (int)$t[2];
            }
        }

        $limiteAtual = isset($limitesPausa[$status]) ? $limitesPausa[$status] : 60;
        $estourou = ($minutosDecorridos >= $limiteAtual);

        $pausasData[] = array(
            'matricula'  => trim(db_result($res, 'matricula')),
            'nome'       => dashboard_utf8_encode(db_result($res, 'nome_operador')),
            'supervisor' => dashboard_utf8_encode(db_result($res, 'supervisor')),
            'status'     => $status,
            'tempo'      => $tempoStr,
            'estourou'   => $estourou,
            'foto'       => trim(db_result($res, 'foto_path'))
        );

        $resumoPausas['total']++;
        if ($estourou) $resumoPausas['excedidas']++;

        if (!isset($kpisPausas[$status])) $kpisPausas[$status] = 0;
        $kpisPausas[$status]++;
    }
}

$h = floor($segTotal / 3600);
$m = floor(($segTotal % 3600) / 60);
$s = $segTotal % 60;
$resumoPausas['tempo_total'] = sprintf('%02d:%02d:%02d', $h, $m, $s);

function getColorPorPausa($status) {
    if (strpos($status, 'LANCHE') !== false) return 'blue';
    if (strpos($status, 'BANHEIRO') !== false) return 'indigo';
    if (strpos($status, 'DESCANSO') !== false) return 'emerald';
    if (strpos($status, 'FEEDBACK') !== false) return 'amber';
    if (strpos($status, 'REFEICAO') !== false) return 'rose';
    if (strpos($status, 'BACK OFFICE') !== false) return 'slate';
    if (strpos($status, 'DEFEITO') !== false) return 'orange';
    return 'slate';
}
?>

<div class="p-8 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Monitor <span class="text-indigo-600 dark:text-indigo-400">WFM</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">Visão global das pausas em tempo real</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button onclick="window.location.reload();" class="px-6 py-3 bg-white dark:bg-[#0f172a] hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all active:scale-95 shadow-sm flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Sincronizar Agora
            </button>
            
            <?php if ($isBotOnline): ?>
                <span class="px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest border border-emerald-200 dark:border-emerald-500/20 flex items-center">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span> Conectado
                </span>
            <?php else: ?>
                <span class="px-4 py-3 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest border border-red-200 dark:border-red-500/20 flex items-center shadow-[0_0_15px_rgba(239,68,68,0.2)]" title="Última atualização: <?php echo $lastUpdateStr; ?>">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Robô Offline
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isBotOnline): ?>
        <div class="mb-8 p-6 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-2xl flex items-start space-x-4 animate-in slide-in-from-top duration-500">
            <div class="text-red-600 dark:text-red-400 mt-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <h4 class="text-xs font-black text-red-700 dark:text-red-400 uppercase tracking-widest mb-1">Atenção: Sincronização Pausada</h4>
                <p class="text-xs text-red-600 dark:text-red-300 font-medium">O sistema parou de enviar dados para a base às <span class="font-bold"><?php echo $lastUpdateStr; ?></span>. Para evitar dados incorretos, a tela esta vazia. Contate a equipe .</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 <?php if(!$isBotOnline) echo 'opacity-50 grayscale pointer-events-none'; ?>">
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-transform hover:-translate-y-1">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Agentes em Pausa</p>
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $resumoPausas['total']; ?></p>
            </div>
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between group transition-transform hover:-translate-y-1 <?php if($resumoPausas['excedidas'] > 0) echo 'border-red-200 dark:border-red-500/30'; ?>">
            <div>
                <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Pausas Estouradas</p>
                <p class="text-4xl font-black text-red-600 tracking-tighter <?php if($resumoPausas['excedidas'] > 0) echo 'animate-pulse'; ?>"><?php echo $resumoPausas['excedidas']; ?></p>
            </div>
            <div class="w-14 h-14 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-transform hover:-translate-y-1">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Acumulado</p>
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter italic"><?php echo $resumoPausas['tempo_total']; ?></p>
            </div>
            <div class="w-14 h-14 bg-slate-50 dark:bg-slate-800/50 text-slate-400 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 mb-8 <?php if(!$isBotOnline) echo 'hidden'; ?>">
        <button onclick="filtrarCards('TODOS')" id="btn-filtro-TODOS" class="filtro-btn px-6 py-3 rounded-[1rem] bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none transition-all scale-105 border border-transparent">
            Todos (<?php echo $resumoPausas['total']; ?>)
        </button>
        <?php foreach ($kpisPausas as $status => $qtd): 
            $cor = getColorPorPausa($status);
        ?>
            <button onclick="filtrarCards('<?php echo $status; ?>')" id="btn-filtro-<?php echo md5($status); ?>" class="filtro-btn px-6 py-3 rounded-[1rem] bg-white dark:bg-[#0f172a] text-slate-500 dark:text-slate-400 font-bold text-[10px] uppercase tracking-widest border border-slate-200 dark:border-slate-700 hover:border-<?php echo $cor; ?>-400 hover:text-<?php echo $cor; ?>-600 dark:hover:text-<?php echo $cor; ?>-400 transition-all">
                <?php echo $status; ?> (<?php echo $qtd; ?>)
            </button>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="grid-operadores">
        <?php if (!$isBotOnline || empty($pausasData)): ?>
            <div class="col-span-full py-20 text-center bg-white dark:bg-[#0f172a] rounded-[3rem] border border-dashed border-slate-200 dark:border-slate-800">
                <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest text-xs italic">Nenhum operador em pausa (ou robô inativo).</p>
            </div>
        <?php else: foreach ($pausasData as $p): 
            $corBase = getColorPorPausa($p['status']);
            $isEstourou = $p['estourou'];
        ?>
        <div class="card-operador bg-white dark:bg-[#0f172a] rounded-[2.5rem] border <?php echo $isEstourou ? 'border-red-200 dark:border-red-500/50 shadow-[0_10px_30px_-10px_rgba(239,68,68,0.3)]' : 'border-slate-100 dark:border-slate-800 shadow-sm'; ?> p-8 relative overflow-hidden transition-all duration-500 hover:-translate-y-1" data-status="<?php echo $p['status']; ?>">
            
            <div class="absolute top-6 right-6">
                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?php echo $isEstourou ? 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/20' : 'bg-'.$corBase.'-50 dark:bg-'.$corBase.'-500/10 text-'.$corBase.'-600 dark:text-'.$corBase.'-400 border-'.$corBase.'-100 dark:border-'.$corBase.'-500/20'; ?>">
                    <?php echo $p['status']; ?>
                </span>
            </div>

            <div class="flex items-center space-x-4 mb-6 mt-2">
                <div class="w-14 h-14 rounded-[1.2rem] overflow-hidden border-2 flex items-center justify-center font-black text-white <?php echo $isEstourou ? 'border-red-400 bg-red-500' : 'border-slate-100 dark:border-slate-700 bg-indigo-500'; ?>">
                    <?php if (!empty($p['foto'])): ?>
                        <img src="<?php echo $p['foto']; ?>" alt="<?php echo $p['nome']; ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <span style="display:none;"><?php echo substr($p['nome'], 0, 1); ?></span>
                    <?php else: ?>
                        <span><?php echo substr($p['nome'], 0, 1); ?></span>
                    <?php endif; ?>
                </div>
                <div class="pr-10 overflow-hidden"> 
                    <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter leading-none truncate w-full" title="<?php echo $p['nome']; ?>"><?php echo $p['nome']; ?></h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 truncate">Mat: <?php echo $p['matricula']; ?></p>
                </div>
            </div>

            <div class="text-center py-6 bg-slate-50/50 dark:bg-slate-800/30 rounded-3xl border border-slate-50 dark:border-slate-800/50 mb-6 relative z-10">
                <p class="text-[9px] font-black uppercase tracking-[0.3em] mb-2 <?php echo $isEstourou ? 'text-red-500' : 'text-slate-400 dark:text-slate-500'; ?>">
                    Tempo Decorrido
                </p>
                <p class="text-5xl font-black italic tracking-tighter <?php echo $isEstourou ? 'text-red-600 dark:text-red-400 animate-pulse' : 'text-slate-800 dark:text-white'; ?>">
                    <?php echo $p['tempo']; ?>
                </p>
                <?php if($isEstourou): ?>
                    <p class="text-[10px] font-bold text-red-500 uppercase tracking-widest mt-3 flex items-center justify-center">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Limite Excedido
                    </p>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-between relative z-10">
                <div class="flex items-center text-slate-400 dark:text-slate-500 overflow-hidden">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span class="text-[9px] font-bold uppercase tracking-widest truncate w-32" title="<?php echo $p['supervisor']; ?>">Sup: <?php echo $p['supervisor']; ?></span>
                </div>
            </div>
            
            <?php if($isEstourou): ?>
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-red-500/10 rounded-full blur-2xl pointer-events-none"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function filtrarCards(tipo) {
    const botoes = document.querySelectorAll('.filtro-btn');
    botoes.forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white', 'scale-105', 'shadow-lg');
        btn.classList.add('bg-white', 'dark:bg-[#0f172a]', 'text-slate-500');
    });

    const idBtn = 'btn-filtro-' + md5_simulado(tipo);
    const btnAtivo = document.getElementById(idBtn);
    if (btnAtivo) {
        btnAtivo.classList.remove('bg-white', 'dark:bg-[#0f172a]', 'text-slate-500');
        btnAtivo.classList.add('bg-indigo-600', 'text-white', 'scale-105', 'shadow-lg');
    }

    const cards = document.querySelectorAll('.card-operador');
    cards.forEach(card => {
        const status = card.getAttribute('data-status');
        if (tipo === 'TODOS' || status === tipo) {
            card.style.display = 'block';
            card.animate([{opacity: 0, transform: 'translateY(10px)'}, {opacity: 1, transform: 'translateY(0)'}], {duration: 300, fill: 'forwards'});
        } else {
            card.style.display = 'none';
        }
    });
}

function md5_simulado(str) {
    return str.replace(/\s+/g, '');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.filtro-btn').forEach(btn => {
        if(btn.id !== 'btn-filtro-TODOS') {
            const status = btn.innerText.split(' (')[0];
            btn.id = 'btn-filtro-' + md5_simulado(status);
        }
    });
});
</script>

<?php include 'shared/ui/footer.php'; ?>