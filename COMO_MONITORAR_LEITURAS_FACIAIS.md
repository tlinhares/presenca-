# 📊 **COMO MONITORAR LEITURAS FACIAIS EM TEMPO REAL**

## 🎯 **CONFIRMAÇÃO: OS DADOS ESTÃO SENDO ENVIADOS!**

Baseado nos logs do sistema, **confirmamos que o dispositivo facial ESTÁ enviando dados** para o sistema:

```
[2025-09-17 17:30:21] Recebida leitura facial: Tiago Linhares Tavares do dispositivo 10.144.129.78
[2025-09-17 17:30:21] Nova presença registrada para Tiago Linhares Tavares: atrasado às 17:30:21
```

---

## 🔍 **FORMAS DE MONITORAR**

### **1. 📱 MONITOR WEB (Recomendado)**

**Acesse:** `https://presenca.aom.org.br/monitor_leitura_facial.php`

**Recursos:**
- ✅ Status dos dispositivos em tempo real
- ✅ Logs atualizados automaticamente a cada 5 segundos
- ✅ Últimas presenças registradas
- ✅ Interface visual amigável

### **2. 📋 LOGS DIRETOS**

**Localização:** `/var/www/html/presenca/logs/leitura_facial_culto_YYYY-MM-DD.log`

**Comando para ver logs em tempo real:**
```bash
cd /var/www/html/presenca
tail -f logs/leitura_facial_culto_$(date +%Y-%m-%d).log
```

**Comando para ver últimas 20 linhas:**
```bash
cd /var/www/html/presenca
tail -20 logs/leitura_facial_culto_$(date +%Y-%m-%d).log
```

### **3. 🖥️ MONITOR VIA TERMINAL**

**Script dedicado:**
```bash
cd /var/www/html/presenca
php monitor_terminal.php
```

**Recursos:**
- ✅ Logs coloridos por tipo
- ✅ Atualização em tempo real
- ✅ Timestamp de cada entrada

### **4. 🗄️ BANCO DE DADOS**

**Verificar presenças registradas:**
```sql
SELECT pc.*, u.nome, df.nome as dispositivo
FROM presencas_culto pc
JOIN usuarios u ON pc.id_usuario = u.id
LEFT JOIN dispositivos_faciais df ON pc.dispositivo_ip = df.ip
ORDER BY pc.data DESC, pc.horario_confirmacao DESC
LIMIT 10;
```

---

## 📊 **TIPOS DE LOGS**

### **✅ SUCESSO (Verde)**
```
[2025-09-17 17:30:21] Nova presença registrada para Tiago Linhares Tavares: atrasado às 17:30:21
[2025-09-17 17:33:01] Presença atualizada para Tiago Linhares Tavares: atrasado às 17:33:01
```

### **⚠️ RECEBIDO (Amarelo)**
```
[2025-09-17 17:30:21] Recebida leitura facial: Tiago Linhares Tavares do dispositivo 10.144.129.78
```

### **❌ ERRO (Vermelho)**
```
[2025-09-17 17:29:18] ERRO: Usuário não encontrado: João Silva
[2025-09-17 17:22:38] ERRO: Dispositivo não encontrado ou inativo: 10.144.198.50
```

---

## 🔧 **CONFIGURAÇÃO DO DISPOSITIVO**

### **Configuração Atual (Correta):**
```
Modo de operação: POST eventos
Ativar: ✅
ReportPicture: ✅
Endereço de IP: presenca.aom.org.br
Porta: 80
Path do servidor: /api/culto/receber_leitura_facial.php
HTTPS: ✅
```

### **URL Completa:**
```
https://presenca.aom.org.br/api/culto/receber_leitura_facial.php
```

---

## 🧪 **TESTE MANUAL**

### **Via cURL:**
```bash
curl -X POST https://presenca.aom.org.br/api/culto/receber_leitura_facial.php \
  -H "Content-Type: application/json" \
  -d '{
    "nome_usuario": "Tiago Linhares Tavares",
    "ip_dispositivo": "10.144.129.78",
    "timestamp": 1726574400
  }'
```

### **Resposta Esperada:**
```json
{
  "status": "success",
  "message": "Presença registrada com sucesso",
  "data": {
    "usuario": "Tiago Linhares Tavares",
    "data": "2025-09-17",
    "horario": "17:30:21",
    "status": "atrasado",
    "dispositivo": "MESA TI",
    "acao": "registrada"
  }
}
```

---

## 📈 **ESTATÍSTICAS**

### **Dados dos Logs de Hoje:**
- ✅ **Leituras recebidas:** Múltiplas
- ✅ **Presenças registradas:** Funcionando
- ✅ **Dispositivo ativo:** 10.144.129.78 (MESA TI)
- ✅ **Usuários reconhecidos:** Tiago Linhares Tavares, Neize Pereira da Silva Rodrigues

---

## 🎯 **CONCLUSÃO**

**✅ O sistema está funcionando perfeitamente!**

1. **Dispositivo facial** está enviando dados
2. **API** está recebendo e processando
3. **Logs** estão sendo gerados
4. **Presenças** estão sendo registradas
5. **Monitoramento** está disponível

**🚀 Sistema de reconhecimento facial para culto está operacional!**
