<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Verificação de login
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');
include_once(__DIR__ . '/../utils/config.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: refeicoes_reserva (acesso_padrao=1)                    ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('refeicoes_reserva');

// Variáveis de sessão - admin OU quem tem permissão de administrar refeições
$isAdmin = MenuPermissaoService::isAdmin();
$isAdminRefeicoes = $isAdmin; // Admin tem permissão total
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Mensagem inicial do sistema
$mensagem_inicio = get_config('mensagem_inicio', '');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Módulo de Refeições</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/feedback-system.css" rel="stylesheet">
  <style>
    body {
      background-color: #f0f2f5;
    }
    .header-refeicoes {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      padding: 1.5rem 0;
      margin-bottom: 1.5rem;
    }
    .card-reserva {
      border-radius: 12px;
      overflow: hidden;
      transition: all 0.3s ease;
      border: none;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    .card-reserva:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .card-reserva .card-header {
      border-bottom: none;
      font-weight: 600;
    }
    .card-reserva.card-principal {
      border-top: 4px solid #28a745;
    }
    .card-reserva.card-adicional {
      border-top: 4px solid #007bff;
    }
    .card-reserva.card-departamento {
      border-top: 4px solid #ffc107;
    }
    .btn-reserva-principal {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      border: none;
      padding: 12px 32px;
      font-size: 1.1rem;
    }
    .btn-reserva-principal:hover {
      background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
      transform: scale(1.02);
    }
    /* Botão de cancelar reserva */
    #btnReservaPropria.btn-danger {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      border: none;
      padding: 12px 32px;
      font-size: 1.1rem;
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }
    #btnReservaPropria.btn-danger:hover {
      background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
      transform: scale(1.02);
      box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
    }
    .foto-perfil-mini {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      overflow: hidden;
      background: #e9ecef;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .foto-perfil-mini img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .foto-perfil-mini i {
      font-size: 11px;
    }
    /* Garantir que todos os botões do header tenham o mesmo tamanho */
    .header-refeicoes .d-flex.gap-2 .btn {
      height: 31px !important;
      padding: 0.25rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.5 !important;
      display: inline-flex !important;
      align-items: center !important;
      white-space: nowrap;
      box-sizing: border-box;
    }
    /* Garantir que o botão de configurações não tenha tamanho diferente */
    #btnConfiguracoes {
      min-width: auto !important;
      max-width: none !important;
    }
  </style>
</head>
<body>

<!-- Header -->
<div class="header-refeicoes">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="mb-1"><i class="bi bi-egg-fried me-2"></i>Módulo de Refeições</h3>
        <small class="opacity-75">Reservas de Almoço</small>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="btn btn-outline-light btn-sm d-flex align-items-center">
          <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <?php if ($isAdmin): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="btn btn-light btn-sm d-flex align-items-center">
          <i class="bi bi-gear me-1"></i>Painel Admin
        </a>
        <?php endif; ?>
        <button class="btn btn-outline-light btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalConfiguracoes" id="btnConfiguracoes" style="min-width: auto;">
          <div class="foto-perfil-mini me-2" id="fotoPerfilMini">
            <i class="bi bi-person-fill text-muted"></i>
          </div>
          <i class="bi bi-gear"></i>
        </button>
        <a href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>" class="btn btn-outline-light btn-sm d-flex align-items-center">
          <i class="bi bi-power me-1"></i>Sair
        </a>
      </div>
    </div>
  </div>
</div>

