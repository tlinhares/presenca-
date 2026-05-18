<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: permissoes                                             ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('permissoes');

// Inclui serviço de permissões para obter os módulos
require_once __DIR__ . '/../core/services/PermissaoService.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Permissões - Sistema de Presença</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .nivel-badge {
            min-width: 100px;
            text-align: center;
        }
        .usuario-card {
            transition: all 0.2s;
            border-radius: 12px;
        }
        .usuario-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .modulo-icon {
            font-size: 1.5rem;
            width: 40px;
            text-align: center;
        }
        .permissao-select {
            min-width: 140px;
            border-radius: 8px;
        }
        .admin-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .table-permissoes th {
            white-space: nowrap;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 8px;
        }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; }
        .card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Gerenciamento de Permissões</h3>
                    <small class="opacity-75">Configure o acesso dos usuários aos módulos do sistema</small>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

<div class="container pb-5">

    <!-- Legenda de Níveis -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Níveis de Permissão</h6>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-2">
                    <span class="badge bg-secondary w-100 py-2">0 - Sem Acesso</span>
                </div>
                <div class="col-md-2">
                    <span class="badge bg-info w-100 py-2">1 - Visualizar</span>
                </div>
                <div class="col-md-2">
                    <span class="badge bg-warning text-dark w-100 py-2">2 - Editar</span>
                </div>
                <div class="col-md-2">
                    <span class="badge bg-danger w-100 py-2">3 - Excluir</span>
                </div>
                <div class="col-md-2">
                    <span class="badge bg-success w-100 py-2">4 - Administrar</span>
                </div>
                <div class="col-md-2">
                    <span class="badge admin-badge text-white w-100 py-2">
                        <i class="bi bi-star-fill"></i> Admin (Total)
                    </span>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="bi bi-lightbulb"></i> 
                <strong>Nota:</strong> Usuários com categoria "admin" têm acesso total automático a todos os módulos.
            </small>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Buscar Usuário:</label>
                    <input type="text" id="filtroUsuario" class="form-control" placeholder="Nome ou email...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Usuário:</label>
                    <select id="filtroTipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="funcionario">Funcionários</option>
                        <option value="admin">Administradores</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status:</label>
                    <select id="filtroStatus" class="form-select">
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="carregarUsuarios()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Usuários e Permissões -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-people"></i> Usuários e Permissões</h6>
            <span class="badge bg-primary" id="contadorUsuarios">0 usuários</span>
        </div>
        <div class="card-body position-relative">
            <!-- Loading -->
            <div id="loadingUsuarios" class="loading-overlay" style="display: none;">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando usuários...</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-permissoes align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 200px;">Usuário</th>
                            <th class="text-center">Tipo</th>
                            <?php
                            $modulos = PermissaoService::getModulos();
                            foreach ($modulos as $modulo): 
                            ?>
                            <th class="text-center" style="min-width: 130px;">
                                <i class="<?= htmlspecialchars($modulo['icone']) ?>"></i>
                                <br>
                                <small><?= htmlspecialchars($modulo['nome']) ?></small>
                            </th>
                            <?php endforeach; ?>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaUsuarios">
                        <tr>
                            <td colspan="<?= count($modulos) + 3 ?>" class="text-center text-muted py-5">
                                <i class="bi bi-arrow-clockwise display-4"></i>
                                <p class="mt-2">Clique em "Filtrar" para carregar os usuários</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edição Rápida -->
<div class="modal fade" id="modalPermissoes" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-check"></i> Editar Permissões
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_usuario_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuário:</label>
                    <p id="modal_usuario_nome" class="mb-0"></p>
                </div>
                <hr>
                <div id="modal_permissoes_lista">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarPermissoesModal()">
                    <i class="bi bi-check-lg"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Módulos disponíveis (carregados do PHP)
const modulos = <?= json_encode($modulos) ?>;

// Cache de permissões
let permissoesCache = {};

