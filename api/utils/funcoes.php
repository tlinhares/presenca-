<?php
// api/utils/funcoes.php

function gerarHashAntigo($senha) {
    $salt = 'presenca_aom_salt'; // deve ser exatamente isso
    return hash('sha256', $salt . $senha);
}