<div class="container pb-5">

  <!-- Mensagem inicial do sistema -->
  <?php if (!empty($mensagem_inicio)): ?>
    <div class="alert alert-info text-center shadow-sm" style="border-radius: 12px;">
      <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($mensagem_inicio) ?>
    </div>
  <?php endif; ?>

  <!-- Card: Reserva própria -->
  <div class="card card-reserva card-principal mb-4">
    <div class="card-body text-center py-4">
      <h5 class="mb-3"><i class="bi bi-person-check me-2"></i>Minha Reserva</h5>
      <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-3 mb-3">
        <button id="btnReservaPropria" class="btn btn-success btn-reserva-principal" data-estado="reservar">
          <i class="bi bi-egg-fried me-2"></i>Reservar meu almoço
        </button>
        <button id="btnReservaMultipla" class="btn btn-outline-primary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#modalReservaMultipla">
          <i class="bi bi-calendar-range me-2"></i>Reservar para vários dias
        </button>
      </div>
      <div id="mensagemPropria" class="mb-2"></div>
    </div>
  </div>

  <!-- Modal Reserva Múltipla -->
  <div class="modal fade" id="modalReservaMultipla" tabindex="-1" aria-labelledby="modalReservaMultiplaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="formReservaMultipla">
          <div class="modal-header">
            <h5 class="modal-title" id="modalReservaMultiplaLabel">Reservar para vários dias</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <!-- Abas para diferentes tipos de reserva -->
            <ul class="nav nav-tabs mb-3" id="reservaMultiplaTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="reserva-propria-tab" data-bs-toggle="tab" data-bs-target="#reserva-propria" type="button" role="tab">
                  <i class="bi bi-person"></i> Minha Reserva
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="reserva-dependentes-tab" data-bs-toggle="tab" data-bs-target="#reserva-dependentes" type="button" role="tab">
                  <i class="bi bi-people"></i> Dependentes
                </button>
              </li>
            </ul>
            
            <div class="tab-content" id="reservaMultiplaTabsContent">
              <!-- Aba: Reserva Própria -->
              <div class="tab-pane fade show active" id="reserva-propria" role="tabpanel">
                <div class="mb-3">
                  <label for="data_inicio_propria" class="form-label">Data inicial</label>
                  <input type="date" class="form-control" id="data_inicio_propria" name="data_inicio_propria">
                </div>
                <div class="mb-3">
                  <label for="data_fim_propria" class="form-label">Data final</label>
                  <input type="date" class="form-control" id="data_fim_propria" name="data_fim_propria">
                </div>
                <div class="alert alert-info small">
                  <i class="bi bi-info-circle"></i> Serão reservados apenas dias úteis (segunda a sexta-feira) dentro do intervalo selecionado.
                </div>
                <div class="text-center mt-3">
                  <button type="button" class="btn btn-primary" id="btnReservarPropriaMultipla">
                    <i class="bi bi-calendar-check"></i> Reservar Minha Reserva
                  </button>
                </div>
              </div>
              
              <!-- Aba: Reserva de Dependentes -->
              <div class="tab-pane fade" id="reserva-dependentes" role="tabpanel">
                <div class="row">
                  <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <label class="form-label mb-0">Selecionar Dependentes</label>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelecionarTodos">
                        <i class="bi bi-check-all"></i> Selecionar Todos
                      </button>
                    </div>
                    <div id="listaDependentesMultiplos" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                      <!-- Será preenchido via JavaScript -->
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="data_inicio_dependentes" class="form-label">Data inicial</label>
                      <input type="date" class="form-control" id="data_inicio_dependentes" name="data_inicio_dependentes">
                    </div>
                    <div class="mb-3">
                      <label for="data_fim_dependentes" class="form-label">Data final</label>
                      <input type="date" class="form-control" id="data_fim_dependentes" name="data_fim_dependentes">
                    </div>
                  </div>
                </div>
                <div class="alert alert-info small">
                  <i class="bi bi-info-circle"></i> Serão reservados apenas dias úteis (segunda a sexta-feira) para os dependentes selecionados.
                </div>
                <div class="text-center mt-3">
                  <button type="button" class="btn btn-primary" id="btnReservarDependentesMultipla">
                    <i class="bi bi-people"></i> Reservar para Dependentes
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </form>
      </div>
    </div>
</div>

  <!-- Card: Reservas de Departamento (apenas admin) -->
  <?php if ($isAdmin): ?>
  <div class="card card-reserva card-departamento mb-4">
    <div class="card-header bg-warning bg-opacity-25">
      <h5 class="mb-0 text-dark">
        <i class="bi bi-building me-2"></i>Reservas de Departamento
        <span class="badge bg-warning text-dark ms-2">Admin</span>
      </h5>
    </div>
    <div class="card-body">
      <form id="formReservaDepartamento" class="row g-3">
        <div class="col-md-4">
          <label for="dept_entidade_select" class="form-label">Departamento</label>
          <select class="form-select" id="dept_entidade_select" name="entidade_id" required>
            <option value="">Selecione o departamento</option>
            <!-- Será preenchido via JavaScript -->
          </select>
        </div>
        <div class="col-md-2">
          <label for="dept_quantidade" class="form-label">Quantidade</label>
          <input type="number" class="form-control" id="dept_quantidade" 
                 name="quantidade" min="1" value="1" required>
        </div>
        <div class="col-md-4">
          <label for="dept_evento_motivo" class="form-label">Nome do Evento/Motivo</label>
          <input type="text" class="form-control" id="dept_evento_motivo" 
                 name="evento_motivo" placeholder="Ex: Reunião, Treinamento, etc." required>
        </div>
        <div class="col-md-2">
          <label for="dept_data" class="form-label">Data</label>
          <input type="date" class="form-control" id="dept_data" 
                 name="data" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-building me-1"></i>Reservar para Departamento
          </button>
          <button type="button" class="btn btn-outline-primary ms-2" 
                  onclick="abrirAbaDeptReservas()">
            <i class="bi bi-list-ul me-1"></i>Visualizar Reservas
          </button>
        </div>
      </form>
      <div id="dept_mensagem" class="mt-3"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Card: Reservas adicionais -->
  <div class="card card-reserva card-adicional mb-4">
    <div class="card-header bg-primary bg-opacity-10">
      <h5 class="mb-0 text-primary"><i class="bi bi-plus-circle me-2"></i>Reservas Adicionais</h5>
    </div>
    <div class="card-body">
  <form id="formReservaAdicional" class="row g-3">
    <div class="col-md-4">
      <label for="data" class="form-label">Data</label>
      <input type="date" class="form-control" id="data" name="data" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-2">
      <label for="quantidade" class="form-label">Quantidade</label>
      <input type="number" class="form-control" id="quantidade" name="quantidade" min="1" value="1" disabled required>
    </div>
    <div class="col-md-6">
      <label for="detalhe" class="form-label">Detalhe</label>
      <input type="text" class="form-control" id="detalhe" name="detalhe" value="Reserva Adicional" disabled>
    </div>
