<?php
/**
 * API para capturar dados do dispositivo facial
 * Baseado no exemplo fetch_logs.php
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../../config/timezone.php';

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

function http_digest($method, $url, $user, $pass, $postFields = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postFields) ? http_build_query($postFields) : $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $pass);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [$code, $resp, $err];
}

function logLeituraFacial($mensagem) {
    $logFile = __DIR__ . '/../../logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $mensagem" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Parâmetros do dispositivo
$ip = $_GET['ip'] ?? '192.168.3.87';
$user = $_GET['user'] ?? 'admin';
$pass = $_GET['pass'] ?? 'acesso1234';

$now = time();
$end = isset($_GET['end']) ? (int)$_GET['end'] : $now;
$start = isset($_GET['start']) ? (int)$_GET['start'] : ($now - 5*60);

if ($start > $end) {
    $t = $start;
    $start = $end;
    $end = $t;
}

$fmt = 'Y-m-d H:i:s';
$times = [
    ['label' => 'local', 'start' => date($fmt, $start), 'end' => date($fmt, $end)],
    ['label' => 'gmt', 'start' => gmdate($fmt, $start), 'end' => gmdate($fmt, $end)]
];

// Nomes de registros para tentar
$namesToTry = [
    'AccessControlEvent', 'AccessControlFaceRec', 'AccessControlCardRec',
    'AccessControlLog', 'Event', 'Record', 'FaceRecord', 'CardRecord'
];

$attempts = [];
$success = null;
$dataOut = null;
$methods = ['GET', 'POST'];

// Tentar diferentes combinações
foreach ($namesToTry as $recordName) {
    foreach ($times as $t) {
        foreach ($methods as $method) {
            $base = "http://{$ip}/cgi-bin/recordFinder.cgi?action=find";
            
            if ($method === 'GET') {
                $url = $base . "&name={$recordName}&StartTime=" . urlencode($t['start']) . "&EndTime=" . urlencode($t['end']);
                [$code, $resp, $err] = http_digest('GET', $url, $user, $pass);
                $attempts[] = [
                    'method' => $method,
                    'tz' => $t['label'],
                    'name' => $recordName,
                    'url' => $url,
                    'code' => $code,
                    'err' => $err,
                    'sample' => substr(trim((string)$resp), 0, 160)
                ];
            } else {
                $url = $base;
                $post = [
                    'name' => $recordName,
                    'StartTime' => $t['start'],
                    'EndTime' => $t['end'],
                    'Page' => 1,
                    'PageSize' => 50,
                    'Rows' => 50
                ];
                [$code, $resp, $err] = http_digest('POST', $url, $user, $pass, $post);
                $attempts[] = [
                    'method' => $method,
                    'tz' => $t['label'],
                    'name' => $recordName,
                    'url' => $url,
                    'post' => $post,
                    'code' => $code,
                    'err' => $err,
                    'sample' => substr(trim((string)$resp), 0, 160)
                ];
            }

            // Verificar se obteve sucesso
            if ($resp !== false && $code >= 200 && $code < 300) {
                $trim = trim((string)$resp);
                
                if ($trim === '') {
                    $success = end($attempts);
                    $dataOut = ['ok' => true, 'format' => 'empty', 'data' => []];
                    break 3;
                }
                
                if ($trim[0] === '{' || $trim[0] === '[') {
                    $json = json_decode($trim, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $success = end($attempts);
                        $dataOut = ['ok' => true, 'format' => 'json', 'data' => $json];
                        break 3;
                    }
                }
                
                if ($trim[0] === '<') {
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($trim);
                    if ($xml !== false) {
                        $json = json_decode(json_encode($xml), true);
                        $success = end($attempts);
                        $dataOut = ['ok' => true, 'format' => 'xml', 'data' => $json];
                        break 3;
                    }
                }
                
                $success = end($attempts);
                $dataOut = ['ok' => true, 'format' => 'text', 'raw' => $trim];
                break 3;
            }
        }
    }
}

if ($success) {
    // Processar eventos capturados
    $eventos = processarEventos($dataOut);
    $logs = [];
    $estatisticas = [
        'total_leituras' => count($eventos),
        'dispositivos_ativos' => 1,
        'ultima_leitura' => count($eventos) > 0 ? $eventos[0]['time'] : 'Nenhuma',
        'status_sistema' => 'Online'
    ];
    
    // Converter eventos para logs
    foreach ($eventos as $evento) {
        $logs[] = [
            'timestamp' => $evento['time'],
            'mensagem' => "Usuário {$evento['user_id']} - {$evento['result']}",
            'tipo' => $evento['result'] === 'Pass' ? 'success' : 'warning'
        ];
    }
    
    // Log da captura
    logLeituraFacial("Captura realizada com sucesso: " . count($eventos) . " eventos");
    
    respond(200, [
        'success' => true,
        'message' => 'Dados capturados com sucesso',
        'estatisticas' => $estatisticas,
        'logs' => $logs,
        'eventos' => $eventos,
        'used' => [
            'method' => $success['method'],
            'timezone_format' => $success['tz'],
            'name' => $success['name']
        ]
    ]);
}

// Se chegou aqui, falhou em todas as tentativas
logLeituraFacial("Falha na captura: " . json_encode($attempts));
respond(400, [
    'success' => false,
    'message' => 'O dispositivo retornou erro em todas as tentativas',
    'estatisticas' => [
        'total_leituras' => 0,
        'dispositivos_ativos' => 0,
        'ultima_leitura' => 'Nenhuma',
        'status_sistema' => 'Offline'
    ],
    'logs' => [[
        'timestamp' => date('H:i:s'),
        'mensagem' => 'Falha na conexão com o dispositivo',
        'tipo' => 'error'
    ]],
    'tried' => $attempts
]);

function processarEventos($dataOut) {
    if (!$dataOut || $dataOut['format'] === 'empty') {
        return [];
    }
    
    $eventos = [];
    
    if ($dataOut['format'] === 'json' && $dataOut['data']) {
        $data = $dataOut['data'];
        
        // Tentar encontrar array de eventos
        $arr = [];
        if (is_array($data)) {
            $arr = $data;
        } else if (isset($data['Events']) && is_array($data['Events'])) {
            $arr = $data['Events'];
        } else if (isset($data['Event']) && is_array($data['Event'])) {
            $arr = $data['Event'];
        } else if (isset($data['Items']) && is_array($data['Items'])) {
            $arr = $data['Items'];
        } else if (isset($data['Records']) && is_array($data['Records'])) {
            $arr = $data['Records'];
        }
        
        foreach ($arr as $item) {
            $eventos[] = mapearEvento($item);
        }
    }
    
    return $eventos;
}

function mapearEvento($x) {
    $o = is_array($x) ? $x : ['value' => $x];
    
    return [
        'user_id' => $o['UserID'] ?? $o['PersonID'] ?? $o['userId'] ?? $o['EmployeeNo'] ?? $o['employee'] ?? null,
        'card' => $o['CardNo'] ?? $o['CardID'] ?? $o['card'] ?? null,
        'face' => $o['FaceID'] ?? $o['face'] ?? null,
        'event_type' => $o['EventType'] ?? $o['eventType'] ?? $o['Type'] ?? $o['type'] ?? null,
        'result' => $o['Pass'] ?? $o['Result'] ?? $o['result'] ?? null,
        'time' => $o['Time'] ?? $o['EventTime'] ?? $o['time'] ?? $o['Timestamp'] ?? $o['timestamp'] ?? null,
        'raw' => $o
    ];
}
?>
