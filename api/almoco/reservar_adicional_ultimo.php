<?php
/**
 * ENDPOINT DESATIVADO em 2026-06-29 (auditoria de cobrança).
 * Inseria em reservas_adicionais sem id_dependente, sem checagem de idade,
 * com valor da config — vetor de cobrança indevida. Mantido como tombstone.
 * Use /api/almoco/reservar_adicional.php.
 */
header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);
echo json_encode([
    'status' => 'erro',
    'mensagem' => 'Endpoint desativado. Use /api/almoco/reservar_adicional.php'
]);