<div class="col-md-6">
  <label for="dependente" class="form-label">Dependente</label>
  <div class="input-group">
    <select class="form-select" id="dependente" name="dependente">
      <option value="">Selecione um dependente</option>
    </select>
    <button type="button" class="btn btn-outline-primary" title="Novo Dependente"
              data-bs-toggle="modal" data-bs-target="#modalDependente"><i class="bi bi-person-plus"></i></button>
  </div>
</div>
<div class="col-md-4">
      <label for="tipo" class="form-label">Tipo</label>
      <select class="form-select" id="tipo" name="tipo" required>
        <option value="presencial">Presencial</option>
        <option value="marmitex">Marmitex</option>
      </select>
    </div>
    <div class="col-12">
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Adicionar Reserva</button>
        </div>
      </form>
    </div>
  </div>

<!-- Modal Novo Dependente -->
<div class="modal fade" id="modalDependente" tabindex="-1" aria-labelledby="modalDependenteLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <form id="formDependente">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDependenteLabel">Novo Dependente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label for="nome_dependente" class="form-label">Nome</label>
            <input type="text" class="form-control" id="nome_dependente" name="nome" required>
          </div>
          <div class="mb-3">
            <label for="parentesco_dependente" class="form-label">Parentesco</label>
            <input type="text" class="form-control" id="parentesco_dependente" name="parentesco" required>
          </div>
          <div class="mb-3">
            <label for="nascimento_dependente" class="form-label">Data de Nascimento</label>
            <input type="date" class="form-control" id="nascimento_dependente" name="nascimento_dependente" required>
          </div>
          <div class="mb-3">
            <label for="foto_dependente" class="form-label">Foto</label>
            <input type="file" class="form-control" id="foto_dependente" name="foto" accept="image/*">
            <div class="mt-3 text-center">
              <img id="previewFotoDependente" src="#" alt="Prévia da Foto" class="img-thumbnail d-none" style="max-width: 150px;">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary" >Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>

    </div>
  </div>
