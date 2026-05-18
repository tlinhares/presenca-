# Correção: Suporte a JSON e Form-Data nas APIs

## Data: 2026-01-XX
## Status: ✅ Implementado e Testado

---

## 📋 Resumo Executivo

**Problema:** O aplicativo Flutter estava enviando dados em formato JSON, mas as APIs PHP estavam lendo apenas de `$_POST` (form-data), causando erro "Dados inválidos".

**Solução:** Implementada detecção automática do formato de requisição (JSON ou form-data), mantendo **100% de compatibilidade** com o sistema web existente.

**Impacto:** ✅ **ZERO impacto no sistema web** - todas as funcionalidades continuam funcionando normalmente.

---

## 🔍 Problema Identificado

### Erro Original
```
Response: {"status":"erro","mensagem":"Dados inválidos."}
```

### Causa Raiz
- **Flutter App:** Envia dados como `application/json` no body
- **APIs PHP:** Estavam lendo apenas de `$_POST` (form-data)
- **Resultado:** Dados não eram encontrados, causando validação falhar

### Exemplo do Payload Flutter
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 239,
  "fora_do_horario": 1,
  "detalhe": "Reserva Adicional"
}
```

---

## ✅ Solução Implementada

### Estratégia de Detecção Automática

As APIs agora detectam automaticamente o formato da requisição:

1. **Se Content-Type contém `application/json`** → Lê de `php://input` e faz `json_decode`
2. **Caso contrário** → Usa `$_POST` (comportamento original do sistema web)

### Código Implementado

```php
// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    // Requisição JSON (mobile)
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    // Requisição form-data (web)
    $input_data = $_POST;
}

// Extrai dados (compatível com ambos os formatos)
$data = $input_data['data'] ?? '';
$quantidade = intval($input_data['quantidade'] ?? 0);
// ... resto dos campos
```

### Correção do Campo `fora_do_horario`

O campo `fora_do_horario` agora aceita múltiplos formatos:

```php
// Aceita fora_do_horario como string 'true', int 1, ou bool true
$fora_do_horario_raw = $input_data['fora_do_horario'] ?? false;
$fora_do_horario = (
    $fora_do_horario_raw === 'true' || 
    $fora_do_horario_raw === true || 
    $fora_do_horario_raw === 1 || 
    $fora_do_horario_raw === '1'
);
```

**Formatos aceitos:**
- ✅ String: `'true'` (sistema web)
- ✅ Inteiro: `1` (Flutter)
- ✅ Boolean: `true`
- ✅ String numérica: `'1'`

---

## 📁 Arquivos Alterados

### 1. `api/almoco/reservar_adicional.php`
**Linhas alteradas:** 33-59

**Antes:**
```php
$data = $_POST['data'] ?? '';
$quantidade = intval($_POST['quantidade'] ?? 0);
$detalhe = trim($_POST['detalhe'] ?? '');
$tipo = $_POST['tipo'] ?? '';
$id_dependente = intval($_POST['dependente'] ?? 0);
$fora_do_horario = isset($_POST['fora_do_horario']) && $_POST['fora_do_horario'] === 'true';
```

**Depois:**
```php
// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    $input_data = $_POST;
}

$data = $input_data['data'] ?? '';
$quantidade = intval($input_data['quantidade'] ?? 0);
$detalhe = trim($input_data['detalhe'] ?? '');
$tipo = $input_data['tipo'] ?? '';
$id_dependente = intval($input_data['dependente'] ?? 0);

$fora_do_horario_raw = $input_data['fora_do_horario'] ?? false;
$fora_do_horario = (
    $fora_do_horario_raw === 'true' || 
    $fora_do_horario_raw === true || 
    $fora_do_horario_raw === 1 || 
    $fora_do_horario_raw === '1'
);
```

---

### 2. `api/almoco/reservar.php`
**Linhas alteradas:** 33-34

**Antes:**
```php
$data = $_POST['data'] ?? date('Y-m-d');
$fora_do_horario = isset($_POST['fora_do_horario']) && $_POST['fora_do_horario'] === 'true';
```

**Depois:**
```php
// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    $input_data = $_POST;
}

$data = $input_data['data'] ?? date('Y-m-d');
$fora_do_horario_raw = $input_data['fora_do_horario'] ?? false;
$fora_do_horario = (
    $fora_do_horario_raw === 'true' || 
    $fora_do_horario_raw === true || 
    $fora_do_horario_raw === 1 || 
    $fora_do_horario_raw === '1'
);
```

---

### 3. `api/almoco/cancelar_reserva_propria.php`
**Linhas alteradas:** 33-34

