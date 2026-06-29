<?php
/**
 * ENDPOINT DESATIVADO em 2026-06-29 (auditoria de cobrança).
 * Este era um endpoint legado que inseria reservas sem valor e sem validações.
 * Mantido aqui apenas como tombstone — qualquer chamada recebe HTTP 410.
 * Use /api/almoco/reservar.php.
 */
header('Content-Type: application/json; charset=UTF-8');
http_response_code(410); // Gone
echo json_encode([
    'status' => 'erro',
    'mensagem' => 'Endpoint desativado. Use /api/almoco/reservar.php'
]);