</div>

  <!-- Modal de Configurações -->
  <div class="modal fade" id="modalConfiguracoes" tabindex="-1" aria-labelledby="modalConfiguracoesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none;">
        <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 1.25rem 1.5rem;">
          <div>
            <h5 class="modal-title mb-0" id="modalConfiguracoesLabel">
              <i class="bi bi-gear-fill me-2"></i>Configurações
            </h5>
            <small class="opacity-75">Gerencie seu perfil e preferências</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-0">
          <!-- Navegação por abas estilizada -->
          <div class="config-tabs-container" style="background: #f8f9fa; border-bottom: 1px solid #e9ecef; padding: 0.75rem 1rem;">
            <ul class="nav nav-pills config-pills" id="configTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="perfil-tab" data-bs-toggle="tab" data-bs-target="#perfil" type="button" role="tab" aria-controls="perfil" aria-selected="true">
                  <i class="bi bi-person-circle me-1"></i>
                  <span>Meu Perfil</span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="dependentes-tab" data-bs-toggle="tab" data-bs-target="#dependentes" type="button" role="tab" aria-controls="dependentes" aria-selected="false">
                  <i class="bi bi-people-fill me-1"></i>
                  <span>Dependentes</span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="reservas-tab" data-bs-toggle="tab" data-bs-target="#reservas" type="button" role="tab" aria-controls="reservas" aria-selected="false">
                  <i class="bi bi-calendar-check-fill me-1"></i>
                  <span>Minhas Reservas</span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="reservas-adicionais-tab" data-bs-toggle="tab" data-bs-target="#reservas-adicionais" type="button" role="tab" aria-controls="reservas-adicionais" aria-selected="false">
                  <i class="bi bi-plus-circle-fill me-1"></i>
                  <span>Reservas Adicionais</span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="notificacoes-tab" data-bs-toggle="tab" data-bs-target="#notificacoes" type="button" role="tab" aria-controls="notificacoes" aria-selected="false">
                  <i class="bi bi-bell-fill me-1"></i>
                  <span>Notificações</span>
                </button>
              </li>
              <?php if ($isAdmin): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="dept-reservas-tab" data-bs-toggle="tab" data-bs-target="#dept-reservas" type="button" role="tab" aria-controls="dept-reservas" aria-selected="false">
                  <i class="bi bi-building me-1"></i>
                  <span>Reservas Dept.</span>
                </button>
              </li>
              <?php endif; ?>
            </ul>
          </div>
          <div class="tab-content p-4" id="configTabsContent">
            <div class="tab-pane fade" id="dependentes" role="tabpanel" aria-labelledby="dependentes-tab">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h5 class="mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Meus Dependentes</h5>
                  <small class="text-muted">Gerencie os dependentes vinculados à sua conta</small>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDependente" style="border-radius: 8px;">
                  <i class="bi bi-person-plus-fill me-1"></i>Novo Dependente
                </button>
              </div>
              <div class="table-responsive">
                <style>
                  @media (max-width: 767.98px) {
                    #tabelaDependentes {
                      font-size: 14px;
                    }
                    #tabelaDependentes .btn-group {
                      display: flex;
                      gap: 2px;
                    }
                    #tabelaDependentes .btn {
                      padding: 0.25rem 0.5rem;
                      font-size: 12px;
                    }

                    #tabelaDependentes td {
                      padding: 0.5rem 0.25rem;
                      vertical-align: middle;
                    }
                    #tabelaDependentes th {
                      padding: 0.5rem 0.25rem;
                      font-size: 12px;
                    }
                  }
                  @media (min-width: 768px) {
                    #tabelaDependentes .btn {
                      padding: 0.375rem 0.75rem;
                      font-size: 14px;
                    }
                  }
                </style>
                <table class="table table-hover" id="tabelaDependentes">
                  <thead>
                    <tr>
                      <th class="d-none d-md-table-cell">Nome</th>
                      <th class="d-table-cell d-md-none">Nome</th>
                      <th class="d-none d-lg-table-cell">Parentesco</th>
                      <th class="d-table-cell d-md-none">Data Nasc.</th>
                      <th class="d-none d-lg-table-cell">Data de Nascimento</th>
                      <th class="d-none d-xl-table-cell">Idade</th>
                      <th class="d-table-cell d-md-none">Foto</th>
                      <th class="d-none d-lg-table-cell">Foto</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Será preenchido via JavaScript -->
                  </tbody>
                </table>
              </div>
            </div>
            <div class="tab-pane fade show active" id="perfil" role="tabpanel" aria-labelledby="perfil-tab">
              <div class="mb-4">
                <h5 class="mb-1"><i class="bi bi-person-circle text-success me-2"></i>Meu Perfil</h5>
                <small class="text-muted">Atualize suas informações pessoais</small>
              </div>
              <form id="formPerfilUsuario" enctype="multipart/form-data">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="perfil_nome" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="perfil_nome" name="nome" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="perfil_email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="perfil_email" name="email" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="perfil_telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="perfil_telefone" name="telefone">
                  </div>
                  <div class="col-12 mb-3">
                    <label for="perfil_senha" class="form-label">Nova Senha</label>
                    <input type="password" class="form-control" id="perfil_senha" name="senha" placeholder="Deixe em branco para não alterar">
                  </div>
                  <div class="col-12 mb-3">
                    <label for="perfil_senha_confirma" class="form-label">Confirmar Nova Senha</label>
                    <input type="password" class="form-control" id="perfil_senha_confirma" name="senha_confirma" placeholder="Redigite a nova senha">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Grupo Valor</label>
                    <input type="text" class="form-control" id="perfil_id_valor" name="id_valor" readonly disabled>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Entidade</label>
                    <input type="text" class="form-control" id="perfil_entidade_id" name="entidade_id" readonly disabled>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="perfil_foto" class="form-label">Foto</label>
                    <input type="file" class="form-control" id="perfil_foto" name="foto" accept="image/*">
                    <div class="mt-2 text-center">
                      <img id="perfil_previewFoto" src="#" alt="Prévia da Foto" class="img-thumbnail d-none" style="max-width: 120px;">
                    </div>
                  </div>
                </div>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
              </form>
            </div>
            <div class="tab-pane fade" id="reservas" role="tabpanel" aria-labelledby="reservas-tab">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                  <h5 class="mb-1"><i class="bi bi-calendar-check-fill text-info me-2"></i>Minhas Reservas</h5>
                  <small class="text-muted">Histórico das suas reservas de almoço</small>
                </div>
                <form class="row g-2 align-items-end" id="formFiltroReservas">
                  <div class="col-auto">
                    <label for="filtroDataInicio" class="form-label mb-0">De</label>
                    <input type="date" class="form-control" id="filtroDataInicio" name="data_inicio">
                  </div>
                  <div class="col-auto">
                    <label for="filtroDataFim" class="form-label mb-0">Até</label>
                    <input type="date" class="form-control" id="filtroDataFim" name="data_fim">
                  </div>
                  <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                  </div>
                </form>
              </div>
              
              <!-- Card de Resumo -->
              <div class="row mb-3" id="resumoReservas">
                <div class="col-md-6">
                  <div class="card border-primary">
                    <div class="card-body text-center">
                      <h6 class="card-title text-primary">Quantidade de Reservas</h6>
                      <h3 class="mb-0" id="quantidadeReservas">-</h3>
                      <small class="text-muted">Período selecionado</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card border-success">
                    <div class="card-body text-center">
                      <h6 class="card-title text-success">Valor Total</h6>
                      <h3 class="mb-0" id="valorTotalReservas">R$ 0,00</h3>
                      <small class="text-muted">Período selecionado</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover" id="tabelaReservasUsuario">
                  <thead>
                    <tr>
                      <th>Data</th>
                      <th>Status</th>
                      <th>Valor</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Preenchido via JS -->
                  </tbody>
                </table>
              </div>
            </div>
            <div class="tab-pane fade" id="reservas-adicionais" role="tabpanel" aria-labelledby="reservas-adicionais-tab">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                  <h5 class="mb-1"><i class="bi bi-plus-circle-fill text-warning me-2"></i>Reservas Adicionais</h5>
                  <small class="text-muted">Reservas feitas para seus dependentes</small>
                </div>
                <form class="row g-2 align-items-end" id="formFiltroReservasAdicionais">
                  <div class="col-auto">
                    <label for="filtroDataInicioAdicionais" class="form-label mb-0">De</label>
                    <input type="date" class="form-control" id="filtroDataInicioAdicionais" name="data_inicio">
                  </div>
                  <div class="col-auto">
                    <label for="filtroDataFimAdicionais" class="form-label mb-0">Até</label>
                    <input type="date" class="form-control" id="filtroDataFimAdicionais" name="data_fim">
                  </div>
                  <div class="col-auto">
                    <select class="form-select" id="filtroDependenteAdicionais" name="dependente">
                      <option value="">Todos os dependentes</option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                  </div>
                </form>
              </div>
              
              <!-- Card de Resumo para Reservas Adicionais -->
              <div class="row mb-3" id="resumoReservasAdicionais">
                <div class="col-md-6">
                  <div class="card border-warning">
                    <div class="card-body text-center">
                      <h6 class="card-title text-warning">Quantidade de Reservas</h6>
                      <h3 class="mb-0" id="quantidadeReservasAdicionais">-</h3>
                      <small class="text-muted">Período selecionado</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card border-info">
                    <div class="card-body text-center">
                      <h6 class="card-title text-info">Valor Total</h6>
                      <h3 class="mb-0" id="valorTotalReservasAdicionais">R$ 0,00</h3>
                      <small class="text-muted">Período selecionado</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover" id="tabelaReservasAdicionaisUsuario">
                  <thead>
                    <tr>
                      <th class="d-table-cell d-md-table-cell">Data</th>
                      <th class="d-table-cell d-md-table-cell">Nome</th>
                      <th class="d-none d-md-table-cell">Dependente</th>
                      <th class="d-none d-md-table-cell">Tipo</th>
                      <th class="d-table-cell d-md-table-cell">Valor</th>
                      <!-- <th class="d-none d-md-table-cell">Valor Marmitex</th> -->
                      <th class="d-none d-md-table-cell">Status</th>
                      <th class="d-table-cell d-md-table-cell">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Preenchido via JS -->
                  </tbody>
                </table>
              </div>
            </div>
            <?php if ($isAdmin): ?>
            <div class="tab-pane fade" id="dept-reservas" role="tabpanel" aria-labelledby="dept-reservas-tab">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                  <h5 class="mb-1"><i class="bi bi-building text-secondary me-2"></i>Reservas de Departamento</h5>
                  <small class="text-muted">Gerenciamento de reservas por departamento</small>
                </div>
                <form class="row g-2 align-items-end" id="formFiltroDeptReservas">
                  <div class="col-auto">
                    <label for="dept_filtroDataInicio" class="form-label mb-0">De</label>
                    <input type="date" class="form-control" id="dept_filtroDataInicio" name="data_inicio">
                  </div>
                  <div class="col-auto">
                    <label for="dept_filtroDataFim" class="form-label mb-0">Até</label>
                    <input type="date" class="form-control" id="dept_filtroDataFim" name="data_fim">
                  </div>
                  <div class="col-auto">
                    <select class="form-select" id="dept_filtroDepartamento" name="entidade_id">
                      <option value="">Todos os departamentos</option>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                  </div>
                  <div class="col-auto">
                    <button type="button" class="btn btn-danger" onclick="exportarPdfDepartamento()">
                      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </button>
                  </div>
                </form>
              </div>
              <div class="table-responsive">
                <table class="table table-hover" id="tabelaDeptReservas">
                  <thead>
                    <tr>
                      <th>Data</th>
                      <th>Departamento</th>
                      <th>Evento/Motivo</th>
                      <th>Quantidade</th>
                      <th>Valor Total</th>
                      <th>Criado por</th>
                      <th>Status</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Preenchido via JS -->
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>
            <div class="tab-pane fade" id="notificacoes" role="tabpanel" aria-labelledby="notificacoes-tab">
              <div class="mb-4">
                <h5 class="mb-1"><i class="bi bi-bell-fill text-danger me-2"></i>Configurações de Notificações</h5>
                <small class="text-muted">Escolha os tipos de notificações que você deseja receber</small>
              </div>
              
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="card h-100 notif-card" style="border-radius: 12px; border: 2px solid #e9ecef; transition: all 0.2s;">
                    <div class="card-body">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_propria" style="width: 3em; height: 1.5em;">
                        <label class="form-check-label ms-2" for="notif_propria">
                          <div class="d-flex align-items-center mb-1">
                            <span style="font-size: 1.5rem; margin-right: 8px;">📧</span>
                            <strong>Reserva Própria</strong>
                          </div>
                          <small class="text-muted">Receba notificação ao fazer reserva para você</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="card h-100 notif-card" style="border-radius: 12px; border: 2px solid #e9ecef; transition: all 0.2s;">
                    <div class="card-body">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_adicional" style="width: 3em; height: 1.5em;">
                        <label class="form-check-label ms-2" for="notif_adicional">
                          <div class="d-flex align-items-center mb-1">
                            <span style="font-size: 1.5rem; margin-right: 8px;">👤</span>
                            <strong>Reserva Adicional</strong>
                          </div>
                          <small class="text-muted">Receba notificação ao reservar para dependente</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="card h-100 notif-card" style="border-radius: 12px; border: 2px solid #e9ecef; transition: all 0.2s;">
                    <div class="card-body">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_multipla" style="width: 3em; height: 1.5em;">
                        <label class="form-check-label ms-2" for="notif_multipla">
                          <div class="d-flex align-items-center mb-1">
                            <span style="font-size: 1.5rem; margin-right: 8px;">📅</span>
                            <strong>Reservas Múltiplas</strong>
                          </div>
                          <small class="text-muted">Receba notificação ao reservar vários dias</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="card h-100 notif-card" style="border-radius: 12px; border: 2px solid #e9ecef; transition: all 0.2s;">
                    <div class="card-body">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_cancelada" style="width: 3em; height: 1.5em;">
                        <label class="form-check-label ms-2" for="notif_cancelada">
                          <div class="d-flex align-items-center mb-1">
                            <span style="font-size: 1.5rem; margin-right: 8px;">❌</span>
                            <strong>Cancelamento</strong>
                          </div>
                          <small class="text-muted">Receba notificação ao cancelar reserva</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="card h-100 notif-card" style="border-radius: 12px; border: 2px solid #e9ecef; transition: all 0.2s;">
                    <div class="card-body">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_lembrete_diario" style="width: 3em; height: 1.5em;">
                        <label class="form-check-label ms-2" for="notif_lembrete_diario">
                          <div class="d-flex align-items-center mb-1">
                            <span style="font-size: 1.5rem; margin-right: 8px;">📧</span>
                            <strong>Lembrete Diário</strong>
                          </div>
                          <small class="text-muted">Receba lembrete diário quando não fizer reserva</small>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="alert alert-success mt-4" style="border-radius: 12px; border-left: 4px solid #28a745;">
                <div class="d-flex align-items-start">
                  <i class="bi bi-whatsapp me-3" style="font-size: 1.5rem; color: #25D366;"></i>
                  <div>
                    <strong>Como funciona:</strong><br>
                    <small>Se você tiver telefone cadastrado, receberá por WhatsApp. Caso contrário (ou se o envio por WhatsApp falhar), receberá por email.</small>
                  </div>
                </div>
              </div>
              
              <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-success btn-lg" onclick="salvarConfiguracaoNotificacoes()" style="border-radius: 10px;">
                  <i class="bi bi-check2-circle me-2"></i>Salvar Configurações
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Edição de Dependente -->
  <div class="modal fade" id="modalEditarDependente" tabindex="-1" aria-labelledby="modalEditarDependenteLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formEditarDependente">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditarDependenteLabel">Editar Dependente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="edit_id_dependente" name="id">
            <div class="mb-3">
              <label for="edit_nome_dependente" class="form-label">Nome</label>
              <input type="text" class="form-control" id="edit_nome_dependente" name="nome" required>
            </div>
            <div class="mb-3">
              <label for="edit_parentesco_dependente" class="form-label">Parentesco</label>
              <input type="text" class="form-control" id="edit_parentesco_dependente" name="parentesco" required>
            </div>
            <div class="mb-3">
              <label for="edit_nascimento_dependente" class="form-label">Data de Nascimento</label>
              <input type="date" class="form-control" id="edit_nascimento_dependente" name="nascimento_dependente" required>
            </div>
            <div class="mb-3">
              <label for="edit_foto_dependente" class="form-label">Foto</label>
              <input type="file" class="form-control" id="edit_foto_dependente" name="foto" accept="image/*">
              <div class="mt-3 text-center">
                <img id="edit_previewFotoDependente" src="#" alt="Prévia da Foto" class="img-thumbnail" style="max-width: 150px;">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Confirmação - Reserva Fora do Horário -->
  <div class="modal fade" id="modalConfirmacaoForaHorario" tabindex="-1" aria-labelledby="modalConfirmacaoForaHorarioLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalConfirmacaoForaHorarioLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>ATENÇÃO: Reserva Fora do Horário
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="bi bi-clock-fill me-2"></i>
            <strong>Você está reservando fora do horário limite!</strong>
          </div>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Horário Limite:</strong> <span id="horarioLimite"></span></p>
              <p><strong>Horário Atual:</strong> <span id="horarioAtual"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Valor da Refeição:</strong> R$ <span id="valorForaHorario"></span></p>
              <p><strong>Status:</strong> <span class="badge bg-warning text-dark">Fora do Horário</span></p>
            </div>
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Importante:</strong> Esta ação não pode ser desfeita após a confirmação.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Cancelar
          </button>
          <button type="button" class="btn btn-warning" id="btnConfirmarForaHorario">
            <i class="bi bi-check-circle me-1"></i>Confirmar Reserva
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Confirmação - Reserva Adicional Fora do Horário -->
  <div class="modal fade" id="modalConfirmacaoForaHorarioAdicional" tabindex="-1" aria-labelledby="modalConfirmacaoForaHorarioAdicionalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalConfirmacaoForaHorarioAdicionalLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>ATENÇÃO: Reserva Adicional Fora do Horário
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="bi bi-clock-fill me-2"></i>
            <strong>Você está reservando uma refeição adicional fora do horário limite!</strong>
          </div>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Horário Limite:</strong> <span id="horarioLimiteAdicional"></span></p>
              <p><strong>Horário Atual:</strong> <span id="horarioAtualAdicional"></span></p>
              <p><strong>Dependente:</strong> <span id="dependenteNomeAdicional"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Tipo:</strong> <span id="tipoAdicional"></span></p>
              <p><strong>Valor Refeição:</strong> R$ <span id="valorRefeicaoAdicional"></span></p>
              <p><strong>Valor Marmitex:</strong> R$ <span id="valorMarmitexAdicional"></span></p>
              <p><strong>Status:</strong> <span class="badge bg-warning text-dark">Fora do Horário</span></p>
            </div>
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Importante:</strong> Esta ação não pode ser desfeita após a confirmação.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Cancelar
          </button>
          <button type="button" class="btn btn-warning" id="btnConfirmarForaHorarioAdicional">
            <i class="bi bi-check-circle me-1"></i>Confirmar Reserva Adicional
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Confirmação - Reserva Departamento Fora do Horário -->
  <div class="modal fade" id="modalDeptConfirmacaoForaHorario" tabindex="-1" aria-labelledby="modalDeptConfirmacaoForaHorarioLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalDeptConfirmacaoForaHorarioLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ATENÇÃO: Reserva de Departamento Fora do Horário
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="bi bi-clock-fill me-2"></i>
            <strong>Você está reservando para um departamento fora do horário limite!</strong>
          </div>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Horário Limite:</strong> <span id="dept_horarioLimite"></span></p>
              <p><strong>Horário Atual:</strong> <span id="dept_horarioAtual"></span></p>
              <p><strong>Departamento:</strong> <span id="dept_entidadeNome"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Evento/Motivo:</strong> <span id="dept_eventoMotivo"></span></p>
              <p><strong>Quantidade:</strong> <span id="dept_quantidadeRefeicoes"></span> refeições</p>
              <p><strong>Valor Unitário:</strong> R$ <span id="dept_valorUnitario"></span></p>
              <p><strong>Valor Total:</strong> R$ <span id="dept_valorTotal"></span></p>
            </div>
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Importante:</strong> Esta ação não pode ser desfeita após a confirmação.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Cancelar
          </button>
          <button type="button" class="btn btn-warning" id="dept_btnConfirmarForaHorario">
            <i class="bi bi-check-circle me-1"></i>Confirmar Reserva
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="mensagemAdicional" class="mt-3"></div>
  <hr>

  <!-- Tabela de reservas adicionais -->
  <h5 class="mt-4">Minhas reservas adicionais</h5>
  <div id="listaReservasAdicionais"></div>

  <footer class="mt-5 text-center text-muted small">
    &copy; <?php echo date('Y'); ?> Sistema de Presença - Desenvolvido por Tiago Linhares
  </footer>
