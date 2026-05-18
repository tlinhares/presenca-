<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Verificação básica de sessão
if (!isset($_SESSION['usuario_id'])) {
    echo "Erro: Usuário não logado";
    exit;
}

$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Teste</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
  <h2>Dashboard - Teste</h2>
  <p>Usuário: <?= htmlspecialchars($nome_usuario) ?></p>
  <p>Categoria: <?= $isAdmin ? 'Admin' : 'Usuário' ?></p>

  <div class="row">
    <!-- Card: Presença de Culto -->
    <div class="col-md-6 mb-4">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center d-flex flex-column">
          <h5 class="card-title">
            <i class="bi bi-church me-2 text-primary"></i>Presença de Culto
          </h5>
          <p class="card-text flex-grow-1">Confirme sua presença no culto de hoje</p>
          <div class="mt-auto">
            <a href="culto/presenca.php" class="btn btn-primary btn-lg w-100">
              <i class="bi bi-person-check me-2"></i>Confirmar Presença
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Reservas de Almoço -->
    <div class="col-md-6 mb-4">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center d-flex flex-column">
          <h5 class="card-title">
            <i class="bi bi-egg-fried me-2 text-success"></i>Reservas de Almoço
          </h5>
          <p class="card-text flex-grow-1">Reserve sua refeição</p>
          <div class="mt-auto">
            <a href="reservas/almoco.php" class="btn btn-success btn-lg w-100">
              <i class="bi bi-calendar-plus me-2"></i>Reservar Almoço
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <!-- Card: Administrar Culto (apenas para admin) -->
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100 border-warning">
          <div class="card-body text-center d-flex flex-column">
            <h5 class="card-title text-warning">
              <i class="bi bi-shield-check me-2"></i>Administrar Culto
            </h5>
            <p class="card-text flex-grow-1">Painel administrativo do culto</p>
            <div class="mt-auto">
              <a href="painel/culto_admin.php" class="btn btn-warning btn-lg w-100">
                <i class="bi bi-gear-fill me-2"></i>Administrar
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="mt-4">
    <a href="logout.php" class="btn btn-outline-danger">Sair</a>
  </div>
</div>

</body>
</html>

