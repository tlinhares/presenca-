<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
include_once(__DIR__ . '/../utils/acesso_especial.php');
include_once(__DIR__ . '/../auth/verifica_permissao.php');

// ╔════════════════════════════════════════════════════════════════╗
// ║  SISTEMA DE PERMISSÕES POR MENU                               ║
// ║  Menu: culto_dashboard (acesso_padrao=0, requer_culto=1)      ║
// ║  Acesso: Grupo "Líder de Culto" ou Admin                      ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_dashboard');

$isAdmin = MenuPermissaoService::isAdmin();
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Obter menus da categoria culto
$menus = MenuPermissaoService::getMenusPorCategoria('culto', ['culto_dashboard']);

// Mapeamento de cores para os cards (com valores hex para uso inline)
$coresCards = [
    ['nome' => 'cyan', 'bar' => '#22d3ee', 'bg' => '#ecfeff', 'bgDark' => '#164e63', 'text' => '#0891b2', 'textDark' => '#67e8f9'],
    ['nome' => 'emerald', 'bar' => '#34d399', 'bg' => '#d1fae5', 'bgDark' => '#064e3b', 'text' => '#059669', 'textDark' => '#6ee7b7'],
    ['nome' => 'amber', 'bar' => '#fbbf24', 'bg' => '#fef3c7', 'bgDark' => '#78350f', 'text' => '#d97706', 'textDark' => '#fcd34d'],
    ['nome' => 'orange', 'bar' => '#fb923c', 'bg' => '#fed7aa', 'bgDark' => '#7c2d12', 'text' => '#ea580c', 'textDark' => '#fdba74'],
    ['nome' => 'sky', 'bar' => '#38bdf8', 'bg' => '#e0f2fe', 'bgDark' => '#0c4a6e', 'text' => '#0284c7', 'textDark' => '#7dd3fc'],
    ['nome' => 'blue', 'bar' => '#60a5fa', 'bg' => '#dbeafe', 'bgDark' => '#1e3a8a', 'text' => '#2563eb', 'textDark' => '#93c5fd'],
    ['nome' => 'yellow', 'bar' => '#facc15', 'bg' => '#fef9c3', 'bgDark' => '#713f12', 'text' => '#ca8a04', 'textDark' => '#fde047'],
    ['nome' => 'purple', 'bar' => '#a78bfa', 'bg' => '#ede9fe', 'bgDark' => '#581c87', 'text' => '#7c3aed', 'textDark' => '#c4b5fd'],
    ['nome' => 'pink', 'bar' => '#f472b6', 'bg' => '#fce7f3', 'bgDark' => '#831843', 'text' => '#db2777', 'textDark' => '#f9a8d4'],
    ['nome' => 'indigo', 'bar' => '#818cf8', 'bg' => '#e0e7ff', 'bgDark' => '#312e81', 'text' => '#4f46e5', 'textDark' => '#a5b4fc'],
    ['nome' => 'teal', 'bar' => '#2dd4bf', 'bg' => '#ccfbf1', 'bgDark' => '#134e4a', 'text' => '#0d9488', 'textDark' => '#5eead4'],
    ['nome' => 'lime', 'bar' => '#84cc16', 'bg' => '#ecfccb', 'bgDark' => '#365314', 'text' => '#65a30d', 'textDark' => '#bef264'],
    ['nome' => 'rose', 'bar' => '#fb7185', 'bg' => '#ffe4e6', 'bgDark' => '#881337', 'text' => '#e11d48', 'textDark' => '#fda4af'],
    ['nome' => 'violet', 'bar' => '#a78bfa', 'bg' => '#ede9fe', 'bgDark' => '#581c87', 'text' => '#7c3aed', 'textDark' => '#c4b5fd'],
    ['nome' => 'fuchsia', 'bar' => '#d946ef', 'bg' => '#fae8ff', 'bgDark' => '#701a75', 'text' => '#c026d3', 'textDark' => '#f0abfc'],
    ['nome' => 'green', 'bar' => '#4ade80', 'bg' => '#dcfce7', 'bgDark' => '#14532d', 'text' => '#16a34a', 'textDark' => '#86efac'],
];

