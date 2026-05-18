<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: automacao_relatorios                                   ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('automacao_relatorios');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automação de Relatórios - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-custom {
            border-radius: 5px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        .navbar-custom {
            background-color: #6c757d;
            border-bottom: 1px solid #6c757d;
        }
        .section-title {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
        }
        .btn-dark {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-dark:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(108, 117, 125, 0.1);
        }
        .badge-dark {
            background-color: #6c757d;
        }
        .alert-dark {
            background-color: #d1d3d4;
            border-color: #c6c8ca;
            color: #383d41;
        }
        .status-active {
            color: #28a745;
        }
        .status-inactive {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-robot me-2"></i>
                Automação de Relatórios
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-arrow-left me-1"></i>
                    Voltar ao Painel
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Cabeçalho -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-robot me-2"></i>
                            Sistema de Automação de Relatórios
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">
                            Configure automações para envio automático de relatórios via WhatsApp.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Nova Automação -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            Nova Automação
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="formAutomacao">
                            <input type="hidden" id="automacao_id" name="id" value="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nome_automacao" class="form-label">Nome da Automação *</label>
                                    <input type="text" class="form-control" id="nome_automacao" name="nome_automacao" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tipo_relatorio" class="form-label">Tipo de Relatório *</label>
                                    <select class="form-select" id="tipo_relatorio" name="tipo_relatorio" required>
                                        <option value="">Selecione o tipo</option>
                                        <option value="diario">Relatório Diário</option>
                                        <option value="diario_completo">Relatório Diário Completo</option>
                                        <option value="csv">Relatório CSV Completo</option>
                                        <option value="csv_diario">Relatório CSV Diário</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="numero_whatsapp" class="form-label">Número WhatsApp *</label>
                                    <input type="text" class="form-control" id="numero_whatsapp" name="numero_whatsapp" 
                                           placeholder="5565999793296" required>
                                    <div class="form-text">Formato: 5565999793296 (com código do país)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="horario_envio" class="form-label">Horário de Envio *</label>
                                    <input type="time" class="form-control" id="horario_envio" name="horario_envio" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="dias_semana" class="form-label">Dias da Semana *</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="segunda" name="dias_semana[]" value="1">
                                        <label class="form-check-label" for="segunda">Segunda</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terca" name="dias_semana[]" value="2">
                                        <label class="form-check-label" for="terca">Terça</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="quarta" name="dias_semana[]" value="3">
                                        <label class="form-check-label" for="quarta">Quarta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="quinta" name="dias_semana[]" value="4">
                                        <label class="form-check-label" for="quinta">Quinta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sexta" name="dias_semana[]" value="5">
                                        <label class="form-check-label" for="sexta">Sexta</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mensagem_personalizada" class="form-label">Mensagem Personalizada</label>
                                    <textarea class="form-control" id="mensagem_personalizada" name="mensagem_personalizada" 
                                              rows="3" placeholder="Mensagem que será enviada junto com o relatório..."></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-dark btn-custom">
                                        <i class="bi bi-save me-2"></i>
                                        Salvar Automação
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Automações -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list me-2"></i>
                            Automações Configuradas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabelaAutomacoes">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>WhatsApp</th>
                                        <th>Horário</th>
                                        <th>Dias</th>
                                        <th>Status</th>
                                        <th>Último Envio</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dados carregados via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    <script>
    // Carregar automações
    function carregarAutomacoes() {
        fetch('../api/automacao/listar.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('#tabelaAutomacoes tbody');
                tbody.innerHTML = '';
                
                if (data.status === 'sucesso' && data.dados) {
                    data.dados.forEach(automacao => {
                        const row = document.createElement('tr');
                        const statusClass = automacao.ativo == 1 ? 'status-active' : 'status-inactive';
                        const statusText = automacao.ativo == 1 ? 'Ativo' : 'Inativo';
                        const diasTexto = JSON.parse(automacao.dias_semana).map(dia => 
                            ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][dia]
                        ).join(', ');
                        
                        row.innerHTML = `
                            <td>${automacao.id}</td>
                            <td><strong>${automacao.nome}</strong></td>
                            <td><span class="badge bg-primary">${automacao.tipo_relatorio}</span></td>
                            <td>${automacao.numero_whatsapp}</td>
                            <td>${automacao.horario_envio}</td>
                            <td>${diasTexto}</td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td>${automacao.ultimo_envio || 'Nunca'}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editarAutomacao(${automacao.id})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="excluirAutomacao(${automacao.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="testarAutomacao(${automacao.id})">
                                    <i class="bi bi-play"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Nenhuma automação encontrada</td></tr>';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar automações:', error);
                exibirToast('Erro ao carregar automações', 'error');
            });
    }

    // Salvar automação
    document.getElementById('formAutomacao').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const isEdit = $('#automacao_id').val() !== '';
        
        // Alterar título do botão baseado na operação
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = isEdit ? 'Atualizando...' : 'Salvando...';
        submitBtn.disabled = true;
        
        fetch('../api/automacao/salvar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                exibirToast(data.mensagem, 'success');
                this.reset();
                $('#automacao_id').val(''); // Limpar ID de edição
                $('#modalAutomacaoLabel').text('Nova Automação'); // Resetar título
                $('#modalAutomacao').modal('hide');
                carregarAutomacoes();
            } else {
                exibirToast(data.mensagem, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar automação:', error);
            exibirToast('Erro ao salvar automação', 'error');
        })
        .finally(() => {
            // Resetar botão
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

    // Função para exibir toast
    function exibirToast(mensagem, tipo = 'info') {
        if (window.feedbackSystem && window.feedbackSystem.show) {
            window.feedbackSystem.show(mensagem, tipo);
        } else {
            alert(mensagem);
        }
    }

    // Função para editar automação
    function editarAutomacao(id) {
        // Buscar dados da automação
        fetch('../api/automacao/buscar.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                const automacao = data.data;
                
                // Preencher o formulário
                $('#automacao_id').val(automacao.id);
                $('#nome_automacao').val(automacao.nome);
                $('#tipo_relatorio').val(automacao.tipo_relatorio);
                $('#numero_whatsapp').val(automacao.numero_whatsapp);
                $('#horario_envio').val(automacao.horario_envio);
                $('#mensagem_personalizada').val(automacao.mensagem_personalizada);
                
                // Preencher dias da semana
                $('input[name="dias_semana[]"]').prop('checked', false);
                if (automacao.dias_semana) {
                    automacao.dias_semana.forEach(dia => {
                        $('input[name="dias_semana[]"][value="' + dia + '"]').prop('checked', true);
                    });
                }
                
                // Alterar título do modal
                $('#modalAutomacaoLabel').text('Editar Automação');
                
                // Mostrar modal
                $('#modalAutomacao').modal('show');
                
            } else {
                exibirToast('Erro ao carregar dados da automação', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            exibirToast('Erro ao carregar dados da automação', 'error');
        });
    }

    // Função para excluir automação
    function excluirAutomacao(id) {
        if (confirm('Tem certeza que deseja excluir esta automação?')) {
            fetch('../api/automacao/excluir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    exibirToast(data.mensagem, 'success');
                    carregarAutomacoes();
                } else {
                    exibirToast(data.mensagem, 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao excluir automação:', error);
                exibirToast('Erro ao excluir automação', 'error');
            });
        }
    }

    // Função para testar automação
    function testarAutomacao(id) {
        if (confirm('Deseja testar o envio desta automação agora?')) {
            fetch('../api/automacao/testar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    exibirToast(data.mensagem, 'success');
                } else {
                    exibirToast(data.mensagem, 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao testar automação:', error);
                exibirToast('Erro ao testar automação', 'error');
            });
        }
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        carregarAutomacoes();
    });
    </script>
    
    <!-- Sistema de Feedback -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
        <div id="toastContainer"></div>
    </div>
</body>
</html>
