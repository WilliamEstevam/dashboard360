<?php
$currentView = 'usuarios';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';
$cargosPermitidos = array('admin', 'administrador', 'gerente', 'coordenador');
if (!isset($user) || !in_array(strtolower(trim($user['cargo'])), $cargosPermitidos)) {
    header('Location: index.php?route=dashboard');
    exit;
}

$successMsg = '';
$errorMsg   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {

    if (!dashboard_csrf_validate()) {
        $errorMsg = 'Token de segurança inválido. Recarregue a página.';
    } else {

    $nome      = trim($_POST['nome']);
    $matricula = trim($_POST['matricula']);
    $email     = trim($_POST['email']);
    $cargo     = trim($_POST['cargo']);
    $senha     = trim($_POST['password']);
    $stmtCheck = db_prepare($db_conn,
        'SELECT id FROM consultoria.tbl_usuarios_v2 WHERE matricula = ? OR email = ?');
    $resCheck = db_execute($stmtCheck, array($matricula, $email));

    if ($resCheck && db_fetch_row($stmtCheck)) {
        $errorMsg = 'Erro: Já existe um utilizador com esta Matrícula ou E-mail.';
    } else {
        $novoSalt = dashboard_gerar_salt();
        $novoHash = dashboard_hash($novoSalt, $senha);

        $stmtInsert = db_prepare($db_conn,
            'INSERT INTO consultoria.tbl_usuarios_v2
                (nome_completo, matricula, email, senha, senha_hash, salt, cargo, precisa_mudar_senha)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $resInsert = db_execute($stmtInsert,
            array($nome, $matricula, $email, '', $novoHash, $novoSalt, $cargo));

        if ($resInsert) {
            $successMsg = 'Colaborador adicionado com sucesso!';
        } else {
            $errorMsg = log_erro('Usuarios INSERT', db_errormsg($db_conn));
        }
    }
    } // fecha else do CSRF
}
$listaUsuarios = array();

$resUsers = @db_exec($db_conn,
    'SELECT id, nome_completo, matricula, email, cargo, foto_path
     FROM consultoria.tbl_usuarios_v2
     ORDER BY cargo, nome_completo');

if ($resUsers) {
    while (db_fetch_row($resUsers)) {
        $v_id    = db_result($resUsers, 'id');
        $v_nome  = db_result($resUsers, 'nome_completo');
        $v_mat   = db_result($resUsers, 'matricula');
        $v_email = db_result($resUsers, 'email');
        $v_cargo = db_result($resUsers, 'cargo');
        $v_foto  = db_result($resUsers, 'foto_path');

        $listaUsuarios[] = array(
            'id'        => (int)$v_id,
            'nome'      => !empty($v_nome) ? dashboard_utf8_encode($v_nome) : 'Sem Nome',
            'matricula' => !empty($v_mat) ? trim($v_mat) : 'N/A',
            'email'     => !empty($v_email) ? trim($v_email) : 'N/A',
            'cargo'     => !empty($v_cargo) ? trim($v_cargo) : 'N/A',
            'foto'      => !empty($v_foto) ? trim($v_foto) : ''
        );
    }
} else {
    $errorMsg = log_erro('Usuarios SELECT', db_errormsg($db_conn));
}
?>

<div class="p-10 max-w-[1600px] mx-auto animate-in fade-in duration-700">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
        <div>
            <h3 class="text-3xl font-black italic tracking-tighter uppercase text-slate-800 dark:text-white">Gestão de <span class="text-indigo-600 dark:text-indigo-400">Equipe</span></h3>
            <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mt-1">Administração de acessos e perfis</p>
        </div>
        <button onclick="document.getElementById('modal-user').classList.remove('hidden')" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-[2rem] font-black text-[10px] uppercase tracking-[0.2em] transition-all shadow-xl shadow-indigo-100 dark:shadow-none active:scale-95">
            Novo Colaborador
        </button>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div class="mb-8 p-5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-wider flex items-center animate-in slide-in-from-top">
            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            <?php echo h($successMsg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-8 p-5 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-2xl text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-wider flex items-center animate-in slide-in-from-top">
            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            <?php echo h($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 rounded-[3rem] overflow-hidden shadow-sm transition-colors duration-500">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-slate-800/50">
                    <th class="px-8 py-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center w-24">Perfil</th>
                    <th class="px-8 py-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Nome / Cargo</th>
                    <th class="px-8 py-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Contato</th>
                    <th class="px-8 py-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Matrícula</th>
                    <th class="px-8 py-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                <?php if (empty($listaUsuarios)): ?>
                    <tr><td colspan="5" class="px-8 py-10 text-center text-slate-400 font-bold text-xs uppercase">Nenhum utilizador encontrado na base de dados.</td></tr>
                <?php else: foreach ($listaUsuarios as $u): ?>
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors group">
                    <td class="px-8 py-5 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border-2 border-white dark:border-[#0f172a] shadow-sm flex items-center justify-center overflow-hidden">
                            <?php if (!empty($u['foto'])): ?>
                                <img src="<?php echo h($u['foto']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-indigo-600 dark:text-indigo-400 font-black text-xs"><?php echo substr($u['nome'], 0, 1); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-8 py-5">
                        <p class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight"><?php echo h($u['nome']); ?></p>
                        <p class="text-[9px] font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mt-1"><?php echo h($u['cargo']); ?></p>
                    </td>
                    <td class="px-8 py-5">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400"><?php echo h($u['email']); ?></p>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-lg text-[10px] font-bold tracking-widest">
                            #<?php echo h($u['matricula']); ?>
                        </span>
                    </td>
                    <td class="px-8 py-5 text-right">
                         <div class="flex justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button class="p-2.5 text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl transition-all" title="Editar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div id="modal-user" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm">
        <div class="bg-white dark:bg-[#0f172a] w-full max-w-lg rounded-[3.5rem] shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden animate-in zoom-in duration-300 relative transition-colors duration-500">
            
            <button onclick="document.getElementById('modal-user').classList.add('hidden')" class="absolute top-6 right-6 p-3 bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 rounded-full hover:text-red-500 dark:hover:text-red-400 transition-colors z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="p-12">
                <h4 class="text-2xl font-black text-slate-800 dark:text-white uppercase italic tracking-tighter mb-2">Novo <span class="text-indigo-600 dark:text-indigo-400">Colaborador</span></h4>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest mb-8">Adicione um membro à operação</p>

                <form method="POST" action="index.php?route=usuarios" class="space-y-5">
                    <input type="hidden" name="action" value="create">
                    <?php echo dashboard_csrf_field(); ?>

                    <input type="text" name="nome" placeholder="Nome Completo" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="matricula" placeholder="Matrícula TU" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                        <select name="cargo" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            <option value="Operador">Operador</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Coordenador">Coordenador</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Administrador">Administrador</option>
                        </select>
                    </div>

                    <input type="email" name="email" placeholder="E-mail Corporativo" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                    
                    <div class="relative">
                        <input type="password" name="password" required placeholder="Definir senha de acesso" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black text-slate-400 uppercase tracking-widest">Nova Senha</span>
                    </div>

                    <div class="pt-6 mt-4 border-t border-slate-100 dark:border-slate-800 flex space-x-3">
                        <button type="button" onclick="document.getElementById('modal-user').classList.add('hidden')" class="flex-1 py-5 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Cancelar</button>
                        <button type="submit" class="flex-[2] py-5 bg-indigo-600 text-white rounded-[1.5rem] font-black text-[10px] uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 dark:shadow-none hover:bg-indigo-700 transition-colors active:scale-95">Salvar Acesso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-user');
        if (modal) {
            document.body.appendChild(modal);
        }
    });
</script>

<?php include 'shared/ui/footer.php'; ?>