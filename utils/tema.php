<?php
/**
 * Helper de tema (claro/escuro) renderizado NO SERVIDOR.
 *
 * Lê usuarios.tema do usuário logado e devolve 'dark' ou 'light'. As páginas
 * usam isso pra já renderizar <html class="dark"> — elimina o "flash" de tema
 * errado que acontecia quando o tema era aplicado via AJAX/localStorage depois
 * da primeira pintura.
 *
 * Contrato do tema no sistema (todas as páginas com claro/escuro):
 *   - Fonte da verdade: usuarios.tema (persistido via api/usuarios/salvar_tema.php)
 *   - Render: classe `dark` no <html> (padrão Tailwind darkMode:"class")
 *   - Toggle JS: alterna a classe, salva no servidor E em localStorage('theme')
 *     (o localStorage serve só de espelho para transições entre páginas)
 */
function tema_usuario(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $uid = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($uid <= 0) {
        return 'light';
    }

    // Cache por request
    static $cache = [];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    require_once __DIR__ . '/../api/conexao.php';
    global $conn;

    $tema = 'light';
    try {
        $stmt = $conn->prepare("SELECT tema FROM usuarios WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($r && in_array($r['tema'], ['light', 'dark'], true)) {
                $tema = $r['tema'];
            }
        }
    } catch (Throwable $e) {
        error_log('tema_usuario: ' . $e->getMessage());
    }

    return $cache[$uid] = $tema;
}

/**
 * Atributos prontos pra tag <html>: class="dark" (ou nada) + data-theme.
 */
function tema_html_attrs(): string
{
    $tema = tema_usuario();
    return $tema === 'dark' ? 'class="dark" data-theme="dark"' : 'data-theme="light"';
}
