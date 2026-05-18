<?php
//session_start();
//require_once 'config/timezone.php';
//require_once __DIR__ . '/auth/verifica_sessao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: cozinha (acesso_padrao=1)                              ║
// ╚════════════════════════════════════════════════════════════════╝
//require_once __DIR__ . '/core/services/MenuPermissaoService.php';
//MenuPermissaoService::exigirAcesso('cozinha');


// Conexão com o banco de dados
require_once __DIR__ . '/utils/env.php';
$host    = env('DB_HOST', 'localhost');
$usuario = env('DB_USER', 'root');
$senha   = env('DB_PASS', '');
$banco   = env('DB_NAME', 'presenca_aom');

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Função para buscar reservas do dia
function buscarReservasDoDia($data) {
    global $conn;
    
    $sql = "
        SELECT 
            ra.data,
            1 as quantidade,
            CASE 
                WHEN ra.marmitex = 1 THEN 'marmitex'
                ELSE 'almoco'
            END as tipo,
            ra.valor_refeicao,
            ra.valor_marmitex,
            'usuario' as categoria_idade,
            'Sem departamento' as departamento
        FROM reservas_almoco ra
        WHERE ra.data = ?
        UNION ALL
        SELECT 
            ra.data,
            ra.quantidade,
            ra.tipo,
            ra.valor_refeicao,
            ra.valor_marmitex,
            CASE 
                WHEN d.nascimento IS NOT NULL AND YEAR(CURDATE()) - YEAR(d.nascimento) <= 12 THEN 'menor_12'
                ELSE 'maior_12'
            END as categoria_idade,
            'Sem departamento' as departamento
        FROM reservas_adicionais ra
        LEFT JOIN dependentes d ON ra.id_dependente = d.id
        WHERE ra.data = ?
        UNION ALL
        SELECT 
            rd.data,
            rd.quantidade,
            'departamento' as tipo,
            0 as valor_refeicao,
            0 as valor_marmitex,
            'departamento' as categoria_idade,
            CONCAT('Departamento ', rd.entidade_id) as departamento
        FROM reservas_departamento rd
        WHERE rd.data = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Erro na preparação da consulta: " . $conn->error);
        return [];
    }
    $stmt->bind_param("sss", $data, $data, $data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Função para calcular totais
function calcularTotais($reservas) {
    $totais = [
        'total_geral' => 0,
        'menor_12' => 0,
        'departamentos' => [],
        'marmitex' => 0,
        'departamento_total' => 0
    ];
    
    foreach ($reservas as $reserva) {
        $quantidade = (int)$reserva['quantidade'];
        $totais['total_geral'] += $quantidade;
        
        // Apenas dependentes menores de 12 anos são contados separadamente
        if ($reserva['categoria_idade'] === 'menor_12') {
            $totais['menor_12'] += $quantidade;
        } elseif ($reserva['categoria_idade'] === 'departamento') {
            $totais['departamento_total'] += $quantidade;
        }
        
        if ($reserva['tipo'] === 'marmitex') {
            $totais['marmitex'] += $quantidade;
        }
        
        // Contar por departamento
        $dept = $reserva['departamento'] ?: 'Sem departamento';
        if (!isset($totais['departamentos'][$dept])) {
            $totais['departamentos'][$dept] = 0;
        }
        $totais['departamentos'][$dept] += $quantidade;
    }
    
    return $totais;
}

// Data atual
$dataAtual = date('Y-m-d');
$reservas = buscarReservasDoDia($dataAtual);
$totais = calcularTotais($reservas);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle da Cozinha - <?php echo date('d/m/Y'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
            overflow-x: hidden;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 3px solid rgba(255, 255, 255, 0.3);
            padding: 30px 0;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 3rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            font-size: 1.3rem;
            margin-top: 10px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 40px 20px;
            text-align: center;
            transition: transform 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .stat-number {
            font-size: 4rem;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .stat-label {
            font-size: 1.4rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .table td {
            border: none;
            color: white;
            vertical-align: middle;
        }
        
        .table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .badge {
            font-size: 0.9rem;
            padding: 8px 12px;
        }
        
        .badge.bg-success {
            background: rgba(40, 167, 69, 0.8) !important;
        }
        
        .badge.bg-warning {
            background: rgba(255, 193, 7, 0.8) !important;
        }
        
        .badge.bg-info {
            background: rgba(23, 162, 184, 0.8) !important;
        }
        
        .badge.bg-danger {
            background: rgba(220, 53, 69, 0.8) !important;
        }
        
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 20px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .clock {
            font-size: 1.8rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .department-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            margin: 2px;
            display: inline-block;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-utensils me-3"></i>
                        Controle da Cozinha
                    </h1>
                    <p class="mb-0 opacity-75">Reservas do dia - <?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="clock" id="clock"></div>
                    <div class="auto-refresh">
                        <i class="fas fa-sync-alt me-2"></i>
                        <span id="refresh-timer">60s</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Cards de Estatísticas -->
        <div class="row mb-5">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-number text-success">
                        <i class="fas fa-users me-3"></i>
                        <?php echo $totais['total_geral']; ?>
                    </div>
                    <div class="stat-label">Total Geral</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-number text-info">
                        <i class="fas fa-child me-3"></i>
                        <?php echo $totais['menor_12']; ?>
                    </div>
                    <div class="stat-label">Menores de 12 anos</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-number text-primary">
                        <i class="fas fa-building me-3"></i>
                        <?php echo $totais['departamento_total']; ?>
                    </div>
                    <div class="stat-label">Departamentos</div>
                </div>
            </div>
        </div>


    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); display: none !important;">
        <div class="loading">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3">Atualizando dados...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let refreshTimer = 60;
        let refreshInterval;
        
        // Atualizar relógio
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('pt-BR');
            document.getElementById('clock').textContent = timeString;
        }
        
        // Atualizar timer de refresh
        function updateRefreshTimer() {
            refreshTimer--;
            document.getElementById('refresh-timer').textContent = refreshTimer + 's';
            
            if (refreshTimer <= 0) {
                refreshTimer = 60;
                refreshPage();
            }
        }
        
        // Atualizar página
        function refreshPage() {
            document.getElementById('loading-overlay').style.display = 'flex';
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateClock();
            setInterval(updateClock, 1000);
            
            refreshInterval = setInterval(updateRefreshTimer, 1000);
        });
        
        // Pausar refresh ao focar na janela
        window.addEventListener('focus', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = setInterval(updateRefreshTimer, 1000);
            }
        });
        
        // Retomar refresh ao sair do foco
        window.addEventListener('blur', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
