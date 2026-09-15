<?php
$currentView = 'perfil';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$successMsg = '';
$errorMsg = '';

$userId = isset($user['id']) ? (int)$user['id'] : 0;

$stmtUser = db_prepare($db_conn,
    'SELECT email, nome_completo, senha, senha_hash, salt, cargo, matricula, foto_path
     FROM consultoria.tbl_usuarios_v2
     WHERE id = ?');
db_execute($stmtUser, array($userId));

$dadosAtuais = array();
if ($stmtUser && db_fetch_row($stmtUser)) {
    $dadosAtuais = array(
        'email' => trim(db_result($stmtUser, 'email')),
        'nome'  => dashboard_utf8_encode(db_result($stmtUser, 'nome_completo')),
        'senha' => trim(db_result($stmtUser, 'senha')),
        'hash'  => trim(db_result($stmtUser, 'senha_hash')),
        'salt'  => trim(db_result($stmtUser, 'salt')),
        'cargo' => trim(db_result($stmtUser, 'cargo')),
        'mat'   => trim(db_result($stmtUser, 'matricula')),
        'foto'  => trim(db_result($stmtUser, 'foto_path'))
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    if (!dashboard_csrf_validate()) {
        $errorMsg = 'Token de segurança inválido. Recarregue a página.';
    } else {

    $novoNome  = dashboard_escape_sql(trim($_POST['nome']));
    $novoEmail = dashboard_escape_sql(trim($_POST['email']));
    $senhaAtual = trim($_POST['senha_atual']);
    $novaSenha  = trim($_POST['nova_senha']);
    $confirma   = trim($_POST['confirma_senha']);

    $podeGravar = true;
    $querySenha = "";
    $queryFoto  = "";

    if (!empty($novaSenha)) {
        if (!empty($dadosAtuais['salt']) && !empty($dadosAtuais['hash'])) {
            $senhaValida = dashboard_verify($senhaAtual, $dadosAtuais['hash'], $dadosAtuais['salt']);
        } else {
            $senhaValida = ($senhaAtual === $dadosAtuais['senha']);
        }

        if (!$senhaValida) {
            $errorMsg   = 'A senha atual está incorreta.';
            $podeGravar = false;
        } else if ($novaSenha !== $confirma) {
            $errorMsg   = 'A nova senha e a confirmação não coincidem.';
            $podeGravar = false;
        }
    }

    if ($podeGravar && isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] == 0) {
        $uploadResult = dashboard_validate_upload($_FILES['nova_foto'], 2 * 1024 * 1024, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
        if (!$uploadResult['valid']) {
            $errorMsg   = $uploadResult['error'];
            $podeGravar = false;
        } else {
            $pastaDestino = 'shared/assets/img/avatars/';

            if (!file_exists($pastaDestino)) {
                @mkdir($pastaDestino, 0755, true);
            }

            $ext          = strtolower(pathinfo($_FILES['nova_foto']['name'], PATHINFO_EXTENSION));
            $nomeArquivo  = dashboard_safe_filename('avatar_' . $userId, $ext);
            $caminhoFinal = $pastaDestino . $nomeArquivo;

        if (move_uploaded_file($_FILES['nova_foto']['tmp_name'], $caminhoFinal)) {
            $novaFotoPath           = $caminhoFinal;
            $dadosAtuais['foto']    = $caminhoFinal;
            $_SESSION['user']['foto'] = $caminhoFinal;
        } else {
            $errorMsg   = 'Ocorreu um erro ao gravar a foto no servidor.';
            $podeGravar = false;
        }
        } // fecha else do upload validation
    }

    if ($podeGravar) {
        $setClauses = array();
        $params     = array();

        $setClauses[] = 'nome_completo = ?';
        $params[]     = $novoNome;

        $setClauses[] = 'email = ?';
        $params[]     = $novoEmail;

        if (!empty($novaSenha)) {
            $novoSalt     = dashboard_gerar_salt();
            $novoHash     = dashboard_hash($novoSalt, $novaSenha);
            $setClauses[] = 'senha_hash = ?';
            $params[]     = $novoHash;
            $setClauses[] = 'salt = ?';
            $params[]     = $novoSalt;
            $setClauses[] = "senha = ''";
        }

        if (!empty($novaFotoPath)) {
            $setClauses[] = 'foto_path = ?';
            $params[]     = $novaFotoPath;
        }

        $params[] = $userId;

        $sqlUpd  = 'UPDATE consultoria.tbl_usuarios_v2 SET '
                 . implode(', ', $setClauses)
                 . ' WHERE id = ?';
        $stmtUpd = db_prepare($db_conn, $sqlUpd);
        $resUpd  = db_execute($stmtUpd, $params);

        if ($resUpd) {
            $successMsg = 'Perfil atualizado com sucesso!';

            $dadosAtuais['nome']  = $novoNome;
            $dadosAtuais['email'] = $novoEmail;
            if (!empty($novaSenha)) {
                $dadosAtuais['hash'] = $novoHash;
                $dadosAtuais['salt'] = $novoSalt;
            }

            $_SESSION['user']['nome'] = $novoNome;
            $user['nome']             = $novoNome;
            if (!empty($novaFotoPath)) {
                $user['foto'] = $novaFotoPath;
            }

        } else {
            $errorMsg = log_erro('Perfil UPDATE', db_errormsg($db_conn));
        }
    }
    } // fecha else do CSRF
}
?>

<div class="p-10 max-w-[1200px] mx-auto animate-in fade-in duration-700">
    
    <div class="mb-12">
        <h2 class="text-3xl font-black italic tracking-tighter text-slate-800 dark:text-white uppercase">
            Meu <span class="text-indigo-600 dark:text-indigo-400">Perfil</span>
        </h2>
        <p class="text-slate-400 dark:text-slate-500 text-sm font-medium mt-1 uppercase tracking-widest text-xs">
            Efetue a gestão da sua conta e credenciais de acesso
        </p>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div class="mb-10 p-6 bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 rounded-2xl text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-widest flex items-center animate-in slide-in-from-top duration-500 shadow-sm">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            <?php echo h($successMsg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-10 p-6 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-2xl text-red-700 dark:text-red-400 text-xs font-bold uppercase tracking-widest flex items-center animate-in slide-in-from-top duration-500 shadow-sm">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
            <?php echo h($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        
        <div class="lg:col-span-1 space-y-10">
            <div class="bg-white dark:bg-[#0f172a] rounded-[3rem] p-10 border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col items-center text-center relative overflow-hidden transition-colors duration-500">
                <div class="absolute top-0 left-0 w-full h-32 bg-indigo-600"></div>
                
                <div class="relative mt-8 mb-6">
                    <div class="w-32 h-32 rounded-full bg-white dark:bg-[#0f172a] p-2 relative shadow-xl">
                        <label for="upload-foto-input" id="preview-avatar-container" class="w-full h-full rounded-full overflow-hidden bg-slate-100 dark:bg-slate-800 relative group cursor-pointer border border-slate-200 dark:border-slate-700 block">
                            <?php if (!empty($dadosAtuais['foto'])): ?>
                                <img id="preview-avatar" src="<?php echo $dadosAtuais['foto']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <img id="preview-avatar" src="https://ui-avatars.com/api/?name=<?php echo urlencode($dadosAtuais['nome']); ?>&background=6366f1&color=fff&bold=true" class="w-full h-full object-cover">
                            <?php endif; ?>
                            
                            <div class="absolute inset-0 bg-slate-900/60 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-8 h-8 text-white mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span class="text-[8px] font-black text-white uppercase tracking-widest">Alterar</span>
                            </div>
                        </label>
                    </div>
                </div>

                <h3 class="text-2xl font-black text-slate-800 dark:text-white uppercase italic tracking-tighter leading-tight mb-2"><?php echo h($dadosAtuais['nome']); ?></h3>
                <p class="text-[10px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mb-6"><?php echo h($dadosAtuais['cargo']); ?></p>
                
                <div class="w-full pt-6 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-left">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Matrícula</p>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">#<?php echo h($dadosAtuais['mat']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Status</p>
                        <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest flex items-center justify-end">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full mr-1.5 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span> Ativo
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-[#0f172a] rounded-[3rem] p-12 border border-slate-100 dark:border-slate-800 shadow-sm transition-colors duration-500">
                <form method="POST" action="index.php?route=perfil" enctype="multipart/form-data" class="space-y-12">
                    <input type="hidden" name="update_profile" value="1">
                    <?php echo dashboard_csrf_field(); ?>
                    
                    <input type="file" id="upload-foto-input" name="nova_foto" accept="image/*" class="hidden" onchange="const fr=new FileReader();fr.onload=function(){document.getElementById('preview-avatar').src=fr.result;};fr.readAsDataURL(this.files[0]);">

                    <div>
                        <div class="flex items-center space-x-4 mb-8">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <h4 class="text-xl font-black italic uppercase tracking-tighter text-slate-800 dark:text-white">Informações <span class="text-indigo-600 dark:text-indigo-400">Pessoais</span></h4>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-3 ml-1">Nome Completo</label>
                                <input type="text" name="nome" value="<?php echo h($dadosAtuais['nome']); ?>" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-3 ml-1">E-mail Corporativo</label>
                                <input type="email" name="email" value="<?php echo h($dadosAtuais['email']); ?>" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="w-full h-px bg-slate-100 dark:bg-slate-800"></div>

                    <div>
                        <div class="flex items-center space-x-4 mb-8">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <h4 class="text-xl font-black italic uppercase tracking-tighter text-slate-800 dark:text-white">Segurança & <span class="text-amber-500">Acesso</span></h4>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-3 ml-1">Senha Atual (Obrigatória para alterar)</label>
                                <input type="password" name="senha_atual" placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-3 ml-1">Nova Senha</label>
                                <input type="password" name="nova_senha" placeholder="Apenas se desejar alterar" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-3 ml-1">Confirmar Nova Senha</label>
                                <input type="password" name="confirma_senha" placeholder="Apenas se desejar alterar" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 text-right">
                        <button type="submit" class="px-10 py-5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-[2rem] font-black text-[11px] uppercase tracking-[0.2em] shadow-xl shadow-indigo-200 dark:shadow-none transition-all active:scale-95 flex items-center justify-center ml-auto">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Guardar Alterações
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'shared/ui/footer.php'; ?>