// Carregar usuários
async function carregarUsuarios() {
    const loading = document.getElementById('loadingUsuarios');
    const tbody = document.getElementById('tabelaUsuarios');
    
    loading.style.display = 'flex';
    
    const filtroUsuario = document.getElementById('filtroUsuario').value;
    const filtroTipo = document.getElementById('filtroTipo').value;
    const filtroStatus = document.getElementById('filtroStatus').value;
    
    try {
        const params = new URLSearchParams({
            busca: filtroUsuario,
            categoria: filtroTipo,
            ativo: filtroStatus
        });
        
        const response = await fetch(`../api/permissoes/listar_usuarios.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            renderizarUsuarios(data.usuarios);
            document.getElementById('contadorUsuarios').textContent = `${data.usuarios.length} usuários`;
        } else {
            tbody.innerHTML = `<tr><td colspan="${modulos.length + 3}" class="text-center text-danger">
                <i class="bi bi-exclamation-triangle"></i> ${data.message || 'Erro ao carregar'}
            </td></tr>`;
        }
    } catch (error) {
        console.error('Erro:', error);
        tbody.innerHTML = `<tr><td colspan="${modulos.length + 3}" class="text-center text-danger">
            <i class="bi bi-exclamation-triangle"></i> Erro de conexão
        </td></tr>`;
    } finally {
        loading.style.display = 'none';
    }
}

// Renderizar tabela de usuários
function renderizarUsuarios(usuarios) {
    const tbody = document.getElementById('tabelaUsuarios');
    
    if (usuarios.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${modulos.length + 3}" class="text-center text-muted py-5">
            <i class="bi bi-search display-4"></i>
            <p class="mt-2">Nenhum usuário encontrado</p>
        </td></tr>`;
        return;
    }
    
    let html = '';
    
    usuarios.forEach(usuario => {
        // Armazena permissões no cache
        permissoesCache[usuario.id] = usuario.permissoes || {};
        
        const isAdmin = usuario.categoria === 'admin';
        const statusBadge = usuario.ativo == 1 
            ? '<span class="badge bg-success">Ativo</span>' 
            : '<span class="badge bg-secondary">Inativo</span>';
        
        html += `<tr data-usuario-id="${usuario.id}">
            <td>
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        ${usuario.foto_base64 
                            ? `<img src="data:image/jpeg;base64,${usuario.foto_base64}" class="rounded-circle" width="40" height="40" style="object-fit: cover;">` 
                            : `<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-person"></i>
                               </div>`
                        }
                    </div>
                    <div>
                        <strong>${escapeHtml(usuario.nome)}</strong>
                        ${statusBadge}
                        <br>
                        <small class="text-muted">${escapeHtml(usuario.email || '-')}</small>
                    </div>
                </div>
            </td>
            <td class="text-center">
                ${isAdmin 
                    ? '<span class="badge admin-badge text-white"><i class="bi bi-star-fill"></i> Admin</span>' 
                    : '<span class="badge bg-primary">Usuário</span>'
                }
            </td>`;
        
        // Colunas de permissões para cada módulo
        modulos.forEach(modulo => {
            if (isAdmin) {
                html += `<td class="text-center">
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Total</span>
                </td>`;
            } else {
                const nivel = usuario.permissoes?.[modulo.codigo] || 0;
                html += `<td class="text-center">
                    <select class="form-select form-select-sm permissao-select" 
                            data-usuario-id="${usuario.id}" 
                            data-modulo="${modulo.codigo}"
                            onchange="atualizarPermissao(this)">
                        <option value="0" ${nivel == 0 ? 'selected' : ''}>Sem acesso</option>
                        <option value="1" ${nivel == 1 ? 'selected' : ''}>Visualizar</option>
                        <option value="2" ${nivel == 2 ? 'selected' : ''}>Editar</option>
                        <option value="3" ${nivel == 3 ? 'selected' : ''}>Excluir</option>
                        <option value="4" ${nivel == 4 ? 'selected' : ''}>Administrar</option>
                    </select>
                </td>`;
            }
        });
        
        html += `<td class="text-center">
            ${!isAdmin ? `
                <button class="btn btn-sm btn-outline-primary" onclick="abrirModalPermissoes(${usuario.id}, '${escapeHtml(usuario.nome)}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="copiarPermissoes(${usuario.id})" title="Copiar permissões de outro usuário">
                    <i class="bi bi-clipboard"></i>
                </button>
            ` : `
                <span class="text-muted small">Acesso total</span>
            `}
        </td></tr>`;
    });
    
    tbody.innerHTML = html;
}

// Atualizar permissão individual
async function atualizarPermissao(select) {
    const usuarioId = select.dataset.usuarioId;
    const modulo = select.dataset.modulo;
    const nivel = select.value;
    
    select.disabled = true;
    
    try {
        const response = await fetch('../api/permissoes/atualizar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                usuario_id: usuarioId,
                modulo: modulo,
                nivel: nivel
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Feedback visual
            select.classList.add('is-valid');
            setTimeout(() => select.classList.remove('is-valid'), 2000);
            
            // Atualiza cache
            if (!permissoesCache[usuarioId]) permissoesCache[usuarioId] = {};
            permissoesCache[usuarioId][modulo] = parseInt(nivel);
        } else {
            select.classList.add('is-invalid');
            setTimeout(() => select.classList.remove('is-invalid'), 2000);
            alert('Erro: ' + (data.message || 'Não foi possível salvar'));
        }
    } catch (error) {
        console.error('Erro:', error);
        select.classList.add('is-invalid');
        setTimeout(() => select.classList.remove('is-invalid'), 2000);
    } finally {
        select.disabled = false;
    }
}

// Abrir modal de permissões
function abrirModalPermissoes(usuarioId, nome) {
    document.getElementById('modal_usuario_id').value = usuarioId;
    document.getElementById('modal_usuario_nome').textContent = nome;
    
    const permissoes = permissoesCache[usuarioId] || {};
    let html = '';
    
    modulos.forEach(modulo => {
        const nivel = permissoes[modulo.codigo] || 0;
        html += `<div class="mb-3">
            <label class="form-label">
                <i class="${modulo.icone}"></i> ${modulo.nome}
            </label>
            <select class="form-select" id="modal_perm_${modulo.codigo}">
                <option value="0" ${nivel == 0 ? 'selected' : ''}>0 - Sem acesso</option>
                <option value="1" ${nivel == 1 ? 'selected' : ''}>1 - Visualizar</option>
                <option value="2" ${nivel == 2 ? 'selected' : ''}>2 - Editar</option>
                <option value="3" ${nivel == 3 ? 'selected' : ''}>3 - Excluir</option>
                <option value="4" ${nivel == 4 ? 'selected' : ''}>4 - Administrar</option>
            </select>
        </div>`;
    });
    
    document.getElementById('modal_permissoes_lista').innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('modalPermissoes'));
    modal.show();
}

