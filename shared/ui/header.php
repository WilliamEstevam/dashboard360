<?php
$currentView = isset($currentView) ? $currentView : 'dashboard';

$userCargo = isset($user['cargo']) ? strtolower(trim($user['cargo'])) : 'operador';
$isAltaGestao = in_array($userCargo, array('administrador', 'gerente', 'coordenador'));
$isGestaoEquipa = in_array($userCargo, array('administrador', 'gerente', 'coordenador'));
$isOperador = ($userCargo === 'operador');
$menuGroups = array();

if (!$isOperador) {
    $menuGroups['atendimentos'] = array(
        'label' => 'Gestão de Atendimentos',
        'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 
        'items' => array(
            array('view' => 'tu', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Relatório TU'),
            array('view' => 'cubo_tu', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'label' => 'Cubo x TU'),
            array('view' => 'metas', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'label' => 'Metas e Performance')
        )
    );
    $wfmItems = array(
        array('view' => 'pausas', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Monitor WFM'),
        array('view' => 'banheiro', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'label' => 'Pausa Banheiro'),
        array('view' => 'absenteismo', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'label' => 'Absenteísmo (Zerolog)') // TELA NOVA AQUI
    );
    if ($isAltaGestao) {
        $wfmItems[] = array('view' => 'ajustes_pausa', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'label' => 'Ajustes de Pausa');
    }
    $menuGroups['wfm'] = array(
        'label' => 'Monitoria WFM',
        'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'items' => $wfmItems
    );
    $menuGroups['consolidadas'] = array(
        'label' => 'Pausas Consolidadas',
        'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'items' => array(
            array('view' => 'tempo_logado', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Tempo Logado'),
            array('view' => 'pausas_consolidadas', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Pausas')
        )
    );
    $equipaItems = array(
        array('view' => 'comunidade', 'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H14', 'label' => 'Comunidade Dashboard'),
        array('view' => 'banco_horas', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Banco de Horas') // NOVO MENU
    );
    if ($isGestaoEquipa) {
        $equipaItems[] = array('view' => 'usuarios', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Gestão de Equipa');
    }
    $menuGroups['equipa'] = array(
        'label' => 'Comunicação & Equipa',
        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        'items' => $equipaItems
    );

} else {
    $menuGroups['minha_performance'] = array(
        'label' => 'Acesso Operador',
        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'items' => array(
            array('view' => 'comunidade', 'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H14', 'label' => 'Mural de Avisos')
        )
    );
}

function isGroupActive($groupId, $groups, $current) {
    foreach($groups[$groupId]['items'] as $i) {
        if ($i['view'] === $current) return true;
    }
    return false;
}

$ajudaMascote = array(
    'dashboard' => array('titulo' => 'Painel de Controlo', 'texto' => 'Visão global da operação. Atenção: Os dados aqui apresentados são D-1, atualizados com base no fecho do Relatório TU do dia anterior.'),
    'tu' => array('titulo' => 'Relatório TU', 'texto' => 'Detalhamento de performance por operador e histórico.'),
    'cubo_tu' => array('titulo' => 'Cubo x TU', 'texto' => 'Comparativo de aderência entre as chamadas atendidas (Cubo Oficial) e as tabuladas no sistema (TU).'),
    'banheiro' => array('titulo' => 'Gestão de Banheiro', 'texto' => 'Monitoramento Ao Vivo do tempo de pausa de banheiro.'),
    'pausas' => array('titulo' => 'Monitor WFM', 'texto' => 'Visão em tempo real de todas as pausas ativas na operação.'),
    'metas' => array('titulo' => 'Metas e Performance', 'texto' => 'Acompanhamento consolidado das metas mensais.'),
    'comunidade' => array('titulo' => 'Comunidade Dashboard', 'texto' => 'Mural de recados e alertas para toda a operação.'),
    'usuarios' => array('titulo' => 'Gestão de Equipa', 'texto' => 'Administração de acessos e colaboradores.'),
    'ajustes_pausa' => array('titulo' => 'Ajustes de Pausas', 'texto' => 'Configuração dos limites de tempo permitidos.'),
    'tempo_logado' => array('titulo' => 'Tempo Logado', 'texto' => 'Visão oficial das pausas consolidadas. Acompanhamento do % Logado e % Pago por operador.'),
    'pausas_consolidadas' => array('titulo' => 'Pausas Oficiais', 'texto' => 'Acompanhamento das pausas consolidadas e gestão de justificativas para os excessos de tempo.')
);
$ajudaAtual = isset($ajudaMascote[$currentView]) ? $ajudaMascote[$currentView] : array('titulo' => 'Assistente', 'texto' => 'Navegue pelo menu flutuante.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard360 - Gestão Integrada</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('dashboard_theme') === 'dark' || (!('dashboard_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('dashboard_theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('dashboard_theme', 'dark');
            }
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="shared/assets/css/dashboard.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .nav-island:hover .nav-label {
            max-width: 150px !important;
            opacity: 1 !important;
            margin-left: 0.75rem !important;
            margin-right: 0.25rem !important;
            transition-delay: 0.4s !important; 
        }
    </style>
</head>
<body class="antialiased transition-colors duration-500 bg-slate-50 dark:bg-[#020617] min-h-screen overflow-x-hidden text-slate-800 dark:text-slate-100">

    <header class="relative w-full p-8 flex justify-between items-start z-50">
        <div class="flex items-center space-x-4">
            <div class="relative w-14 h-14 flex items-center justify-center cursor-pointer group" onclick="document.getElementById('mascot-help-modal').classList.remove('hidden')">
                <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 opacity-0 group-hover:opacity-100 group-hover:translate-y-1 transition-all duration-300 pointer-events-none z-50 flex flex-col items-center">
                    <div class="w-2.5 h-2.5 bg-indigo-600 dark:bg-indigo-500 rotate-45 -mb-1.5 rounded-sm z-0"></div>
                    <span class="bg-indigo-600 dark:bg-indigo-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg shadow-lg whitespace-nowrap relative z-10">Como funciona?</span>
                </div>
                <img src="shared/assets/img/mascote.png" alt="Mascote Dashboard" class="w-full h-full object-contain drop-shadow-lg z-10 hover:scale-110 transition-transform duration-300" onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='flex';">
                <div id="fallback-logo" class="absolute inset-0 bg-indigo-600 rounded-2xl flex items-center justify-center text-white font-black italic shadow-lg shadow-indigo-200 dark:shadow-none transition-transform group-hover:scale-110" style="display: none;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
            <div>
                <h1 class="text-2xl font-black italic tracking-tighter uppercase leading-none">Dashboard<span class="text-indigo-600 dark:text-indigo-400">360</span></h1>
                <p class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mt-1">Gestão Centralizada</p>
            </div>
        </div>

        <nav class="nav-island flex items-center bg-white/90 dark:bg-[#0f172a]/90 backdrop-blur-xl p-2 rounded-full shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200/80 dark:border-slate-800 transition-all duration-500 overflow-hidden">
            
            <div id="menu-level-main" class="menu-level flex items-center space-x-1 animate-in fade-in zoom-in-95 duration-200">
                
                <?php if (!$isOperador): ?>
                <?php $isDashActive = ($currentView == 'dashboard') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 active' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400'; ?>
                <a href="index.php?route=dashboard" class="nav-item <?php echo $isDashActive; ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span class="nav-label">Dashboard</span>
                </a>
                <?php endif; ?>

                <?php foreach($menuGroups as $id => $group): 
                    $isActiveGroup = isGroupActive($id, $menuGroups, $currentView) ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 active' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400';
                ?>
                <button onclick="openMenu('menu-level-<?php echo $id; ?>')" class="nav-item focus:outline-none <?php echo $isActiveGroup; ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $group['icon']; ?>"></path></svg>
                    <span class="nav-label"><?php echo $group['label']; ?></span>
                </button>
                <?php endforeach; ?>

                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1"></div>
            
                <button onclick="toggleTheme()" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-amber-500/10 flex items-center justify-center text-slate-500 dark:text-amber-400 hover:bg-slate-200 dark:hover:bg-amber-500/20 transition-colors ml-1 mr-1" title="Alternar Modo Escuro">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                
                <a href="index.php?route=perfil" class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-[#020617] flex items-center justify-center overflow-hidden border-2 border-white dark:border-slate-700 shadow-sm cursor-pointer ml-1 mr-1 relative group" title="Configurar Perfil">
                    <img src="<?php echo !empty($user['foto']) ? h($user['foto']) : 'https://ui-avatars.com/api/?name='.urlencode(isset($user['nome']) ? $user['nome'] : 'User').'&background=6366f1&color=fff&bold=true'; ?>" class="w-full h-full object-cover">
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-[#0f172a] rounded-full"></span>
                </a>
                
                <a href="index.php?route=logout" class="nav-item text-slate-400 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 ml-1">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>

            <?php foreach($menuGroups as $id => $group): ?>
            <div id="menu-level-<?php echo $id; ?>" class="menu-level hidden flex items-center space-x-1 animate-in fade-in zoom-in-95 duration-200">
                <button onclick="resetMenu()" class="nav-item text-slate-400 dark:text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 focus:outline-none" title="Voltar ao menu anterior">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                
                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1"></div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mx-3 whitespace-nowrap hidden sm:block">
                    <?php echo $group['label']; ?>
                </span>
                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1 hidden sm:block"></div>

                <?php foreach($group['items'] as $item): 
                    $isItemActive = ($currentView == $item['view']) ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 active' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400';
                ?>
                <a href="index.php?route=<?php echo $item['view']; ?>" class="nav-item <?php echo $isItemActive; ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $item['icon']; ?>"></path></svg>
                    <span class="nav-label"><?php echo $item['label']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

        </nav>
    </header>

    <div id="mascot-help-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 backdrop-blur-md bg-slate-900/60 transition-all duration-300">
        <div class="bg-white dark:bg-[#0f172a] w-full max-w-md rounded-[3rem] p-10 shadow-2xl border border-slate-100 dark:border-slate-800 relative animate-in zoom-in duration-300">
            <button onclick="document.getElementById('mascot-help-modal').classList.add('hidden')" class="absolute top-6 right-6 p-3 bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 rounded-full hover:text-red-500 dark:hover:text-red-400 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="flex items-center space-x-5 mb-8 mt-2">
                <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center p-2 shadow-inner border border-indigo-100 dark:border-indigo-500/20">
                    <img src="shared/assets/img/mascote.png" alt="Mascote" class="w-full h-full object-contain drop-shadow-sm" onerror="this.style.display='none'; document.getElementById('modal-fallback-logo').style.display='block';">
                    <svg id="modal-fallback-logo" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-black italic text-slate-800 dark:text-white uppercase tracking-tighter leading-none mb-1"><?php echo h($ajudaAtual['titulo']); ?></h3>
                    <p class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-[0.2em]">Assistente Dashboard</p>
                </div>
            </div>
            <div class="p-6 bg-slate-50 dark:bg-slate-800/40 rounded-[2rem] border border-slate-100 dark:border-slate-800 relative">
                <svg class="absolute -top-3 -left-3 w-8 h-8 text-indigo-200 dark:text-indigo-900/50" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300 leading-relaxed relative z-10 pl-2"><?php echo h($ajudaAtual['texto']); ?></p>
            </div>
            <div class="mt-8">
                <button onclick="document.getElementById('mascot-help-modal').classList.add('hidden')" class="w-full py-4 bg-indigo-600 text-white rounded-[1.5rem] font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all active:scale-95">Entendi!</button>
            </div>
        </div>
    </div>

    <script>
        function openMenu(menuId) {
            document.querySelectorAll('.menu-level').forEach(el => el.classList.add('hidden'));
            const menuToShow = document.getElementById(menuId);
            if(menuToShow) menuToShow.classList.remove('hidden');
        }
        
        function resetMenu() {
            document.querySelectorAll('.menu-level').forEach(el => el.classList.add('hidden'));
            document.getElementById('menu-level-main').classList.remove('hidden');
        }
    </script>

    <main class="pt-4 pb-10 w-full relative z-10 transition-colors duration-500">