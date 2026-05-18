<?php
/**
 * Permission - Classe para gerenciamento de permissões
 */
class Permission {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Verifica se o usuário tem permissão para uma ação específica em um módulo
     * 
     * @param int $perfil_id ID do perfil do usuário
     * @param string $modulo Nome do módulo
     * @param string $acao Nome da ação
     * @return bool
     */
    public function check($perfil_id, $modulo, $acao) {
        $query = "SELECT permitido FROM permissoes 
                 WHERE perfil_id = :perfil_id 
                 AND modulo = :modulo 
                 AND acao = :acao";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':perfil_id', $perfil_id);
        $stmt->bindParam(':modulo', $modulo);
        $stmt->bindParam(':acao', $acao);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (bool)$row['permitido'];
        }
        
        return false;
    }
    
    /**
     * Obtém todas as permissões de um perfil
     * 
     * @param int $perfil_id ID do perfil
     * @return array
     */
    public function getPermissionsByProfile($perfil_id) {
        $query = "SELECT modulo, acao, permitido FROM permissoes 
                 WHERE perfil_id = :perfil_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':perfil_id', $perfil_id);
        $stmt->execute();
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['modulo']][$row['acao']] = (bool)$row['permitido'];
        }
        
        return $permissions;
    }
    
    /**
     * Atualiza uma permissão
     * 
     * @param int $perfil_id ID do perfil
     * @param string $modulo Nome do módulo
     * @param string $acao Nome da ação
     * @param bool $permitido Se a permissão é concedida ou não
     * @return bool
     */
    public function updatePermission($perfil_id, $modulo, $acao, $permitido) {
        // Verificar se a permissão já existe
        $query = "SELECT id FROM permissoes 
                 WHERE perfil_id = :perfil_id 
                 AND modulo = :modulo 
                 AND acao = :acao";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':perfil_id', $perfil_id);
        $stmt->bindParam(':modulo', $modulo);
        $stmt->bindParam(':acao', $acao);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Atualizar permissão existente
            $query = "UPDATE permissoes 
                     SET permitido = :permitido 
                     WHERE perfil_id = :perfil_id 
                     AND modulo = :modulo 
                     AND acao = :acao";
        } else {
            // Inserir nova permissão
            $query = "INSERT INTO permissoes (perfil_id, modulo, acao, permitido) 
                     VALUES (:perfil_id, :modulo, :acao, :permitido)";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':perfil_id', $perfil_id);
        $stmt->bindParam(':modulo', $modulo);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':permitido', $permitido, PDO::PARAM_BOOL);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém todos os módulos disponíveis no sistema
     * 
     * @return array
     */
    public function getAllModules() {
        return [
            'obras' => 'Gerenciamento de Obras',
            'financeiro' => 'Gerenciamento Financeiro',
            'cronograma' => 'Gerenciamento de Cronograma',
            'documentos' => 'Gerenciamento de Documentos',
            'usuarios' => 'Gerenciamento de Usuários'
        ];
    }
    
    /**
     * Obtém todas as ações disponíveis no sistema
     * 
     * @return array
     */
    public function getAllActions() {
        return [
            'visualizar' => 'Visualizar',
            'criar' => 'Criar',
            'editar' => 'Editar',
            'excluir' => 'Excluir'
        ];
    }
}
?>
