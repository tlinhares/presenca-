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
            "primary": "#1a227f",
            "background-light": "#f6f6f8",
            "background-dark": "#121320",
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
  </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="bg-white dark:bg-slate-900 shadow-xl rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800">

      <div class="relative h-48 bg-primary/10 flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/20 to-transparent"></div>
        <div class="relative z-10 flex flex-col items-center gap-3">
          <img src="img/logo-intranet-aom.png" alt="Intranet AOM" class="h-16 w-auto object-contain drop-shadow-lg">
          <div class="text-center">
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Presença AOM</h1>
            <p class="text-xs font-medium text-primary uppercase tracking-widest">Sistema de Gestão</p>
          </div>
        </div>
        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-primary/5 rounded-full"></div>
      </div>

      <div class="p-8">
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Bem-vindo</h2>
          <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Acesse o portal de gestão de presença</p>
        </div>

        <div id="mensagemLogin" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        <form id="formLogin" class="space-y-5">
          <div class="space-y-2">
            <label for="email" class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
              <span class="material-symbols-outlined text-lg">mail</span>
              Endereço de E-mail
            </label>
            <input
              type="email"
              id="email"
              name="email"
              required
              autofocus
              placeholder="seu.nome@empresa.com.br"
              class="w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors outline-none"
            >
          </div>

          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <label for="senha" class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">lock</span>
                Senha
              </label>
              <a href="recuperar_senha.php" class="text-xs font-medium text-primary hover:underline">Esqueceu a senha?</a>
            </div>
            <div class="relative group">
              <input
                type="password"
                id="senha"
                name="senha"
                required
                placeholder="••••••••"
                class="w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors outline-none pr-12"
              >
              <button type="button" id="toggleSenha" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                <span class="material-symbols-outlined text-xl">visibility</span>
              </button>
            </div>
          </div>

          <div class="flex items-center">
            <input type="checkbox" id="remember" class="w-4 h-4 text-primary border-slate-300 rounded focus:ring-primary">
            <label for="remember" class="ml-2 text-sm text-slate-600 dark:text-slate-400">Lembrar neste dispositivo</label>
          </div>

          <button type="submit" id="btnLogin" class="w-full bg-primary hover:bg-primary/90 text-white font-semibold py-3.5 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 group">
            <span id="btnLoginText">Acessar Sistema</span>
            <span id="btnLoginIcon" class="material-symbols-outlined text-xl group-hover:translate-x-1 transition-transform">arrow_forward</span>
            <svg id="btnLoginSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </button>
        </form>

        <div class="mt-10 pt-6 border-t border-slate-100 dark:border-slate-800 text-center">
          <p class="text-xs text-slate-400 dark:text-slate-500">
            Ambiente Seguro e Monitorado. Em caso de dúvidas, contate o suporte de TI.
          </p>
          <div class="mt-4 flex justify-center gap-4">
            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-400 uppercase tracking-widest">
              <span class="material-symbols-outlined text-xs">verified_user</span>
              SSL Encrypted
            </span>
            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-400 uppercase tracking-widest">
              <span class="material-symbols-outlined text-xs">shield</span>
              Acesso Protegido
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-8 flex justify-center items-center gap-4">
      <div class="flex items-center gap-2 opacity-50 grayscale hover:grayscale-0 transition-all cursor-default">
        <div class="w-6 h-6 bg-slate-400 rounded-sm flex items-center justify-center">
          <span class="material-symbols-outlined text-white text-xs">apartment</span>
        </div>
        <span class="text-sm font-semibold text-slate-500">AOM - Gestão de Presença</span>
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
        window.location.href = 'resumo.php';
      } else {
        $('#mensagemLogin')
          .removeClass('hidden bg-green-100 text-green-800')
          .addClass('bg-red-50 text-red-700 border border-red-200')
          .text(res.mensagem);
        $('#formLogin').addClass('shake');
        setTimeout(function() { $('#formLogin').removeClass('shake'); }, 400);
        resetBtn();
      }
    },
    error: function() {
      $('#mensagemLogin')
        .removeClass('hidden bg-green-100 text-green-800')
        .addClass('bg-red-50 text-red-700 border border-red-200')
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
</script>
</body>
</html>
