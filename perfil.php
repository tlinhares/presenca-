<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Verificação de login
include_once(__DIR__ . '/auth/verifica_sessao.php');

include_once(__DIR__ . '/utils/config.php');

// Variáveis de sessão
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$id_usuario = $_SESSION['usuario_id'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Meu Perfil - Sistema de Presença</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
  <link href="css/feedback-system.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">

  <!-- Botão de voltar -->
  <div class="mb-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Voltar para o Dashboard
    </a>
  </div>

  <div class="d-flex justify-content-end mb-3">
    <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-power"></i> Sair</a>
  </div>

  <h2 class="mb-4 text-center">
    <i class="bi bi-person-circle me-2"></i>Meu Perfil
  </h2>

  <!-- Card: Informações Pessoais -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">
        <i class="bi bi-person me-2"></i>Informações Pessoais
      </h5>
    </div>
    <div class="card-body">
      <form id="formPerfilBasico">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="nome" class="form-label">Nome Completo</label>
            <input type="text" class="form-control" id="nome" name="nome" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Categoria</label>
            <input type="text" class="form-control" value="<?= $isAdmin ? 'Administrador' : 'Usuário' ?>" readonly>
          </div>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card: Alterar Senha -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-warning text-dark">
      <h5 class="mb-0">
        <i class="bi bi-shield-lock me-2"></i>Alterar Senha
      </h5>
    </div>
    <div class="card-body">
      <form id="formAlterarSenha">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="senha_atual" class="form-label">Senha Atual</label>
            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="nova_senha" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
          </div>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-key me-1"></i>Alterar Senha
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card: Foto do Perfil -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0">
        <i class="bi bi-camera me-2"></i>Foto do Perfil
      </h5>
    </div>
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-4 text-center">
          <div class="foto-perfil-grande mb-3" id="fotoPerfilGrande">
            <i class="bi bi-person-fill text-muted"></i>
          </div>
          <input type="file" class="form-control" id="foto_perfil" name="foto" accept="image/*">
        </div>
        <div class="col-md-8">
          <p class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Selecione uma nova foto para seu perfil. Formatos aceitos: JPG, PNG, GIF. 
            Tamanho máximo: 2MB.
          </p>
          <button type="button" class="btn btn-info" id="btnAtualizarFoto">
            <i class="bi bi-upload me-1"></i>Atualizar Foto
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Estatísticas do Usuário -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0">
        <i class="bi bi-graph-up me-2"></i>Minhas Estatísticas
      </h5>
    </div>
    <div class="card-body">
      <div class="row text-center">
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body">
              <h4 class="text-primary" id="totalPresencasCulto">-</h4>
              <p class="text-muted mb-0">Presenças no Culto</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body">
              <h4 class="text-success" id="totalReservasAlmoco">-</h4>
              <p class="text-muted mb-0">Reservas de Almoço</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body">
              <h4 class="text-info" id="totalDependentes">-</h4>
              <p class="text-muted mb-0">Dependentes</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body">
              <h4 class="text-warning" id="frequenciaCulto">-</h4>
              <p class="text-muted mb-0">Frequência Culto</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Ações Rápidas -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0">
        <i class="bi bi-lightning me-2"></i>Ações Rápidas
      </h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <a href="culto/historico.php" class="btn btn-outline-primary w-100">
            <i class="bi bi-clock-history me-2"></i>Histórico de Presenças
          </a>
        </div>
        <div class="col-md-6 mb-3">
          <a href="reservas/almoco.php" class="btn btn-outline-success w-100">
            <i class="bi bi-egg-fried me-2"></i>Reservar Almoço
          </a>
        </div>
        
        <?php if ($isAdmin): ?>
        <div class="col-md-6 mb-3">
          <a href="culto/admin.php" class="btn btn-outline-info w-100">
            <i class="bi bi-shield-check me-2"></i>Administrar Culto
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="js/feedback-system.js"></script>
<script src="js/perfil.js"></script>

<style>
.foto-perfil-grande {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background-color: #f8f9fa;
  border: 3px solid #dee2e6;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin: 0 auto;
  position: relative;
  min-height: 120px;
}

.foto-perfil-grande img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
  border-radius: 50% !important;
  display: block !important;
  position: absolute;
  top: 0;
  left: 0;
}

.foto-perfil-grande i {
  font-size: 48px;
  color: #6c757d;
  display: block;
  z-index: 1;
}

.card-hover:hover {
  box-shadow: 0 0 0 4px #0d6efd33, 0 4px 24px rgba(0,0,0,0.10);
  border-color: #0d6efd !important;
  transform: translateY(-2px) scale(1.02);
  transition: all 0.2s;
}
</style>

</body>
</html>
