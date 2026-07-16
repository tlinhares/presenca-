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
    <title>Divulgar App - Intranet AOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #1d4e8f 0%, #3fa3c4 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(29,78,143,.35); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #2d3748; }
        .help { font-size: .82rem; color: #718096; }
        .canal-check { border: 2px solid #e2e8f0; border-radius: 12px; padding: .8rem 1rem; cursor: pointer; display:flex; align-items:center; gap:.6rem; font-weight:600; color:#4a5568; user-select:none; }
        .canal-check.on { border-color:#1d4e8f; background:#e9f0fa; color:#1d4e8f; }
        .canal-check i { font-size: 1.3rem; }
        .preview-box { background:#e7f6e7; border:1px solid #bfe3bf; border-radius:12px; padding:14px; white-space:pre-wrap; font-size:.92rem; }
        .progress { height: 22px; border-radius: 12px; }
    </style>
</head>
<body>

<div class="header-page">
    <div class="container d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-phone-vibrate-fill me-2"></i>Divulgar Aplicativo</h5>
        <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="container py-4" style="max-width: 860px;">

    <!-- 1. Links das lojas -->
    <div class="card-main p-4 mb-4">
        <h5 class="mb-3"><i class="bi bi-1-square me-2"></i>Links das lojas</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><i class="bi bi-android2 text-success me-1"></i>Google Play (Android)</label>
                <input type="url" class="form-control" id="link_android" placeholder="https://play.google.com/store/apps/details?id=...">
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="bi bi-apple me-1"></i>App Store (iPhone)</label>
                <input type="url" class="form-control" id="link_ios" placeholder="https://apps.apple.com/br/app/...">
            </div>
        </div>
    </div>

    <!-- 2. Mensagem -->
    <div class="card-main p-4 mb-4">
        <h5 class="mb-3"><i class="bi bi-2-square me-2"></i>Mensagem</h5>
        <div class="mb-3">
            <label class="form-label">Assunto (usado no e-mail)</label>
            <input type="text" class="form-control" id="assunto" maxlength="160">
        </div>
        <div class="mb-2">
            <label class="form-label">Texto</label>
            <textarea class="form-control" id="mensagem" rows="9"></textarea>
            <div class="help mt-1">
                Placeholders: <code>{nome}</code> (primeiro nome do usuário), <code>{link_android}</code>, <code>{link_ios}</code>.
                No WhatsApp, *asteriscos* deixam em negrito.
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" id="btnSalvar"><i class="bi bi-save me-1"></i>Salvar</button>
            <button class="btn btn-outline-secondary" id="btnPreview"><i class="bi bi-eye me-1"></i>Pré-visualizar</button>
        </div>
        <div id="preview" class="preview-box mt-3" style="display:none;"></div>
    </div>

    <!-- 3. Envio -->
    <div class="card-main p-4 mb-4">
        <h5 class="mb-3"><i class="bi bi-3-square me-2"></i>Enviar</h5>

        <label class="form-label d-block">Canais</label>
        <div class="d-flex gap-2 flex-wrap mb-2">
            <div class="canal-check on" data-canal="whatsapp" onclick="toggleCanal(this)">
                <i class="bi bi-whatsapp text-success"></i>WhatsApp <span class="badge bg-light text-dark" id="alc-whats">–</span>
            </div>
            <div class="canal-check on" data-canal="email" onclick="toggleCanal(this)">
                <i class="bi bi-envelope text-primary"></i>E-mail <span class="badge bg-light text-dark" id="alc-email">–</span>
            </div>
        </div>
        <div class="help mb-3">Os números mostram quantos dos <span id="alc-ativos">–</span> usuários ativos têm telefone/e-mail cadastrado.</div>

        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" id="btnTestar">
                <i class="bi bi-send-check me-1"></i>Enviar teste pra mim
            </button>
            <button class="btn btn-danger" id="btnDisparar" style="background:#1d4e8f; border:none;">
                <i class="bi bi-megaphone-fill me-1"></i>Enviar para TODOS
            </button>
        </div>
        <div id="resultado" class="mt-3"></div>
    </div>

    <!-- 4. Progresso -->
    <div class="card-main p-4" id="card-progresso" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Progresso do disparo</h5>
            <button class="btn btn-sm btn-outline-danger" id="btnCancelar"><i class="bi bi-x-octagon me-1"></i>Cancelar pendentes</button>
        </div>
        <div class="progress mb-2"><div class="progress-bar progress-bar-striped" id="barra" style="width:0%">0%</div></div>
        <div class="small text-muted" id="prog-detalhe"></div>
        <div id="prog-falhas" class="mt-2"></div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
const API = '<?= MenuPermissaoService::ajustarUrl('/api/painel/divulgacao') ?>';
let poller = null;

function canaisSelecionados() {
    return $('.canal-check.on').map(function() { return $(this).data('canal'); }).get();
}
function toggleCanal(el) { $(el).toggleClass('on'); }

function msg(html, cls) {
    $('#resultado').html(`<div class="alert alert-${cls} mb-0">${html}</div>`);
}

function carregar() {
    $.getJSON(API + '/obter.php', function(r) {
        if (r.status !== 'ok') { msg('Erro ao carregar configuração', 'danger'); return; }
        $('#link_android').val(r.config.link_android);
        $('#link_ios').val(r.config.link_ios);
        $('#assunto').val(r.config.assunto);
        $('#mensagem').val(r.config.mensagem);
        $('#alc-ativos').text(r.alcance.ativos);
        $('#alc-whats').text(r.alcance.com_telefone);
        $('#alc-email').text(r.alcance.com_email);
    });
}

function salvar(cb) {
    $.ajax({
        url: API + '/salvar.php', type: 'POST', contentType: 'application/json',
        data: JSON.stringify({
            link_android: $('#link_android').val().trim(),
            link_ios: $('#link_ios').val().trim(),
            assunto: $('#assunto').val().trim(),
            mensagem: $('#mensagem').val().trim(),
        }),
        dataType: 'json',
        success: function(r) {
            if (r.status === 'ok') { if (cb) cb(); else msg('<i class="bi bi-check-circle"></i> ' + r.mensagem, 'success'); }
            else msg(r.mensagem, 'danger');
        },
        error: function(xhr) { msg(xhr.status === 401 ? 'Sessão expirada — recarregue a página.' : 'Erro de comunicação', 'danger'); }
    });
}

$('#btnSalvar').on('click', function() { salvar(); });

$('#btnPreview').on('click', function() {
    const m = $('#mensagem').val()
        .replaceAll('{nome}', 'Tiago')
        .replaceAll('{link_android}', $('#link_android').val().trim() || '(link em breve)')
        .replaceAll('{link_ios}', $('#link_ios').val().trim() || '(link em breve)');
    $('#preview').text(m).toggle();
});

$('#btnTestar').on('click', function() {
    const canais = canaisSelecionados();
    if (!canais.length) { msg('Selecione ao menos um canal', 'warning'); return; }
    const btn = $(this).prop('disabled', true);
    msg('<i class="bi bi-hourglass-split"></i> Salvando e enviando teste…', 'info');
    salvar(function() {
        $.ajax({
            url: API + '/testar.php', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ canais: canais }), dataType: 'json',
            success: function(r) {
                let det = '';
                if (r.resultados) {
                    det = '<ul class="mb-0 mt-1">';
                    for (const [canal, res] of Object.entries(r.resultados)) {
                        det += `<li>${canal}: ${res.sucesso ? '✅ enviado' : '❌ ' + res.mensagem}</li>`;
                    }
                    det += '</ul>';
                }
                msg((r.status === 'ok' ? '<i class="bi bi-check-circle"></i> ' : '⚠️ ') + r.mensagem + det, r.status === 'ok' ? 'success' : 'warning');
            },
            error: function() { msg('Erro de comunicação no teste', 'danger'); },
            complete: function() { btn.prop('disabled', false); }
        });
    });
});

$('#btnDisparar').on('click', function() {
    const canais = canaisSelecionados();
    if (!canais.length) { msg('Selecione ao menos um canal', 'warning'); return; }
    if (!confirm(`⚠️ Enviar a divulgação para TODOS os usuários ativos por ${canais.join(' + ')}?\n\nO envio é gradual (WhatsApp tem intervalo anti-bloqueio) e pode levar vários minutos.`)) return;
    const btn = $(this).prop('disabled', true);
    msg('<i class="bi bi-hourglass-split"></i> Salvando e enfileirando…', 'info');
    salvar(function() {
        $.ajax({
            url: API + '/disparar.php', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ canais: canais }), dataType: 'json',
            success: function(r) {
                if (r.status === 'ok') {
                    msg('<i class="bi bi-megaphone"></i> ' + r.mensagem, 'success');
                    iniciarPoll();
                } else {
                    msg(r.mensagem, 'danger');
                }
            },
            error: function() { msg('Erro de comunicação no disparo', 'danger'); },
            complete: function() { btn.prop('disabled', false); }
        });
    });
});

