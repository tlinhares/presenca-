# 📚 Documentação Completa de APIs - Sistema de Presença AOM

## 📋 Resumo para Outra Sessão do Cursor

Este documento contém a documentação **COMPLETA** de todas as APIs do sistema, incluindo:
- Endpoint completo
- Método HTTP
- Parâmetros (query string, body, headers)
- Formato de resposta
- Onde é usado no sistema
- Comportamento detalhado
- Status de suporte mobile

---

## 🔐 Autenticação

Todas as APIs que requerem autenticação aceitam:
- **Web:** Sessão PHP (`$_SESSION['usuario_id']`)
- **Mobile:** Bearer Token (`Authorization: Bearer <token>`)

---

## 📦 MÓDULO: ALMOÇO

### 1. Verificar Horário Disponível

**Endpoint:** `GET /api/almoco/verificar_horario.php`

**Descrição:** Verifica se está no horário permitido para fazer reserva e retorna valores.

**Parâmetros Query:**
- `data` (opcional): Data da reserva no formato `YYYY-MM-DD` (padrão: hoje)
- `tipo` (opcional): Tipo de refeição - `presencial` ou `marmitex` (padrão: `presencial`)

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta Sucesso:**
```json
{
    "status": "sucesso",
    "mensagem": "Horário disponível para reserva",
    "data": "2026-01-20",
    "tipo": "presencial",
    "fora_do_horario": false,
    "hora_atual": "08:30",
    "horario_limite": "09:00",
    "valor_normal": 15.00,
    "valor_fora_horario": 30.00
}
```

**Resposta Erro:**
```json
{
    "status": "erro",
    "mensagem": "Você já possui uma reserva para esta data"
}
```

**Onde Usado:**
- Web: `reservas/almoco.php` - Antes de criar reserva
- Mobile: Tela de reserva - Verificar horário e valores

**Comportamento:**
- Valida formato de data
- Não permite datas passadas (exceto hoje)
- Limite de 30 dias no futuro
- Verifica se já existe reserva para a data
- Se for hoje, verifica se passou do horário limite
- Retorna valores baseados no grupo do usuário

**Status Mobile:** ✅ SIM

---

### 2. Status da Reserva

**Endpoint:** `GET /api/almoco/status_reserva.php`

**Descrição:** Retorna o status da reserva do dia atual (se já reservou e se está fora do horário).

**Parâmetros:** Nenhum

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "reservou_hoje": false,
    "hora_excedida": false,
    "hora_atual": "08:30",
    "hora_limite": "09:00"
}
```

**Onde Usado:**
- Web: `reservas/almoco.php` - Atualizar texto do botão
- Mobile: Tela inicial - Mostrar status da reserva

**Comportamento:**
- Verifica se usuário já reservou para hoje
- Compara hora atual com horário limite configurado
- Não bloqueia reserva, apenas informa

**Status Mobile:** ✅ SIM

---

### 3. Criar Reserva

**Endpoint:** `POST /api/almoco/reservar.php`

**Descrição:** Cria uma reserva de almoço para o usuário logado.

**Body (JSON ou form-data):**
```json
{
    "data": "2026-01-20",
    "fora_do_horario": false
}
```

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)
- `Content-Type: application/json` (mobile) ou `application/x-www-form-urlencoded` (web)

**Resposta Sucesso:**
```json
{
    "status": "sucesso",
    "mensagem": "Reserva criada com sucesso",
    "reserva": {
        "id": 123,
        "data": "2026-01-20",
        "valor": 15.00
    }
}
```

**Resposta Erro:**
```json
{
    "status": "erro",
    "mensagem": "Você já possui uma reserva para esta data"
}
```

**Onde Usado:**
- Web: `reservas/almoco.php` - Botão "Reservar meu almoço"
- Mobile: Tela de reserva - Criar reserva

**Comportamento:**
- Valida se já existe reserva para a data
- Verifica horário limite se for hoje
- Calcula valor baseado em `fora_do_horario`
- Cria registro na tabela `reservas_almoco`
- Envia notificação WhatsApp se configurado

**Status Mobile:** ✅ SIM

---

### 4. Cancelar Reserva Própria

**Endpoint:** `POST /api/almoco/cancelar_reserva_propria.php`

**Descrição:** Cancela a reserva do usuário logado para o dia atual.

**Body (JSON ou form-data):**
```json
{
    "data": "2026-01-20"
}
```

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta Sucesso:**
```json
{
    "status": "sucesso",
    "mensagem": "Reserva cancelada com sucesso"
}
```

**Onde Usado:**
- Web: `reservas/almoco.php` - Botão "Cancelar Reserva"
- Mobile: Tela de reserva - Cancelar reserva

**Comportamento:**
- Verifica se reserva existe e pertence ao usuário
- Remove registro da tabela `reservas_almoco`
- Envia notificação de cancelamento se configurado

**Status Mobile:** ✅ SIM

---

### 5. Listar Reservas do Usuário

**Endpoint:** `GET /api/almoco/listar_reservas_usuario.php`

**Descrição:** Lista todas as reservas do usuário logado em um período.

**Parâmetros Query:**
- `data_inicio` (opcional): Data inicial no formato `YYYY-MM-DD` (padrão: primeiro dia do mês)
- `data_fim` (opcional): Data final no formato `YYYY-MM-DD` (padrão: último dia do mês)

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "ok",
    "reservas": [
        {
            "id": 123,
            "data": "2026-01-20",
            "valor": 15.00,
            "fora_do_horario": false
        }
    ]
}
```

