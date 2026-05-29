<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_alertas');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Alertas de Estoque - Configuração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(250, 112, 154, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .resumo-card { border-radius: 14px; padding: 1.1rem 1.25rem; color: #fff; }
        .resumo-card .n { font-size: 2rem; font-weight: 700; line-height: 1; }
        .resumo-card .l { font-size: .8rem; opacity: .9; }
        .resumo-alerta { background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%); }
        .resumo-fallback { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); }
        .form-label { font-weight: 600; color: #2d3748; }
        .help { font-size: .82rem; color: #718096; }
        .form-switch .form-check-input { width: 3rem; height: 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Alertas de Estoque</h5>
                        <small class="opacity-75">Avisos automáticos por WhatsApp</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4" style="max-width: 820px;">
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="resumo-card resumo-alerta">
                    <div class="n" id="resumo-alerta">–</div>
                    <div class="l">produto(s) em alerta agora</div>
                </div>
            </div>
            <div class="col-6">
                <div class="resumo-card resumo-fallback">
                    <div class="n" id="resumo-fallback">–</div>
                    <div class="l">departamento(s) sem responsável (vão p/ fallback)</div>
                </div>
            </div>
        </div>

        <div class="card-main p-4">
            <form id="formConfig">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="ativo">
                    <label class="form-check-label form-label ms-2" for="ativo">
                        Enviar avisos de estoque baixo por WhatsApp
                    </label>
                    <div class="help ms-1">Quando desligado, os alertas continuam aparecendo no sino do painel, mas nenhuma mensagem é enviada.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="intervalo_horas">Intervalo de repetição (horas)</label>
                    <input type="number" class="form-control" id="intervalo_horas" min="1" max="8760" step="1" style="max-width: 200px;">
                    <div class="help mt-1">O mesmo produto só é avisado novamente depois desse tempo. Ex.: <strong>24</strong> = no máximo um aviso por dia por produto.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="telefone_fallback">Telefone de fallback</label>
                    <input type="text" class="form-control" id="telefone_fallback" placeholder="(65) 99999-9999" style="max-width: 280px;">
                    <div class="help mt-1">Recebe os alertas de departamentos que não têm responsável cadastrado. Deixe em branco para apenas registrar (sem WhatsApp) nesses casos.</div>
                </div>

                <div class="d-flex align-items-center gap-3 pt-2 border-top">
                    <button type="submit" class="btn btn-primary px-4" id="btnSalvar" style="background: #ed8936; border: none;">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                    <small class="help" id="atualizado_em"></small>
                </div>
            </form>
        </div>

        <div class="help mt-3 px-1">
            <i class="bi bi-info-circle me-1"></i>
            A verificação roda automaticamente de hora em hora. Para que um departamento avise direto o gestor,
            cadastre-o em <a href="responsaveis.php">Responsáveis</a>.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';

        function carregar() {
            $.getJSON(baseUrl + '/api/estoque/config_alertas/obter.php', function(data) {
                if (data.status === 'ok') {
                    $('#ativo').prop('checked', data.config.ativo == 1);
                    $('#intervalo_horas').val(data.config.intervalo_horas);
                    $('#telefone_fallback').val(data.config.telefone_fallback || '');
                    $('#resumo-alerta').text(data.resumo.produtos_em_alerta);
                    $('#resumo-fallback').text(data.resumo.departamentos_sem_responsavel);
                    if (data.config.atualizado_em) {
                        $('#atualizado_em').text('Última alteração: ' + data.config.atualizado_em);
                    }
                } else {
                    exibirToast(data.mensagem || 'Erro ao carregar configuração', 'danger');
                }
            }).fail(function() {
                exibirToast('Erro de comunicação ao carregar', 'danger');
            });
        }

        $('#formConfig').on('submit', function(e) {
            e.preventDefault();
            const payload = {
                ativo: $('#ativo').is(':checked') ? 1 : 0,
                intervalo_horas: parseInt($('#intervalo_horas').val(), 10) || 24,
                telefone_fallback: $('#telefone_fallback').val().trim()
            };
            $('#btnSalvar').prop('disabled', true);
            $.ajax({
                url: baseUrl + '/api/estoque/config_alertas/salvar.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        exibirToast(res.mensagem, 'success');
                        $('#telefone_fallback').val(res.config.telefone_fallback || '');
                        carregar();
                    } else {
                        exibirToast(res.mensagem || 'Erro ao salvar', 'danger');
                    }
                },
                error: function() { exibirToast('Erro de comunicação ao salvar', 'danger'); },
                complete: function() { $('#btnSalvar').prop('disabled', false); }
            });
        });

        carregar();
    </script>
</body>
</html>