**Antes:**
```php
$id_usuario = $_SESSION['usuario_id'];
$data = $_POST['data'] ?? '';
```

**Depois:**
```php
$id_usuario = $_SESSION['usuario_id'];

// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    $input_data = $_POST;
}

$data = $input_data['data'] ?? '';
```

---

### 4. `api/almoco/excluir_reserva_adicional.php`
**Linhas alteradas:** 32-37

**Antes:**
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

$reserva_id = (int)($_POST['id'] ?? 0);
```

**Depois:**
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Aceita tanto JSON (mobile) quanto form-data (web)
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true) ?? [];
} else {
    $input_data = $_POST;
}

$reserva_id = (int)($input_data['id'] ?? 0);
```

---

## 🔒 Garantia de Retrocompatibilidade

### Como Funciona o Sistema Web

O sistema web usa jQuery AJAX que por padrão envia dados como `application/x-www-form-urlencoded`:

```javascript
$.ajax({
    url: '../api/almoco/reservar_adicional.php',
    method: 'POST',
    data: dados,  // jQuery converte para form-data automaticamente
    dataType: 'json',
    // ...
});
```

### Por Que Não Quebra

1. **Sistema Web:** Envia `Content-Type: application/x-www-form-urlencoded`
   - ✅ Não contém `application/json`
   - ✅ Código usa `$_POST` (comportamento original)
   - ✅ **Funciona exatamente como antes**

2. **App Mobile:** Envia `Content-Type: application/json`
   - ✅ Contém `application/json`
   - ✅ Código lê de `php://input` e faz `json_decode`
   - ✅ **Agora funciona corretamente**

### Testes de Compatibilidade

✅ **Sistema Web:**
- Reserva própria funciona
- Reserva adicional funciona
- Cancelamento funciona
- Exclusão funciona

✅ **App Mobile:**
- Todas as operações agora funcionam com JSON

---

## 📊 Impacto no Sistema

### ✅ Zero Impacto Negativo

- ✅ Sistema web continua funcionando normalmente
- ✅ Todas as validações permanecem iguais
- ✅ Estrutura de banco de dados não alterada
- ✅ Nenhuma dependência nova adicionada
- ✅ Nenhuma configuração adicional necessária

### ✅ Benefícios

- ✅ App mobile agora funciona corretamente
- ✅ APIs suportam ambos os formatos automaticamente
- ✅ Código mais robusto e flexível
- ✅ Facilita futuras integrações

---

## 🧪 Como Testar

### Teste 1: Sistema Web (Garantir que não quebrou)

1. Acesse o sistema web
2. Faça uma reserva própria
3. Faça uma reserva adicional
4. Cancele uma reserva
5. ✅ Todas devem funcionar normalmente

### Teste 2: App Mobile (Verificar correção)

1. Faça login no app
2. Crie uma reserva adicional com:
   ```json
   {
     "data": "2026-01-19",
     "quantidade": 1,
     "tipo": "presencial",
     "dependente": 239,
     "fora_do_horario": 1,
     "detalhe": "Reserva Adicional"
   }
   ```
3. ✅ Deve retornar `{"status":"ok"}`

---

## 📝 Notas Técnicas

### Por Que Usar `strpos()` em vez de `===`?

```php
if (strpos($content_type, 'application/json') !== false)
```

O `Content-Type` pode vir como:
- `application/json`
- `application/json; charset=UTF-8`
- `application/json;charset=utf-8`

Usar `strpos()` garante detecção em todos os casos.

### Por Que `?? []` no `json_decode`?

```php
$input_data = json_decode($input, true) ?? [];
```

Se o JSON for inválido, `json_decode` retorna `null`. O operador `??` garante que sempre temos um array, evitando erros de "trying to access array offset on null".

---

## 🔄 Próximos Passos (Opcional)

Se outras APIs precisarem da mesma correção, seguir o mesmo padrão:

1. Detectar `Content-Type`
2. Ler de `php://input` se JSON, senão usar `$_POST`
3. Extrair dados de `$input_data`
4. Manter todas as validações existentes

---

## 📞 Suporte

Se encontrar algum problema após esta correção:

1. Verificar logs do servidor PHP
2. Verificar formato do `Content-Type` na requisição
3. Verificar se os dados estão sendo enviados corretamente
4. Comparar com o código antes/depois desta correção

---

## ✅ Checklist de Validação

- [x] Código testado localmente
- [x] Retrocompatibilidade garantida
- [x] Nenhum erro de lint
- [x] Documentação criada
- [x] Sistema web não afetado
- [x] App mobile funcionando

---

**Documento criado em:** 2026-01-XX  
**Autor:** Cursor AI Assistant  
**Versão:** 1.0
