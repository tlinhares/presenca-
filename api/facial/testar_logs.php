<?php
// api/facial/testar_logs.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

/**
 * Script para testar acesso ao diretório de logs e verificar permissões
 * Útil para diagnosticar problemas de permissão
 */

// Função para testar escrita em um diretório
function testarEscritaEmDiretorio($diretorio) {
    $resultado = [
        'diretorio' => $diretorio,
        'existe' => false,
        'gravavel' => false,
        'detalhes' => [],
        'teste_escrita' => false
    ];
    
    // Verificar se o diretório existe
    if (file_exists($diretorio)) {
        $resultado['existe'] = true;
        
        // Verificar permissões
        $resultado['gravavel'] = is_writable($diretorio);
        
        // Obter permissões em formato octal
        $perms = fileperms($diretorio);
        $resultado['detalhes']['permissoes_octal'] = substr(sprintf('%o', $perms), -4);
        
        // Obter proprietário
        if (function_exists('posix_getpwuid')) {
            $owner = posix_getpwuid(fileowner($diretorio));
            $resultado['detalhes']['proprietario'] = $owner['name'];
        } else {
            $resultado['detalhes']['proprietario'] = 'Função posix_getpwuid não disponível';
        }
        
        // Obter grupo
        if (function_exists('posix_getgrgid')) {
            $group = posix_getgrgid(filegroup($diretorio));
            $resultado['detalhes']['grupo'] = $group['name'];
        } else {
            $resultado['detalhes']['grupo'] = 'Função posix_getgrgid não disponível';
        }
        
        // Tentar criar um arquivo de teste
        $arquivo_teste = $diretorio . '/teste_' . time() . '.tmp';
        $conteudo = "Teste de escrita em " . date('Y-m-d H:i:s');
        
        if (@file_put_contents($arquivo_teste, $conteudo)) {
            $resultado['teste_escrita'] = true;
            
            // Tentar ler o arquivo
            $lido = @file_get_contents($arquivo_teste);
            $resultado['detalhes']['leitura_sucesso'] = ($lido === $conteudo);
            
            // Remover o arquivo de teste
            @unlink($arquivo_teste);
            $resultado['detalhes']['remocao_sucesso'] = !file_exists($arquivo_teste);
        }
    }
    
    return $resultado;
}

// Função para obter informações do ambiente PHP
function obterInfoAmbiente() {
    return [
        'sistema_operacional' => PHP_OS,
        'versao_php' => PHP_VERSION,
        'servidor_web' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Desconhecido',
        'usuario_php' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Desconhecido',
        'diretorio_temporario' => sys_get_temp_dir(),
        'safe_mode' => ini_get('safe_mode'),
        'open_basedir' => ini_get('open_basedir'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'disable_functions' => ini_get('disable_functions')
    ];
}

try {
    // Diretórios para testar
    $diretorios = [
        'logs_padrao' => __DIR__ . '/../../logs',
        'logs_relativo' => './logs',
        'logs_absoluto' => realpath(__DIR__ . '/../../logs'),
        'diretorio_atual' => __DIR__,
        'diretorio_pai' => dirname(__DIR__),
        'temp_sistema' => sys_get_temp_dir(),
        'diretorio_raiz_web' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Desconhecido'
    ];
    
    $resultados = [];
    
    // Testar cada diretório
    foreach ($diretorios as $nome => $diretorio) {
        $resultados[$nome] = testarEscritaEmDiretorio($diretorio);
    }
    
    // Verificar configurações do servidor
    $ambiente = obterInfoAmbiente();
    
    // Tentar criar o diretório de logs se não existir
    $logs_dir = $diretorios['logs_padrao'];
    $criado = false;
    
    if (!file_exists($logs_dir)) {
        $criado = @mkdir($logs_dir, 0777, true);
        $resultados['criacao_diretorio'] = [
            'tentativa' => true,
            'sucesso' => $criado,
            'diretorio' => $logs_dir
        ];
        
        if ($criado) {
            $resultados['logs_padrao'] = testarEscritaEmDiretorio($logs_dir);
        }
    }
    
    // Tentar alterar permissões se o diretório existir mas não for gravável
    if (file_exists($logs_dir) && !is_writable($logs_dir)) {
        $permissao_alterada = @chmod($logs_dir, 0777);
        $resultados['alteracao_permissao'] = [
            'tentativa' => true,
            'sucesso' => $permissao_alterada,
            'diretorio' => $logs_dir
        ];
        
        if ($permissao_alterada) {
            $resultados['logs_padrao'] = testarEscritaEmDiretorio($logs_dir);
        }
    }
    
    // Gerar um log de teste
    $arquivo_log = ($resultados['logs_padrao']['gravavel'] ? $logs_dir : sys_get_temp_dir()) . '/teste_log_' . date('Y-m-d_H-i-s') . '.log';
    $conteudo_log = "Teste de log gerado em " . date('Y-m-d H:i:s') . "\n";
    $conteudo_log .= "Sistema: " . PHP_OS . "\n";
    $conteudo_log .= "PHP Version: " . PHP_VERSION . "\n";
    
    $log_criado = @file_put_contents($arquivo_log, $conteudo_log);
    
    $resultados['log_teste'] = [
        'arquivo' => $arquivo_log,
        'sucesso' => $log_criado !== false,
        'tamanho' => $log_criado
    ];
    
    // Preparar resposta JSON
    echo json_encode([
        'status' => 'ok',
        'diretorio_logs_padrao' => $logs_dir,
        'resultados_testes' => $resultados,
        'informacoes_ambiente' => $ambiente,
        'recomendacoes' => [
            'usar_diretorio_padrao' => $resultados['logs_padrao']['gravavel'],
            'usar_diretorio_alternativo' => $resultados['logs_padrao']['gravavel'] ? null : ($resultados['temp_sistema']['gravavel'] ? $resultados['temp_sistema']['diretorio'] : null),
            'necessita_correcao_permissoes' => file_exists($logs_dir) && !$resultados['logs_padrao']['gravavel']
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro durante os testes: ' . $e->getMessage(),
        'stack_trace' => $e->getTraceAsString()
    ]);
}
?> 