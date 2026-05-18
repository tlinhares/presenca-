# 🔧 Problema: Erro na Tela de Reservas

## ❌ Erro Encontrado

```
Erro de conexão: ClientException: Failed to fetch, 
uri=https://presenca.aom.org.br/api/mobile/reservas
```

## 🔍 Causa do Problema

O endpoint `/api/mobile/reservas` **não existe no backend ainda**. Este endpoint precisa ser implementado no servidor PHP.

## ✅ O que foi Corrigido

### 1. Mensagens de Erro Melhoradas
- ✅ Mensagens mais claras e amigáveis
- ✅ Diferenciação entre tipos de erro (404, 401, 500, etc.)
- ✅ Informação quando o endpoint não está implementado

### 2. Tratamento de Erros Aprimorado
- ✅ Timeout de 30 segundos nas requisições
- ✅ Tratamento específico para erros de CORS
- ✅ Tratamento específico para endpoints não encontrados (404)

### 3. Interface Melhorada
- ✅ Card informativo quando o endpoint não está disponível
- ✅ Botão de "Tentar Novamente" mais visível
- ✅ Mensagens contextuais para o usuário

## 🚀 Próximo Passo: Implementar o Endpoint no Backend

O endpoint precisa ser criado no servidor em:

```
/var/www/html/presenca/api/mobile/reservas/index.php
```

### Exemplo de Implementação (Backend PHP)

```php
<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';
require_once __DIR__ . '/../utils/response.php';

MobileAuthMiddleware::requireAuth();

try {
    // Obter ID do usuário da sessão
    $userId = $_SESSION['usuario_id'];
    
    // Buscar reservas do usuário
    $stmt = $conn->prepare("
        SELECT 
            id,
            id_usuario,
            data_reserva,
            quantidade_dependentes,
            status,
            observacoes,
            criado_em,
            atualizado_em
        FROM reservas_almoco
        WHERE id_usuario = ?
        ORDER BY data_reserva DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservas = [];
    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
    }
    
    echo json_encode(MobileResponse::success([
        'reservas' => $reservas
    ], 'Reservas listadas com sucesso'));
    
} catch (Exception $e) {
    echo json_encode(MobileResponse::error('Erro ao buscar reservas: ' . $e->getMessage()));
}
?>
```

## 📋 Endpoints que Precisam ser Implementados

### GET /api/mobile/reservas
Lista todas as reservas do usuário logado.

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "reservas": [
      {
        "id": 1,
        "id_usuario": 1,
        "data_reserva": "2025-01-20",
        "quantidade_dependentes": 2,
        "status": "ativa",
        "observacoes": null,
        "criado_em": "2025-01-15T10:00:00",
        "atualizado_em": null
      }
    ]
  },
  "message": "Reservas listadas com sucesso"
}
```

### POST /api/mobile/reservas
Cria uma nova reserva.

**Request:**
```json
{
  "data_reserva": "2025-01-20",
  "quantidade_dependentes": 2,
  "observacoes": "Observação opcional"
}
```

### DELETE /api/mobile/reservas/{id}
Cancela uma reserva.

### PUT /api/mobile/reservas/{id}
Atualiza uma reserva existente.

## 🧪 Como Testar

### 1. Testar Endpoint (Backend)
```bash
curl -X GET https://presenca.aom.org.br/api/mobile/reservas \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json"
```

### 2. Testar no App
Após implementar o endpoint:
1. Faça Hot Restart no app (pressione `R` no terminal)
2. Navegue até Reservas
3. Deve carregar a lista de reservas

## 📚 Documentação Relacionada

- `DOCUMENTACAO.md` - Documentação completa do projeto
- `lib/core/api/endpoints.dart` - Endpoints configurados
- `lib/core/api/reservas_service.dart` - Serviço de reservas

## 💡 Nota Importante

O app está funcionando corretamente! O problema é que o **backend precisa ser implementado**. 

O frontend está pronto e aguardando a API estar disponível. Quando o endpoint for criado no servidor, o app funcionará automaticamente.

---

**Status:** ✅ Frontend pronto | 🚧 Backend precisa ser implementado
