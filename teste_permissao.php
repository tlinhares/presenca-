<?php
echo "<h2>Teste de Permissões - Diretório Logs</h2>";

$logDir = __DIR__ . "/logs";
$logFile = $logDir . "/teste_permissao_" . date('Y-m-d_H-i-s') . ".log";

echo "<h3>Informações do Diretório:</h3>";
echo "<strong>Diretório:</strong> " . $logDir . "<br>";
echo "<strong>Existe:</strong> " . (is_dir($logDir) ? 'SIM' : 'NÃO') . "<br>";
echo "<strong>Legível:</strong> " . (is_readable($logDir) ? 'SIM' : 'NÃO') . "<br>";
echo "<strong>Gravável:</strong> " . (is_writable($logDir) ? 'SIM' : 'NÃO') . "<br>";

if (is_dir($logDir)) {
    $perms = fileperms($logDir);
    echo "<strong>Permissões:</strong> " . substr(sprintf('%o', $perms), -4) . "<br>";
    
    $owner = posix_getpwuid(fileowner($logDir));
    $group = posix_getgrgid(filegroup($logDir));
    echo "<strong>Dono:</strong> " . $owner['name'] . "<br>";
    echo "<strong>Grupo:</strong> " . $group['name'] . "<br>";
}

echo "<h3>Informações do PHP:</h3>";
echo "<strong>Usuário do PHP:</strong> " . get_current_user() . "<br>";
echo "<strong>UID do PHP:</strong> " . posix_getuid() . "<br>";
echo "<strong>GID do PHP:</strong> " . posix_getgid() . "<br>";

echo "<h3>Teste de Criação de Arquivo:</h3>";

// Tentar criar o diretório se não existir
if (!is_dir($logDir)) {
    echo "Tentando criar diretório...<br>";
    $criado = mkdir($logDir, 0755, true);
    echo "<strong>Diretório criado:</strong> " . ($criado ? 'SIM' : 'NÃO') . "<br>";
    if (!$criado) {
        echo "<strong>Erro:</strong> " . error_get_last()['message'] . "<br>";
    }
}

// Tentar criar o arquivo
echo "<br>Tentando criar arquivo: " . $logFile . "<br>";
$conteudo = "Teste de permissão - " . date('Y-m-d H:i:s') . "\n";
$resultado = file_put_contents($logFile, $conteudo);

if ($resultado !== false) {
    echo "<strong style='color: green;'>SUCESSO!</strong> Arquivo criado com " . $resultado . " bytes<br>";
    
    // Verificar se o arquivo foi realmente criado
    if (file_exists($logFile)) {
        echo "<strong>Arquivo existe:</strong> SIM<br>";
        echo "<strong>Tamanho:</strong> " . filesize($logFile) . " bytes<br>";
        echo "<strong>Permissões:</strong> " . substr(sprintf('%o', fileperms($logFile)), -4) . "<br>";
        
        // Tentar ler o arquivo
        $lido = file_get_contents($logFile);
        echo "<strong>Conteúdo lido:</strong> " . htmlspecialchars($lido) . "<br>";
        
        // Limpar o arquivo de teste
        unlink($logFile);
        echo "<strong>Arquivo de teste removido</strong><br>";
    }
} else {
    echo "<strong style='color: red;'>ERRO!</strong> Não foi possível criar o arquivo<br>";
    echo "<strong>Erro:</strong> " . error_get_last()['message'] . "<br>";
}

echo "<h3>Restrições do PHP:</h3>";
$open_basedir = ini_get('open_basedir');
echo "<strong>open_basedir:</strong> " . ($open_basedir ? $open_basedir : 'NÃO DEFINIDO') . "<br>";

$safe_mode = ini_get('safe_mode');
echo "<strong>safe_mode:</strong> " . ($safe_mode ? 'ATIVO' : 'INATIVO') . "<br>";

echo "<h3>Teste Final:</h3>";
echo "Se tudo estiver OK acima, o problema pode estar em:<br>";
echo "- SELinux ou AppArmor bloqueando<br>";
echo "- Restrições do servidor web<br>";
echo "- Arquivo de log já existente com dono diferente<br>";
?> 