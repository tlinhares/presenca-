<?php
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

if (empty($_SESSION['usuario_categoria']) || $_SESSION['usuario_categoria'] !== 'admin') {
    header('Location: ../resumo.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicados - Intranet AOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #1d4e8f 0%, #3fa3c4 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(29,78,143,.35); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #2d3748; }
        .help { font-size: .82rem; color: #718096; }
        .badge-status { font-size: .75rem; }
        .com-row { border-bottom: 1px solid #edf2f7; padding: .9rem .25rem; }
        .com-row:last-child { border-bottom: none; }
        .com-titulo { font-weight: 700; color: #1d2b45; }
        .com-meta { font-size: .8rem; color: #718096; }
        .com-thumb { width: 64px; height: 44px; object-fit: cover; border-radius: 8px; }
        .btn-icon { padding: .25rem .5rem; font-size: .85rem; }
        .preview-img { max-width: 100%; max-height: 160px; border-radius: 10px; }
    </style>
    <link href="../css/aom-ui.css?v=<?= time() ?>" rel="stylesheet"> <!-- design system AOM (carregar por último) -->
    <script src="../js/aom-ui.js?v=<?= time() ?>"></script>
</head>
<body>

<div class="header-page">
    <div class="container d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Comunicados Internos</h5>
        <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">

        <!-- Formulário -->
        <div class="col-lg-5">
            <div class="card-main p-4">
                <h6 class="fw-bold mb-3" id="form-titulo-h"><i class="bi bi-plus-circle me-1"></i> Novo comunicado</h6>
                <form id="formCom" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="f-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="titulo" id="f-titulo" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Corpo <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="corpo" id="f-corpo" rows="4" required></textarea>
                        <div class="help">Texto exibido no app. O push (opcional) usa os primeiros 140 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Imagem (opcional)</label>
                        <input type="file" class="form-control" name="imagem" id="f-imagem" accept="image/jpeg,image/png,image/webp">
                        <div class="help">JPG, PNG ou WebP até 3MB. Aparece como banner no app.</div>
                        <div id="img-atual" class="mt-2 d-none">
                            <img src="" class="preview-img" id="img-atual-src">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remover_imagem" value="1" id="f-remover-img">
                                <label class="form-check-label help" for="f-remover-img">Remover imagem atual</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link externo (opcional)</label>
                        <input type="url" class="form-control" name="link" id="f-link" placeholder="https://...">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Expira em (opcional)</label>
                            <input type="datetime-local" class="form-control" name="expira_em" id="f-expira">
                            <div class="help">Depois disso some do app.</div>
                        </div>
                        <div class="col-5 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="destaque" value="1" id="f-destaque">
                                <label class="form-check-label" for="f-destaque">Destaque na Home</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-save"></i> Salvar rascunho
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btn-cancelar-edicao">Cancelar</button>
                    </div>
                    <div class="help mt-2"><i class="bi bi-info-circle"></i> Salvar cria um <b>rascunho</b>. Publicar é uma ação separada na lista ao lado.</div>
                </form>
                <div id="msg-form" class="alert d-none mt-3 mb-0"></div>
            </div>
        </div>

        <!-- Lista -->
        <div class="col-lg-7">
            <div class="card-main p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-1"></i> Comunicados</h6>
                    <select class="form-select form-select-sm w-auto" id="filtro-status">
                        <option value="todos">Todos</option>
                        <option value="publicado">Publicados</option>
                        <option value="rascunho">Rascunhos</option>
                        <option value="arquivado">Arquivados</option>
                    </select>
                </div>
                <div id="lista-coms"><div class="text-muted text-center py-4">Carregando...</div></div>
            </div>
        </div>

    </div>
</div>

<!-- Modal publicar -->
<div class="modal fade" id="modalPublicar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-megaphone me-1"></i> Publicar comunicado</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">O comunicado <b id="pub-titulo"></b> ficará visível no aplicativo imediatamente.</p>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="pub-push" checked>
                    <label class="form-check-label" for="pub-push">Enviar notificação push para todos os usuários</label>
                </div>
                <div class="help mt-1">O push sai em até 1 minuto (fila do cron).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="pub-confirmar"><i class="bi bi-send"></i> Publicar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
const API = '<?= MenuPermissaoService::ajustarUrl('/api/painel/comunicados') ?>';
let pubId = null;
const modalPublicar = new bootstrap.Modal(document.getElementById('modalPublicar'));

function esc(s) { return $('<span>').text(s ?? '').html(); }

function badge(status) {
    const map = { publicado: 'success', rascunho: 'secondary', arquivado: 'dark' };
    return `<span class="badge bg-${map[status]} badge-status">${status}</span>`;
}

function msgForm(ok, txt) {
    $('#msg-form').removeClass('d-none alert-success alert-danger')
        .addClass(ok ? 'alert-success' : 'alert-danger').text(txt);
    setTimeout(() => $('#msg-form').addClass('d-none'), 5000);
}

function carregar() {
    $.getJSON(API + '/listar.php', { status: $('#filtro-status').val() }, function (res) {
        if (res.status !== 'ok') { $('#lista-coms').html('<div class="text-danger">Erro ao carregar</div>'); return; }
        if (!res.comunicados.length) { $('#lista-coms').html('<div class="text-muted text-center py-4">Nenhum comunicado</div>'); return; }
        let html = '';
        for (const c of res.comunicados) {
            const img = c.imagem ? `<img src="${c.imagem}" class="com-thumb me-2">` : '';
            const dest = c.destaque ? '<i class="bi bi-star-fill text-warning" title="Destaque na Home"></i> ' : '';
            const exp = c.expira_em ? ` · expira ${c.expira_em.substring(0, 16)}` : '';
            let acoes = `<button class="btn btn-outline-primary btn-icon" onclick="editar(${c.id})" title="Editar"><i class="bi bi-pencil"></i></button> `;
            if (c.status !== 'publicado') {
                acoes += `<button class="btn btn-success btn-icon" onclick="abrirPublicar(${c.id}, this)" data-titulo="${esc(c.titulo)}" title="Publicar"><i class="bi bi-megaphone"></i></button> `;
                acoes += `<button class="btn btn-outline-danger btn-icon" onclick="excluir(${c.id})" title="Excluir"><i class="bi bi-trash"></i></button>`;
            } else {
                acoes += `<button class="btn btn-outline-warning btn-icon" onclick="alterarStatus(${c.id}, 'despublicar')" title="Voltar para rascunho"><i class="bi bi-arrow-counterclockwise"></i></button> `;
                acoes += `<button class="btn btn-outline-dark btn-icon" onclick="alterarStatus(${c.id}, 'arquivar')" title="Arquivar"><i class="bi bi-archive"></i></button>`;
            }
            html += `
            <div class="com-row d-flex align-items-start" data-json='${JSON.stringify(c).replace(/'/g, "&#39;")}'>
                ${img}
                <div class="flex-grow-1">
                    <div class="com-titulo">${dest}${esc(c.titulo)} ${badge(c.status)}</div>
                    <div class="com-meta">
                        ${c.publicado_em ? 'publicado ' + c.publicado_em.substring(0, 16) : 'criado ' + c.criado_em.substring(0, 16)}
                        por ${esc(c.criado_por_nome || '—')}${exp}
                        · <i class="bi bi-eye"></i> ${c.leituras} leram
                    </div>
                </div>
                <div class="text-nowrap ms-2">${acoes}</div>
            </div>`;
        }
        $('#lista-coms').html(html);
    });
}

$('#formCom').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({
        url: API + '/salvar.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
        success: function (res) {
            if (res.status === 'ok') {
                msgForm(true, 'Salvo! Use o botão de megafone na lista para publicar.');
                cancelarEdicao();
                carregar();
            } else msgForm(false, res.mensagem);
        },
        error: function (xhr) {
            msgForm(false, xhr.status === 401 ? 'Sessão expirada — recarregue a página e faça login.' : 'Erro de comunicação');
        }
    });
});

function editar(id) {
    const row = $(`.com-row`).filter(function () { return JSON.parse($(this).attr('data-json')).id === id; });
    const c = JSON.parse(row.attr('data-json'));
    $('#f-id').val(c.id);
    $('#f-titulo').val(c.titulo);
    $('#f-corpo').val(c.corpo);
    $('#f-link').val(c.link || '');
    $('#f-expira').val(c.expira_em ? c.expira_em.substring(0, 16).replace(' ', 'T') : '');
    $('#f-destaque').prop('checked', c.destaque === 1);
    $('#f-remover-img').prop('checked', false);
    if (c.imagem) { $('#img-atual').removeClass('d-none'); $('#img-atual-src').attr('src', c.imagem); }
    else $('#img-atual').addClass('d-none');
    $('#form-titulo-h').html('<i class="bi bi-pencil me-1"></i> Editando comunicado #' + c.id);
    $('#btn-cancelar-edicao').removeClass('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelarEdicao() {
    $('#formCom')[0].reset();
    $('#f-id').val('');
    $('#img-atual').addClass('d-none');
    $('#form-titulo-h').html('<i class="bi bi-plus-circle me-1"></i> Novo comunicado');
    $('#btn-cancelar-edicao').addClass('d-none');
}
$('#btn-cancelar-edicao').on('click', cancelarEdicao);

function abrirPublicar(id, el) {
    pubId = id;
    $('#pub-titulo').text($(el).data('titulo'));
    $('#pub-push').prop('checked', true);
    modalPublicar.show();
}

$('#pub-confirmar').on('click', function () {
    alterarStatus(pubId, 'publicar', $('#pub-push').is(':checked'));
    modalPublicar.hide();
});

function alterarStatus(id, acao, enviarPush = false) {
    $.ajax({
        url: API + '/alterar_status.php', type: 'POST', contentType: 'application/json',
        data: JSON.stringify({ id: id, acao: acao, enviar_push: enviarPush }), dataType: 'json',
        success: function (res) {
            if (res.status === 'ok') { msgForm(true, res.mensagem + (res.push ? ' — ' + res.push : '')); carregar(); }
            else msgForm(false, res.mensagem);
        },
        error: function (xhr) {
            msgForm(false, xhr.status === 401 ? 'Sessão expirada — recarregue a página e faça login.' : 'Erro de comunicação');
        }
    });
}

function excluir(id) {
    if (!confirm('Excluir definitivamente este comunicado?')) return;
    $.ajax({
        url: API + '/excluir.php', type: 'POST', contentType: 'application/json',
        data: JSON.stringify({ id: id }), dataType: 'json',
        success: function (res) {
            if (res.status === 'ok') { msgForm(true, res.mensagem); carregar(); }
            else msgForm(false, res.mensagem);
        },
        error: function (xhr) {
            msgForm(false, xhr.status === 401 ? 'Sessão expirada — recarregue a página e faça login.' : 'Erro de comunicação');
        }
    });
}

$('#filtro-status').on('change', carregar);
carregar();
</script>
</body>
</html>
