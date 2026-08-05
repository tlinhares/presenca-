<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: painel_dashboard (acesso_padrao=0)                     ║
// ║  Acesso: Grupos com permissão ou Admin                        ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('painel_dashboard');

$isAdmin = MenuPermissaoService::isAdmin();
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Tema renderizado no servidor (sem flash) — ver utils/tema.php
require_once __DIR__ . '/../utils/tema.php';
$temaUsuario = tema_usuario();

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURAÇÃO DAS SEÇÕES DO DASHBOARD
// Cada seção mostra menus de uma categoria específica
// ═══════════════════════════════════════════════════════════════════════════
$secoes = [
    [
        'categoria' => 'gerenciamento',
        'titulo' => 'Gerenciamento',
        'icone' => 'tune',
        'cor_icone' => 'indigo',
        'excluir' => ['painel_dashboard', 'painel_index']
    ],
    [
        'categoria' => 'refeicoes',
        'titulo' => 'Refeições',
        'icone' => 'restaurant',
        'cor_icone' => 'orange',
        'excluir' => []
    ],
    [
        'categoria' => 'culto',
        'titulo' => 'Culto',
        'icone' => 'church',
        'cor_icone' => 'cyan',
        'excluir' => ['culto_dashboard']
    ],
    [
        'categoria' => 'frota',
        'titulo' => 'Frota',
        'icone' => 'directions_car',
        'cor_icone' => 'teal',
        'excluir' => ['frota_dashboard']
    ],
    [
        'categoria' => 'estoque',
        'titulo' => 'Estoque',
        'icone' => 'inventory_2',
        'cor_icone' => 'emerald',
        'excluir' => []
    ],
    [
        'categoria' => 'estoque_config',
        'titulo' => 'Configurações do Estoque',
        'icone' => 'settings',
        'cor_icone' => 'slate',
        'excluir' => []
    ]
];

// Mapeamento de ícones Bootstrap para Material Symbols
$iconeMapping = [
    'bi-sliders' => 'tune',
    'bi-egg-fried' => 'restaurant',
    'bi-people-fill' => 'group',
    'bi-truck' => 'directions_car',
    'bi-box-seam' => 'inventory_2',
    'bi-gear' => 'settings',
    'bi-calendar-month' => 'calendar_month',
    'bi-bar-chart' => 'analytics',
    'bi-person-circle' => 'group',
    'bi-gear-wide-connected' => 'admin_panel_settings',
    'bi-bell' => 'notifications_active',
    'bi-chat' => 'chat',
    'bi-camera' => 'face',
    'bi-smart-display' => 'smart_display',
    'bi-sync-alt' => 'sync',
    'bi-file-text' => 'assignment_ind',
    'bi-building' => 'phonelink_ring',
    'bi-arrow-repeat' => 'sync_alt',
    'bi-person-check' => 'assignment_ind',
    'bi-archive' => 'inventory',
    'bi-list-ul' => 'list',
    'bi-gear-fill' => 'settings',
    'bi-house-door' => 'home',
    'bi-power' => 'logout',
    'bi-arrow-left' => 'arrow_back',
];

// Acento visual POR SEÇÃO (antes: 17 gradientes aleatórios por índice — cada
// card de uma cor diferente, visual caótico nos dois temas). Agora todos os
// cards de uma seção compartilham o acento da seção; o resto do card é neutro.
// Classes literais de propósito: o Tailwind CDN só gera o que enxerga no DOM.
$acentosSecao = [
    'indigo'  => ['tile' => 'from-indigo-500 to-indigo-600',   'hover' => 'group-hover:text-indigo-600 dark:group-hover:text-indigo-400'],
    'orange'  => ['tile' => 'from-orange-500 to-orange-600',   'hover' => 'group-hover:text-orange-600 dark:group-hover:text-orange-400'],
    'cyan'    => ['tile' => 'from-cyan-500 to-cyan-600',       'hover' => 'group-hover:text-cyan-600 dark:group-hover:text-cyan-400'],
    'teal'    => ['tile' => 'from-teal-500 to-teal-600',       'hover' => 'group-hover:text-teal-600 dark:group-hover:text-teal-400'],
    'emerald' => ['tile' => 'from-emerald-500 to-emerald-600', 'hover' => 'group-hover:text-emerald-600 dark:group-hover:text-emerald-400'],
    'slate'   => ['tile' => 'from-slate-500 to-slate-600',     'hover' => 'group-hover:text-slate-600 dark:group-hover:text-slate-300'],
];

function converterIcone($iconeBootstrap) {
    global $iconeMapping;
    $icone = str_replace('bi-', '', $iconeBootstrap);
    $chave = 'bi-' . $icone;
    return $iconeMapping[$chave] ?? $iconeMapping['bi-gear'] ?? 'settings';
}

function obterAcentoSecao($corIcone) {
    global $acentosSecao;
    return $acentosSecao[$corIcone] ?? $acentosSecao['indigo'];
}

