<?php
// api/facial/verificar_tabelas.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);

// Incluir arquivos necessários
require_once __DIR__ . '/../../api/conexao.php';
require_once __DIR__ . '/../../auth/verifica_sessao.php';



// Função para criar logs
function registrarLog($mensagem) {
    $logs_dir = __DIR__ . '/../../logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0777, true);
    }
    
    $log_file = $logs_dir . '/verificacao_tabelas_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$time] $mensagem" . PHP_EOL, FILE_APPEND);
}

// Função para verificar e criar tabela se não existir
function verificarCriarTabela($conn, $tabela, $sql_criacao) {
    $verifica = $conn->query("SHOW TABLES LIKE '$tabela'");
    
    if ($verifica->num_rows == 0) {
        registrarLog("Tabela $tabela não encontrada. Criando...");
        
        if ($conn->query($sql_criacao)) {
            registrarLog("Tabela $tabela criada com sucesso.");
            return [
                'status' => true,
                'mensagem' => "Tabela $tabela criada com sucesso.",
                'nova' => true
            ];
        } else {
            registrarLog("ERRO ao criar tabela $tabela: " . $conn->error);
            return [
                'status' => false,
                'mensagem' => "Erro ao criar tabela $tabela: " . $conn->error
            ];
        }
    }
    
    registrarLog("Tabela $tabela já existe.");
    return [
        'status' => true,
        'mensagem' => "Tabela $tabela já existe.",
        'nova' => false
    ];
}

// Definição da tabela facial_sync
$sql_facial_sync = "CREATE TABLE facial_sync (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_usuario INT(11) NOT NULL,
    data DATE NOT NULL,
    status ENUM('pendente', 'sincronizado', 'falha') NOT NULL DEFAULT 'pendente',
    horario_sync DATETIME DEFAULT NULL,
    detalhes TEXT,
    PRIMARY KEY (id),
    KEY idx_usuario_data (id_usuario, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Definição da tabela checkin_facial
$sql_checkin_facial = "CREATE TABLE checkin_facial (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_usuario INT(11) NOT NULL,
    data_hora DATETIME NOT NULL,
    dispositivo_id VARCHAR(50),
    tipo VARCHAR(20) DEFAULT 'entrada',
    PRIMARY KEY (id),
    KEY idx_usuario (id_usuario),
    KEY idx_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Verificar e criar as tabelas
$resultado = [
    'status' => 'ok',
    'tabelas' => [
        'facial_sync' => verificarCriarTabela($conn, 'facial_sync', $sql_facial_sync),
        'checkin_facial' => verificarCriarTabela($conn, 'checkin_facial', $sql_checkin_facial)
    ]
];

// Verificar se houve algum erro
foreach ($resultado['tabelas'] as $tabela => $info) {
    if (!$info['status']) {
        $resultado['status'] = 'erro';
        break;
    }
}

// Responder com o resultado
echo json_encode($resultado);
?> 