// Mapeamento de ícones Bootstrap para Material Icons
$iconeMapping = [
    'bi-sync-alt' => 'sync_alt',
    'bi-arrow-repeat' => 'sync_alt',
    'bi-people-fill' => 'how_to_reg',
    'bi-person-check' => 'how_to_reg',
    'bi-file-text' => 'assignment',
    'bi-file-earmark-text' => 'assignment',
    'bi-gear' => 'settings_suggest',
    'bi-gear-fill' => 'settings_suggest',
    'bi-cloud-arrow-up' => 'cloud_sync',
    'bi-cloud' => 'cloud_sync',
    'bi-bar-chart' => 'analytics',
    'bi-bar-chart-fill' => 'analytics',
    'bi-download' => 'backup',
    'bi-archive' => 'backup',
    'bi-pencil-square' => 'edit_note',
    'bi-pencil' => 'edit_note',
    'bi-camera' => 'face',
    'bi-camera-fill' => 'face',
    'bi-bell' => 'notifications_active',
    'bi-bell-fill' => 'notifications_active',
    'bi-check-circle' => 'check_circle',
    'bi-clock' => 'schedule',
    'bi-info-circle' => 'info',
    'bi-code' => 'code',
];

function converterIcone($iconeBootstrap) {
    global $iconeMapping;
    $icone = str_replace('bi-', '', $iconeBootstrap);
    $chave = 'bi-' . $icone;
    return $iconeMapping[$chave] ?? $iconeMapping['bi-gear'] ?? 'settings';
}

function obterCorCard($index) {
    global $coresCards;
    return $coresCards[$index % count($coresCards)];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Módulo de Culto - Redesign</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#8B5CF6", // Violet 500 equivalent
                        secondary: "#7C3AED", // Violet 600
                        "background-light": "#F3F4F6", // Gray 100
                        "background-dark": "#111827", // Gray 900
                        "card-light": "#FFFFFF",
                        "card-dark": "#1F2937", // Gray 800
                        "surface-light": "#F9FAFB", // Gray 50
                        "surface-dark": "#374151", // Gray 700
                    },
                    fontFamily: {
                        display: ["Inter", "sans-serif"],
                        body: ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                        'xl': "1rem",
                        '2xl': "1.5rem",
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
                        'hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                    }
                },
            },
        };
    </script>
