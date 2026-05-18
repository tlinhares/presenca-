<?php
/**
 * PermissaoService - Serviço centralizado para controle de permissões por módulo
 * 
 * Este serviço gerencia todas as verificações de permissão do sistema,
 * mantendo compatibilidade total com o sistema de admin existente.
 * 
 * IMPORTANTE: Usuários com categoria 'admin' SEMPRE têm acesso total.
 * O sistema de permissões é COMPLEMENTAR para usuários não-admin.
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 * @see /docs/SISTEMA_PERMISSOES.md
 */

class PermissaoService {
    
    // Níveis de permissão (constantes para uso no código)
    const NENHUM = 0;
    const VISUALIZAR = 1;
    const EDITAR = 2;
    const EXCLUIR = 3;
    const ADMINISTRAR = 4;
    
    // Cache de permissões para evitar consultas repetidas na mesma requisição
    private static $cache_permissoes = [];
    private static $cache_modulos = [];
    private static $conn = null;
    
    /**
     * Obtém conexão com o banco de dados
     * Reutiliza a conexão global $conn se disponível
     */
    private static function getConexao() {
        global $conn;
        
        if (self::$conn !== null) {
            return self::$conn;
        }
        
        // Se já existe conexão global, usa ela
        if (isset($conn) && $conn instanceof mysqli) {
            self::$conn = $conn;
            return self::$conn;
        }
        
        // Tenta incluir arquivo de conexão
        $config_paths = [
            __DIR__ . '/../../api/conexao.php',
            __DIR__ . '/../../config/config.php',
            __DIR__ . '/../../includes/conexao.php'
        ];
        
        foreach ($config_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (isset($conn) && $conn instanceof mysqli) {
                    self::$conn = $conn;
                    return self::$conn;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Verifica se o usuário atual é admin
     * 
     * @return bool
     */
    public static function isAdmin() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    }
    
    /**
     * Obtém o ID do usuário logado
     * 
     * @return int|null
     */
    public static function getUsuarioId() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return $_SESSION['usuario_id'] ?? null;
    }
    
    /**
     * Verifica se o usuário está logado
     * 
     * @return bool
     */
    public static function isLogado() {
        return self::getUsuarioId() !== null;
    }
    
    /**
     * Obtém o ID do módulo pelo código
     * 
     * @param string $codigo_modulo Código do módulo (ex: 'refeicoes', 'culto')
     * @return int|null
     */
    public static function getModuloId($codigo_modulo) {
        // Verifica cache
        if (isset(self::$cache_modulos[$codigo_modulo])) {
            return self::$cache_modulos[$codigo_modulo];
        }
        
        $conn = self::getConexao();
        if (!$conn) {
            return null;
        }
        
        try {
            $stmt = $conn->prepare("SELECT id FROM modulos WHERE codigo = ? AND ativo = 1");
            if (!$stmt) {
                return null;
            }
            
            $stmt->bind_param("s", $codigo_modulo);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row) {
                self::$cache_modulos[$codigo_modulo] = (int)$row['id'];
                return self::$cache_modulos[$codigo_modulo];
            }
        } catch (Exception $e) {
            error_log("PermissaoService::getModuloId - Erro: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Obtém o nível de permissão do usuário para um módulo
     * 
     * @param string $codigo_modulo Código do módulo
     * @param int|null $usuario_id ID do usuário (usa sessão se null)
     * @return int Nível de permissão (0-4)
     */
    public static function getNivelPermissao($codigo_modulo, $usuario_id = null) {
        // Admin sempre tem permissão máxima
        if (self::isAdmin()) {
            return self::ADMINISTRAR;
        }
        
        $usuario_id = $usuario_id ?? self::getUsuarioId();
        if (!$usuario_id) {
            return self::NENHUM;
        }
        
        // Verifica cache
        $cache_key = "{$usuario_id}_{$codigo_modulo}";
        if (isset(self::$cache_permissoes[$cache_key])) {
            return self::$cache_permissoes[$cache_key];
        }
        
        $conn = self::getConexao();
        if (!$conn) {
            return self::NENHUM;
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT up.nivel_permissao 
                FROM usuario_permissoes up
                INNER JOIN modulos m ON m.id = up.modulo_id
                WHERE up.usuario_id = ? AND m.codigo = ? AND m.ativo = 1
            ");
            
            if (!$stmt) {
                return self::NENHUM;
            }
            
            $stmt->bind_param("is", $usuario_id, $codigo_modulo);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            $nivel = $row ? (int)$row['nivel_permissao'] : self::NENHUM;
            self::$cache_permissoes[$cache_key] = $nivel;
            
            return $nivel;
        } catch (Exception $e) {
            error_log("PermissaoService::getNivelPermissao - Erro: " . $e->getMessage());
            return self::NENHUM;
        }
    }
    
    /**
     * Verifica se o usuário pode acessar um módulo (qualquer nível > 0)
     * 
     * @param string $codigo_modulo Código do módulo
     * @param int|null $usuario_id ID do usuário (usa sessão se null)
     * @return bool
     */
    public static function podeAcessar($codigo_modulo, $usuario_id = null) {
        return self::getNivelPermissao($codigo_modulo, $usuario_id) >= self::VISUALIZAR;
    }
    
    /**
     * Verifica se o usuário tem pelo menos determinado nível de permissão
     * 
     * @param string $codigo_modulo Código do módulo
     * @param int $nivel_minimo Nível mínimo requerido
     * @param int|null $usuario_id ID do usuário (usa sessão se null)
     * @return bool
     */
    public static function temPermissao($codigo_modulo, $nivel_minimo, $usuario_id = null) {
        return self::getNivelPermissao($codigo_modulo, $usuario_id) >= $nivel_minimo;
    }
    
    /**
     * Verifica se o usuário pode visualizar (nível >= 1)
     */
    public static function podeVisualizar($codigo_modulo, $usuario_id = null) {
        return self::temPermissao($codigo_modulo, self::VISUALIZAR, $usuario_id);
    }
    
    /**
     * Verifica se o usuário pode editar (nível >= 2)
     */
    public static function podeEditar($codigo_modulo, $usuario_id = null) {
        return self::temPermissao($codigo_modulo, self::EDITAR, $usuario_id);
    }
    
    /**
     * Verifica se o usuário pode excluir (nível >= 3)
     */
    public static function podeExcluir($codigo_modulo, $usuario_id = null) {
        return self::temPermissao($codigo_modulo, self::EXCLUIR, $usuario_id);
    }
    
    /**
     * Verifica se o usuário pode administrar (nível >= 4)
     */
    public static function podeAdministrar($codigo_modulo, $usuario_id = null) {
        return self::temPermissao($codigo_modulo, self::ADMINISTRAR, $usuario_id);
    }
    
    /**
     * Obtém todos os módulos ativos
     * 
     * @return array
     */
    public static function getModulos() {
        $conn = self::getConexao();
        if (!$conn) {
            return [];
        }
        
        try {
            $result = $conn->query("SELECT * FROM modulos WHERE ativo = 1 ORDER BY ordem");
            $modulos = [];
            while ($row = $result->fetch_assoc()) {
                $modulos[] = $row;
            }
            return $modulos;
        } catch (Exception $e) {
            error_log("PermissaoService::getModulos - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém os módulos que o usuário tem acesso
     * 
     * @param int|null $usuario_id
     * @return array
     */
    public static function getModulosDoUsuario($usuario_id = null) {
        // Admin tem acesso a todos
        if (self::isAdmin()) {
            return self::getModulos();
        }
        
        $usuario_id = $usuario_id ?? self::getUsuarioId();
        if (!$usuario_id) {
            return [];
        }
        
        $conn = self::getConexao();
        if (!$conn) {
            return [];
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT m.*, up.nivel_permissao
                FROM modulos m
                INNER JOIN usuario_permissoes up ON up.modulo_id = m.id
                WHERE up.usuario_id = ? AND m.ativo = 1 AND up.nivel_permissao > 0
                ORDER BY m.ordem
            ");
            
            if (!$stmt) {
                return [];
            }
            
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $modulos = [];
            while ($row = $result->fetch_assoc()) {
                $modulos[] = $row;
            }
            $stmt->close();
            
            return $modulos;
        } catch (Exception $e) {
            error_log("PermissaoService::getModulosDoUsuario - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Define a permissão de um usuário para um módulo
     * 
     * @param int $usuario_id
     * @param string $codigo_modulo
     * @param int $nivel
     * @return bool
     */
    public static function setPermissao($usuario_id, $codigo_modulo, $nivel) {
        $conn = self::getConexao();
        if (!$conn) {
            return false;
        }
        
        $modulo_id = self::getModuloId($codigo_modulo);
        if (!$modulo_id) {
            return false;
        }
        
        try {
            // Usa INSERT ... ON DUPLICATE KEY UPDATE para criar ou atualizar
            $stmt = $conn->prepare("
                INSERT INTO usuario_permissoes (usuario_id, modulo_id, nivel_permissao)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE nivel_permissao = VALUES(nivel_permissao)
            ");
            
            if (!$stmt) {
                return false;
            }
            
            $stmt->bind_param("iii", $usuario_id, $modulo_id, $nivel);
            $result = $stmt->execute();
            $stmt->close();
            
            // Limpa cache
            $cache_key = "{$usuario_id}_{$codigo_modulo}";
            unset(self::$cache_permissoes[$cache_key]);
            
            return $result;
        } catch (Exception $e) {
            error_log("PermissaoService::setPermissao - Erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove todas as permissões de um usuário
     * 
     * @param int $usuario_id
     * @return bool
     */
    public static function removerTodasPermissoes($usuario_id) {
        $conn = self::getConexao();
        if (!$conn) {
            return false;
        }
        
        try {
            $stmt = $conn->prepare("DELETE FROM usuario_permissoes WHERE usuario_id = ?");
            if (!$stmt) {
                return false;
            }
            
            $stmt->bind_param("i", $usuario_id);
            $result = $stmt->execute();
            $stmt->close();
            
            // Limpa cache do usuário
            foreach (self::$cache_permissoes as $key => $value) {
                if (strpos($key, "{$usuario_id}_") === 0) {
                    unset(self::$cache_permissoes[$key]);
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PermissaoService::removerTodasPermissoes - Erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém todas as permissões de um usuário
     * 
     * @param int $usuario_id
     * @return array
     */
    public static function getPermissoesDoUsuario($usuario_id) {
        $conn = self::getConexao();
        if (!$conn) {
            return [];
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT m.codigo, m.nome, up.nivel_permissao
                FROM usuario_permissoes up
                INNER JOIN modulos m ON m.id = up.modulo_id
                WHERE up.usuario_id = ? AND m.ativo = 1
            ");
            
            if (!$stmt) {
                return [];
            }
            
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $permissoes = [];
            while ($row = $result->fetch_assoc()) {
                $permissoes[$row['codigo']] = [
                    'nome' => $row['nome'],
                    'nivel' => (int)$row['nivel_permissao']
                ];
            }
            $stmt->close();
            
            return $permissoes;
        } catch (Exception $e) {
            error_log("PermissaoService::getPermissoesDoUsuario - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém nome legível do nível de permissão
     * 
     * @param int $nivel
     * @return string
     */
    public static function getNomeNivel($nivel) {
        $nomes = [
            self::NENHUM => 'Sem acesso',
            self::VISUALIZAR => 'Visualizar',
            self::EDITAR => 'Editar',
            self::EXCLUIR => 'Excluir',
            self::ADMINISTRAR => 'Administrar'
        ];
        
        return $nomes[$nivel] ?? 'Desconhecido';
    }
    
    /**
     * Obtém cor do badge para o nível de permissão
     * 
     * @param int $nivel
     * @return string
     */
    public static function getCorNivel($nivel) {
        $cores = [
            self::NENHUM => 'secondary',
            self::VISUALIZAR => 'info',
            self::EDITAR => 'warning',
            self::EXCLUIR => 'danger',
            self::ADMINISTRAR => 'success'
        ];
        
        return $cores[$nivel] ?? 'secondary';
    }
    
    /**
     * Limpa o cache de permissões (útil após alterações)
     */
    public static function limparCache() {
        self::$cache_permissoes = [];
        self::$cache_modulos = [];
    }
    
    /**
     * Redireciona para página de acesso negado se não tiver permissão
     * 
     * @param string $codigo_modulo
     * @param int $nivel_minimo
     * @param string $redirect_url URL para redirecionar se não tiver acesso
     */
    public static function exigirPermissao($codigo_modulo, $nivel_minimo = self::VISUALIZAR, $redirect_url = '../index.php') {
        if (!self::temPermissao($codigo_modulo, $nivel_minimo)) {
            // Log de tentativa de acesso negado
            error_log(sprintf(
                "PermissaoService: Acesso negado - Usuario ID: %s, Módulo: %s, Nível requerido: %d",
                self::getUsuarioId() ?? 'null',
                $codigo_modulo,
                $nivel_minimo
            ));
            
            // Verifica se é requisição AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                exit;
            }
            
            // Redireciona para página de acesso negado ou index
            header("Location: $redirect_url?erro=acesso_negado");
            exit;
        }
    }
}

