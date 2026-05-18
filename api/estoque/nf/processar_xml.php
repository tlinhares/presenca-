<?php
/**
 * API - Processar XML de NF-e
 * Suporta diferentes formatos de XML de NF-e brasileira
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once __DIR__ . '/../../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../conexao.php';

try {
    if (!isset($_FILES['xml']) || $_FILES['xml']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro ao receber o arquivo XML');
    }
    
    $xmlContent = file_get_contents($_FILES['xml']['tmp_name']);
    
    if (empty($xmlContent)) {
        throw new Exception('Arquivo XML está vazio');
    }
    
    // Log para debug
    error_log("XML recebido - tamanho: " . strlen($xmlContent) . " bytes");
    
    // Tentar carregar o XML original primeiro
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    
    if (!$xml) {
        // Se falhou, tentar removendo namespaces
        $xmlContent = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $xmlContent = preg_replace('/\s+/', ' ', $xmlContent);
        $xml = simplexml_load_string($xmlContent);
    }
    
    if (!$xml) {
        $errors = libxml_get_errors();
        $errorMsg = !empty($errors) ? $errors[0]->message : 'Estrutura XML inválida';
        libxml_clear_errors();
        throw new Exception('Erro ao processar XML: ' . trim($errorMsg));
    }
    
    // Registrar namespaces se existirem
    $namespaces = $xml->getNamespaces(true);
    
    // Tentar diferentes caminhos para encontrar os dados da NF
    $infNFe = null;
    $paths = [
        $xml->NFe->infNFe ?? null,
        $xml->infNFe ?? null,
        $xml->nfeProc->NFe->infNFe ?? null,
        $xml->children('http://www.portalfiscal.inf.br/nfe')->NFe->infNFe ?? null,
    ];
    
    foreach ($paths as $path) {
        if ($path !== null && isset($path->ide)) {
            $infNFe = $path;
            break;
        }
    }
    
    // Se ainda não encontrou, tentar com namespace
    if (!$infNFe && !empty($namespaces)) {
        foreach ($namespaces as $prefix => $ns) {
            $xml->registerXPathNamespace('nfe', $ns);
            $result = $xml->xpath('//nfe:infNFe');
            if (!empty($result)) {
                $infNFe = $result[0];
                break;
            }
        }
    }
    
    // Última tentativa: buscar diretamente no XML
    if (!$infNFe) {
        // Tentar extrair por regex se tudo falhar
        if (preg_match('/<infNFe[^>]*>(.*?)<\/infNFe>/s', $xmlContent, $matches)) {
            $infNFeContent = '<infNFe>' . $matches[1] . '</infNFe>';
            $infNFe = simplexml_load_string($infNFeContent);
        }
    }
    
    if (!$infNFe) {
        error_log("Estrutura XML não reconhecida. Primeiros 500 chars: " . substr($xmlContent, 0, 500));
        throw new Exception('Estrutura do XML não reconhecida. Certifique-se de que é uma NF-e válida.');
    }
    
    $ide = $infNFe->ide ?? null;
    $emit = $infNFe->emit ?? null;
    $total = $infNFe->total->ICMSTot ?? $infNFe->total ?? null;
    
    if (!$ide) {
        throw new Exception('Dados de identificação da NF (ide) não encontrados');
    }
    
    // Dados da NF
    $dataEmissao = (string)($ide->dhEmi ?? $ide->dEmi ?? '');
    if (!empty($dataEmissao)) {
        $dataEmissao = date('d/m/Y', strtotime($dataEmissao));
    } else {
        $dataEmissao = date('d/m/Y');
    }
    
    // Extrair chave de acesso (pode vir com prefixo "NFe")
    $chaveAcessoRaw = (string)($infNFe['Id'] ?? '');
    // Remover prefixo "NFe" se existir
    $chaveAcesso = preg_replace('/^NFe/i', '', $chaveAcessoRaw);
    // Remover qualquer caractere não numérico e limitar a 44 caracteres
    $chaveAcesso = preg_replace('/[^0-9]/', '', $chaveAcesso);
    if (strlen($chaveAcesso) > 44) {
        $chaveAcesso = substr($chaveAcesso, 0, 44);
    }
    
    $nf = [
        'numero' => (string)($ide->nNF ?? '0'),
        'serie' => (string)($ide->serie ?? '1'),
        'data_emissao' => $dataEmissao,
        'chave_acesso' => $chaveAcesso,
        'valor_total' => (float)($total->vNF ?? $total->vProd ?? 0)
    ];
    
    // Dados do fornecedor
    $fornecedor = [
        'nome' => (string)($emit->xNome ?? $emit->xFant ?? 'Fornecedor não identificado'),
        'cnpj' => (string)($emit->CNPJ ?? $emit->CPF ?? ''),
        'ie' => (string)($emit->IE ?? '')
    ];
    
    // Produtos
    $produtos = [];
    $dets = $infNFe->det ?? [];
    
    // Se det não é um array, converter
    if ($dets && !is_array($dets) && !($dets instanceof \Traversable)) {
        $dets = [$dets];
    }
    
    foreach ($dets as $det) {
        $prod = $det->prod ?? $det;
        
        if (!$prod) continue;
        
        $codigo = (string)($prod->cProd ?? '');
        $nome = (string)($prod->xProd ?? 'Produto sem nome');
        $ncm = (string)($prod->NCM ?? '');
        $unidade = strtoupper((string)($prod->uCom ?? $prod->uTrib ?? 'UN'));
        $quantidade = (float)($prod->qCom ?? $prod->qTrib ?? 1);
        $valorUnitario = (float)($prod->vUnCom ?? $prod->vUnTrib ?? 0);
        $valorTotal = (float)($prod->vProd ?? 0);
        
        // Verificar se produto já existe no sistema
        $existente = false;
        if (!empty($codigo) && isset($conn)) {
            $stmt = $conn->prepare("SELECT id FROM estoque_produtos WHERE codigo = ? OR codigo_barras = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $codigo, $codigo);
                $stmt->execute();
                $existente = $stmt->get_result()->num_rows > 0;
            }
        }
        
        $produtos[] = [
            'codigo' => $codigo,
            'nome' => $nome,
            'ncm' => $ncm,
            'unidade' => $unidade,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal,
            'existente' => $existente
        ];
    }
    
    if (empty($produtos)) {
        throw new Exception('Nenhum produto encontrado no XML');
    }
    
    echo json_encode([
        'status' => 'ok',
        'nf' => $nf,
        'fornecedor' => $fornecedor,
        'produtos' => $produtos,
        'total_produtos' => count($produtos)
    ]);

} catch (Exception $e) {
    error_log("Erro em nf/processar_xml.php: " . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}

if (isset($conn)) $conn->close();
