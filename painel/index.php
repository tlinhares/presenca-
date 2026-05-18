<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: painel_index                                           ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('painel_index');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel Administrativo</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <h3 class="mb-4">Painel Administrativo</h3>

  <div class="row g-4">
    <div class="col-sm-6 col-lg-4">
      <a href="dashboard.php" class="text-decoration-none">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title">📊 Dashboard</h5>
            <p class="card-text text-muted">Resumo geral das refeições.</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
      <a href="usuarios.php" class="text-decoration-none">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title">👥 Usuários</h5>
            <p class="card-text text-muted">Gerenciar membros do sistema.</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-sm-6 col-lg-4">
  <a href="configuracoes.php" class="text-decoration-none">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <h5 class="card-title">⚙️ Configurações</h5>
        <p class="card-text text-muted">Gerenciar horário e valores.</p>
      </div>
      </div>
    </a>
  </div>

    <div class="col-sm-6 col-lg-4">
      <a href="../reservas/almoco.php" class="text-decoration-none">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title">🍽️ Refeições</h5>
            <p class="card-text text-muted">Ir para agendamento de almoço.</p>
          </div>
        </div>
      </a>
    </div>


  <div class="col-sm-6 col-lg-4">
  <a href="presenca_facial.php" class="text-decoration-none">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <h5 class="card-title">👤 Reconhecimento Facial</h5>
        <p class="card-text text-muted">Gerenciar sincronização com o dispositivo facial.</p>
      </div>
    </div>
  </a>
</div>

<div class="col-sm-6 col-lg-4">
  <a href="config_facial.php" class="text-decoration-none">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <h5 class="card-title">🔌 Config. Dispositivo</h5>
        <p class="card-text text-muted">Configurar dispositivo de reconhecimento facial.</p>
      </div>
    </div>
  </a>
</div>


<div class="col-sm-6 col-lg-4">
      <a href="notificar_usuarios.php" class="text-decoration-none">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title">👥Notificar Usuários</h5>
            <p class="card-text text-muted">Gerenciar membros do sistema.</p>
          </div>
        </div>
      </a>
    </div>

<div class="col-sm-6 col-lg-4">
      <a href="../logout.php" class="text-decoration-none">
        <div class="card border-danger shadow-sm h-100">
          <div class="card-body text-center">
            <h5 class="card-title text-danger">⏹️ Sair</h5>
            <p class="card-text text-muted">Encerrar sessão.</p>
          </div>
        </div>
      </a>
    </div>


  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
