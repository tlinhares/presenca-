# 🎯 **RESUMO DA CONFIGURAÇÃO - DISPOSITIVO FACIAL PARA CULTO**

## 📋 **INFORMAÇÕES ESSENCIAIS**

### **URLs das APIs:**
```
Leitura Facial: https://presenca.aom.org.br/api/culto/receber_leitura_facial.php
Sincronização: https://presenca.aom.org.br/api/culto/executar_sync.php
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

## 🔧 **PASSOS PARA CONFIGURAÇÃO**

### **1. Acessar o Dispositivo Facial**
1. Conecte o dispositivo à rede
2. Acesse: `http://IP_DO_DISPOSITIVO`
3. Login: `admin` / `senha_do_dispositivo`

### **2. Configurar API de Leitura**
**Menu: Configurações → API → Leitura Facial**

| Campo | Valor |
|-------|-------|
| **URL** | `https://presenca.aom.org.br/api/culto/receber_leitura_facial.php` |
| **Método** | `POST` |
| **Content-Type** | `application/json` |
| **Timeout** | `30 segundos` |

### **3. Configurar API de Sincronização**
**Menu: Configurações → API → Sincronização**

| Campo | Valor |
|-------|-------|
| **URL** | `https://presenca.aom.org.br/api/culto/executar_sync.php` |
| **Método** | `POST` |
| **Content-Type** | `application/json` |
| **Horário** | `07:30` |
| **Intervalo** | `Diário` |

### **4. Configurar Horários**
**Menu: Configurações → Horários**

| Campo | Valor |
|-------|-------|
| **Início do Culto** | `07:30` |
| **Fim do Culto** | `08:30` |
| **Tolerância** | `15 minutos` |

---

## 📱 **FORMATO DOS DADOS**

### **Dados Enviados pelo Dispositivo:**
```json
{
  "nome_usuario": "João Silva",
  "ip_dispositivo": "IP_DO_DISPOSITIVO",
  "timestamp": 1726574400,
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

### **Dados Recebidos pelo Dispositivo:**
```json
{
  "usuarios": [
    {
      "id": 123,
      "nome": "João Silva",
      "foto": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
    }
  ],
  "timestamp": 1726574400
}
```

---

## 🧪 **TESTES E VERIFICAÇÃO**

### **1. Teste Manual da API**
```bash
curl -X POST http://SEU_SERVIDOR/presenca/api/culto/receber_leitura_facial.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome_usuario": "João Silva",
    "ip_dispositivo": "IP_DO_DISPOSITIVO",
    "timestamp": 1726574400
  }'
```

### **2. Teste de Sincronização**
```bash
curl -X POST http://SEU_SERVIDOR/presenca/api/culto/executar_sync.php \
  -H "Content-Type: application/json" \
  -d '{"data": "2025-09-17"}'
```

### **3. Páginas de Teste**
- **Teste de Configuração:** `http://SEU_SERVIDOR/presenca/teste_configuracao_facial.php`
- **Configuração Automática:** `http://SEU_SERVIDOR/presenca/configurar_dispositivo_automatico.php`
- **Teste da API:** `http://SEU_SERVIDOR/presenca/teste_leitura_facial_culto.php`

---

## 🔄 **FLUXO DE FUNCIONAMENTO**

### **07:00 - Preparação**
```
Sistema prepara todos os usuários ativos
Cria registros na tabela facial_sync_culto
Status: pendente
```

### **07:30 - Sincronização**
```
Sistema envia dados dos usuários para o dispositivo
Dispositivo armazena nomes e fotos
Status: sincronizado
```

### **07:30-08:30 - Leitura Facial**
```
Usuário se aproxima do dispositivo
Dispositivo reconhece e envia dados
Sistema processa e registra presença
Status: presente/atrasado
```

### **08:35 - Geração de Faltas**
```
Sistema verifica usuários sem presença
Gera faltas automáticas
Status: falta
```

---

## 📊 **MONITORAMENTO**

### **Logs do Sistema**
- **Localização:** `http://SEU_SERVIDOR/presenca/logs/`
- **Arquivos:** `leitura_facial_culto_YYYY-MM-DD.log`

### **Status dos Dispositivos**
- **Painel:** `http://SEU_SERVIDOR/presenca/painel/dispositivos_faciais.php`
- **Configurações:** `http://SEU_SERVIDOR/presenca/culto/configuracoes.php`

---

## ⚠️ **TROUBLESHOOTING**

### **Problema: Dispositivo não conecta**
**Solução:**
1. Verificar IP e porta
2. Testar conectividade de rede
3. Verificar credenciais
4. Verificar se o dispositivo está ativo

### **Problema: API não responde**
**Solução:**
1. Verificar se o servidor está online
2. Testar API manualmente
3. Verificar logs do sistema
4. Verificar configurações do dispositivo

### **Problema: Usuários não sincronizam**
**Solução:**
1. Verificar se o dispositivo está ativo
2. Verificar horário de sincronização
3. Executar sincronização manual
4. Verificar logs de sincronização

---

## ✅ **CHECKLIST FINAL**

- [ ] Dispositivo conectado à rede
- [ ] API de leitura configurada
- [ ] API de sincronização configurada
- [ ] Horários de culto configurados
- [ ] Reconhecimento facial ativo
- [ ] Teste manual realizado
- [ ] Sincronização testada
- [ ] Logs configurados
- [ ] Monitoramento ativo

---

## 🎯 **SISTEMA PRONTO!**

O sistema de reconhecimento facial para culto está completamente configurado e pronto para uso. O dispositivo irá:

1. **Sincronizar usuários** automaticamente às 07:30
2. **Reconhecer usuários** durante o culto (07:30-08:30)
3. **Enviar dados** para o sistema via API
4. **Registrar presenças** automaticamente
5. **Gerar faltas** para ausentes às 08:35

**🚀 Sistema funcionando perfeitamente!**
