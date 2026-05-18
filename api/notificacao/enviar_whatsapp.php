<?php
function enviarWhatsapp($numero, $mensagem) {
    $url = "https://api.z-api.io/instances/SEU_ID/token/SEU_TOKEN/send-messages";

    $payload = json_encode([
        "phone" => "55" . preg_replace('/\D/', '', $numero), // limpa e adiciona DDI Brasil
        "message" => $mensagem
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $resposta = curl_exec($ch);
    curl_close($ch);

    return json_decode($resposta, true);
}
?>