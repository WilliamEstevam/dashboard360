<?php

$currentView = 'comunidade';
include 'shared/ui/header.php';
require_once 'shared/config/database.php';

$successMsg = '';
$errorMsg = '';

// Garante que a variável user existe (vinda do index.php/sessão)
$usuarioAtual = isset($user) ? $user : array('nome' => 'Desconhecido', 'cargo' => 'Operador', 'foto' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comunidade_submit'])) {
    
    if (!dashboard_csrf_validate()) {
        $errorMsg = 'Token de segurança inválido. Recarregue a página.';
    } else {

    $conteudo = dashboard_escape_sql(trim($_POST['conteudo']));
    $tipo     = dashboard_escape_sql(trim($_POST['tipo']));
    
    $autorNome  = dashboard_escape_sql($usuarioAtual['nome']);
    $autorCargo = dashboard_escape_sql($usuarioAtual['cargo']);
    $autorFoto  = dashboard_escape_sql($usuarioAtual['foto']);
    
    $dataCriacao = date('Y-m-d H:i:s');
    $imagemPath  = '';

    // Lógica de Upload de Imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $uploadResult = dashboard_validate_upload($_FILES['imagem'], 5 * 1024 * 1024, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
        if (!$uploadResult['valid']) {
            $errorMsg = $uploadResult['error'];
        } else {
            $pastaDestino = 'shared/assets/img/uploads/';

            if (!file_exists($pastaDestino)) {
                @mkdir($pastaDestino, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $novoNome = dashboard_safe_filename('post', $ext);
            $caminhoFinal = $pastaDestino . $novoNome;

            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoFinal)) {
                $imagemPath = $caminhoFinal;
            } else {
                $errorMsg = "Aviso: O post foi criado, mas não foi possível gravar a imagem no servidor.";
            }
        }
    }

    // Inserção na Base de Dados (Prepared Statement)
    $stmtInsert = db_prepare($db_conn,
        'INSERT INTO consultoria.tbl_comunidade_posts
            (autor_nome, autor_cargo, autor_foto, conteudo, tipo, likes, dislikes, data_criacao, imagem_path)
         VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)');
    $resInsert = db_execute($stmtInsert,
        array($autorNome, $autorCargo, $autorFoto, $conteudo, $tipo, $dataCriacao, $imagemPath));

    if ($resInsert) {
        $successMsg = 'Comunicado publicado com sucesso para toda a operação!';
    } else {
        $errorMsg = log_erro('Comunidade INSERT', db_errormsg($db_conn));
    }
    } // fecha else do CSRF
}

$listaPosts = array();
$sqlPosts = "SELECT id, autor_nome, autor_cargo, autor_foto, conteudo, tipo, likes, dislikes, data_criacao, imagem_path 
             FROM consultoria.tbl_comunidade_posts 
             ORDER BY data_criacao DESC";

$resPosts = @db_exec($db_conn, $sqlPosts);

if ($resPosts) {
    while (db_fetch_row($resPosts)) {
        $listaPosts[] = array(
            'id'          => db_result($resPosts, 'id'),
            'autor_nome'  => dashboard_utf8_encode(db_result($resPosts, 'autor_nome')),
            'autor_cargo' => dashboard_utf8_encode(db_result($resPosts, 'autor_cargo')),
            'autor_foto'  => db_result($resPosts, 'autor_foto'),
            'conteudo'    => dashboard_utf8_encode(db_result($resPosts, 'conteudo')),
            'tipo'        => trim(db_result($resPosts, 'tipo')),
            'likes'       => (int)db_result($resPosts, 'likes'),
            'dislikes'    => (int)db_result($resPosts, 'dislikes'),
            'data'        => db_result($resPosts, 'data_criacao'),
            'imagem_path' => trim(db_result($resPosts, 'imagem_path'))
        );
    }
}
?>

