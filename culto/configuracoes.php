<?php
session_start();
require_once '../auth/verifica_sessao.php';
require_once '../auth/verifica_permissao.php';
require_once '../api/conexao.php';
require_once '../config/timezone.php';
require_once '../config/dominio.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_configuracoes (acesso_padrao=0, requer_culto=1)  ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_configuracoes');

// A conexão global já está disponível via require_once '../api/conexao.php'

// Verificar se as variáveis de configuração estão definidas
if (!isset($URL_API_LEITURA_FACIAL)) {
    $URL_API_LEITURA_FACIAL = 'http://localhost/presenca/api/monitor/stream_events_facial.php';
}

// Remover processamento PHP - agora será feito via AJAX

// Buscar configurações atuais
$configuracoes = [];

// A conexão global $conn já está disponível via require_once '../api/conexao.php'

$stmt = $conn->prepare("SELECT chave, valor FROM configuracoes_culto");
if (!$stmt) {
    error_log("Erro ao preparar consulta de configurações: " . $conn->error);
    die("Erro ao preparar consulta.");
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $configuracoes[$row['chave']] = $row['valor'];
}

// Valores padrão para os campos reais da tabela configuracoes_culto
$dias_semana = $configuracoes['dias_semana'] ?? '1,2,3,4,5';
$gerar_faltas_automaticas = $configuracoes['gerar_faltas_automaticas'] ?? '1';
$horario_fim = $configuracoes['horario_fim'] ?? '08:00:00';
$horario_inicio = $configuracoes['horario_inicio'] ?? '07:30:00';
$notificacao_ausencia = $configuracoes['notificacao_ausencia'] ?? '1';
$notificar_presencas = $configuracoes['notificar_presencas'] ?? '1';
$permitir_atraso = $configuracoes['permitir_atraso'] ?? '1';
$tolerancia_atraso = $configuracoes['tolerancia_atraso'] ?? '10';

// Garantir formato correto para inputs type="time" (HH:MM:SS)
$campos_horario = ['horario_inicio', 'horario_fim'];
foreach ($campos_horario as $campo) {
    if (strlen($$campo) == 5) {
        $$campo .= ':00';
    }
}

// Remover consulta de dispositivos - não é mais necessária
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Culto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/feedback-system.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../culto/dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <a href="../culto/dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-gear-fill me-2 text-success"></i>Configurações do Culto
                </h2>
            </div>
        </div>

        <!-- Mensagens serão exibidas via JavaScript -->

        <!-- Formulário Principal - Apenas campos reais da tabela configuracoes_culto -->
        <form id="formConfiguracoes" method="post">
            <div class="row">
                <!-- Seção 1: Configurações de Horário -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clock me-2"></i>Configurações de Horário
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-3 col-md-6">
                                    <div class="mb-3">
                                        <label for="horario_inicio" class="form-label">Horário de Início</label>
                                        <input type="time" class="form-control" id="horario_inicio" name="horario_inicio" 
                                               value="<?= $horario_inicio ?>" required>
                                        <small class="text-muted">Horário que o sistema começa a aceitar presenças</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="mb-3">
                                        <label for="horario_fim" class="form-label">Horário de Fim</label>
                                        <input type="time" class="form-control" id="horario_fim" name="horario_fim" 
                                               value="<?= $horario_fim ?>" required>
                                        <small class="text-muted">Horário limite para confirmação de presença</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="mb-3">
                                        <label for="tolerancia_atraso" class="form-label">Tolerância (min)</label>
                                        <input type="number" class="form-control" id="tolerancia_atraso" name="tolerancia_atraso" 
                                               value="<?= $tolerancia_atraso ?>" min="0" max="60" required>
                                        <small class="text-muted">Tolerância em minutos para atraso</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="mb-3">
                                        <label for="dias_semana" class="form-label">Dias da Semana</label>
                                        <input type="text" class="form-control" id="dias_semana" name="dias_semana" 
                                               value="<?= $dias_semana ?>" placeholder="1,2,3,4,5" required>
                                        <small class="text-muted">Dias da semana que há culto (1=segunda, 7=domingo)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção 2: Configurações de Notificação -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-bell me-2"></i>Configurações de Notificação
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="notificar_presencas" 
                                               name="notificar_presencas" <?= $notificar_presencas ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="notificar_presencas">
                                            <strong>Notificar Presenças</strong>
                                            <small class="text-muted d-block">Enviar notificações de presença</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="notificacao_ausencia" 
                                               name="notificacao_ausencia" <?= $notificacao_ausencia ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="notificacao_ausencia">
                                            <strong>Notificar Ausências</strong>
                                            <small class="text-muted d-block">Enviar notificação para ausências</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção 3: Configurações de Comportamento -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-sliders me-2"></i>Configurações de Comportamento
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="gerar_faltas_automaticas" 
                                               name="gerar_faltas_automaticas" <?= $gerar_faltas_automaticas ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="gerar_faltas_automaticas">
                                            <strong>Gerar Faltas Automáticas</strong>
                                            <small class="text-muted d-block">Gerar faltas automáticas para ausentes</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="permitir_atraso" 
                                               name="permitir_atraso" <?= $permitir_atraso ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="permitir_atraso">
                                            <strong>Permitir Atraso</strong>
                                            <small class="text-muted d-block">Permitir confirmação após horário do culto</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botão de Salvar -->
                <div class="col-12">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-save me-2"></i>Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    
    <script>
        $(document).ready(function() {
            // Interceptar envio do formulário
            $('#formConfiguracoes').on('submit', function(e) {
                e.preventDefault();
                
                // Desabilitar botão para evitar duplo envio
                const btnSalvar = $(this).find('button[type="submit"]');
                const textoOriginal = btnSalvar.html();
                
                btnSalvar.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Salvando...');
                
                // Coletar dados do formulário - apenas campos reais da tabela configuracoes_culto
                const formData = {
                    dias_semana: $('#dias_semana').val(),
                    gerar_faltas_automaticas: $('#gerar_faltas_automaticas').is(':checked') ? '1' : '0',
                    horario_fim: $('#horario_fim').val(),
                    horario_inicio: $('#horario_inicio').val(),
                    notificacao_ausencia: $('#notificacao_ausencia').is(':checked') ? '1' : '0',
                    notificar_presencas: $('#notificar_presencas').is(':checked') ? '1' : '0',
                    permitir_atraso: $('#permitir_atraso').is(':checked') ? '1' : '0',
                    tolerancia_atraso: $('#tolerancia_atraso').val()
                };
                
                // Enviar via AJAX
                $.ajax({
                    url: '../api/culto/salvar-configuracoes.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: true,
                    contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
                    success: function(response) {
                        if (response.status === 'sucesso') {
                            exibirToast(response.mensagem, 'success');
                        } else {
                            exibirToast(response.mensagem, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', error);
                        exibirToast('Erro ao salvar configurações. Tente novamente.', 'danger');
                    },
                    complete: function() {
                        // Reabilitar botão
                        btnSalvar.prop('disabled', false).html(textoOriginal);
                    }
                });
            });
        });
    </script>
</body>
</html>
