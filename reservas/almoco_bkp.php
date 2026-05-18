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
    <button class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalConfiguracoes">
      <i class="bi bi-gear"></i> Configurações
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
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formReservaMultipla">
          <div class="modal-header">
            <h5 class="modal-title" id="modalReservaMultiplaLabel">Reservar para vários dias</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="data_inicio" class="form-label">Data inicial</label>
              <input type="date" class="form-control" id="data_inicio" name="data_inicio" required>
            </div>
            <div class="mb-3">
              <label for="data_fim" class="form-label">Data final</label>
              <input type="date" class="form-control" id="data_fim" name="data_fim" required>
            </div>
            <div class="alert alert-info small">
              Serão reservados apenas dias úteis (segunda a sexta-feira) dentro do intervalo selecionado.
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check"></i> Reservar Dias</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
</div>

  <!-- Card: Reservas adicionais -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Reservas Adicionais</h5>
    </div>
    <div class="card-body">
  <form id="formReservaAdicional" class="row g-3">
    <div class="col-md-4">
      <label for="data" class="form-label">Data</label>
      <input type="date" class="form-control" id="data" name="data" required>
    </div>
    <div class="col-md-2">
      <label for="quantidade" class="form-label">Quantidade</label>
      <input type="number" class="form-control" id="quantidade" name="quantidade" min="1" required>
    </div>
    <div class="col-md-6">
      <label for="detalhe" class="form-label">Detalhe</label>
      <input type="text" class="form-control" id="detalhe" name="detalhe">
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
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalConfiguracoesLabel">Configurações</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="dependentes-tab" data-bs-toggle="tab" data-bs-target="#dependentes" type="button" role="tab" aria-controls="dependentes" aria-selected="true">Dependentes</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="perfil-tab" data-bs-toggle="tab" data-bs-target="#perfil" type="button" role="tab" aria-controls="perfil" aria-selected="false">Meu Perfil</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="reservas-tab" data-bs-toggle="tab" data-bs-target="#reservas" type="button" role="tab" aria-controls="reservas" aria-selected="false">Minhas Reservas</button>
            </li>
          </ul>
          <div class="tab-content mt-3" id="configTabsContent">
            <div class="tab-pane fade show active" id="dependentes" role="tabpanel" aria-labelledby="dependentes-tab">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Meus Dependentes</h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDependente">
                  + Novo Dependente
                </button>
              </div>
              <div class="table-responsive">
                <table class="table table-hover" id="tabelaDependentes">
                  <thead>
                    <tr>
                      <th>Nome</th>
                      <th>Parentesco</th>
                      <th>Data de Nascimento</th>
                      <th>Idade</th>
                      <th>Foto</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Será preenchido via JavaScript -->
                  </tbody>
                </table>
              </div>
            </div>
            <div class="tab-pane fade" id="perfil" role="tabpanel" aria-labelledby="perfil-tab">
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

  <div id="mensagemAdicional" class="mt-3"></div>
  <hr>

  <!-- Tabela de reservas adicionais -->
  <h5 class="mt-4">Minhas reservas adicionais</h5>
  <div id="listaReservasAdicionais"></div>

  <footer class="mt-5 text-center text-muted small">
    &copy; <?php echo date('Y'); ?> Sistema de Presença - Desenvolvido por Tiago Linhares
  </footer>
</div>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toastContainer"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="../js/almoco.js"></script>
<style>
.card-hover:hover {
  box-shadow: 0 0 0 4px #0d6efd33, 0 4px 24px rgba(0,0,0,0.10);
  border-color: #0d6efd !important;
  transform: translateY(-2px) scale(1.03);
  transition: all 0.2s;
}
</style>
</body>
</html>