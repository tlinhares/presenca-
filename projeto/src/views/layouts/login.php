<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gestão de Construtora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 15px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .card-header h4 {
            margin-bottom: 0;
        }
        .card-body {
            padding: 30px;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .btn-primary {
            background-color: #343a40;
            border-color: #343a40;
            width: 100%;
            padding: 10px;
        }
        .btn-primary:hover {
            background-color: #23272b;
            border-color: #23272b;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo i {
            font-size: 48px;
            color: #343a40;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-building"></i>
        </div>
        <div class="card">
            <div class="card-header">
                <h4>Sistema de Gestão de Construtora</h4>
            </div>
            <div class="card-body">
                <form action="<?php echo config('base_url'); ?>login" method="post">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="nome@exemplo.com" required>
                        <label for="email">Email</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required>
                        <label for="senha">Senha</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Entrar</button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="text-decoration-none">Esqueceu a senha?</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3 text-muted">
            <small>&copy; 2025 Construtora XYZ. Todos os direitos reservados.</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
