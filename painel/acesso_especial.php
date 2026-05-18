<?php
session_start();
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: acesso_especial (acesso_padrao=0)                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('acesso_especial');

// Sistema antigo mantido para compatibilidade
if (!pode_acessar_especial()) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Especial - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            background-color: #dc3545;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-custom {
            border-radius: 5px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        .navbar-custom {
            background-color: #dc3545;
            border-bottom: 1px solid #dc3545;
        }
        .section-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.1);
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>
                Acesso Especial
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>
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
                            <i class="fas fa-shield-alt me-2"></i>
                            Administração Avançada de Refeições
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">
                            Sistema de acesso especial para criação e gerenciamento de reservas administrativas.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Criação de Reserva -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Criar Reserva Especial
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="formReservaEspecial">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="usuario_id" class="form-label">Usuário *</label>
                                    <select class="form-select" id="usuario_id" name="usuario_id" required>
                                        <option value="">Selecione um usuário</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="data" class="form-label">Data *</label>
                                    <input type="date" class="form-control" id="data" name="data" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tipo" class="form-label">Tipo de Reserva *</label>
                                    <select class="form-select" id="tipo" name="tipo" required>
                                        <option value="">Selecione o tipo</option>
                                        <option value="propria">Própria</option>
                                        <option value="adicional">Adicional</option>
                                        <option value="marmitex">Marmitex</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="dependente_id" class="form-label">Dependente</label>
                                    <select class="form-select" id="dependente_id" name="dependente_id" disabled>
                                        <option value="">Selecione o tipo "Adicional" e um usuário primeiro</option>
                                    </select>
                                    <div class="form-text">Selecione o tipo "Adicional" e um usuário para habilitar este campo</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="quantidade" class="form-label">Quantidade</label>
                                    <input type="number" class="form-control" id="quantidade" name="quantidade" value="1" min="1">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="valor_especial" class="form-label">Valor Especial (R$)</label>
                                    <input type="number" class="form-control" id="valor_especial" name="valor_especial" step="0.01" min="0">
                                    <div class="form-text">Deixe em branco para usar valor padrão</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="observacao" class="form-label">Observação *</label>
                                    <input type="text" class="form-control" id="observacao" name="observacao" placeholder="Observações especiais" required>
                                    <div class="form-text">Campo obrigatório para reservas especiais</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-danger btn-custom">
                                        <i class="fas fa-save me-2"></i>
                                        Criar Reserva Especial
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Reservas Especiais -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Reservas Especiais Recentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabelaReservas">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Dependente</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Quantidade</th>
                                        <th>Valor</th>
                                        <th>Observação</th>
                                        <th>Criado em</th>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Processar mensagens do servidor e converter para toast
    document.addEventListener('DOMContentLoaded', function() {
        const mensagemDiv = document.querySelector('.alert');
        if (mensagemDiv) {
            const mensagem = mensagemDiv.textContent.trim();
            const tipo = mensagemDiv.classList.contains('alert-success') ? 'success' : 
                        mensagemDiv.classList.contains('alert-danger') ? 'error' : 
                        mensagemDiv.classList.contains('alert-warning') ? 'warning' : 'info';
            
            // Exibir toast
            if (typeof exibirToast !== 'undefined') {
                exibirToast(mensagem, tipo);
            }
            
            // Remover a div de mensagem imediatamente
            mensagemDiv.remove();
        }
    });

    // Carregar usuários
    function carregarUsuarios() {
        fetch('../api/usuarios/listar.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('usuario_id');
                select.innerHTML = '<option value="">Selecione um usuário</option>';
                
                if (data.status === 'sucesso' && data.dados) {
                    data.dados.forEach(usuario => {
                        const option = document.createElement('option');
                        option.value = usuario.id;
                        option.textContent = `${usuario.nome} (${usuario.email})`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao carregar usuários:', error);
                exibirToast('Erro ao carregar usuários', 'error');
            });
    }

    // Carregar dependentes
    function carregarDependentes(usuarioId) {
        const tipo = document.getElementById('tipo').value;
        const selectDependente = document.getElementById('dependente_id');
        const labelDependente = document.querySelector('label[for="dependente_id"]');
        
        // Se não for tipo adicional ou não tiver usuário selecionado, limpar e desabilitar
        if (!usuarioId || tipo !== 'adicional') {
            selectDependente.innerHTML = '<option value="">Selecione um dependente</option>';
            selectDependente.disabled = true;
            if (labelDependente) {
                labelDependente.classList.add('text-muted');
            }
            return;
        }
        
        // Habilitar campo de dependente
        selectDependente.disabled = false;
        if (labelDependente) {
            labelDependente.classList.remove('text-muted');
        }
        
        // Mostrar loading
        selectDependente.innerHTML = '<option value="">Carregando dependentes...</option>';

        fetch(`../api/dependentes/listar.php?usuario_id=${usuarioId}`)
            .then(response => response.json())
            .then(data => {
                selectDependente.innerHTML = '<option value="">Selecione um dependente</option>';
                
                // Verificar ambos os formatos possíveis de resposta
                const dependentes = (data.status === 'sucesso' || data.status === 'ok') 
                    ? (data.dados || data.data || []) 
                    : [];
                
                if (dependentes.length > 0) {
                    dependentes.forEach(dependente => {
                        const option = document.createElement('option');
                        option.value = dependente.id;
                        
                        // Calcular idade usando nascimento ou data_nascimento
                        const dataNascimento = dependente.nascimento || dependente.data_nascimento;
                        let idadeAjustada = dependente.idade || 0;
                        
                        if (dataNascimento) {
                            const nascimento = new Date(dataNascimento);
                            const hoje = new Date();
                            
                            if (!isNaN(nascimento.getTime())) {
                                let idade = hoje.getFullYear() - nascimento.getFullYear();
                                const mesAtual = hoje.getMonth();
                                const mesNascimento = nascimento.getMonth();
                                
                                if (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < nascimento.getDate())) {
                                    idade--;
                                }
                                
                                idadeAjustada = idade;
                            }
                        }
                        
                        // Determinar status de cobrança
                        const cobrar = dependente.cobrar !== undefined ? dependente.cobrar : (idadeAjustada <= 12 ? 1 : 0);
                        const statusCobranca = cobrar === 1 ? "GRATUITO" : "PAGO";
                        
                        option.textContent = `${dependente.nome} (${dependente.parentesco}) - ${idadeAjustada} anos - ${statusCobranca}`;
                        selectDependente.appendChild(option);
                    });
                } else {
                    selectDependente.innerHTML = '<option value="">Nenhum dependente encontrado</option>';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar dependentes:', error);
                selectDependente.innerHTML = '<option value="">Erro ao carregar dependentes</option>';
                exibirToast('Erro ao carregar dependentes do usuário', 'error');
            });
    }
    
    // Função auxiliar para verificar e atualizar dependentes
    function atualizarDependentes() {
        const usuarioId = document.getElementById('usuario_id').value;
        const tipo = document.getElementById('tipo').value;
        
        if (tipo === 'adicional' && usuarioId) {
            carregarDependentes(usuarioId);
        } else {
            carregarDependentes(null); // Limpar campo
        }
    }

    // Carregar reservas especiais
    function carregarReservasEspeciais() {
        fetch('../api/acesso_especial/listar_reservas.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('#tabelaReservas tbody');
                tbody.innerHTML = '';
                
                if (data.status === 'sucesso' && data.dados) {
                    data.dados.forEach(reserva => {
                        const row = document.createElement('tr');
                        const dependenteNome = reserva.dependente_nome || '-';
                        const tipoBadge = reserva.tipo === 'propria' ? 'bg-primary' : 
                                        reserva.tipo === 'adicional' ? 'bg-success' : 'bg-warning';
                        
                        row.innerHTML = `
                            <td>${reserva.id}</td>
                            <td>${reserva.usuario_nome}</td>
                            <td>${dependenteNome}</td>
                            <td>${reserva.data}</td>
                            <td><span class="badge ${tipoBadge}">${reserva.tipo}</span></td>
                            <td>${reserva.quantidade}</td>
                            <td>R$ ${parseFloat(reserva.valor).toFixed(2)}</td>
                            <td>${reserva.observacao || '-'}</td>
                            <td>${reserva.criado_em}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="excluirReservaEspecial(${reserva.id}, '${reserva.tipo}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Nenhuma reserva especial encontrada</td></tr>';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar reservas:', error);
                exibirToast('Erro ao carregar reservas especiais', 'error');
            });
    }

    // Event listeners
    document.getElementById('usuario_id').addEventListener('change', function() {
        atualizarDependentes();
    });

    document.getElementById('tipo').addEventListener('change', function() {
        atualizarDependentes();
    });

    document.getElementById('formReservaEspecial').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validação do campo observação
        const observacao = document.getElementById('observacao').value.trim();
        if (!observacao) {
            exibirToast('O campo Observação é obrigatório para reservas especiais', 'error');
            document.getElementById('observacao').focus();
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../api/acesso_especial/criar_reserva.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                exibirToast(data.mensagem, 'success');
                this.reset();
                carregarReservasEspeciais();
            } else {
                exibirToast(data.mensagem, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao criar reserva:', error);
            exibirToast('Erro ao criar reserva especial', 'error');
        });
    });

    // Função para excluir reserva especial
    function excluirReservaEspecial(reservaId, tipo) {
        mostrarConfirmacao(
            'Tem certeza que deseja excluir esta reserva especial?',
            () => {
                excluirReservaConfirmada(reservaId, tipo);
            }
        );
    }
    
    // Função para confirmar exclusão
    function excluirReservaConfirmada(reservaId, tipo) {
        const formData = new FormData();
        formData.append('reserva_id', reservaId);
        formData.append('tipo', tipo);

        fetch('../api/acesso_especial/excluir_reserva.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                exibirToast(data.mensagem, 'success');
                carregarReservasEspeciais();
            } else {
                exibirToast(data.mensagem, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao excluir reserva:', error);
            exibirToast('Erro ao excluir reserva especial', 'error');
        });
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        carregarUsuarios();
        carregarReservasEspeciais();
        
        // Definir data atual
        const hoje = new Date().toISOString().split('T')[0];
        document.getElementById('data').value = hoje;
        
        // Garantir que o campo dependente está desabilitado inicialmente
        const dependenteSelect = document.getElementById('dependente_id');
        if (dependenteSelect) {
            dependenteSelect.disabled = true;
            const labelDependente = document.querySelector('label[for="dependente_id"]');
            if (labelDependente) {
                labelDependente.classList.add('text-muted');
            }
        }
    });
    
    // Função para exibir toast
    function exibirToast(mensagem, tipo = 'info') {
        if (window.feedbackSystem && window.feedbackSystem.show) {
            window.feedbackSystem.show(mensagem, tipo);
        } else {
            // Fallback para alert simples
            alert(mensagem);
        }
    }
    
    // Função para mostrar confirmação personalizada
    function mostrarConfirmacao(mensagem, callback) {
        const modal = new bootstrap.Modal(document.getElementById('modalConfirmacao'));
        const texto = document.getElementById('modalConfirmacaoTexto');
        const btnConfirmar = document.getElementById('btnConfirmarAcao');
        
        texto.textContent = mensagem;
        
        // Remover listeners anteriores
        btnConfirmar.replaceWith(btnConfirmar.cloneNode(true));
        const novoBtnConfirmar = document.getElementById('btnConfirmarAcao');
        
        novoBtnConfirmar.addEventListener('click', () => {
            modal.hide();
            if (callback) callback();
        });
        
        modal.show();
    }
    </script>
    
    <!-- Sistema de Feedback -->
    <script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
    
    <!-- Modal de Confirmação Personalizado -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalConfirmacaoLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Ação
                    </h5>
                    <button type="button" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-question-circle text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0" id="modalConfirmacaoTexto">
                                Tem certeza que deseja realizar esta ação?
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times-circle me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarAcao">
                        <i class="fas fa-check-circle me-1"></i>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
