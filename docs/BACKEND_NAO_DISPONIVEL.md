# ⚠️ Backend Não Disponível - Modo Mock Ativado

## 🔍 Situação Atual

O endpoint `/api/mobile/reservas` ainda não está implementado no backend, então o app está usando **dados mock** com a estrutura real do banco de dados.

## ✅ O que Foi Feito

1. **Modo Mock Reativado** (`_useMockData = true`)
   - Dados mock agora usam a estrutura real do banco
   - Campos corretos: `data`, `reservou_conjuge`, `marmitex`, etc.
   - Inclui campos calculados: `status`, `pode_excluir`

2. **Dados Mock Realistas**
   - 3 reservas de exemplo
   - Uma futura (pode excluir)
   - Uma atual (pode excluir)
   - Uma finalizada (não pode excluir)
   - Inclui exemplos com cônjuge e marmitex

## 🚀 Como Usar Agora

1. **Faça Hot Restart** (pressione `R` no terminal)
2. **O app funcionará** com dados mock
3. **Teste todas as funcionalidades:**
   - Listar reservas
   - Criar nova reserva
   - Cancelar reserva

## 🔄 Quando o Backend Estiver Pronto

1. **Implemente o endpoint** no backend:
   ```
   /var/www/html/presenca/api/mobile/reservas/index.php
   ```

2. **Desative o modo mock:**
   - Abra `lib/core/api/reservas_service.dart`
   - Mude linha 9: `static const bool _useMockData = false;`

3. **Faça Hot Restart**

## 📋 Endpoint que Precisa ser Implementado

### GET /api/mobile/reservas

**Exemplo de implementação PHP:**

```php
<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';
require_once __DIR__ . '/../utils/response.php';

MobileAuthMiddleware::requireAuth();

try {
    $userId = $_SESSION['usuario_id'];
    $conn = require __DIR__ . '/../../../config/database.php';
    
    // Buscar reservas do usuário
    $stmt = $conn->prepare("
        SELECT 
            id,
            id_usuario,
            data,
            reservou_conjuge,
            marmitex,
            valor_refeicao,
            valor_marmitex,
            horario_confirmacao,
            observacao_especial
        FROM reservas_almoco
        WHERE id_usuario = ?
        ORDER BY data DESC, horario_confirmacao DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservas = [];
    $hoje = new DateTime();
    
    while ($row = $result->fetch_assoc()) {
        // Calcular status e pode_excluir
        $dataReserva = new DateTime($row['data']);
        $status = 'Futura';
        $podeExcluir = true;
        
        if ($dataReserva->format('Y-m-d') < $hoje->format('Y-m-d')) {
            $status = 'Finalizada';
            $podeExcluir = false;
        } elseif ($dataReserva->format('Y-m-d') == $hoje->format('Y-m-d')) {
            // Verificar hora limite (buscar da configuração)
            $status = 'Atual';
            // Lógica para pode_excluir baseada em hora limite
        }
        
        $row['status'] = $status;
        $row['pode_excluir'] = $podeExcluir;
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

## 📝 Formato de Resposta Esperado

```json
{
  "success": true,
  "data": {
    "reservas": [
      {
        "id": 4159,
        "id_usuario": 22346,
        "data": "2026-01-16",
        "reservou_conjuge": 0,
        "marmitex": 0,
        "valor_refeicao": "30.00",
        "valor_marmitex": null,
        "horario_confirmacao": "2026-01-16 09:54:02",
        "observacao_especial": null,
        "status": "Atual",
        "pode_excluir": true
      }
    ]
  },
  "message": "Reservas listadas com sucesso"
}
```

## ✅ Status

- ✅ **Frontend:** Pronto e funcional
- ✅ **Modelo:** Atualizado conforme banco real
- ✅ **Mock:** Ativo com estrutura real
- 🚧 **Backend:** Precisa implementar endpoint

---

**Faça Hot Restart agora e teste o app com dados mock! 🚀**
