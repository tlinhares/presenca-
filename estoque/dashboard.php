<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('estoque_dashboard');

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';

// Função auxiliar para renderizar card de menu apenas se tiver permissão
function renderizarMenuCard($codigo, $url, $icone, $titulo, $descricao, $gradient) {
    if (!MenuPermissaoService::podeAcessar($codigo)) {
        return '';
    }
    
    // Se URL não começar com /, é relativa ao diretório atual
    if (strpos($url, '/') !== 0) {
        $urlAjustada = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    } else {
        $urlAjustada = htmlspecialchars(MenuPermissaoService::ajustarUrl($url), ENT_QUOTES, 'UTF-8');
    }
    $iconeEscapado = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
    $tituloEscapado = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $descricaoEscapada = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
    $gradientEscapado = htmlspecialchars($gradient, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<div class="col-4 col-md-3">
    <a href="{$urlAjustada}" class="menu-card">
        <div class="icon-wrapper" style="background: {$gradientEscapado};">
            <i class="bi {$iconeEscapado}"></i>
        </div>
        <h6>{$tituloEscapado}</h6>
        <small class="d-none d-md-block">{$descricaoEscapada}</small>
    </a>
</div>
HTML;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Estoque - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        /* Header */
        .header-estoque {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-estoque h4 {
            margin: 0;
            font-weight: 600;
        }
        
        /* Cards de estatística */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.info { background: var(--info-gradient); }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.875rem;
            margin: 0;
        }
        
        /* Menu cards */
        .menu-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .menu-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: #2d3748;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            height: 100%;
            min-height: 140px;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.25);
            color: #667eea;
        }
        
        .menu-card .icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .menu-card:hover .icon-wrapper {
            transform: scale(1.1);
        }
        
        .menu-card h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .menu-card small {
            color: #718096;
            font-size: 0.8rem;
        }
        
        /* Alertas */
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s ease;
        }
        
        .alert-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .alert-card.critico {
            border-left: 4px solid #e53e3e;
        }
        
        .alert-card.baixo {
            border-left: 4px solid #dd6b20;
        }
        
        .alert-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Dropdown de alertas do sino */
        .painel-alertas {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 340px;
            max-width: 90vw;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.18);
            z-index: 1050;
            overflow: hidden;
            color: #2d3748;
        }
        .painel-alertas-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-bottom: 1px solid #edf2f7;
            background: #f8f9fa;
        }
        .painel-alertas-lista {
            max-height: 360px;
            overflow-y: auto;
        }
        .painel-alertas-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid #f1f3f5;
            text-decoration: none;
            color: #2d3748;
            transition: background 0.15s;
        }
        .painel-alertas-item:hover { background: #f8f9fa; }
        .painel-alertas-item:last-child { border-bottom: none; }
        .painel-alertas-item .qtd {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .painel-alertas-item .qtd.critico { background: #fed7d7; color: #c53030; }
        .painel-alertas-item .qtd.baixo   { background: #feebc8; color: #c05621; }
        .painel-alertas-foot {
            display: block;
            text-align: center;
            padding: 0.65rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #c05621;
            background: #fffaf0;
            text-decoration: none;
            border-top: 1px solid #edf2f7;
        }
        .painel-alertas-foot:hover { background: #feebc8; }
        
        .alert-badge.critico {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-badge.baixo {
            background: #feebc8;
            color: #c05621;
        }
        
        /* Requisições pendentes */
        .req-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .req-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }
        
        .priority-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-badge.urgente { background: #e53e3e; color: white; }
        .priority-badge.alta { background: #dd6b20; color: white; }
        .priority-badge.normal { background: #3182ce; color: white; }
        .priority-badge.baixa { background: #718096; color: white; }
        
        /* Departamentos */
        .dept-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .dept-card:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .dept-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.25rem;
            color: white;
        }
        
        /* Footer navigation mobile */
        .mobile-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            padding: 0.5rem;
            z-index: 1000;
        }
        
        .mobile-nav a {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            color: #718096;
            text-decoration: none;
            font-size: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .mobile-nav a.active,
        .mobile-nav a:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .mobile-nav a i {
            font-size: 1.25rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stat-card { padding: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .menu-card { padding: 1rem; min-height: 120px; }
            .menu-card .icon-wrapper { width: 48px; height: 48px; font-size: 1.25rem; }
            .mobile-nav { display: flex; }
            body { padding-bottom: 70px; }
            .hide-mobile { display: none !important; }
        }
        
        @media (max-width: 576px) {
            .header-estoque { padding: 0.75rem 0; }
            .stat-value { font-size: 1.25rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.25rem; }
        }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px;
        }
        
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-estoque">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="btn btn-outline-light btn-sm hide-mobile">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h4><i class="bi bi-box-seam me-2"></i>Estoque</h4>
                        <small class="opacity-75 d-none d-sm-inline">Controle de materiais</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="position-relative" id="wrap-alertas">
                        <button class="btn btn-light btn-sm position-relative" id="btn-alertas" title="Alertas de estoque baixo" type="button">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="badge-alertas" style="display: none;">
                                0
                            </span>
                        </button>
                        <div id="painel-alertas" class="painel-alertas" style="display: none;">
                            <div class="painel-alertas-head">
                                <span><i class="bi bi-exclamation-triangle text-warning me-1"></i>Estoque baixo</span>
                                <span class="badge bg-danger" id="painel-alertas-total">0</span>
                            </div>
                            <div id="painel-alertas-lista" class="painel-alertas-lista">
                                <div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div>
                            </div>
                            <a href="produtos/?filtro=estoque_baixo" class="painel-alertas-foot">Ver todos os produtos com estoque baixo</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-none d-sm-inline"><?= htmlspecialchars($nomeUsuario) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>"><i class="bi bi-house me-2"></i>Início</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'acesso_negado'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Acesso negado!</strong> Você não tem permissão para acessar essa página.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Cards de Estatística -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card primary">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="stat-produtos">-</div>
                            <p class="stat-label">Produtos</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card warning">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="stat-baixo">-</div>
                            <p class="stat-label">Estoque Baixo</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card info">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon info">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="stat-requisicoes">-</div>
                            <p class="stat-label">Requisições</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card success">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon success">
                            <i class="bi bi-arrow-down-up"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="stat-movimentacoes">-</div>
                            <p class="stat-label">Mov. Hoje</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Coluna Principal -->
            <div class="col-lg-8">
                <!-- Menu Principal -->
                <div class="menu-section">
                    <div class="section-title">
                        <i class="bi bi-grid"></i>
                        Operações
                    </div>
                    <div class="row g-3">
                        <?= renderizarMenuCard('estoque_produtos', 'produtos/', 'bi-box-seam', 'Produtos', 'Catálogo', 'var(--primary-gradient)') ?>
                        <?= renderizarMenuCard('estoque_entrada', 'movimentacoes/entrada.php', 'bi-box-arrow-in-down', 'Entrada', 'Dar entrada', 'var(--success-gradient)') ?>
                        <?= renderizarMenuCard('estoque_importar_xml', 'movimentacoes/importar_xml.php', 'bi-file-earmark-code', 'XML NF', 'Importar NF', 'var(--info-gradient)') ?>
                        <?= renderizarMenuCard('estoque_requisicoes', 'requisicoes/', 'bi-clipboard-check', 'Requisições', 'Solicitações', 'var(--warning-gradient)') ?>
                        <?= renderizarMenuCard('estoque_nova_requisicao', 'requisicoes/nova.php', 'bi-plus-circle', 'Nova Req.', 'Solicitar', 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)') ?>
                        <?= renderizarMenuCard('estoque_movimentacoes', 'movimentacoes/', 'bi-arrow-left-right', 'Movimentos', 'Histórico', 'linear-gradient(135deg, #5ee7df 0%, #b490ca 100%)') ?>
                        <?= renderizarMenuCard('estoque_inventario', 'inventario/', 'bi-ui-checks', 'Inventário', 'Contagem', 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)') ?>
                        <?= renderizarMenuCard('estoque_relatorios', 'relatorios/', 'bi-file-earmark-bar-graph', 'Relatórios', 'Análises', 'var(--dark-gradient)') ?>
                    </div>
                </div>

                <!-- Configurações -->
                <?php
                // Limpa cache de permissões para garantir dados atualizados
                MenuPermissaoService::limparCache();
                
                // Lista padrão de menus de configuração
                $menusPadrao = [
                    ['codigo' => 'estoque_config_departamentos', 'url' => 'configuracoes/departamentos.php', 'icone' => 'bi-building', 'titulo' => 'Deptos', 'descricao' => 'Setores', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
                    ['codigo' => 'estoque_config_categorias', 'url' => 'configuracoes/categorias.php', 'icone' => 'bi-tags', 'titulo' => 'Categorias', 'descricao' => 'Tipos', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
                    ['codigo' => 'estoque_config_unidades', 'url' => 'configuracoes/unidades.php', 'icone' => 'bi-rulers', 'titulo' => 'Unidades', 'descricao' => 'Medidas', 'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
                    ['codigo' => 'estoque_config_fornecedores', 'url' => 'configuracoes/fornecedores.php', 'icone' => 'bi-truck', 'titulo' => 'Fornecedores', 'descricao' => 'Parceiros', 'gradient' => 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)'],
                    ['codigo' => 'estoque_config_responsaveis', 'url' => 'configuracoes/responsaveis.php', 'icone' => 'bi-people', 'titulo' => 'Responsáveis', 'descricao' => 'Gestores', 'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'],
                    ['codigo' => 'estoque_config_localizacoes', 'url' => 'configuracoes/localizacoes.php', 'icone' => 'bi-geo-alt', 'titulo' => 'Localizações', 'descricao' => 'Prateleiras', 'gradient' => 'linear-gradient(135deg, #434343 0%, #000000 100%)']
                ];
                
                // Primeiro, tenta buscar menus da categoria estoque_config do banco
                $menusConfigBanco = MenuPermissaoService::getMenusPorCategoria('estoque_config');
                
                $menusConfig = [];
                $usarVerificacaoPermissao = true;
                
                if (!empty($menusConfigBanco)) {
                    // Menus do banco já foram filtrados por permissão em getMenusDoUsuario()
                    // Então podemos renderizar diretamente sem verificação adicional
                    $usarVerificacaoPermissao = false;
                    
                    // Usar menus do banco diretamente
                    foreach ($menusConfigBanco as $menuBanco) {
                        $codigo = $menuBanco['codigo'] ?? '';
                        $url = $menuBanco['url'] ?? '';
                        
                        // Se URL começa com /, remover para ser relativa ao diretório estoque
                        if (strpos($url, '/estoque/') === 0) {
                            $url = substr($url, strlen('/estoque/'));
                        } elseif (strpos($url, '/') === 0) {
                            $url = substr($url, 1);
                        }
                        
                        // Se não tem URL, tentar mapear pelo código
                        if (empty($url)) {
                            $urlMap = [
                                'estoque_config_departamentos' => 'configuracoes/departamentos.php',
                                'estoque_config_categorias' => 'configuracoes/categorias.php',
                                'estoque_config_unidades' => 'configuracoes/unidades.php',
                                'estoque_config_fornecedores' => 'configuracoes/fornecedores.php',
                                'estoque_config_responsaveis' => 'configuracoes/responsaveis.php',
                                'estoque_config_localizacoes' => 'configuracoes/localizacoes.php'
                            ];
                            $url = $urlMap[$codigo] ?? '';
                        }
                        
                        if ($url) {
                            $menusConfig[] = [
                                'codigo' => $codigo,
                                'url' => $url,
                                'icone' => $menuBanco['icone'] ?? 'bi-gear',
                                'titulo' => $menuBanco['nome'] ?? 'Config',
                                'descricao' => $menuBanco['descricao_card'] ?? $menuBanco['descricao'] ?? '',
                                'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
                            ];
                        }
                    }
                }
                
                // Se não encontrou menus no banco OU encontrou mas está vazio, usar fallback com verificação de permissão
                if (empty($menusConfig)) {
                    // Filtrar apenas menus que o usuário tem permissão
                    foreach ($menusPadrao as $menuPadrao) {
                        if (MenuPermissaoService::podeAcessar($menuPadrao['codigo'])) {
                            $menusConfig[] = $menuPadrao;
                        }
                    }
                }
                
                // Função auxiliar para renderizar sem verificação de permissão (quando já veio do banco)
                function renderizarMenuCardSemVerificacao($url, $icone, $titulo, $descricao, $gradient) {
                    if (strpos($url, '/') !== 0) {
                        $urlAjustada = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                    } else {
                        $urlAjustada = htmlspecialchars(MenuPermissaoService::ajustarUrl($url), ENT_QUOTES, 'UTF-8');
                    }
                    $iconeEscapado = htmlspecialchars($icone, ENT_QUOTES, 'UTF-8');
                    $tituloEscapado = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
                    $descricaoEscapada = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
                    $gradientEscapado = htmlspecialchars($gradient, ENT_QUOTES, 'UTF-8');
                    
                    return <<<HTML
<div class="col-4 col-md-3">
    <a href="{$urlAjustada}" class="menu-card">
        <div class="icon-wrapper" style="background: {$gradientEscapado};">
            <i class="bi {$iconeEscapado}"></i>
        </div>
        <h6>{$tituloEscapado}</h6>
        <small class="d-none d-md-block">{$descricaoEscapada}</small>
    </a>
</div>
HTML;
                }
                
                $htmlConfig = '';
                foreach ($menusConfig as $menu) {
                    if (!$usarVerificacaoPermissao) {
                        // Menus do banco já foram filtrados por permissão, renderiza diretamente
                        $htmlConfig .= renderizarMenuCardSemVerificacao($menu['url'], $menu['icone'], $menu['titulo'], $menu['descricao'], $menu['gradient']);
                    } else {
                        // Fallback: verifica permissão individual
                        $htmlConfig .= renderizarMenuCard($menu['codigo'], $menu['url'], $menu['icone'], $menu['titulo'], $menu['descricao'], $menu['gradient']);
                    }
                }
                
                // Sempre mostra a seção se houver menus configurados
                // A verificação de permissão já foi feita acima (via getMenusPorCategoria ou podeAcessar)
                if (!empty($menusConfig)):
                ?>
                <div class="menu-section" id="section-config">
                    <div class="section-title">
                        <i class="bi bi-gear"></i>
                        Configurações
                    </div>
                    <div class="row g-3">
                        <?= $htmlConfig ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Departamentos -->
                <div class="menu-section">
                    <div class="section-title">
                        <i class="bi bi-building"></i>
                        Meus Departamentos
                    </div>
                    <div class="row g-3" id="lista-departamentos">
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="bi bi-building"></i>
                                <p>Carregando departamentos...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral -->
            <div class="col-lg-4">
                <!-- Alertas de Estoque Baixo -->
                <div class="menu-section">
                    <div class="section-title">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                        Estoque Baixo
                    </div>
                    <div id="lista-alertas">
                        <div class="empty-state">
                            <i class="bi bi-check-circle text-success"></i>
                            <p>Nenhum alerta</p>
                        </div>
                    </div>
                </div>

                <!-- Requisições Pendentes -->
                <div class="menu-section">
                    <div class="section-title">
                        <i class="bi bi-clipboard-check text-info"></i>
                        Requisições Pendentes
                    </div>
                    <div id="lista-requisicoes">
                        <div class="empty-state">
                            <i class="bi bi-clipboard-check"></i>
                            <p>Nenhuma requisição pendente</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegação Mobile -->
    <nav class="mobile-nav">
        <a href="resumo.php" class="active">
            <i class="bi bi-house"></i>
            <span>Início</span>
        </a>
        <?php if (MenuPermissaoService::podeAcessar('estoque_produtos')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/produtos/') ?>">
            <i class="bi bi-box-seam"></i>
            <span>Produtos</span>
        </a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('estoque_nova_requisicao')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/requisicoes/nova.php') ?>">
            <i class="bi bi-plus-circle"></i>
            <span>Solicitar</span>
        </a>
        <?php endif; ?>
        <?php if (MenuPermissaoService::podeAcessar('estoque_movimentacoes')): ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/movimentacoes/') ?>">
            <i class="bi bi-arrow-left-right"></i>
            <span>Movimentos</span>
        </a>
        <?php endif; ?>
        <?php
        // Verifica se tem acesso a pelo menos um menu de configuração
        $temConfig = false;
        $menusConfigMobile = ['estoque_config_departamentos', 'estoque_config_categorias', 'estoque_config_unidades', 'estoque_config_fornecedores', 'estoque_config_responsaveis', 'estoque_config_localizacoes'];
        foreach ($menusConfigMobile as $codigo) {
            if (MenuPermissaoService::podeAcessar($codigo)) {
                $temConfig = true;
                break;
            }
        }
        if ($temConfig):
        ?>
        <a href="<?= MenuPermissaoService::ajustarUrl('/estoque/configuracoes/') ?>">
            <i class="bi bi-gear"></i>
            <span>Config</span>
        </a>
        <?php endif; ?>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        $(document).ready(function() {
            // Carregar dados do dashboard
            carregarEstatisticas();
            carregarDepartamentos();
            carregarAlertas();
            carregarRequisicoesPendentes();
        });
        
        function carregarEstatisticas() {
            $.ajax({
                url: baseUrl + '/api/estoque/dashboard/estatisticas.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        $('#stat-produtos').text(data.estatisticas.total_produtos || 0);
                        $('#stat-baixo').text(data.estatisticas.estoque_baixo || 0);
                        $('#stat-requisicoes').text(data.estatisticas.requisicoes_pendentes || 0);
                        $('#stat-movimentacoes').text(data.estatisticas.movimentacoes_hoje || 0);
                        
                        // Badge de alertas
                        const totalAlertas = (data.estatisticas.estoque_baixo || 0);
                        if (totalAlertas > 0) {
                            $('#badge-alertas').text(totalAlertas > 99 ? '99+' : totalAlertas).show();
                        }
                    }
                },
                error: function() {
                    $('#stat-produtos, #stat-baixo, #stat-requisicoes, #stat-movimentacoes').text('0');
                }
            });
        }
        
        function carregarDepartamentos() {
            $.ajax({
                url: baseUrl + '/api/estoque/departamentos/listar.php',
                method: 'GET',
                data: { meus: true },
                dataType: 'json',
                success: function(data) {
                    const container = $('#lista-departamentos');
                    
                    if (data.status === 'ok' && data.departamentos && data.departamentos.length > 0) {
                        let html = '';
                        data.departamentos.forEach(function(dept) {
                            html += `
                                <div class="col-6 col-md-4">
                                    <div class="dept-card" onclick="window.location='produtos/?departamento=${dept.id}'">
                                        <div class="dept-icon" style="background: ${dept.cor || '#667eea'};">
                                            <i class="bi ${dept.icone || 'bi-box'}"></i>
                                        </div>
                                        <h6 class="mb-1">${dept.nome}</h6>
                                        <small class="text-muted">${dept.total_produtos || 0} produtos</small>
                                    </div>
                                </div>
                            `;
                        });
                        container.html(html);
                    } else {
                        container.html(`
                            <div class="col-12">
                                <div class="empty-state">
                                    <i class="bi bi-building"></i>
                                    <p>Nenhum departamento vinculado</p>
                                    <small>Solicite acesso a um departamento</small>
                                </div>
                            </div>
                        `);
                    }
                }
            });
        }
        
        let alertasCache = [];

        function renderizarPainelAlertas(alertas) {
            const lista = $('#painel-alertas-lista');
            $('#painel-alertas-total').text(alertas.length);
            if (!alertas.length) {
                lista.html('<div class="text-center text-success py-4"><i class="bi bi-check-circle fs-3 d-block mb-2"></i>Estoque OK!</div>');
                $('.painel-alertas-foot').hide();
                return;
            }
            $('.painel-alertas-foot').show();
            let html = '';
            alertas.forEach(function(a) {
                const nivel = a.quantidade_atual <= 0 ? 'critico' : 'baixo';
                html += `
                    <a class="painel-alertas-item" href="produtos/?departamento=${a.id_departamento}">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">${a.nome}</div>
                            <small class="text-muted">${a.departamento} · mín: ${a.quantidade_minima} ${a.unidade}</small>
                        </div>
                        <span class="qtd ${nivel}">${a.quantidade_atual} ${a.unidade}</span>
                    </a>`;
            });
            lista.html(html);
        }

        // Toggle do painel ao clicar no sino
        $('#btn-alertas').on('click', function(e) {
            e.stopPropagation();
            const painel = $('#painel-alertas');
            if (painel.is(':visible')) {
                painel.hide();
            } else {
                renderizarPainelAlertas(alertasCache);
                painel.show();
            }
        });
        // Fechar ao clicar fora
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#wrap-alertas').length) {
                $('#painel-alertas').hide();
            }
        });

        function carregarAlertas() {
            $.ajax({
                url: baseUrl + '/api/estoque/dashboard/alertas.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const container = $('#lista-alertas');
                    alertasCache = (data.status === 'ok' && data.alertas) ? data.alertas : [];
                    renderizarPainelAlertas(alertasCache);

                    if (data.status === 'ok' && data.alertas && data.alertas.length > 0) {
                        let html = '';
                        data.alertas.slice(0, 5).forEach(function(alerta) {
                            const nivel = alerta.quantidade_atual <= 0 ? 'critico' : 'baixo';
                            html += `
                                <div class="alert-card ${nivel}">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">${alerta.nome}</div>
                                        <small class="text-muted">${alerta.departamento}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="alert-badge ${nivel}">
                                            ${alerta.quantidade_atual} ${alerta.unidade}
                                        </span>
                                        <div class="small text-muted">Mín: ${alerta.quantidade_minima}</div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        if (data.alertas.length > 5) {
                            html += `
                                <a href="produtos/?filtro=estoque_baixo" class="btn btn-outline-warning btn-sm w-100 mt-2">
                                    Ver todos (${data.alertas.length})
                                </a>
                            `;
                        }
                        
                        container.html(html);
                    } else {
                        container.html(`
                            <div class="empty-state">
                                <i class="bi bi-check-circle text-success"></i>
                                <p>Estoque OK!</p>
                            </div>
                        `);
                    }
                }
            });
        }
        
        function carregarRequisicoesPendentes() {
            $.ajax({
                url: baseUrl + '/api/estoque/requisicoes/listar.php',
                method: 'GET',
                data: { status: 'pendente', limite: 5 },
                dataType: 'json',
                success: function(data) {
                    const container = $('#lista-requisicoes');
                    
                    if (data.status === 'ok' && data.requisicoes && data.requisicoes.length > 0) {
                        let html = '';
                        data.requisicoes.forEach(function(req) {
                            html += `
                                <div class="req-card" onclick="window.location='requisicoes/visualizar.php?id=${req.id}'">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>#${req.numero}</strong>
                                            <span class="priority-badge ${req.prioridade} ms-2">${req.prioridade}</span>
                                        </div>
                                        <small class="text-muted">${req.data_formatada}</small>
                                    </div>
                                    <div class="small text-muted">${req.solicitante}</div>
                                    <div class="small">${req.total_itens} ite${req.total_itens > 1 ? 'ns' : 'm'}</div>
                                </div>
                            `;
                        });
                        
                        if (data.total > 5) {
                            html += `
                                <a href="requisicoes/" class="btn btn-outline-primary btn-sm w-100 mt-2">
                                    Ver todas (${data.total})
                                </a>
                            `;
                        }
                        
                        container.html(html);
                    } else {
                        container.html(`
                            <div class="empty-state">
                                <i class="bi bi-clipboard-check text-success"></i>
                                <p>Nenhuma requisição pendente</p>
                            </div>
                        `);
                    }
                }
            });
        }
    </script>
</body>
</html>


