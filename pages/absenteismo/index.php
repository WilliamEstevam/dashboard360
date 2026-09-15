<?php

if (!isset($_SESSION)) { session_start(); }
require_once 'shared/config/database.php';
$user = isset($_SESSION['user']) ? $_SESSION['user'] : array('cargo' => 'operador', 'nome' => 'Desconhecido', 'nome_oficial' => 'Desconhecido');

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$userNomeOficial = isset($user['nome_oficial']) ? $user['nome_oficial'] : (isset($user['nome']) ? $user['nome'] : '');

$isAdmin = ($userCargo === 'administrador');
$isGerente = ($userCargo === 'gerente');
$isCoordenador = ($userCargo === 'coordenador');
$isSupervisor = ($userCargo === 'supervisor');
$hoje = date('Y-m-d');
$filtroDataIni = isset($_GET['data_ini']) ? $_GET['data_ini'] : $hoje;
$filtroDataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $hoje;
if (!validar_data($filtroDataIni)) $filtroDataIni = $hoje;
if (!validar_data($filtroDataFim)) $filtroDataFim = $hoje;

$filtroCoord = isset($_GET['coordenador']) ? dashboard_escape_sql($_GET['coordenador']) : '';
$filtroSuper = isset($_GET['supervisor']) ? dashboard_escape_sql($_GET['supervisor']) : '';

if ($isCoordenador) { $filtroCoord = $userNomeOficial; }
if ($isSupervisor) { $filtroSuper = $userNomeOficial; $filtroCoord = ''; }

$mapSupCoordView = array();
$sqlMap = "SELECT DISTINCT SUPERVISOR_QUADRO, COORDENADOR 
           FROM consultoria.TBL_PERM_RELATORIO_TU 
           WHERE DATA_REFERENCIA >= DATEADD(day, -30, GETDATE()) 
           AND SUPERVISOR_QUADRO IS NOT NULL AND COORDENADOR IS NOT NULL";
$resMap = @db_exec($db_conn, $sqlMap);
if ($resMap) {
    while(db_fetch_row($resMap)) {
        $sup = dashboard_utf8_encode(trim(db_result($resMap, 'SUPERVISOR_QUADRO')));
        $coord = dashboard_utf8_encode(trim(db_result($resMap, 'COORDENADOR')));
        if (!empty($sup) && !empty($coord)) {
            $mapSupCoordView[$sup] = $coord;
        }
    }
}

$d15_inicio = date('Y-m-d', strtotime('-15 days', strtotime($filtroDataFim)));

$justificativasArr = array(
    'FALTA JUSTIFICADA', 'FERIAS', 'TREINAMENTO', 'AFASTADO', 
    'LICENCA MEDICA', 'BANCO DE HORAS', 'PROCESSO DE ABANDONO', 
    'JUSTIFICATIVA RH', 'ATESTADO', 'DISPENSA JUSTIFICADA', 
    'JUSTIFICATIVA RH DAP', 'INFRA INDISPONIVEL', 'ATRASO', 'SAIDA ANTECIPADA'
);

$statusDeslogadoStr = "'DESLOGADO', 'NÃO LOGADO', 'OFFLINE'";

$rawAbsences = array();

if ($filtroDataFim >= $hoje && $filtroDataIni <= $hoje) {
    $sqlTemp = "SELECT matricula, nome_operador, supervisor, status_real, status_planejado 
                FROM consultoria.tbl_temp_estado_atual 
                WHERE UPPER(RTRIM(LTRIM(status_real))) IN ($statusDeslogadoStr)
                AND UPPER(RTRIM(LTRIM(status_planejado))) NOT IN ($statusDeslogadoStr)
                AND CAST(ultima_atualizacao AS DATE) = CAST(GETDATE() AS DATE)";
    
    $resTemp = @db_exec($db_conn, $sqlTemp);
    if ($resTemp) {
        while(db_fetch_row($resTemp)) {
            $rawAbsences[] = array(
                'data' => $hoje,
                'matricula' => trim(db_result($resTemp, 'matricula')),
                'nome' => dashboard_utf8_encode(trim(db_result($resTemp, 'nome_operador'))),
                'supervisor' => dashboard_utf8_encode(trim(db_result($resTemp, 'supervisor'))),
                'status_real' => dashboard_utf8_encode(trim(db_result($resTemp, 'status_real'))),
                'status_planejado' => dashboard_utf8_encode(trim(db_result($resTemp, 'status_planejado')))
            );
        }
    }
}

