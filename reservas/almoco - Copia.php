<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Verificação de login
include_once(__DIR__ . '/../auth/verifica_sessao.php');

include_once(__DIR__ . '/../utils/config.php');

// Variáveis de sessão
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

// Mensagem inicial do sistema
$mensagem_inicio = get_config('mensagem_inicio', '');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Reserva de Almoço</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/feedback-system.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">

  <!-- Mensagem inicial do sistema -->
  <?php if (!empty($mensagem_inicio)): ?>
    <div class="alert alert-info text-center">
      <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($mensagem_inicio) ?>
    </div>
  <?php endif; ?>

  <!-- Botão de voltar (somente admin) -->
  <?php if ($isAdmin): ?>
    <div class="mb-4">
      <a href="../painel/dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar para o Painel
      </a>
    </div>
  <?php endif; ?>
  <div class="d-flex justify-content-end mb-3">
    <a href="../logout.php" class="btn btn-outline-danger"><i class="bi bi-power"></i> Sair</a>
    <button class="btn btn-outline-primary ms-2 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalConfiguracoes" id="btnConfiguracoes">
      <div class="d-flex align-items-center">
        <div class="foto-perfil-mini me-2" id="fotoPerfilMini">
          <i class="bi bi-person-fill text-muted"></i>
        </div>
        <i class="bi bi-gear"></i>
      </div>
    </button>
  </div>
  <h2 class="mb-4 text-center">Reserva de Almoço</h2>

  <!-- Card: Reserva própria -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body text-center">
      <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-2 mb-3">
        <button id="btnReservaPropria" class="btn btn-success btn-lg px-5">
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
  <div class="card mb-4 shadow-sm border-warning">
    <div class="card-header bg-warning text-dark">
      <h5 class="mb-0">
        <i class="bi bi-building"></i> Reservas de Departamento
        <span class="badge bg-dark ms-2">Administrador</span>
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
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Reservas Adicionais</h5>
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
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalConfiguracoesLabel">Configurações</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="perfil-tab" data-bs-toggle="tab" data-bs-target="#perfil" type="button" role="tab" aria-controls="perfil" aria-selected="true">
                <i class="bi bi-person d-inline d-md-none"></i>
                <span class="d-none d-md-inline">Meu Perfil</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="dependentes-tab" data-bs-toggle="tab" data-bs-target="#dependentes" type="button" role="tab" aria-controls="dependentes" aria-selected="false">
                <i class="bi bi-people d-inline d-md-none"></i>
                <span class="d-none d-md-inline">Dependentes</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="reservas-tab" data-bs-toggle="tab" data-bs-target="#reservas" type="button" role="tab" aria-controls="reservas" aria-selected="false">
                <i class="bi bi-calendar-check d-inline d-md-none"></i>
                <span class="d-none d-md-inline">Minhas Reservas</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="reservas-adicionais-tab" data-bs-toggle="tab" data-bs-target="#reservas-adicionais" type="button" role="tab" aria-controls="reservas-adicionais" aria-selected="false">
                <i class="bi bi-plus-circle d-inline d-md-none"></i>
                <span class="d-none d-md-inline">Reservas Adicionais</span>
              </button>
            </li>
            <?php if ($isAdmin): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="dept-reservas-tab" data-bs-toggle="tab" data-bs-target="#dept-reservas" type="button" role="tab" aria-controls="dept-reservas" aria-selected="false">
                <i class="bi bi-building d-inline d-md-none"></i>
                <span class="d-none d-md-inline">Reservas Departamento</span>
              </button>
            </li>
            <?php endif; ?>
          </ul>
          <div class="tab-content mt-3" id="configTabsContent">
            <div class="tab-pane fade" id="dependentes" role="tabpanel" aria-labelledby="dependentes-tab">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Meus Dependentes</h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDependente">
                  + Novo Dependente
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
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Minhas Reservas</h6>
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
              <div class="d-flex justify-content-between align-items-center mb-3">
                
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
              <div class="table-responsive">
                <table class="table table-hover" id="tabelaReservasAdicionaisUsuario">
                  <thead>
                    <tr>
                      <th class="d-table-cell d-md-table-cell">Data</th>
                      <th class="d-table-cell d-md-table-cell">Nome</th>
                      <th class="d-none d-md-table-cell">Dependente</th>
                      <th class="d-none d-md-table-cell">Tipo</th>
                      <th class="d-table-cell d-md-table-cell">Valor</th>
                      <th class="d-none d-md-table-cell">Valor Marmitex</th>
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
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Reservas de Departamento</h6>
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
<script src="../js/almoco.js?v=<?php echo time(); ?>&fix=9"></script>
<script src="../js/dept_almoco.js?v=<?php echo time(); ?>"></script>
<style>
.card-hover:hover {
  box-shadow: 0 0 0 4px #0d6efd33, 0 4px 24px rgba(0,0,0,0.10);
  border-color: #0d6efd !important;
  transform: translateY(-2px) scale(1.03);
  transition: all 0.2s;
}

/* Estilo para a miniatura da foto de perfil */
.foto-perfil-mini {
  width: 32px;
  height: 32px;
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
  font-size: 16px;
  color: #6c757d;
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

/* Estilos responsivos para as abas do modal */
@media (max-width: 767.98px) {
  #configTabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
  }
  
  #configTabs .nav-item {
    flex: 0 0 auto;
    min-width: 60px;
  }
  
  #configTabs .nav-link {
    padding: 0.75rem 0.5rem;
    text-align: center;
    border-radius: 0.375rem 0.375rem 0 0;
    font-size: 14px;
  }
  
  #configTabs .nav-link i {
    font-size: 18px;
    display: block;
    margin-bottom: 0.25rem;
  }
  
  #configTabs .nav-link.active {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
  }
  
  #configTabs .nav-link:not(.active) {
    background-color: #f8f9fa;
    color: #6c757d;
    border-color: #dee2e6;
  }
  
  #configTabs .nav-link:hover:not(.active) {
    background-color: #e9ecef;
    color: #495057;
  }
}
</style>
</body>
</html>