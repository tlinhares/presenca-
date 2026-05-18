$(document).ready(function() {
    $('#formLogin').submit(function(e) {
        e.preventDefault();

        var email = $('#email').val();
        var senha = $('#senha').val();

        $.ajax({
            url: 'api/auth/login.php',
            method: 'POST',
            data: {
                email: email,
                senha: senha
            },
            dataType: 'json',
            success: function(resposta) {
                if (resposta.status === 'ok') {
                    if (resposta.categoria === 'admin') {
                        window.location.href = 'painel/index.php';
                    } else {
                        window.location.href = 'reservas/almoco.php';
                    }
                } else {
                    $('#mensagem').text(resposta.mensagem);
                }
            },
            error: function() {
                $('#mensagem').text('Erro ao tentar login.');
            }
        });
    });
});
