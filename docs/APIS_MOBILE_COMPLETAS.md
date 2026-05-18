# 📱 APIs Mobile - Documentação Completa

## 📋 Resumo Executivo

Foram identificadas e atualizadas **11 APIs críticas** para suporte mobile (Bearer Token). Todas as APIs agora suportam autenticação via Bearer Token do aplicativo Flutter e são compatíveis com requisições web (form-data) e mobile (JSON).

---

## ✅ APIs Criadas/Atualizadas

### 1. Dependentes (`/api/dependentes/`)

#### ✅ `criar.php` - Criar Dependente
**Endpoint:** `POST /api/dependentes/criar.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

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

**Onde Usado:** Mobile - Tela de Cadastro de Dependentes

---

#### ✅ `editar.php` - Editar Dependente
**Endpoint:** `POST /api/dependentes/editar.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "id": 123,
    "nome": "Nome Atualizado",
    "parentesco": "Filho",
    "nascimento_dependente": "2010-01-15",
    "foto_base64": "base64_string..." // Opcional
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Dependente atualizado com sucesso"
}
```

**Onde Usado:** Mobile - Tela de Edição de Dependentes

---

#### ✅ `excluir.php` - Excluir Dependente
**Endpoint:** `POST /api/dependentes/excluir.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "id": 123
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Dependente excluído com sucesso"
}
```

**Onde Usado:** Mobile - Tela de Listagem de Dependentes

---

### 2. Culto (`/api/culto/`)

#### ✅ `listar_faltas_usuario.php` - Listar Faltas
**Endpoint:** `GET /api/culto/listar_faltas_usuario.php`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros (Query String):**
- `data_inicio` (opcional): Data inicial (YYYY-MM-DD)
- `data_fim` (opcional): Data final (YYYY-MM-DD)
- `filtro_status` (opcional): Filtrar por status ('pendente', 'aprovada', 'rejeitada', 'falta')

**Resposta:**
```json
{
    "status": "ok",
    "faltas": [
        {
            "data": "2026-01-20",
            "status": "falta",
            "motivo": null,
            "status_justificativa": null,
            "tipo_falta": "implícita"
        },
        {
            "data": "2026-01-19",
            "status": "falta",
            "motivo": "Motivo da falta",
            "status_justificativa": "pendente",
            "tipo_falta": "justificada"
        }
    ],
    "estatisticas": {
        "faltas": 5,
        "pendentes": 2,
        "aprovadas": 1,
        "rejeitadas": 0,
        "total": 8
    }
}
```

**Onde Usado:** Mobile - Tela de Faltas de Culto

---

#### ✅ `enviar_justificativa.php` - Enviar Justificativa
**Endpoint:** `POST /api/culto/enviar_justificativa.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "data_falta": "2026-01-20",
    "motivo": "Motivo da falta",
    "observacoes": "Observações adicionais"
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Justificativa enviada com sucesso! Aguarde a análise do administrador."
}
```

**Onde Usado:** Mobile - Tela de Envio de Justificativa

---

#### ✅ `historico_usuario.php` - Histórico de Presenças
**Endpoint:** `GET /api/culto/historico_usuario.php`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros (Query String):**
- `mes` (opcional): Mês no formato YYYY-MM (padrão: mês atual)
- `periodo` (opcional): 'mes', '3meses', '6meses', 'ano', 'todos' (padrão: 'mes')

**Resposta:**
```json
{
    "status": "ok",
    "presencas": [
        {
            "data": "2026-01-20",
            "horario_confirmacao": "19:00:00",
            "status": "presente",
            "tipo_confirmacao": "facial",
            "tem_culto": true,
            "tem_culto_programado": true,
            "justificativa": null
        },
        {
            "data": "2026-01-19",
            "horario_confirmacao": null,
            "status": "falta",
            "tipo_confirmacao": null,
            "tem_culto": true,
            "tem_culto_programado": true,
            "justificativa": {
                "id": 123,
                "motivo": "Motivo",
                "status": "pendente"
            }
        },
        {
            "data": "2026-01-18",
            "horario_confirmacao": null,
            "status": "sem-presenca",
            "tipo_confirmacao": null,
            "tem_culto": false,
            "tem_culto_programado": false,
            "justificativa": null
        }
    ],
    "estatisticas": {
        "total_presentes": 15,
        "total_atrasados": 2,
        "total_faltas": 6,
        "total_justificativas": 1
    },
    "periodo": {
        "inicio": "2026-01-01",
        "fim": "2026-01-31"
    },
    "dias_culto": [
        "2026-01-05",
        "2026-01-12",
        "2026-01-19",
        "2026-01-20",
        "2026-01-26"
    ]
}
```

**Campos Importantes para Calendário:**
- `dias_culto`: Lista de todos os dias com culto programado no período
- `tem_culto`: Indica se há culto (programado ou confirmado) naquele dia
- `tem_culto_programado`: Indica se é dia de culto programado (baseado na configuração)

**Onde Usado:** Mobile - Tela de Histórico de Presenças

---

### 3. Frota (`/api/frota/`)

#### ✅ `minha_utilizacao.php` - Minha Utilização Atual
**Endpoint:** `GET /api/frota/minha_utilizacao.php`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta:**
```json
{
    "status": "ok",
    "tem_veiculo": true,
    "utilizacao": {
        "id": 123,
        "id_veiculo": 5,
        "placa": "ABC-1234",
        "modelo": "Corolla",
        "marca": "Toyota",
        "cor": "Branco",
        "entidade": "Entidade A",
        "data_saida": "2026-01-20 10:00:00",
        "data_saida_formatada": "20/01/2026 10:00",
        "km_saida": 50000,
        "destino": "Destino",
        "motivo": "Motivo",
        "tempo_uso": "2h 30min"
    }
}
```

**Onde Usado:** Mobile - Dashboard - Card de Frota

---

#### ✅ `meu_historico.php` - Meu Histórico
**Endpoint:** `GET /api/frota/meu_historico.php`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros (Query String):**
- `estatisticas` (opcional): 1 para retornar apenas estatísticas
- `dias` (opcional): Filtrar últimos N dias
- `status` (opcional): Filtrar por status ('em_andamento', 'finalizada', 'cancelada')

**Resposta:**
```json
{
    "status": "ok",
    "utilizacoes": [
        {
            "id": 123,
            "placa": "ABC-1234",
            "modelo": "Corolla",
            "marca": "Toyota",
            "entidade": "Entidade A",
            "data_saida": "2026-01-20 10:00:00",
            "data_saida_formatada": "20/01/2026 10:00",
            "data_entrada": "2026-01-20 12:30:00",
            "data_entrada_formatada": "20/01/2026 12:30",
            "km_saida": 50000,
            "km_entrada": 50100,
            "km_percorrido": 100,
            "tempo_formatado": "2h 30min",
            "destino": "Destino",
            "motivo": "Motivo",
            "status": "finalizada"
        }
    ],
    "total": 1
}
```

**Com `estatisticas=1`:**
```json
{
    "status": "ok",
    "estatisticas": {
        "total_viagens": 10,
        "km_total": 500,
        "tempo_total": "25h 30min",
        "mes_atual": 3
    }
}
```

**Onde Usado:** Mobile - Tela de Histórico de Utilizações

---

#### ✅ `registrar_saida.php` - Registrar Saída
**Endpoint:** `POST /api/frota/registrar_saida.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "id_veiculo": 5,
    "id_entidade": 1,
    "km_saida": 50000,
    "destino": "Destino",
    "motivo": "Motivo",
    "observacoes_saida": "Observações",
    "foto_selfie": "data:image/jpeg;base64,...",
    "foto_km": "data:image/jpeg;base64,...",
    "foto_veiculo1": "data:image/jpeg;base64,...",
    "foto_veiculo2": "data:image/jpeg;base64,...",
    "foto_veiculo3": "data:image/jpeg;base64,...",
    "checklist": {
        "pneus_ok": 1,
        "farois_ok": 1,
        "documentos_ok": 1,
        "limpeza_ok": 1,
        "nivel_combustivel": "3/4",
        "avarias_encontradas": "Nenhuma"
    }
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Veículo retirado com sucesso",
    "utilizacao_id": 123
}
```

**Onde Usado:** Mobile - Tela de Retirada de Veículo

---

#### ✅ `registrar_entrada.php` - Registrar Entrada
**Endpoint:** `POST /api/frota/registrar_entrada.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "id_utilizacao": 123,
    "km_entrada": 50100,
    "observacoes_entrada": "Observações",
    "foto_selfie": "data:image/jpeg;base64,...",
    "foto_km": "data:image/jpeg;base64,...",
    "foto_veiculo1": "data:image/jpeg;base64,...",
    "foto_veiculo2": "data:image/jpeg;base64,...",
    "foto_veiculo3": "data:image/jpeg;base64,...",
    "checklist": {
        "pneus_ok": 1,
        "farois_ok": 1,
        "documentos_ok": 1,
        "limpeza_ok": 1,
        "nivel_combustivel": "1/2",
        "avarias_encontradas": "Arranhão na porta"
    }
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Veículo devolvido com sucesso"
}
```

**Onde Usado:** Mobile - Tela de Devolução de Veículo

---

### 4. Usuários (`/api/usuarios/`)

#### ✅ `atualizar_foto.php` - Atualizar Foto
**Endpoint:** `POST /api/usuarios/atualizar_foto.php`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "foto": "data:image/jpeg;base64,..."
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Foto atualizada com sucesso",
    "foto_base64": "base64_string..."
}
```

