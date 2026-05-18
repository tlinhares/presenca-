<?php
/*
// Executa o verificar_e_preparar.php
$verificar = shell_exec("php " . __DIR__ . "/verificar_e_preparar.php");

// Executa o sincronizador
$executar = shell_exec("php " . __DIR__ . "/executar_sync.php");

// Salva log opcional
file_put_contents(__DIR__ . "/../../logs/sincronizacao_agendada_" . date('Y-m-d') . ".log", 
    "[" . date('Y-m-d H:i:s') . "] Execução agendada\n" .
    "Verificar e preparar:\n$verificar\n\nExecutar:\n$executar\n", FILE_APPEND);

*/


?>

<?php
// Arquivo: sincronizacao_agendada.php

// INCLUIR CONFIGURAÇÃO DE TIMEZONE PRIMEIRO
include_once(__DIR__ . '/../../config/timezone.php');

// OBTER DATA ATUAL COM TIMEZONE CORRETO
$data_hoje = date('Y-m-d');
$hora_atual = date('H:i:s');

// Log de início
$log_msg = "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização para data: $data_hoje\n";

// Executa o verificar_e_preparar.php COM PARÂMETRO DE DATA
$verificar = shell_exec("php " . __DIR__ . "/verificar_e_preparar.php " . escapeshellarg($data_hoje));

// Executa o sincronizador COM PARÂMETRO DE DATA
$executar = shell_exec("php " . __DIR__ . "/executar_sync.php " . escapeshellarg($data_hoje));

// Salva log detalhado
file_put_contents(__DIR__ . "/../../logs/sincronizacao_agendada_" . $data_hoje . ".log", 
    $log_msg .
    "Verificar e preparar:\n$verificar\n\nExecutar:\n$executar\n" .
    "========================================\n", FILE_APPEND);
?>
