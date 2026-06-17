<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$usuario_id = (int) $_SESSION['usuario_id'];

/**
 * Lê uma flag inteira do input. Se a chave não veio, devolve $default
 * (assim o app/web pode mandar só os campos que está alterando).
 */
$flag = function (string $key, int $default) use ($input): int {
    if (!array_key_exists($key, $input)) return $default;
    $v = $input[$key];
    if (is_bool($v)) return $v ? 1 : 0;
    if (is_string($v)) return in_array(strtolower($v), ['true', '1', 'sim', 'on'], true) ? 1 : 0;
    return (int) $v ? 1 : 0;
};

// Garante tabela. Cria com TODAS as colunas — versão atual.
$tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;
if (!$tabela_existe) {
    $sql_create = "CREATE TABLE IF NOT EXISTS notificacoes_usuario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario BIGINT UNSIGNED NOT NULL,
        notificar_reserva_propria TINYINT(1) DEFAULT 0,
        notificar_reserva_adicional TINYINT(1) DEFAULT 0,
        notificar_reserva_multipla TINYINT(1) DEFAULT 0,
        notificar_reserva_cancelada TINYINT(1) DEFAULT 0,
        notificar_lembrete_diario TINYINT(1) DEFAULT 1,
        notificar_justificativa_culto TINYINT(1) DEFAULT 1,
        canal_email TINYINT(1) NOT NULL DEFAULT 1,
        canal_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
        canal_push TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_usuario (id_usuario),
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql_create)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao criar tabela: ' . $conn->error]);
        $conn->close();
        exit;
    }
}

// Estado atual (default 1 para canais — assume "todos ligados" se ainda não tem linha).
$atualRes = $conn->query("SELECT * FROM notificacoes_usuario WHERE id_usuario = $usuario_id");
$atual = $atualRes && $atualRes->num_rows > 0 ? $atualRes->fetch_assoc() : [
    'notificar_reserva_propria' => 0, 'notificar_reserva_adicional' => 0,
    'notificar_reserva_multipla' => 0, 'notificar_reserva_cancelada' => 0,
    'notificar_lembrete_diario' => 1, 'notificar_justificativa_culto' => 1,
    'canal_email' => 1, 'canal_whatsapp' => 1, 'canal_push' => 1,
];

$np  = $flag('notificar_reserva_propria',     (int) ($atual['notificar_reserva_propria']     ?? 0));
$na  = $flag('notificar_reserva_adicional',   (int) ($atual['notificar_reserva_adicional']   ?? 0));
$nm  = $flag('notificar_reserva_multipla',    (int) ($atual['notificar_reserva_multipla']    ?? 0));
$nc  = $flag('notificar_reserva_cancelada',   (int) ($atual['notificar_reserva_cancelada']   ?? 0));
$nl  = $flag('notificar_lembrete_diario',     (int) ($atual['notificar_lembrete_diario']     ?? 1));
$nj  = $flag('notificar_justificativa_culto', (int) ($atual['notificar_justificativa_culto'] ?? 1));
$ce  = $flag('canal_email',                   (int) ($atual['canal_email']                   ?? 1));
$cw  = $flag('canal_whatsapp',                (int) ($atual['canal_whatsapp']                ?? 1));
$cp  = $flag('canal_push',                    (int) ($atual['canal_push']                    ?? 1));

$existe = $atualRes && $atualRes->num_rows > 0;

if ($existe) {
    $stmt = $conn->prepare(
        "UPDATE notificacoes_usuario SET
            notificar_reserva_propria = ?, notificar_reserva_adicional = ?,
            notificar_reserva_multipla = ?, notificar_reserva_cancelada = ?,
            notificar_lembrete_diario = ?, notificar_justificativa_culto = ?,
            canal_email = ?, canal_whatsapp = ?, canal_push = ?,
            atualizado_em = NOW()
         WHERE id_usuario = ?"
    );
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar UPDATE: ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param('iiiiiiiiii', $np, $na, $nm, $nc, $nl, $nj, $ce, $cw, $cp, $usuario_id);
} else {
    $stmt = $conn->prepare(
        "INSERT INTO notificacoes_usuario
            (id_usuario, notificar_reserva_propria, notificar_reserva_adicional,
             notificar_reserva_multipla, notificar_reserva_cancelada,
             notificar_lembrete_diario, notificar_justificativa_culto,
             canal_email, canal_whatsapp, canal_push)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    if (!$stmt) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar INSERT: ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param('iiiiiiiiii', $usuario_id, $np, $na, $nm, $nc, $nl, $nj, $ce, $cw, $cp);
}

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configurações salvas com sucesso',
        'dados' => [
            'notificar_reserva_propria'    => (bool) $np,
            'notificar_reserva_adicional'  => (bool) $na,
            'notificar_reserva_multipla'   => (bool) $nm,
            'notificar_reserva_cancelada'  => (bool) $nc,
            'notificar_lembrete_diario'    => (bool) $nl,
            'notificar_justificativa_culto'=> (bool) $nj,
            'canal_email'                  => (bool) $ce,
            'canal_whatsapp'               => (bool) $cw,
            'canal_push'                   => (bool) $cp,
        ],
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
