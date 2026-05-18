<?php
/**
 * API para buscar menus e módulos que o usuário tem permissão de acessar
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

$termo = trim($_GET['termo'] ?? '');

if (strlen($termo) < 2) {
    echo json_encode(['status' => 'ok', 'resultados' => []]);
    exit;
}

try {
    // Obter todos os menus que o usuário tem permissão
    $menus = MenuPermissaoService::getMenusDoUsuario();
    
    // Normalizar termo de busca (remover acentos, lowercase)
    $termo_normalizado = mb_strtolower($termo, 'UTF-8');
    $termo_normalizado = preg_replace('/[àáâãäå]/u', 'a', $termo_normalizado);
    $termo_normalizado = preg_replace('/[èéêë]/u', 'e', $termo_normalizado);
    $termo_normalizado = preg_replace('/[ìíîï]/u', 'i', $termo_normalizado);
    $termo_normalizado = preg_replace('/[òóôõö]/u', 'o', $termo_normalizado);
    $termo_normalizado = preg_replace('/[ùúûü]/u', 'u', $termo_normalizado);
    $termo_normalizado = preg_replace('/[ç]/u', 'c', $termo_normalizado);
    
    $resultados = [];
    
    // Buscar nos menus
    foreach ($menus as $menu) {
        $nome = mb_strtolower($menu['nome'] ?? '', 'UTF-8');
        $nome_normalizado = preg_replace('/[àáâãäå]/u', 'a', $nome);
        $nome_normalizado = preg_replace('/[èéêë]/u', 'e', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ìíîï]/u', 'i', $nome_normalizado);
        $nome_normalizado = preg_replace('/[òóôõö]/u', 'o', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ùúûü]/u', 'u', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ç]/u', 'c', $nome_normalizado);
        
        $descricao = mb_strtolower($menu['descricao'] ?? '', 'UTF-8');
        $descricao_normalizada = preg_replace('/[àáâãäå]/u', 'a', $descricao);
        $descricao_normalizada = preg_replace('/[èéêë]/u', 'e', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ìíîï]/u', 'i', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[òóôõö]/u', 'o', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ùúûü]/u', 'u', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ç]/u', 'c', $descricao_normalizada);
        
        $codigo = mb_strtolower($menu['codigo'] ?? '', 'UTF-8');
        
        // Verificar se o termo está no nome, descrição ou código
        if (strpos($nome_normalizado, $termo_normalizado) !== false ||
            strpos($descricao_normalizada, $termo_normalizado) !== false ||
            strpos($codigo, $termo_normalizado) !== false) {
            
            // Determinar ícone baseado na categoria ou código
            $icone = 'description';
            $categoria = $menu['categoria'] ?? '';
            
            if (strpos($categoria, 'culto') !== false || strpos($menu['codigo'] ?? '', 'culto') !== false) {
                $icone = 'calendar_month';
            } elseif (strpos($categoria, 'refeicoes') !== false || strpos($categoria, 'almoco') !== false || strpos($menu['codigo'] ?? '', 'refeicoes') !== false || strpos($menu['codigo'] ?? '', 'almoco') !== false) {
                $icone = 'restaurant';
            } elseif (strpos($categoria, 'frota') !== false || strpos($menu['codigo'] ?? '', 'frota') !== false) {
                $icone = 'directions_car';
            } elseif (strpos($categoria, 'estoque') !== false || strpos($menu['codigo'] ?? '', 'estoque') !== false) {
                $icone = 'inventory';
            } elseif (strpos($categoria, 'painel') !== false || strpos($menu['codigo'] ?? '', 'painel') !== false || strpos($menu['codigo'] ?? '', 'dashboard') !== false) {
                $icone = 'dashboard';
            } elseif (strpos($menu['codigo'] ?? '', 'config') !== false) {
                $icone = 'settings';
            }
            
            $resultados[] = [
                'tipo' => 'menu',
                'nome' => $menu['nome'] ?? 'Menu',
                'descricao' => $menu['descricao'] ?? '',
                'url' => MenuPermissaoService::ajustarUrl($menu['url'] ?? '#'),
                'icone' => $icone,
                'categoria' => $categoria
            ];
        }
    }
    
    // Buscar nos módulos principais (baseado nas permissões já verificadas)
    $modulos = [
        ['codigo' => 'painel_dashboard', 'nome' => 'Gerenciamento', 'descricao' => 'Painel administrativo', 'url' => '/painel/dashboard.php', 'icone' => 'settings'],
        ['codigo' => 'culto_dashboard', 'nome' => 'Culto', 'descricao' => 'Presenças', 'url' => '/culto/dashboard.php', 'icone' => 'calendar_month'],
        ['codigo' => 'refeicoes_reserva', 'nome' => 'Refeições', 'descricao' => 'Reservas', 'url' => '/reservas/almoco.php', 'icone' => 'restaurant'],
        ['codigo' => 'frota_dashboard', 'nome' => 'Frota', 'descricao' => 'Veículos', 'url' => '/frota/dashboard.php', 'icone' => 'directions_car'],
        ['codigo' => 'estoque_dashboard', 'nome' => 'Estoque', 'descricao' => 'Materiais', 'url' => '/estoque/dashboard.php', 'icone' => 'inventory']
    ];
    
    foreach ($modulos as $modulo) {
        // Verificar se usuário tem acesso a este módulo
        if (!MenuPermissaoService::podeAcessar($modulo['codigo'])) {
            continue;
        }
        
        $nome = mb_strtolower($modulo['nome'], 'UTF-8');
        $nome_normalizado = preg_replace('/[àáâãäå]/u', 'a', $nome);
        $nome_normalizado = preg_replace('/[èéêë]/u', 'e', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ìíîï]/u', 'i', $nome_normalizado);
        $nome_normalizado = preg_replace('/[òóôõö]/u', 'o', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ùúûü]/u', 'u', $nome_normalizado);
        $nome_normalizado = preg_replace('/[ç]/u', 'c', $nome_normalizado);
        
        $descricao = mb_strtolower($modulo['descricao'], 'UTF-8');
        $descricao_normalizada = preg_replace('/[àáâãäå]/u', 'a', $descricao);
        $descricao_normalizada = preg_replace('/[èéêë]/u', 'e', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ìíîï]/u', 'i', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[òóôõö]/u', 'o', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ùúûü]/u', 'u', $descricao_normalizada);
        $descricao_normalizada = preg_replace('/[ç]/u', 'c', $descricao_normalizada);
        
        if (strpos($nome_normalizado, $termo_normalizado) !== false ||
            strpos($descricao_normalizada, $termo_normalizado) !== false) {
            
            // Verificar se já não está nos resultados (evitar duplicatas)
            $ja_existe = false;
            foreach ($resultados as $r) {
                if ($r['url'] === MenuPermissaoService::ajustarUrl($modulo['url'])) {
                    $ja_existe = true;
                    break;
                }
            }
            
            if (!$ja_existe) {
                $resultados[] = [
                    'tipo' => 'modulo',
                    'nome' => $modulo['nome'],
                    'descricao' => $modulo['descricao'],
                    'url' => MenuPermissaoService::ajustarUrl($modulo['url']),
                    'icone' => $modulo['icone'],
                    'categoria' => 'modulos'
                ];
            }
        }
    }
    
    // Limitar a 10 resultados
    $resultados = array_slice($resultados, 0, 10);
    
    echo json_encode([
        'status' => 'ok',
        'resultados' => $resultados,
        'total' => count($resultados)
    ]);
    
} catch (Exception $e) {
    error_log("Erro em buscar_menus_modulos.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar',
        'resultados' => []
    ]);
}
?>

