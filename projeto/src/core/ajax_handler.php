<?php
/**
 * AjaxHandler - Classe para processamento de requisições AJAX
 */
class AjaxHandler {
    private $conn;
    private $response = [
        'success' => false,
        'message' => '',
        'data' => null
    ];
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Processa uma requisição AJAX
     * 
     * @param string $action Nome da ação
     * @param array $data Dados da requisição
     * @return array Resposta em formato JSON
     */
    public function process($action, $data = []) {
        // Verificar se o usuário está autenticado
        if (!Auth::isLoggedIn() && $action != 'login') {
            $this->response['message'] = 'Usuário não autenticado';
            return $this->response;
        }
        
        // Verificar permissões para ações que requerem autenticação
        if (Auth::isLoggedIn()) {
            $permission = new Permission($this->conn);
            $perfil_id = Auth::getUserProfileId();
            
            // Mapear ações para módulos e permissões
            $actionMap = [
                // Obras
                'getObras' => ['modulo' => 'obras', 'acao' => 'visualizar'],
                'getObra' => ['modulo' => 'obras', 'acao' => 'visualizar'],
                'salvarObra' => ['modulo' => 'obras', 'acao' => 'criar'],
                'atualizarObra' => ['modulo' => 'obras', 'acao' => 'editar'],
                'excluirObra' => ['modulo' => 'obras', 'acao' => 'excluir'],
                
                // Financeiro
                'getNotasFiscais' => ['modulo' => 'financeiro', 'acao' => 'visualizar'],
                'getNotaFiscal' => ['modulo' => 'financeiro', 'acao' => 'visualizar'],
                'salvarNotaFiscal' => ['modulo' => 'financeiro', 'acao' => 'criar'],
                'atualizarNotaFiscal' => ['modulo' => 'financeiro', 'acao' => 'editar'],
                'excluirNotaFiscal' => ['modulo' => 'financeiro', 'acao' => 'excluir'],
                
                // Usuários
                'getUsuarios' => ['modulo' => 'usuarios', 'acao' => 'visualizar'],
                'getUsuario' => ['modulo' => 'usuarios', 'acao' => 'visualizar'],
                'salvarUsuario' => ['modulo' => 'usuarios', 'acao' => 'criar'],
                'atualizarUsuario' => ['modulo' => 'usuarios', 'acao' => 'editar'],
                'excluirUsuario' => ['modulo' => 'usuarios', 'acao' => 'excluir'],
            ];
            
            // Verificar permissão se a ação estiver mapeada
            if (isset($actionMap[$action])) {
                $modulo = $actionMap[$action]['modulo'];
                $acao = $actionMap[$action]['acao'];
                
                if (!$permission->check($perfil_id, $modulo, $acao)) {
                    $this->response['message'] = 'Permissão negada';
                    return $this->response;
                }
            }
        }
        
        // Processar a ação
        switch ($action) {
            case 'login':
                return $this->processLogin($data);
            
            // Obras
            case 'getObras':
                return $this->getObras();
            case 'getObra':
                return $this->getObra($data);
            case 'salvarObra':
                return $this->salvarObra($data);
            case 'atualizarObra':
                return $this->atualizarObra($data);
            case 'excluirObra':
                return $this->excluirObra($data);
            
            // Financeiro
            case 'getNotasFiscais':
                return $this->getNotasFiscais($data);
            case 'getNotaFiscal':
                return $this->getNotaFiscal($data);
            case 'salvarNotaFiscal':
                return $this->salvarNotaFiscal($data);
            case 'atualizarNotaFiscal':
                return $this->atualizarNotaFiscal($data);
            case 'excluirNotaFiscal':
                return $this->excluirNotaFiscal($data);
            
            default:
                $this->response['message'] = 'Ação não reconhecida';
                return $this->response;
        }
    }
    
    /**
     * Processa login via AJAX
     * 
     * @param array $data Dados do login
     * @return array
     */
    private function processLogin($data) {
        if (empty($data['email']) || empty($data['senha'])) {
            $this->response['message'] = 'Preencha todos os campos';
            return $this->response;
        }
        
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $senha = $data['senha'];
        
        // Buscar usuário pelo email
        $query = "SELECT id, nome, senha_hash, perfil_id, ativo FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar se o usuário está ativo
            if (!$usuario['ativo']) {
                $this->response['message'] = 'Usuário inativo. Entre em contato com o administrador.';
                return $this->response;
            }
            
            // Verificar senha
            if (Auth::verifyPassword($senha, $usuario['senha_hash'])) {
                // Login bem-sucedido
                Auth::login($usuario['id'], $usuario['nome'], $usuario['perfil_id']);
                
                $this->response['success'] = true;
                $this->response['message'] = 'Login realizado com sucesso';
                $this->response['data'] = [
                    'usuario_id' => $usuario['id'],
                    'usuario_nome' => $usuario['nome'],
                    'perfil_id' => $usuario['perfil_id']
                ];
                return $this->response;
            }
        }
        