**Onde Usado:** Mobile - Tela de Perfil

---

## 🔐 Autenticação

Todas as APIs requerem autenticação via Bearer Token:

```
Authorization: Bearer {token}
```

O token é obtido através do endpoint:
```
POST /api/mobile/auth/login.php
```

---

## 📝 Padrão de Implementação

Todas as APIs seguem este padrão:

1. **Headers CORS** configurados
2. **Middleware mobile** para autenticação Bearer Token
3. **Suporte a JSON** (mobile) e **form-data** (web)
4. **Tratamento de OPTIONS** (CORS preflight)
5. **Respostas padronizadas** em JSON

---

## 🧪 Exemplos de Uso (Flutter)

### Criar Dependente
```dart
final response = await http.post(
  Uri.parse('https://presenca.aom.org.br/api/dependentes/criar.php'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: json.encode({
    'nome': 'Nome do Dependente',
    'parentesco': 'Filho',
    'nascimento_dependente': '2010-01-15',
    'foto_base64': fotoBase64, // Opcional
  }),
);
```

### Listar Faltas
```dart
final response = await http.get(
  Uri.parse('https://presenca.aom.org.br/api/culto/listar_faltas_usuario.php?data_inicio=2026-01-01&data_fim=2026-01-31'),
  headers: {'Authorization': 'Bearer $token'},
);
```

