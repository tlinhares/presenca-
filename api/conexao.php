<?php
// api/conexao.php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

// Impedir que mensagens de erros não fatais sejam exibidas
ini_set('display_errors', 0);

// Suprimir erros específicos de versão
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../utils/env.php';

$host    = env('DB_HOST', 'localhost');
$usuario = env('DB_USER', 'root');
$senha   = env('DB_PASS', '');
$banco   = env('DB_NAME', 'presenca_aom');

// Verificar se já existe uma conexão ativa
if (!isset($conn) || $conn->connect_error || $conn->ping() === false) {
    try {
        $conn = new mysqli($host, $usuario, $senha, $banco);
        
        if ($conn->connect_error) {
            error_log("Conexão falhou: " . $conn->connect_error);
            die("Erro ao conectar ao banco de dados. Verifique os logs.");
        }
        
        $conn->set_charset("utf8");
        
        // Configurar para manter a conexão viva
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 60);
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, 60);
        
    } catch (Exception $e) {
        error_log("Erro ao conectar: " . $e->getMessage());
        die("Erro ao conectar ao banco de dados. Verifique os logs.");
    }
}
?>