<?php
// limpar_facial.php - Apaga usuários do facial automaticamente (apenas restaurante, preserva culto)

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../utils/logger.php';

// Buscar dispositivos faciais ativos do tipo restaurante (NÃO incluir culto)
$sql = "SELECT id, nome, ip, porta, usuario, senha FROM dispositivos_faciais WHERE tipo_dispositivo = 'restaurante' AND ativo = 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "Nenhum dispositivo facial ativo encontrado.\n";
    exit;
}

$dispositivos = $result->fetch_all(MYSQLI_ASSOC);
$total_dispositivos = count($dispositivos);
$sucessos = 0;
$erros = 0;

echo "Iniciando limpeza em $total_dispositivos dispositivo(s) facial(is) do tipo RESTAURANTE...\n";
echo "NOTA: Dispositivos do tipo CULTO serão preservados e NÃO serão limpos.\n";

foreach ($dispositivos as $dispositivo) {
    $id = $dispositivo['id'];
    $nome = $dispositivo['nome'];
    $ip = $dispositivo['ip'];
    $porta = $dispositivo['porta'] ?: 80;
    $usuario = $dispositivo['usuario'];
    $senha = $dispositivo['senha'];
    
    echo "Processando dispositivo: $nome ($ip:$porta)...\n";
    
    // Montar URL
    $url = "http://{$ip}:{$porta}/cgi-bin/recordUpdater.cgi?action=clear&name=AccessControlCard";
    
    // Executar requisição com autenticação DIGEST
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verificar resultado
    if ($erro) {
        $status = "ERRO";
        $mensagem = "Erro cURL: $erro";
        $erros++;
    } elseif ($http_code == 200) {
        $status = "SUCESSO";
        $mensagem = "Dispositivo limpo com sucesso";
        $sucessos++;
    } else {
        $status = "ERRO";
        $mensagem = "HTTP $http_code: $resposta";
        $erros++;
    }
    
    echo "  $status: $mensagem\n";
    
    Logger::emergencial('limpar_facial', "Dispositivo RESTAURANTE: $nome ($ip:$porta) - $status: $mensagem");
}

// Resumo final
echo "\n=== RESUMO ===\n";
echo "Total de dispositivos RESTAURANTE processados: $total_dispositivos\n";
echo "Sucessos: $sucessos\n";
echo "Erros: $erros\n";
echo "NOTA: Dispositivos do tipo CULTO foram preservados e NÃO foram limpos.\n";

if ($erros > 0) {
    Logger::emergencial('limpar_facial', "RESUMO LIMPEZA RESTAURANTE - Total: $total_dispositivos, Sucessos: $sucessos, Erros: $erros");
}

if ($erros > 0) {
    exit(1); // Código de erro para indicar falhas
}
?>
