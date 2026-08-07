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

// ═══════════════════════════════════════════════════════════════════════════
// ÍCONE + COR POR MENU (pedido do admin, 2026-08-07): o ícone precisa
// representar a ação (dias fechado = calendário, whatsapp = chat...) e a cor
// referencia a ação — sem deixar tudo azul, mas sem virar arco-íris aleatório:
// cores são SEMÂNTICAS e reutilizadas por família de ação.
// Classes Tailwind literais de propósito (o CDN só gera o que enxerga no DOM).
// ═══════════════════════════════════════════════════════════════════════════
$paletas = [
    'rose'    => ['tile' => 'from-rose-500 to-rose-600',       'hover' => 'group-hover:text-rose-600 dark:group-hover:text-rose-400'],
    'amber'   => ['tile' => 'from-amber-500 to-amber-600',     'hover' => 'group-hover:text-amber-600 dark:group-hover:text-amber-400'],
    'emerald' => ['tile' => 'from-emerald-500 to-emerald-600', 'hover' => 'group-hover:text-emerald-600 dark:group-hover:text-emerald-400'],
    'sky'     => ['tile' => 'from-sky-500 to-sky-600',         'hover' => 'group-hover:text-sky-600 dark:group-hover:text-sky-400'],
    'indigo'  => ['tile' => 'from-indigo-500 to-indigo-600',   'hover' => 'group-hover:text-indigo-600 dark:group-hover:text-indigo-400'],
    'violet'  => ['tile' => 'from-violet-500 to-violet-600',   'hover' => 'group-hover:text-violet-600 dark:group-hover:text-violet-400'],
    'cyan'    => ['tile' => 'from-cyan-500 to-cyan-600',       'hover' => 'group-hover:text-cyan-600 dark:group-hover:text-cyan-400'],
    'teal'    => ['tile' => 'from-teal-500 to-teal-600',       'hover' => 'group-hover:text-teal-600 dark:group-hover:text-teal-400'],
    'orange'  => ['tile' => 'from-orange-500 to-orange-600',   'hover' => 'group-hover:text-orange-600 dark:group-hover:text-orange-400'],
    'slate'   => ['tile' => 'from-slate-500 to-slate-600',     'hover' => 'group-hover:text-slate-600 dark:group-hover:text-slate-300'],
    'aom'     => ['tile' => 'from-[#1d4e8f] to-[#163d72]',     'hover' => 'group-hover:text-[#1d4e8f] dark:group-hover:text-[#7da7d9]'],
];

// bi-* (banco) => [ícone Material fiel, paleta semântica]
$iconeMenu = [
    // Calendário/datas
    'bi-calendar-x'          => ['event_busy', 'rose'],
    'bi-calendar-check'      => ['event_available', 'emerald'],
    'bi-calendar-month'      => ['calendar_month', 'sky'],
    // Notificações/push/comunicação
    'bi-bell'                => ['notifications', 'amber'],
    'bi-bell-fill'           => ['notifications_active', 'amber'],
    'bi-send-fill'           => ['send', 'sky'],
    'bi-megaphone-fill'      => ['campaign', 'orange'],
    'bi-phone-vibrate-fill'  => ['smartphone', 'teal'],
    'bi-whatsapp'            => ['chat', 'emerald'],
    'bi-chat'                => ['chat', 'emerald'],
    // Pessoas/usuários
    'bi-people'              => ['group', 'indigo'],
    'bi-people-fill'         => ['group', 'indigo'],
    'bi-person-check-fill'   => ['how_to_reg', 'emerald'],
    'bi-person-circle'       => ['account_circle', 'indigo'],
    // Facial/câmeras
    'bi-person-bounding-box' => ['familiar_face_and_zone', 'cyan'],
    'bi-camera'              => ['face', 'cyan'],
    'bi-camera-video'        => ['videocam', 'cyan'],
    'bi-hdd-stack-fill'      => ['dns', 'slate'],
    // Relatórios/gráficos/documentos
    'bi-graph-up'            => ['monitoring', 'violet'],
    'bi-bar-chart'           => ['analytics', 'violet'],
    'bi-file-earmark-bar-graph' => ['analytics', 'violet'],
    'bi-file-text'           => ['description', 'slate'],
    'bi-file-text-fill'      => ['description', 'violet'],
    'bi-file-earmark-code'   => ['code', 'violet'],
    // Segurança/config
    'bi-shield-check'        => ['verified_user', 'emerald'],
    'bi-shield-lock-fill'    => ['lock_person', 'rose'],
    'bi-gear'                => ['settings', 'slate'],
    'bi-gear-fill'           => ['settings', 'slate'],
    'bi-gear-wide-connected' => ['admin_panel_settings', 'slate'],
    'bi-menu-button-wide'    => ['list_alt', 'indigo'],
    'bi-sliders'             => ['tune', 'slate'],
    // Refeições/valores
    'bi-egg-fried'           => ['restaurant', 'orange'],
    'bi-cash-coin'           => ['payments', 'emerald'],
    'bi-robot'               => ['smart_toy', 'violet'],
    // Estoque
    'bi-box-seam'            => ['inventory_2', 'amber'],
    'bi-box-arrow-in-down'   => ['archive', 'emerald'],
    'bi-clipboard-check'     => ['fact_check', 'sky'],
    'bi-plus-circle'         => ['add_circle', 'emerald'],
    'bi-ui-checks'           => ['checklist', 'teal'],
    'bi-arrow-left-right'    => ['swap_horiz', 'sky'],
    'bi-check-circle'        => ['task_alt', 'emerald'],
    'bi-building'            => ['apartment', 'slate'],
    'bi-tags'                => ['sell', 'amber'],
    'bi-rulers'              => ['straighten', 'slate'],
    'bi-geo-alt'             => ['location_on', 'rose'],
    // Frota
    'bi-truck'               => ['local_shipping', 'sky'],
    'bi-car-front'           => ['directions_car', 'sky'],
    'bi-tools'               => ['build', 'amber'],
    'bi-box-arrow-right'     => ['logout', 'orange'],
    'bi-box-arrow-in-left'   => ['input', 'emerald'],
    'bi-clock-history'       => ['history', 'slate'],
    // Diversos
    'bi-speedometer2'        => ['speed', 'aom'],
    'bi-house'               => ['home', 'aom'],
    'bi-house-door'          => ['home', 'aom'],
    'bi-download'            => ['download', 'slate'],
    'bi-arrow-clockwise'     => ['sync', 'sky'],
    'bi-arrow-repeat'        => ['sync_alt', 'sky'],
    'bi-sync-alt'            => ['sync', 'sky'],
    'bi-smart-display'       => ['smart_display', 'cyan'],
    'bi-power'               => ['logout', 'rose'],
    'bi-arrow-left'          => ['arrow_back', 'slate'],
];

