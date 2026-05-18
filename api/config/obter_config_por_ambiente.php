<?php
// api/config/obter_config_por_ambiente.php
header('Content-Type: application/json');

// Desativar a exibição de erros para o usuário final
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

// Incluir arquivos necessários
require_once '../conexao.php';
require_once '../utils/funcoes_helper.php';
session_start();

/**
 * Função para obter configurações baseadas no ambiente atual
 * 
 * @param string $ambiente O ambiente atual (desenvolvimento, homologacao, producao)
 * @return array Configurações específicas do ambiente
 */
function obterConfigPorAmbiente($conn, $ambiente = null) {
    // Se o ambiente não for fornecido, tentar detectar automaticamente
    if ($ambiente === null) {
        // Verificar o hostname ou endereço IP para determinar o ambiente
        $servidor = $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'] ?? '';
        
        if (strpos($servidor, 'localhost') !== false || strpos($servidor, '127.0.0.1') !== false) {
            $ambiente = 'desenvolvimento';
        } elseif (strpos($servidor, 'homolog') !== false || strpos($servidor, 'stage') !== false || strpos($servidor, 'test') !== false) {
            $ambiente = 'homologacao';
        } else {
            $ambiente = 'producao';
        }
    }
    
    // Verificar se a tabela de configurações existe
    $verificar_tabela = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    
    if ($verificar_tabela->num_rows == 0) {
        return ['erro' => 'Tabela de configurações não encontrada'];
    }
    
    // Buscar as configurações marcadas para o ambiente atual
    $sql = "SELECT * FROM configuracoes WHERE ambiente = ? OR ambiente = 'todos'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $ambiente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $configs = [];
    
    while ($row = $resultado->fetch_assoc()) {
        // Aplicamos uma lógica para converter os valores para seus tipos apropriados
        $valor = $row['valor'];
        
        // Converter valores booleanos
        if ($valor === 'true') {
            $valor = true;
        } elseif ($valor === 'false') {
            $valor = false;
        } 
        // Converter valores numéricos
        elseif (is_numeric($valor)) {
            if (strpos($valor, '.') !== false) {
                $valor = (float) $valor;
            } else {
                $valor = (int) $valor;
            }
        }
        // Tentar decodificar JSON se for um array ou objeto
        elseif (substr($valor, 0, 1) === '{' || substr($valor, 0, 1) === '[') {
            $json_valor = json_decode($valor, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $valor = $json_valor;
            }
        }
        
        $configs[$row['chave']] = $valor;
    }
    
    return $configs;
}

// Resposta principal do script
try {
    $ambiente = isset($_GET['ambiente']) ? $_GET['ambiente'] : null;
    $configs = obterConfigPorAmbiente($conn, $ambiente);
    
    // Adicionar informações sobre o ambiente detectado
    if (!isset($_GET['ambiente'])) {
        $servidor = $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'] ?? '';
        $configs['_ambiente_detectado'] = [
            'servidor' => $servidor,
            'ambiente' => $ambiente ?? 'desconhecido'
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $configs
    ]);
} catch (Exception $e) {
    // Registrar o erro no log do sistema
    error_log('Erro ao obter configurações por ambiente: ' . $e->getMessage());
    
    // Retornar erro genérico para o cliente
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Ocorreu um erro ao processar sua solicitação'
    ]);
} 