function renderizarSecaoTailwind($categoria, $titulo, $iconeMaterial, $corIcone, $excluir = []) {
    $menus = MenuPermissaoService::getMenusPorCategoria($categoria, $excluir);
    
    if (empty($menus)) {
        return '';
    }
    
    $corClasses = [
        'indigo' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400',
        'orange' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400',
        'cyan' => 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400',
        'teal' => 'bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400',
        'emerald' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400',
        'slate' => 'bg-slate-100 dark:bg-slate-900/30 text-slate-600 dark:text-slate-400',
    ];
    
    $corClasse = $corClasses[$corIcone] ?? $corClasses['indigo'];
    
    $html = '<section>';
    $html .= '<div class="flex items-center justify-between mb-6">';
    $html .= '<div class="flex items-center gap-3">';
    $html .= '<div class="w-10 h-10 rounded-lg ' . $corClasse . ' flex items-center justify-center">';
    $html .= '<span class="material-symbols-outlined">' . htmlspecialchars($iconeMaterial) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<h2 class="text-xl font-bold text-gray-900 dark:text-white">' . htmlspecialchars($titulo) . '</h2>';
    $html .= '<p class="text-sm text-gray-500 dark:text-gray-400">Gestão de ' . strtolower($titulo) . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<span class="h-px flex-1 bg-gray-100 dark:bg-slate-800 ml-6 hidden sm:block"></span>';
    $html .= '</div>';
    $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">';
    
    $acento = obterAcentoSecao($corIcone);

    foreach ($menus as $index => $menu) {
        $url = MenuPermissaoService::ajustarUrl($menu['url']);
        $nome = htmlspecialchars($menu['nome']);
        $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? 'Gestão de ' . strtolower($nome));
        $iconeBootstrap = $menu['icone'] ?? 'bi-gear';
        $iconeMaterial = converterIcone($iconeBootstrap);

        $html .= '<a class="dashboard-card group bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-card hover:shadow-card-hover border border-gray-100 dark:border-slate-700/50" href="' . htmlspecialchars($url) . '">';
        $html .= '<div class="flex items-start justify-between mb-4">';
        $html .= '<div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white premium-icon-box bg-gradient-to-br ' . $acento['tile'] . ' shadow-md">';
        $html .= '<span class="material-symbols-outlined text-2xl">' . htmlspecialchars($iconeMaterial) . '</span>';
        $html .= '</div>';
        $html .= '<span class="material-symbols-outlined text-gray-300 dark:text-slate-500 transition-colors ' . $acento['hover'] . '">arrow_outward</span>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h3 class="text-base font-bold text-gray-900 dark:text-white mb-1 transition-colors ' . $acento['hover'] . '">' . $nome . '</h3>';
        $html .= '<p class="text-xs text-gray-500 dark:text-gray-400 font-medium">' . $descricao . '</p>';
        $html .= '</div>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    $html .= '</section>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="pt-BR" <?= tema_html_attrs() ?>>
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Painel de Módulos Clean</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        slate: {
                            850: '#151e2e',
                        },
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    boxShadow: {
                        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.07)',
                        'glow': '0 0 20px rgba(99, 102, 241, 0.3)',
                        'card': '0 2px 10px rgba(0,0,0,0.03)',
                        'card-hover': '0 15px 30px -5px rgba(0,0,0,0.08)',
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                    }
                },
            },
        };
    </script>
