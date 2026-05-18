# 🚀 Melhorias no Sistema de Envio de WhatsApp

## 📋 Resumo das Alterações

Implementadas melhorias significativas no sistema de envio de WhatsApp para evitar detecção como bot e melhorar a experiência do usuário.

---

## ✅ O que foi implementado

### 1. **Sistema de Mensagens Variadas**
- ✅ Criada tabela `mensagens_padrao` no banco de dados
- ✅ 8 mensagens variadas do tipo `lembrete_reserva` inseridas
- ✅ Função `buscarMensagemAleatoria()` que sorteia uma mensagem diferente para cada envio
- ✅ Sistema mantém compatibilidade: se a tabela não existir, usa mensagem padrão

### 2. **Delays Inteligentes e Variados**
- ✅ Método `calcularDelayVariado()`: delays de 8-40s com 15% de chance de pausa longa (1-6 min)
- ✅ Método `enviarMensagensEmBatches()`: divide envios em batches de 10-30 mensagens
- ✅ Pausas longas de 5-15 minutos entre batches
- ✅ Método `estaNaJanelaEnvio()`: valida horário permitido (07:00-08:30)

### 3. **Modificações no Cron de Notificação**
- ✅ `cron/notificacao_reserva.php` agora usa mensagens variadas
- ✅ Sistema de batches implementado automaticamente
- ✅ Logs melhorados para rastreamento

---

## 📁 Arquivos Modificados

### Novos Arquivos Criados:
1. **`sql/criar_tabela_mensagens_padrao.sql`** - Script SQL para criar a tabela
2. **`api/instalar_mensagens_padrao.php`** - Script de instalação (já executado)
3. **`api/testar_mensagens_variadas.php`** - Script de teste das funcionalidades
4. **`MELHORIAS_WHATSAPP.md`** - Este documento

### Arquivos Modificados:
1. **`core/services/WhatsAppService.php`**
   - Adicionado: `calcularDelayVariado()`
   - Adicionado: `estaNaJanelaEnvio()`
   - Adicionado: `enviarMensagensEmBatches()`

2. **`cron/notificacao_reserva.php`**
   - Adicionado: função `buscarMensagemAleatoria()`
   - Modificado: uso de mensagens variadas
   - Modificado: uso de batches com delays variados

---

## 🔧 Como Funciona Agora

### Antes:
- Mensagem fixa para todos os usuários
- Delay fixo de 5-15 segundos
- Envio sequencial sem pausas longas

### Agora:
1. **Mensagens Variadas**: Cada usuário recebe uma mensagem diferente (sorteada aleatoriamente)
2. **Delays Variados**: 
   - Delay normal: 8-40 segundos entre mensagens
   - 15% de chance de pausa longa: 1-6 minutos
3. **Batches Inteligentes**:
   - Divide envios em grupos de 10-30 mensagens
   - Pausa de 5-15 minutos entre batches
4. **Janela de Envio**: Valida horário recomendado (07:00-08:30)

---

## 📊 Estrutura da Tabela `mensagens_padrao`

```sql
CREATE TABLE mensagens_padrao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    mensagem TEXT NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo_ativo (tipo, ativo)
);
```

### Mensagens Inseridas:
- 8 variações do tipo `lembrete_reserva`
- Todas ativas por padrão
- Placeholders: `{nome}` e `{horario_limite}`

---

## 🧪 Como Testar

### 1. Testar Mensagens Variadas:
Acesse: `http://seu-servidor/presenca/api/testar_mensagens_variadas.php`

### 2. Verificar Instalação:
Acesse: `http://seu-servidor/presenca/api/instalar_mensagens_padrao.php`

### 3. Ver Logs:
```bash
tail -f /var/www/html/presenca/logs/notificacoes.log
```

---

## ⚙️ Configurações Disponíveis

### No método `enviarMensagensEmBatches()`:

```php
WhatsAppService::enviarMensagensEmBatches($destinatarios, [
    'tamanho_batch' => rand(10, 30),        // Tamanho do batch (10-30)
    'pausa_entre_batches_min' => 5,         // Pausa mínima entre batches (minutos)
    'pausa_entre_batches_max' => 15,        // Pausa máxima entre batches (minutos)
    'log_callback' => function($msg) { ... } // Callback para logs
]);
```

### No método `calcularDelayVariado()`:

```php
WhatsAppService::calcularDelayVariado(
    $minimo_curto = 8,                      // Delay mínimo curto (segundos)
    $maximo_curto = 40,                     // Delay máximo curto (segundos)
    $probabilidade_pausa_longa = 0.15        // Probabilidade de pausa longa (15%)
);
```

---

## 🔒 Segurança e Compatibilidade

### ✅ Garantias:
- ✅ Sistema mantém compatibilidade total
- ✅ Se a tabela `mensagens_padrao` não existir, usa mensagem padrão
- ✅ Se não houver mensagens ativas, usa mensagem padrão
- ✅ Todos os métodos antigos continuam funcionando
- ✅ Nenhuma funcionalidade existente foi quebrada

### ⚠️ Observações:
- O sistema está em produção e todas as alterações foram feitas de forma segura
- Os métodos antigos (`enviarMensagensEmLote`) continuam disponíveis
- O novo sistema é usado apenas no cron de notificação de reserva

---

## 📝 Próximos Passos (Opcional)

1. **Adicionar mais tipos de mensagens**:
   - `confirmacao_reserva`
   - `cancelamento_reserva`
   - `lembrete_culto`
   - etc.

2. **Ajustar parâmetros** conforme necessidade:
   - Tamanho dos batches
   - Delays entre mensagens
   - Janela de envio

3. **Monitorar logs** para verificar eficácia:
   - Verificar se ainda há problemas de deslogamento
   - Ajustar probabilidade de pausas longas se necessário

---

## 🆘 Suporte

Em caso de problemas:
1. Verifique os logs: `/var/www/html/presenca/logs/notificacoes.log`
2. Execute o script de teste: `api/testar_mensagens_variadas.php`
3. Verifique se a tabela existe: `api/instalar_mensagens_padrao.php`

---

**Data de Implementação:** 2026-01-07
**Status:** ✅ Implementado e Testado
**Impacto:** 🟢 Baixo (alterações seguras, compatibilidade mantida)