</div>

<!-- Modal de Confirmação Personalizado -->
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalConfirmacaoLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Confirmar Ação
                </h5>
                <button type="button" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-question-circle-fill text-warning" style="font-size: 2rem;"></i>
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
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAcao">
                    <i class="bi bi-check-circle me-1"></i>
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="../js/feedback-system.js?v=<?php echo time(); ?>"></script>
<script src="../js/editar_reserva_adicional.js?v=<?php echo time(); ?>"></script>
<script src="../js/almoco.js?v=<?php echo time(); ?>&fix=9"></script>
<script src="../js/dept_almoco.js?v=<?php echo time(); ?>"></script>
<style>
/* ========================================
   ESTILOS DO MODAL DE CONFIGURAÇÕES
   ======================================== */

/* Container das abas */
.config-tabs-container {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* Abas estilizadas */
.config-pills {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.5rem;
  min-width: max-content;
}

.config-pills .nav-link {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.6rem 1rem;
  border-radius: 10px;
  color: #495057;
  background: white;
  border: 1px solid #dee2e6;
  font-weight: 500;
  font-size: 0.9rem;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.config-pills .nav-link:hover {
  background: #e9ecef;
  color: #212529;
  transform: translateY(-1px);
}

.config-pills .nav-link.active {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.config-pills .nav-link i {
  font-size: 1rem;
}

/* Cards de notificação */
.notif-card:hover {
  border-color: #28a745 !important;
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
}

.notif-card .form-check-input:checked ~ .form-check-label {
  color: #28a745;
}

/* Cards de resumo melhorados */
#resumoReservas .card,
#resumoReservasAdicionais .card {
  border-radius: 12px;
}

/* Responsividade mobile para modal */
@media (max-width: 767.98px) {
  .config-pills .nav-link {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
  }
  
  .config-pills .nav-link span {
    display: none;
  }
  
  .config-pills .nav-link i {
    font-size: 1.2rem;
    margin: 0;
  }
  
  #configTabsContent h5 {
    font-size: 1.1rem;
  }
  
  #modalConfiguracoes .modal-body {
    padding: 0 !important;
  }
  
  #configTabsContent {
    padding: 1rem !important;
  }
}

