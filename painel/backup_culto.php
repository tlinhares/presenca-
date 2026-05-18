<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_backup (acesso_padrao=0, requer_culto=1)         ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_backup');

// Incluir conexão para obter credenciais
include_once(__DIR__ . '/../api/conexao.php');

// Processar ação de backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    $erros = [];
    $sucessos = [];
    
    // Diretório de backup (fora do webroot — vem do .env)
    require_once __DIR__ . '/../utils/env.php';
    $backup_dir = rtrim(env('BACKUP_BKP_PATH', __DIR__ . '/bkp'), '/') . '/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0770, true);
    }
    
    $timestamp = date('Y-m-d_His');
    
    // Backup do banco de dados
    if ($acao === 'banco' || $acao === 'ambos') {
        try {
            require_once __DIR__ . '/../utils/env.php';
            $db_host = env('DB_HOST', 'localhost');
            $db_user = env('DB_USER', 'root');
            $db_pass = env('DB_PASS', '');
            $db_name = env('DB_NAME', 'presenca_aom');
            
            $sql_file = $backup_dir . "backup_banco_{$timestamp}.sql";
            
            // Comando mysqldump
            $command = "mysqldump -h {$db_host} -u {$db_user} -p'{$db_pass}' {$db_name} > {$sql_file} 2>&1";
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($sql_file) && filesize($sql_file) > 0) {
                $sucessos[] = [
                    'tipo' => 'Banco de Dados',
                    'arquivo' => "backup_banco_{$timestamp}.sql",
                    'tamanho' => formatarTamanho(filesize($sql_file)),
                    'caminho' => $sql_file
                ];
            } else {
                $erros[] = "Erro ao criar backup do banco de dados. Verifique as permissões e credenciais.";
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao criar backup do banco: " . $e->getMessage();
        }
    }
    
    // Backup dos arquivos da aplicação
    if ($acao === 'arquivos' || $acao === 'ambos') {
        try {
            $app_root = dirname(__DIR__);
            $zip_file = $backup_dir . "backup_aplicacao_{$timestamp}.zip";
            
            // Diretórios e arquivos que devem ser EXCLUÍDOS do backup
            $excluir = [
                'node_modules',
                '.git',
                '.gitignore',
                'vendor',
                'bkp',
                'backups',
                'cache',
                'logs',
                '.env',
                'tmp',
                'whatsapp-bot', // Excluir bot do WhatsApp se não fizer parte da aplicação principal
                'projeto' // Excluir se for projeto de teste
            ];
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                // Adicionar TODOS os arquivos e diretórios da raiz, exceto os excluídos
                $itens = scandir($app_root);
                
                foreach ($itens as $item) {
                    if ($item === '.' || $item === '..') continue;
                    
                    // Verificar se está na lista de exclusão
                    if (in_array($item, $excluir)) {
                        continue;
                    }
                    
                    $item_path = $app_root . '/' . $item;
                    
                    if (is_dir($item_path)) {
                        // Adicionar diretório completo (exceto subdiretórios excluídos)
                        adicionarDiretorioAoZip($zip, $item_path, $item, $excluir);
                    } elseif (is_file($item_path)) {
                        // Adicionar arquivo
                        $zip->addFile($item_path, $item);
                    }
                }
                
                $zip->close();
                
                if (file_exists($zip_file) && filesize($zip_file) > 0) {
                    $sucessos[] = [
                        'tipo' => 'Aplicação',
                        'arquivo' => "backup_aplicacao_{$timestamp}.zip",
                        'tamanho' => formatarTamanho(filesize($zip_file)),
                        'caminho' => $zip_file
                    ];
                } else {
                    $erros[] = "Erro ao criar arquivo ZIP do backup.";
                }
            } else {
                $erros[] = "Não foi possível criar o arquivo ZIP.";
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao criar backup dos arquivos: " . $e->getMessage();
        }
    }
    
    // Retornar JSON
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => count($erros) === 0,
        'sucessos' => $sucessos,
        'erros' => $erros
    ]);
    exit;
}

// Função para adicionar diretório recursivamente ao ZIP
function adicionarDiretorioAoZip($zip, $dir, $base_dir = '', $excluir = []) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $file_path = $dir . '/' . $file;
        $zip_path = ($base_dir ? $base_dir . '/' : '') . $file;
        
        // Ignorar arquivos/diretórios na lista de exclusão
        if (in_array($file, $excluir)) {
            continue;
        }
        
        // Ignorar arquivos/diretórios ocultos e temporários comuns
        if (in_array($file, ['.git', '.gitignore', '.env', '.DS_Store', 'Thumbs.db'])) {
            continue;
        }
        
        if (is_dir($file_path)) {
            // Verificar se o diretório não está excluído antes de adicionar recursivamente
            if (!in_array($file, $excluir)) {
                adicionarDiretorioAoZip($zip, $file_path, $zip_path, $excluir);
            }
        } else {
            // Adicionar arquivo ao ZIP
            $zip->addFile($file_path, $zip_path);
        }
    }
}

