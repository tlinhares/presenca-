<?php
/**
 * Configuração do domínio do sistema
 * Este arquivo define o domínio base para as APIs
 */

// Configuração do domínio
// Altere aqui para o domínio correto do seu servidor
$DOMINIO_SISTEMA = 'presenca.aom.org.br';

// Função para obter a URL completa da API
function obterUrlAPI($endpoint) {
    global $DOMINIO_SISTEMA;
    
    // Se estiver em desenvolvimento local, usar localhost
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        return 'http://' . $_SERVER['HTTP_HOST'] . '/presenca/' . $endpoint;
    }
    
    // Em produção, usar o domínio configurado (sem subpasta /presenca)
    return 'https://' . $DOMINIO_SISTEMA . '/' . $endpoint;
}

// URLs das APIs
$URL_API_LEITURA_FACIAL = obterUrlAPI('api/culto/receber_leitura_facial.php');
$URL_API_SINCRONIZACAO = obterUrlAPI('api/culto/executar_sync.php');

// Função para obter o domínio base
function obterDominioBase() {
    global $DOMINIO_SISTEMA;
    
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        return 'http://' . $_SERVER['HTTP_HOST'] . '/presenca';
    }
    
    return 'https://' . $DOMINIO_SISTEMA;
}
?>