<style>
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background-color: #CBD5E1;
    border-radius: 20px;
}
.dark ::-webkit-scrollbar-thumb {
    background-color: #4B5563;
}
.card-transition {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.card-transition:hover {
    transform: translateY(-4px);
}
.card-link {
    text-decoration: none;
    color: inherit;
}
.card-link:hover {
    text-decoration: none;
    color: inherit;
}
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-gray-800 dark:text-gray-100 font-body min-h-screen flex flex-col transition-colors duration-300">
<header class="bg-primary text-white shadow-lg relative overflow-hidden">
<div class="absolute inset-0 opacity-10 pointer-events-none">
<svg class="h-full w-full" preserveAspectRatio="none" viewBox="0 0 100 100">
<path d="M0 100 C 20 0 50 0 100 100 Z" fill="currentColor"></path>
</svg>
</div>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative z-10">
<div class="flex flex-col md:flex-row justify-between items-center gap-4">
<div class="flex items-center gap-4">
<div class="bg-white/20 p-2.5 rounded-lg backdrop-blur-sm">
<span class="material-icons-round text-3xl">groups</span>
</div>
<div>
<h1 class="text-2xl font-bold tracking-tight">Módulo de Culto</h1>
<p class="text-purple-100 text-sm font-medium opacity-90">Controle de Presenças e Justificativas</p>
</div>
</div>
<div class="flex items-center gap-3">
<a href="<?= MenuPermissaoService::ajustarUrl('/resumo.php') ?>" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors border border-white/20 backdrop-blur-sm">
<span class="material-icons-round text-base">arrow_back</span>
                        Voltar
                    </a>
<a href="<?= MenuPermissaoService::ajustarUrl('/logout.php') ?>" class="flex items-center gap-2 bg-red-500/80 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm backdrop-blur-sm">
<span class="material-icons-round text-base">logout</span>
                        Sair
                    </a>
<button class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white ml-2 transition-colors" onclick="toggleTheme()">
<span class="material-icons-round text-base block dark:hidden">dark_mode</span>
<span class="material-icons-round text-base hidden dark:block">light_mode</span>
</button>
</div>
</div>
</div>
</header>
<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full space-y-8">
<?php if (empty($menus)): ?>
<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-6 text-center">
<p class="text-yellow-800 dark:text-yellow-200">
<span class="material-icons-round align-middle mr-2">info</span>
Nenhum menu disponível para seu perfil de acesso.
</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<?php foreach ($menus as $index => $menu): 
    $url = MenuPermissaoService::ajustarUrl($menu['url']);
    $nome = htmlspecialchars($menu['nome']);
    $descricao = htmlspecialchars($menu['descricao_card'] ?? $menu['descricao'] ?? '');
    $iconeBootstrap = $menu['icone'] ?? 'bi-gear';
    $iconeMaterial = converterIcone($iconeBootstrap);
    $cor = obterCorCard($index);
    $badge = strtoupper(substr($nome, 0, 3));
?>
<a href="<?= $url ?>" class="card-link card-transition bg-white dark:bg-gray-800 rounded-xl shadow-soft hover:shadow-hover overflow-hidden border border-gray-100 dark:border-gray-700 group cursor-pointer block">
<div class="h-2" style="background-color: <?= $cor['bar'] ?>"></div>
<div class="p-6 flex flex-col items-center text-center h-full">
<div class="mb-4 p-4 rounded-full group-hover:scale-110 transition-transform duration-300 icon-bg-<?= $index % 16 ?>" style="background-color: <?= $cor['bg'] ?>">
<span class="material-icons-round text-4xl icon-color-<?= $index % 16 ?>" style="color: <?= $cor['text'] ?>"><?= $iconeMaterial ?></span>
</div>
<h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2"><?= $nome ?></h3>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4 flex-grow"><?= $descricao ?></p>
<span class="text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider badge-<?= $index % 16 ?>" style="color: <?= $cor['text'] ?>; background-color: <?= $cor['bg'] ?>"><?= $badge ?></span>
</div>
<style>
.dark .icon-color-<?= $index % 16 ?> {
    color: <?= $cor['textDark'] ?> !important;
}
.dark .badge-<?= $index % 16 ?> {
    color: <?= $cor['textDark'] ?> !important;
    background-color: <?= $cor['bgDark'] ?> !important;
}
</style>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-card-light dark:bg-card-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-8 mt-8">
<div class="flex flex-col md:flex-row gap-8 justify-between">
<div class="flex-1 space-y-4">
<div class="flex items-center gap-2 mb-2">
<span class="material-icons-round text-primary text-2xl">info</span>
<h2 class="text-lg font-bold text-gray-900 dark:text-white">Informações do Sistema de Culto</h2>
</div>
<div class="space-y-3 pl-1">
<div class="flex items-center gap-3">
<div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400">
<span class="material-icons-round text-sm">check_circle</span>
</div>
<div>
<p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Status do Sistema</p>
<p class="text-sm font-medium text-gray-900 dark:text-white">Sistema Ativo e Operacional</p>
</div>
</div>
<div class="flex items-center gap-3">
<div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
<span class="material-icons-round text-sm">schedule</span>
</div>
<div>
<p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Última Atualização</p>
<p class="text-sm font-medium text-gray-900 dark:text-white"><?= date('d/m/Y H:i') ?></p>
</div>
</div>
<div class="flex items-center gap-3">
<div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
<span class="material-icons-round text-sm">code</span>
</div>
<div>
<p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Versão Atual</p>
<p class="text-sm font-medium text-gray-900 dark:text-white">2.0 (Dinâmico)</p>
</div>
</div>
</div>
</div>
<div class="hidden md:block w-px bg-gray-200 dark:bg-gray-700"></div>
<div class="flex-1">
<h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">Funcionalidades Ativas</h3>
<ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">face</span>
                            Reconhecimento facial
                        </li>
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">description</span>
                            Gestão de justificativas
                        </li>
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">bar_chart</span>
                            Relatórios automáticos
                        </li>
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">sync</span>
                            Sincronização em tempo real
                        </li>
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">notifications_active</span>
                            Alertas de presença
                        </li>
<li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
<span class="material-icons-round text-primary text-base">cloud_done</span>
                            Backup automático
                        </li>
</ul>
</div>
</div>
</div>
</main>
<footer class="mt-auto py-6 text-center text-gray-500 dark:text-gray-400 text-sm bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
<div class="max-w-7xl mx-auto px-4">
<p>© <?php echo date('Y'); ?> Sistema de Presença - Desenvolvido por Tiago Linhares</p>
</div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
// Toggle de tema claro/escuro
function toggleTheme() {
    const html = document.documentElement;
    html.classList.toggle('dark');
    
    // Salvar preferência
    const isDark = html.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// Carregar tema salvo
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (savedTheme === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        // Preferência do sistema
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    }
});
</script>
</body>
</html>
