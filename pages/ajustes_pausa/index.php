<?php

$currentView = 'ajustes_pausa';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meta_submit'])) {
    
    // Limpeza e conversão
    $chave = trim($_POST['meta_chave']);
    $valor = (int)$_POST['meta_valor'];
    
    if (!empty($chave) && $valor > 0) {
        // Atualiza o valor e regista a hora da alteração (Prepared Statement)
        $stmtUpd = db_prepare($db_conn, 
            "UPDATE consultoria.TBL_TEMP_parametros_metas 
             SET valor = ?, ultima_atualizacao = GETDATE() 
             WHERE chave = ?");
             
        $resUpd = db_execute($stmtUpd, array($valor, $chave));
        
        if ($resUpd) {
            $successMsg = "O limite de tempo foi atualizado com sucesso e já está em vigor!";
        } else {
            $errorMsg = log_erro('AjustesPausa UPDATE', db_errormsg($db_conn));
        }
    } else {
        $errorMsg = "Dados inválidos. O tempo deve ser superior a zero.";
    }
}

$limitesPausa = array(
    'limite_refeicao' => array('label' => 'Refeição', 'icon' => '🍽️', 'valor' => 60),
    'limite_banheiro' => array('label' => 'Banheiro', 'icon' => '🚽', 'valor' => 10),
    'limite_lanche'   => array('label' => 'Lanche',   'icon' => '🥪', 'valor' => 20),
    'limite_descanso' => array('label' => 'Descanso', 'icon' => '💤', 'valor' => 10),
);

$sqlParams = "SELECT chave, valor FROM consultoria.TBL_TEMP_parametros_metas WHERE categoria = 'pausas' OR chave LIKE 'limite_%'";
$resParams = @db_exec($db_conn, $sqlParams);

if ($resParams) {
    while (db_fetch_row($resParams)) {
        $chaveDb = strtolower(trim(db_result($resParams, 'chave')));
        $valorDb = (int)db_result($resParams, 'valor');
        
        if (isset($limitesPausa[$chaveDb])) {
            $limitesPausa[$chaveDb]['valor'] = $valorDb;
        }
    }
}
?>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    <!-- Header da Página -->
    <div class="mb-12 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
                Ajustes de <span class="text-indigo-600 dark:text-indigo-400">Pausa</span>
            </h2>
            <p class="text-slate-400 dark:text-slate-500 text-sm font-medium mt-1 uppercase tracking-widest text-xs">
                Defina os limites de tempo permitidos na operação
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="index.php?route=dashboard" class="px-6 py-4 bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all shadow-sm">
                ← Voltar ao Início
            </a>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (!empty($successMsg)): ?>
    <div class="mb-10 p-6 bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 rounded-2xl text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-widest animate-in slide-in-from-top duration-500">
        <?php echo $successMsg; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
    <div class="mb-10 p-6 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-2xl text-red-700 dark:text-red-400 text-xs font-bold uppercase tracking-widest animate-in slide-in-from-top duration-500">
        <?php echo $errorMsg; ?>
    </div>
    <?php endif; ?>

    <!-- Grid de Cartões de Ajuste -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php foreach ($limitesPausa as $chave => $info): ?>
        <button onclick="openEditModal('<?php echo $chave; ?>', '<?php echo $info['label']; ?>', <?php echo $info['valor']; ?>)" 
                class="bg-white dark:bg-[#1e293b] hover:bg-slate-50 dark:hover:bg-[#2d3a4f] p-12 rounded-[3rem] border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all hover:scale-[1.03] active:scale-95 text-center group relative overflow-hidden">
            <div class="relative z-10">
                <div class="text-5xl mb-8 group-hover:scale-110 transition-transform duration-500">
                    <?php echo $info['icon']; ?>
                </div>
                <p class="text-slate-400 dark:text-slate-400 text-[11px] font-black uppercase tracking-[0.2em] mb-3">
                    <?php echo $info['label']; ?>
                </p>
                <div class="flex items-center justify-center space-x-2">
                    <span class="text-5xl font-black text-slate-800 dark:text-white tracking-tighter italic">
                        <?php echo $info['valor']; ?>
                    </span>
                    <span class="text-slate-400 dark:text-slate-500 font-bold text-sm">min</span>
                </div>
            </div>
            
            <div class="absolute -right-4 -bottom-4 text-slate-100 dark:text-white/5 text-8xl font-black italic pointer-events-none group-hover:scale-125 transition-transform duration-1000">
                <?php echo $info['valor']; ?>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Nota Informativa -->
    <div class="mt-16 p-10 bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 rounded-[3rem] flex items-start space-x-6 shadow-sm transition-colors duration-500">
        <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-2xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <h4 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-widest mb-2">Impacto das Alterações</h4>
            <p class="text-slate-500 dark:text-slate-400 text-xs font-medium leading-relaxed max-w-2xl uppercase tracking-wider">
                As alterações nos limites de tempo atualizam diretamente a tabela. Os alertas nas telas de Monitorização WFM e Gestão de Banheiro adotarão os novos valores em tempo real.
            </p>
        </div>
    </div>
</div>

<div id="modal-edit-pausa" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 backdrop-blur-md bg-slate-900/60 dark:bg-black/80 transition-all duration-300">
    <div class="bg-white dark:bg-[#0f172a] w-full max-w-md rounded-[3.5rem] p-12 shadow-2xl animate-in zoom-in duration-300 border border-slate-100 dark:border-slate-800 relative transition-colors duration-500">
        
        <button type="button" onclick="document.getElementById('modal-edit-pausa').classList.add('hidden')" class="absolute top-6 right-6 p-3 bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 rounded-full hover:text-red-500 dark:hover:text-red-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 id="modal-title" class="text-2xl font-black text-slate-800 dark:text-white italic uppercase mb-2 mt-4">Editar Limite</h3>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest mb-10">Configure o tempo máximo permitido</p>
        
        <form method="POST" action="index.php?route=ajustes_pausa">
            <input type="hidden" name="meta_chave" id="input-meta-chave">
            <div class="mb-10">
                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-4 ml-1">Tempo em Minutos</label>
                <div class="relative">
                    <input type="number" name="meta_valor" id="input-meta-valor" required min="1" max="180"
                           class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-slate-100 dark:border-slate-700 p-8 rounded-3xl text-4xl font-black text-indigo-600 dark:text-indigo-400 outline-none focus:border-indigo-400 dark:focus:border-indigo-500 transition-all text-center">
                    <span class="absolute right-8 top-1/2 -translate-y-1/2 text-slate-300 dark:text-slate-600 font-black italic">MIN</span>
                </div>
            </div>
            
            <div class="flex space-x-4">
                <button type="button" onclick="document.getElementById('modal-edit-pausa').classList.add('hidden')" class="flex-1 py-6 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">Cancelar</button>
                <button type="submit" name="update_meta_submit" class="flex-1 py-6 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all active:scale-95">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-edit-pausa');
        if (modal) {
            document.body.appendChild(modal);
        }
    });

    // Função para preparar os dados do formulário e exibir o modal
    function openEditModal(chave, label, valor) {
        document.getElementById('input-meta-chave').value = chave;
        document.getElementById('input-meta-valor').value = valor;
        document.getElementById('modal-title').innerText = 'Ajustar ' + label;
        document.getElementById('modal-edit-pausa').classList.remove('hidden');
        
        // Focar no campo de input automaticamente após abrir o modal
        setTimeout(function() {
            document.getElementById('input-meta-valor').focus();
        }, 100);
    }
</script>

<?php include 'shared/ui/footer.php'; ?>