<?php
/**
 * API Mobile - Login e geração de Bearer Token
 * 
 * Endpoint: POST /api/mobile/auth/login.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "email": "usuario@exemplo.com",
 *   "senha": "senha123"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
 *     "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
 *     "expires_in": 86400,
 *     "token_type": "Bearer",
 *     "user": {
 *       "id": 1,
 *       "nome": "João Silva",
 *       "email": "usuario@exemplo.com",
 *       "categoria": "admin"
 *     }
 *   },
 *   "message": "Login realizado com sucesso"
 * }
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../api/conexao.php';
require_once __DIR__ . '/../../../core/services/TokenService.php';
require_once __DIR__ . '/../utils/response.php';

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(MobileResponse::error('Método não permitido', 405));
    exit;
}

try {
    // Obtém dados do body (JSON)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validação de entrada
    if (!$data) {
        echo json_encode(MobileResponse::error('JSON inválido', 400));
        exit;
    }
    
    $email = isset($data['email']) ? trim($data['email']) : '';
    $senha = isset($data['senha']) ? trim($data['senha']) : '';

    if (empty($email) || empty($senha)) {
        echo json_encode(MobileResponse::error('Email e senha são obrigatórios', 400));
        exit;
    }
    
    // Busca usuário no banco
    $stmt = $conn->prepare("SELECT id, nome, categoria, senha, email, culto FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        echo json_encode(MobileResponse::error('Credenciais inválidas', 401));
        exit;
    }

    $stmt->bind_result($id, $nome, $categoria, $senha_hash, $email_db, $culto);
    $stmt->fetch();
    $stmt->close();
    
    // Verifica senha
    if (!password_verify($senha, $senha_hash)) {
        echo json_encode(MobileResponse::error('Credenciais inválidas', 401));
        exit;
    }
    
    // Atualiza último login
    $conn->query("UPDATE usuarios SET ultimo_login = NOW() WHERE id = {$id}");

    // Registro OPORTUNÍSTICO do token FCM: se o app mandar `fcm_token` no body
    // do login, já faz o UPSERT (equivalente a /api/mobile/notifications/register.php).
    // Isso serve para apps que não conseguem/lembram chamar o register separadamente
    // após o login — mais robusto do que depender só de onTokenRefresh.
    if (!empty($data['fcm_token'])) {
        $fcm_token  = trim((string) $data['fcm_token']);
        $plataforma = strtolower(trim((string) ($data['plataforma'] ?? 'android')));
        if (!in_array($plataforma, ['android', 'ios', 'web'], true)) $plataforma = 'android';
        $modelo = trim((string) ($data['modelo_dispositivo'] ?? '')) ?: null;
        $versao = trim((string) ($data['versao_app']         ?? '')) ?: null;

        if (strlen($fcm_token) >= 20) {
            $stmt_r = $conn->prepare(
                "INSERT INTO notificacoes_push_dispositivos
                    (id_usuario, fcm_token, plataforma, modelo_dispositivo, versao_app, ativo, ultimo_uso)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                    id_usuario = VALUES(id_usuario),
                    plataforma = VALUES(plataforma),
                    modelo_dispositivo = VALUES(modelo_dispositivo),
                    versao_app = VALUES(versao_app),
                    ativo = 1,
                    ultimo_uso = NOW()"
            );
            if ($stmt_r) {
                $stmt_r->bind_param('issss', $id, $fcm_token, $plataforma, $modelo, $versao);
                $stmt_r->execute();
                $stmt_r->close();
            }
        }
    }

    // Gera tokens
    $tokens = TokenService::generateToken($id, $nome, $categoria, $email_db);
    
    // Prepara resposta
    $responseData = [
        'token' => $tokens['token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in' => $tokens['expires_in'],
        'token_type' => $tokens['token_type'],
        'user' => [
            'id' => (int)$id,
            'nome' => $nome,
            'email' => $email_db,
            'categoria' => $categoria,
            'culto' => (int)$culto
        ]
    ];
    
    echo json_encode(MobileResponse::success($responseData, 'Login realizado com sucesso'));
    
} catch (Exception $e) {
    error_log("Erro no login mobile: " . $e->getMessage());
    echo json_encode(MobileResponse::error('Erro interno do servidor', 500));
}

$conn->close();
