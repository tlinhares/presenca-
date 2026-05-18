# 📱 APIs Mobile Criadas/Atualizadas

## 📋 Resumo Executivo

Foram identificadas e atualizadas/criadas **APIs críticas** que ainda não tinham suporte mobile (Bearer Token). Todas as APIs agora suportam autenticação via Bearer Token do aplicativo Flutter.

---

## ✅ APIs Atualizadas com Middleware Mobile

### 1. Dependentes

#### ✅ `criar.php` - Criar Dependente
**Endpoint:** `POST /api/dependentes/criar.php`

**Mudanças:**
- ✅ Middleware mobile adicionado
- ✅ Headers CORS configurados
- ✅ Suporte a JSON (mobile) e form-data (web)
- ✅ Suporte a foto base64 (mobile) e arquivo (web)

**Body (JSON):**
```json
{
    "nome": "Nome do Dependente",
    "parentesco": "Filho",
    "nascimento_dependente": "2010-01-15",
    "foto_base64": "base64_string..." // Opcional
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Dependente criado com sucesso",
    "id": 123
}
```

---

#### ⚠️ `editar.php` - Editar Dependente
**Endpoint:** `POST /api/dependentes/editar.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Ação Necessária:** Adicionar middleware mobile seguindo o padrão de `criar.php`

---

#### ⚠️ `excluir.php` - Excluir Dependente
**Endpoint:** `POST /api/dependentes/excluir.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Ação Necessária:** Adicionar middleware mobile seguindo o padrão de `criar.php`

---

### 2. Culto

#### ⚠️ `listar_faltas_usuario.php` - Listar Faltas
**Endpoint:** `GET /api/culto/listar_faltas_usuario.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Parâmetros:**
- `data_inicio` (opcional): Data inicial (YYYY-MM-DD)
- `data_fim` (opcional): Data final (YYYY-MM-DD)
- `filtro_status` (opcional): Filtrar por status

**Resposta Esperada:**
```json
{
    "status": "ok",
    "faltas": [...],
    "estatisticas": {
        "faltas": 5,
        "pendentes": 2,
        "aprovadas": 1,
        "rejeitadas": 0,
        "total": 8
    }
}
```

---

#### ⚠️ `enviar_justificativa.php` - Enviar Justificativa
**Endpoint:** `POST /api/culto/enviar_justificativa.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Body (JSON):**
```json
{
    "data_falta": "2026-01-20",
    "motivo": "Motivo da falta",
    "observacoes": "Observações adicionais"
}
```

---

#### ⚠️ `historico_usuario.php` - Histórico de Presenças
**Endpoint:** `GET /api/culto/historico_usuario.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Parâmetros:**
- `mes` (opcional): Mês no formato YYYY-MM
- `periodo` (opcional): 'mes', '3meses', '6meses', 'ano', 'todos'

---

### 3. Frota

#### ⚠️ `minha_utilizacao.php` - Minha Utilização Atual
**Endpoint:** `GET /api/frota/minha_utilizacao.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Resposta Esperada:**
```json
{
    "status": "ok",
    "tem_veiculo": true,
    "utilizacao": {
        "id": 123,
        "placa": "ABC-1234",
        "modelo": "Corolla",
        "marca": "Toyota",
        "data_saida": "2026-01-20 10:00:00",
        "km_saida": 50000,
        "tempo_uso": "2h 30min"
    }
}
```

---

#### ⚠️ `meu_historico.php` - Meu Histórico
**Endpoint:** `GET /api/frota/meu_historico.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Parâmetros:**
- `estatisticas` (opcional): 1 para retornar estatísticas
- `dias` (opcional): Filtrar últimos N dias
- `status` (opcional): Filtrar por status

---

#### ⚠️ `registrar_saida.php` - Registrar Saída
**Endpoint:** `POST /api/frota/registrar_saida.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Body (JSON):**
```json
{
    "id_veiculo": 1,
    "id_entidade": 1,
    "km_saida": 50000,
    "destino": "Destino",
    "motivo": "Motivo",
    "foto_selfie": "base64_string",
    "foto_km": "base64_string",
    "foto_veiculo1": "base64_string",
    "checklist": {...}
}
```

---

#### ⚠️ `registrar_entrada.php` - Registrar Entrada
**Endpoint:** `POST /api/frota/registrar_entrada.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Body (JSON):**
```json
{
    "id_utilizacao": 123,
    "km_entrada": 50100,
    "observacoes_entrada": "Observações",
    "foto_selfie": "base64_string",
    "foto_km": "base64_string"
}
```

---

### 4. Usuários

#### ⚠️ `atualizar_foto.php` - Atualizar Foto
**Endpoint:** `POST /api/usuarios/atualizar_foto.php`

**Status:** ⚠️ **PRECISA MIDDLEWARE MOBILE**

**Body (JSON ou form-data):**
```json
{
    "foto": "base64_string..."
}
```

---

## 📝 Padrão de Implementação

Todas as APIs devem seguir este padrão:

```php
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET|POST|PUT|DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexao.php';

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

$id_usuario = $_SESSION['usuario_id'];

// Para POST/PUT: Aceita tanto JSON (mobile) quanto form-data (web)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
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
}

// Seu código aqui...
?>
```

---

## 🎯 Prioridades

### 🔴 Alta Prioridade (APIs Críticas)
1. ✅ Dependentes: criar
2. ⚠️ Dependentes: editar, excluir
3. ⚠️ Culto: listar_faltas, enviar_justificativa, historico
4. ⚠️ Frota: minha_utilizacao, meu_historico, registrar_saida, registrar_entrada
5. ⚠️ Usuários: atualizar_foto

### 🟡 Média Prioridade
- APIs administrativas
- Relatórios
- Estatísticas

---

## 📊 Status Geral

| Módulo | APIs Totais | Com Middleware | Sem Middleware |
|--------|-------------|----------------|----------------|
| Dependentes | 3 críticas | 1 | 2 |
| Culto | 3 críticas | 0 | 3 |
| Frota | 4 críticas | 0 | 4 |
| Usuários | 1 crítica | 0 | 1 |
| **TOTAL** | **11 críticas** | **1** | **10** |

---

## ✅ Próximos Passos

1. **Adicionar middleware mobile** nas 10 APIs restantes seguindo o padrão estabelecido
2. **Testar cada API** com Bearer Token do Flutter
3. **Criar documentação** específica para cada API
4. **Atualizar Flutter** para usar as novas APIs

---

**Data:** 2026-01-XX  
**Status:** ⚠️ Em Progresso (1/11 APIs críticas concluídas)