$sqlHist = "SELECT CAST(start_time AS DATE) as data_evento, matricula, nome_operador, supervisor, status_real, status_planejado 
            FROM consultoria.tbl_perm_historico_eventos 
            WHERE UPPER(RTRIM(LTRIM(status_real))) IN ($statusDeslogadoStr)
            AND UPPER(RTRIM(LTRIM(status_planejado))) NOT IN ($statusDeslogadoStr)
            AND CAST(start_time AS DATE) <= '$filtroDataFim' 
            AND CAST(start_time AS DATE) >= '$d15_inicio' 
            AND CAST(start_time AS DATE) < '$hoje'"; 

$resHist = @db_exec($db_conn, $sqlHist);
if ($resHist) {
    while(db_fetch_row($resHist)) {
        $rawAbsences[] = array(
            'data' => date('Y-m-d', strtotime(db_result($resHist, 'data_evento'))),
            'matricula' => trim(db_result($resHist, 'matricula')),
            'nome' => dashboard_utf8_encode(trim(db_result($resHist, 'nome_operador'))),
            'supervisor' => dashboard_utf8_encode(trim(db_result($resHist, 'supervisor'))),
            'status_real' => dashboard_utf8_encode(trim(db_result($resHist, 'status_real'))),
            'status_planejado' => dashboard_utf8_encode(trim(db_result($resHist, 'status_planejado')))
        );
    }
}

$operadores = array();

foreach ($rawAbsences as $row) {
    $mat = $row['matricula'];
    $data = $row['data'];
    $stPlan = strtoupper(trim($row['status_planejado']));
    
    $isJustificado = in_array($stPlan, $justificativasArr);
    
    if (!isset($operadores[$mat])) {
        $sup = $row['supervisor'];
        $coord = isset($mapSupCoordView[$sup]) ? $mapSupCoordView[$sup] : 'Sem Coordenador';
        
        $operadores[$mat] = array(
            'matricula' => $mat,
            'nome' => $row['nome'],
            'supervisor' => $sup,
            'coordenador' => $coord,
            'status_atual' => $row['status_real'],
            'status_planejado' => $row['status_planejado'],
            'dias_falta_injustificada' => 0,
            'historico_datas_injustificadas' => array(),
            'historico_datas_justificadas' => array(),
            'is_in_filtered_period' => false,
            'is_justificado_in_period' => false
        );
    }
    
    if ($data >= $filtroDataIni && $data <= $filtroDataFim) {
        $operadores[$mat]['is_in_filtered_period'] = true;
        $operadores[$mat]['status_atual'] = $row['status_real'];
        $operadores[$mat]['status_planejado'] = $row['status_planejado'];
        if ($isJustificado) {
            $operadores[$mat]['is_justificado_in_period'] = true;
        }
    }
    
    $dataFormatada = date('d/m/Y', strtotime($data));
    if ($isJustificado) {
        if (!in_array($dataFormatada, $operadores[$mat]['historico_datas_justificadas'])) {
            $operadores[$mat]['historico_datas_justificadas'][] = $dataFormatada;
        }
    } else {
        if (!in_array($dataFormatada, $operadores[$mat]['historico_datas_injustificadas'])) {
            $operadores[$mat]['historico_datas_injustificadas'][] = $dataFormatada;
            $operadores[$mat]['dias_falta_injustificada']++;
        }
    }
}

$dadosGerais = array();
$kpiZerolog = 0;
$kpiOfensores = 0;
$kpiJustificados = 0;

