# Guia Prático: Cadastro de Usuário e Foto no Dispositivo Facial

**Versão:** 1.0  
**Data:** 09/02/2026  
**Dispositivos:** Intelbras/Dahua compatíveis

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Pré-requisitos](#pré-requisitos)
3. [Cadastro de Usuário](#cadastro-de-usuário)
4. [Cadastro de Foto Facial](#cadastro-de-foto-facial)
5. [Cadastro Completo (Usuário + Foto)](#cadastro-completo-usuário--foto)
6. [Exemplos Práticos](#exemplos-práticos)
7. [Tratamento de Erros](#tratamento-de-erros)
8. [Referência de API](#referência-de-api)

---

## 🎯 Visão Geral

Este guia mostra como cadastrar usuários e fotos faciais em dispositivos Intelbras/Dahua usando requisições HTTP com autenticação Digest.

**Fluxo básico:**
1. Cadastrar usuário no dispositivo (obrigatório primeiro)
2. Cadastrar foto facial do usuário (opcional, mas necessário para reconhecimento)

---

## ✅ Pré-requisitos

### Informações Necessárias

- **IP do Dispositivo**: Ex: `10.144.129.69`
- **Porta**: Ex: `80` (padrão HTTP) ou `443` (HTTPS)
- **Usuário do Dispositivo**: Ex: `admin`
- **Senha do Dispositivo**: Ex: `Arcs2901`
- **ID do Usuário**: ID único (geralmente o mesmo ID do banco de dados)
- **Nome do Usuário**: Nome completo
- **Foto em Base64**: Foto codificada em base64 (sem prefixo `data:image`)

### Requisitos Técnicos

- PHP com extensão `curl` habilitada
- Acesso de rede ao dispositivo facial
- Foto em formato JPEG/PNG convertida para base64

---

## 👤 Cadastro de Usuário

### Endpoint

```
POST http://{IP}:{PORTA}/cgi-bin/AccessUser.cgi?action=insertMulti
```

### Estrutura de Dados (JSON)

```json
{
    "UserList": [
        {
            "UserID": "123",
            "UserName": "João Silva",
            "UserType": 0,
            "Authority": 2,
            "Password": "123456",
            "Doors": [0],
            "TimeSections": [255],
            "ValidFrom": "2026-02-09 00:00:00",
            "ValidTo": "2027-02-09 23:59:59"
        }
    ]
}
```

### Campos Obrigatórios

| Campo | Tipo | Descrição | Exemplo |
|-------|------|-----------|---------|
| `UserID` | string | ID único do usuário (mesmo do banco) | `"123"` |
| `UserName` | string | Nome completo do usuário | `"João Silva"` |
| `UserType` | int | Tipo de usuário (0 = normal) | `0` |
| `Authority` | int | Autoridade (2 = usuário normal) | `2` |
| `Password` | string | Senha padrão do usuário | `"123456"` |
| `Doors` | array | Portas permitidas ([0] = todas) | `[0]` |
| `TimeSections` | array | Seções de tempo ([255] = sempre) | `[255]` |
| `ValidFrom` | string | Data/hora início validade | `"2026-02-09 00:00:00"` |
| `ValidTo` | string | Data/hora fim validade | `"2027-02-09 23:59:59"` |

### Código PHP Completo

```php
<?php
/**
 * Cadastra um usuário no dispositivo facial
 * 
 * @param string $ip IP do dispositivo
 * @param int $porta Porta do dispositivo
 * @param string $usuario_disp Usuário do dispositivo
 * @param string $senha_disp Senha do dispositivo
 * @param int|string $user_id ID do usuário
 * @param string $user_name Nome do usuário
 * @return array ['sucesso' => bool, 'mensagem' => string]
 */
function cadastrarUsuarioDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name) {
    // Preparar dados do usuário
    $hoje = date('Y-m-d H:i:s');
    $valido_ate = date('Y-m-d H:i:s', strtotime('+1 year'));
    
    $dados_usuario = [
        "UserList" => [
            [
                "UserID" => (string)$user_id,
                "UserName" => $user_name,
                "UserType" => 0,              // 0 = usuário normal
                "Authority" => 2,            // 2 = usuário normal (não admin)
                "Password" => "123456",       // Senha padrão
                "Doors" => [0],               // [0] = todas as portas
                "TimeSections" => [255],      // [255] = sempre permitido
                "ValidFrom" => $hoje,
                "ValidTo" => $valido_ate
            ]
        ]
    ];
    
    // Converter para JSON
    $json_dados = json_encode($dados_usuario, JSON_UNESCAPED_UNICODE);
    
    // Construir URL
    $url = "http://{$ip}:{$porta}/cgi-bin/AccessUser.cgi?action=insertMulti";
    
    // Configurar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_dados);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);  // Autenticação Digest
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_dados)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Executar requisição
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_erro = curl_error($ch);
    curl_close($ch);
    
    // Verificar resultado
    if ($http_code == 200 && trim($resposta) === 'OK') {
        return [
            'sucesso' => true,
            'mensagem' => 'Usuário cadastrado com sucesso'
        ];
    } else {
        return [
            'sucesso' => false,
            'mensagem' => "Erro ao cadastrar usuário: " . ($curl_erro ?: "HTTP $http_code - $resposta")
        ];
    }
}

// Exemplo de uso
$resultado = cadastrarUsuarioDispositivo(
    $ip = '10.144.129.69',
    $porta = 80,
    $usuario_disp = 'admin',
    $senha_disp = 'Arcs2901',
    $user_id = 123,
    $user_name = 'João Silva'
);

if ($resultado['sucesso']) {
    echo "✅ " . $resultado['mensagem'] . "\n";
} else {
    echo "❌ " . $resultado['mensagem'] . "\n";
}
?>
```

### Resposta de Sucesso

**HTTP Status:** `200 OK`  
**Corpo:** `OK`

### Resposta de Erro

**HTTP Status:** `400`, `401`, `500`, etc.  
**Corpo:** Mensagem de erro do dispositivo

---

## 📸 Cadastro de Foto Facial

### Endpoint

```
POST http://{IP}:{PORTA}/cgi-bin/AccessFace.cgi?action=updateMulti
```

### Estrutura de Dados (JSON)

```json
{
    "FaceList": [
        {
            "UserID": "123",
            "PhotoData": ["base64_encoded_image_data"]
        }
    ]
}
```

### Campos Obrigatórios

| Campo | Tipo | Descrição | Exemplo |
|-------|------|-----------|---------|
| `UserID` | string | ID do usuário (deve existir no dispositivo) | `"123"` |
| `PhotoData` | array | Array com string base64 da foto (sem prefixo) | `["iVBORw0KGgoAAAANS..."]` |

### ⚠️ Importante sobre a Foto

- **Formato Base64**: A foto deve estar em base64 **SEM** o prefixo `data:image/jpeg;base64,`
- **Tamanho**: Recomendado máximo 300KB após compressão
- **Formato**: JPEG ou PNG
- **Dimensões**: Recomendado máximo 800x600 pixels

### Código PHP Completo

```php
<?php
/**
 * Cadastra foto facial de um usuário no dispositivo
 * 
 * @param string $ip IP do dispositivo
 * @param int $porta Porta do dispositivo
 * @param string $usuario_disp Usuário do dispositivo
 * @param string $senha_disp Senha do dispositivo
 * @param int|string $user_id ID do usuário
 * @param string $foto_base64 Foto em base64 (sem prefixo data:image)
 * @return array ['sucesso' => bool, 'mensagem' => string]
 */
function cadastrarFotoDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64) {
    // Remover prefixo data:image se existir
    if (strpos($foto_base64, 'data:image/') === 0) {
        $pos = strpos($foto_base64, ',');
        if ($pos !== false) {
            $foto_base64 = substr($foto_base64, $pos + 1);
        }
    }
    
    // Preparar dados da foto
    $dados_foto = [
        "FaceList" => [
            [
                "UserID" => (string)$user_id,
                "PhotoData" => [$foto_base64]  // Array com uma string base64
            ]
        ]
    ];
    
    // Converter para JSON
    $json_dados = json_encode($dados_foto, JSON_UNESCAPED_UNICODE);
    
    // Construir URL
    $url = "http://{$ip}:{$porta}/cgi-bin/AccessFace.cgi?action=updateMulti";
    
    // Configurar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_dados);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);  // Autenticação Digest
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_dados)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);  // Timeout maior para upload de imagem
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Executar requisição
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_erro = curl_error($ch);
    curl_close($ch);
    
    // Verificar resultado
    if ($http_code == 200 && trim($resposta) === 'OK') {
        return [
            'sucesso' => true,
            'mensagem' => 'Foto cadastrada com sucesso'
        ];
    } else {
        return [
            'sucesso' => false,
            'mensagem' => "Erro ao cadastrar foto: " . ($curl_erro ?: "HTTP $http_code - $resposta")
        ];
    }
}

// Exemplo de uso
$foto_base64 = "iVBORw0KGgoAAAANSUhEUgAA..."; // Base64 sem prefixo

$resultado = cadastrarFotoDispositivo(
    $ip = '10.144.129.69',
    $porta = 80,
    $usuario_disp = 'admin',
    $senha_disp = 'Arcs2901',
    $user_id = 123,
    $foto_base64 = $foto_base64
);

if ($resultado['sucesso']) {
    echo "✅ " . $resultado['mensagem'] . "\n";
} else {
    echo "❌ " . $resultado['mensagem'] . "\n";
}
?>
```

### Resposta de Sucesso

**HTTP Status:** `200 OK`  
**Corpo:** `OK`

### Resposta de Erro

**HTTP Status:** `400`, `401`, `500`, etc.  
**Corpo:** Mensagem de erro do dispositivo

---

## 🔄 Cadastro Completo (Usuário + Foto)

### Código PHP Completo

```php
<?php
/**
 * Cadastra usuário completo (usuário + foto) no dispositivo facial
 * 
 * @param string $ip IP do dispositivo
 * @param int $porta Porta do dispositivo
 * @param string $usuario_disp Usuário do dispositivo
 * @param string $senha_disp Senha do dispositivo
 * @param int|string $user_id ID do usuário
 * @param string $user_name Nome do usuário
 * @param string|null $foto_base64 Foto em base64 (opcional)
 * @return array ['sucesso' => bool, 'mensagem' => string, 'detalhes' => array]
 */
function cadastrarUsuarioCompleto($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64 = null) {
    $detalhes = [];
    
    // 1. Cadastrar usuário primeiro (obrigatório)
    $resultado_usuario = cadastrarUsuarioDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name);
    $detalhes['usuario'] = $resultado_usuario;
    
    if (!$resultado_usuario['sucesso']) {
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao cadastrar usuário: ' . $resultado_usuario['mensagem'],
            'detalhes' => $detalhes
        ];
    }
    
    // 2. Cadastrar foto (se fornecida)
    if (!empty($foto_base64)) {
        $resultado_foto = cadastrarFotoDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64);
        $detalhes['foto'] = $resultado_foto;
        
        if ($resultado_foto['sucesso']) {
            return [
                'sucesso' => true,
                'mensagem' => 'Usuário e foto cadastrados com sucesso',
                'detalhes' => $detalhes
            ];
        } else {
            return [
                'sucesso' => true,  // Parcial: usuário OK, foto falhou
                'mensagem' => 'Usuário cadastrado, mas erro na foto: ' . $resultado_foto['mensagem'],
                'detalhes' => $detalhes
            ];
        }
    }
    
    return [
        'sucesso' => true,
        'mensagem' => 'Usuário cadastrado sem foto',
        'detalhes' => $detalhes
    ];
}

// Exemplo de uso completo
$foto_base64 = "iVBORw0KGgoAAAANSUhEUgAA..."; // Base64 sem prefixo

$resultado = cadastrarUsuarioCompleto(
    $ip = '10.144.129.69',
    $porta = 80,
    $usuario_disp = 'admin',
    $senha_disp = 'Arcs2901',
    $user_id = 123,
    $user_name = 'João Silva',
    $foto_base64 = $foto_base64
);

if ($resultado['sucesso']) {
    echo "✅ " . $resultado['mensagem'] . "\n";
    print_r($resultado['detalhes']);
} else {
    echo "❌ " . $resultado['mensagem'] . "\n";
    print_r($resultado['detalhes']);
}
?>
```

---

## 💡 Exemplos Práticos

### Exemplo 1: Cadastro Simples de Usuário

```php
<?php
// Dados do dispositivo
$ip = '10.144.129.69';
$porta = 80;
$usuario_disp = 'admin';
$senha_disp = 'Arcs2901';

// Dados do usuário
$user_id = 123;
$user_name = 'João Silva';

// Cadastrar usuário
$resultado = cadastrarUsuarioDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name);

if ($resultado['sucesso']) {
    echo "Usuário cadastrado!\n";
} else {
    echo "Erro: " . $resultado['mensagem'] . "\n";
}
?>
```

### Exemplo 2: Cadastro com Foto a partir de Arquivo

```php
<?php
// Ler arquivo de imagem e converter para base64
$caminho_foto = '/caminho/para/foto.jpg';
$foto_binaria = file_get_contents($caminho_foto);
$foto_base64 = base64_encode($foto_binaria);

// Dados do dispositivo
$ip = '10.144.129.69';
$porta = 80;
$usuario_disp = 'admin';
$senha_disp = 'Arcs2901';

// Dados do usuário
$user_id = 123;
$user_name = 'João Silva';

// Cadastrar usuário completo
$resultado = cadastrarUsuarioCompleto(
    $ip, $porta, $usuario_disp, $senha_disp,
    $user_id, $user_name, $foto_base64
);

if ($resultado['sucesso']) {
    echo "Usuário e foto cadastrados!\n";
} else {
    echo "Erro: " . $resultado['mensagem'] . "\n";
}
?>
```

### Exemplo 3: Cadastro com Foto do Banco de Dados

```php
<?php
require_once 'conexao.php';

// Buscar usuário do banco
$user_id = 123;
$stmt = $conn->prepare("SELECT id, nome, foto_base64 FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario || empty($usuario['foto_base64'])) {
    die("Usuário não encontrado ou sem foto\n");
}

// Dados do dispositivo
$ip = '10.144.129.69';
$porta = 80;
$usuario_disp = 'admin';
$senha_disp = 'Arcs2901';

// Cadastrar no dispositivo
$resultado = cadastrarUsuarioCompleto(
    $ip, $porta, $usuario_disp, $senha_disp,
    $usuario['id'], $usuario['nome'], $usuario['foto_base64']
);

if ($resultado['sucesso']) {
    echo "Usuário '{$usuario['nome']}' cadastrado no dispositivo!\n";
} else {
    echo "Erro: " . $resultado['mensagem'] . "\n";
}
?>
```

### Exemplo 4: Cadastro em Múltiplos Dispositivos

```php
<?php
// Lista de dispositivos
$dispositivos = [
    ['ip' => '10.144.129.69', 'porta' => 80, 'usuario' => 'admin', 'senha' => 'Arcs2901'],
    ['ip' => '10.144.129.70', 'porta' => 80, 'usuario' => 'admin', 'senha' => 'Arcs2901'],
];

// Dados do usuário
$user_id = 123;
$user_name = 'João Silva';
$foto_base64 = "iVBORw0KGgoAAAANSUhEUgAA...";

// Cadastrar em cada dispositivo
$resultados = [];
foreach ($dispositivos as $dispositivo) {
    $resultado = cadastrarUsuarioCompleto(
        $dispositivo['ip'],
        $dispositivo['porta'],
        $dispositivo['usuario'],
        $dispositivo['senha'],
        $user_id,
        $user_name,
        $foto_base64
    );
    
    $resultados[] = [
        'dispositivo' => $dispositivo['ip'],
        'resultado' => $resultado
    ];
}

// Exibir resultados
foreach ($resultados as $item) {
    $status = $item['resultado']['sucesso'] ? '✅' : '❌';
    echo "{$status} Dispositivo {$item['dispositivo']}: {$item['resultado']['mensagem']}\n";
}
?>
```

### Exemplo 5: Usando o DispositivoFacialService (Recomendado)

```php
<?php
require_once __DIR__ . '/core/services/DispositivoFacialService.php';

// Dados do dispositivo
$ip = '10.144.129.69';
$porta = 80;
$usuario_disp = 'admin';
$senha_disp = 'Arcs2901';

// Dados do usuário
$user_id = 123;
$user_name = 'João Silva';
$foto_base64 = "iVBORw0KGgoAAAANSUhEUgAA..."; // Base64 sem prefixo

// Usar o serviço (recomendado - já tem tratamento de erros e logs)
$resultado = DispositivoFacialService::sincronizarUsuarioIntelbras(
    $ip,
    $porta,
    $usuario_disp,
    $senha_disp,
    $user_id,
    $user_name,
    $foto_base64,
    [], // Opções adicionais (vazio = padrão)
    function($msg) { echo "[LOG] $msg\n"; } // Callback de log
);

if ($resultado['sucesso']) {
    echo "✅ " . $resultado['mensagem'] . "\n";
    print_r($resultado['detalhes']);
} else {
    echo "❌ " . $resultado['mensagem'] . "\n";
}
?>
```

---

## ⚠️ Tratamento de Erros

### Códigos HTTP Comuns

| Código | Significado | Solução |
|--------|-------------|---------|
| `200` | Sucesso | Operação concluída com sucesso |
| `400` | Requisição inválida | Verificar formato JSON e dados enviados |
| `401` | Não autorizado | Verificar credenciais (usuário/senha) |
| `404` | Não encontrado | Verificar URL e endpoint |
| `500` | Erro interno | Problema no dispositivo, tentar novamente |

### Exemplo de Tratamento de Erros

```php
<?php
function cadastrarComTratamentoErros($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64) {
    // Tentar cadastrar usuário
    $resultado = cadastrarUsuarioCompleto($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name, $foto_base64);
    
    if ($resultado['sucesso']) {
        return $resultado;
    }
    
    // Tratar erros específicos
    $mensagem = $resultado['mensagem'];
    
    // Erro de autenticação
    if (strpos($mensagem, '401') !== false || strpos($mensagem, 'não autorizado') !== false) {
        return [
            'sucesso' => false,
            'mensagem' => 'Credenciais inválidas. Verifique usuário e senha do dispositivo.',
            'codigo_erro' => 'AUTH_ERROR'
        ];
    }
    
    // Erro de conexão
    if (strpos($mensagem, 'timeout') !== false || strpos($mensagem, 'Connection') !== false) {
        return [
            'sucesso' => false,
            'mensagem' => 'Erro de conexão com o dispositivo. Verifique se está online.',
            'codigo_erro' => 'CONNECTION_ERROR'
        ];
    }
    
    // Erro genérico
    return [
        'sucesso' => false,
        'mensagem' => $mensagem,
        'codigo_erro' => 'UNKNOWN_ERROR'
    ];
}
?>
```

---

## 📚 Referência de API

### Endpoints Disponíveis

#### 1. Inserir Usuário
```
POST /cgi-bin/AccessUser.cgi?action=insertMulti
```

#### 2. Atualizar Usuário
```
POST /cgi-bin/AccessUser.cgi?action=updateMulti
```

#### 3. Remover Usuário
```
GET /cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]={user_id}
```

#### 4. Listar Usuário
```
GET /cgi-bin/AccessUser.cgi?action=list&UserIDList[0]={user_id}
```

#### 5. Inserir Foto
```
POST /cgi-bin/AccessFace.cgi?action=insertMulti
```

#### 6. Atualizar Foto
```
POST /cgi-bin/AccessFace.cgi?action=updateMulti
```

#### 7. Remover Foto
```
GET /cgi-bin/AccessFace.cgi?action=removeMulti&UserIDList[0]={user_id}
```

#### 8. Listar Foto
```
GET /cgi-bin/AccessFace.cgi?action=list&UserIDList[0]={user_id}
```

### Autenticação

**Tipo:** HTTP Digest Authentication  
**Formato:** `usuario:senha`  
**Exemplo:** `admin:Arcs2901`

### Headers Necessários

```
Content-Type: application/json
Content-Length: {tamanho_do_json}
```

---

## 🔍 Verificações Úteis

### Verificar se Usuário Existe

```php
<?php
function verificarUsuarioExiste($ip, $porta, $usuario_disp, $senha_disp, $user_id) {
    $url = "http://{$ip}:{$porta}/cgi-bin/AccessUser.cgi?action=list&UserIDList[0]={$user_id}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        // Verificar se UserID está na resposta XML
        return strpos($resposta, "<UserID>{$user_id}</UserID>") !== false;
    }
    
    return false;
}
?>
```

### Verificar se Foto Existe

```php
<?php
function verificarFotoExiste($ip, $porta, $usuario_disp, $senha_disp, $user_id) {
    $url = "http://{$ip}:{$porta}/cgi-bin/AccessFace.cgi?action=list&UserIDList[0]={$user_id}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        // Verificar se UserID e PhotoData estão na resposta XML
        return strpos($resposta, "<UserID>{$user_id}</UserID>") !== false &&
               strpos($resposta, "<PhotoData>") !== false;
    }
    
    return false;
}
?>
```

---

## 📝 Notas Importantes

1. **Ordem de Operações:**
   - Sempre cadastrar usuário ANTES da foto
   - Foto requer que usuário já exista no dispositivo

2. **Formato da Foto:**
   - Base64 SEM prefixo `data:image/jpeg;base64,`
   - Tamanho recomendado: máximo 300KB
   - Formato: JPEG ou PNG

3. **UserID:**
   - Deve ser único por dispositivo
   - Geralmente usa o mesmo ID do banco de dados
   - Tipo string no JSON (mesmo que seja número)

4. **Timeout:**
   - Usuário: 30 segundos
   - Foto: 60 segundos (upload maior)

5. **Autenticação:**
   - Sempre usar HTTP Digest (`CURLAUTH_DIGEST`)
   - Não usar Basic Authentication

---

## 🚀 Quick Start

```php
<?php
// Copie e cole este código para começar rapidamente

function cadastrarUsuarioDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name) {
    $dados = [
        "UserList" => [[
            "UserID" => (string)$user_id,
            "UserName" => $user_name,
            "UserType" => 0,
            "Authority" => 2,
            "Password" => "123456",
            "Doors" => [0],
            "TimeSections" => [255],
            "ValidFrom" => date('Y-m-d H:i:s'),
            "ValidTo" => date('Y-m-d H:i:s', strtotime('+1 year'))
        ]]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$ip}:{$porta}/cgi-bin/AccessUser.cgi?action=insertMulti");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200 && trim($resposta) === 'OK';
}

function cadastrarFotoDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64) {
    // Remover prefixo se existir
    if (strpos($foto_base64, 'data:image/') === 0) {
        $foto_base64 = substr($foto_base64, strpos($foto_base64, ',') + 1);
    }
    
    $dados = [
        "FaceList" => [[
            "UserID" => (string)$user_id,
            "PhotoData" => [$foto_base64]
        ]]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$ip}:{$porta}/cgi-bin/AccessFace.cgi?action=updateMulti");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$usuario_disp}:{$senha_disp}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200 && trim($resposta) === 'OK';
}

// USO:
$ip = '10.144.129.69';
$porta = 80;
$usuario_disp = 'admin';
$senha_disp = 'Arcs2901';
$user_id = 123;
$user_name = 'João Silva';
$foto_base64 = 'iVBORw0KGgoAAAANSUhEUgAA...'; // Base64 sem prefixo

// 1. Cadastrar usuário
if (cadastrarUsuarioDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $user_name)) {
    echo "Usuário cadastrado!\n";
    
    // 2. Cadastrar foto
    if (cadastrarFotoDispositivo($ip, $porta, $usuario_disp, $senha_disp, $user_id, $foto_base64)) {
        echo "Foto cadastrada!\n";
    } else {
        echo "Erro ao cadastrar foto!\n";
    }
} else {
    echo "Erro ao cadastrar usuário!\n";
}
?>
```

---

**Fim da Documentação**
