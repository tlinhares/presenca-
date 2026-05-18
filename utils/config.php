<?php
// utils/config.php

include_once(__DIR__ . '/../api/conexao.php');

function get_config($chave, $padrao = '') {
    global $conn;

    $stmt = $conn->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    if (!$stmt) {
        return $padrao;
    }

    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $stmt->bind_result($valor);
    if ($stmt->fetch()) {
        return $valor;
    }

    return $padrao;
}