foreach ($operadores as $mat => $op) {
    if (!$op['is_in_filtered_period']) continue;
    if (!empty($filtroCoord) && strtolower(trim($op['coordenador'])) !== strtolower(trim($filtroCoord))) continue;
    if (!empty($filtroSuper) && strtolower(trim($op['supervisor'])) !== strtolower(trim($filtroSuper))) continue;
    if ($op['is_justificado_in_period']) {
        $op['categoria'] = 'justificado';
        $op['historico_dias'] = $op['historico_datas_justificadas'];
        $op['dias_falta'] = count($op['historico_datas_justificadas']);
        $kpiJustificados++;
    } else {
        if ($op['dias_falta_injustificada'] >= 3) {
            $op['categoria'] = 'ofensor';
            $kpiOfensores++;
        } else {
            $op['categoria'] = 'zerolog';
            $kpiZerolog++;
        }
        $op['historico_dias'] = $op['historico_datas_injustificadas'];
        $op['dias_falta'] = $op['dias_falta_injustificada'];
    }
    
    $dadosGerais[] = $op;
}

function sortAbsenteismo($a, $b) {
    $pesoA = ($a['categoria'] == 'ofensor') ? 3 : (($a['categoria'] == 'zerolog') ? 2 : 1);
    $pesoB = ($b['categoria'] == 'ofensor') ? 3 : (($b['categoria'] == 'zerolog') ? 2 : 1);
    if ($pesoA == $pesoB) return 0;
    return ($pesoA < $pesoB) ? 1 : -1;
}
usort($dadosGerais, 'sortAbsenteismo');

if (isset($_GET['export_advanced'])) {
    $expZero = isset($_GET['exp_zerolog']);
    $expOfensor = isset($_GET['exp_ofensores']);
    $expJustificado = isset($_GET['exp_justificados']);
    
    $filename = "Relatorio_Absenteismo_" . date('Ymd_His') . ".csv";
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    fputcsv($output, array('Matrícula', 'Operador', 'Supervisor', 'Coordenador', 'Status Planejado', 'Período Filtrado', 'Data da Falta', 'Categoria', 'Ofensor'), ';');
    
    $periodoFiltro = ($filtroDataIni == $filtroDataFim) ? date('d/m/Y', strtotime($filtroDataIni)) : date('d/m/Y', strtotime($filtroDataIni)) . ' a ' . date('d/m/Y', strtotime($filtroDataFim));

    foreach ($dadosGerais as $row) {
        if ($row['categoria'] == 'zerolog' && !$expZero) continue;
        if ($row['categoria'] == 'ofensor' && !$expOfensor) continue;
        if ($row['categoria'] == 'justificado' && !$expJustificado) continue;
        
        $labelCategoria = 'Falta Injustificada';
        if ($row['categoria'] == 'ofensor') $labelCategoria = 'Ofensor D-15';
        if ($row['categoria'] == 'justificado') $labelCategoria = 'Falta Justificada';

        $isOfensor = ($row['categoria'] == 'ofensor') ? 'Sim' : 'Não';

        if (!empty($row['historico_dias'])) {
            $exportouPeloMenosUmaLinha = false;
            
            foreach ($row['historico_dias'] as $dataEspecifica) {
                $dParts = explode('/', $dataEspecifica);
                if (count($dParts) == 3) {
                    $dtIso = $dParts[2] . '-' . $dParts[1] . '-' . $dParts[0];
                    
                    if ($dtIso >= $filtroDataIni && $dtIso <= $filtroDataFim) {
                        fputcsv($output, array(
                            $row['matricula'],
                            $row['nome'],
                            $row['supervisor'],
                            $row['coordenador'],
                            $row['status_planejado'],
                            $periodoFiltro,
                            $dataEspecifica, 
                            $labelCategoria,
                            $isOfensor
                        ), ';');
                        $exportouPeloMenosUmaLinha = true;
                    }
                }
            }
            
            if (!$exportouPeloMenosUmaLinha) {
                fputcsv($output, array($row['matricula'], $row['nome'], $row['supervisor'], $row['coordenador'], $row['status_planejado'], $periodoFiltro, '-', $labelCategoria, $isOfensor), ';');
            }
            
        } else {
            fputcsv($output, array($row['matricula'], $row['nome'], $row['supervisor'], $row['coordenador'], $row['status_planejado'], $periodoFiltro, '-', $labelCategoria, $isOfensor), ';');
        }
    }
    fclose($output);
    exit;
}

$currentView = 'absenteismo';
include 'shared/ui/header.php';