function obterIconeMenu($iconeBootstrap) {
    global $iconeMenu, $paletas;
    $info = $iconeMenu[$iconeBootstrap] ?? ['settings', 'aom'];
    return ['icone' => $info[0]] + $paletas[$info[1]];
}

function renderizarSecaoTailwind($categoria, $titulo, $iconeMaterial, $corIcone, $excluir = []) {
    $menus = MenuPermissaoService::getMenusPorCategoria($categoria, $excluir);
    
    if (empty($menus)) {
        return '';
    }
    
    $corClasse = 'bg-[#eaf1fa] dark:bg-[#1d4e8f]/25 text-[#1d4e8f] dark:text-[#7da7d9]';
    
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
    
    foreach ($menus as $index => $menu) {
        $url = MenuPermissaoService::ajustarUrl($menu['url']);
        $nome = htmlspecialchars($menu['nome']);
        $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? 'Gestão de ' . strtolower($nome));
        $acento = obterIconeMenu($menu['icone'] ?? 'bi-gear');
        $iconeMaterial = $acento['icone'];

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
<header class="h-20 z-40 sticky top-0 bg-[#1d4e8f] shadow-md">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
<div class="flex items-center gap-10">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-white/15 border border-white/25 flex items-center justify-center text-white">
<span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
</div>
<div>
<h1 class="text-lg font-bold text-white leading-tight">Painel <span class="opacity-75">Administrativo</span></h1>
</div>
</div>
<nav class="hidden md:flex items-center gap-1 p-1 bg-white/10 rounded-xl border border-white/20">
<a class="px-4 py-1.5 rounded-lg text-sm font-medium text-white/70 hover:text-white hover:bg-white/10 transition-all" href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>">
                        Dashboards
                    </a>
<a class="px-4 py-1.5 rounded-lg text-sm font-medium text-[#1d4e8f] bg-white shadow-sm" href="#">
                        Módulos
                    </a>
</nav>
</div>
<div class="flex items-center gap-4">
<button class="flex items-center justify-center w-10 h-10 rounded-xl text-white/80 hover:bg-white/15 hover:text-white transition-all duration-200" onclick="toggleTheme()" title="Alternar tema claro/escuro">
<span class="material-symbols-outlined dark:hidden">dark_mode</span>
<span class="material-symbols-outlined hidden dark:block">light_mode</span>
</button>
<div class="h-8 w-px bg-white/25 hidden md:block"></div>
<a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="flex items-center gap-2 px-4 py-2 text-white/85 hover:bg-white/15 hover:text-white rounded-xl text-sm font-medium transition-colors">
<span class="material-symbols-outlined text-lg">arrow_back</span>
<span class="hidden sm:inline">Voltar</span>
</a>
<a href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>" class="flex items-center gap-2 px-5 py-2 bg-[#dc3545] text-white rounded-xl text-sm font-medium hover:bg-[#bb2d3b] transition-colors">
<span class="material-symbols-outlined text-lg">logout</span>
<span>Sair</span>
</a>
</div>
</div>
</header>
<main class="flex-1 relative scroll-smooth w-full">

<div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-20 space-y-12">
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
<div>
<h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Painel de Módulos</h2>
<p class="mt-1 text-gray-500 dark:text-gray-400">Selecione uma categoria para gerenciar o sistema.</p>
</div>
<div class="relative w-full md:w-80">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-xl pointer-events-none">search</span>
<input type="text" id="busca-modulo" placeholder="Buscar módulo… (ex.: push, backup, usuário)" autocomplete="off"
class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1d4e8f]/40 focus:border-[#1d4e8f] shadow-sm">
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
