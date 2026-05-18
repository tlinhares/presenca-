# 🚀 Sistema Facial Eficiente - Culto

## 📋 **VISÃO GERAL**

Sistema completamente reformulado baseado no exemplo Python fornecido, implementando uma abordagem mais eficiente onde **todos os usuários ativos ficam permanentemente nos dispositivos faciais de culto**.

## 🔄 **ARQUITETURA NOVA**

### **ANTES (Ineficiente):**
- ❌ Sincronização diária de todos os usuários
- ❌ Usuários removidos e reenviados constantemente
- ❌ Processo manual e demorado
- ❌ Desperdício de recursos

### **AGORA (Eficiente):**
- ✅ **Usuários permanentes** no dispositivo facial
- ✅ **Sincronização automática** via triggers
- ✅ **Adição/remoção automática** quando usuário é cadastrado/inativado
- ✅ **Busca de dados** usando `recordFinder.cgi` (como exemplo Python)
- ✅ **Processamento em tempo real**

## 🛠️ **COMPONENTES IMPLEMENTADOS**

### **1. API de Busca de Dados (`api/culto/buscar_dados_facial.php`)**
```php
// Baseado no exemplo Python fornecido
$url = "http://{$device_ip}/cgi-bin/recordFinder.cgi?action=find&name=AccessControlCardRec&StartTime={$start_time}&EndTime={$end_time}";
```

**Funcionalidades:**
- Busca dados do dispositivo usando `recordFinder.cgi`
- Autenticação digest (como exemplo Python)
- Suporte a múltiplos dispositivos
- Parse de resposta XML/texto

### **2. Sincronização Permanente (`api/culto/sincronizar_usuario_permanente.php`)**
**Ações disponíveis:**
- `sincronizar_todos`: Envia todos os usuários ativos
- `adicionar_usuario`: Adiciona usuário específico
- `remover_usuario`: Remove usuário específico

### **3. Triggers Automáticos (`sql_scripts/triggers_sincronizacao_facial.sql`)**
**Triggers implementados:**
- `tr_usuario_inserido_culto`: Quando usuário é cadastrado
- `tr_usuario_atualizado_culto`: Quando usuário é ativado/inativado
- `tr_dispositivo_inserido_culto`: Quando dispositivo é cadastrado
- `tr_dispositivo_atualizado_culto`: Quando dispositivo é ativado/inativado

### **4. Processamento Automático (`api/culto/processar_sincronizacao_automatica.php`)**
- Processa ações pendentes dos triggers
- Adiciona usuários aos dispositivos
- Remove usuários dos dispositivos
- Atualiza status na tabela `facial_sync_culto`

### **5. Interface de Administração (`culto/sincronizacao_permanente.php`)**
**Funcionalidades:**
- Estatísticas em tempo real
- Ações manuais de sincronização
- Monitoramento de dispositivos
- Histórico de ações

### **6. Cron Job (`cron/processar_sync_automatica.php`)**
- Executa a cada 5 minutos
- Processa ações automáticas pendentes
- Logs detalhados

## 📊 **FLUXO DE FUNCIONAMENTO**

### **Cadastro de Usuário:**
1. Usuário é cadastrado no sistema
2. Trigger `tr_usuario_inserido_culto` é executado
3. Registros são criados em `facial_sync_culto` com status `pendente`
4. Cron job processa e envia usuário para todos os dispositivos de culto
5. Status é atualizado para `sincronizado`

### **Inativação de Usuário:**
1. Usuário é inativado no sistema
2. Trigger `tr_usuario_atualizado_culto` é executado
3. Registros são marcados com status `remover`
4. Cron job processa e remove usuário de todos os dispositivos
5. Status é atualizado para `removido`

### **Busca de Dados:**
1. Sistema consulta dispositivo usando `recordFinder.cgi`
2. Dados são processados e retornados
3. Logs são gerados para auditoria

## 🗄️ **ESTRUTURA DO BANCO**

### **Tabela `facial_sync_culto` (Atualizada):**
```sql
ALTER TABLE facial_sync_culto 
MODIFY COLUMN status ENUM('pendente', 'sincronizado', 'falha', 'remover', 'dispositivo_inativo') 
DEFAULT 'pendente';
```

### **Nova Tabela `logs_sistema`:**
```sql
CREATE TABLE logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    mensagem TEXT NOT NULL,
    dados JSON,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔧 **CONFIGURAÇÃO**

### **1. Executar Scripts SQL:**
```bash
mysql -u root -p presenca_aom < sql_scripts/triggers_sincronizacao_facial.sql
```

### **2. Configurar Cron Job:**
```bash
# Adicionar ao crontab
*/5 * * * * /usr/bin/php /var/www/html/presenca/cron/processar_sync_automatica.php
```

### **3. Acessar Interface:**
```
https://presenca.aom.org.br/culto/sincronizacao_permanente.php
```

## 📈 **BENEFÍCIOS**

### **Eficiência:**
- ✅ Usuários ficam permanentemente no dispositivo
- ✅ Sem sincronização diária desnecessária
- ✅ Processamento automático via triggers

### **Confiabilidade:**
- ✅ Sincronização automática em tempo real
- ✅ Logs detalhados de todas as ações
- ✅ Tratamento de erros robusto

### **Manutenibilidade:**
- ✅ Interface administrativa completa
- ✅ Monitoramento em tempo real
- ✅ Estatísticas detalhadas

### **Escalabilidade:**
- ✅ Suporte a múltiplos dispositivos
- ✅ Processamento em lote
- ✅ Sistema preparado para crescimento

## 🚨 **MONITORAMENTO**

### **Logs Disponíveis:**
- `logs/sync_permanente_culto_YYYY-MM-DD.log`
- `logs/processamento_automatico_YYYY-MM-DD.log`
- `logs/busca_facial_culto_YYYY-MM-DD.log`
- `logs/cron_sync_automatica_YYYY-MM-DD.log`

### **Status de Sincronização:**
- `pendente`: Aguardando processamento
- `sincronizado`: Usuário no dispositivo
- `falha`: Erro na sincronização
- `remover`: Marcado para remoção
- `dispositivo_inativo`: Dispositivo inativo

## 🔍 **TESTES**

### **1. Testar Sincronização:**
```bash
curl -X POST https://presenca.aom.org.br/api/culto/sincronizar_usuario_permanente.php \
  -H "Content-Type: application/json" \
  -d '{"acao": "sincronizar_todos"}'
```

### **2. Testar Busca de Dados:**
```bash
curl "https://presenca.aom.org.br/api/culto/buscar_dados_facial.php?data_inicio=2025-09-17"
```

### **3. Testar Processamento:**
```bash
curl "https://presenca.aom.org.br/api/culto/processar_sincronizacao_automatica.php"
```

## 📝 **PRÓXIMOS PASSOS**

1. **Configurar cron job** para processamento automático
2. **Testar triggers** com cadastro/inativação de usuários
3. **Monitorar logs** para verificar funcionamento
4. **Ajustar horários** de processamento se necessário
5. **Treinar administradores** na nova interface

---

## 🎯 **RESULTADO FINAL**

Sistema facial completamente reformulado seguindo as melhores práticas e o exemplo Python fornecido. Agora todos os usuários ativos ficam permanentemente nos dispositivos faciais de culto, com sincronização automática e eficiente.

**🚀 Sistema mais rápido, confiável e eficiente!**
