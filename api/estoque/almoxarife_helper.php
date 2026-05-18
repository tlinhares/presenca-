<?php
/**
 * Helpers de almoxarife — usuários cadastrados em estoque_responsaveis
 * (tipo 'responsavel' ou 'auxiliar') ou administradores do sistema.
 *
 * Carregar com:
 *   require_once __DIR__ . '/../api/estoque/almoxarife_helper.php';
 */

if (!function_exists('eh_almoxarife')) {
    /**
     * Verifica se um usuário pode atuar como almoxarife.
     * Admin do sistema é considerado almoxarife por padrão.
     */
    function eh_almoxarife(mysqli $conn, int $usuario_id, bool $eh_admin = false): bool
    {
        if ($eh_admin) {
            return true;
        }
        if ($usuario_id <= 0) {
            return false;
        }
        $stmt = $conn->prepare(
            "SELECT 1 FROM estoque_responsaveis
              WHERE id_usuario = ? AND ativo = 1 LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $tem = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $tem;
    }

    /**
     * Retorna os IDs dos departamentos onde o usuário é responsável/auxiliar.
     * Admin retorna todos.
     */
    function departamentos_almoxarife(mysqli $conn, int $usuario_id, bool $eh_admin = false): array
    {
        if ($eh_admin) {
            $r = $conn->query("SELECT id FROM estoque_departamentos WHERE ativo = 1");
            $ids = [];
            while ($row = $r->fetch_assoc()) {
                $ids[] = (int)$row['id'];
            }
            return $ids;
        }
        if ($usuario_id <= 0) {
            return [];
        }
        $stmt = $conn->prepare(
            "SELECT DISTINCT id_departamento FROM estoque_responsaveis
              WHERE id_usuario = ? AND ativo = 1"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $ids = [];
        while ($row = $r->fetch_assoc()) {
            $ids[] = (int)$row['id_departamento'];
        }
        $stmt->close();
        return $ids;
    }
}
