<?php
session_start();

// Simular login para teste
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nome'] = 'Usuário Teste';
$_SESSION['usuario_categoria'] = 'usuario';

echo "<h1>Teste das APIs</h1>";

// Testar API de buscar dados
echo "<h2>Testando API buscar_dados.php:</h2>";
$url = 'http://localhost/presenca/api/usuarios/buscar_dados.php';
$response = file_get_contents($url);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Testar API de estatísticas de almoço
echo "<h2>Testando API estatisticas_pessoais.php (almoço):</h2>";
$url = 'http://localhost/presenca/api/almoco/estatisticas_pessoais.php';
$response = file_get_contents($url);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Testar API de estatísticas de culto
echo "<h2>Testando API estatisticas_pessoais.php (culto):</h2>";
$url = 'http://localhost/presenca/api/culto/estatisticas_pessoais.php';
$response = file_get_contents($url);
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Testar API de contar dependentes
echo "<h2>Testando API contar.php (dependentes):</h2>";
$url = 'http://localhost/presenca/api/dependentes/contar.php';
$response = file_get_contents($url);
echo "<pre>" . htmlspecialchars($response) . "</pre>";
?>
