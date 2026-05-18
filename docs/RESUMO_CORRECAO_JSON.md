# Resumo: Correção Suporte JSON nas APIs

## ⚠️ IMPORTANTE: NÃO QUEBRA O SISTEMA WEB

Todas as alterações são **100% retrocompatíveis**. O sistema web continua funcionando normalmente porque:
- Sistema web envia `application/x-www-form-urlencoded` (form-data)
- Código detecta automaticamente e usa `$_POST` quando não é JSON
- **Comportamento original preservado**

---

## 🔧 O Que Foi Alterado

### Problema
App Flutter envia JSON, mas APIs liam apenas de `$_POST` → Erro "Dados inválidos"

### Solução
Detecção automática do formato:
- Se `Content-Type` contém `application/json` → Lê de `php://input` (JSON)
- Caso contrário → Usa `$_POST` (form-data - sistema web)

---

## 📁 Arquivos Modificados

### 1. `api/almoco/reservar_adicional.php`
**Mudança:** Linhas 33-59
- Adicionada detecção de formato JSON/form-data
- Campo `fora_do_horario` agora aceita: `'true'`, `1`, `true`, `'1'`

### 2. `api/almoco/reservar.php`
**Mudança:** Linhas 33-34
- Adicionada detecção de formato JSON/form-data
- Campo `fora_do_horario` agora aceita múltiplos formatos

### 3. `api/almoco/cancelar_reserva_propria.php`
**Mudança:** Linhas 33-34
- Adicionada detecção de formato JSON/form-data

### 4. `api/almoco/excluir_reserva_adicional.php`
**Mudança:** Linhas 32-37
- Adicionada detecção de formato JSON/form-data

---

## 💻 Padrão de Código Implementado

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

// Extrai dados normalmente
$campo = $input_data['campo'] ?? '';
```

---

## ✅ Validação

- ✅ Sistema web funciona normalmente (testado)
- ✅ App mobile agora funciona com JSON
- ✅ Nenhum erro de lint
- ✅ Retrocompatibilidade garantida

---

## 📝 Para Outras APIs

Se precisar aplicar a mesma correção em outras APIs, use o mesmo padrão acima.

---

**Data:** 2026-01-XX  
**Status:** ✅ Implementado e Testado
