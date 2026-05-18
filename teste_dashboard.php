<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Simular login para teste
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nome'] = 'Usuário Teste';
$_SESSION['usuario_categoria'] = 'admin'; // Testar como admin

echo "<h1>Teste do Dashboard</h1>";
echo "<p>Usuário: " . $_SESSION['usuario_nome'] . "</p>";
echo "<p>Categoria: " . $_SESSION['usuario_categoria'] . "</p>";
echo "<p><a href='dashboard.php'>Ir para Dashboard</a></p>";
?>

