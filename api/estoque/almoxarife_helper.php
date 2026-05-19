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
     * Verifica se um usuário pode atuar como almoxarife (visão completa
     * de requisições e demais ações administrativas do estoque).
     *
     * Considera positivo se QUALQUER uma das condições for verdadeira:
     *  1) Admin do sistema ($eh_admin = true)
     *  2) Cadastrado em estoque_responsaveis com ativo = 1
     *  3) Pertence a algum grupo (usuario_grupos) que tenha permissão ao
     *     menu 'estoque_autorizar_requisicoes' — este menu é a "chave"
     *     do perfil completo (ex.: grupo "Estoque - Administrador").
     */
    function eh_almoxarife(mysqli $conn, int $usuario_id, bool $eh_admin = false): bool
    {
        if ($eh_admin) {
            return true;
        }
        if ($usuario_id <= 0) {
            return false;
        }

        // 1) Cadastro direto em estoque_responsaveis
        $stmt = $conn->prepare(
            "SELECT 1 FROM estoque_responsaveis
              WHERE id_usuario = ? AND ativo = 1 LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $tem = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($tem) {
                return true;
            }
        }

        // 2) Acesso ao menu estoque_autorizar_requisicoes via grupo
        $stmt = $conn->prepare(
            "SELECT 1
               FROM usuario_grupos ug
               JOIN grupo_menus gm ON gm.grupo_id = ug.grupo_id
               JOIN menus m ON m.id = gm.menu_id
              WHERE ug.usuario_id = ?
                AND m.codigo = 'estoque_autorizar_requisicoes'
                AND m.ativo = 1
              LIMIT 1"
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