        $this->response['message'] = 'Email ou senha inválidos';
        return $this->response;
    }
    
    /**
     * Obtém todas as obras
     * 
     * @return array
     */
    private function getObras() {
        require_once SRC_PATH . '/models/obra_model.php';
        $obraModel = new ObraModel($this->conn);
        $obras = $obraModel->getAll();
        
        $this->response['success'] = true;
        $this->response['data'] = $obras;
        return $this->response;
    }
    
    /**
     * Obtém uma obra pelo ID
     * 
     * @param array $data Dados da requisição
     * @return array
     */
    private function getObra($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da obra não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/obra_model.php';
        $obraModel = new ObraModel($this->conn);
        $obra = $obraModel->getById($data['id']);
        
        if (!$obra) {
            $this->response['message'] = 'Obra não encontrada';
            return $this->response;
        }
        
        $this->response['success'] = true;
        $this->response['data'] = $obra;
        return $this->response;
    }
    
    /**
     * Salva uma nova obra
     * 
     * @param array $data Dados da obra
     * @return array
     */
    private function salvarObra($data) {
        require_once SRC_PATH . '/models/obra_model.php';
        $obraModel = new ObraModel($this->conn);
        
        $result = $obraModel->create($data);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Obra salva com sucesso';
            $this->response['data'] = ['id' => $result];
        } else {
            $this->response['message'] = 'Erro ao salvar obra';
        }
        
        return $this->response;
    }
    
    /**
     * Atualiza uma obra existente
     * 
     * @param array $data Dados da obra
     * @return array
     */
    private function atualizarObra($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da obra não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/obra_model.php';
        $obraModel = new ObraModel($this->conn);
        
        $result = $obraModel->update($data['id'], $data);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Obra atualizada com sucesso';
        } else {
            $this->response['message'] = 'Erro ao atualizar obra';
        }
        
        return $this->response;
    }
    
    /**
     * Exclui uma obra
     * 
     * @param array $data Dados da requisição
     * @return array
     */
    private function excluirObra($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da obra não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/obra_model.php';
        $obraModel = new ObraModel($this->conn);
        
        $result = $obraModel->delete($data['id']);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Obra excluída com sucesso';
        } else {
            $this->response['message'] = 'Erro ao excluir obra';
        }
        
        return $this->response;
    }
    
    /**
     * Obtém todas as notas fiscais
     * 
     * @param array $data Filtros opcionais
     * @return array
     */
    private function getNotasFiscais($data = []) {
        require_once SRC_PATH . '/models/financeiro_model.php';
        $financeiroModel = new FinanceiroModel($this->conn);
        
        $filtros = [];
        if (!empty($data['obra_id'])) $filtros['obra_id'] = $data['obra_id'];
        if (!empty($data['fornecedor_id'])) $filtros['fornecedor_id'] = $data['fornecedor_id'];
        if (!empty($data['data_inicio'])) $filtros['data_inicio'] = $data['data_inicio'];
        if (!empty($data['data_fim'])) $filtros['data_fim'] = $data['data_fim'];
        
        $notas = $financeiroModel->getAllNotasFiscais($filtros);
        
        $this->response['success'] = true;
        $this->response['data'] = $notas;
        return $this->response;
    }
    
    /**
     * Obtém uma nota fiscal pelo ID
     * 
     * @param array $data Dados da requisição
     * @return array
     */
    private function getNotaFiscal($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da nota fiscal não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/financeiro_model.php';
        $financeiroModel = new FinanceiroModel($this->conn);
        
        $nota = $financeiroModel->getNotaFiscalById($data['id']);
        
        if (!$nota) {
            $this->response['message'] = 'Nota fiscal não encontrada';
            return $this->response;
        }
        
        // Obter parcelas se existirem
        $nota['parcelas'] = $financeiroModel->getParcelasByNotaFiscalId($data['id']);
        
        $this->response['success'] = true;
        $this->response['data'] = $nota;
        return $this->response;
    }
    
    /**
     * Salva uma nova nota fiscal
     * 
     * @param array $data Dados da nota fiscal
     * @return array
     */
    private function salvarNotaFiscal($data) {
        require_once SRC_PATH . '/models/financeiro_model.php';
        $financeiroModel = new FinanceiroModel($this->conn);
        
        // Processar upload de arquivo se existir
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = UPLOAD_DIR . 'notas_fiscais/';
            
            // Criar diretório se não existir
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['arquivo']['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $filePath)) {
                $data['arquivo_path'] = '/uploads/notas_fiscais/' . $fileName;
            }
        }
        
        $result = $financeiroModel->createNotaFiscal($data);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Nota fiscal salva com sucesso';
            $this->response['data'] = ['id' => $result];
        } else {
            $this->response['message'] = 'Erro ao salvar nota fiscal';
        }
        
        return $this->response;
    }
    
    /**
     * Atualiza uma nota fiscal existente
     * 
     * @param array $data Dados da nota fiscal
     * @return array
     */
    private function atualizarNotaFiscal($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da nota fiscal não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/financeiro_model.php';
        $financeiroModel = new FinanceiroModel($this->conn);
        
        // Processar upload de arquivo se existir
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = UPLOAD_DIR . 'notas_fiscais/';
            
            // Criar diretório se não existir
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['arquivo']['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $filePath)) {
                $data['arquivo_path'] = '/uploads/notas_fiscais/' . $fileName;
            }
        }
        
        $result = $financeiroModel->updateNotaFiscal($data['id'], $data);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Nota fiscal atualizada com sucesso';
        } else {
            $this->response['message'] = 'Erro ao atualizar nota fiscal';
        }
        
        return $this->response;
    }
    
    /**
     * Exclui uma nota fiscal
     * 
     * @param array $data Dados da requisição
     * @return array
     */
    private function excluirNotaFiscal($data) {
        if (empty($data['id'])) {
            $this->response['message'] = 'ID da nota fiscal não informado';
            return $this->response;
        }
        
        require_once SRC_PATH . '/models/financeiro_model.php';
        $financeiroModel = new FinanceiroModel($this->conn);
        
        $result = $financeiroModel->deleteNotaFiscal($data['id']);
        
        if ($result) {
            $this->response['success'] = true;
            $this->response['message'] = 'Nota fiscal excluída com sucesso';
        } else {
            $this->response['message'] = 'Erro ao excluir nota fiscal';
        }
        
        return $this->response;
    }
}
?>
