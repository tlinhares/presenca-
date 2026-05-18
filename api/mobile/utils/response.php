<?php
/**
 * MobileResponse - Classe helper para respostas padronizadas da API Mobile
 * 
 * Padroniza todas as respostas da API mobile em formato JSON consistente
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 */

class MobileResponse {
    
    /**
     * Retorna resposta de sucesso
     * 
     * @param mixed $data Dados a serem retornados
     * @param string $message Mensagem de sucesso
     * @param int $httpCode Código HTTP (padrão: 200)
     * @return array Array formatado para JSON
     */
    public static function success($data = null, $message = 'Operação realizada com sucesso', $httpCode = 200) {
        http_response_code($httpCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c') // ISO 8601
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $response;
    }
    
    /**
     * Retorna resposta de erro
     * 
     * @param string $message Mensagem de erro
     * @param int $httpCode Código HTTP (padrão: 400)
     * @param mixed $errors Erros adicionais (opcional)
     * @return array Array formatado para JSON
     */
    public static function error($message = 'Erro na operação', $httpCode = 400, $errors = null) {
        http_response_code($httpCode);
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return $response;
    }
    
    /**
     * Retorna resposta de erro de validação
     * 
     * @param array $errors Array de erros de validação
     * @param string $message Mensagem geral
     * @return array Array formatado para JSON
     */
    public static function validationError($errors, $message = 'Erro de validação') {
        return self::error($message, 422, $errors);
    }
    
    /**
     * Retorna resposta de não autorizado
     * 
     * @param string $message Mensagem
     * @return array Array formatado para JSON
     */
    public static function unauthorized($message = 'Não autorizado') {
        return self::error($message, 401);
    }
    
    /**
     * Retorna resposta de acesso negado
     * 
     * @param string $message Mensagem
     * @return array Array formatado para JSON
     */
    public static function forbidden($message = 'Acesso negado') {
        return self::error($message, 403);
    }
    
    /**
     * Retorna resposta de não encontrado
     * 
     * @param string $message Mensagem
     * @return array Array formatado para JSON
     */
    public static function notFound($message = 'Recurso não encontrado') {
        return self::error($message, 404);
    }
    
    /**
     * Retorna resposta de erro interno do servidor
     * 
     * @param string $message Mensagem
     * @return array Array formatado para JSON
     */
    public static function serverError($message = 'Erro interno do servidor') {
        return self::error($message, 500);
    }
    
    /**
     * Retorna resposta paginada
     * 
     * @param array $data Dados
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @param int $total Total de itens
     * @param string $message Mensagem
     * @return array Array formatado para JSON
     */
    public static function paginated($data, $page, $perPage, $total, $message = 'Dados recuperados com sucesso') {
        $totalPages = ceil($total / $perPage);
        
        $response = self::success([
            'items' => $data,
            'pagination' => [
                'page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => (int)$total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ], $message);
        
        return $response;
    }
}
