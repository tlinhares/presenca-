<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  NOVO SISTEMA DE PERMISSÕES POR MENU                          ║
// ║  Menu: gerenciar_menus (requer_admin=1)                       ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_menus');

require_once __DIR__ . '/../api/conexao.php';

// Buscar todos os menus
$menus = [];
$result = $conn->query("SELECT * FROM menus ORDER BY categoria, ordem");
while ($row = $result->fetch_assoc()) {
    $menus[] = $row;
}

// Buscar todos os grupos
$grupos = [];
$result = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM usuario_grupos WHERE grupo_id = g.id) as total_usuarios FROM grupos_acesso g WHERE g.ativo = 1 ORDER BY g.id");
while ($row = $result->fetch_assoc()) {
    $grupos[] = $row;
}

// Agrupar menus por categoria
$menus_por_categoria = [];
foreach ($menus as $menu) {
    $cat = $menu['categoria'] ?? 'geral';
    if (!isset($menus_por_categoria[$cat])) {
        $menus_por_categoria[$cat] = [];
    }
    $menus_por_categoria[$cat][] = $menu;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Menus e Permissões - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #6f42c1 0%, #9f5ed0 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .card-menu { transition: all 0.2s; }
        .card-menu:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .badge-acesso { font-size: 0.7rem; }
        .nav-pills .nav-link.active { background-color: #6f42c1; }
        .grupo-card { border-left: 4px solid; }
        .switch-acesso { width: 50px; height: 26px; }
        .content-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-menu-button-wide me-2"></i>Gerenciar Menus e Permissões</h3>
                    <small class="opacity-75">Configure quais menus cada grupo de usuários pode acessar</small>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">

        <!-- Tabs -->
        <ul class="nav nav-pills mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="menus-tab" data-bs-toggle="pill" data-bs-target="#menus" type="button">
                    <i class="bi bi-list me-1"></i> Menus (<?= count($menus) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grupos-tab" data-bs-toggle="pill" data-bs-target="#grupos" type="button">
                    <i class="bi bi-people me-1"></i> Grupos (<?= count($grupos) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="usuarios-tab" data-bs-toggle="pill" data-bs-target="#usuarios" type="button">
                    <i class="bi bi-person me-1"></i> Usuários por Grupo
                </button>
            </li>
        </ul>

        <div class="tab-content" id="mainTabsContent">
            <!-- Tab: Menus -->
            <div class="tab-pane fade show active" id="menus" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="alert alert-info mb-0 flex-grow-1 me-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Como funciona:</strong> 
                        Menus com <span class="badge bg-success">Acesso Livre</span> podem ser acessados por todos. 
                        Menus com <span class="badge bg-warning text-dark">Por Grupo</span> só podem ser acessados por usuários nos grupos configurados.
                    </div>
                    <button class="btn btn-success" onclick="abrirModalNovoMenu()">
                        <i class="bi bi-plus-circle me-1"></i> Novo Menu
                    </button>
                </div>

                <?php foreach ($menus_por_categoria as $categoria => $menus_cat): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-folder me-2"></i>
                            <?= ucfirst($categoria) ?>
                            <span class="badge bg-secondary ms-2"><?= count($menus_cat) ?> menus</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Menu</th>
                                        <th>URL</th>
                                        <th class="text-center">Acesso Livre</th>
                                        <th class="text-center">Requer Culto</th>
                                        <th class="text-center">Só Admin</th>
                                        <th class="text-center">Ativo</th>
                                        <th>Grupos com Acesso</th>
                                                    <th width="80">Ações</th>
                                                </tr>
                                                </thead>
                                <tbody>
                                    <?php foreach ($menus_cat as $menu): ?>
                                    <tr>
                                        <td>
                                            <i class="bi <?= $menu['icone'] ?> me-2 text-primary"></i>
                                            <strong><?= htmlspecialchars($menu['nome']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($menu['descricao']) ?></small>
                                        </td>
                                        <td><code><?= htmlspecialchars($menu['url']) ?></code></td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input switch-acesso" type="checkbox" 
                                                       id="acesso_<?= $menu['id'] ?>"
                                                       data-menu-id="<?= $menu['id'] ?>"
                                                       data-campo="acesso_padrao"
                                                       <?= $menu['acesso_padrao'] ? 'checked' : '' ?>
                                                       <?= $menu['requer_admin'] ? 'disabled' : '' ?>>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-menu-id="<?= $menu['id'] ?>"
                                                       data-campo="requer_culto"
                                                       <?= $menu['requer_culto'] ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-menu-id="<?= $menu['id'] ?>"
                                                       data-campo="requer_admin"
                                                       <?= $menu['requer_admin'] ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-menu-id="<?= $menu['id'] ?>"
                                                       data-campo="ativo"
                                                       <?= $menu['ativo'] ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="abrirModalGrupos(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['nome']) ?>')"
                                                    title="Configurar Grupos">
                                                <i class="bi bi-people"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary" 
                                                        onclick='editarMenu(<?= json_encode($menu) ?>)'
                                                        title="Editar Menu">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="excluirMenu(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['nome']) ?>')"
                                                        title="Excluir Menu">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tab: Grupos -->
            <div class="tab-pane fade" id="grupos" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-12">
                        <button class="btn btn-primary" onclick="abrirModalNovoGrupo()">
                            <i class="bi bi-plus-circle me-1"></i> Novo Grupo
                        </button>
                    </div>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($grupos as $grupo): ?>
                    <div class="col-md-4">
                        <div class="card grupo-card h-100" style="border-left-color: <?= $grupo['cor'] ?>">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <span class="badge me-2" style="background-color: <?= $grupo['cor'] ?>">&nbsp;</span>
                                    <?= htmlspecialchars($grupo['nome']) ?>
                                </h5>
                                <p class="card-text text-muted small"><?= htmlspecialchars($grupo['descricao']) ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-people me-1"></i><?= $grupo['total_usuarios'] ?> usuários
                                    </span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarGrupo(<?= $grupo['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="verMenusGrupo(<?= $grupo['id'] ?>, '<?= htmlspecialchars($grupo['nome']) ?>')">
                                            <i class="bi bi-list-check"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab: Usuários por Grupo -->
            <div class="tab-pane fade" id="usuarios" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-search me-2"></i>Buscar Usuário
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control mb-3" id="buscarUsuario" 
                                       placeholder="Digite nome ou email...">
                                <div id="resultadoBusca" style="max-height: 400px; overflow-y: auto;">
                                    <p class="text-muted text-center">Digite para buscar...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-person-badge me-2"></i>Grupos do Usuário Selecionado
                            </div>
                            <div class="card-body" id="gruposUsuario">
                                <p class="text-muted text-center">Selecione um usuário para ver seus grupos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Configurar Grupos do Menu -->
    <div class="modal fade" id="modalGruposMenu" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>Grupos com Acesso ao Menu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Menu: <strong id="nomeMenuModal"></strong></p>
                    <div id="listaGruposMenu">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarGruposMenu()">
                        <i class="bi bi-check me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Novo Menu -->
    <div class="modal fade" id="modalNovoMenu" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Novo Menu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="novoMenuCodigo" 
                                       placeholder="Ex: frota_dashboard" pattern="[a-z0-9_]+"
                                       title="Apenas letras minúsculas, números e underscore">
                                <small class="text-muted">Identificador único (sem espaços, minúsculas)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="novoMenuNome" 
                                       placeholder="Ex: Dashboard Frota">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">URL <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="novoMenuUrl" 
                                       placeholder="Ex: /frota/dashboard.php">
                                <small class="text-muted">Caminho do arquivo PHP</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="novoMenuCategoria">
                                    <option value="geral">Geral</option>
                                    <option value="gerenciamento">Gerenciamento</option>
                                    <option value="refeicoes">Refeições</option>
                                    <option value="culto">Culto</option>
                                    <option value="frota">Frota</option>
                                    <option value="estoque">Estoque</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" id="novoMenuDescricao" 
                               placeholder="Ex: Painel principal do controle de frota">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ícone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-circle" id="previewIcone"></i></span>
                                    <input type="text" class="form-control" id="novoMenuIcone" 
                                           placeholder="bi-truck" value="bi-circle">
                                </div>
                                <small class="text-muted">
                                    <a href="https://icons.getbootstrap.com/" target="_blank">Ver ícones disponíveis</a>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" class="form-control" id="novoMenuOrdem" value="0">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="novoMenuAcessoPadrao" checked>
                                <label class="form-check-label" for="novoMenuAcessoPadrao">
                                    <strong>Acesso Livre</strong>
                                    <br><small class="text-muted">Todos podem acessar</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="novoMenuRequerCulto">
                                <label class="form-check-label" for="novoMenuRequerCulto">
                                    <strong>Requer Culto</strong>
                                    <br><small class="text-muted">Só quem participa do culto</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="novoMenuRequerAdmin">
                                <label class="form-check-label" for="novoMenuRequerAdmin">
                                    <strong>Só Admin</strong>
                                    <br><small class="text-muted">Apenas administradores</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="criarMenu()">
                        <i class="bi bi-check me-1"></i>Criar Menu
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Menu -->
    <div class="modal fade" id="modalEditarMenu" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Editar Menu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editMenuId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="editMenuCodigo" readonly>
                                <small class="text-muted">O código não pode ser alterado</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editMenuNome">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">URL <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editMenuUrl">
                                <small class="text-muted">Caminho do arquivo PHP</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="editMenuCategoria">
                                    <option value="geral">Geral</option>
                                    <option value="gerenciamento">Gerenciamento</option>
                                    <option value="refeicoes">Refeições</option>
                                    <option value="culto">Culto</option>
                                    <option value="frota">Frota</option>
                                    <option value="estoque">Estoque</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" id="editMenuDescricao">
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ícone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-circle" id="editPreviewIcone"></i></span>
                                    <input type="text" class="form-control" id="editMenuIcone">
                                </div>
                                <small class="text-muted">
                                    <a href="https://icons.getbootstrap.com/" target="_blank">Ver ícones</a>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Estilo do Card</label>
                                <select class="form-select" id="editMenuClasseCard">
                                    <option value="gerenciamento">Gerenciamento (cinza)</option>
                                    <option value="usuarios">Usuários (azul)</option>
                                    <option value="relatorios">Relatórios (verde)</option>
                                    <option value="config">Configurações (amarelo)</option>
                                    <option value="facial">Facial (roxo)</option>
                                    <option value="refeicoes">Refeições (verde água)</option>
                                    <option value="culto">Culto (vermelho)</option>
                                    <option value="logs">Logs (cinza escuro)</option>
                                    <option value="frota">Frota (ciano)</option>
                                    <option value="estoque">Estoque (laranja)</option>
                                    <option value="presenca">Presença (verde escuro)</option>
                                    <option value="relatorio">Relatório (azul claro)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" class="form-control" id="editMenuOrdem">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição do Card</label>
                        <input type="text" class="form-control" id="editMenuDescricaoCard" placeholder="Texto curto que aparece abaixo do título">
                        <small class="text-muted">Aparece nos dashboards dinâmicos</small>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editMenuAcessoPadrao">
                                <label class="form-check-label" for="editMenuAcessoPadrao">
                                    <strong>Acesso Livre</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editMenuRequerCulto">
                                <label class="form-check-label" for="editMenuRequerCulto">
                                    <strong>Requer Culto</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editMenuRequerAdmin">
                                <label class="form-check-label" for="editMenuRequerAdmin">
                                    <strong>Só Admin</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editMenuAtivo">
                                <label class="form-check-label" for="editMenuAtivo">
                                    <strong>Ativo</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoMenu()">
                        <i class="bi bi-check me-1"></i>Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmar Exclusão -->
    <div class="modal fade" id="modalExcluirMenu" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o menu <strong id="nomeMenuExcluir"></strong>?</p>
                    <p class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Esta ação não pode ser desfeita!</p>
                    <input type="hidden" id="idMenuExcluir">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarExclusaoMenu()">
                        <i class="bi bi-trash me-1"></i>Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Novo Grupo -->
    <div class="modal fade" id="modalNovoGrupo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Novo Grupo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do Grupo</label>
                        <input type="text" class="form-control" id="nomeNovoGrupo" placeholder="Ex: Líderes de Departamento">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="descNovoGrupo" rows="2" placeholder="Descrição do grupo..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cor</label>
                        <input type="color" class="form-control form-control-color" id="corNovoGrupo" value="#6c757d">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="criarGrupo()">
                        <i class="bi bi-check me-1"></i>Criar Grupo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Menus do Grupo -->
    <div class="modal fade" id="modalMenusGrupo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-list-check me-2"></i>Menus do Grupo: <span id="nomeGrupoModal"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="listaMenusGrupo">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-info" onclick="salvarMenusGrupo()">
                        <i class="bi bi-check me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let menuIdAtual = null;
        let grupoIdAtual = null;
        const grupos = <?= json_encode($grupos) ?>;
        const menus = <?= json_encode($menus) ?>;

        // Atualizar campo do menu
        $(document).on('change', '[data-menu-id][data-campo]', function() {
            const menuId = $(this).data('menu-id');
            const campo = $(this).data('campo');
            const valor = $(this).is(':checked') ? 1 : 0;
            
            $.post('../api/menus/atualizar.php', {
                menu_id: menuId,
                campo: campo,
                valor: valor
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Menu atualizado!', 'success');
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json').fail(function() {
                mostrarToast('Erro ao atualizar menu', 'danger');
            });
        });

        // Abrir modal de grupos do menu
        function abrirModalGrupos(menuId, nomeMenu) {
            menuIdAtual = menuId;
            $('#nomeMenuModal').text(nomeMenu);
            
            // Buscar grupos que têm acesso a este menu
            $.get('../api/menus/grupos_menu.php', { menu_id: menuId }, function(response) {
                let html = '';
                const gruposComAcesso = response.grupos || [];
                
                grupos.forEach(grupo => {
                    // Converter para inteiro para comparação correta
                    const grupoId = parseInt(grupo.id);
                    const temAcesso = gruposComAcesso.includes(grupoId);
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input grupo-menu-check" type="checkbox" 
                                   value="${grupoId}" id="grupo_${grupoId}" ${temAcesso ? 'checked' : ''}>
                            <label class="form-check-label" for="grupo_${grupoId}">
                                <span class="badge me-1" style="background-color: ${grupo.cor}">&nbsp;</span>
                                ${grupo.nome}
                                <small class="text-muted">(${grupo.total_usuarios} usuários)</small>
                            </label>
                        </div>
                    `;
                });
                
                $('#listaGruposMenu').html(html);
                new bootstrap.Modal($('#modalGruposMenu')).show();
            }, 'json');
        }

        // Salvar grupos do menu
        function salvarGruposMenu() {
            const gruposSelecionados = [];
            $('.grupo-menu-check:checked').each(function() {
                gruposSelecionados.push($(this).val());
            });
            
            $.post('../api/menus/salvar_grupos.php', {
                menu_id: menuIdAtual,
                grupos: gruposSelecionados
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Grupos atualizados!', 'success');
                    bootstrap.Modal.getInstance($('#modalGruposMenu')).hide();
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json');
        }

        // Abrir modal de novo menu
        function abrirModalNovoMenu() {
            $('#novoMenuCodigo').val('');
            $('#novoMenuNome').val('');
            $('#novoMenuUrl').val('');
            $('#novoMenuDescricao').val('');
            $('#novoMenuIcone').val('bi-circle');
            $('#novoMenuCategoria').val('geral');
            $('#novoMenuOrdem').val(0);
            $('#novoMenuAcessoPadrao').prop('checked', true);
            $('#novoMenuRequerCulto').prop('checked', false);
            $('#novoMenuRequerAdmin').prop('checked', false);
            $('#previewIcone').attr('class', 'bi bi-circle');
            new bootstrap.Modal($('#modalNovoMenu')).show();
        }

        // Preview do ícone
        $('#novoMenuIcone').on('input', function() {
            const icone = $(this).val() || 'bi-circle';
            $('#previewIcone').attr('class', 'bi ' + icone);
        });

        // Criar menu
        function criarMenu() {
            const codigo = $('#novoMenuCodigo').val().trim().toLowerCase().replace(/\s+/g, '_');
            const nome = $('#novoMenuNome').val().trim();
            const url = $('#novoMenuUrl').val().trim();
            const descricao = $('#novoMenuDescricao').val().trim();
            const icone = $('#novoMenuIcone').val().trim() || 'bi-circle';
            const categoria = $('#novoMenuCategoria').val();
            const ordem = parseInt($('#novoMenuOrdem').val()) || 0;
            const acessoPadrao = $('#novoMenuAcessoPadrao').is(':checked') ? 1 : 0;
            const requerCulto = $('#novoMenuRequerCulto').is(':checked') ? 1 : 0;
            const requerAdmin = $('#novoMenuRequerAdmin').is(':checked') ? 1 : 0;
            
            if (!codigo || !nome || !url) {
                mostrarToast('Preencha os campos obrigatórios (Código, Nome e URL)', 'warning');
                return;
            }
            
            $.post('../api/menus/criar_menu.php', {
                codigo: codigo,
                nome: nome,
                url: url,
                descricao: descricao,
                icone: icone,
                categoria: categoria,
                ordem: ordem,
                acesso_padrao: acessoPadrao,
                requer_culto: requerCulto,
                requer_admin: requerAdmin
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Menu criado com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json');
        }

        // Abrir modal de novo grupo
        function abrirModalNovoGrupo() {
            $('#nomeNovoGrupo').val('');
            $('#descNovoGrupo').val('');
            $('#corNovoGrupo').val('#6c757d');
            new bootstrap.Modal($('#modalNovoGrupo')).show();
        }

        // Criar grupo
        function criarGrupo() {
            const nome = $('#nomeNovoGrupo').val().trim();
            const descricao = $('#descNovoGrupo').val().trim();
            const cor = $('#corNovoGrupo').val();
            
            if (!nome) {
                mostrarToast('Digite o nome do grupo', 'warning');
                return;
            }
            
            $.post('../api/menus/criar_grupo.php', {
                nome: nome,
                descricao: descricao,
                cor: cor
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Grupo criado!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json');
        }

        // Ver menus do grupo
        function verMenusGrupo(grupoId, nomeGrupo) {
            grupoIdAtual = grupoId;
            $('#nomeGrupoModal').text(nomeGrupo);
            
            $.get('../api/menus/menus_grupo.php', { grupo_id: grupoId }, function(response) {
                let html = '<div class="row">';
                const menusDoGrupo = response.menus || [];
                
                // Agrupar por categoria
                const categorias = {};
                menus.forEach(menu => {
                    if (!categorias[menu.categoria]) {
                        categorias[menu.categoria] = [];
                    }
                    categorias[menu.categoria].push(menu);
                });
                
                for (const [cat, menusCategoria] of Object.entries(categorias)) {
                    html += `<div class="col-md-6 mb-3">
                        <h6 class="text-muted">${cat.toUpperCase()}</h6>`;
                    
                    menusCategoria.forEach(menu => {
                        // Converter para inteiro para comparação correta
                        const menuId = parseInt(menu.id);
                        const temAcesso = menusDoGrupo.includes(menuId);
                        html += `
                            <div class="form-check">
                                <input class="form-check-input menu-grupo-check" type="checkbox" 
                                       value="${menuId}" id="menu_${menuId}" ${temAcesso ? 'checked' : ''}>
                                <label class="form-check-label" for="menu_${menuId}">
                                    <i class="bi ${menu.icone} me-1"></i>${menu.nome}
                                </label>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                }
                html += '</div>';
                
                $('#listaMenusGrupo').html(html);
                new bootstrap.Modal($('#modalMenusGrupo')).show();
            }, 'json');
        }

        // Salvar menus do grupo
        function salvarMenusGrupo() {
            const menusSelecionados = [];
            $('.menu-grupo-check:checked').each(function() {
                menusSelecionados.push($(this).val());
            });
            
            $.post('../api/menus/salvar_menus_grupo.php', {
                grupo_id: grupoIdAtual,
                menus: menusSelecionados
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Menus do grupo atualizados!', 'success');
                    bootstrap.Modal.getInstance($('#modalMenusGrupo')).hide();
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json');
        }

        // Buscar usuário
        let timeoutBusca = null;
        $('#buscarUsuario').on('input', function() {
            clearTimeout(timeoutBusca);
            const termo = $(this).val().trim();
            
            if (termo.length < 2) {
                $('#resultadoBusca').html('<p class="text-muted text-center">Digite pelo menos 2 caracteres...</p>');
                return;
            }
            
            timeoutBusca = setTimeout(() => {
                $.get('../api/menus/buscar_usuarios.php', { termo: termo }, function(response) {
                    if (response.usuarios && response.usuarios.length > 0) {
                        let html = '<div class="list-group">';
                        response.usuarios.forEach(u => {
                            html += `
                                <a href="#" class="list-group-item list-group-item-action" 
                                   onclick="selecionarUsuario(${u.id}, '${u.nome}'); return false;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${u.nome}</strong>
                                            <br><small class="text-muted">${u.email}</small>
                                        </div>
                                        <span class="badge bg-${u.categoria === 'admin' ? 'danger' : 'secondary'}">${u.categoria}</span>
                                    </div>
                                </a>
                            `;
                        });
                        html += '</div>';
                        $('#resultadoBusca').html(html);
                    } else {
                        $('#resultadoBusca').html('<p class="text-muted text-center">Nenhum usuário encontrado</p>');
                    }
                }, 'json');
            }, 300);
        });

        // Selecionar usuário
        function selecionarUsuario(usuarioId, nomeUsuario) {
            $.get('../api/menus/grupos_usuario.php', { usuario_id: usuarioId }, function(response) {
                let html = `<h5 class="mb-3">${nomeUsuario}</h5>`;
                
                if (response.categoria === 'admin') {
                    html += '<div class="alert alert-danger"><i class="bi bi-shield-fill me-2"></i>Este usuário é ADMIN e tem acesso total ao sistema.</div>';
                } else {
                    html += '<p class="text-muted">Marque os grupos que este usuário deve pertencer:</p>';
                    
                    const gruposDoUsuario = response.grupos || [];
                    
                    grupos.forEach(grupo => {
                        // Converter para inteiro para comparação correta
                        const grupoId = parseInt(grupo.id);
                        const pertence = gruposDoUsuario.includes(grupoId);
                        html += `
                            <div class="form-check mb-2">
                                <input class="form-check-input usuario-grupo-check" type="checkbox" 
                                       value="${grupoId}" data-usuario="${usuarioId}"
                                       id="ug_${grupoId}" ${pertence ? 'checked' : ''}>
                                <label class="form-check-label" for="ug_${grupoId}">
                                    <span class="badge me-1" style="background-color: ${grupo.cor}">&nbsp;</span>
                                    ${grupo.nome}
                                </label>
                            </div>
                        `;
                    });
                    
                    html += `
                        <button class="btn btn-primary mt-3" onclick="salvarGruposUsuario(${usuarioId})">
                            <i class="bi bi-check me-1"></i>Salvar Grupos
                        </button>
                    `;
                }
                
                $('#gruposUsuario').html(html);
            }, 'json');
        }

        // Salvar grupos do usuário
        function salvarGruposUsuario(usuarioId) {
            const gruposSelecionados = [];
            $('.usuario-grupo-check:checked').each(function() {
                gruposSelecionados.push($(this).val());
            });
            
            $.post('../api/menus/salvar_grupos_usuario.php', {
                usuario_id: usuarioId,
                grupos: gruposSelecionados
            }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Grupos do usuário atualizados!', 'success');
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json');
        }

        // Editar grupo
        function editarGrupo(grupoId) {
            // Por simplicidade, recarrega a página após edição
            alert('Funcionalidade de edição em desenvolvimento. Use o banco de dados por enquanto.');
        }

        // Editar menu
        function editarMenu(menu) {
            $('#editMenuId').val(menu.id);
            $('#editMenuCodigo').val(menu.codigo);
            $('#editMenuNome').val(menu.nome);
            $('#editMenuUrl').val(menu.url);
            $('#editMenuDescricao').val(menu.descricao || '');
            $('#editMenuDescricaoCard').val(menu.descricao_card || '');
            $('#editMenuIcone').val(menu.icone || 'bi-circle');
            $('#editMenuCategoria').val(menu.categoria || 'geral');
            $('#editMenuClasseCard').val(menu.classe_card || 'gerenciamento');
            $('#editMenuOrdem').val(menu.ordem || 0);
            $('#editMenuAcessoPadrao').prop('checked', menu.acesso_padrao == 1);
            $('#editMenuRequerCulto').prop('checked', menu.requer_culto == 1);
            $('#editMenuRequerAdmin').prop('checked', menu.requer_admin == 1);
            $('#editMenuAtivo').prop('checked', menu.ativo == 1);
            $('#editPreviewIcone').attr('class', 'bi ' + (menu.icone || 'bi-circle'));
            
            new bootstrap.Modal($('#modalEditarMenu')).show();
        }

        // Preview do ícone na edição
        $('#editMenuIcone').on('input', function() {
            const icone = $(this).val() || 'bi-circle';
            $('#editPreviewIcone').attr('class', 'bi ' + icone);
        });

        // Salvar edição do menu
        function salvarEdicaoMenu() {
            const menuId = $('#editMenuId').val();
            const dados = {
                menu_id: menuId,
                nome: $('#editMenuNome').val().trim(),
                url: $('#editMenuUrl').val().trim(),
                descricao: $('#editMenuDescricao').val().trim(),
                descricao_card: $('#editMenuDescricaoCard').val().trim(),
                icone: $('#editMenuIcone').val().trim() || 'bi-circle',
                categoria: $('#editMenuCategoria').val(),
                classe_card: $('#editMenuClasseCard').val(),
                ordem: parseInt($('#editMenuOrdem').val()) || 0,
                acesso_padrao: $('#editMenuAcessoPadrao').is(':checked') ? 1 : 0,
                requer_culto: $('#editMenuRequerCulto').is(':checked') ? 1 : 0,
                requer_admin: $('#editMenuRequerAdmin').is(':checked') ? 1 : 0,
                ativo: $('#editMenuAtivo').is(':checked') ? 1 : 0
            };
            
            if (!dados.nome || !dados.url) {
                mostrarToast('Preencha os campos obrigatórios (Nome e URL)', 'warning');
                return;
            }
            
            $.post('../api/menus/editar_menu.php', dados, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Menu atualizado com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json').fail(function() {
                mostrarToast('Erro ao atualizar menu', 'danger');
            });
        }

        // Excluir menu
        function excluirMenu(menuId, nomeMenu) {
            $('#idMenuExcluir').val(menuId);
            $('#nomeMenuExcluir').text(nomeMenu);
            new bootstrap.Modal($('#modalExcluirMenu')).show();
        }

        // Confirmar exclusão do menu
        function confirmarExclusaoMenu() {
            const menuId = $('#idMenuExcluir').val();
            
            $.post('../api/menus/excluir_menu.php', { menu_id: menuId }, function(response) {
                if (response.status === 'sucesso') {
                    mostrarToast('Menu excluído com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarToast('Erro: ' + response.mensagem, 'danger');
                }
            }, 'json').fail(function() {
                mostrarToast('Erro ao excluir menu', 'danger');
            });
        }

        // Toast de notificação
        function mostrarToast(mensagem, tipo) {
            const toast = $(`
                <div class="toast align-items-center text-white bg-${tipo} border-0 position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999;" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${mensagem}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('body').append(toast);
            const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
            bsToast.show();
            toast.on('hidden.bs.toast', function() { $(this).remove(); });
        }
    </script>
</body>
</html>

