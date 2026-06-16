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
    <title>Notificações Push - Configuração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .resumo-card { border-radius: 14px; padding: 1.1rem 1.25rem; color: #fff; }
        .resumo-card .n { font-size: 2rem; font-weight: 700; line-height: 1; }
        .resumo-card .l { font-size: .8rem; opacity: .9; }
        .resumo-disp { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); }
        .resumo-usr { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
        .resumo-env { background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); }
        .form-label { font-weight: 600; color: #2d3748; }
        .help { font-size: .82rem; color: #718096; }
        .badge-status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: .75rem; font-weight: 700; }
        .badge-ok { background: #c6f6d5; color: #22543d; }
        .badge-nok { background: #fed7d7; color: #822727; }
        textarea.sa-json { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../resumo.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-bell-fill me-2"></i>Notificações Push</h5>
                        <small class="opacity-75">Firebase Cloud Messaging — aplicativo mobile</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4" style="max-width: 960px;">
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-6">
                <div class="resumo-card resumo-disp">
                    <div class="n" id="r-dispositivos">–</div>
                    <div class="l">dispositivo(s) ativo(s)</div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="resumo-card resumo-usr">
                    <div class="n" id="r-usuarios">–</div>
                    <div class="l">usuário(s) alcançável(eis)</div>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="resumo-card resumo-env">
                    <div class="n"><span id="r-sucessos">–</span> <small style="font-size:.55em;opacity:.7;">/ <span id="r-envios">–</span></small></div>
                    <div class="l">envios sucesso / total (24h)</div>
                </div>
            </div>
        </div>

        <div class="card-main p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configuração</h5>
                <span class="badge-status" id="badge-status">–</span>
            </div>

            <form id="formConfig">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="ativo" style="width: 3rem; height: 1.5rem; cursor: pointer;">
                    <label class="form-check-label form-label ms-2" for="ativo">Notificações push ativas</label>
                    <div class="help ms-1">Quando desligado, dispositivos continuam registrados mas o backend não envia nada.</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label" for="titulo_padrao">Título padrão</label>
                        <input type="text" class="form-control" id="titulo_padrao" maxlength="120">
                        <div class="help mt-1">Usado quando o disparo não especifica um título próprio.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="som_padrao">Som padrão</label>
                        <input type="text" class="form-control" id="som_padrao" placeholder="default">
                        <div class="help mt-1">Nome do recurso de som (use <code>default</code> se não tiver custom).</div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Credenciais Firebase</h6>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Project ID</label>
                        <div class="form-control bg-light" id="info-project-id" style="min-height: 38px;">–</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client e-mail</label>
                        <div class="form-control bg-light text-truncate" id="info-client-email" style="min-height: 38px;">–</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sa_json">Service Account JSON</label>
                    <textarea class="form-control sa-json" id="sa_json" rows="10" placeholder='Cole aqui o JSON inteiro da Service Account exportado do Firebase Console > Project Settings > Service Accounts > "Generate new private key". Deixe vazio para manter a credencial atual.'></textarea>
                    <div class="help mt-1">
                        O arquivo é salvo em <code>/var/backups/presenca/firebase/</code> com permissão restrita. <strong>Não é exibido depois de salvo</strong> — só o <code>project_id</code> e <code>client_email</code> para confirmação.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 pt-2 border-top">
                    <button type="submit" class="btn px-4 text-white" id="btnSalvar" style="background: #ee5a6f; border: none;">
                        <i class="bi bi-check-lg me-1"></i>Salvar configuração
                    </button>
                    <small class="help" id="atualizado_em"></small>
                </div>
            </form>
        </div>

        <div class="card-main p-4">
            <h5 class="mb-3"><i class="bi bi-send me-2"></i>Enviar teste</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="teste_email">E-mail do destinatário</label>
                    <input type="email" class="form-control" id="teste_email" placeholder="usuario@aom.org.br">
                    <div class="help mt-1">Ou use o ID numérico no campo abaixo.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="teste_id">ID</label>
                    <input type="number" class="form-control" id="teste_id" placeholder="opcional">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="teste_titulo">Título</label>
                <input type="text" class="form-control" id="teste_titulo" value="Teste de notificação" maxlength="120">
            </div>
            <div class="mb-3">
                <label class="form-label" for="teste_corpo">Mensagem</label>
                <textarea class="form-control" id="teste_corpo" rows="2">Esta é uma notificação de teste enviada do painel.</textarea>
            </div>
            <button type="button" class="btn btn-primary" id="btnTeste" style="background: #3182ce; border: none;">
                <i class="bi bi-send me-1"></i>Enviar teste
            </button>
            <div class="mt-3" id="teste-resultado"></div>
        </div>

        <div class="help mt-3 px-1">
            <i class="bi bi-info-circle me-1"></i>
            Para receber, o usuário precisa abrir o aplicativo autenticado pelo menos uma vez — o app registra o token FCM automaticamente.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        function carregar() {
            $.getJSON(baseUrl + '/api/painel/notificacoes_push/obter.php', function(data) {
                if (data.status !== 'ok') {
                    exibirToast(data.mensagem || 'Erro ao carregar', 'danger');
                    return;
                }
                const c = data.config;
                $('#ativo').prop('checked', c.ativo === 1);
                $('#titulo_padrao').val(c.titulo_padrao || 'Presença AOM');
                $('#som_padrao').val(c.som_padrao || 'default');
                $('#info-project-id').text(c.project_id || '(não configurado)');
                $('#info-client-email').text(c.client_email || '(não configurado)');
                if (c.atualizado_em) $('#atualizado_em').text('Última alteração: ' + c.atualizado_em);

                const pronta = c.service_account_configurado && c.project_id && c.ativo === 1;
                $('#badge-status').toggleClass('badge-ok', pronta).toggleClass('badge-nok', !pronta)
                    .text(pronta ? 'OPERACIONAL' : (c.service_account_configurado ? 'CONFIGURADO MAS DESATIVADO' : 'PENDENTE'));

                const r = data.resumo;
                $('#r-dispositivos').text(r.dispositivos_ativos);
                $('#r-usuarios').text(r.usuarios_alcancaveis);
                $('#r-envios').text(r.envios_24h);
                $('#r-sucessos').text(r.sucessos_24h);
            }).fail(function() { exibirToast('Erro de comunicação', 'danger'); });
        }

        $('#formConfig').on('submit', function(e) {
            e.preventDefault();
            const payload = {
                ativo: $('#ativo').is(':checked') ? 1 : 0,
                titulo_padrao: $('#titulo_padrao').val().trim(),
                som_padrao: $('#som_padrao').val().trim() || 'default',
                service_account_json: $('#sa_json').val().trim()
            };
            $('#btnSalvar').prop('disabled', true);
            $.ajax({
                url: baseUrl + '/api/painel/notificacoes_push/salvar.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        exibirToast(res.mensagem, 'success');
                        $('#sa_json').val('');
                        carregar();
                    } else {
                        exibirToast(res.mensagem || 'Erro ao salvar', 'danger');
                    }
                },
                error: function() { exibirToast('Erro de comunicação ao salvar', 'danger'); },
                complete: function() { $('#btnSalvar').prop('disabled', false); }
            });
        });

        $('#btnTeste').on('click', function() {
            const id = parseInt($('#teste_id').val(), 10);
            const email = $('#teste_email').val().trim();
            if (!id && !email) { exibirToast('Informe ID ou e-mail do destinatário', 'warning'); return; }
            const payload = {
                id_usuario: id || 0,
                email: email,
                titulo: $('#teste_titulo').val().trim(),
                corpo: $('#teste_corpo').val().trim(),
                dados: { tipo: 'teste_painel' }
            };
            $('#btnTeste').prop('disabled', true);
            $('#teste-resultado').html('<div class="text-muted">Enviando...</div>');
            $.ajax({
                url: baseUrl + '/api/painel/notificacoes_push/teste.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        const r = res.resultado;
                        const cls = r.falhas > 0 ? 'alert-warning' : (r.enviados > 0 ? 'alert-success' : 'alert-info');
                        let html = '<div class="alert ' + cls + ' mb-0">'
                            + '<strong>' + (res.destinatario ? res.destinatario.nome : '') + '</strong><br>'
                            + 'Dispositivos: ' + r.dispositivos + ' · Enviados: ' + r.enviados + ' · Falhas: ' + r.falhas + '<br>'
                            + '<small>' + res.mensagem + '</small>';
                        if (r.erros && r.erros.length) {
                            html += '<br><small class="text-danger">Erros: ' + r.erros.join('; ') + '</small>';
                        }
                        html += '</div>';
                        $('#teste-resultado').html(html);
                        carregar();
                    } else {
                        $('#teste-resultado').html('<div class="alert alert-danger mb-0">' + (res.mensagem || 'Erro') + '</div>');
                    }
                },
                error: function() { $('#teste-resultado').html('<div class="alert alert-danger mb-0">Erro de comunicação</div>'); },
                complete: function() { $('#btnTeste').prop('disabled', false); }
            });
        });

        carregar();
    </script>
</body>
</html>
