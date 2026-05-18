<?php
/**
 * MenuPermissaoService - Controle de acesso por menu
 * 
 * IMPORTANTE: Este serviço é PERMISSIVO por padrão para não quebrar o sistema.
 * - Se o menu não existir no banco = PERMITE
 * - Se acesso_padrao = 1 = PERMITE
 * - Se as tabelas não existirem = PERMITE
 * - Só bloqueia se explicitamente configurado
 * 
 * @version 1.0
 * @author Sistema de Presença AOM
 */

class MenuPermissaoService {
    
    private static $conn = null;
    private static $cache_menus = [];
    private static $cache_acesso = [];
    private static $base_url = null;
    
    /**
     * Obtém a base URL do sistema
     * Em localhost: /presenca
     * Em produção: '' (raiz)
     */
    public static function getBaseUrl() {
        if (self::$base_url !== null) {
            return self::$base_url;
        }
        
        // Detecta se está em localhost
        if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            self::$base_url = '/presenca';
        } else {
            // Em produção, o sistema está na raiz
            self::$base_url = '';
        }
        
        return self::$base_url;
    }
    
    /**
     * Ajusta URL para incluir base do sistema
     */
    public static function ajustarUrl($url) {
        $base = self::getBaseUrl();
        
        // Se URL já começa com a base, não duplicar
        if ($base && strpos($url, $base) === 0) {
            return $url;
        }
        
        return $base . $url;
    }
    
    /**
     * Obtém conexão com o banco de dados
     */
    private static function getConexao() {
        global $conn;
        
        if (self::$conn !== null) {
            return self::$conn;
        }
        
        if (isset($conn) && $conn instanceof mysqli) {
            self::$conn = $conn;
            return self::$conn;
        }
        
        $paths = [
            __DIR__ . '/../../api/conexao.php',
            __DIR__ . '/../../config/config.php'
        ];
        
        foreach ($paths as $path) {
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
     * Verifica se é admin
     */
    public static function isAdmin() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
    }
    
    /**
     * Obtém ID do usuário logado
     */
    public static function getUsuarioId() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return $_SESSION['usuario_id'] ?? null;
    }
    
    /**
     * Verifica se usuário participa do culto
     */
    public static function usuarioTemCulto() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Primeiro verifica se já está na sessão
        if (isset($_SESSION['usuario_culto'])) {
            return $_SESSION['usuario_culto'] == 1;
        }
        
        // Se não estiver, busca no banco
        $usuario_id = self::getUsuarioId();
        if (!$usuario_id) return false;
        
        $conn = self::getConexao();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare("SELECT culto FROM usuarios WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $usuario_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                if ($row) {
                    $_SESSION['usuario_culto'] = $row['culto'];
                    return $row['culto'] == 1;
                }
            }
        } catch (Exception $e) {
            error_log("MenuPermissaoService::usuarioTemCulto - Erro: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Verifica se as tabelas do sistema de menus existem
     */
    private static function tabelasExistem() {
        $conn = self::getConexao();
        if (!$conn) return false;
        
        try {
            $result = $conn->query("SHOW TABLES LIKE 'menus'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Busca menu por código ou URL
     * NOTA: Busca INCLUINDO inativos para poder verificar se o menu existe mas está desativado
     */
    private static function getMenu($url_ou_codigo) {
        // Verifica cache
        if (isset(self::$cache_menus[$url_ou_codigo])) {
            return self::$cache_menus[$url_ou_codigo];
        }
        
        $conn = self::getConexao();
        if (!$conn) return null;
        
        try {
            // Busca SEM filtrar por ativo - para poder verificar se está inativo
            $stmt = $conn->prepare("SELECT * FROM menus WHERE (codigo = ? OR url = ?) LIMIT 1");
            if (!$stmt) return null;
            
            $stmt->bind_param("ss", $url_ou_codigo, $url_ou_codigo);
            $stmt->execute();
            $result = $stmt->get_result();
            $menu = $result->fetch_assoc();
            $stmt->close();
            
            // Guarda no cache
            self::$cache_menus[$url_ou_codigo] = $menu;
            
            return $menu;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::getMenu - Erro: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se usuário tem acesso via grupos
     */
    private static function usuarioTemAcessoViaGrupo($menu_id) {
        $usuario_id = self::getUsuarioId();
        if (!$usuario_id) return false;
        
        // Verifica cache
        $cache_key = "{$usuario_id}_{$menu_id}";
        if (isset(self::$cache_acesso[$cache_key])) {
            return self::$cache_acesso[$cache_key];
        }
        
        $conn = self::getConexao();
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as tem_acesso
                FROM usuario_grupos ug
                INNER JOIN grupo_menus gm ON gm.grupo_id = ug.grupo_id
                WHERE ug.usuario_id = ? AND gm.menu_id = ?
            ");
            
            if (!$stmt) return false;
            
            $stmt->bind_param("ii", $usuario_id, $menu_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            $tem_acesso = $row && $row['tem_acesso'] > 0;
            
            // Guarda no cache
            self::$cache_acesso[$cache_key] = $tem_acesso;
            
            return $tem_acesso;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::usuarioTemAcessoViaGrupo - Erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se o usuário pode acessar uma página
     * 
     * IMPORTANTE: RETORNA TRUE (permite) nos seguintes casos:
     * - Tabelas não existem (sistema ainda não configurado)
     * - Menu não existe no banco (não cadastrado = permitido)
     * - Menu está ATIVO E:
     *   - Usuário é admin
     *   - Menu tem acesso_padrao = 1
     *   - Menu requer_culto = 1 E usuário tem culto
     *   - Usuário está em um grupo que tem acesso ao menu
     * 
     * BLOQUEIA (retorna FALSE):
     * - Menu está INATIVO (para TODOS, inclusive admin)
     * - Menu requer_admin = 1 e usuário não é admin
     * 
     * @param string $url_ou_codigo Código do menu ou URL da página
     * @return bool
     */
    public static function podeAcessar($url_ou_codigo) {
        // 1. Se tabelas não existem, permite (não quebra sistema)
        if (!self::tabelasExistem()) {
            return true;
        }
        
        // 2. Busca o menu
        $menu = self::getMenu($url_ou_codigo);
        
        // 3. Menu não cadastrado = permite (não quebra sistema existente)
        if (!$menu) {
            return true;
        }
        
        // 4. Menu INATIVO = bloqueia para TODOS (inclusive admin)
        if (!$menu['ativo']) {
            return false;
        }
        
        // 5. Admin SEMPRE pode (se menu está ativo)
        if (self::isAdmin()) {
            return true;
        }
        
        // 6. Menu requer admin exclusivo
        if ($menu['requer_admin']) {
            return false; // Só admin, e já verificamos acima
        }
        
        // 7. Menu tem acesso padrão liberado
        if ($menu['acesso_padrao']) {
            // Mas verifica se requer participar do culto
            if ($menu['requer_culto']) {
                return self::usuarioTemCulto();
            }
            return true;
        }
        
        // 8. Menu requer culto e usuário não tem
        if ($menu['requer_culto'] && !self::usuarioTemCulto()) {
            return false;
        }
        
        // 9. Verifica se usuário tem acesso via grupos
        return self::usuarioTemAcessoViaGrupo($menu['id']);
    }
    
    /**
     * Exige acesso ao menu ou redireciona
     * 
     * @param string $url_ou_codigo Código do menu ou URL
     * @param string $redirect URL para redirecionar se não tiver acesso
     */
    public static function exigirAcesso($url_ou_codigo, $redirect = '../index.php') {
        if (!self::podeAcessar($url_ou_codigo)) {
            // Log de tentativa de acesso negado
            error_log(sprintf(
                "MenuPermissaoService: Acesso negado - Usuario ID: %s, Menu: %s",
                self::getUsuarioId() ?? 'null',
                $url_ou_codigo
            ));
            
            // Verifica se é requisição AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                exit;
            }
            
            // Redireciona para uma página válida
            // Se estiver em uma página de estoque, redireciona para dashboard de estoque
            $pathAtual = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($pathAtual, '/estoque/') !== false) {
                $redirectEstoque = self::ajustarUrl('/estoque/dashboard.php');
                $caminhoCompleto = $_SERVER['DOCUMENT_ROOT'] . str_replace(self::getBaseUrl(), '', $redirectEstoque);
                if (file_exists($caminhoCompleto)) {
                    header("Location: $redirectEstoque?erro=acesso_negado");
                    exit;
                }
            }
            
            // Fallback para dashboard principal
            $redirectPrincipal = self::ajustarUrl('/dashboard.php');
            header("Location: $redirectPrincipal?erro=acesso_negado");
            exit;
        }
    }
    
    /**
     * Exige que o usuário seja admin (para APIs)
     * Retorna JSON 403 se não for admin
     */
    public static function exigirAdmin() {
        if (!self::isAdmin()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Acesso negado. Apenas administradores podem realizar esta ação.'
            ]);
            exit;
        }
    }
    
    /**
     * Exige acesso a um menu (para APIs)
     * Retorna JSON 403 se não tiver acesso
     * 
     * @param string $url_ou_codigo Código do menu
     */
    public static function exigirAcessoAPI($url_ou_codigo) {
        if (!self::podeAcessar($url_ou_codigo)) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Acesso negado. Você não tem permissão para esta ação.'
            ]);
            exit;
        }
    }
    
    /**
     * Obtém todos os menus que o usuário pode acessar
     * 
     * @return array
     */
    public static function getMenusDoUsuario() {
        // Admin vê todos os menus ativos
        if (self::isAdmin()) {
            return self::getTodosMenus();
        }
        
        $usuario_id = self::getUsuarioId();
        if (!$usuario_id) return [];
        
        $conn = self::getConexao();
        if (!$conn) return [];
        
        $tem_culto = self::usuarioTemCulto() ? 1 : 0;
        
        try {
            // Menus com acesso_padrao=1 OU via grupos
            $stmt = $conn->prepare("
                SELECT DISTINCT m.*
                FROM menus m
                LEFT JOIN grupo_menus gm ON gm.menu_id = m.id
                LEFT JOIN usuario_grupos ug ON ug.grupo_id = gm.grupo_id AND ug.usuario_id = ?
                WHERE m.ativo = 1 
                AND m.requer_admin = 0
                AND (m.requer_culto = 0 OR ? = 1)
                AND (m.acesso_padrao = 1 OR ug.usuario_id IS NOT NULL)
                ORDER BY m.categoria, m.ordem
            ");
            
            if (!$stmt) return [];
            
            $stmt->bind_param("ii", $usuario_id, $tem_culto);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $menus = [];
            while ($row = $result->fetch_assoc()) {
                $menus[] = $row;
            }
            $stmt->close();
            
            return $menus;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::getMenusDoUsuario - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém menus agrupados por categoria
     * 
     * @return array
     */
    public static function getMenusAgrupados() {
        $menus = self::getMenusDoUsuario();
        $agrupados = [];
        
        foreach ($menus as $menu) {
            $cat = $menu['categoria'] ?? 'geral';
            if (!isset($agrupados[$cat])) {
                $agrupados[$cat] = [];
            }
            $agrupados[$cat][] = $menu;
        }
        
        return $agrupados;
    }
    
    /**
     * Obtém todos os menus ativos
     * 
     * @return array
     */
    public static function getTodosMenus() {
        $conn = self::getConexao();
        if (!$conn) return [];
        
        try {
            $result = $conn->query("SELECT * FROM menus WHERE ativo = 1 ORDER BY categoria, ordem");
            if (!$result) return [];
            
            $menus = [];
            while ($row = $result->fetch_assoc()) {
                $menus[] = $row;
            }
            return $menus;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::getTodosMenus - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém grupos do usuário
     * 
     * @param int|null $usuario_id
     * @return array
     */
    public static function getGruposDoUsuario($usuario_id = null) {
        $usuario_id = $usuario_id ?? self::getUsuarioId();
        if (!$usuario_id) return [];
        
        $conn = self::getConexao();
        if (!$conn) return [];
        
        try {
            $stmt = $conn->prepare("
                SELECT g.*
                FROM grupos_acesso g
                INNER JOIN usuario_grupos ug ON ug.grupo_id = g.id
                WHERE ug.usuario_id = ? AND g.ativo = 1
            ");
            
            if (!$stmt) return [];
            
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $grupos = [];
            while ($row = $result->fetch_assoc()) {
                $grupos[] = $row;
            }
            $stmt->close();
            
            return $grupos;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::getGruposDoUsuario - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpa o cache (útil após alterações)
     */
    public static function limparCache() {
        self::$cache_menus = [];
        self::$cache_acesso = [];
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // MÉTODOS DE RENDERIZAÇÃO - Para exibir menus dinamicamente
    // ═══════════════════════════════════════════════════════════════════════════
    
    /**
     * Obtém menus por categoria que o usuário pode acessar
     * 
     * @param string $categoria Categoria dos menus (culto, refeicoes, gerenciamento, geral)
     * @param array $excluir Códigos de menus a excluir
     * @return array
     */
    public static function getMenusPorCategoria($categoria, $excluir = []) {
        $menus = self::getMenusDoUsuario();
        $filtrados = [];
        
        foreach ($menus as $menu) {
            if ($menu['categoria'] === $categoria && !in_array($menu['codigo'], $excluir)) {
                $filtrados[] = $menu;
            }
        }
        
        return $filtrados;
    }
    
    /**
     * Renderiza um card de menu Bootstrap
     * 
     * @param array $menu Dados do menu
     * @param string $base_url URL base (IGNORADO - detectado automaticamente)
     * @return string HTML do card
     */
    public static function renderizarCard($menu, $base_url = '') {
        // Ajusta URL para incluir base do sistema (localhost vs produção)
        $url = self::ajustarUrl($menu['url']);
        
        $cor = $menu['cor'] ?? 'primary';
        $icone = $menu['icone'] ?? 'bi-circle';
        $nome = htmlspecialchars($menu['nome']);
        $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? '');
        
        // Cor especial para purple
        $style_border = '';
        $style_icon = '';
        if ($cor === 'purple') {
            $style_border = 'style="border-color: #6f42c1 !important;"';
            $style_icon = 'style="color: #6f42c1;"';
            $cor_classe = '';
        } else {
            $cor_classe = "border-{$cor}";
            $style_icon = '';
        }
        
        $text_color = $cor === 'purple' ? '' : "text-{$cor}";
        
        return <<<HTML
<div class="col-sm-6 col-lg-4">
  <a href="{$url}" class="text-decoration-none">
    <div class="card shadow-sm h-100 {$cor_classe} card-hover" {$style_border}>
      <div class="card-body text-center">
        <i class="bi {$icone} display-4 {$text_color} mb-2" {$style_icon}></i>
        <h5 class="card-title">{$nome}</h5>
        <p class="card-text text-muted">{$descricao}</p>
      </div>
    </div>
  </a>
</div>
HTML;
    }
    
    /**
     * Renderiza cards de uma categoria
     * 
     * @param string $categoria
     * @param string $base_url
     * @param array $excluir Códigos a excluir
     * @return string HTML
     */
    public static function renderizarCategoria($categoria, $base_url = '', $excluir = []) {
        $menus = self::getMenusPorCategoria($categoria, $excluir);
        $html = '';
        
        foreach ($menus as $menu) {
            $html .= self::renderizarCard($menu, $base_url);
        }
        
        return $html;
    }
    
    /**
     * Renderiza um card específico se o usuário tiver permissão
     * 
     * @param string $codigo Código do menu
     * @param string $base_url URL base
     * @return string HTML do card ou vazio se não tiver acesso
     */
    public static function renderizarCardSe($codigo, $base_url = '') {
        if (!self::podeAcessar($codigo)) {
            return '';
        }
        
        $menu = self::getMenu($codigo);
        if (!$menu) {
            return '';
        }
        
        return self::renderizarCard($menu, $base_url);
    }
    
    /**
     * Renderiza um botão de ação se o usuário tiver permissão
     * 
     * @param string $codigo Código do menu
     * @param string $classe_btn Classe CSS do botão (ex: 'btn-reserva')
     * @param string $base_url URL base (IGNORADO - detectado automaticamente)
     * @param string $icone_override Ícone personalizado (opcional)
     * @param string $texto_override Texto personalizado (opcional)
     * @return string HTML do botão ou vazio
     */
    public static function renderizarBotaoSe($codigo, $classe_btn, $base_url = '', $icone_override = null, $texto_override = null) {
        if (!self::podeAcessar($codigo)) {
            return '';
        }
        
        $menu = self::getMenu($codigo);
        if (!$menu) {
            return '';
        }
        
        // Ajusta URL para incluir base do sistema
        $url = self::ajustarUrl($menu['url']);
        
        $icone = $icone_override ?? $menu['icone'] ?? 'bi-circle';
        $texto = $texto_override ?? $menu['nome'];
        
        return <<<HTML
<button class="btn btn-calendario {$classe_btn}" onclick="window.location.href='{$url}'">
    <i class="bi {$icone}"></i>
    <span>{$texto}</span>
</button>
HTML;
    }
    
    /**
     * Renderiza um card de culto (estilo especial)
     * 
     * @param array $menu
     * @param string $base_url (IGNORADO - detectado automaticamente)
     * @return string
     */
    public static function renderizarCardCulto($menu, $base_url = '') {
        // Ajusta URL para incluir base do sistema
        $url = self::ajustarUrl($menu['url']);
        
        $cor = $menu['cor'] ?? 'primary';
        $icone = $menu['icone'] ?? 'bi-circle';
        $nome = htmlspecialchars($menu['nome']);
        $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? '');
        
        return <<<HTML
<div class="col-sm-6 col-lg-4">
  <a href="{$url}" class="text-decoration-none">
    <div class="card shadow-sm h-100 border-{$cor} card-hover culto-card">
      <div class="card-body text-center">
        <i class="bi {$icone} display-4 text-{$cor} mb-2"></i>
        <h5 class="card-title">{$nome}</h5>
        <p class="card-text text-muted">{$descricao}</p>
      </div>
    </div>
  </a>
</div>
HTML;
    }
    
    /**
     * Renderiza todos os cards de culto que o usuário pode acessar
     * 
     * @param string $base_url
     * @param array $excluir Códigos a excluir (ex: ['culto_dashboard'] para não mostrar o dashboard dentro dele mesmo)
     * @return string
     */
    public static function renderizarCardsCulto($base_url = '', $excluir = []) {
        $menus = self::getMenusPorCategoria('culto', $excluir);
        $html = '';
        
        foreach ($menus as $menu) {
            $html .= self::renderizarCardCulto($menu, $base_url);
        }
        
        return $html;
    }
    
    /**
     * Verifica se deve mostrar seção (se tem pelo menos um menu visível)
     * 
     * @param string $categoria
     * @param array $excluir
     * @return bool
     */
    public static function temMenusNaCategoria($categoria, $excluir = []) {
        $menus = self::getMenusPorCategoria($categoria, $excluir);
        return count($menus) > 0;
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // MÉTODOS DE RENDERIZAÇÃO v2 - Estilo Dashboard Moderno
    // ═══════════════════════════════════════════════════════════════════════════
    
    /**
     * Renderiza um card no estilo moderno do dashboard
     * 
     * @param array $menu Dados do menu
     * @return string HTML do card
     */
    public static function renderizarCardModerno($menu) {
        $url = self::ajustarUrl($menu['url']);
        $icone = $menu['icone'] ?? 'bi-circle';
        $nome = htmlspecialchars($menu['nome']);
        $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? '');
        $classe = $menu['classe_card'] ?? 'gerenciamento';
        
        return <<<HTML
<div class="col-6 col-md-4 col-lg-3">
  <a href="{$url}" class="card-link">
    <div class="card card-funcao">
      <div class="funcao-img {$classe}">
        <i class="bi {$icone}"></i>
      </div>
      <div class="card-body text-center py-3">
        <h6 class="card-title mb-1">{$nome}</h6>
        <small class="text-muted">{$descricao}</small>
      </div>
    </div>
  </a>
</div>
HTML;
    }
    
    /**
     * Renderiza todos os cards de uma categoria no estilo moderno
     * 
     * @param string $categoria Categoria dos menus
     * @param array $excluir Códigos de menus a excluir
     * @return string HTML dos cards
     */
    public static function renderizarSecaoModerna($categoria, $excluir = []) {
        $menus = self::getMenusPorCategoria($categoria, $excluir);
        $html = '';
        
        foreach ($menus as $menu) {
            $html .= self::renderizarCardModerno($menu);
        }
        
        return $html;
    }
    
    /**
     * Renderiza uma seção completa com título no estilo moderno
     * 
     * @param string $categoria Categoria dos menus
     * @param string $titulo Título da seção
     * @param string $icone Ícone do título (ex: bi-gear)
     * @param array $excluir Códigos de menus a excluir
     * @return string HTML da seção completa ou vazio se não houver menus
     */
    public static function renderizarSecaoCompletaModerna($categoria, $titulo, $icone, $excluir = []) {
        $menus = self::getMenusPorCategoria($categoria, $excluir);
        
        if (empty($menus)) {
            return '';
        }
        
        $html = <<<HTML
<div class="section-title">
  <i class="bi {$icone}"></i>{$titulo}
</div>
<div class="row g-3 mb-4">
HTML;
        
        foreach ($menus as $menu) {
            $html .= self::renderizarCardModerno($menu);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderiza múltiplas seções agrupadas por categoria
     * Útil para dashboards que mostram várias categorias
     * 
     * @param array $secoes Array de configurações: [['categoria' => 'culto', 'titulo' => 'Culto', 'icone' => 'bi-people'], ...]
     * @param array $excluir Códigos de menus a excluir globalmente
     * @return string HTML de todas as seções
     */
    public static function renderizarMultiplasSecoes($secoes, $excluir = []) {
        $html = '';
        
        foreach ($secoes as $secao) {
            $html .= self::renderizarSecaoCompletaModerna(
                $secao['categoria'],
                $secao['titulo'],
                $secao['icone'],
                array_merge($excluir, $secao['excluir'] ?? [])
            );
        }
        
        return $html;
    }
    
    /**
     * Retorna o CSS necessário para os cards modernos
     * Pode ser incluído no <style> da página
     * 
     * @return string CSS
     */
    public static function getCSSDashboard() {
        return <<<CSS
body {
  background-color: #f0f2f5;
}
.header-painel {
  background: linear-gradient(135deg, #343a40 0%, #495057 100%);
  color: white;
  padding: 1.5rem 0;
  margin-bottom: 1.5rem;
}
.card-funcao {
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.3s ease;
  border: none;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  height: 100%;
}
.card-funcao:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.funcao-img {
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.funcao-img i {
  font-size: 2.5rem;
}
/* Cores por categoria */
.funcao-img.gerenciamento { background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%); }
.funcao-img.gerenciamento i { color: #495057; }
.funcao-img.usuarios { background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%); }
.funcao-img.usuarios i { color: #007bff; }
.funcao-img.relatorios { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); }
.funcao-img.relatorios i { color: #28a745; }
.funcao-img.config { background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); }
.funcao-img.config i { color: #ffc107; }
.funcao-img.facial { background: linear-gradient(135deg, #e2d5f1 0%, #d4c4e8 100%); }
.funcao-img.facial i { color: #6f42c1; }
.funcao-img.refeicoes { background: linear-gradient(135deg, #d1f2eb 0%, #a3e4d7 100%); }
.funcao-img.refeicoes i { color: #20c997; }
.funcao-img.culto { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); }
.funcao-img.culto i { color: #dc3545; }
.funcao-img.logs { background: linear-gradient(135deg, #d6d8db 0%, #c6c8ca 100%); }
.funcao-img.logs i { color: #6c757d; }
.funcao-img.frota { background: linear-gradient(135deg, #cff4fc 0%, #9eeaf9 100%); }
.funcao-img.frota i { color: #0dcaf0; }
.funcao-img.estoque { background: linear-gradient(135deg, #f5e6d3 0%, #e8d4b8 100%); }
.funcao-img.estoque i { color: #fd7e14; }
.funcao-img.presenca { background: linear-gradient(135deg, #c3e6cb 0%, #a8e0b0 100%); }
.funcao-img.presenca i { color: #198754; }
.funcao-img.relatorio { background: linear-gradient(135deg, #bee5eb 0%, #abdde5 100%); }
.funcao-img.relatorio i { color: #17a2b8; }

.card-link {
  text-decoration: none;
  color: inherit;
}
.card-link:hover {
  color: inherit;
}
.section-title {
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6c757d;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e9ecef;
}
.section-title i {
  margin-right: 0.5rem;
}
CSS;
    }
    
    /**
     * Obtém todas as categorias únicas que têm menus acessíveis pelo usuário
     * 
     * @param array $excluir Códigos de menus a excluir
     * @return array Lista de categorias
     */
    public static function getCategoriasDisponiveis($excluir = []) {
        $menus = self::getMenusDoUsuario();
        $categorias = [];
        
        foreach ($menus as $menu) {
            if (!in_array($menu['codigo'], $excluir)) {
                $cat = $menu['categoria'] ?? 'geral';
                if (!in_array($cat, $categorias)) {
                    $categorias[] = $cat;
                }
            }
        }
        
        return $categorias;
    }
    
    /**
     * Obtém nomes dos módulos da tabela modulos
     * 
     * @return array Array associativo: ['codigo' => 'nome']
     */
    private static function getNomesModulos() {
        $conn = self::getConexao();
        if (!$conn) return [];
        
        try {
            // Verificar se tabela modulos existe
            $result = $conn->query("SHOW TABLES LIKE 'modulos'");
            if (!$result || $result->num_rows == 0) {
                return [];
            }
            
            $result = $conn->query("SELECT codigo, nome FROM modulos WHERE ativo = 1");
            if (!$result) return [];
            
            $nomes = [];
            while ($row = $result->fetch_assoc()) {
                $nomes[$row['codigo']] = $row['nome'];
            }
            
            return $nomes;
        } catch (Exception $e) {
            error_log("MenuPermissaoService::getNomesModulos - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém menus principais de módulos para a sidebar do resumo.php
     * Busca menus que são dashboards principais ou primeiro menu de cada categoria
     * 
     * @return array Array de menus formatados para sidebar: ['codigo', 'nome', 'url', 'icone', 'cor_hover']
     */
    public static function getMenusModulosSidebar() {
        $menus = self::getMenusDoUsuario();
        $modulos = [];
        $categorias_processadas = [];
        
        // Buscar nomes dos módulos da tabela modulos
        $nomes_modulos = self::getNomesModulos();
        
        // Mapeamento de categorias para configurações de sidebar
        $config_categorias = [
            'culto' => ['nome_sidebar' => $nomes_modulos['culto'] ?? 'Culto', 'icone' => 'calendar_month', 'cor_hover' => 'primary', 'ordem' => 1],
            'refeicoes' => ['nome_sidebar' => $nomes_modulos['refeicoes'] ?? 'Refeições', 'icone' => 'restaurant', 'cor_hover' => 'orange-400', 'ordem' => 2],
            'frota' => ['nome_sidebar' => $nomes_modulos['frota'] ?? 'Frota', 'icone' => 'directions_car', 'cor_hover' => 'blue-400', 'ordem' => 3],
            'estoque' => ['nome_sidebar' => $nomes_modulos['estoque'] ?? 'Estoque', 'icone' => 'inventory', 'cor_hover' => 'purple-400', 'ordem' => 4],
            'gerenciamento' => ['nome_sidebar' => $nomes_modulos['gerenciamento'] ?? 'Gerenciamento', 'icone' => 'settings', 'cor_hover' => 'purple-400', 'ordem' => 5]
        ];
        
        // Códigos de menus principais conhecidos por categoria
        $menus_principais_por_categoria = [
            'culto' => ['culto_dashboard'],
            'refeicoes' => ['refeicoes_reserva', 'reservas_almoco'],
            'frota' => ['frota_dashboard'],
            'estoque' => ['estoque_dashboard'],
            'gerenciamento' => ['painel_dashboard']
        ];
        
        // Primeiro, buscar menus principais conhecidos (prioridade alta)
        foreach ($menus as $menu) {
            $categoria = $menu['categoria'] ?? 'geral';
            
            // Ignorar categorias que não são módulos principais
            if (!isset($config_categorias[$categoria])) {
                continue;
            }
            
            // Se já processou esta categoria, pular
            if (in_array($categoria, $categorias_processadas)) {
                continue;
            }
            
            $codigo = $menu['codigo'] ?? '';
            
            // Verificar se é um menu principal conhecido
            if (isset($menus_principais_por_categoria[$categoria]) && 
                in_array($codigo, $menus_principais_por_categoria[$categoria])) {
                
                $config = $config_categorias[$categoria];
                $icone_menu = $menu['icone'] ?? '';
                
                // Converter ícone Bootstrap (bi-*) para material-symbols-outlined se necessário
                if (!empty($icone_menu) && strpos($icone_menu, 'bi-') === 0) {
                    // Mapear ícones Bootstrap comuns para Material Symbols
                    $icone_map = [
                        'bi-calendar-month' => 'calendar_month',
                        'bi-calendar' => 'calendar_month',
                        'bi-restaurant' => 'restaurant',
                        'bi-egg-fried' => 'restaurant',
                        'bi-truck' => 'directions_car',
                        'bi-box-seam' => 'inventory',
                        'bi-box' => 'inventory',
                        'bi-gear' => 'settings',
                        'bi-gear-wide' => 'settings',
                        'bi-gear-fill' => 'settings',
                        'bi-speedometer2' => 'settings',
                        'bi-speedometer' => 'settings',
                        'bi-people-fill' => 'calendar_month',
                        'bi-person-check-fill' => 'calendar_month'
                    ];
                    // Tentar encontrar no mapeamento, senão usar padrão da categoria
                    $icone_menu = $icone_map[$icone_menu] ?? $config['icone'];
                } else {
                    // Se não tem ícone ou não é Bootstrap, usar o padrão da categoria
                    // Mas se já é Material Symbol válido (sem bi-), manter
                    if (!empty($icone_menu) && strpos($icone_menu, 'bi-') === false && 
                        (strpos($icone_menu, '_') !== false || strlen($icone_menu) > 3)) {
                        // Parece ser Material Symbol válido, manter
                        $icone_menu = $icone_menu;
                    } else {
                        // Usar padrão da categoria
                        $icone_menu = $config['icone'];
                    }
                }
                
                // Usar nome da tabela modulos (não do menu)
                $nome_modulo = $config['nome_sidebar'];
                
                $modulos[] = [
                    'codigo' => $codigo,
                    'nome' => $nome_modulo,
                    'url' => $menu['url'] ?? '',
                    'icone' => $icone_menu,
                    'cor_hover' => $config['cor_hover'],
                    'categoria' => $categoria,
                    'ordem' => $config['ordem']
                ];
                
                $categorias_processadas[] = $categoria;
            }
        }
        
        // Depois, buscar dashboards ou primeiro menu de cada categoria restante
        foreach ($menus as $menu) {
            $categoria = $menu['categoria'] ?? 'geral';
            
            // Ignorar categorias que não são módulos principais ou já processadas
            if (!isset($config_categorias[$categoria]) || 
                in_array($categoria, $categorias_processadas)) {
                continue;
            }
            
            $codigo = $menu['codigo'] ?? '';
            $url = $menu['url'] ?? '';
            
            // Verificar se é dashboard
            $eh_dashboard = strpos($codigo, '_dashboard') !== false || 
                           strpos($codigo, 'dashboard') !== false ||
                           strpos($url, 'dashboard.php') !== false;
            
            // Se é dashboard ou primeiro menu da categoria (ordem = 1), adicionar
            if ($eh_dashboard || ($menu['ordem'] ?? 999) == 1) {
                $config = $config_categorias[$categoria];
                $icone_menu = $menu['icone'] ?? '';
                
                // Converter ícone Bootstrap para material-symbols-outlined se necessário
                if (!empty($icone_menu) && strpos($icone_menu, 'bi-') === 0) {
                    $icone_map = [
                        'bi-calendar-month' => 'calendar_month',
                        'bi-calendar' => 'calendar_month',
                        'bi-restaurant' => 'restaurant',
                        'bi-truck' => 'directions_car',
                        'bi-box-seam' => 'inventory',
                        'bi-box' => 'inventory',
                        'bi-gear' => 'settings',
                        'bi-gear-wide' => 'settings',
                        'bi-gear-fill' => 'settings',
                        'bi-people-fill' => 'calendar_month',
                        'bi-person-check-fill' => 'calendar_month'
                    ];
                    // Tentar encontrar no mapeamento, senão usar padrão da categoria
                    $icone_menu = $icone_map[$icone_menu] ?? $config['icone'];
                } else {
                    // Se não tem ícone ou não é Bootstrap, usar o padrão da categoria
                    // Mas se já é Material Symbol válido (sem bi-), manter
                    if (!empty($icone_menu) && strpos($icone_menu, 'bi-') === false && 
                        (strpos($icone_menu, '_') !== false || strlen($icone_menu) > 3)) {
                        // Parece ser Material Symbol válido, manter
                        $icone_menu = $icone_menu;
                    } else {
                        // Usar padrão da categoria
                        $icone_menu = $config['icone'];
                    }
                }
                
                // Usar nome da tabela modulos (não do menu)
                $nome_modulo = $config['nome_sidebar'];
                
                $modulos[] = [
                    'codigo' => $codigo,
                    'nome' => $nome_modulo,
                    'url' => $url,
                    'icone' => $icone_menu,
                    'cor_hover' => $config['cor_hover'],
                    'categoria' => $categoria,
                    'ordem' => $config['ordem']
                ];
                
                $categorias_processadas[] = $categoria;
            }
        }
        
        // Ordenar por ordem definida
        usort($modulos, function($a, $b) {
            return ($a['ordem'] ?? 999) <=> ($b['ordem'] ?? 999);
        });
        
        return $modulos;
    }
    
    /**
     * Renderiza os menus de módulos na sidebar do resumo.php
     * 
     * @return string HTML dos links da sidebar
     */
    public static function renderizarModulosSidebar() {
        $modulos = self::getMenusModulosSidebar();
        $html = '';
        
        foreach ($modulos as $modulo) {
            $url = self::ajustarUrl($modulo['url']);
            $icone = htmlspecialchars($modulo['icone']);
            $nome = htmlspecialchars($modulo['nome']);
            $cor_hover = htmlspecialchars($modulo['cor_hover']);
            
            $html .= <<<HTML
<a href="{$url}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-200">
    <span class="material-symbols-outlined text-[20px] group-hover:text-{$cor_hover} transition-colors">{$icone}</span>
    <span class="text-sm font-medium">{$nome}</span>
</a>
HTML;
        }
        
        return $html;
    }
}