**Onde Usado:**
- Web: Histórico de reservas
- Mobile: Tela de histórico

**Comportamento:**
- Busca apenas reservas do usuário principal (não dependentes)
- Filtra por período especificado
- Ordena por data decrescente

**Status Mobile:** ✅ SIM

---

### 6. Verificar Horário para Reserva Adicional

**Endpoint:** `GET /api/almoco/verificar_horario_adicional.php`

**Descrição:** Verifica horário e valores para reserva adicional (dependente).

**Parâmetros Query:**
- `id_dependente` (obrigatório): ID do dependente
- `data` (opcional): Data da reserva (padrão: hoje)
- `tipo` (opcional): `presencial` ou `marmitex` (padrão: `presencial`)

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta Sucesso:**
```json
{
    "status": "ok",
    "mensagem": "Reserva pode ser feita",
    "dependente": {
        "id": 5,
        "nome": "João Silva Filho",
        "cobrar": 0
    },
    "valores": {
        "valor_refeicao": 15.00,
        "valor_marmitex": 0.00,
        "fora_do_horario": false
    },
    "horario": {
        "hora_atual": "08:30",
        "horario_limite": "09:00",
        "fora_do_horario": false
    }
}
```

**Onde Usado:**
- Web: Modal de reserva adicional
- Mobile: Tela de reserva para dependente

**Comportamento:**
- Valida se dependente pertence ao usuário
- Verifica se já existe reserva para o dependente na data
- Calcula valores baseado em idade do dependente (cobrar = 0 se maior de 12 anos)
- Verifica horário limite se for hoje

**Status Mobile:** ✅ SIM

---

### 7. Criar Reserva Adicional

**Endpoint:** `POST /api/almoco/reservar_adicional.php`

**Descrição:** Cria reserva adicional para um dependente.

**Body (JSON ou form-data):**
```json
{
    "data": "2026-01-20",
    "quantidade": 1,
    "detalhe": "",
    "tipo": "presencial",
    "dependente": 5,
    "fora_do_horario": false
}
```

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta Sucesso:**
```json
{
    "status": "sucesso",
    "mensagem": "Reserva adicional criada com sucesso"
}
```

**Onde Usado:**
- Web: Modal de reserva adicional
- Mobile: Tela de reserva para dependente

**Comportamento:**
- Valida dependente pertence ao usuário
- Verifica se marmitex está habilitado (se tipo = marmitex)
- Valida se já existe reserva para o dependente na data
- Cria registro na tabela `reservas_adicionais`
- Calcula valor baseado em idade e tipo

**Status Mobile:** ✅ SIM

---

### 8. Listar Reservas Adicionais do Usuário

**Endpoint:** `GET /api/almoco/listar_reservas_adicionais_usuario.php`

**Descrição:** Lista reservas adicionais do usuário em um período.

**Parâmetros Query:**
- `data_inicio` (opcional): Data inicial (padrão: primeiro dia do mês)
- `data_fim` (opcional): Data final (padrão: último dia do mês)
- `dependente` (opcional): Filtrar por dependente específico

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "ok",
    "reservas": [
        {
            "id": 456,
            "data": "2026-01-20",
            "dependente_id": 5,
            "dependente_nome": "João Silva Filho",
            "tipo": "presencial",
            "valor": 15.00
        }
    ]
}
```

**Onde Usado:**
- Web: Histórico de reservas adicionais
- Mobile: Tela de histórico

**Status Mobile:** ✅ SIM

---

### 9. Excluir Reserva Adicional

**Endpoint:** `POST /api/almoco/excluir_reserva_adicional.php`

**Descrição:** Exclui uma reserva adicional.

**Body (JSON ou form-data):**
```json
{
    "id": 456
}
```

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "sucesso",
    "mensagem": "Reserva adicional excluída com sucesso"
}
```

**Onde Usado:**
- Web: Lista de reservas adicionais
- Mobile: Tela de histórico

**Comportamento:**
- Valida se reserva pertence ao usuário
- Remove registro da tabela `reservas_adicionais`

**Status Mobile:** ✅ SIM

---

### 10. Editar Reserva Adicional

**Endpoint:** `POST /api/almoco/editar_reserva_adicional.php`

