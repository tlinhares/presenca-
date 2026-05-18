<?php
// Script de teste para verificar a API de dependentes
session_start();

// Simular sessão de admin
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_categoria'] = 'admin';

echo "Testando API de dependentes...\n";

// Testar listar_por_usuario.php
echo "\n1. Testando listar_por_usuario.php:\n";
$_GET['usuario_id'] = 22246;
ob_start();
include 'api/dependentes/listar_por_usuario.php';
$output = ob_get_clean();
echo "Resposta: " . $output . "\n";

// Testar buscar.php
echo "\n2. Testando buscar.php:\n";
$_GET['id'] = 1;
ob_start();
include 'api/dependentes/buscar.php';
$output = ob_get_clean();
echo "Resposta: " . $output . "\n";

echo "\nTeste concluído.\n";
?>
