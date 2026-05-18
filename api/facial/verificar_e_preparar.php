<?php
/**
 * verificar_e_preparar.php - Verifica e prepara registros para sincronização
 * 
 * Este script verifica se a tabela facial_sync existe, cria-a se necessário,
 * e prepara os registros de usuários para sincronização com o dispositivo facial.
 * 
 * @version 1.0
 * @modified 2023-11-10
 */

// Configurar o content type para JSON
header('Content-Type: application/json; charset=UTF-8');

// Incluir arquivo de conexão
require_once __DIR__ . '/..../../conexao.php';

// Incluir arquivo com função de verificação de tabela
require_once __DIR__ . '/processarLoteSincronizacao.php';

// Iniciar sessão para verificação de permissões
session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar permissão de administrador
$is_admin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
if (!$is_admin) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Acesso não autorizado'
    ]);
    exit;
}

// Configuração de logs
$logs_dir = __DIR__ . '/../../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0777, true);
}
$log_file = $logs_dir . '/presenca_sincronizacao_' . date('Y-m-d') . '.log';

// Função para registrar logs
function registrarLog($mensagem) {
    global $log_file;
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] $mensagem" . PHP_EOL, FILE_APPEND);
}

// Parâmetros da requisição
$data = isset($_REQUEST['data']) ? $_REQUEST['data'] : date('Y-m-d');
$id_usuario = isset($_REQUEST['id_usuario']) ? (int)$_REQUEST['id_usuario'] : 0;

// Validar formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    // Formato incorreto - tentar converter de DD/MM/YYYY para YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
        $data = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        registrarLog("Data convertida de formato DD/MM/YYYY para YYYY-MM-DD: $data");
    } else {
        registrarLog("AVISO: Formato de data inválido, usando data atual");
        $data = date('Y-m-d');
    }
}

registrarLog("======== INICIANDO VERIFICAÇÃO E PREPARAÇÃO ========");
registrarLog("Parâmetros: data=$data, id_usuario=$id_usuario");

