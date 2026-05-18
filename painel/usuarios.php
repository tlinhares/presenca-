<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: usuarios                                               ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('usuarios');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciamento de Usuários</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../css/feedback-system.css" rel="stylesheet">
  <style>
    body { background-color: #f0f2f5; }
    .header-page {
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: white;
      padding: 1.5rem 0;
      margin-bottom: 1.5rem;
    }
    .content-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      padding: 1.5rem;
    }
    .filter-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }
    #btnNovoUsuario { white-space: nowrap; }
    .loading-overlay {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      border-radius: 8px;
    }
    .table-responsive { min-height: 200px; }
    #modalConfirmacao { z-index: 2060 !important; }
    #modalConfirmacao .modal-backdrop { z-index: 2055 !important; }
    .form-control, .form-select { border-radius: 8px; }
    .btn { border-radius: 8px; }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header-page">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h3 class="mb-1"><i class="bi bi-people-fill me-2"></i>Gerenciamento de Usuários</h3>
          <small class="opacity-75">Cadastro e controle de usuários do sistema</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
          </a>
        </div>
      </div>
    </div>
  </div>

<div class="container pb-5">
  <!-- Filtros -->
  <div class="filter-section">
    <div class="row g-3 align-items-end">
    <div class="col-md-2">
      <button class="btn btn-success w-100" id="btnNovoUsuario">Novo Usuário</button>
    </div>
    <div class="col-md-4">
      <label class="form-label">Buscar por Nome:</label>
      <input type="text" id="filtroNome" class="form-control" placeholder="Digite o nome">
    </div>
    <div class="col-md-3">
      <label class="form-label">Filtrar por Categoria:</label>
      <select id="filtroCategoria" class="form-select">
        <option value="">Todas</option>
        <option value="funcionario">Funcionário</option>
        <option value="admin">Administrador</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Atividade:</label>
      <select id="filtroStatus" class="form-select">
        <option value="">Todos</option>
        <option value="1">Ativo</option>
        <option value="0">Inativo</option>
      </select>
    </div>
    </div>
  </div>

  <div id="mensagemUsuario" class="mb-3"></div>

  <div class="content-card">
  <div class="table-responsive position-relative">
    <!-- Loading Spinner -->
    <div id="loadingUsuarios" class="loading-overlay" style="display: none;">
      <div class="text-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="mt-3 text-muted">Carregando usuários...</p>
      </div>
    </div>
    
    <table class="table table-bordered table-hover" id="tabelaUsuarios">
      <thead class="table-light">
        <tr>
          <th>Nome</th>
          <th>Email</th>
          <th>CPF</th>
          <th>Status</th>
          <th>Foto</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  </div>
</div>

<!-- Modal de edição/cadastro -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"> <!-- modal-lg para mais espaço -->
    <form class="modal-content" id="formUsuario">
      <div class="modal-header">
        <h5 class="modal-title" id="modalUsuarioLabel">Novo Usuário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="usuario_id">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="nome" class="form-label">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" required>
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
          </div>

          <div class="col-md-6">
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha">
          </div>
          <div class="col-md-6">
            <label for="categoria" class="form-label">Categoria</label>
            <select class="form-select" id="categoria" name="categoria" required>
              <option value="admin">Administrador</option>
              <option value="funcionario">Funcionário</option>
            </select>
          </div>

          <div class="col-md-6">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(00) 00000-0000">
          </div>
          <div class="col-md-6">
            <label for="cpf" class="form-label">CPF</label>
            <input type="text" class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00">
          </div>

          <div class="col-md-6">
            <label for="id_valor" class="form-label">Grupo de Valor</label>
            <select class="form-select" id="id_valor" name="id_valor">
              <option value="">Carregando...</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="entidade_id" class="form-label">Entidade</label>
            <select class="form-select" id="entidade_id" name="entidade_id">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="col-12">
            <div class="form-check mt-2">
              <input type="checkbox" class="form-check-input" id="gerar_qrcode" name="gerar_qrcode" checked>
              <label for="gerar_qrcode" class="form-check-label">Gerar QR Code automaticamente</label>
            </div>
          </div>

          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="culto" name="culto" value="1">
              <label class="form-check-label" for="culto">
                <strong>Culto Ativo</strong>
              </label>
              <div class="form-text">Marque se o usuário está ativo no culto</div>
            </div>
          </div>

          <div class="col-md-6">
            <label for="foto" class="form-label">Foto do Usuário</label>
            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
          </div>

          <div class="col-md-6 text-center align-self-center">
            <div class="border rounded p-2 d-inline-block bg-light shadow-sm">
              <img id="previewFoto" src="#" alt="Prévia da foto"
                   style="display: none; max-height: 160px; border-radius: 8px;">
            </div>
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de Confirmação -->
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="modalConfirmacaoLabel">
          <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Ação
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="modalConfirmacaoTexto">Tem certeza que deseja realizar esta ação?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarAcao">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Dependentes -->
<div class="modal fade" id="modalDependentes" tabindex="-1" aria-labelledby="modalDependentesLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDependentesLabel">Dependentes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="usuario_id_dependentes">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6>Lista de Dependentes</h6>
          <button type="button" class="btn btn-success" id="btnNovoDependente">
            <i class="bi bi-person-plus"></i> Novo Dependente
          </button>
        </div>
        
        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="tabelaDependentesUsuario">
            <thead class="table-light">
              <tr>
                <th>Nome</th>
                <th>Parentesco</th>
                <th>Data de Nascimento</th>
                <th>Foto</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Edição/Cadastro de Dependente -->
<div class="modal fade" id="modalDependente" tabindex="-1" aria-labelledby="modalDependenteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formDependente">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDependenteLabel">Novo Dependente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="dependente_id">
        <input type="hidden" name="usuario_id" id="dependente_usuario_id">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="dependente_nome" class="form-label">Nome</label>
            <input type="text" class="form-control" id="dependente_nome" name="nome" required>
          </div>
          <div class="col-md-6">
            <label for="dependente_parentesco" class="form-label">Parentesco</label>
            <input type="text" class="form-control" id="dependente_parentesco" name="parentesco" required>
          </div>

          <div class="col-md-6">
            <label for="dependente_nascimento" class="form-label">Data de Nascimento</label>
            <input type="date" class="form-control" id="dependente_nascimento" name="nascimento_dependente" required>
          </div>

          <div class="col-md-6">
            <label for="dependente_foto" class="form-label">Foto do Dependente</label>
            <input type="file" class="form-control" id="dependente_foto" name="foto" accept="image/*">
          </div>

          <div class="col-12 text-center">
            <div class="border rounded p-3 d-inline-block bg-light shadow-sm">
              <img id="previewFotoDependente" src="#" alt="Prévia da foto"
                   style="display: none; max-height: 200px; border-radius: 8px;">
              <div id="placeholderFotoDependente" class="text-muted">
                <i class="bi bi-image" style="font-size: 48px;"></i>
                <p>Nenhuma foto selecionada</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-mask-plugin@1.14.16/dist/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/feedback-system.js"></script>
<script src="../js/usuarios.js"></script>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toastContainer"></div>
</div>
</body>
</html>
