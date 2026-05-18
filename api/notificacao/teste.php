<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.z-api.io/instances/3E17B3D7D18CC0218929BAA23289AB67/token/AB6844F22F9DAA8039D056BD/send-text",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    "phone" => "5511999998888",
    "message" => "Olá, você foi convidado para testar o sistema *Presença AOM*!"
  ]),
  CURLOPT_HTTPHEADER => array(
    "content-type: application/json"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "Erro cURL: " . $err;
} else {
  echo "Resposta: " . $response;
}
