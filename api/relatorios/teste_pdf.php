<?php
require_once __DIR__ . '/../../vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf([
    'tempDir' => __DIR__ . '/../../../tmp/mpdf'
]);

$mpdf->WriteHTML('<h1>PDF gerado com sucesso!</h1>');
$mpdf->Output();