**Descrição:** Edita uma reserva adicional existente.

**Body (JSON ou form-data):**
```json
{
    "id": 456,
    "data": "2026-01-21",
    "tipo": "marmitex",
    "quantidade": 2
}
```

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "sucesso",
    "mensagem": "Reserva adicional atualizada com sucesso"
}
```

**Onde Usado:**
- Web: Edição de reserva adicional
- Mobile: Tela de edição

**Comportamento:**
- Valida se reserva pertence ao usuário
- Verifica se marmitex está habilitado (se tipo = marmitex)
- Atualiza registro na tabela `reservas_adicionais`

**Status Mobile:** ✅ SIM

---

### 11. Listar Tipos de Adicionais

**Endpoint:** `GET /api/almoco/listar_adicionais.php`

**Descrição:** Lista os tipos de adicionais disponíveis (entidades).

**Parâmetros:** Nenhum

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "ok",
    "adicionais": [
        {
            "id": 1,
            "nome": "Entidade 1",
            "ativo": true
        }
    ]
}
```

**Onde Usado:**
- Web: Seleção de entidade na reserva adicional
- Mobile: Tela de reserva adicional

**Status Mobile:** ✅ SIM

---

## 👥 MÓDULO: DEPENDENTES

### 1. Listar Dependentes

**Endpoint:** `GET /api/dependentes/listar.php`

**Descrição:** Lista todos os dependentes do usuário logado.

**Parâmetros Query:**
- `usuario_id` (opcional): ID do usuário (padrão: usuário logado)

**Headers:**
- `Authorization: Bearer <token>` (mobile) ou Cookie com sessão (web)

**Resposta:**
```json
{
    "status": "ok",
    "dados": [
        {
            "id": 5,
            "nome": "João Silva Filho",
            "parentesco": "Filho",
            "data_nascimento": "2010-05-15",
            "idade": 15,
            "foto_base64": null
        }
    ]
}
```

**Onde Usado:**
- Web: Lista de dependentes
- Mobile: Tela de dependentes

**Comportamento:**
- Busca apenas dependentes ativos do usuário
- Calcula idade automaticamente
- Ordena por nome

**Status Mobile:** ✅ SIM

---

## 🔐 MÓDULO: AUTENTICAÇÃO MOBILE

### 1. Login

**Endpoint:** `POST /api/mobile/auth/login.php`

**Descrição:** Realiza login e retorna tokens de autenticação.

**Body (JSON):**
```json
{
    "email": "usuario@exemplo.com",
    "senha": "senha123"
}
```

**Headers:**
- `Content-Type: application/json`

**Resposta Sucesso:**
```json
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "expires_in": 86400,
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "nome": "João Silva",
            "email": "usuario@exemplo.com",
            "categoria": "admin"
        }
    },
    "message": "Login realizado com sucesso"
}
```

**Resposta Erro:**
```json
{
    "success": false,
    "message": "Credenciais inválidas"
}
```

**Onde Usado:**
- Mobile: Tela de login

**Comportamento:**
- Valida email e senha
- Verifica se usuário está ativo
- Gera tokens JWT (access + refresh)
- Atualiza último login

**Status Mobile:** ✅ SIM

---

### 2. Renovar Token

**Endpoint:** `POST /api/mobile/auth/refresh.php`

**Descrição:** Renova o access token usando o refresh token.

**Headers:**
- `Authorization: Bearer <refresh_token>`

**Resposta:**
```json
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "expires_in": 86400
    }
}
```

**Onde Usado:**
- Mobile: Quando access token expira

**Status Mobile:** ✅ SIM

---

### 3. Logout

**Endpoint:** `POST /api/mobile/auth/logout.php`

**Descrição:** Invalida o token atual.

**Headers:**
- `Authorization: Bearer <token>`

**Resposta:**
```json
{
    "success": true,
    "message": "Logout realizado com sucesso"
}
```

**Onde Usado:**
- Mobile: Botão de logout

**Status Mobile:** ✅ SIM

---

## 📊 Resumo de Status

### APIs com Suporte Mobile Completo: 11
- ✅ `verificar_horario.php`
- ✅ `status_reserva.php`
- ✅ `reservar.php`
- ✅ `cancelar_reserva_propria.php`
- ✅ `listar_reservas_usuario.php`
- ✅ `verificar_horario_adicional.php`
- ✅ `reservar_adicional.php`
- ✅ `listar_reservas_adicionais_usuario.php`
- ✅ `excluir_reserva_adicional.php`
- ✅ `editar_reserva_adicional.php`
- ✅ `listar_adicionais.php`
- ✅ `listar.php` (dependentes)
- ✅ `login.php`, `refresh.php`, `logout.php` (auth)

### APIs que Precisam Middleware: ~190+

Consulte `docs/MAPEAMENTO_COMPLETO_APIS.md` para lista completa.

---

**Data:** 2026-01-XX  
**Versão:** 1.0
