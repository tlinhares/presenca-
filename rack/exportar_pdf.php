<?php
require_once __DIR__ . '/../vendor/autoload.php'; // mPDF via Composer

// Conexão
$conn = new mysqli("10.144.128.22", "tiago", "@Arcs2901", "racktables_db");
if ($conn->connect_error) {
    die("Erro ao conectar ao banco: " . $conn->connect_error);
}

// Inicializa mPDF com diretório temporário
$mpdf = new \Mpdf\Mpdf([
    'tempDir' => __DIR__ . '/tmp'
]);

// Garante que o diretório exista
if (!is_dir(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0775, true);
}

// Buscar todos os tipos distintos de objetos (via Dictionary)
$sqlTipos = "SELECT D.dict_key, D.dict_value
             FROM Object O
             JOIN Dictionary D ON O.objtype_id = D.dict_key
             GROUP BY D.dict_key
             ORDER BY D.dict_value";

$resTipos = $conn->query($sqlTipos);
if (!$resTipos) {
    die("Erro na consulta de tipos: " . $conn->error);
}

$html = "<h1 style='text-align:center;'>Relatório de Objetos RackTables</h1>";

while ($tipo = $resTipos->fetch_assoc()) {
    $html .= "<h2>Tipo: {$tipo['dict_value']}</h2>";

    $sqlObjs = "SELECT * FROM Object WHERE objtype_id = {$tipo['dict_key']} ORDER BY name";
    $resObjs = $conn->query($sqlObjs);
    if (!$resObjs) continue;

    while ($obj = $resObjs->fetch_assoc()) {
        $html .= "<div style='border:1px solid #ccc; padding:10px; margin-bottom:10px'>";
        $html .= "<strong>Nome:</strong> {$obj['name']}<br>";
        $html .= "<strong>Label:</strong> {$obj['label']}<br>";
        $html .= "<strong>Asset Nº:</strong> {$obj['asset_no']}<br>";
        $html .= "<strong>Comentário:</strong> {$obj['comment']}<br>";

        // IPs
        $sqlIPs = "SELECT INET_NTOA(A.ip) as ip, A.name, A.comment
                   FROM IPv4Allocation AL
                   JOIN IPv4Address A ON A.id = AL.ipv4addr
                   WHERE AL.object_id = {$obj['id']}";
        $resIPs = $conn->query($sqlIPs);
        if ($resIPs && $resIPs->num_rows > 0) {
            $html .= "<strong>Endereços IP:</strong><ul>";
            while ($ip = $resIPs->fetch_assoc()) {
                $html .= "<li>{$ip['ip']} - {$ip['name']} ({$ip['comment']})</li>";
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
}

// Gerar PDF
$caminho = __DIR__ . "/../tickets/exportacao_objetos.pdf";
$html = mb_convert_encoding($html, 'UTF-8', 'auto');
$mpdf->WriteHTML($html);
$mpdf->Output($caminho, \Mpdf\Output\Destination::FILE);

echo "PDF gerado com sucesso: <a href='/tickets/exportacao_objetos.pdf' target='_blank'>Abrir PDF</a>";
