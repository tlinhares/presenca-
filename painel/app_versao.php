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
    <title>Versão do App - Intranet AOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-page { color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 1000; }
        .card-main { padding: 1.5rem; }
        .help { font-size: .82rem; color: #718096; }
    </style>
    <link href="../css/aom-ui.css?v=<?= time() ?>" rel="stylesheet"> <!-- design system AOM -->
</head>
<body>

<div class="header-page">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm" title="Voltar ao Painel"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Versão do App</h5>
                <small class="opacity-75">Atualização obrigatória — versão mínima e links das lojas</small>
            </div>
        </div>
    </div>
</div>

<div class="container py-4" style="max-width: 720px;">
    <div class="card-main card">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Cuidado:</strong> subir a versão mínima <u>bloqueia</u> todo aparelho com versão
            menor até o usuário atualizar pela loja. Só aumente depois que a versão nova estiver
            disponível na Play Store <em>e</em> na App Store.
        </div>

        <div class="mb-3">
            <label class="form-label">Versão mínima <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="versao_minima" placeholder="1.0.5" style="max-width: 160px;">
            <div class="help">Formato x.y.z. Aparelhos com versão menor verão a tela de atualização obrigatória.</div>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-android2 text-success me-1"></i>Link Play Store (Android) <span class="text-danger">*</span></label>
            <input type="url" class="form-control" id="url_android">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-apple me-1"></i>Link App Store (iPhone) <span class="text-danger">*</span></label>
            <input type="url" class="form-control" id="url_ios">
            <div class="help">App não listado — o link é fixo e não muda entre versões.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Mensagem da tela de bloqueio</label>
            <textarea class="form-control" id="mensagem" rows="2" maxlength="300"></textarea>
            <div class="help">Opcional — vazio usa o texto padrão do app.</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-primary" id="btnSalvar"><i class="bi bi-save me-1"></i>Salvar</button>
            <small class="help" id="ultima-alteracao"></small>
        </div>
        <div id="msg" class="alert d-none mt-3 mb-0"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
const API = '<?= MenuPermissaoService::ajustarUrl('/api/painel/app_versao') ?>';

function msg(ok, texto) {
    $('#msg').removeClass('d-none alert-success alert-danger')
        .addClass(ok ? 'alert-success' : 'alert-danger').text(texto);
    setTimeout(() => $('#msg').addClass('d-none'), 6000);
}

$.getJSON(API + '/obter.php', function (r) {
    if (r.status !== 'ok') { msg(false, 'Erro ao carregar configuração'); return; }
    $('#versao_minima').val(r.config.versao_minima);
    $('#url_android').val(r.config.url_android);
    $('#url_ios').val(r.config.url_ios);
    $('#mensagem').val(r.config.mensagem);
    if (r.config.atualizado_por_nome) {
        $('#ultima-alteracao').text('Última alteração: ' + r.config.atualizado_em + ' por ' + r.config.atualizado_por_nome);
    }
});

$('#btnSalvar').on('click', function () {
    const btn = $(this).prop('disabled', true);
    $.ajax({
        url: API + '/salvar.php', type: 'POST', contentType: 'application/json',
        data: JSON.stringify({
            versao_minima: $('#versao_minima').val().trim(),
            url_android: $('#url_android').val().trim(),
            url_ios: $('#url_ios').val().trim(),
            mensagem: $('#mensagem').val().trim(),
        }),
        dataType: 'json',
        success: r => msg(r.status === 'ok', r.mensagem),
        error: xhr => msg(false, xhr.status === 401 ? 'Sessão expirada — recarregue a página.' : 'Erro de comunicação'),
        complete: () => btn.prop('disabled', false),
    });
});
</script>
</body>
</html>
