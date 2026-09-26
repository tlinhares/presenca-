<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
if (isset($_SESSION['usuario_id'])) {
  // Todos os usuários (admin e funcionário) vão para resumo.php
  header('Location: resumo.php');
  exit;
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Presença AOM</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#1d4e8f",
            "primary-claro": "#4f8ad4",
          },
          fontFamily: {
            "display": ["Inter", "sans-serif"]
          },
          borderRadius: {
            "DEFAULT": "0.25rem",
            "lg": "0.5rem",
            "xl": "0.75rem",
            "full": "9999px"
          },
        },
      },
    }
  </script>
  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .shake {
      animation: shake 0.4s ease-in-out;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }
    /* Animação de saída ao logar com sucesso */
    @keyframes flyOutLeft  { to { transform: translateX(-140%) rotate(-5deg); opacity: 0; } }
    @keyframes flyOutRight { to { transform: translateX(140%)  rotate(5deg);  opacity: 0; } }
    @keyframes cardCollapse { to { transform: scale(.85) translateY(-24px); opacity: 0; } }
    .fly-left  { animation: flyOutLeft  .5s cubic-bezier(.6,-.28,.74,.05) forwards; }
    .fly-right { animation: flyOutRight .5s cubic-bezier(.6,-.28,.74,.05) forwards; }
    .card-collapse { animation: cardCollapse .45s ease-in forwards; }

    /* ═══════════════════════════════════════════════════════════════
       FUNDO "AURORA" — curvas luminosas em azul AOM (referência: vídeo
       aprovado pelo admin; original era dourado). CSS puro, sem vídeo:
       fitas com gradiente + blur + deriva lenta. Respeita
       prefers-reduced-motion.
       ═══════════════════════════════════════════════════════════════ */
    body { background: #070b14; overflow-x: hidden; }
    .aurora {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
    }
    .aurora::after {
      /* vinheta pra escurecer as bordas como no vídeo */
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 90% 70% at 50% 45%, transparent 45%, rgba(3, 6, 12, .88) 100%);
    }
    .fita {
      position: absolute;
      width: 160vmax;
      height: 160vmax;
      left: 50%;
      top: 50%;
      border-radius: 42% 58% 55% 45% / 48% 42% 58% 52%;
      filter: blur(14px);
      opacity: .55;
      will-change: transform;
    }
    .fita-1 {
      border: 3px solid transparent;
      background:
        conic-gradient(from 180deg, transparent 0 40%, #1d4e8f 47%, #7db4f5 50%, #1d4e8f 53%, transparent 60% 100%) border-box;
      -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
              mask-composite: exclude;
      transform: translate(-50%, -50%) rotate(0deg);
      animation: girar 46s linear infinite;
    }
    .fita-2 {
      border: 2px solid transparent;
      background:
        conic-gradient(from 20deg, transparent 0 30%, #163d72 40%, #4f8ad4 46%, #a8ccf5 48%, #4f8ad4 50%, transparent 62% 100%) border-box;
      -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
              mask-composite: exclude;
      width: 130vmax; height: 130vmax;
      border-radius: 55% 45% 40% 60% / 45% 55% 45% 55%;
      transform: translate(-50%, -50%) rotate(120deg);
      animation: girar 64s linear infinite reverse;
      opacity: .4;
    }
    .fita-3 {
      width: 100vmax; height: 100vmax;
      background: radial-gradient(ellipse at center, rgba(29, 78, 143, .16) 0%, transparent 60%);
      transform: translate(-50%, -50%);
      filter: blur(30px);
      animation: pulsar 9s ease-in-out infinite;
    }
    @keyframes girar {
      to { transform: translate(-50%, -50%) rotate(360deg); }
    }
    @keyframes pulsar {
      0%, 100% { opacity: .35; }
      50% { opacity: .6; }
    }
    @media (prefers-reduced-motion: reduce) {
      .fita { animation: none !important; }
    }

    /* Cartão de vidro */
    .glass-card {
      background: rgba(13, 20, 34, .72);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border: 1px solid rgba(125, 180, 245, .14);
      box-shadow: 0 24px 70px rgba(0, 0, 0, .55), inset 0 1px 0 rgba(255, 255, 255, .06);
    }
    .glass-input {
      background: rgba(7, 11, 20, .6) !important;
      border: 1px solid rgba(125, 180, 245, .18) !important;
      color: #e8eef7 !important;
    }
    .glass-input::placeholder { color: #5b6b83; }
    .glass-input:focus {
      border-color: #4f8ad4 !important;
      box-shadow: 0 0 0 3px rgba(79, 138, 212, .22) !important;
    }
  </style>
</head>
<body class="font-display min-h-screen flex items-center justify-center p-4">

  <!-- Fundo animado (curvas luminosas azul AOM) -->
  <div class="aurora" aria-hidden="true">
    <div class="fita fita-3"></div>
    <div class="fita fita-1"></div>
    <div class="fita fita-2"></div>
  </div>

  <div class="w-full max-w-md relative z-10">
    <div class="glass-card rounded-2xl overflow-hidden">

      <div class="relative pt-10 pb-6 flex items-center justify-center">
        <div class="relative z-10 flex flex-col items-center gap-3">
          <div class="bg-white/95 rounded-2xl px-5 py-3 shadow-lg shadow-black/30">
            <img src="img/logo-intranet-aom.png" alt="Intranet AOM" class="h-12 w-auto object-contain">
          </div>
          <div class="text-center">
            <h1 class="text-xl font-bold text-white tracking-tight">Presença AOM</h1>
            <p class="text-[11px] font-medium text-primary-claro uppercase tracking-widest">Sistema de Gestão</p>
          </div>
        </div>
      </div>

      <div class="px-8 pb-8">
        <div class="mb-6">
          <h2 class="text-2xl font-bold text-white">Bem-vindo</h2>
          <p class="text-slate-400 text-sm mt-1">Acesse o portal de gestão de presença</p>
        </div>

        <div id="mensagemLogin" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        <form id="formLogin" class="space-y-5">
          <div class="space-y-2">
            <label for="email" class="text-sm font-semibold text-slate-300 flex items-center gap-2">
              <span class="material-symbols-outlined text-lg text-primary-claro">mail</span>
              Endereço de E-mail
            </label>
            <input
              type="email"
              id="email"
              name="email"
              required
              autofocus
              placeholder="seu.nome@empresa.com.br"
              class="glass-input w-full px-4 py-3 rounded-lg transition-colors outline-none"
            >
          </div>

          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <label for="senha" class="text-sm font-semibold text-slate-300 flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary-claro">lock</span>
                Senha
              </label>
              <a href="recuperar_senha.php" class="text-xs font-medium text-primary-claro hover:underline">Esqueceu a senha?</a>
            </div>
            <div class="relative group">
              <input
                type="password"
                id="senha"
                name="senha"
                required
                placeholder="••••••••"
                class="glass-input w-full px-4 py-3 rounded-lg transition-colors outline-none pr-12"
              >
              <button type="button" id="toggleSenha" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                <span class="material-symbols-outlined text-xl">visibility</span>
              </button>
            </div>
          </div>

          <div class="flex items-center">
            <input type="checkbox" id="remember" class="w-4 h-4 text-primary bg-transparent border-slate-600 rounded focus:ring-primary">
            <label for="remember" class="ml-2 text-sm text-slate-400">Lembrar neste dispositivo</label>
          </div>

          <button type="submit" id="btnLogin" class="w-full bg-primary hover:bg-[#163d72] text-white font-semibold py-3.5 rounded-lg shadow-lg shadow-primary/30 hover:shadow-primary/40 transition-all flex items-center justify-center gap-2 group">
            <span id="btnLoginText">Acessar Sistema</span>
            <span id="btnLoginIcon" class="material-symbols-outlined text-xl group-hover:translate-x-1 transition-transform">arrow_forward</span>
            <svg id="btnLoginSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </button>
        </form>

        <div class="mt-8 pt-6 border-t border-white/10 text-center">
          <p class="text-xs text-slate-500">
            Ambiente Seguro e Monitorado. Em caso de dúvidas, contate o suporte de TI.
          </p>
          <div class="mt-4 flex justify-center gap-4">
            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-500 uppercase tracking-widest">
              <span class="material-symbols-outlined text-xs">verified_user</span>
              SSL Encrypted
            </span>
            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-500 uppercase tracking-widest">
              <span class="material-symbols-outlined text-xs">shield</span>
              Acesso Protegido
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-8 flex justify-center items-center gap-4">
      <div class="flex items-center gap-2 opacity-40 hover:opacity-70 transition-all cursor-default">
        <div class="w-6 h-6 bg-slate-500 rounded-sm flex items-center justify-center">
          <span class="material-symbols-outlined text-white text-xs">apartment</span>
        </div>
        <span class="text-sm font-semibold text-slate-400">AOM - Gestão de Presença</span>
      </div>
    </div>
  </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#toggleSenha').on('click', function() {
  var input = $('#senha');
  var icon = $(this).find('.material-symbols-outlined');
  if (input.attr('type') === 'password') {
    input.attr('type', 'text');
    icon.text('visibility_off');
  } else {
    input.attr('type', 'password');
    icon.text('visibility');
  }
});

$('#formLogin').submit(function(e) {
  e.preventDefault();

  $('#mensagemLogin').addClass('hidden');
  $('#btnLogin').prop('disabled', true);
  $('#btnLoginText').text('Entrando...');
  $('#btnLoginIcon').addClass('hidden');
  $('#btnLoginSpinner').removeClass('hidden');

  var dados = $(this).serialize();

  $.ajax({
    url: 'api/auth/login.php',
    type: 'POST',
    data: dados,
    dataType: 'json',
    success: function(res) {
      if (res.status === 'ok') {
        animarSaidaERedirecionar();
      } else {
        $('#mensagemLogin')
          .removeClass('hidden bg-green-100 text-green-800')
          .addClass('bg-red-500/15 text-red-300 border border-red-500/30')
          .text(res.mensagem);
        $('#formLogin').addClass('shake');
        setTimeout(function() { $('#formLogin').removeClass('shake'); }, 400);
        resetBtn();
      }
    },
    error: function() {
      $('#mensagemLogin')
        .removeClass('hidden bg-green-100 text-green-800')
        .addClass('bg-red-500/15 text-red-300 border border-red-500/30')
        .text('Erro ao tentar logar. Tente novamente.');
      $('#formLogin').addClass('shake');
      setTimeout(function() { $('#formLogin').removeClass('shake'); }, 400);
      resetBtn();
    }
  });
});

function resetBtn() {
  $('#btnLogin').prop('disabled', false);
  $('#btnLoginText').text('Acessar Sistema');
  $('#btnLoginIcon').removeClass('hidden');
  $('#btnLoginSpinner').addClass('hidden');
}

function animarSaidaERedirecionar() {
  // Fallback de segurança: o redirect SEMPRE acontece em <= 800ms,
  // mesmo que a animação CSS falhe ou não seja suportada.
  var jaRedirecionou = false;
  function ir() {
    if (jaRedirecionou) return;
    jaRedirecionou = true;
    window.location.href = 'resumo.php';
  }
  setTimeout(ir, 800);

  try {
    // Campos do form saem alternando esquerda/direita, em sequência
    var itens = document.querySelectorAll('#formLogin > div, #formLogin > button');
    itens.forEach(function (el, i) {
      el.style.animationDelay = (i * 70) + 'ms';
      el.classList.add(i % 2 === 0 ? 'fly-left' : 'fly-right');
    });
    // O card inteiro colapsa logo depois
    var card = document.querySelector('.w-full.max-w-md > div');
    if (card) {
      card.style.animationDelay = '260ms';
      card.classList.add('card-collapse');
    }
  } catch (e) {
    ir(); // se algo der errado, redireciona na hora
  }
}
</script>
</body>
</html>
