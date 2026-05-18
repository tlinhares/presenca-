# 📱 Menu de Gerenciamento de Mensagens WhatsApp

## ✅ Implementação Concluída

Foi criado um sistema completo para gerenciar as mensagens padrão do WhatsApp que são usadas no sistema de envio.

---

## 📁 Arquivos Criados

### Interface Principal
- **`painel/gerenciar_mensagens_whatsapp.php`**
  - Interface completa para gerenciar mensagens
  - Listagem com filtros por tipo
  - Preview das mensagens com placeholders substituídos
  - Cards visuais com status (ativo/inativo)

### APIs (CRUD Completo)
- **`api/mensagens_whatsapp/listar.php`** - Lista todas as mensagens
- **`api/mensagens_whatsapp/buscar.php`** - Busca uma mensagem específica
- **`api/mensagens_whatsapp/criar.php`** - Cria nova mensagem
- **`api/mensagens_whatsapp/editar.php`** - Edita mensagem existente
- **`api/mensagens_whatsapp/excluir.php`** - Exclui mensagem

### Scripts de Instalação
- **`sql/inserir_menu_mensagens_whatsapp.sql`** - Script SQL para criar o menu
- **`api/mensagens_whatsapp/instalar_menu.php`** - Script PHP de instalação (alternativo)

---

## 🎯 Funcionalidades

### ✅ Gerenciamento Completo
- ✅ **Criar** novas mensagens com diferentes tipos
- ✅ **Editar** mensagens existentes
- ✅ **Excluir** mensagens
- ✅ **Ativar/Desativar** mensagens (inativas não são sorteadas)
- ✅ **Filtrar** por tipo de mensagem
- ✅ **Preview** em tempo real com placeholders substituídos

### 📋 Tipos de Mensagens Suportados
- `lembrete_reserva` - Lembrete de reserva de almoço
- `confirmacao_reserva` - Confirmação de reserva
- `cancelamento_reserva` - Cancelamento de reserva
- *Novos tipos podem ser adicionados facilmente*

### 🔐 Segurança
- ✅ Acesso exclusivo para **administradores** (`requer_admin = 1`)
- ✅ Todas as APIs verificam permissão antes de executar
- ✅ Validação de dados em todas as operações

---

## 🚀 Como Acessar

### Via Menu do Sistema
1. Faça login como **administrador**
2. Acesse: **Gerenciamento → Gerenciar Mensagens WhatsApp**
3. Ou acesse diretamente: `/painel/gerenciar_mensagens_whatsapp.php`

### Menu Cadastrado
- **Código:** `gerenciar_mensagens_whatsapp`
- **Nome:** Gerenciar Mensagens WhatsApp
- **Categoria:** gerenciamento
- **Acesso:** Apenas administradores

---

## 💡 Como Usar

### Criar Nova Mensagem
1. Clique em **"Nova Mensagem"**
2. Selecione ou digite o **tipo** da mensagem
3. Digite a **mensagem** usando placeholders:
   - `{nome}` - Será substituído pelo nome do usuário
   - `{horario_limite}` - Será substituído pelo horário limite
4. Ative/desative conforme necessário
5. Veja o **preview** em tempo real
6. Clique em **Salvar**

### Editar Mensagem
1. Clique em **"Editar"** no card da mensagem
2. Modifique os campos desejados
3. Clique em **Salvar**

### Excluir Mensagem
1. Clique em **"Excluir"** no card da mensagem
2. Confirme a exclusão

### Filtrar por Tipo
- Use o dropdown **"Filtrar por Tipo"** para ver apenas mensagens de um tipo específico

---

## 🔗 Integração com Sistema de Envio

As mensagens cadastradas são automaticamente usadas pelo sistema de envio:

```php
// No cron/notificacao_reserva.php
$mensagem_variada = buscarMensagemAleatoria($conn, 'lembrete_reserva', [
    'nome' => $usuario['nome'],
    'horario_limite' => $horario_limite_agendamento
]);
```

O sistema:
1. Busca todas as mensagens ativas do tipo especificado
2. Sorteia uma aleatoriamente
3. Substitui os placeholders pelos valores reais
4. Envia via WhatsApp

---

## 📊 Estrutura da Tabela

A tabela `mensagens_padrao` possui:
- `id` - ID único
- `tipo` - Tipo da mensagem (ex: 'lembrete_reserva')
- `mensagem` - Template com placeholders
- `ativo` - Se está ativa (1) ou inativa (0)
- `criado_em` - Data de criação
- `atualizado_em` - Data da última atualização

---

## 🎨 Interface

A interface foi desenvolvida com:
- Design moderno e responsivo
- Cards visuais para cada mensagem
- Preview em tempo real
- Filtros por tipo
- Badges de status (ativo/inativo)
- Exemplos de mensagens com placeholders substituídos

---

## ✅ Status

- ✅ Menu criado e cadastrado no banco
- ✅ Interface principal funcionando
- ✅ APIs CRUD completas
- ✅ Validações e segurança implementadas
- ✅ Integração com sistema de envio funcionando
- ✅ Testes de sintaxe passaram

---

## 📝 Próximos Passos (Opcional)

1. **Adicionar mais tipos de mensagens:**
   - Mensagens de confirmação
   - Mensagens de cancelamento
   - Mensagens de relatórios
   - etc.

2. **Estatísticas:**
   - Ver quantas vezes cada mensagem foi usada
   - Relatório de mensagens mais utilizadas

3. **Validação de Placeholders:**
   - Verificar se todos os placeholders usados existem
   - Sugerir placeholders disponíveis

---

**Data de Criação:** 2026-01-07  
**Status:** ✅ Completo e Funcional