<div class="p-10 max-w-4xl mx-auto animate-in fade-in duration-700">
    <!-- Título -->
    <div class="mb-10 text-center">
        <h3 class="text-4xl font-black text-slate-800 dark:text-white tracking-tighter italic uppercase">
            Comunidade<span class="text-indigo-600 dark:text-indigo-400">Dashboard</span>
        </h3>
        <p class="text-slate-400 dark:text-slate-500 font-medium text-sm mt-2 tracking-tight">Fique por dentro das últimas atualizações e alertas da operação.</p>
    </div>

    <!-- Alertas -->
    <?php if (!empty($successMsg)): ?>
        <div class="mb-8 p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 rounded-3xl text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center shadow-sm animate-in slide-in-from-top">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            <?php echo h($successMsg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-8 p-6 bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 rounded-3xl text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-widest flex items-center shadow-sm animate-in slide-in-from-top">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
            <?php echo h($errorMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de Novo Post (Visível apenas para Gestão/Admin) -->
    <?php if (isset($usuarioAtual['cargo']) && in_array(strtolower($usuarioAtual['cargo']), array('administrador', 'gerente', 'coordenador'))): ?>
    <div class="bg-white dark:bg-[#0f172a] border border-slate-100 dark:border-slate-800 rounded-[3rem] p-10 mb-12 shadow-sm transition-colors duration-500">
        <form method="POST" enctype="multipart/form-data" action="index.php?route=comunidade" class="space-y-6">
            <?php echo dashboard_csrf_field(); ?>
            <div class="flex items-center space-x-4 mb-2">
                <div class="w-10 h-10 bg-indigo-600 dark:bg-indigo-500 rounded-2xl flex items-center justify-center text-white font-black text-xs overflow-hidden shadow-lg shadow-indigo-100 dark:shadow-none border-2 border-white dark:border-[#0f172a]">
                    <?php if (!empty($usuarioAtual['foto'])): ?>
                        <img src="<?php echo $usuarioAtual['foto']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span><?php echo substr($usuarioAtual['nome'], 0, 1); ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-[11px] font-black text-slate-800 dark:text-white uppercase tracking-widest italic">Nova Publicação</p>
            </div>
            
            <textarea name="conteudo" required rows="4" 
                class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-[2rem] p-8 text-sm text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-all resize-none font-medium placeholder-slate-400 dark:placeholder-slate-500" 
                placeholder="O que deseja informar à equipa hoje?"></textarea>
            
            <!-- Pré-visualização Simples da Imagem -->
            <div id="preview-post-container" class="hidden relative mt-2 mb-4 w-fit">
                <img id="preview-post-img" src="" class="rounded-3xl max-h-48 object-cover border border-slate-200 dark:border-slate-700 shadow-sm">
                <button type="button" onclick="document.getElementById('preview-post-container').classList.add('hidden'); document.getElementById('foto-upload-input').value='';" class="absolute -top-3 -right-3 bg-red-500 text-white rounded-full p-1.5 shadow-md hover:bg-red-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <select name="tipo" class="bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl px-6 py-4 text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 outline-none focus:border-indigo-500 cursor-pointer transition-colors">
                        <option value="SISTEMA">💠 SISTEMA</option>
                        <option value="ALERTA">⚠️ ALERTA</option>
                        <option value="URGENTE">🚨 URGENTE</option>
                    </select>

                    <label class="cursor-pointer flex items-center space-x-3 px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all group">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-[10px] font-black uppercase tracking-widest">Anexar Foto</span>
                        <input type="file" id="foto-upload-input" name="imagem" accept="image/*" class="hidden" onchange="const fr=new FileReader();fr.onload=function(){document.getElementById('preview-post-img').src=fr.result;document.getElementById('preview-post-container').classList.remove('hidden');};fr.readAsDataURL(this.files[0]);">
                    </label>
                </div>

                <button type="submit" name="post_comunidade_submit" class="px-10 py-5 bg-indigo-600 dark:bg-indigo-500 text-white rounded-[2rem] font-black text-[11px] uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 dark:shadow-none hover:bg-indigo-700 dark:hover:bg-indigo-600 transition-all active:scale-95">
                    Publicar Comunicado
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Lista de Posts (Feed) -->
    <div class="space-y-12">
        <?php if (empty($listaPosts)): ?>
            <div class="text-center py-24 bg-white dark:bg-[#0f172a] rounded-[3.5rem] border border-slate-100 dark:border-slate-800 border-dashed">
                <div class="w-20 h-20 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300 dark:text-slate-600">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path></svg>
                </div>
                <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest text-[10px]">Ainda não há comunicados publicados</p>
            </div>
        <?php else: foreach ($listaPosts as $post): ?>
            <article class="bg-white dark:bg-[#0f172a] border border-slate-50 dark:border-slate-800 rounded-[3.5rem] shadow-sm overflow-hidden hover:shadow-xl hover:shadow-slate-100/50 dark:hover:shadow-indigo-500/5 transition-all duration-700">
                <!-- Header do Post -->
                <div class="p-10 pb-6 flex justify-between items-start">
                    <div class="flex items-center space-x-5">
                        <div class="w-14 h-14 rounded-[1.5rem] bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center overflow-hidden border-2 border-white dark:border-[#0f172a] shadow-inner text-indigo-600 font-black">
                            <?php if (!empty($post['autor_foto'])): ?>
                                <img src="<?php echo $post['autor_foto']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span><?php echo substr($post['autor_nome'], 0, 1); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-base font-black text-slate-800 dark:text-white leading-none uppercase tracking-tighter italic"><?php echo h($post['autor_nome']); ?></p>
                            <p class="text-[9px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-[0.2em] mt-2"><?php echo h($post['autor_cargo']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest"><?php echo date('d/m/Y', strtotime($post['data'])); ?></span>
                        <p class="text-[9px] font-bold text-slate-300 dark:text-slate-600 uppercase mt-1"><?php echo date('H:i', strtotime($post['data'])); ?></p>
                    </div>
                </div>

                <!-- Conteúdo -->
                <div class="px-10 py-4">
                    <div class="mb-6">
                        <?php 
                            $badgeClass = 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700';
                            if($post['tipo'] == 'URGENTE') $badgeClass = 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/20';
                            if($post['tipo'] == 'ALERTA') $badgeClass = 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/20';
                        ?>
                        <span class="px-4 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest border <?php echo $badgeClass; ?>">
                            <?php echo $post['tipo']; ?>
                        </span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed font-medium">
                        <?php echo nl2br(h($post['conteudo'])); ?>
                    </p>
                </div>

                <!-- Imagem Anexada -->
                <?php if (!empty($post['imagem_path'])): ?>
                <div class="px-10 py-6">
                    <div class="rounded-[2.5rem] overflow-hidden border-4 border-slate-50 dark:border-slate-800/50 shadow-sm bg-slate-50 dark:bg-slate-900">
                        <img src="<?php echo $post['imagem_path']; ?>" class="w-full h-auto object-cover max-h-[600px] hover:scale-105 transition-transform duration-1000">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Interações (Gosto/Não Gosto Visuais) -->
                <div class="p-10 pt-4 border-t border-slate-50 dark:border-slate-800 flex items-center space-x-10">
                    <button class="flex items-center space-x-3 text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all group">
                        <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center group-hover:bg-indigo-50 dark:group-hover:bg-indigo-500/10 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 10h4.704a2 2 0 011.94 2.415l-1.42 5.585A2 2 0 0117.284 20H8.716a2 2 0 01-1.94-1.585l-1.42-5.585A2 2 0 017.296 10H12V4.5A1.5 1.5 0 0113.5 3h.75a.75.75 0 01.75.75V10z"></path></svg>
                        </div>
                        <span class="text-xs font-black"><?php echo $post['likes']; ?></span>
                    </button>
                    <button class="flex items-center space-x-3 text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 transition-all group">
                         <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center group-hover:bg-red-50 dark:group-hover:bg-red-500/10 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14H5.296a2 2 0 01-1.94-2.415l1.42-5.585A2 2 0 016.716 4h8.568a2 2 0 011.94 1.585l1.42 5.585a2 2 0 01-1.94 2.415H12v5.5a1.5 1.5 0 01-1.5 1.5h-.75a.75.75 0 01-.75-.75V14z"></path></svg>
                         </div>
                        <span class="text-xs font-black"><?php echo $post['dislikes']; ?></span>
                    </button>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include 'shared/ui/footer.php'; ?>