<style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .dark .glass-panel {
            background: rgba(15, 23, 42, 0.85);
        }
        .premium-icon-box {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .premium-icon-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(to bottom, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 100%);
            border-radius: inherit;
            pointer-events: none;
        }
        .dashboard-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dashboard-card:hover {
            transform: translateY(-6px);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            height: 100vh;
            overflow: hidden;
        }
        main {
            height: calc(100vh - 5rem);
            overflow-y: auto;
            overflow-x: hidden;
        }
        /* Scrollbar customizada */
        main::-webkit-scrollbar {
            width: 8px;
        }
        main::-webkit-scrollbar-track {
            background: transparent;
        }
        main::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 20px;
        }
        .dark main::-webkit-scrollbar-thumb {
            background-color: #4B5563;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-[#0b1120] font-sans text-gray-800 dark:text-gray-200 transition-colors duration-300 flex flex-col">
<header class="h-20 z-40 glass-panel sticky top-0 border-b border-gray-200/50 dark:border-slate-800/60 shadow-sm">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
<div class="flex items-center gap-10">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 shadow-lg shadow-indigo-500/30 flex items-center justify-center text-white premium-icon-box">
<span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
</div>
<div>
<h1 class="text-lg font-bold text-gray-900 dark:text-white leading-tight">Admin<span class="text-indigo-600 dark:text-indigo-400">Pro</span></h1>
</div>
</div>
<nav class="hidden md:flex items-center gap-1 p-1 bg-gray-100/50 dark:bg-slate-800/50 rounded-xl border border-gray-200/50 dark:border-slate-700/50">
<a class="px-4 py-1.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-white dark:hover:bg-slate-700 transition-all" href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>">
                        Dashboards
                    </a>
<a class="px-4 py-1.5 rounded-lg text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-slate-700 shadow-sm shadow-gray-200/50 dark:shadow-none" href="#">
                        Módulos
                    </a>
</nav>
</div>
<div class="flex items-center gap-4">
<button class="flex items-center justify-center w-10 h-10 rounded-xl text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-indigo-600 transition-all duration-200" onclick="toggleTheme()" title="Alternar tema claro/escuro">
<span class="material-symbols-outlined dark:hidden">dark_mode</span>
<span class="material-symbols-outlined hidden dark:block">light_mode</span>
</button>
<div class="h-8 w-px bg-gray-200 dark:bg-slate-700 hidden md:block"></div>
<a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="flex items-center gap-2 px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-xl text-sm font-medium transition-colors">
<span class="material-symbols-outlined text-lg">arrow_back</span>
<span class="hidden sm:inline">Voltar</span>
</a>
<a href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>" class="flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-red-500 to-rose-600 text-white shadow-lg shadow-rose-500/30 rounded-xl text-sm font-medium hover:from-red-600 hover:to-rose-700 transition-all transform hover:scale-[1.02]">
<span class="material-symbols-outlined text-lg">logout</span>
<span>Sair</span>
</a>
</div>
</div>
</header>
<main class="flex-1 relative scroll-smooth w-full">
<div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-indigo-50/50 to-transparent dark:from-indigo-950/20 dark:to-transparent pointer-events-none z-0"></div>
<div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-20 space-y-12">
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
<div>
<h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Painel de Módulos</h2>
<p class="mt-1 text-gray-500 dark:text-gray-400">Selecione uma categoria para gerenciar o sistema.</p>
</div>
<div class="relative w-full md:w-80">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-xl pointer-events-none">search</span>
<input type="text" id="busca-modulo" placeholder="Buscar módulo… (ex.: push, backup, usuário)" autocomplete="off"
class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 shadow-sm">
</div>
</div>
<div id="busca-vazia" class="hidden bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700/50 rounded-2xl p-8 text-center text-gray-500 dark:text-gray-400">
<span class="material-symbols-outlined text-3xl mb-2 block">search_off</span>
Nenhum módulo encontrado para a busca.
</div>

<?php
// Renderizar seções dinamicamente
$temAlgumaSecao = false;
foreach ($secoes as $secao) {
    $htmlSecao = renderizarSecaoTailwind(
        $secao['categoria'],
        $secao['titulo'],
        $secao['icone'],
        $secao['cor_icone'],
        $secao['excluir'] ?? []
    );
    
    if (!empty($htmlSecao)) {
        echo $htmlSecao;
        $temAlgumaSecao = true;
    }
}

if (!$temAlgumaSecao):
?>
<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-6 text-center">
<p class="text-yellow-800 dark:text-yellow-200">
<span class="material-symbols-outlined align-middle mr-2">info</span>
Nenhum menu disponível para seu perfil de acesso.
</p>
</div>
<?php endif; ?>

</div>
<footer class="mt-auto py-8 text-center border-t border-gray-200 dark:border-slate-800/50 bg-gray-50/50 dark:bg-slate-900/50">
<p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                © <?php echo date('Y'); ?> Sistema de Presença - Desenvolvido por Tiago Linhares
            </p>
</footer>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="../js/aom-ui.js?v=<?= time() ?>"></script>
<script>
// Tema: já vem renderizado do SERVIDOR (classe dark no <html>, ver
// utils/tema.php) — sem loader pós-carregamento, sem flash.
// O toggle alterna a classe, persiste no servidor (usuarios.tema) e espelha
// no localStorage pra outras páginas.
function toggleTheme() {
    const html = document.documentElement;
    html.classList.toggle('dark');

    const novoTema = html.classList.contains('dark') ? 'dark' : 'light';
    html.setAttribute('data-theme', novoTema);
    localStorage.setItem('theme', novoTema);
    $.ajax({
        url: '<?= MenuPermissaoService::ajustarUrl('/api/usuarios/salvar_tema.php') ?>',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ tema: novoTema })
    });
}
localStorage.setItem('theme', '<?= $temaUsuario ?>');

// ── Busca de módulos: filtra os cards conforme digita ──
(function() {
    const campo = document.getElementById('busca-modulo');
    if (!campo) return;
    const norm = t => t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    campo.addEventListener('input', function() {
        const q = norm(campo.value.trim());
        let visiveis = 0;
        document.querySelectorAll('main section').forEach(function(sec) {
            let visiveisSecao = 0;
            sec.querySelectorAll('a.dashboard-card').forEach(function(card) {
                const mostra = q === '' || norm(card.textContent).includes(q);
                card.style.display = mostra ? '' : 'none';
                if (mostra) visiveisSecao++;
            });
            sec.style.display = visiveisSecao > 0 ? '' : 'none';
            visiveis += visiveisSecao;
        });
        document.getElementById('busca-vazia').classList.toggle('hidden', visiveis > 0);
    });
    // Atalho: tecla "/" foca a busca
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement !== campo && !/input|textarea/i.test(document.activeElement.tagName)) {
            e.preventDefault();
            campo.focus();
        }
    });
})();
</script>
</body>
</html>
