<?php
/**
 * POST /api/painel/comunicados/salvar.php  (multipart/form-data)
 *
 * Campos:
 *   id?           — se presente, edita; senão cria
 *   titulo        — obrigatório (máx 150)
 *   corpo         — obrigatório
 *   link?         — URL externa opcional
 *   destaque?     — "1" pra aparecer no banner da Home do app
 *   expira_em?    — "YYYY-MM-DDTHH:MM" ou "YYYY-MM-DD HH:MM" (vazio = nunca expira)
 *   imagem?       — arquivo jpg/png/webp até 3MB (substitui a anterior na edição)
 *   remover_imagem? — "1" remove a imagem atual sem subir outra
 *
 * Sempre salva como rascunho quando novo; publicar é ação separada
 * (alterar_status.php) pra evitar publicação acidental.
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../auth/verifica_sessao_ajax.php';
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
require_once __DIR__ . '/../../conexao.php';

MenuPermissaoService::exigirAdmin();

date_default_timezone_set('America/Cuiaba');

const COM_UPLOAD_DIR = __DIR__ . '/../../../uploads/comunicados';
const COM_UPLOAD_URL = '/uploads/comunicados';

try {
    $id       = (int) ($_POST['id'] ?? 0);
    $titulo   = trim($_POST['titulo'] ?? '');
    $corpo    = trim($_POST['corpo'] ?? '');
    $link     = trim($_POST['link'] ?? '') ?: null;
    $destaque = ($_POST['destaque'] ?? '') === '1' ? 1 : 0;
    $expira   = trim($_POST['expira_em'] ?? '');
    $remover_imagem = ($_POST['remover_imagem'] ?? '') === '1';

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Título é obrigatório (máx. 150 caracteres)']);
        exit;
    }
    if ($corpo === '') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Corpo do comunicado é obrigatório']);
        exit;
    }
    if ($link !== null && !filter_var($link, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Link inválido — use uma URL completa (https://...)']);
        exit;
    }

    // Normaliza expira_em
    $expira_sql = null;
    if ($expira !== '') {
        $expira = str_replace('T', ' ', $expira);
        $dt = DateTime::createFromFormat('Y-m-d H:i', $expira) ?: DateTime::createFromFormat('Y-m-d H:i:s', $expira);
        if (!$dt) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Data de expiração inválida']);
            exit;
        }
        $expira_sql = $dt->format('Y-m-d H:i:s');
        if ($expira_sql <= date('Y-m-d H:i:s')) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'A expiração precisa ser no futuro']);
            exit;
        }
    }

    // Se edição: carrega atual (pra imagem antiga)
    $imagem_atual = null;
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT imagem FROM comunicados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Comunicado não encontrado']);
            exit;
        }
        $imagem_atual = $row['imagem'];
    }

    // Upload de imagem (opcional)
    $imagem_nova = null;
    if (!empty($_FILES['imagem']['tmp_name']) && is_uploaded_file($_FILES['imagem']['tmp_name'])) {
        if ($_FILES['imagem']['size'] > 3 * 1024 * 1024) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Imagem muito grande (máx. 3MB)']);
            exit;
        }
        $mime = mime_content_type($_FILES['imagem']['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Formato inválido — use JPG, PNG ou WebP']);
            exit;
        }
        $nome_arquivo = 'com_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['imagem']['tmp_name'], COM_UPLOAD_DIR . '/' . $nome_arquivo)) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao salvar a imagem no servidor']);
            exit;
        }
        @chmod(COM_UPLOAD_DIR . '/' . $nome_arquivo, 0664);
        $imagem_nova = COM_UPLOAD_URL . '/' . $nome_arquivo;
    }

    // Resolve imagem final
    if ($imagem_nova !== null) {
        $imagem_final = $imagem_nova;
    } elseif ($remover_imagem) {
        $imagem_final = null;
    } else {
        $imagem_final = $imagem_atual;
    }

    if ($id > 0) {
        $stmt = $conn->prepare(
            "UPDATE comunicados
                SET titulo = ?, corpo = ?, link = ?, destaque = ?, expira_em = ?, imagem = ?
              WHERE id = ?"
        );
        $stmt->bind_param('sssissi', $titulo, $corpo, $link, $destaque, $expira_sql, $imagem_final, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $criado_por = (int) $_SESSION['usuario_id'];
        $stmt = $conn->prepare(
            "INSERT INTO comunicados (titulo, corpo, link, destaque, expira_em, imagem, status, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, 'rascunho', ?)"
        );
        $stmt->bind_param('sssissi', $titulo, $corpo, $link, $destaque, $expira_sql, $imagem_final, $criado_por);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
    }

    // Se trocou/removeu imagem, apaga o arquivo antigo do disco
    if ($imagem_atual && $imagem_atual !== $imagem_final) {
        $path_antigo = realpath(__DIR__ . '/../../../' . ltrim($imagem_atual, '/'));
        $base = realpath(COM_UPLOAD_DIR);
        if ($path_antigo && $base && strpos($path_antigo, $base) === 0) {
            @unlink($path_antigo);
        }
    }

    echo json_encode(['status' => 'ok', 'id' => $id, 'mensagem' => 'Comunicado salvo']);
} catch (Throwable $e) {
    error_log('Erro em painel/comunicados/salvar.php: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

$conn->close();