?>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Gestão de <span class="text-indigo-600 dark:text-indigo-400">Absenteísmo</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase tracking-[0.2em]">
                Monitoramento de Faltas, Zerologs e Ofensores
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="document.getElementById('modal-export').classList.remove('hidden')" class="px-6 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center active:scale-95">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Exportar Relatório
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm mb-8 transition-colors duration-500">
        <form id="formAbsenteismo" method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-6 gap-6 items-end">
            <input type="hidden" name="route" value="absenteismo">
            
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Data Inicial</label>
                <input type="date" name="data_ini" value="<?php echo $filtroDataIni; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Data Final</label>
                <input type="date" name="data_fim" value="<?php echo $filtroDataFim; ?>" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block ml-1">Coordenador</label>
                <?php if ($isCoordenador || $isSupervisor): ?>
                    <input type="text" readonly value="<?php echo $isCoordenador ? $filtroCoord : 'Automático'; ?>" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs font-bold text-slate-500 outline-none cursor-not-allowed">
                    <?php if ($isCoordenador): ?><input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>"><?php endif; ?>
                <?php else: ?>
                    <select name="coordenador" onchange="document.getElementById('formAbsenteismo').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
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
                    <select name="supervisor" onchange="document.getElementById('formAbsenteismo').submit();" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-xl p-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none cursor-pointer focus:border-indigo-500">
                        <option value="">TODOS</option>
                        <?php foreach($listaSupers as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if($filtroSuper == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full px-4 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-200 dark:shadow-none transition-all flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filtrar Dados
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between transition-colors duration-500">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Zerologs (S/ Justificativa)</p>
                <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter"><?php echo $kpiZerolog; ?></p>
            </div>
            <div class="w-14 h-14 bg-slate-50 dark:bg-slate-800/50 text-slate-400 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-rose-500 to-red-600 rounded-[2.5rem] p-8 border border-red-500 shadow-[0_10px_30px_-10px_rgba(225,29,72,0.4)] flex items-center justify-between relative overflow-hidden group">
            <div class="relative z-10 text-white">
                <p class="text-[10px] font-black text-rose-200 uppercase tracking-widest mb-1">Ofensores D-15 (>=3 Faltas)</p>
                <div class="flex items-baseline space-x-2">
                    <p class="text-4xl font-black tracking-tighter italic"><?php echo $kpiOfensores; ?></p>
                </div>
            </div>
            <div class="relative z-10 w-14 h-14 bg-white/20 backdrop-blur-md text-white rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] p-8 rounded-[2.5rem] border border-emerald-100 dark:border-emerald-500/20 shadow-sm flex items-center justify-between transition-colors duration-500">
            <div>
                <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">Faltas Justificadas</p>
                <p class="text-4xl font-black text-emerald-600 dark:text-emerald-400 tracking-tighter"><?php echo $kpiJustificados; ?></p>
            </div>
            <div class="w-14 h-14 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 mb-8">
        <button onclick="filterAbs('todos')" id="btn-abs-todos" class="abs-btn px-6 py-3 rounded-xl bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest shadow-md transition-all border border-transparent">
            Mostrar Todos
        </button>
        <button onclick="filterAbs('zerolog')" id="btn-abs-zerolog" class="abs-btn px-6 py-3 rounded-xl bg-white dark:bg-[#0f172a] text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold text-[10px] uppercase tracking-widest hover:border-slate-400 transition-all">
            Zerologs
        </button>
        <button onclick="filterAbs('ofensor')" id="btn-abs-ofensor" class="abs-btn px-6 py-3 rounded-xl bg-white dark:bg-[#0f172a] text-red-500 dark:text-red-400 border border-red-200 dark:border-red-500/30 font-bold text-[10px] uppercase tracking-widest hover:border-red-400 transition-all">
            Ofensores
        </button>
        <button onclick="filterAbs('justificado')" id="btn-abs-justificado" class="abs-btn px-6 py-3 rounded-xl bg-white dark:bg-[#0f172a] text-emerald-500 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 font-bold text-[10px] uppercase tracking-widest hover:border-emerald-400 transition-all">
            Justificados
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="grid-zerolog">
        <?php if(empty($dadosGerais)): ?>
            <div class="col-span-full py-24 text-center bg-white dark:bg-[#0f172a] rounded-[3.5rem] border border-slate-100 dark:border-slate-800 border-dashed">
                <p class="text-slate-400 dark:text-slate-600 font-bold uppercase tracking-widest text-[11px] italic">Nenhum registo de absenteísmo no período.</p>
            </div>
        <?php else: foreach($dadosGerais as $idx => $z): 
            $cat = $z['categoria'];
            $cardStyle = 'border-slate-100 dark:border-slate-800 bg-white dark:bg-[#0f172a]';
            $badgeColor = 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400';
            
            if ($cat == 'ofensor') {
                $cardStyle = 'border-red-300 dark:border-red-500/50 shadow-[0_5px_20px_rgba(225,29,72,0.15)] bg-red-50/30 dark:bg-red-900/10';
                $badgeColor = 'bg-red-500 text-white shadow-md shadow-red-200 dark:shadow-none';
            } else if ($cat == 'justificado') {
                $cardStyle = 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/30 dark:bg-emerald-900/10';
                $badgeColor = 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30';
            }
        ?>
        <div class="card-abs <?php echo $cat; ?> rounded-[2rem] border <?php echo $cardStyle; ?> p-6 relative overflow-hidden transition-all duration-500 group">
            
            <?php if($cat == 'ofensor'): ?>
                <div class="absolute top-0 left-0 w-full h-1.5 bg-red-500"></div>
                <div class="absolute top-5 right-5">
                    <span class="px-2 py-1 rounded-md text-[8px] font-black uppercase tracking-widest border bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/30 animate-pulse shadow-sm">
                        OFENSOR (<?php echo $z['dias_falta']; ?> FALTAS)
                    </span>
                </div>
            <?php else: ?>
                <div class="absolute top-5 right-5">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">
                        <?php echo $cat == 'justificado' ? 'Justificado' : $z['dias_falta'] . ' Falta(s) D-15'; ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="flex items-center space-x-4 mb-6 mt-2">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center font-black text-lg <?php echo $badgeColor; ?>">
                    <?php echo substr($z['nome'], 0, 1); ?>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight truncate w-full" title="<?php echo $z['nome']; ?>"><?php echo $z['nome']; ?></h4>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5 truncate w-full">Mat: <?php echo $z['matricula']; ?></p>
                </div>
            </div>

            <div class="space-y-3 mb-6">
                <div class="bg-white dark:bg-[#0f172a] p-3 rounded-xl border border-slate-100 dark:border-slate-800/50 shadow-sm">
                    <p class="text-[8px] font-black text-indigo-500 uppercase tracking-widest mb-1">Status Planejado</p>
                    <p class="text-xs font-bold <?php echo $cat == 'justificado' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300'; ?> truncate" title="<?php echo $z['status_planejado']; ?>"><?php echo $z['status_planejado']; ?></p>
                </div>
                <div class="p-3 rounded-xl border <?php echo $cat == 'justificado' ? 'bg-emerald-50/50 dark:bg-emerald-500/5 border-emerald-100 dark:border-emerald-900/30' : 'bg-red-50/50 dark:bg-red-500/5 border-red-100 dark:border-red-900/30'; ?>">
                    <p class="text-[8px] font-black <?php echo $cat == 'justificado' ? 'text-emerald-500' : 'text-red-500'; ?> uppercase tracking-widest mb-1">Status Atual</p>
                    <p class="text-xs font-black <?php echo $cat == 'justificado' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'; ?> italic truncate"><?php echo $z['status_atual']; ?></p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-50 dark:border-slate-800/50">
                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1 truncate">Sup: <?php echo $z['supervisor']; ?></p>
                <p class="text-[8px] font-bold text-slate-400 opacity-60 uppercase tracking-widest truncate">Coord: <?php echo $z['coordenador']; ?></p>
                
                <?php if($cat == 'ofensor'): ?>
                    <p class="text-[8px] font-black text-red-500 uppercase tracking-widest mb-2 mt-3">Histórico D-15</p>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach($z['historico_dias'] as $dia): ?>
                            <span class="px-2 py-0.5 bg-white dark:bg-[#0f172a] border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 rounded text-[9px] font-bold"><?php echo $dia; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>


<div id="modal-export" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#0f172a] w-full max-w-md rounded-[3.5rem] shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden animate-in zoom-in duration-300 relative">
        <button onclick="document.getElementById('modal-export').classList.add('hidden')" class="absolute top-6 right-6 p-3 bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 rounded-full hover:text-red-500 dark:hover:text-red-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="p-12">
            <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center mb-6 shadow-inner border border-emerald-100 dark:border-emerald-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            
            <h4 class="text-2xl font-black text-slate-800 dark:text-white uppercase italic tracking-tighter mb-2">Exportar <span class="text-emerald-600 dark:text-emerald-400">Relatório</span></h4>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest mb-8">Selecione os dados que deseja incluir no ficheiro CSV consolidado.</p>

            <form method="GET" action="index.php">
                <input type="hidden" name="route" value="absenteismo">
                <input type="hidden" name="export_advanced" value="1">
                <input type="hidden" name="data_ini" value="<?php echo $filtroDataIni; ?>">
                <input type="hidden" name="data_fim" value="<?php echo $filtroDataFim; ?>">
                <input type="hidden" name="coordenador" value="<?php echo $filtroCoord; ?>">
                <input type="hidden" name="supervisor" value="<?php echo $filtroSuper; ?>">

                <div class="space-y-4 mb-10">
                    <label class="flex items-center p-4 border border-slate-100 dark:border-slate-700 rounded-2xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <input type="checkbox" name="exp_zerolog" checked class="w-5 h-5 text-indigo-600 bg-slate-100 border-slate-300 rounded focus:ring-indigo-500">
                        <span class="ml-3 text-sm font-black text-slate-700 dark:text-slate-300 uppercase">Zerologs (S/ Justificativa)</span>
                    </label>
                    <label class="flex items-center p-4 border border-red-100 dark:border-red-900/30 rounded-2xl cursor-pointer hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-colors">
                        <input type="checkbox" name="exp_ofensores" checked class="w-5 h-5 text-red-600 bg-slate-100 border-slate-300 rounded focus:ring-red-500">
                        <span class="ml-3 text-sm font-black text-red-600 dark:text-red-400 uppercase">Ofensores Críticos (D-15)</span>
                    </label>
                    <label class="flex items-center p-4 border border-emerald-100 dark:border-emerald-900/30 rounded-2xl cursor-pointer hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition-colors">
                        <input type="checkbox" name="exp_justificados" checked class="w-5 h-5 text-emerald-600 bg-slate-100 border-slate-300 rounded focus:ring-emerald-500">
                        <span class="ml-3 text-sm font-black text-emerald-600 dark:text-emerald-400 uppercase">Faltas Justificadas</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-5 bg-emerald-600 text-white rounded-[1.5rem] font-black text-[11px] uppercase tracking-[0.2em] shadow-xl shadow-emerald-200 dark:shadow-none hover:bg-emerald-700 transition-colors active:scale-95">
                    Transferir Ficheiro .CSV
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function filterAbs(type) {
        const botoes = document.querySelectorAll('.abs-btn');
        botoes.forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-md', 'border-transparent');
            btn.classList.add('bg-white', 'dark:bg-[#0f172a]');
            if(btn.id === 'btn-abs-zerolog') btn.classList.add('text-slate-500');
            if(btn.id === 'btn-abs-ofensor') btn.classList.add('text-red-500');
            if(btn.id === 'btn-abs-justificado') btn.classList.add('text-emerald-500');
            if(btn.id === 'btn-abs-todos') btn.classList.add('text-slate-500');
        });

        const btnAtivo = document.getElementById('btn-abs-' + type);
        if (btnAtivo) {
            btnAtivo.classList.remove('bg-white', 'dark:bg-[#0f172a]', 'text-slate-500', 'text-red-500', 'text-emerald-500');
            btnAtivo.classList.add('bg-indigo-600', 'text-white', 'shadow-md', 'border-transparent');
        }

        const cards = document.querySelectorAll('.card-abs');
        cards.forEach(card => {
            if (type === 'todos' || card.classList.contains(type)) {
                card.style.display = 'block';
                card.animate([
                    {opacity: 0, transform: 'translateY(10px)'}, 
                    {opacity: 1, transform: 'translateY(0)'}
                ], {duration: 300, fill: 'forwards'});
            } else {
                card.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-export');
        if (modal) {
            document.body.appendChild(modal);
        }
    });
</script>

<?php include 'shared/ui/footer.php'; ?>