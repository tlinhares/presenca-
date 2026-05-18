<?php
// painel/dispositivos_faciais.php - Painel de controle para dispositivos faciais
session_start();
require_once '../api/conexao.php';
include_once '../auth/verifica_sessao.php';
include_once '../auth/verifica_permissao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: dispositivos_faciais                                   ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('dispositivos_faciais');

$mensagem = '';
$tipoMensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        switch ($_POST['acao']) {
            case 'adicionar':
                $nome = $_POST['nome'];
                $ip = $_POST['ip'];
                $porta = $_POST['porta'];
                $usuario = $_POST['usuario'];
                $senha = $_POST['senha'];
                $tipo_dispositivo = $_POST['tipo_dispositivo'];
                $modelo = isset($_POST['modelo']) ? $_POST['modelo'] : null;
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                // Validar formato do IP
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $mensagem = 'Endereço IP inválido: ' . htmlspecialchars($ip);
                    $tipoMensagem = 'error';
                    break;
                }
                
                $stmt = $conn->prepare("INSERT INTO dispositivos_faciais (nome, ip, porta, usuario, senha, tipo_dispositivo, modelo, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissssi", $nome, $ip, $porta, $usuario, $senha, $tipo_dispositivo, $modelo, $ativo);
                
                if ($stmt->execute()) {
                    $mensagem = 'Dispositivo adicionado com sucesso! IP: ' . htmlspecialchars($ip);
                    $tipoMensagem = 'success';
                } else {
                    $mensagem = 'Erro ao adicionar dispositivo: ' . $stmt->error;
                    $tipoMensagem = 'error';
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nome = $_POST['nome'];
                $ip = $_POST['ip'];
                $porta = $_POST['porta'];
                $usuario = $_POST['usuario'];
                $senha = $_POST['senha'];
                $tipo_dispositivo = $_POST['tipo_dispositivo'];
                $modelo = isset($_POST['modelo']) ? $_POST['modelo'] : null;
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                // Validar formato do IP
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $mensagem = 'Endereço IP inválido: ' . htmlspecialchars($ip);
                    $tipoMensagem = 'error';
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE dispositivos_faciais SET nome = ?, ip = ?, porta = ?, usuario = ?, senha = ?, tipo_dispositivo = ?, modelo = ?, ativo = ? WHERE id = ?");
                $stmt->bind_param("ssissssii", $nome, $ip, $porta, $usuario, $senha, $tipo_dispositivo, $modelo, $ativo, $id);
                
                if ($stmt->execute()) {
                    $mensagem = 'Dispositivo atualizado com sucesso! IP: ' . htmlspecialchars($ip);
                    $tipoMensagem = 'success';
                } else {
                    $mensagem = 'Erro ao atualizar dispositivo: ' . $stmt->error;
                    $tipoMensagem = 'error';
                }
                break;
                
            case 'excluir':
                $id = $_POST['id'];
                
                // Verificar se há registros na facial_sync
                $check = $conn->prepare("SELECT COUNT(*) FROM facial_sync WHERE id_dispositivo = ?");
                $check->bind_param("i", $id);
                $check->execute();
                $check->bind_result($count);
                $check->fetch();
                $check->close();
                
                if ($count > 0) {
                    $mensagem = 'Não é possível excluir o dispositivo pois existem registros de sincronização associados.';
                    $tipoMensagem = 'warning';
                } else {
                    $stmt = $conn->prepare("DELETE FROM dispositivos_faciais WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $mensagem = 'Dispositivo excluído com sucesso!';
                        $tipoMensagem = 'success';
                    } else {
                        $mensagem = 'Erro ao excluir dispositivo: ' . $stmt->error;
                        $tipoMensagem = 'error';
                    }
                }
                break;
                
            case 'testar_conectividade':
                $id = $_POST['id'];
                
                $stmt = $conn->prepare("SELECT ip, porta, usuario, senha FROM dispositivos_faciais WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->bind_result($ip, $porta, $usuario, $senha);
                $stmt->fetch();
                $stmt->close();
                
                // Testar conectividade - usar endpoint global.cgi para Intelbras
                $url = "http://$ip:$porta/cgi-bin/global.cgi?action=getCurrentTime";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                
                $resposta = curl_exec($ch);
                $erro = curl_error($ch);
                $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($codigo == 200 || $codigo == 401 || $codigo == 400) {
                    if ($codigo == 200) {
                        $mensagem = 'Conectividade OK! Dispositivo respondendo normalmente. Código: ' . $codigo;
                        $tipoMensagem = 'success';
                    } elseif ($codigo == 401) {
                        $mensagem = 'Dispositivo online, mas credenciais podem estar incorretas. Código: ' . $codigo;
                        $tipoMensagem = 'warning';
                    } else {
                        $mensagem = 'Dispositivo online, mas parâmetros podem estar incorretos. Código: ' . $codigo;
                        $tipoMensagem = 'warning';
                    }
                    
                    // Atualizar status
                    $update = $conn->prepare("UPDATE dispositivos_faciais SET status_conexao = 'online' WHERE id = ?");
                    $update->bind_param("i", $id);
                    $update->execute();
                } else {
                    $mensagem = 'Falha na conectividade. Código: ' . $codigo . ' | Erro: ' . $erro;
                    $tipoMensagem = 'error';
                    
                    // Atualizar status
                    $update = $conn->prepare("UPDATE dispositivos_faciais SET status_conexao = 'offline' WHERE id = ?");
                    $update->bind_param("i", $id);
                    $update->execute();
                }
                break;
        }
    }
}

