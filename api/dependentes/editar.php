<?php
// Aumentar limites para processamento de imagens
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);

// Habilitar exibição de erros para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros na tela, apenas log
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Registrar handler de erro fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("DEBUG - Erro fatal: " . $error['message'] . " em " . $error['file'] . " linha " . $error['line']);
        http_response_code(500);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro interno do servidor: ' . $error['message']
        ]);
    }
});

try {
    require_once __DIR__ . '/../conexao.php';
} catch (Exception $e) {
    error_log("DEBUG - Erro ao carregar conexao.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao conectar ao banco de dados'
    ]);
    exit;
}

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
try {
    require_once __DIR__ . '/../../core/middleware/mobile_auth.php';
} catch (Exception $e) {
    error_log("DEBUG - Erro ao carregar mobile_auth.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao carregar middleware de autenticação'
    ]);
    exit;
}

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

try {
    // Aceita tanto JSON (mobile) quanto form-data (web)
    $input_data = [];
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        // Requisição JSON (mobile)
        $input = file_get_contents('php://input');
        $input_data = json_decode($input, true) ?? [];
    } else {
        // Requisição form-data (web)
        $input_data = $_POST;
    }
    
    $id = $input_data['id'] ?? '';
    $nome = $input_data['nome'] ?? '';
    $parentesco = $input_data['parentesco'] ?? '';
    $nascimento = $input_data['nascimento_dependente'] ?? $input_data['nascimento'] ?? '';
    
    error_log("DEBUG - Dados recebidos: ID=$id, Nome=$nome, Parentesco=$parentesco, Nascimento=$nascimento");
    
    // Verificar se $conn está definida
    if (!isset($conn) || !$conn) {
        error_log("DEBUG - Erro: Variável \$conn não está definida");
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro de conexão com o banco de dados'
        ]);
        exit;
    }
    
    // Verificar permissão: admin tem acesso total; não-admin só pode editar dependente do próprio usuário
    $isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    $usuarioLogadoId = intval($_SESSION['usuario_id']);

    // Validar dados
    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID do dependente inválido']);
        exit;
    }
    
    if (empty($nome) || empty($parentesco)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Nome e parentesco são obrigatórios']);
        exit;
    }

    // Processar foto se fornecida (pode vir como base64 do mobile ou como arquivo do web)
    $foto_processada = null;
    
    error_log("DEBUG - Verificando foto_base64: isset = " . (isset($input_data['foto_base64']) ? 'SIM' : 'NÃO'));
    if (isset($input_data['foto_base64'])) {
        error_log("DEBUG - foto_base64 não vazio: " . (!empty($input_data['foto_base64']) ? 'SIM' : 'NÃO'));
        if (!empty($input_data['foto_base64'])) {
            error_log("DEBUG - Tamanho foto_base64: " . strlen($input_data['foto_base64']) . " caracteres");
        }
    }
    
    // Verificar se veio como base64 (mobile ou web via FormData)
    if (isset($input_data['foto_base64']) && !empty($input_data['foto_base64'])) {
        $foto_processada = $input_data['foto_base64'];
        error_log("DEBUG - Foto recebida como base64, tamanho inicial: " . strlen($foto_processada) . " caracteres");
        // Remover prefixo data:image se existir
        if (strpos($foto_processada, 'data:image/') === 0) {
            $pos = strpos($foto_processada, ',');
            if ($pos !== false) {
                $foto_processada = substr($foto_processada, $pos + 1);
                error_log("DEBUG - Prefixo data:image removido, tamanho final: " . strlen($foto_processada) . " caracteres");
            }
        }
        error_log("DEBUG - Foto processada com sucesso (base64)");
    }
    // Verificar se veio como arquivo (web)
    elseif (isset($_FILES['foto'])) {
        // Log detalhado para debug
        error_log("DEBUG - Verificando foto: isset(\$_FILES['foto']) = " . (isset($_FILES['foto']) ? 'SIM' : 'NÃO'));
        if (isset($_FILES['foto'])) {
            error_log("DEBUG - \$_FILES['foto']['error'] = " . $_FILES['foto']['error']);
            error_log("DEBUG - \$_FILES['foto']['size'] = " . ($_FILES['foto']['size'] ?? 'NÃO DEFINIDO'));
            error_log("DEBUG - \$_FILES['foto']['tmp_name'] = " . ($_FILES['foto']['tmp_name'] ?? 'NÃO DEFINIDO'));
            error_log("DEBUG - \$_FILES['foto']['name'] = " . ($_FILES['foto']['name'] ?? 'NÃO DEFINIDO'));
        }
        
        if (isset($_FILES['foto']) && 
            $_FILES['foto']['error'] === UPLOAD_ERR_OK && 
            isset($_FILES['foto']['size']) &&
            $_FILES['foto']['size'] > 0 &&
            !empty($_FILES['foto']['tmp_name']) &&
            file_exists($_FILES['foto']['tmp_name'])) {
            error_log("DEBUG - Processando foto...");
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_data = file_get_contents($foto_tmp);
            $foto_processada = base64_encode($foto_data);
            
            // Verificar tamanho da foto base64
            $tamanho_foto = strlen($foto_processada);
            error_log("DEBUG - Tamanho da foto base64: " . $tamanho_foto . " caracteres");
            
            // Limitar tamanho da foto (máximo 1MB em base64)
            if ($tamanho_foto > 1024 * 1024) {
                error_log("DEBUG - Foto muito grande, comprimindo...");
                // Comprimir a imagem
                $imagem = imagecreatefromstring($foto_data);
                if ($imagem !== false) {
                    $largura_original = imagesx($imagem);
                    $altura_original = imagesy($imagem);
                    
                    // Redimensionar para máximo 800x600
                    $nova_largura = min(800, $largura_original);
                    $nova_altura = min(600, $altura_original);
                    
                    $imagem_redimensionada = imagecreatetruecolor($nova_largura, $nova_altura);
                    imagecopyresampled($imagem_redimensionada, $imagem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura_original, $altura_original);
                    
                    ob_start();
                    imagejpeg($imagem_redimensionada, null, 80);
                    $foto_comprimida = ob_get_contents();
                    ob_end_clean();
                    
                    $foto_processada = base64_encode($foto_comprimida);
                    $tamanho_final = strlen($foto_processada);
                    error_log("DEBUG - Foto comprimida: " . $tamanho_final . " caracteres");
                    
                    imagedestroy($imagem);
                    imagedestroy($imagem_redimensionada);
                }
            }
        } else {
            error_log("DEBUG - Foto NÃO foi processada. Motivos:");
            if (!isset($_FILES['foto'])) {
                error_log("DEBUG -   - \$_FILES['foto'] não está definido");
            } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                error_log("DEBUG -   - Erro no upload: " . $_FILES['foto']['error']);
            } elseif (!isset($_FILES['foto']['size']) || $_FILES['foto']['size'] <= 0) {
                error_log("DEBUG -   - Tamanho inválido: " . ($_FILES['foto']['size'] ?? 'NÃO DEFINIDO'));
            } elseif (empty($_FILES['foto']['tmp_name'])) {
                error_log("DEBUG -   - tmp_name vazio");
            } elseif (!file_exists($_FILES['foto']['tmp_name'])) {
                error_log("DEBUG -   - Arquivo temporário não existe: " . $_FILES['foto']['tmp_name']);
            }
        }
    }

    // Calcular idade e definir cobrar
    // Regra oficial: idade <= 12 anos => cobrar = 1 (NÃO cobra). Bate com
    // dependentes/criar.php e verificar_horario_adicional. Antes daqui usava
    // '< 12', o que zerava o cobrar de quem tinha exatamente 12 anos e gerava
    // cobrança indevida (caso Samuel Nunes Amaral, 16/06/2026).
    $cobrar = 0;
    if (!empty($nascimento)) {
        $nascimento_date = new DateTime($nascimento);
        $hoje = new DateTime();
        $idade = $nascimento_date->diff($hoje)->y;
        $cobrar = $idade <= 12 ? 1 : 0;
    }

    // Verificar se o dependente existe antes de atualizar e obter o id_usuario para checagem de permissão
    $stmt_check = $conn->prepare("SELECT id, id_usuario FROM dependentes WHERE id = ?");
    if (!$stmt_check) {
        error_log("DEBUG - Erro no prepare check: " . $conn->error);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta: ' . $conn->error]);
        exit;
    }
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Dependente não encontrado'
        ]);
        exit;
    }
    $depRow = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$isAdmin && intval($depRow['id_usuario']) !== $usuarioLogadoId) {
        error_log("DEBUG - Tentativa de edição de dependente: ID=$id, UsuarioDependente={$depRow['id_usuario']}, UsuarioLogado=$usuarioLogadoId, IsAdmin=" . ($isAdmin ? 'true' : 'false'));
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado - você só pode editar seus próprios dependentes']);
        exit;
    }

    // Atualizar dependente
    if ($foto_processada) {
        $stmt = $conn->prepare("
            UPDATE dependentes 
            SET nome = ?, parentesco = ?, nascimento = ?, foto_base64 = ?, cobrar = ? 
            WHERE id = ?
        ");
        if (!$stmt) {
            error_log("DEBUG - Erro no prepare UPDATE com foto: " . $conn->error);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("ssssii", $nome, $parentesco, $nascimento, $foto_processada, $cobrar, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE dependentes 
            SET nome = ?, parentesco = ?, nascimento = ?, cobrar = ? 
            WHERE id = ?
        ");
        if (!$stmt) {
            error_log("DEBUG - Erro no prepare UPDATE sem foto: " . $conn->error);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro na consulta: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("sssii", $nome, $parentesco, $nascimento, $cobrar, $id);
    }
    
    error_log("DEBUG - Executando UPDATE com foto_processada: " . ($foto_processada ? "SIM" : "NÃO"));
    if ($stmt->execute()) {
        error_log("DEBUG - UPDATE executado com sucesso");
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Dependente atualizado com sucesso'
        ]);
    } else {
        error_log("DEBUG - Erro ao executar UPDATE dependente: " . $stmt->error);
        error_log("DEBUG - SQL: " . ($foto_processada ? "UPDATE com foto" : "UPDATE sem foto"));
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao atualizar dependente: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("DEBUG - Exceção capturada: " . $e->getMessage());
    error_log("DEBUG - Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar dependente: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("DEBUG - Erro fatal capturado: " . $e->getMessage());
    error_log("DEBUG - Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro fatal ao atualizar dependente: ' . $e->getMessage()
    ]);
}

?>