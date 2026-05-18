$(document).ready(function () {
    $('#formCadastro').submit(function (e) {
        e.preventDefault();

        const nome = $('#nome').val();
        const email = $('#email').val();
        const senha = $('#senha').val();
        const categoria = $('#categoria').val();

        $.ajax({
            url: '../api/usuarios/cadastrar.php',
            type: 'POST',
            dataType: 'json',
            data: {
                nome: nome,
                email: email,
                senha: senha,
                categoria: categoria
            },
            success: function (resposta) {
                if (resposta.status === 'ok') {
                    $('#mensagem').html('<div class="alert alert-success">' + resposta.mensagem + '</div>');
                    $('#formCadastro')[0].reset();
                } else {
                    $('#mensagem').html('<div class="alert alert-danger">' + resposta.mensagem + '</div>');
                }
            },
            error: function () {
                $('#mensagem').html('<div class="alert alert-danger">Erro ao cadastrar usuário.</div>');
            }
        });
    });
});
