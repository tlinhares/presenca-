<?php
/**
 * Script para limpar tokens de senha expirados
 * Pode ser executado via cron job: 0 */6 * * * /usr/bin/php /var/www/html/presenca/limpar_tokens_expirados.php
 */

require_once 'api/conexao.php';

$sql = "DELETE FROM tokens_senha WHERE expiracao < NOW()";
$result = $conn->query($sql);

if ($result) {
    $linhasAfetadas = $conn->affected_rows;
    echo "Tokens expirados removidos: {$linhasAfetadas}\n";
} else {
    echo "Erro ao limpar tokens expirados: " . $conn->error . "\n";
}

$conn->close();
?>