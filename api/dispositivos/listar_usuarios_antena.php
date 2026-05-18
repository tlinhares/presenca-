<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('dispositivos_faciais');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../core/services/DispositivoFacialService.php';

try {
    if (!isset($_GET['id_dispositivo']) || empty($_GET['id_dispositivo'])) {
        throw new Exception('ID do dispositivo não fornecido');
    }
    
    $id_dispositivo = intval($_GET['id_dispositivo']);
    
    $stmt = $conn->prepare("SELECT id, nome, ip, porta, usuario, senha, modelo FROM dispositivos_faciais WHERE id = ?");
    $stmt->bind_param("i", $id_dispositivo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Dispositivo não encontrado');
    }
    
    $dispositivo = $result->fetch_assoc();
    $stmt->close();
    
    $ip = $dispositivo['ip'];
    $porta = $dispositivo['porta'];
    $usuario_disp = $dispositivo['usuario'];
    $senha_disp = $dispositivo['senha'];
    
    $usuarios = [];
    $start = 0;
    $count = 100;
    $total_dispositivo = 0;
    $tentativas = 0;
    $max_paginas = 50;
    
    do {
        $tentativas++;
        
        $url = DispositivoFacialService::construirUrl(
            $ip, $porta,
            "/cgi-bin/AccessUser.cgi?action=getAll&start={$start}&count={$count}"
        );
        
        $resultado = DispositivoFacialService::executarRequisicao(
            $url, $usuario_disp, $senha_disp,
            ['timeout' => 15]
        );
        
        if ($resultado['codigo'] == 401) {
            throw new Exception('Falha na autenticação com o dispositivo. Verifique usuário e senha.');
        }
        
        if ($resultado['codigo'] != 200) {
            throw new Exception(
                'Erro ao conectar ao dispositivo (HTTP ' . $resultado['codigo'] . '). ' .
                ($resultado['erro'] ? 'Detalhe: ' . $resultado['erro'] : 'Verifique se o dispositivo está online.')
            );
        }
        
        $resposta = $resultado['resposta'];
        
        if (empty($resposta)) {
            throw new Exception('Dispositivo retornou resposta vazia. Verifique a conectividade.');
        }
        
        $dados = json_decode($resposta, true);
        
        if ($dados !== null) {
            $userList = $dados['UserList'] ?? [];
            $total_dispositivo = $dados['Total'] ?? $total_dispositivo;
            
            foreach ($userList as $user) {
                $usuarios[] = [
                    'user_id' => $user['UserID'] ?? '',
                    'user_name' => $user['UserName'] ?? '',
                    'user_type' => $user['UserType'] ?? 0,
                    'authority' => $user['Authority'] ?? 0,
                    'doors' => $user['Doors'] ?? [],
                    'valid_from' => $user['ValidFrom'] ?? '',
                    'valid_to' => $user['ValidTo'] ?? '',
                ];
            }
            
            $start += $count;
            
            if (count($userList) < $count) {
                break;
            }
        } else {
            $parsed = parseIntelbrasResponse($resposta);
            
            if (!empty($parsed)) {
                $total_dispositivo = $parsed['total'] ?? count($parsed['users']);
                foreach ($parsed['users'] as $user) {
                    $usuarios[] = $user;
                }
            }
            break;
        }
        
    } while ($tentativas < $max_paginas && $start < $total_dispositivo);
    
    echo json_encode([
        'status' => 'sucesso',
        'dispositivo' => [
            'id' => $dispositivo['id'],
            'nome' => $dispositivo['nome'],
            'ip' => $dispositivo['ip'],
            'modelo' => $dispositivo['modelo'] ?? ''
        ],
        'usuarios' => $usuarios,
        'total' => count($usuarios),
        'total_dispositivo' => $total_dispositivo
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Faz parse da resposta no formato key=value do Intelbras/Dahua
 * Ex: UserList[0].UserID=123\nUserList[0].UserName=Fulano\n...
 */
function parseIntelbrasResponse($resposta) {
    $lines = explode("\n", trim($resposta));
    $users = [];
    $total = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (preg_match('/^Total=(\d+)/', $line, $m)) {
            $total = intval($m[1]);
            continue;
        }
        
        if (preg_match('/^UserList\[(\d+)\]\.(\w+)=(.*)$/', $line, $m)) {
            $idx = intval($m[1]);
            $key = $m[2];
            $val = $m[3];
            
            if (!isset($users[$idx])) {
                $users[$idx] = [
                    'user_id' => '',
                    'user_name' => '',
                    'user_type' => 0,
                    'authority' => 0,
                    'doors' => [],
                    'valid_from' => '',
                    'valid_to' => '',
                ];
            }
            
            switch ($key) {
                case 'UserID': $users[$idx]['user_id'] = $val; break;
                case 'UserName': $users[$idx]['user_name'] = $val; break;
                case 'UserType': $users[$idx]['user_type'] = intval($val); break;
                case 'Authority': $users[$idx]['authority'] = intval($val); break;
                case 'ValidFrom': $users[$idx]['valid_from'] = $val; break;
                case 'ValidTo': $users[$idx]['valid_to'] = $val; break;
                case 'CardNo': $users[$idx]['card_no'] = $val; break;
            }
        }
    }
    
    return ['users' => array_values($users), 'total' => $total ?: count($users)];
}
