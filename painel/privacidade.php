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
    <title>Política de Privacidade - Editar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #4c51bf 0%, #553c9a 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(76,81,191,.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .editor-area textarea { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; min-height: 520px; resize: vertical; }
        .preview-area { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; max-height: 600px; overflow: auto; }
        .preview-area h1 { font-size: 1.6rem; }
        .preview-area h2 { font-size: 1.15rem; margin-top: 1.5rem; color: #2d3748; border-bottom: 1px solid #edf2f7; padding-bottom: .35rem; }
        .preview-area p, .preview-area li { color: #4a5568; line-height: 1.6; }
        .preview-area a { color: #4c51bf; }
        .meta-row { font-size: .8rem; color: #718096; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../resumo.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Política de Privacidade</h5>
                        <small class="opacity-75">Edita o conteúdo exibido em <code>/privacidade.html</code></small>
                    </div>
                </div>
                <a href="../privacidade.html" target="_blank" class="btn btn-light btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Abrir página pública
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main p-4 mb-3">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="versao">Versão</label>
                    <input type="text" class="form-control" id="versao" maxlength="20" placeholder="1.0">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="vigente_desde">Vigente desde</label>
                    <input type="date" class="form-control" id="vigente_desde">
                </div>
                <div class="col-md-6 text-md-end">
                    <button type="button" class="btn btn-success px-4" id="btnSalvar" style="background: #4c51bf; border: none;">
                        <i class="bi bi-check2-circle me-1"></i>Salvar
                    </button>
                    <small class="meta-row d-block mt-1" id="meta"></small>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6 editor-area">
                    <label class="form-label fw-semibold">
                        Conteúdo HTML
                        <small class="text-muted fw-normal">(use tags como <code>&lt;h2&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;a&gt;</code>)</small>
                    </label>
                    <textarea class="form-control" id="conteudo_html" spellcheck="false"></textarea>
                </div>
                <div class="col-lg-6">
                    <label class="form-label fw-semibold">Pré-visualização</label>
                    <div class="preview-area" id="preview">
                        <em class="text-muted">Carregando…</em>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-main p-3 small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            A página pública (<code>/privacidade.html</code>) carrega esse conteúdo via API com cache de 5 minutos. Após salvar, talvez seja preciso atualizar a aba pública (Ctrl+F5) para ver na hora.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        function renderPreview() {
            $('#preview').html($('#conteudo_html').val() || '<em class="text-muted">(vazio)</em>');
        }

        function carregar() {
            $.getJSON(baseUrl + '/api/privacidade/obter.php', function(data) {
                if (data.status !== 'ok') {
                    exibirToast(data.mensagem || 'Erro ao carregar', 'danger');
                    return;
                }
                $('#conteudo_html').val(data.conteudo_html || '');
                $('#versao').val(data.versao || '1.0');
                $('#vigente_desde').val((data.vigente_desde || '').substring(0, 10));
                if (data.atualizado_em) $('#meta').text('Última alteração: ' + data.atualizado_em);
                renderPreview();
            }).fail(function() { exibirToast('Erro de comunicação', 'danger'); });
        }

        $('#conteudo_html').on('input', renderPreview);

        $('#btnSalvar').on('click', function() {
            const payload = {
                conteudo_html: $('#conteudo_html').val().trim(),
                versao: $('#versao').val().trim() || '1.0',
                vigente_desde: $('#vigente_desde').val() || ''
            };
            if (!payload.conteudo_html) { exibirToast('Conteúdo não pode ficar vazio', 'warning'); return; }
            $('#btnSalvar').prop('disabled', true);
            $.ajax({
                url: baseUrl + '/api/privacidade/salvar.php',
                type: 'POST', contentType: 'application/json',
                data: JSON.stringify(payload), dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        exibirToast(res.mensagem, 'success');
                        carregar();
                    } else { exibirToast(res.mensagem || 'Erro ao salvar', 'danger'); }
                },
                error: function() { exibirToast('Erro de comunicação ao salvar', 'danger'); },
                complete: function() { $('#btnSalvar').prop('disabled', false); }
            });
        });

        carregar();
    </script>
</body>
</html>
