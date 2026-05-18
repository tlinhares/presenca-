<?php
session_start();

echo "Sessão iniciada: " . (session_status() === PHP_SESSION_ACTIVE ? "SIM" : "NÃO") . "<br>";
echo "ID da sessão: " . session_id() . "<br>";
echo "usuario_id: " . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'NÃO DEFINIDO') . "<br>";
echo "id_usuario: " . (isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 'NÃO DEFINIDO') . "<br>";
echo "usuario_categoria: " . (isset($_SESSION['usuario_categoria']) ? $_SESSION['usuario_categoria'] : 'NÃO DEFINIDO') . "<br>";

if (!isset($_SESSION['usuario_id'])) {
    echo "<br>ERRO: Usuário não logado!";
} else {
    echo "<br>SUCESSO: Usuário logado!";
}
?>