// Buscar dispositivos
$dispositivos = [];
$sql = "SELECT * FROM dispositivos_faciais ORDER BY nome";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dispositivos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispositivos Faciais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .header-painel {
            background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .card-custom {
            border-radius: 12px;
            overflow: hidden;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .card-custom .card-header {
            background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 1rem 1.25rem;
        }
        .table th {
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }
        .badge-status {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }
        .btn-action {
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
        }
        .empty-state {
            padding: 3rem 1rem;
        }
        .empty-state i {
            font-size: 4rem;
            color: #adb5bd;
        }
        .modal-header.bg-purple {
            background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-painel">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-phone me-2"></i>Dispositivos Faciais</h3>
                    <small class="opacity-75">Gerenciamento de terminais de reconhecimento</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarDispositivo">
                        <i class="bi bi-plus-lg me-1"></i>Adicionar
                    </button>
                    <a href="<?= MenuPermissaoService::ajustarUrl('/painel/dashboard.php') ?>" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <div class="card card-custom">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-hdd-stack me-2"></i>Dispositivos Cadastrados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($dispositivos)): ?>
                    <div class="empty-state text-center">
                        <i class="bi bi-inbox mb-3"></i>
                        <h5 class="text-muted">Nenhum dispositivo cadastrado</h5>
                        <p class="text-muted">Clique em "Adicionar" para cadastrar um novo dispositivo.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>IP</th>
                                    <th>Porta</th>
                                    <th>Tipo</th>
                                    <th>Modelo</th>
                                    <th>Status</th>
                                    <th>Última Sincronização</th>
                                    <th width="180">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dispositivos as $dispositivo): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-hdd text-primary me-2"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($dispositivo['nome']); ?></strong>
                                                    <?php if ($dispositivo['ativo']): ?>
                                                        <span class="badge bg-success ms-2">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary ms-2">Inativo</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($dispositivo['ip']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-info badge-status"><?php echo htmlspecialchars($dispositivo['porta']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $tipo_class = $dispositivo['tipo_dispositivo'] === 'culto' ? 'bg-danger' : 'bg-success';
                                            $tipo_text = $dispositivo['tipo_dispositivo'] === 'culto' ? 'Culto' : 'Restaurante';
                                            $tipo_icon = $dispositivo['tipo_dispositivo'] === 'culto' ? 'bi-people' : 'bi-cup-hot';
                                            ?>
                                            <span class="badge <?php echo $tipo_class; ?> badge-status">
                                                <i class="bi <?php echo $tipo_icon; ?> me-1"></i>
                                                <?php echo $tipo_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($dispositivo['modelo'])) {
                                                echo '<span class="badge bg-secondary badge-status">' . htmlspecialchars($dispositivo['modelo']) . '</span>';
                                            } else {
                                                echo '<span class="text-muted"><small>N/A</small></span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = 'bg-secondary';
                                            $status_text = 'Desconhecido';
                                            $status_icon = 'bi-question-circle';
                                            
                                            switch ($dispositivo['status_conexao']) {
                                                case 'online':
                                                    $status_class = 'bg-success';
                                                    $status_text = 'Online';
                                                    $status_icon = 'bi-check-circle';
                                                    break;
                                                case 'offline':
                                                    $status_class = 'bg-danger';
                                                    $status_text = 'Offline';
                                                    $status_icon = 'bi-x-circle';
                                                    break;
                                                case 'erro':
                                                    $status_class = 'bg-warning text-dark';
                                                    $status_text = 'Erro';
                                                    $status_icon = 'bi-exclamation-triangle';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> badge-status">
                                                <i class="bi <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($dispositivo['ultima_sincronizacao']) {
                                                echo '<small class="text-muted">' . date('d/m/Y H:i', strtotime($dispositivo['ultima_sincronizacao'])) . '</small>';
                                            } else {
                                                echo '<span class="text-muted"><small>Nunca</small></span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary btn-action" onclick="testarConectividade(<?php echo $dispositivo['id']; ?>)" title="Testar Conectividade">
                                                    <i class="bi bi-wifi"></i>
                                                </button>
                                                <button class="btn btn-outline-info btn-action" onclick="listarUsuariosDispositivo(<?php echo $dispositivo['id']; ?>, '<?php echo htmlspecialchars($dispositivo['nome']); ?>')" title="Listar Usuários">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary btn-action" onclick="editarDispositivo(<?php echo $dispositivo['id']; ?>, '<?php echo addslashes($dispositivo['nome']); ?>', '<?php echo addslashes($dispositivo['ip']); ?>', <?php echo $dispositivo['porta']; ?>, '<?php echo addslashes($dispositivo['usuario']); ?>', '<?php echo addslashes($dispositivo['senha']); ?>', '<?php echo $dispositivo['tipo_dispositivo']; ?>', '<?php echo isset($dispositivo['modelo']) ? addslashes($dispositivo['modelo']) : ''; ?>', <?php echo $dispositivo['ativo']; ?>)" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-action" onclick="excluirDispositivo(<?php echo $dispositivo['id']; ?>, '<?php echo htmlspecialchars($dispositivo['nome']); ?>')" title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
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

    <!-- Modal Adicionar Dispositivo -->
    <div class="modal fade" id="modalAdicionarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-lg me-2"></i>Adicionar Dispositivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="adicionar">
                        
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Dispositivo</label>
                            <input type="text" class="form-control" id="nome" name="nome" required placeholder="Ex: Refeitório Principal">
                        </div>
                        
                        <div class="mb-3">
                            <label for="ip" class="form-label">Endereço IP</label>
                            <input type="text" class="form-control" id="ip" name="ip" required 
                                   placeholder="Ex: 192.168.1.100" 
                                   pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                   title="Digite um endereço IP válido (ex: 192.168.1.100)"
                                   maxlength="15">
                        </div>
                        
                        <div class="mb-3">
                            <label for="porta" class="form-label">Porta</label>
                            <input type="number" class="form-control" id="porta" name="porta" value="80" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tipo_dispositivo" class="form-label">Tipo do Dispositivo</label>
                            <select class="form-select" id="tipo_dispositivo" name="tipo_dispositivo" required>
                                <option value="restaurante">Restaurante</option>
                                <option value="culto">Culto</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo do Dispositivo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" placeholder="Ex: ct3000 2pb, ss3710, facial_recon">
                            <small class="form-text text-muted">Opcional: Modelo do dispositivo (ex: ct3000 2pb, ss3710)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuário</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required placeholder="Ex: admin">
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                                <label class="form-check-label" for="ativo">
                                    Dispositivo Ativo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Dispositivo -->
    <div class="modal fade" id="modalEditarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Editar Dispositivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label for="edit_nome" class="form-label">Nome do Dispositivo</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_ip" class="form-label">Endereço IP</label>
                            <input type="text" class="form-control" id="edit_ip" name="ip" required 
                                   pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                   title="Digite um endereço IP válido (ex: 192.168.1.100)"
                                   maxlength="15">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_porta" class="form-label">Porta</label>
                            <input type="number" class="form-control" id="edit_porta" name="porta" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_tipo_dispositivo" class="form-label">Tipo do Dispositivo</label>
                            <select class="form-select" id="edit_tipo_dispositivo" name="tipo_dispositivo" required>
                                <option value="restaurante">Restaurante</option>
                                <option value="culto">Culto</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_modelo" class="form-label">Modelo do Dispositivo</label>
                            <input type="text" class="form-control" id="edit_modelo" name="modelo" placeholder="Ex: ct3000 2pb, ss3710, facial_recon">
                            <small class="form-text text-muted">Opcional: Modelo do dispositivo (ex: ct3000 2pb, ss3710)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_usuario" class="form-label">Usuário</label>
                            <input type="text" class="form-control" id="edit_usuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="edit_senha" name="senha" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_ativo" name="ativo">
                                <label class="form-check-label" for="edit_ativo">
                                    Dispositivo Ativo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Ação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-question-circle text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0" id="modalConfirmacaoTexto">
                                Tem certeza que deseja realizar esta ação?
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarAcao">
                        <i class="bi bi-check-lg me-1"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Listar Usuários do Dispositivo -->
    <div class="modal fade" id="modalListarUsuarios" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-people me-2"></i>Usuários no Dispositivo - <span id="modalDispositivoNome"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingUsuarios" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Consultando dispositivo...</p>
                    </div>
                    <div id="conteudoUsuarios" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Total: <strong id="totalUsuarios">0</strong> usuários registrados no dispositivo</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>UserID</th>
                                        <th>Nome</th>
                                        <th>Válido De</th>
                                        <th>Válido Até</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelaUsuarios"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="erroUsuarios" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer class="py-4 text-center text-muted small">
        &copy; <?php echo date('Y'); ?> Sistema de Presença - Desenvolvido por Tiago Linhares
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/notificacoes.js') ?>"></script>
    <script>
    // Exibir mensagem do servidor via toast
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($mensagem): ?>
            if (typeof exibirToast !== 'undefined') {
                exibirToast('<?= addslashes($mensagem) ?>', '<?= $tipoMensagem ?>');
            }
        <?php endif; ?>
    });
    
    // Função para validar IP
    function validarIP(ip) {
        const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        return ipRegex.test(ip);
    }
    
    // Validar campo IP ao perder foco
    document.addEventListener('DOMContentLoaded', function() {
        const ipInputs = document.querySelectorAll('input[name="ip"]');
        ipInputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                const ip = this.value.trim();
                if (ip && !validarIP(ip)) {
                    this.setCustomValidity('Digite um endereço IP válido (ex: 192.168.1.100)');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                }
            });
            
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
            });
        });
    });

    function editarDispositivo(id, nome, ip, porta, usuario, senha, tipo_dispositivo, modelo, ativo) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome').value = nome;
        document.getElementById('edit_ip').value = ip;
        document.getElementById('edit_porta').value = porta;
        document.getElementById('edit_usuario').value = usuario;
        document.getElementById('edit_senha').value = senha;
        document.getElementById('edit_tipo_dispositivo').value = tipo_dispositivo;
        document.getElementById('edit_modelo').value = modelo || '';
        document.getElementById('edit_ativo').checked = ativo == 1;
        
        new bootstrap.Modal(document.getElementById('modalEditarDispositivo')).show();
    }

    function excluirDispositivo(id, nome) {
        document.getElementById('modalConfirmacaoTexto').textContent = `Tem certeza que deseja excluir o dispositivo "${nome}"?`;
        document.getElementById('btnConfirmarAcao').onclick = function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        };
        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    function testarConectividade(id) {
        document.getElementById('modalConfirmacaoTexto').textContent = 'Deseja testar a conectividade deste dispositivo?';
        document.getElementById('btnConfirmarAcao').onclick = function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="acao" value="testar_conectividade">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        };
        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    function listarUsuariosDispositivo(idDispositivo, nomeDispositivo) {
        const modalEl = document.getElementById('modalListarUsuarios');
        document.getElementById('modalDispositivoNome').textContent = nomeDispositivo;
        
        document.getElementById('loadingUsuarios').style.display = 'block';
        document.getElementById('conteudoUsuarios').style.display = 'none';
        document.getElementById('erroUsuarios').style.display = 'none';
        document.getElementById('tabelaUsuarios').innerHTML = '';
        
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        
        fetch(`../api/dispositivos/listar_usuarios_antena.php?id_dispositivo=${idDispositivo}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingUsuarios').style.display = 'none';
                
                if (data.status === 'sucesso') {
                    const usuarios = data.usuarios || [];
                    document.getElementById('totalUsuarios').textContent = usuarios.length;
                    document.getElementById('conteudoUsuarios').style.display = 'block';
                    
                    const tbody = document.getElementById('tabelaUsuarios');
                    tbody.innerHTML = '';
                    
                    if (usuarios.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Nenhum usuário registrado no dispositivo</td></tr>';
                    } else {
                        usuarios.forEach(function(u) {
                            const tr = document.createElement('tr');
                            const validFrom = u.valid_from || '-';
                            const validTo = u.valid_to || '-';
                            
                            tr.innerHTML = `
                                <td><code>${u.user_id}</code></td>
                                <td><strong>${u.user_name || '<span class="text-muted">-</span>'}</strong></td>
                                <td><small>${validFrom}</small></td>
                                <td><small>${validTo}</small></td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                } else {
                    document.getElementById('erroUsuarios').style.display = 'block';
                    document.getElementById('erroUsuarios').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> ${data.mensagem || 'Erro ao consultar dispositivo'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('loadingUsuarios').style.display = 'none';
                document.getElementById('erroUsuarios').style.display = 'block';
                document.getElementById('erroUsuarios').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Erro:</strong> Falha ao conectar com o servidor: ${error.message}
                    </div>
                `;
            });
    }
    </script>
</body>
</html>
