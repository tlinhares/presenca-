<?php
/**
 * Model para gerenciamento de obras
 */
class ObraModel {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtém todas as obras cadastradas
     * 
     * @return array
     */
    public function getAll() {
        $query = "SELECT o.*, c.nome as cliente_nome, s.nome as status_nome 
                 FROM obras o 
                 LEFT JOIN clientes c ON o.cliente_id = c.id 
                 LEFT JOIN status_obra s ON o.status_id = s.id 
                 ORDER BY o.id DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém uma obra pelo ID
     * 
     * @param int $id ID da obra
     * @return array
     */
    public function getById($id) {
        $query = "SELECT o.*, c.nome as cliente_nome, s.nome as status_nome 
                 FROM obras o 
                 LEFT JOIN clientes c ON o.cliente_id = c.id 
                 LEFT JOIN status_obra s ON o.status_id = s.id 
                 WHERE o.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cria uma nova obra
     * 
     * @param array $data Dados da obra
     * @return bool
     */
    public function create($data) {
        $query = "INSERT INTO obras (nome, descricao, cliente_id, endereco_obra, 
                                    data_inicio_prevista, data_fim_prevista, status_id, orcamento_total) 
                 VALUES (:nome, :descricao, :cliente_id, :endereco_obra, 
                        :data_inicio_prevista, :data_fim_prevista, :status_id, :orcamento_total)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitização e binding dos parâmetros
        $nome = htmlspecialchars(strip_tags($data['nome']));
        $descricao = htmlspecialchars(strip_tags($data['descricao']));
        $endereco_obra = htmlspecialchars(strip_tags($data['endereco_obra']));
        $orcamento_total = str_replace(',', '.', $data['orcamento_total']);
        
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':cliente_id', $data['cliente_id']);
        $stmt->bindParam(':endereco_obra', $endereco_obra);
        $stmt->bindParam(':data_inicio_prevista', $data['data_inicio_prevista']);
        $stmt->bindParam(':data_fim_prevista', $data['data_fim_prevista']);
        $stmt->bindParam(':status_id', $data['status_id']);
        $stmt->bindParam(':orcamento_total', $orcamento_total);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Atualiza uma obra existente
     * 
     * @param int $id ID da obra
     * @param array $data Dados da obra
     * @return bool
     */
    public function update($id, $data) {
        $query = "UPDATE obras 
                 SET nome = :nome, 
                     descricao = :descricao, 
                     cliente_id = :cliente_id, 
                     endereco_obra = :endereco_obra, 
                     data_inicio_prevista = :data_inicio_prevista, 
                     data_fim_prevista = :data_fim_prevista, 
                     status_id = :status_id, 
                     orcamento_total = :orcamento_total 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitização e binding dos parâmetros
        $nome = htmlspecialchars(strip_tags($data['nome']));
        $descricao = htmlspecialchars(strip_tags($data['descricao']));
        $endereco_obra = htmlspecialchars(strip_tags($data['endereco_obra']));
        $orcamento_total = str_replace(',', '.', $data['orcamento_total']);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':cliente_id', $data['cliente_id']);
        $stmt->bindParam(':endereco_obra', $endereco_obra);
        $stmt->bindParam(':data_inicio_prevista', $data['data_inicio_prevista']);
        $stmt->bindParam(':data_fim_prevista', $data['data_fim_prevista']);
        $stmt->bindParam(':status_id', $data['status_id']);
        $stmt->bindParam(':orcamento_total', $orcamento_total);
        
        return $stmt->execute();
    }
    
    /**
     * Exclui uma obra
     * 
     * @param int $id ID da obra
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM obras WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém todos os status de obra disponíveis
     * 
     * @return array
     */
    public function getAllStatus() {
        $query = "SELECT * FROM status_obra ORDER BY id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém todos os clientes disponíveis
     * 
     * @return array
     */
    public function getAllClientes() {
        $query = "SELECT * FROM clientes ORDER BY nome";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