/* ========================================
   FIM ESTILOS DO MODAL DE CONFIGURAÇÕES
   ======================================== */

.card-hover:hover {
  box-shadow: 0 0 0 4px #0d6efd33, 0 4px 24px rgba(0,0,0,0.10);
  border-color: #0d6efd !important;
  transform: translateY(-2px) scale(1.03);
  transition: all 0.2s;
}

/* Estilo para a miniatura da foto de perfil */
.foto-perfil-mini {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background-color: #f8f9fa;
  border: 2px solid #dee2e6;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}

.foto-perfil-mini img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.foto-perfil-mini i {
  font-size: 12px;
  color: #6c757d;
}

/* Garantir que todos os botões do header tenham o mesmo tamanho */
.header-refeicoes .btn {
  height: 31px;
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
  line-height: 1.5;
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}

/* Hover effect para o botão */
#btnConfiguracoes:hover .foto-perfil-mini {
  border-color: #0d6efd;
  transform: scale(1.05);
  transition: all 0.2s ease;
}

/* Estilos responsivos para tabela de reservas adicionais */
@media (max-width: 767.98px) {
  #tabelaReservasAdicionaisUsuario {
    font-size: 14px;
  }
  
  #tabelaReservasAdicionaisUsuario th,
  #tabelaReservasAdicionaisUsuario td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
  }
  
  #tabelaReservasAdicionaisUsuario .btn {
    padding: 0.25rem 0.5rem;
    font-size: 12px;
  }
  
  #tabelaReservasAdicionaisUsuario .btn i {
    margin-right: 0;
  }
}

/* Estilos para cards de resumo */
#resumoReservas .card,
#resumoReservasAdicionais .card {
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

#resumoReservas .card:hover,
#resumoReservasAdicionais .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

#resumoReservas .card-title,
#resumoReservasAdicionais .card-title {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

#resumoReservas h3,
#resumoReservasAdicionais h3 {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

#resumoReservas .text-muted,
#resumoReservasAdicionais .text-muted {
  font-size: 0.8rem;
}

/* Responsividade para cards de resumo */
@media (max-width: 767.98px) {
  #resumoReservas h3,
  #resumoReservasAdicionais h3 {
    font-size: 1.5rem;
  }
  
  #resumoReservas .card-title,
  #resumoReservasAdicionais .card-title {
    font-size: 0.8rem;
  }
}
</style>
</body>
</html>