try {
    // Verificar se a tabela facial_sync existe e criá-la se necessário
    if (!verificarTabelaSync($conn)) {
        throw new Exception("Erro ao verificar/criar tabela facial_sync");
    }
    
    registrarLog("Tabela facial_sync verificada com sucesso");
    
    // Contagem de registros existentes antes da preparação
    $sql_count_before = "SELECT COUNT(*) as total FROM facial_sync WHERE data = ?";
    $stmt_count_before = $conn->prepare($sql_count_before);
    
    if (!$stmt_count_before) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    
    $stmt_count_before->bind_param("s", $data);
    $stmt_count_before->execute();
    $stmt_count_before->bind_result($total_antes);
    $stmt_count_before->fetch();
    $stmt_count_before->close();
    
    registrarLog("Total de registros existentes para a data $data: $total_antes");
    
    // Preparar SQL para obter usuários com reservas para a data
    $sql_usuarios = "
        SELECT DISTINCT r.id_usuario, u.nome
        FROM reservas_almoco r
        JOIN usuarios u ON r.id_usuario = u.id
        WHERE r.data = ?
    ";
    
    // Se um ID de usuário específico foi fornecido, adicionar filtro
    if ($id_usuario > 0) {
        $sql_usuarios .= " AND r.id_usuario = ?";
    }
    
    $stmt_usuarios = $conn->prepare($sql_usuarios);
    
    if (!$stmt_usuarios) {
        throw new Exception("Erro ao preparar consulta de usuários: " . $conn->error);
    }
    
    // Bind de parâmetros
    if ($id_usuario > 0) {
        $stmt_usuarios->bind_param("si", $data, $id_usuario);
    } else {
        $stmt_usuarios->bind_param("s", $data);
    }
    
    $stmt_usuarios->execute();
    
    // Obter resultado
    $result = null;
    if (method_exists($stmt_usuarios, 'get_result')) {
        // Se tiver mysqlnd, podemos usar get_result
        $result = $stmt_usuarios->get_result();
        $total_usuarios = $result->num_rows;
    } else {
        // Sem mysqlnd, precisamos fazer de outra forma
        $stmt_usuarios->store_result();
        $total_usuarios = $stmt_usuarios->num_rows;
        
        // Obter as colunas
        $stmt_usuarios->bind_result($id_usuario_result, $nome_usuario);
    }
    
    registrarLog("Encontrados $total_usuarios usuários com reservas para a data $data");
    
    // Preparar consulta para verificar se o usuário já está na tabela sync
    $sql_check = "SELECT id FROM facial_sync WHERE id_usuario = ? AND data = ?";
    $stmt_check = $conn->prepare($sql_check);
    
    if (!$stmt_check) {
        throw new Exception("Erro ao preparar consulta de verificação: " . $conn->error);
    }
    
    // Preparar inserção
    $sql_inserir = "INSERT INTO facial_sync (id_usuario, data, status) VALUES (?, ?, 'pendente')";
    $stmt_inserir = $conn->prepare($sql_inserir);
    
    if (!$stmt_inserir) {
        throw new Exception("Erro ao preparar consulta de inserção: " . $conn->error);
    }
    
    // Processar usuários
    $inseridos = 0;
    $ja_existentes = 0;
    $usuarios_inseridos = [];
    
    if (method_exists($stmt_usuarios, 'get_result')) {
        // Com mysqlnd
        while ($row = $result->fetch_assoc()) {
            $id_usuario_atual = $row['id_usuario'];
            $nome_usuario_atual = $row['nome'];
            
            // Verificar se já existe
            $stmt_check->bind_param("is", $id_usuario_atual, $data);
            $stmt_check->execute();
            $stmt_check->store_result();
            $ja_existe = ($stmt_check->num_rows > 0);
            $stmt_check->free_result();
            
            if ($ja_existe) {
                $ja_existentes++;
                registrarLog("Usuário já existe na fila: $nome_usuario_atual (ID: $id_usuario_atual)");
                continue;
            }
            
            // Inserir na tabela de sincronização
            $stmt_inserir->bind_param("is", $id_usuario_atual, $data);
            
            if ($stmt_inserir->execute()) {
                $inseridos++;
                $usuarios_inseridos[] = [
                    'id' => $id_usuario_atual,
                    'nome' => $nome_usuario_atual
                ];
                registrarLog("Inserido registro para usuário: $nome_usuario_atual (ID: $id_usuario_atual)");
            } else {
                registrarLog("ERRO ao inserir registro para usuário: $nome_usuario_atual (ID: $id_usuario_atual): " . $stmt_inserir->error);
            }
        }
    } else {
        // Sem mysqlnd
        while ($stmt_usuarios->fetch()) {
            // Verificar se já existe
            $stmt_check->bind_param("is", $id_usuario_result, $data);
            $stmt_check->execute();
            $stmt_check->store_result();
            $ja_existe = ($stmt_check->num_rows > 0);
            $stmt_check->free_result();
            
            if ($ja_existe) {
                $ja_existentes++;
                registrarLog("Usuário já existe na fila: $nome_usuario (ID: $id_usuario_result)");
                continue;
            }
            
            // Inserir na tabela de sincronização
            $stmt_inserir->bind_param("is", $id_usuario_result, $data);
            
            if ($stmt_inserir->execute()) {
                $inseridos++;
                $usuarios_inseridos[] = [
                    'id' => $id_usuario_result,
                    'nome' => $nome_usuario
                ];
                registrarLog("Inserido registro para usuário: $nome_usuario (ID: $id_usuario_result)");
            } else {
                registrarLog("ERRO ao inserir registro para usuário: $nome_usuario (ID: $id_usuario_result): " . $stmt_inserir->error);
            }
        }
    }
    
    // Fechar statements
    $stmt_usuarios->close();
    $stmt_check->close();
    $stmt_inserir->close();
    
    // Contagem de registros após a preparação
    $sql_count_after = "SELECT COUNT(*) as total FROM facial_sync WHERE data = ?";
    $stmt_count_after = $conn->prepare($sql_count_after);
    $stmt_count_after->bind_param("s", $data);
    $stmt_count_after->execute();
    $stmt_count_after->bind_result($total_depois);
    $stmt_count_after->fetch();
    $stmt_count_after->close();
    
    registrarLog("Processo de preparação concluído. Inseridos: $inseridos, Já existentes: $ja_existentes");
    registrarLog("Total de registros após a preparação: $total_depois");
    
    // Retornar resposta
    echo json_encode([
        'status' => 'ok',
        'mensagem' => "Processo concluído. Foram inseridos $inseridos usuários na fila de sincronização facial.",
        'data' => $data,
        'total_usuarios' => $total_usuarios,
        'total_antes' => $total_antes,
        'total_depois' => $total_depois,
        'inseridos' => $inseridos,
        'ja_existentes' => $ja_existentes,
        'usuarios' => $usuarios_inseridos
    ]);
    
    registrarLog("======== FIM DA VERIFICAÇÃO E PREPARAÇÃO ========");
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage());
    registrarLog("======== FIM DA VERIFICAÇÃO E PREPARAÇÃO COM ERRO ========");
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ocorreu um erro: ' . $e->getMessage()
    ]);
}
?> 