// Salvar permissões do modal
async function salvarPermissoesModal() {
    const usuarioId = document.getElementById('modal_usuario_id').value;
    const permissoes = {};
    
    modulos.forEach(modulo => {
        permissoes[modulo.codigo] = parseInt(document.getElementById(`modal_perm_${modulo.codigo}`).value);
    });
    
    try {
        const response = await fetch('../api/permissoes/atualizar_todas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                usuario_id: usuarioId,
                permissoes: permissoes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPermissoes')).hide();
            carregarUsuarios();
            alert('Permissões atualizadas com sucesso!');
        } else {
            alert('Erro: ' + (data.message || 'Não foi possível salvar'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro de conexão');
    }
}

// Copiar permissões de outro usuário
async function copiarPermissoes(usuarioDestinoId) {
    const usuarioOrigemId = prompt('Digite o ID do usuário para copiar as permissões:');
    
    if (!usuarioOrigemId) return;
    
    if (!confirm(`Deseja copiar as permissões do usuário ID ${usuarioOrigemId} para este usuário?`)) return;
    
    try {
        const response = await fetch('../api/permissoes/copiar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                usuario_origem_id: usuarioOrigemId,
                usuario_destino_id: usuarioDestinoId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            carregarUsuarios();
            alert('Permissões copiadas com sucesso!');
        } else {
            alert('Erro: ' + (data.message || 'Não foi possível copiar'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro de conexão');
    }
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Carregar ao iniciar
document.addEventListener('DOMContentLoaded', () => {
    // Carregar usuários automaticamente
    carregarUsuarios();
    
    // Enter no campo de busca
    document.getElementById('filtroUsuario').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') carregarUsuarios();
    });
});
</script>
</body>
</html>

