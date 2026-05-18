# 🔧 **GUIA DE CONFIGURAÇÃO - DISPOSITIVO FACIAL SS3530 PARA CULTO**

## 📋 **INFORMAÇÕES DO SISTEMA**

### **URL da API:**
```
http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php
```

### **Método HTTP:**
```
POST
```

### **Content-Type:**
```
application/json
```

---

## 🖥️ **CONFIGURAÇÃO NO DISPOSITIVO FACIAL**

### **1. Acessar o Dispositivo**
1. Conecte o dispositivo à rede
2. Acesse via navegador: `http://IP_DO_DISPOSITIVO`
3. Faça login com as credenciais do dispositivo

### **2. Configurar API de Leitura Facial**

#### **Menu: Configurações → API → Leitura Facial**

**Configurações necessárias:**

| Campo | Valor |
|-------|-------|
| **URL da API** | `http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php` |
| **Método** | `POST` |
| **Content-Type** | `application/json` |
| **Timeout** | `30 segundos` |
| **Tentativas** | `3` |
| **Intervalo entre tentativas** | `5 segundos` |

### **3. Formato dos Dados Enviados**

O dispositivo deve enviar os seguintes dados em JSON:

```json
{
  "nome_usuario": "João Silva",
  "ip_dispositivo": "10.144.198.50",
  "timestamp": 1726574400,
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

**Campos obrigatórios:**
- `nome_usuario`: Nome do usuário reconhecido
- `ip_dispositivo`: IP do dispositivo que fez a leitura
- `timestamp`: Timestamp Unix da leitura
- `foto_base64`: Foto do usuário em base64 (opcional)

### **4. Configurar Sincronização de Usuários**

#### **Menu: Configurações → Sincronização → Usuários**

**Configurações necessárias:**

| Campo | Valor |
|-------|-------|
| **URL de Sincronização** | `http://SEU_SERVIDOR/presenca/api/culto/executar_sync.php` |
| **Método** | `POST` |
| **Content-Type** | `application/json` |
| **Horário de Sincronização** | `07:30` |
| **Intervalo** | `Diário` |

---

## ⚙️ **CONFIGURAÇÃO AVANÇADA**

### **1. Configurar Horários de Funcionamento**

#### **Menu: Configurações → Horários**

| Campo | Valor |
|-------|-------|
| **Início do Culto** | `07:30` |
| **Fim do Culto** | `08:30` |
| **Tolerância para Atraso** | `15 minutos` |

### **2. Configurar Reconhecimento Facial**

#### **Menu: Configurações → Reconhecimento**

| Campo | Valor |
|-------|-------|
| **Precisão** | `Alta (90%)` |
| **Velocidade** | `Rápida` |
| **Iluminação** | `Automática` |
| **Detecção de Rosto** | `Ativa` |

### **3. Configurar Logs e Monitoramento**

#### **Menu: Configurações → Logs**

| Campo | Valor |
|-------|-------|
| **Nível de Log** | `Informações` |
| **Retenção** | `30 dias` |
| **Envio de Logs** | `Ativo` |

---

## 🔄 **FLUXO DE SINCRONIZAÇÃO**

### **1. Preparação (07:00)**
```
Sistema → Dispositivo: Lista de usuários ativos
Dispositivo: Armazena dados dos usuários
```

### **2. Sincronização (07:30)**
```
Sistema → Dispositivo: Dados atualizados dos usuários
Dispositivo: Atualiza base de reconhecimento
```

### **3. Leitura Facial (07:30-08:30)**
```
Usuário → Dispositivo: Aproxima-se
Dispositivo → Sistema: Envia dados da leitura
Sistema: Processa e registra presença
```

---

## 📱 **CONFIGURAÇÃO VIA INTERFACE WEB**

### **1. Acessar Configurações**
1. Acesse: `http://IP_DO_DISPOSITIVO`
2. Login: `admin` / `senha_do_dispositivo`
3. Menu: `Configurações → API`

