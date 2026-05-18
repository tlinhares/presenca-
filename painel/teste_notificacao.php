<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: teste_notificacao                                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('teste_notificacao');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Notificação - Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1055;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3" style="min-height: 100vh;">
                <h5 class="mb-4">Presença</h5>
                <nav class="nav flex-column">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="bi bi-house-door me-2"></i>Dashboard
                    </a>
                    <a class="nav-link text-white" href="usuarios.php">
                        <i class="bi bi-people me-2"></i>Usuários
                    </a>
                    <a class="nav-link text-white" href="monitor_culto.php">
                        <i class="bi bi-eye me-2"></i>Monitor Culto
                    </a>
                    <a class="nav-link text-white" href="monitor_publico.php">
                        <i class="bi bi-eye me-2"></i>Monitor Público
                    </a>
                    <a class="nav-link text-white" href="logs.php">
                        <i class="bi bi-file-text me-2"></i>Logs
                    </a>
                    <a class="nav-link text-white active" href="teste_notificacao.php">
                        <i class="bi bi-bell me-2"></i>Teste Notificação
                    </a>
                    <a class="nav-link text-white" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-bell me-2"></i>Teste de Notificação de Reserva</h2>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtros de Busca</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Buscar usuário:</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Digite nome ou email...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status da reserva:</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">Todos</option>
                                    <option value="sem_reserva">Sem reserva</option>
                                    <option value="com_reserva">Com reserva</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de contato:</label>
                                <select class="form-select" id="contactFilter">
                                    <option value="">Todos</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Usuários -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Usuários</h5>
                        <button class="btn btn-primary" onclick="carregarUsuarios()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="usuariosContainer">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            carregarUsuarios();
            
            // Filtros em tempo real
            $('#searchInput, #statusFilter, #contactFilter').on('input change', function() {
                carregarUsuarios();
            });
        });

        function carregarUsuarios() {
            const busca = $('#searchInput').val();
            const status = $('#statusFilter').val();
            const contato = $('#contactFilter').val();
            
            $.ajax({
                url: '../api/notificacao/buscar_usuarios.php',
                method: 'GET',
                data: {
                    busca: busca,
                    status: status,
                    contato: contato
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'sucesso') {
                        exibirUsuarios(response.usuarios);
                    } else {
                        exibirToast('Erro ao carregar usuários: ' + response.mensagem, 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar usuários', 'error');
                }
            });
        }

        function exibirUsuarios(usuarios) {
            let html = '';
            
            if (usuarios.length === 0) {
                html = '<div class="text-center text-muted"><p>Nenhum usuário encontrado</p></div>';
            } else {
                usuarios.forEach(usuario => {
                    const temReserva = usuario.tem_reserva;
                    const temWhatsApp = usuario.telefone && usuario.telefone.trim() !== '';
                    const temEmail = usuario.email && usuario.email.trim() !== '';
                    
                    let statusBadge = '';
                    if (temReserva) {
                        statusBadge = '<span class="badge bg-success">Com reserva</span>';
                    } else {
                        statusBadge = '<span class="badge bg-warning">Sem reserva</span>';
                    }
                    
                    let contatoInfo = '';
                    if (temWhatsApp) {
                        contatoInfo += '<i class="bi bi-whatsapp text-success me-1" title="WhatsApp"></i>';
                    }
                    if (temEmail) {
                        contatoInfo += '<i class="bi bi-envelope text-primary me-1" title="Email"></i>';
                    }
                    if (!temWhatsApp && !temEmail) {
                        contatoInfo = '<span class="text-muted">Sem contato</span>';
                    }
                    
                    html += `
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="mb-1">${usuario.nome}</h6>
                                        <small class="text-muted">ID: ${usuario.id}</small>
                                    </div>
                                    <div class="col-md-2">
                                        ${statusBadge}
                                    </div>
                                    <div class="col-md-3">
                                        ${contatoInfo}
                                    </div>
                                    <div class="col-md-3 text-end">
                                        ${!temReserva ? `
                                            <button class="btn btn-sm btn-outline-primary" onclick="testarNotificacao(${usuario.id}, '${usuario.nome}')">
                                                <i class="bi bi-bell me-1"></i>Testar
                                            </button>
                                        ` : `
                                            <span class="text-muted">Já tem reserva</span>
                                        `}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            $('#usuariosContainer').html(html);
        }

        function testarNotificacao(usuarioId, nomeUsuario) {
            if (confirm(`Deseja enviar notificação de teste para ${nomeUsuario}?`)) {
                $.ajax({
                    url: '../api/notificacao/testar.php',
                    method: 'POST',
                    data: { usuario_id: usuarioId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'sucesso') {
                            exibirToast(`Notificação enviada para ${nomeUsuario}`, 'success');
                        } else {
                            exibirToast('Erro: ' + response.mensagem, 'error');
                        }
                    },
                    error: function() {
                        exibirToast('Erro ao enviar notificação', 'error');
                    }
                });
            }
        }

        function exibirToast(mensagem, tipo) {
            const toastId = 'toast-' + Date.now();
            const bgClass = tipo === 'success' ? 'bg-success' : tipo === 'error' ? 'bg-danger' : 'bg-info';
            
            const toastHtml = `
                <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
                    <div class="toast-body">
                        ${mensagem}
                    </div>
                </div>
            `;
            
            $('.toast-container').append(toastHtml);
            
            const toast = new bootstrap.Toast(document.getElementById(toastId), {
                autohide: true,
                delay: 5000
            });
            toast.show();
            
            // Remover o toast após ser escondido
            document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
    </script>
</body>
</html>

