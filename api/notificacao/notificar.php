<?php

$request = new HttpRequest();
$request->setUrl('https://api.z-api.io/instances/3E17B3D7D18CC0218929BAA23289AB67/token/AB6844F22F9DAA8039D056BD/send-text');
$request->setMethod(HTTP_METH_POST);

$request->setHeaders(array(
  'content-type' => 'application/json',
  'client-token' => '{{security-token}}'
));

$request->setBody('{"phone": "5511999998888", "message": "Welcome to *Z-API*"}');

try {
  $response = $request->send();

  echo $response->getBody();
} catch (HttpException $ex) {
  echo $ex;
}