<?php
// Relatório de usuários sem CPF cadastrado
// Não requer autenticação - acesso público para administradores

include_once 'api/conexao.php';

// Buscar usuários sem CPF (apenas ativos)
$sql = "SELECT id, nome, email, categoria, ativo, ultimo_login 
        FROM usuarios 
        WHERE (cpf IS NULL OR cpf = '' OR cpf = '0' OR cpf = '000.000.000-00') 
        AND ativo = 1
        ORDER BY nome ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Erro na consulta: " . $conn->error);
}

$usuarios_sem_cpf = [];
while ($row = $result->fetch_assoc()) {
    $usuarios_sem_cpf[] = $row;
}

$total_usuarios = count($usuarios_sem_cpf);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório - Usuários sem CPF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-report {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table th {
            background: #dc3545;
            color: white;
            border: none;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .badge-status {
            font-size: 0.8em;
        }
        .btn-export {
            background: #dc3545;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .btn-export:hover {
            background: #c82333;
            color: white;
        }
        .alert-warning {
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Cabeçalho do Relatório -->
                <div class="header-report">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Relatório de Usuários Ativos sem CPF
                            </h1>
                            <p class="mb-0 mt-2">Usuários ativos que não possuem CPF cadastrado no sistema</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h2 mb-0"><?php echo $total_usuarios; ?></div>
                            <small>usuários encontrados</small>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="stats-card">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Usuários Ativos sem CPF:</strong><br>
                            <span class="h4 text-danger"><?php echo $total_usuarios; ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Status:</strong><br>
                            <span class="h4 text-success">Todos Ativos</span>
                        </div>
                        <div class="col-md-4">
                            <strong>Gerado em:</strong><br>
                            <span class="h6"><?php echo date('d/m/Y H:i:s'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="mb-3">
                    <a href="api/relatorios/exportar_usuarios_sem_cpf.php" class="btn-export">
                        <i class="bi bi-download me-2"></i>Exportar CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i>Imprimir
                    </button>
                </div>

                <?php if ($total_usuarios > 0): ?>
                    <!-- Alerta de Atenção -->
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Atenção!</strong> Existem <?php echo $total_usuarios; ?> usuário(s) ativo(s) sem CPF cadastrado. 
                        Estes usuários não poderão ser integrados com a API de funcionários.
                    </div>

                    <!-- Tabela de Usuários -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Cadastrado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios_sem_cpf as $usuario): ?>
                                    <tr>
                                        <td><strong><?php echo $usuario['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($usuario['email'])): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($usuario['email']); ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($usuario['email']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($usuario['categoria']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($usuario['ativo'] == 1): ?>
                                                <span class="badge bg-success badge-status">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger badge-status">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($usuario['ultimo_login']) {
                                                echo date('d/m/Y', strtotime($usuario['ultimo_login']));
                                            } else {
                                                echo 'Nunca';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?php echo $usuario['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- Mensagem de Sucesso -->
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Excelente!</strong> Todos os usuários ativos possuem CPF cadastrado no sistema.
                    </div>
                <?php endif; ?>

                <!-- Rodapé -->
                <div class="mt-4 text-center text-muted">
                    <small>
                        <i class="bi bi-info-circle me-1"></i>
                        Relatório gerado automaticamente pelo Sistema de Presença<br>
                        Gerado em: <?php echo date('d/m/Y H:i:s'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarUsuario(id) {
            // Redirecionar para edição do usuário (assumindo que existe uma tela de edição)
            if (confirm('Deseja editar este usuário?')) {
                window.open(`painel/usuarios.php?editar=${id}`, '_blank');
            }
        }

        // Auto-refresh a cada 5 minutos
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
