<?php
/**
 * Model para gerenciamento financeiro
 */
class FinanceiroModel {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtém todas as notas fiscais cadastradas
     * 
     * @param array $filtros Filtros opcionais
     * @return array
     */
    public function getAllNotasFiscais($filtros = []) {
        $query = "SELECT nf.*, f.nome_fantasia as fornecedor_nome, o.nome as obra_nome 
                 FROM notas_fiscais nf 
                 LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id 
                 LEFT JOIN obras o ON nf.obra_id = o.id 
                 WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros se existirem
        if (!empty($filtros['obra_id'])) {
            $query .= " AND nf.obra_id = :obra_id";
            $params[':obra_id'] = $filtros['obra_id'];
        }
        
        if (!empty($filtros['fornecedor_id'])) {
            $query .= " AND nf.fornecedor_id = :fornecedor_id";
            $params[':fornecedor_id'] = $filtros['fornecedor_id'];
        }
        
        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $query .= " AND nf.data_emissao BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $filtros['data_inicio'];
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        $query .= " ORDER BY nf.data_emissao DESC";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind dos parâmetros
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém uma nota fiscal pelo ID
     * 
     * @param int $id ID da nota fiscal
     * @return array
     */
    public function getNotaFiscalById($id) {
        $query = "SELECT nf.*, f.nome_fantasia as fornecedor_nome, o.nome as obra_nome 
                 FROM notas_fiscais nf 
                 LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id 
                 LEFT JOIN obras o ON nf.obra_id = o.id 
                 WHERE nf.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cria uma nova nota fiscal
     * 
     * @param array $data Dados da nota fiscal
     * @return int|bool ID da nota fiscal criada ou false em caso de erro
     */
    public function createNotaFiscal($data) {
        $this->conn->beginTransaction();
        
        try {
            $query = "INSERT INTO notas_fiscais (
                        numero, 
                        fornecedor_id, 
                        obra_id, 
                        data_emissao, 
                        valor_total, 
                        descricao, 
                        arquivo_path,
                        forma_pagamento,
                        status_pagamento
                    ) VALUES (
                        :numero, 
                        :fornecedor_id, 
                        :obra_id, 
                        :data_emissao, 
                        :valor_total, 
                        :descricao, 
                        :arquivo_path,
                        :forma_pagamento,
                        :status_pagamento
                    )";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitização e binding dos parâmetros
            $numero = htmlspecialchars(strip_tags($data['numero']));
            $descricao = htmlspecialchars(strip_tags($data['descricao']));
            $arquivo_path = isset($data['arquivo_path']) ? htmlspecialchars(strip_tags($data['arquivo_path'])) : null;
            $forma_pagamento = htmlspecialchars(strip_tags($data['forma_pagamento']));
            $valor_total = str_replace(',', '.', $data['valor_total']);
            
            $stmt->bindParam(':numero', $numero);
            $stmt->bindParam(':fornecedor_id', $data['fornecedor_id']);
            $stmt->bindParam(':obra_id', $data['obra_id']);
            $stmt->bindParam(':data_emissao', $data['data_emissao']);
            $stmt->bindParam(':valor_total', $valor_total);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':arquivo_path', $arquivo_path);
            $stmt->bindParam(':forma_pagamento', $forma_pagamento);
            $stmt->bindParam(':status_pagamento', $data['status_pagamento']);
            
            $stmt->execute();
            
            $nota_fiscal_id = $this->conn->lastInsertId();
            
            // Se houver parcelas, cadastrá-las
            if (!empty($data['parcelas']) && is_array($data['parcelas'])) {
                foreach ($data['parcelas'] as $parcela) {
                    $query = "INSERT INTO parcelas (
                                nota_fiscal_id,
                                numero_parcela,
                                valor,
                                data_vencimento,
                                status
                            ) VALUES (
                                :nota_fiscal_id,
                                :numero_parcela,
                                :valor,
                                :data_vencimento,
                                :status
                            )";
                    
                    $stmt = $this->conn->prepare($query);
                    
                    $valor_parcela = str_replace(',', '.', $parcela['valor']);
                    
                    $stmt->bindParam(':nota_fiscal_id', $nota_fiscal_id);
                    $stmt->bindParam(':numero_parcela', $parcela['numero_parcela']);
                    $stmt->bindParam(':valor', $valor_parcela);
                    $stmt->bindParam(':data_vencimento', $parcela['data_vencimento']);
                    $stmt->bindParam(':status', $parcela['status']);
                    
                    $stmt->execute();
                }
            }
            
            $this->conn->commit();
            return $nota_fiscal_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    /**
     * Atualiza uma nota fiscal existente
     * 
     * @param int $id ID da nota fiscal
     * @param array $data Dados da nota fiscal
     * @return bool
     */
    public function updateNotaFiscal($id, $data) {
        $this->conn->beginTransaction();
        
        try {
            $query = "UPDATE notas_fiscais SET
                        numero = :numero,
                        fornecedor_id = :fornecedor_id,
                        obra_id = :obra_id,
                        data_emissao = :data_emissao,
                        valor_total = :valor_total,
                        descricao = :descricao,
                        forma_pagamento = :forma_pagamento,
                        status_pagamento = :status_pagamento";
            
            // Atualiza o caminho do arquivo apenas se um novo arquivo for enviado
            if (isset($data['arquivo_path']) && !empty($data['arquivo_path'])) {
                $query .= ", arquivo_path = :arquivo_path";
            }
            
            $query .= " WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitização e binding dos parâmetros
            $numero = htmlspecialchars(strip_tags($data['numero']));
            $descricao = htmlspecialchars(strip_tags($data['descricao']));
            $forma_pagamento = htmlspecialchars(strip_tags($data['forma_pagamento']));
            $valor_total = str_replace(',', '.', $data['valor_total']);
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':numero', $numero);
            $stmt->bindParam(':fornecedor_id', $data['fornecedor_id']);
            $stmt->bindParam(':obra_id', $data['obra_id']);
            $stmt->bindParam(':data_emissao', $data['data_emissao']);
            $stmt->bindParam(':valor_total', $valor_total);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':forma_pagamento', $forma_pagamento);
            $stmt->bindParam(':status_pagamento', $data['status_pagamento']);
            
            if (isset($data['arquivo_path']) && !empty($data['arquivo_path'])) {
                $arquivo_path = htmlspecialchars(strip_tags($data['arquivo_path']));
                $stmt->bindParam(':arquivo_path', $arquivo_path);
            }
            
            $stmt->execute();
            
            // Atualizar parcelas existentes ou adicionar novas
            if (!empty($data['parcelas']) && is_array($data['parcelas'])) {
                // Primeiro, excluir parcelas existentes
                $query = "DELETE FROM parcelas WHERE nota_fiscal_id = :nota_fiscal_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':nota_fiscal_id', $id);
                $stmt->execute();
                
                // Depois, inserir as novas parcelas
                foreach ($data['parcelas'] as $parcela) {
                    $query = "INSERT INTO parcelas (
                                nota_fiscal_id,
                                numero_parcela,
                                valor,
                                data_vencimento,
                                status
                            ) VALUES (
                                :nota_fiscal_id,
                                :numero_parcela,
                                :valor,
                                :data_vencimento,
                                :status
                            )";
                    
                    $stmt = $this->conn->prepare($query);
                    
                    $valor_parcela = str_replace(',', '.', $parcela['valor']);
                    
                    $stmt->bindParam(':nota_fiscal_id', $id);
                    $stmt->bindParam(':numero_parcela', $parcela['numero_parcela']);
                    $stmt->bindParam(':valor', $valor_parcela);
                    $stmt->bindParam(':data_vencimento', $parcela['data_vencimento']);
                    $stmt->bindParam(':status', $parcela['status']);
                    
                    $stmt->execute();
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    /**
     * Exclui uma nota fiscal
     * 
     * @param int $id ID da nota fiscal
     * @return bool
     */
    public function deleteNotaFiscal($id) {
        $this->conn->beginTransaction();
        
        try {
            // Primeiro, excluir as parcelas associadas
            $query = "DELETE FROM parcelas WHERE nota_fiscal_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Depois, excluir a nota fiscal
            $query = "DELETE FROM notas_fiscais WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    /**
     * Obtém todas as parcelas de uma nota fiscal
     * 
     * @param int $nota_fiscal_id ID da nota fiscal
     * @return array
     */
    public function getParcelasByNotaFiscalId($nota_fiscal_id) {
        $query = "SELECT * FROM parcelas WHERE nota_fiscal_id = :nota_fiscal_id ORDER BY numero_parcela";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nota_fiscal_id', $nota_fiscal_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza o status de uma parcela
     * 
     * @param int $id ID da parcela
     * @param string $status Novo status
     * @return bool
     */
    public function updateParcelaStatus($id, $status) {
        $query = "UPDATE parcelas SET status = :status WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém resumo financeiro por período
     * 
     * @param string $data_inicio Data inicial
     * @param string $data_fim Data final
     * @param int $obra_id ID da obra (opcional)
     * @return array
     */
    public function getResumoFinanceiro($data_inicio, $data_fim, $obra_id = null) {
        $query = "SELECT 
                    SUM(valor_total) as total,
                    COUNT(*) as quantidade,
                    status_pagamento
                 FROM notas_fiscais
                 WHERE data_emissao BETWEEN :data_inicio AND :data_fim";
        
        if ($obra_id) {
            $query .= " AND obra_id = :obra_id";
        }
        
        $query .= " GROUP BY status_pagamento";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        
        if ($obra_id) {
            $stmt->bindParam(':obra_id', $obra_id);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém parcelas a vencer
     * 
     * @param int $dias_limite Limite de dias para vencimento
     * @return array
     */
    public function getParcelasAVencer($dias_limite = 15) {
        $query = "SELECT 
                    p.*,
                    nf.numero as nota_fiscal_numero,
                    f.nome_fantasia as fornecedor_nome,
                    o.nome as obra_nome
                 FROM parcelas p
                 JOIN notas_fiscais nf ON p.nota_fiscal_id = nf.id
                 LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id
                 LEFT JOIN obras o ON nf.obra_id = o.id
                 WHERE p.status = 'Pendente'
                 AND p.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias_limite DAY)
                 ORDER BY p.data_vencimento";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':dias_limite', $dias_limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém parcelas vencidas
     * 
     * @return array
     */
    public function getParcelasVencidas() {
        $query = "SELECT 
                    p.*,
                    nf.numero as nota_fiscal_numero,
                    f.nome_fantasia as fornecedor_nome,
                    o.nome as obra_nome
                 FROM parcelas p
                 JOIN notas_fiscais nf ON p.nota_fiscal_id = nf.id
                 LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id
                 LEFT JOIN obras o ON nf.obra_id = o.id
                 WHERE p.status = 'Pendente'
                 AND p.data_vencimento < CURDATE()
                 ORDER BY p.data_vencimento";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