### Registrar Saída de Veículo
```dart
final response = await http.post(
  Uri.parse('https://presenca.aom.org.br/api/frota/registrar_saida.php'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: json.encode({
    'id_veiculo': 5,
    'id_entidade': 1,
    'km_saida': 50000,
    'destino': 'Destino',
    'motivo': 'Motivo',
    'foto_selfie': fotoSelfieBase64,
    'foto_km': fotoKmBase64,
  }),
);
```

---

## 📊 Resumo

| Módulo | API | Método | Status |
|--------|-----|--------|--------|
| **Dependentes** | criar.php | POST | ✅ |
| **Dependentes** | editar.php | POST | ✅ |
| **Dependentes** | excluir.php | POST | ✅ |
| **Culto** | listar_faltas_usuario.php | GET | ✅ |
| **Culto** | enviar_justificativa.php | POST | ✅ |
| **Culto** | historico_usuario.php | GET | ✅ |
| **Frota** | minha_utilizacao.php | GET | ✅ |
| **Frota** | meu_historico.php | GET | ✅ |
| **Frota** | registrar_saida.php | POST | ✅ |
| **Frota** | registrar_entrada.php | POST | ✅ |
| **Usuários** | atualizar_foto.php | POST | ✅ |

**Total:** 11 APIs críticas criadas/atualizadas

---

## ✅ Status Final

- ✅ Todas as APIs críticas têm middleware mobile
- ✅ Suporte a Bearer Token
- ✅ Suporte a JSON (mobile) e form-data (web)
- ✅ Headers CORS configurados
- ✅ Documentação completa criada

---

**Data:** 2026-01-XX  
**Status:** ✅ Concluído
