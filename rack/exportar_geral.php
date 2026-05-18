<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../utils/env.php';

// Conexão (banco RackTables — credenciais em .env)
$conn = new mysqli(env('RACK_DB_HOST', '10.144.128.22'), env('RACK_DB_USER', 'tiago'), env('RACK_DB_PASS', ''), env('RACK_DB_NAME', 'racktables_db'));
if ($conn->connect_error) {
    die("Erro ao conectar ao banco: " . $conn->connect_error);
}

// Inicializa mPDF
$mpdf = new \Mpdf\Mpdf([
    'tempDir' => __DIR__ . '/tmp'
]);

if (!is_dir(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0775, true);
}

$html = "<h1 style='text-align:center;'>Relatório Geral de Objetos</h1>";

// Buscar todos os objetos
$sqlObjs = "SELECT O.*, R.name AS rack_name, R.location_name, R.row_name
            FROM Object O
            LEFT JOIN RackSpace RS ON RS.object_id = O.id
            LEFT JOIN Rack R ON R.id = RS.rack_id
            ORDER BY O.name";

$resObjs = $conn->query($sqlObjs);
if (!$resObjs) {
    die("Erro na consulta de objetos: " . $conn->error);
}

$ids_processados = [];

while ($obj = $resObjs->fetch_assoc()) {
    if (in_array($obj['id'], $ids_processados)) continue;
    $ids_processados[] = $obj['id'];

    $html .= "<div style='border:1px solid #ccc; padding:10px; margin-bottom:10px'>";
    $html .= "<strong>Nome:</strong> {$obj['name']}<br>";
    $html .= "<strong>Label:</strong> {$obj['label']}<br>";
    $html .= "<strong>Asset Nº:</strong> {$obj['asset_no']}<br>";
    $html .= "<strong>Comentário:</strong> {$obj['comment']}<br>";

    if (!empty($obj['rack_name'])) {
        $html .= "<strong>Rack:</strong> {$obj['rack_name']} ({$obj['location_name']} / {$obj['row_name']})<br>";
    } else {
        $html .= "<strong>Rack:</strong> Não alocado<br>";
    }

    // IPs (com interface)
    $sqlIPs = "SELECT 
                  INET_NTOA(A.ip) AS ip,
                  A.name AS dns,
                  A.comment,
                  P.name AS interface
               FROM IPv4Allocation AL
               JOIN IPv4Address A ON A.id = AL.ipv4addr
               LEFT JOIN Port P ON P.id = AL.object_port_id
               WHERE AL.object_id = {$obj['id']}
               ORDER BY A.ip";
    $resIPs = $conn->query($sqlIPs);
    if ($resIPs && $resIPs->num_rows > 0) {
        $html .= "<strong>Endereços IP:</strong><ul>";
        while ($ip = $resIPs->fetch_assoc()) {
            $ipStr = "{$ip['ip']}";
            if (!empty($ip['dns'])) $ipStr .= " ({$ip['dns']})";
            if (!empty($ip['interface'])) $ipStr .= " - Interface: {$ip['interface']}";
            if (!empty($ip['comment'])) $ipStr .= " - {$ip['comment']}";
            $html .= "<li>$ipStr</li>";
        }
        $html .= "</ul>";
    }

    // Atributos
    $sqlAttr = "SELECT A.name, V.string_value
                FROM AttributeValue V
                JOIN Attribute A ON A.id = V.attr_id
                WHERE V.object_id = {$obj['id']}";
    $resAttr = $conn->query($sqlAttr);
    if ($resAttr && $resAttr->num_rows > 0) {
        $html .= "<strong>Atributos:</strong><ul>";
        while ($attr = $resAttr->fetch_assoc()) {
            $html .= "<li>{$attr['name']}: {$attr['string_value']}</li>";
        }
        $html .= "</ul>";
    }

    // Portas
    $sqlPortas = "SELECT name, label, type FROM Port WHERE object_id = {$obj['id']}";
    $resPortas = $conn->query($sqlPortas);
    if ($resPortas && $resPortas->num_rows > 0) {
        $html .= "<strong>Portas:</strong><ul>";
        while ($porta = $resPortas->fetch_assoc()) {
            $html .= "<li>{$porta['name']} ({$porta['label']}) - Tipo: {$porta['type']}</li>";
        }
        $html .= "</ul>";
    }

    $html .= "</div>";
}

// Corrigir encoding
$html = '<meta charset="UTF-8">' . $html;
$html = mb_convert_encoding($html, 'UTF-8', 'auto');

// Gerar PDF
$caminho = __DIR__ . "/../tickets/exportacao_geral.pdf";
$mpdf->WriteHTML($html);
$mpdf->Output($caminho, \Mpdf\Output\Destination::FILE);

echo "PDF gerado com sucesso: <a href='/tickets/exportacao_geral.pdf' target='_blank'>Abrir PDF</a>";
