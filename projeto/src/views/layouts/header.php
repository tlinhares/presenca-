<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Construtora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo config('base_url'); ?>public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo config('base_url'); ?>">Construtora XYZ</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <input class="form-control form-control-dark w-100" type="text" placeholder="Pesquisar" aria-label="Pesquisar">
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="<?php echo config('base_url'); ?>logout">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'] == '') ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/obras') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>obras">
                                <i class="fas fa-building"></i>
                                Obras
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/financeiro') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>financeiro/notas-fiscais">
                                <i class="fas fa-dollar-sign"></i>
                                Financeiro
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/cronograma') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>cronograma">
                                <i class="fas fa-calendar-alt"></i>
                                Cronograma
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/documentos') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>documentos">
                                <i class="fas fa-file-alt"></i>
                                Documentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/usuarios') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>usuarios">
                                <i class="fas fa-users"></i>
                                Usuários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/configuracoes') !== false) ? 'active' : ''; ?>" href="<?php echo config('base_url'); ?>configuracoes">
                                <i class="fas fa-cog"></i>
                                Configurações
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
