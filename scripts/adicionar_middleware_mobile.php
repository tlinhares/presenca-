<?php
/**
 * Script para Adicionar Middleware Mobile em Todas as APIs
 * 
 * Este script adiciona automaticamente o middleware de autenticação mobile
 * em todas as APIs que ainda não têm.
 * 
 * USO:
 *   php scripts/adicionar_middleware_mobile.php
 * 
 * ATENÇÃO: Faça backup antes de executar!
 */

$baseDir = __DIR__ . '/../api';
$middlewareCode = <<<'PHP'
// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.']);
        exit;
    }
}
PHP;

$corsHeaders = <<<'PHP'
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
PHP;

function hasMobileMiddleware($content) {
    return strpos($content, 'MobileAuthMiddleware') !== false || 
           strpos($content, 'mobile_auth.php') !== false;
}

function hasCorsHeaders($content) {
    return strpos($content, 'Access-Control-Allow-Origin') !== false;
}

function addMiddlewareToFile($filePath) {
    global $middlewareCode, $corsHeaders;
    
    $content = file_get_contents($filePath);
    
    // Pular se já tem middleware
    if (hasMobileMiddleware($content)) {
        return false;
    }
    
    // Pular arquivos de instalação e testes
    if (strpos($filePath, 'instalar') !== false || 
        strpos($filePath, 'teste') !== false ||
        strpos($filePath, 'test_') !== false) {
        return false;
    }
    
    $lines = explode("\n", $content);
    $newLines = [];
    $headersAdded = false;
    $sessionStarted = false;
    $middlewareAdded = false;
    
    foreach ($lines as $i => $line) {
        // Adicionar headers CORS após <?php
        if ($i === 0 && trim($line) === '<?php') {
            $newLines[] = $line;
            if (!hasCorsHeaders($content)) {
                $newLines[] = '';
                foreach (explode("\n", $corsHeaders) as $headerLine) {
                    $newLines[] = $headerLine;
                }
                $headersAdded = true;
            }
            continue;
        }
        
        // Pular session_start() antigo se existir
        if (preg_match('/^\s*session_start\s*\(/i', $line)) {
            continue;
        }
        
        // Adicionar middleware após includes e antes da lógica
        if (!$middlewareAdded && 
            (strpos($line, 'include') !== false || 
             strpos($line, 'require') !== false ||
             strpos($line, 'conexao.php') !== false)) {
            $newLines[] = $line;
            // Adicionar middleware após alguns includes
            if (strpos($line, 'conexao.php') !== false || 
                strpos($line, 'config.php') !== false) {
                $newLines[] = '';
                foreach (explode("\n", $middlewareCode) as $middlewareLine) {
                    $newLines[] = $middlewareLine;
                }
                $middlewareAdded = true;
            }
            continue;
        }
        
        // Se chegou na verificação de sessão antiga, substituir
        if (!$middlewareAdded && 
            (strpos($line, '$_SESSION[\'usuario_id\']') !== false ||
             strpos($line, 'verifica_sessao') !== false)) {
            // Adicionar middleware antes da verificação antiga
            if (!$middlewareAdded) {
                foreach (explode("\n", $middlewareCode) as $middlewareLine) {
                    $newLines[] = $middlewareLine;
                }
                $middlewareAdded = true;
            }
            // Pular linha antiga de verificação
            continue;
        }
        
        $newLines[] = $line;
    }
    
    // Se não adicionou middleware ainda, adicionar no início após includes
    if (!$middlewareAdded) {
        $finalLines = [];
        $foundInclude = false;
        foreach ($newLines as $line) {
            $finalLines[] = $line;
            if (!$foundInclude && strpos($line, 'include') !== false) {
                $foundInclude = true;
            }
            if ($foundInclude && !$middlewareAdded && 
                (trim($line) === '' || strpos($line, 'include') === false)) {
                foreach (explode("\n", $middlewareCode) as $middlewareLine) {
                    $finalLines[] = $middlewareLine;
                }
                $middlewareAdded = true;
            }
        }
        $newLines = $finalLines;
    }
    
    $newContent = implode("\n", $newLines);
    
    // Backup do arquivo original
    $backupPath = $filePath . '.backup.' . date('YmdHis');
    copy($filePath, $backupPath);
    
    // Salvar novo conteúdo
    file_put_contents($filePath, $newContent);
    
    return true;
}

function scanDirectory($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// Executar
echo "🔍 Escaneando APIs...\n";
$apiFiles = scanDirectory($baseDir);

// Filtrar apenas APIs (não incluir mobile/auth que já tem)
$apiFiles = array_filter($apiFiles, function($file) {
    return strpos($file, '/mobile/auth/') === false &&
           strpos($file, '/mobile/utils/') === false;
});

echo "📋 Encontradas " . count($apiFiles) . " APIs\n\n";

$modified = 0;
$skipped = 0;
$errors = 0;

foreach ($apiFiles as $file) {
    $relativePath = str_replace(__DIR__ . '/../', '', $file);
    
    try {
        if (addMiddlewareToFile($file)) {
            echo "✅ Modificado: $relativePath\n";
            $modified++;
        } else {
            echo "⏭️  Pulado (já tem middleware): $relativePath\n";
            $skipped++;
        }
    } catch (Exception $e) {
        echo "❌ Erro em $relativePath: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "📊 Resumo:\n";
echo "   ✅ Modificadas: $modified\n";
echo "   ⏭️  Puladas: $skipped\n";
echo "   ❌ Erros: $errors\n";
echo "\n";
echo "⚠️  IMPORTANTE: Revise os arquivos modificados antes de fazer commit!\n";
echo "   Backups foram criados com extensão .backup.YYYYMMDDHHIISS\n";

?>