### **2. Configurar Endpoint de Leitura**
```json
{
  "endpoint": "http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json"
  },
  "timeout": 30,
  "retries": 3
}
```

### **3. Configurar Endpoint de Sincronização**
```json
{
  "endpoint": "http://SEU_SERVIDOR/presenca/api/culto/executar_sync.php",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json"
  },
  "schedule": "07:30",
  "interval": "daily"
}
```

---

## 🧪 **TESTE DA CONFIGURAÇÃO**

### **1. Teste Manual da API**
```bash
curl -X POST http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome_usuario": "João Silva",
    "ip_dispositivo": "10.144.198.50",
    "timestamp": 1726574400
  }'
```

### **2. Teste de Sincronização**
```bash
curl -X POST http://SEU_SERVIDOR/presenca/api/culto/executar_sync.php \
  -H "Content-Type: application/json" \
  -d '{"data": "2025-09-17"}'
```

### **3. Verificar Logs**
- Acesse: `http://SEU_SERVIDOR/presenca/logs/`
- Verifique arquivos: `leitura_facial_culto_YYYY-MM-DD.log`

---

## 🔧 **CONFIGURAÇÃO VIA SSH/TELNET**

### **1. Conectar ao Dispositivo**
```bash
ssh admin@IP_DO_DISPOSITIVO
# ou
telnet IP_DO_DISPOSITIVO
```

### **2. Configurar API**
```bash
# Configurar endpoint de leitura
set api.reading.url "http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php"
set api.reading.method "POST"
set api.reading.content_type "application/json"

# Configurar endpoint de sincronização
set api.sync.url "http://SEU_SERVIDOR/presenca/api/culto/executar_sync.php"
set api.sync.method "POST"
set api.sync.schedule "07:30"

# Salvar configurações
save config
```

---

## 📊 **MONITORAMENTO E LOGS**

### **1. Logs do Dispositivo**
- Acesse: `http://IP_DO_DISPOSITIVO/logs`
- Verifique: `facial_recognition.log`
- Verifique: `api_communication.log`

### **2. Logs do Sistema**
- Acesse: `http://SEU_SERVIDOR/presenca/logs/`
- Verifique: `leitura_facial_culto_YYYY-MM-DD.log`
- Verifique: `sync_culto_YYYY-MM-DD.log`

### **3. Status da Conexão**
- Acesse: `http://IP_DO_DISPOSITIVO/status`
- Verifique: `API Connection Status`
- Verifique: `Last Sync Time`

---

## ⚠️ **TROUBLESHOOTING**

### **Problema: API não responde**
**Solução:**
1. Verificar conectividade de rede
2. Verificar se o servidor está online
3. Verificar logs do dispositivo
4. Testar API manualmente

### **Problema: Usuários não sincronizam**
**Solução:**
1. Verificar se o dispositivo está ativo
2. Verificar se a sincronização está agendada
3. Verificar logs de sincronização
4. Executar sincronização manual

### **Problema: Reconhecimento não funciona**
**Solução:**
1. Verificar iluminação do ambiente
2. Verificar qualidade das fotos dos usuários
3. Verificar configurações de precisão
4. Recalibrar o dispositivo

---

## 📞 **SUPORTE TÉCNICO**

### **Informações para Suporte:**
- Modelo do dispositivo: SS3530
- Versão do firmware: [Verificar no dispositivo]
- IP do dispositivo: [IP configurado]
- IP do servidor: [IP do servidor]
- Logs de erro: [Copiar logs relevantes]

### **Contatos:**
- Suporte Técnico: [Contato do fabricante]
- Administrador do Sistema: [Seu contato]

---

## ✅ **CHECKLIST DE CONFIGURAÇÃO**

- [ ] Dispositivo conectado à rede
- [ ] API de leitura configurada
- [ ] API de sincronização configurada
- [ ] Horários de culto configurados
- [ ] Reconhecimento facial ativo
- [ ] Logs configurados
- [ ] Teste manual realizado
- [ ] Sincronização testada
- [ ] Monitoramento ativo

---

**🎯 Sistema configurado e pronto para uso!**
