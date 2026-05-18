<?php
require_once __DIR__ . '/config.php';

function run_sqlcmd(string $tsql, int &$exitCode = 0): string {
    $cmd = sprintf(
        "%s -S %s -d %s -U %s -P %s -C -Q %s -r 1 -b -y 0 -Y 0 2>&1",
        escapeshellarg(SQLCMD),
        escapeshellarg(MSSQL_HOST),
        escapeshellarg(MSSQL_DB),
        escapeshellarg(MSSQL_USER),
        escapeshellarg(MSSQL_PASS),
        escapeshellarg($tsql)
    );
    $out = [];
    exec($cmd, $out, $exitCode);
    return trim(implode("\n", $out));
}

function extract_json(string $raw): ?array {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw ?? '');
    if ($raw === '') return [];
    if (!in_array(substr($raw, 0, 1), ['[','{']) && preg_match('~(\{.*\}|\[.*\])~s', $raw, $m)) {
        $raw = $m[1];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

header('Content-Type: application/json; charset=utf-8');

$cpf   = isset($_GET['cpf']) ? preg_replace('/\D+/', '', $_GET['cpf']) : null;
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$size  = isset($_GET['size']) ? max(1, min(2000, (int)$_GET['size'])) : 500; // padrão 500
$offset = ($page - 1) * $size;

$baseWhere = "ee.id_status = '128'";
if ($cpf) $baseWhere .= " AND e.code_national = '$cpf'";

try {
    // 1) DATA (paginado quando não há CPF)
    $tsqlData = "
    SET NOCOUNT ON;
    SELECT 
        e.entity_code      AS Numero_Entidade,
        e.name_full        AS Nome_Funcionario,
        e.enrollment_code  AS Numero_Funcionario_Entidade,
        e.code_national    AS CPF
    FROM v_employee e
    INNER JOIN v_enrollment_employee ee 
        ON e.id_enrollment = ee.id_enrollment
    WHERE $baseWhere
    ORDER BY e.name_full
    " . ($cpf ? "" : "OFFSET $offset ROWS FETCH NEXT $size ROWS ONLY") . "
    FOR JSON PATH
    ";

    $codeData = 0;
    $rawData  = run_sqlcmd($tsqlData, $codeData);
    $data     = extract_json($rawData);
    if ($data === null) {
        http_response_code(200);
        echo json_encode(['ok'=>false,'error'=>'JSON inválido do sqlcmd (data).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2) TOTAL (apenas quando não há CPF)
    $total = null;
    if (!$cpf) {
        $tsqlTotal = "
        SET NOCOUNT ON;
        SELECT COUNT(*) AS total
        FROM v_employee e
        INNER JOIN v_enrollment_employee ee 
            ON e.id_enrollment = ee.id_enrollment
        WHERE $baseWhere
        FOR JSON PATH, WITHOUT_ARRAY_WRAPPER
        ";
        $codeTot = 0;
        $rawTot  = run_sqlcmd($tsqlTotal, $codeTot);
        $totArr  = extract_json($rawTot);
        if (is_array($totArr) && isset($totArr['total'])) {
            $total = (int)$totArr['total'];
        }
    }

    http_response_code($codeData === 0 ? 200 : 500);
    echo json_encode([
        'ok'    => $codeData === 0,
        'page'  => $cpf ? null : $page,
        'size'  => $cpf ? null : $size,
        'total' => $cpf ? count($data) : $total,
        'count' => count($data),
        'data'  => $data,
        'error' => $codeData === 0 ? null : mb_strimwidth($rawData,0,500,'...')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
