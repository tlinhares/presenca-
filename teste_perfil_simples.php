<?php
session_start();

// Simular login para teste
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nome'] = 'Usuário Teste';
$_SESSION['usuario_categoria'] = 'usuario';

echo "<h1>Teste da Página de Perfil</h1>";

echo "<h2>1. Testando conexão com banco:</h2>";
try {
    require_once 'api/conexao.php';
    echo "<p>✅ Conexão estabelecida com sucesso!</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão: " . $e->getMessage() . "</p>";
}

echo "<h2>2. Testando API buscar_dados.php:</h2>";
$url = 'http://localhost/presenca/api/usuarios/buscar_dados.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);
$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<h2>3. Testando API estatisticas_pessoais.php (almoço):</h2>";
$url = 'http://localhost/presenca/api/almoco/estatisticas_pessoais.php';
$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<h2>4. Testando API estatisticas_pessoais.php (culto):</h2>";
$url = 'http://localhost/presenca/api/culto/estatisticas_pessoais.php';
$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<h2>5. Testando API contar.php (dependentes):</h2>";
$url = 'http://localhost/presenca/api/dependentes/contar.php';
$response = file_get_contents($url, false, $context);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<p><a href='perfil.php'>Ir para a página de perfil</a></p>";
?>