// Função para formatar tamanho
function formatarTamanho($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Listar backups existentes (mesmo path do bloco de criação)
require_once __DIR__ . '/../utils/env.php';
$backup_dir = rtrim(env('BACKUP_BKP_PATH', __DIR__ . '/bkp'), '/') . '/';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $file_path = $backup_dir . $file;
        if (is_file($file_path)) {
            $backups[] = [
                'nome' => $file,
                'tamanho' => formatarTamanho(filesize($file_path)),
                'data' => date('d/m/Y H:i:s', filemtime($file_path)),
                'tipo' => strpos($file, 'banco') !== false ? 'Banco de Dados' : (strpos($file, 'aplicacao') !== false ? 'Aplicação' : 'Desconhecido')
            ];
        }
    }
    // Ordenar por data (mais recente primeiro)
    usort($backups, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['data'])) - strtotime(str_replace('/', '-', $a['data']));
    });
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup do Sistema - Sistema de Presença</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="../css/feedback-system.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .backup-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .backup-icon {
            font-size: 3rem;
        }
        .loading-spinner {
            display: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-house-door me-2"></i>Sistema de Presença
            </a>
            <div class="navbar-nav ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-database me-2"></i>Backup do Sistema</h2>
                <p class="text-muted">Faça backup da aplicação e/ou do banco de dados</p>
            </div>
        </div>

        <!-- Opções de Backup -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card backup-card h-100" onclick="gerarBackup('banco')">
                    <div class="card-body text-center">
                        <i class="bi bi-database-fill backup-icon text-primary"></i>
                        <h5 class="card-title mt-3">Backup do Banco de Dados</h5>
                        <p class="card-text text-muted">Gera um arquivo SQL com todos os dados do banco</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card backup-card h-100" onclick="gerarBackup('arquivos')">
                    <div class="card-body text-center">
                        <i class="bi bi-folder-fill backup-icon text-success"></i>
                        <h5 class="card-title mt-3">Backup da Aplicação</h5>
                        <p class="card-text text-muted">Gera um arquivo ZIP com todos os arquivos do sistema</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card backup-card h-100" onclick="gerarBackup('ambos')">
                    <div class="card-body text-center">
                        <i class="bi bi-archive-fill backup-icon text-warning"></i>
                        <h5 class="card-title mt-3">Backup Completo</h5>
                        <p class="card-text text-muted">Gera backup tanto do banco quanto dos arquivos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div class="text-center loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Processando...</span>
            </div>
            <p class="mt-3 text-muted">Gerando backup, aguarde...</p>
        </div>

        <!-- Lista de Backups -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Backups Existentes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                    <p class="text-muted text-center">Nenhum backup encontrado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Nome do Arquivo</th>
                                    <th>Tamanho</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <?php if ($backup['tipo'] === 'Banco de Dados'): ?>
                                                <span class="badge bg-primary"><i class="bi bi-database"></i> Banco</span>
                                            <?php elseif ($backup['tipo'] === 'Aplicação'): ?>
                                                <span class="badge bg-success"><i class="bi bi-folder"></i> Aplicação</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($backup['tipo']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($backup['nome']) ?></td>
                                        <td><?= $backup['tamanho'] ?></td>
                                        <td><?= $backup['data'] ?></td>
                                        <td>
                                            <a href="download_backup.php?arquivo=<?= urlencode($backup['nome']) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-download"></i> Baixar
                                            </a>
                                            <button class="btn btn-sm btn-danger" onclick="excluirBackup('<?= htmlspecialchars($backup['nome']) ?>')">
                                                <i class="bi bi-trash"></i> Excluir
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/feedback-system.js"></script>
    <script>
        function gerarBackup(tipo) {
            if (!confirm(`Deseja gerar backup ${tipo === 'banco' ? 'do banco de dados' : tipo === 'arquivos' ? 'da aplicação' : 'completo (banco + aplicação)'}?`)) {
                return;
            }
            
            $('#loadingSpinner').fadeIn(200);
            $('.backup-card').css('pointer-events', 'none').css('opacity', '0.5');
            
            $.ajax({
                url: 'backup_culto.php',
                method: 'POST',
                data: { acao: tipo },
                dataType: 'json',
                success: function(response) {
                    if (response.sucesso) {
                        let mensagem = 'Backup gerado com sucesso!\n\n';
                        response.sucessos.forEach(function(item) {
                            mensagem += `✓ ${item.tipo}: ${item.arquivo} (${item.tamanho})\n`;
                        });
                        exibirToast(mensagem, 'success');
                        
                        // Recarregar a página após 2 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        let mensagem = 'Erro ao gerar backup:\n\n';
                        response.erros.forEach(function(erro) {
                            mensagem += `✗ ${erro}\n`;
                        });
                        exibirToast(mensagem, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', error, xhr.responseText);
                    exibirToast('Erro ao gerar backup. Tente novamente.', 'error');
                },
                complete: function() {
                    $('#loadingSpinner').fadeOut(200);
                    $('.backup-card').css('pointer-events', 'auto').css('opacity', '1');
                }
            });
        }
        
        function excluirBackup(nome) {
            if (!confirm(`Deseja excluir o backup "${nome}"?`)) {
                return;
            }
            
            $.ajax({
                url: 'excluir_backup.php',
                method: 'POST',
                data: { arquivo: nome },
                dataType: 'json',
                success: function(response) {
                    if (response.sucesso) {
                        exibirToast('Backup excluído com sucesso!', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        exibirToast('Erro ao excluir backup: ' + response.mensagem, 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir backup', 'error');
                }
            });
        }
    </script>
</body>
</html>

