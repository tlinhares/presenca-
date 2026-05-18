<?php
/**
 * Router - Classe responsável pelo roteamento do sistema
 * 
 * Esta classe gerencia as rotas do sistema, redirecionando
 * as requisições para os controladores apropriados.
 */

class Router {
    private $routes = [];
    private $baseUrl;
    
    /**
     * Construtor da classe
     */
    public function __construct() {
        // Obtém a URL base do arquivo de configuração
        require_once dirname(__DIR__) . '/config/config.php';
        $this->baseUrl = config('base_url');
    }
    
    /**
     * Adiciona uma rota ao sistema
     * 
     * @param string $method Método HTTP (GET, POST, etc)
     * @param string $path Caminho da rota
     * @param callable $callback Função de callback para a rota
     * @return void
     */
    public function add($method, $path, $callback) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }
    
    /**
     * Adiciona uma rota GET
     * 
     * @param string $path Caminho da rota
     * @param callable $callback Função de callback para a rota
     * @return void
     */
    public function get($path, $callback) {
        $this->add('GET', $path, $callback);
    }
    
    /**
     * Adiciona uma rota POST
     * 
     * @param string $path Caminho da rota
     * @param callable $callback Função de callback para a rota
     * @return void
     */
    public function post($path, $callback) {
        $this->add('POST', $path, $callback);
    }
    
    /**
     * Redireciona para uma URL
     * 
     * @param string $url URL para redirecionamento
     * @return void
     */
    public function redirect($url) {
        // Se a URL não começar com http:// ou https://, assume que é uma URL relativa
        if (!preg_match('/^https?:\/\//', $url)) {
            // Remover a barra inicial se existir
            $url = ltrim($url, '/');
            
            // Construir a URL completa
            $url = $this->baseUrl . $url;
        }
        
        header("Location: $url");
        exit;
    }
    
    /**
     * Obtém a URL atual
     * 
     * @return string URL atual
     */
    private function getCurrentUrl() {
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remover a query string, se existir
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        // Remover a URL base para obter apenas o caminho
        $basePath = parse_url($this->baseUrl, PHP_URL_PATH);
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Remover 'public/' do início da URI, se existir
        if (strpos($uri, 'public/') === 0) {
            $uri = substr($uri, strlen('public/'));
        }
        
        // Garantir que a URI comece com /
        return '/' . ltrim($uri, '/');
    }
    
    /**
     * Executa o roteador
     * 
     * @return void
     */
    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];
        $url = $this->getCurrentUrl();
        
        // Procura por uma rota correspondente
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            // Converte o caminho da rota para uma expressão regular
            $pattern = '#^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']) . '$#';
            
            if (preg_match($pattern, $url, $matches)) {
                // Remove os índices numéricos
                foreach ($matches as $key => $value) {
                    if (is_int($key)) {
                        unset($matches[$key]);
                    }
                }
                
                // Executa o callback da rota
                call_user_func_array($route['callback'], [$matches]);
                return;
            }
        }
        
        // Se não encontrou uma rota, exibe uma página 404
        header("HTTP/1.0 404 Not Found");
        echo "Página não encontrada";
    }
}
