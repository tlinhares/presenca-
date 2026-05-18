<?php
session_start();
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: configuracoes                                          ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('configuracoes');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-page {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .header-page h3 { color: #212529; }
        .header-page small { color: rgba(0,0,0,0.6); }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .card-header {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }
        .btn-custom {
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        .section-title {
            color: #fd7e14;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; }
        
        /* Toggle Switch Styles */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: #0d6efd;
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .toggle-switch input:focus + .toggle-slider {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        
        .toggle-switch input:disabled + .toggle-slider {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0;
        }
        
        .toggle-text {
            font-weight: 500;
            color: #495057;
            margin: 0;
        }
        
        .toggle-status {
            font-size: 0.875rem;
            color: #6c757d;
            margin-left: auto;
        }
        
        .toggle-switch input:checked ~ .toggle-status {
            color: #0d6efd;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="fas fa-cog me-2"></i>Configurações do Sistema</h3>
                    <small>Parâmetros e preferências gerais</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="dashboard.php" class="btn btn-dark btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                    <a href="../logout.php" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cog me-2"></i>
                            Configurações do Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="form-configuracoes">
                            <!-- Seção: Configurações Gerais -->
                            <h5 class="section-title"><i class="fas fa-cog me-2"></i>Configurações Gerais</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hora_limite" class="form-label">Hora Limite para Reservas</label>
                                        <input type="time" class="form-control" id="hora_limite" name="hora_limite">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="toggle-label">
                                            <div class="toggle-switch">
                                                <input type="checkbox" id="permitir_reserva_atraso" name="permitir_reserva_atraso" value="1">
                                                <span class="toggle-slider"></span>
                                            </div>
                                            <div>
                                                <p class="toggle-text">Permitir Reserva Fora do Horário</p>
                                                <span class="toggle-status" id="permitir_reserva_atraso_status">Desabilitado</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="limite_reservas_dia" class="form-label">Limite de Reservas por Dia</label>
                                        <input type="number" class="form-control" id="limite_reservas_dia" name="limite_reservas_dia">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fuso_horario" class="form-label">Fuso Horário</label>
                                        <select class="form-select" id="fuso_horario" name="fuso_horario">
                                            <option value="America/Cuiaba">America/Cuiaba</option>
                                            <option value="America/Sao_Paulo">America/Sao_Paulo</option>
                                            <option value="America/Manaus">America/Manaus</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="mensagem_inicio" class="form-label">Mensagem de Início</label>
                                        <textarea class="form-control" id="mensagem_inicio" name="mensagem_inicio" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção: Valores -->
                            <h5 class="section-title"><i class="fas fa-dollar-sign me-2"></i>Valores</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="valor_refeicao" class="form-label">Valor da Refeição (R$)</label>
                                        <input type="number" step="0.01" class="form-control" id="valor_refeicao" name="valor_refeicao">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="valor_marmitex" class="form-label">Valor do Marmitex (R$)</label>
                                        <input type="number" step="0.01" class="form-control" id="valor_marmitex" name="valor_marmitex">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="valor_fora_horario" class="form-label">Valor Fora do Horário (R$)</label>
                                        <input type="number" step="0.01" class="form-control" id="valor_fora_horario" name="valor_fora_horario">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="toggle-label">
                                            <div class="toggle-switch">
                                                <input type="checkbox" id="marmitex_habilitado" name="marmitex_habilitado" value="1">
                                                <span class="toggle-slider"></span>
                                            </div>
                                            <div>
                                                <p class="toggle-text">Marmitex Habilitado</p>
                                                <span class="toggle-status" id="marmitex_habilitado_status">Desabilitado</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção: Departamentos -->
                            <h5 class="section-title"><i class="fas fa-building me-2"></i>Departamentos</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="valor_departamento" class="form-label">Valor Departamento (R$)</label>
                                        <input type="number" step="0.01" class="form-control" id="valor_departamento" name="valor_departamento">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="valor_departamento_fora_horario" class="form-label">Valor Departamento Fora do Horário (R$)</label>
                                        <input type="number" step="0.01" class="form-control" id="valor_departamento_fora_horario" name="valor_departamento_fora_horario">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="horario_departamento" class="form-label">Horário do Departamento</label>
                                        <input type="time" class="form-control" id="horario_departamento" name="horario_departamento">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="toggle-label">
                                            <div class="toggle-switch">
                                                <input type="checkbox" id="permitir_reserva_departamento_atraso" name="permitir_reserva_departamento_atraso" value="1">
                                                <span class="toggle-slider"></span>
                                            </div>
                                            <div>
                                                <p class="toggle-text">Permitir Reserva Departamento Fora do Horário</p>
                                                <span class="toggle-status" id="permitir_reserva_departamento_atraso_status">Desabilitado</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção: Notificações -->
                            <h5 class="section-title"><i class="fas fa-bell me-2"></i>Notificações</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_notificacoes" class="form-label">Email para Notificações</label>
                                        <input type="email" class="form-control" id="email_notificacoes" name="email_notificacoes">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="toggle-label">
                                            <div class="toggle-switch">
                                                <input type="checkbox" id="notificacao_diaria_habilitada" name="notificacao_diaria_habilitada" value="1">
                                                <span class="toggle-slider"></span>
                                            </div>
                                            <div>
                                                <p class="toggle-text">Notificação Diária Habilitada</p>
                                                <span class="toggle-status" id="notificacao_diaria_habilitada_status">Desabilitado</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="horario_notificacao_diaria" class="form-label">Horário da Notificação Diária</label>
                                        <input type="time" class="form-control" id="horario_notificacao_diaria" name="horario_notificacao_diaria">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="assunto_email_notificacao" class="form-label">Assunto do Email de Notificação</label>
                                        <input type="text" class="form-control" id="assunto_email_notificacao" name="assunto_email_notificacao">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="template_email_notificacao" class="form-label">Template do Email de Notificação</label>
                                        <textarea class="form-control" id="template_email_notificacao" name="template_email_notificacao" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção: Email -->
                            <h5 class="section-title"><i class="fas fa-envelope me-2"></i>Configurações de Email</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_email" class="form-label">SMTP Email</label>
                                        <input type="text" class="form-control" id="smtp_email" name="smtp_email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="imap_email" class="form-label">IMAP Email</label>
                                        <input type="text" class="form-control" id="imap_email" name="imap_email">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="port_email" class="form-label">Porta do Email</label>
                                        <input type="number" class="form-control" id="port_email" name="port_email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="senha_email" class="form-label">Senha do Email</label>
                                        <input type="password" class="form-control" id="senha_email" name="senha_email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="nome_remetente_email" class="form-label">Nome do Remetente (From)</label>
                                        <input type="text" class="form-control" id="nome_remetente_email" name="nome_remetente_email" placeholder="Sistema de Presença AOM">
                                    </div>
                                </div>
                            </div>

                            <!-- Seção: Acesso Especial -->
                            <h5 class="section-title"><i class="fas fa-shield-alt me-2"></i>Acesso Especial</h5>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="emails_acesso_especial" class="form-label">Emails com Acesso Especial (separados por vírgula)</label>
                                        <textarea class="form-control" id="emails_acesso_especial" name="emails_acesso_especial" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-custom">
                                    <i class="fas fa-save me-2"></i>
                                    Salvar Configurações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    <script>

        $(document).ready(function() {
            // Carregar configurações
            carregarConfiguracoes();
            
            // Salvar configurações
            $('#form-configuracoes').submit(function(e) {
                e.preventDefault();
                salvarConfiguracoes();
            });
            
            // Atualizar status dos toggles em tempo real
            $('.toggle-switch input').on('change', function() {
                const toggleId = $(this).attr('id');
                const statusElement = $('#' + toggleId + '_status');
                const isChecked = $(this).is(':checked');
                
                if (isChecked) {
                    statusElement.text('Habilitado').removeClass('text-muted').addClass('text-primary');
                } else {
                    statusElement.text('Desabilitado').removeClass('text-primary').addClass('text-muted');
                }
            });
        });
        
        // Carregar configurações
        function carregarConfiguracoes() {
            $.ajax({
                url: '../api/config/buscar_config.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        const configs = response.data;
                        
                        // Configurações Gerais
                        $('#hora_limite').val(configs.hora_limite || '');
                        $('#permitir_reserva_atraso').prop('checked', configs.permitir_reserva_atraso === '1');
                        $('#limite_reservas_dia').val(configs.limite_reservas_dia || '');
                        $('#fuso_horario').val(configs.fuso_horario || 'America/Cuiaba');
                        $('#mensagem_inicio').val(configs.mensagem_inicio || '');
                        
                        // Valores
                        $('#valor_refeicao').val(configs.valor_refeicao || '');
                        $('#valor_marmitex').val(configs.valor_marmitex || '');
                        $('#valor_fora_horario').val(configs.valor_fora_horario || '');
                        $('#marmitex_habilitado').prop('checked', configs.marmitex_habilitado === '1');
                        
                        // Departamentos
                        $('#valor_departamento').val(configs.valor_departamento || '');
                        $('#valor_departamento_fora_horario').val(configs.valor_departamento_fora_horario || '');
                        $('#horario_departamento').val(configs.horario_departamento || '');
                        $('#permitir_reserva_departamento_atraso').prop('checked', configs.permitir_reserva_departamento_atraso === '1');
                        
                        // Notificações
                        $('#email_notificacoes').val(configs.email_notificacoes || '');
                        $('#notificacao_diaria_habilitada').prop('checked', configs.notificacao_diaria_habilitada === '1');
                        $('#horario_notificacao_diaria').val(configs.horario_notificacao_diaria || '');
                        $('#assunto_email_notificacao').val(configs.assunto_email_notificacao || '');
                        $('#template_email_notificacao').val(configs.template_email_notificacao || '');
                        
                        // Email
                        $('#smtp_email').val(configs.smtp_email || '');
                        $('#imap_email').val(configs.imap_email || '');
                        $('#port_email').val(configs.port_email || '');
                        $('#senha_email').val(configs.senha_email || '');
                        $('#nome_remetente_email').val(configs.nome_remetente_email || 'Sistema de Presença AOM');
                        
                        // Acesso Especial
                        $('#emails_acesso_especial').val(configs.emails_acesso_especial || '');
                        
                        // Atualizar status visual dos toggles
                        $('.toggle-switch input').each(function() {
                            const toggleId = $(this).attr('id');
                            const statusElement = $('#' + toggleId + '_status');
                            const isChecked = $(this).is(':checked');
                            
                            if (isChecked) {
                                statusElement.text('Habilitado').removeClass('text-muted').addClass('text-primary');
                            } else {
                                statusElement.text('Desabilitado').removeClass('text-primary').addClass('text-muted');
                            }
                        });
                    }
                },
                error: function() {
                    console.error('Erro ao carregar configurações');
                }
            });
        }
        
        // Salvar configurações
        function salvarConfiguracoes() {
            const dados = {
                // Configurações Gerais
                hora_limite: $('#hora_limite').val(),
                permitir_reserva_atraso: $('#permitir_reserva_atraso').is(':checked') ? '1' : '0',
                limite_reservas_dia: $('#limite_reservas_dia').val(),
                fuso_horario: $('#fuso_horario').val(),
                mensagem_inicio: $('#mensagem_inicio').val(),
                
                // Valores
                valor_refeicao: $('#valor_refeicao').val(),
                valor_marmitex: $('#valor_marmitex').val(),
                valor_fora_horario: $('#valor_fora_horario').val(),
                marmitex_habilitado: $('#marmitex_habilitado').is(':checked') ? '1' : '0',
                
                // Departamentos
                valor_departamento: $('#valor_departamento').val(),
                valor_departamento_fora_horario: $('#valor_departamento_fora_horario').val(),
                horario_departamento: $('#horario_departamento').val(),
                permitir_reserva_departamento_atraso: $('#permitir_reserva_departamento_atraso').is(':checked') ? '1' : '0',
                
                // Notificações
                email_notificacoes: $('#email_notificacoes').val(),
                notificacao_diaria_habilitada: $('#notificacao_diaria_habilitada').is(':checked') ? '1' : '0',
                horario_notificacao_diaria: $('#horario_notificacao_diaria').val(),
                assunto_email_notificacao: $('#assunto_email_notificacao').val(),
                template_email_notificacao: $('#template_email_notificacao').val(),
                
                // Email
                smtp_email: $('#smtp_email').val(),
                imap_email: $('#imap_email').val(),
                port_email: $('#port_email').val(),
                senha_email: $('#senha_email').val(),
                nome_remetente_email: $('#nome_remetente_email').val(),
                
                // Acesso Especial
                emails_acesso_especial: $('#emails_acesso_especial').val()
            };
            
            $.ajax({
                url: '../api/config/atualizar.php',
                method: 'POST',
                data: dados,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast('Configurações salvas com sucesso!', 'success');
                    } else {
                        exibirToast('Erro: ' + response.mensagem, 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao salvar configurações', 'error');
                }
            });
        }
    </script>
</body>
</html>