$('#btnCancelar').on('click', function() {
    if (!confirm('Cancelar todos os envios ainda pendentes?')) return;
    $.ajax({
        url: API + '/cancelar.php', type: 'POST', dataType: 'json',
        success: function(r) { msg(r.mensagem, r.status === 'ok' ? 'success' : 'danger'); atualizarProgresso(); }
    });
});

function iniciarPoll() {
    $('#card-progresso').show();
    atualizarProgresso();
    if (poller) clearInterval(poller);
    poller = setInterval(atualizarProgresso, 5000);
}

function atualizarProgresso() {
    $.getJSON(API + '/status.php', function(r) {
        if (r.status !== 'ok' || !r.lote) return;
        const l = r.lote;
        $('#card-progresso').show();
        $('#barra').css('width', l.percentual + '%').text(l.percentual + '%')
            .toggleClass('progress-bar-animated', l.em_andamento > 0)
            .toggleClass('bg-success', l.em_andamento === 0);
        let det = `Total: ${l.total} · Finalizados: ${l.finalizados} · Na fila: ${l.em_andamento}`;
        const c = l.contagem;
        for (const canal of ['whatsapp', 'email']) {
            if (c[canal]) {
                const env = c[canal].enviado || 0, fal = c[canal].falha || 0, pen = (c[canal].pendente || 0) + (c[canal].processando || 0);
                det += `<br>${canal}: ✅ ${env} enviados · ❌ ${fal} falhas · ⏳ ${pen} na fila`;
            }
        }
        $('#prog-detalhe').html(det);
        if (l.ultimas_falhas && l.ultimas_falhas.length) {
            let h = '<div class="alert alert-warning py-2 small mb-0"><b>Últimas falhas:</b><ul class="mb-0">';
            l.ultimas_falhas.forEach(f => h += `<li>${$('<i/>').text(f.nome).html()} (${f.canal}): ${$('<i/>').text(f.erro || '').html()}</li>`);
            h += '</ul></div>';
            $('#prog-falhas').html(h);
        } else $('#prog-falhas').empty();
        if (l.em_andamento === 0 && poller) { clearInterval(poller); poller = null; }
    });
}

carregar();
atualizarProgresso(); // se já houver lote em andamento, mostra ao abrir
</script>
</body>
</html>
