# Documentação Completa de APIs — Sistema de Intranet AOM (Mobile)

> **Para a IA que vai gerar o app:** Este documento descreve exatamente o contrato de cada endpoint mobile do sistema de presença. Siga as **convenções gerais** abaixo antes de implementar telas — elas valem para todos os endpoints e evitam bugs comuns (status code 200 em erro, dois formatos de resposta diferentes, "ok" vs "sucesso", etc.).
>
> Última revisão: 2026-06-16. Servidor de referência: `https://presenca.aom.org.br`.

---

## 1. Convenções gerais

### 1.1 Base URL

```
https://presenca.aom.org.br
```

Todos os caminhos abaixo são relativos a essa base. Ex.: `/api/almoco/reservar.php` → `https://presenca.aom.org.br/api/almoco/reservar.php`.

### 1.2 Autenticação

O backend usa **JWT (HS256)** stateless, fornecido no header `Authorization`:

```
Authorization: Bearer <access_token>
```

- **Access token**: 24h de validade (`exp` no payload JWT).
- **Refresh token**: 7 dias.
- O JWT é assinado com `JWT_SECRET_KEY` do `.env`; o app não precisa decodificar — só armazenar e reenviar.
- Não há tabela de tokens no banco; logout do servidor é "best-effort" (o app deve descartar localmente).
- Os endpoints leem o usuário autenticado de `$_SESSION` populado pelo middleware. Para o app isso é transparente.

**Fluxo recomendado:** `POST /api/mobile/auth/login.php` → guardar `token` e `refresh_token` → em todos os requests enviar `Authorization: Bearer <token>` → se a resposta indicar token expirado, chamar `POST /api/mobile/auth/refresh.php`.

### 1.3 Dois formatos de resposta

O backend tem **dois formatos diferentes** convivendo. O app precisa tratar os dois:

**Formato A — MobileResponse (usado APENAS pelos 3 endpoints de auth):**

```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "timestamp": "2026-06-16T10:00:00-04:00",
  "data": { ... }
}
```

Em erro:

```json
{
  "success": false,
  "message": "Credenciais inválidas",
  "timestamp": "2026-06-16T10:00:00-04:00"
}
```

HTTP status pode ser 401, 400, 500 nesses casos.

**Formato B — LEGADO (usado por TODOS os 27 endpoints de negócio):**

```json
{ "status": "ok",     "mensagem": "..." , ... }   // sucesso
{ "status": "sucesso","mensagem": "..." , ... }   // sucesso (alguns endpoints)
{ "status": "erro",   "mensagem": "..."  }        // erro
```

⚠️ **HTTP status SEMPRE 200**, mesmo em erro. O app **precisa olhar o campo `status` do JSON**, não o código HTTP. E precisa aceitar tanto `"ok"` quanto `"sucesso"` como sucesso (a tabela em §3 indica qual cada endpoint usa).

Alguns endpoints retornam só os dados crus, sem `status` (ex.: `status_reserva.php`, `listar_adicionais.php`). Estão explicitamente marcados.

### 1.4 Content-Type aceitos

Endpoints que recebem dados aceitam **JSON ou form-data**, exceto onde indicado:

- **JSON** (recomendado para o app): `Content-Type: application/json` + body JSON.
- **form-data** (compatibilidade com web): `application/x-www-form-urlencoded` ou `multipart/form-data`.

Endpoints **só JSON** (rejeitam form-data): `frota/registrar_saida.php`, `frota/registrar_entrada.php`.

### 1.5 CORS

Todos os endpoints respondem CORS:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

Requests `OPTIONS` recebem HTTP 200 vazio (preflight tratado).

### 1.6 Formatos de data

- **Data**: `YYYY-MM-DD` em todas as APIs (ex.: `2026-06-16`).
- **Data com hora** (resposta): `YYYY-MM-DD HH:MM:SS` (cru do MySQL) ou `dd/mm/yyyy HH:MM` (formatadas para exibição — algumas APIs já entregam as duas, com sufixo `_formatada`).
- **Hora**: `HH:MM` (24h).

### 1.7 Fotos / base64

Endpoints que aceitam imagem usam **base64**:

- Prefixo `data:image/...;base64,` é opcional — o backend remove se presente.
- Em algumas respostas (perfil, dependentes), a foto vem como base64 **sem prefixo**. O app deve adicionar `data:image/jpeg;base64,` ao renderizar.
- `dependentes/editar.php` redimensiona automaticamente fotos > 1 MB para 800×600 JPEG q=80.

### 1.8 Categorias de usuário

Algumas APIs verificam `$_SESSION['usuario_categoria']`:
- `admin` — acesso total.
- `funcionario`, `funcionario_culto`, etc. — restrições caso a caso (anotadas por endpoint).

### 1.9 Escopo do app — o que mostrar e o que NÃO mostrar

O app **só deve expor as funcionalidades que têm endpoint mobile listado abaixo**. Não é "admin vê tudo" — admin no app só vê o que existe como API mobile. Em particular:

- ✅ **Mobile**: almoço (incl. dependentes), culto/justificativas, calendário, frota, perfil.
- ❌ **Web-only (NÃO criar tela no app)**: módulo de **estoque** completo (produtos, requisições, alertas, inventário), painéis administrativos (`/painel/*`), gestão de usuários/grupos, gestão de reservas de outros usuários, configurações do sistema, WhatsApp/notificações, frota — partes administrativas (cadastro de veículos/entidades/departamentos, manutenção).
- Se o app precisar de uma tela administrativa nova, ela exige que um endpoint mobile correspondente seja criado primeiro (não invente request).

### 1.10 Gating de módulos no app (qual menu mostrar)

O backend **não tem endpoint** que devolve "lista de módulos liberados". Para evitar que o app esconda módulos para quem deveria ver, use estas regras simples baseadas no objeto `user` que o login devolve:

| Módulo no app | Mostra quando | Não mostra quando |
|---|---|---|
| **Almoço** (próprio + dependentes) | sempre | nunca |
| **Frota** | sempre (qualquer usuário ativo pode retirar veículo) | nunca |
| **Perfil** | sempre | nunca |
| **Culto** (calendário, justificativas, histórico, frequência) | `user.culto === 1` **OU** `user.categoria === "admin"` | `user.culto === 0` E `user.categoria !== "admin"` |
| **Estoque** | nunca (é web-only — ver §1.9) | sempre |
| **Configurações administrativas** (gestão de usuários, painel, etc.) | nunca (web-only) | sempre |

⚠️ **Não invente checagens** como `categoria === "culto"` ou `categoria === "funcionario_culto"` — essas categorias **não existem**. As únicas categorias do sistema são `"admin"` e `"funcionario"`. Quem participa do culto é definido pelo flag `user.culto`, não pela categoria.

**Regras-resumo em pseudo-código:**
```javascript
const podeVerCulto   = user.culto === 1 || user.categoria === "admin";
const podeVerAlmoco  = true;
const podeVerFrota   = true;
const podeVerPerfil  = true;
```

### 1.11 Dropdowns / lookups (origem dos IDs)

Para cada campo `id_*` num body, o app precisa popular o dropdown a partir de um endpoint de listagem. Use esta tabela como referência:

| Campo no body | Origem (GET) | Resposta tem | Observação |
|---|---|---|---|
| `id_veiculo` (frota/registrar_saida) | `/api/frota/listar_veiculos.php?status=disponivel` | `veiculos[].id` | Filtre por `status: "disponivel"` para mostrar só os que dá pra retirar. |
| `id_entidade` (frota/registrar_saida) | `/api/frota/listar_entidades.php` | `entidades[].id` | **Unidade de negócio** (Religiosa, Educação, Loja). É a "casa" que vai assumir o trajeto. §7.6 |
| `id_departamento` (frota/registrar_saida) | `/api/frota/departamentos.php?apenas_ativos=1` | `departamentos[].id` | **Setor** (TI, RH, Contabilidade, AFAM, Engenharia, etc.). É o time/área do solicitante. §7.7 |
| `id_utilizacao` (frota/registrar_entrada) | `/api/frota/minha_utilizacao.php` | `utilizacao.id` | É sempre a utilização em andamento do próprio usuário. |
| `id_dependente` / `dependente` (almoço adicional) | `/api/dependentes/listar.php` | `dados[].id` | |
| `entidade_id` (almoço/reservar_departamento — admin) | `/api/almoco/listar_entidades.php` | `entidades[].id` | **Mesmas Religiosa/Educação/Loja**. No almoço, o web rotula esse dropdown como "Departamento" mas os dados são da tabela `entidade`. Ver §3.12/§3.13. |
| `usuario_id` (dependentes/criar — admin) | _Sem endpoint mobile_ | — | Não há listagem de usuários no mobile. Para o app, use o próprio usuário logado (`$session.user.id` retornado no login). |

⚠️ **Nunca use input de texto para esses IDs.** Eles vêm de listas; se o app está pedindo o número, falta dropdown.

🔴 **"Departamento" no almoço ≠ "Departamento" na frota.** O sistema usa o mesmo rótulo para coisas diferentes:
- **Almoço, tela "Reservas de Departamento" (admin)**: o dropdown chamado "Departamento" lista **entidades** (`Religiosa` / `Educação` / `Loja`). Use `/api/almoco/listar_entidades.php`. **NÃO use** `frota/departamentos.php` aqui.
- **Frota, retirada de veículo**: tela tem **dois dropdowns separados**:
  - **Entidade** (`listar_entidades.php`) — `Religiosa`, `Educação`, `Loja`.
  - **Departamento** (`departamentos.php`) — `TI`, `RH`, `Contabilidade`, `Engenharia`, `AFAM`, `MCA`, `Desbravadores`, `Arquitetura`, `Presidencia`, `Remessa`, `Departamento Pessoal`.

Os dois são **obrigatórios** no body de `registrar_saida.php`. Se o app só está mostrando um dropdown, é bug do app — gere **dois**. Sugestão de label: "Entidade" e "Departamento", iguais ao web.

---

## 2. Módulo: Autenticação Mobile

Endpoints sob `/api/mobile/auth/`. **Estes 3 usam o formato A (MobileResponse).**

### 2.1 `POST /api/mobile/auth/login.php`

Realiza login e devolve par access/refresh.

**Body (JSON):**
```json
{ "email": "fulano@aom.org.br", "senha": "minhasenha" }
```

**Sucesso (HTTP 200):**
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "timestamp": "2026-06-16T10:00:00-04:00",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 86400,
    "token_type": "Bearer",
    "user": {
      "id": 123,
      "nome": "Fulano de Tal",
      "email": "fulano@aom.org.br",
      "categoria": "funcionario",
      "culto": 1
    }
  }
}
```

**Campos do `user`:**
- `id` (int), `nome`, `email` — identificação.
- `categoria` (enum string) — `"admin"` ou `"funcionario"`. **Não há `"culto"`/`"funcionario_culto"`** — não tente gatear por isso.
- `culto` (int) — `1` se o usuário participa do culto, `0` se não. **Use esse campo para decidir se mostra o módulo culto no menu** (ver §1.10).

**Erros possíveis (HTTP 401/400):** `Credenciais inválidas`, `Usuário inativo`, `Email e senha são obrigatórios`.

**Observações:** o app deve armazenar `token`, `refresh_token` E o objeto `user` com segurança (Keychain/Keystore). `expires_in` é em segundos.

---

### 2.2 `POST /api/mobile/auth/refresh.php`

Renova o access token usando o refresh.

**Header:** `Authorization: Bearer <refresh_token>` (mande o refresh aqui, não o access).

**Sucesso:**
```json
{
  "success": true,
  "message": "Token renovado",
  "timestamp": "...",
  "data": {
    "token": "<novo_access>",
    "refresh_token": "<novo_refresh>",
    "expires_in": 86400,
    "token_type": "Bearer"
  }
}
```

**Erros (HTTP 401):** refresh inválido/expirado, usuário desativado.

---

### 2.3 `POST /api/mobile/auth/logout.php`

**Header:** `Authorization: Bearer <access_token>`.

**Sucesso:** `{ "success": true, "message": "Logout realizado com sucesso", "timestamp": "..." }`.

**Observação:** como JWT é stateless, o backend não invalida realmente o token; o app **deve descartar tokens localmente** após chamar este endpoint.

---

## 3. Módulo: Almoço

Endpoints sob `/api/almoco/`. **Todos usam o formato B (legado).**

### 3.1 `GET /api/almoco/verificar_horario.php`

Verifica se a data/hora permite reserva e retorna valores aplicáveis.

**Query:**
- `data` (opcional, default = hoje) — `YYYY-MM-DD`.
- `tipo` (opcional, default `presencial`) — `presencial` | `marmitex`.

**Sucesso:**
```json
{
  "status": "sucesso",
  "mensagem": "Horário disponível para reserva",
  "data": "2026-06-16",
  "tipo": "presencial",
  "fora_do_horario": false,
  "hora_atual": "08:30",
  "horario_limite": "09:00",
  "valor_normal": 15.0,
  "valor_fora_horario": 30.0
}
```

⚠️ Esse endpoint usa `status: "sucesso"`.

**Erros possíveis (`status: "erro"`):** `Não é possível agendar para datas passadas`, `Não é possível agendar com mais de 30 dias de antecedência`, `Você já possui uma reserva para esta data`, `Data inválida`, `Usuário não logado`.

**Regras:** não aceita datas passadas (exceto hoje); limite +30 dias no futuro; já verifica reserva duplicada.

---

### 3.2 `GET /api/almoco/status_reserva.php`

Status rápido da reserva de hoje. **Sem campo `status`** (resposta crua).

**Query:** nenhum.

**Sucesso:**
```json
{
  "reservou_hoje": false,
  "hora_excedida": true,
  "hora_atual": "10:42",
  "hora_limite": "09:00"
}
```

**Erro (`status: "erro"`):** `Usuário não logado`.

**Uso típico:** atualizar texto/CTA do botão "Reservar" na home.

---

### 3.3 `POST /api/almoco/reservar.php`

Cria reserva de almoço para o próprio usuário.

**Body (JSON ou form-data):**
```json
{ "data": "2026-06-16", "fora_do_horario": false }
```

**Sucesso:**
```json
{
  "status": "ok",
  "mensagem": "Reserva realizada com sucesso",
  "valor_aplicado": 15.0
}
```

⚠️ Esse endpoint usa `status: "ok"` (não "sucesso").

**Erros possíveis:**
- `Você já possui uma reserva para esta data`
- `O refeitório está fechado nesta data (Dia dos namorados). Não é possível fazer reservas.` ← **nova validação `dias_fechado` (2026-06-15)**
- `Usuário não logado`
- `Erro ao registrar reserva`

**Regras:**
- Bloqueia se a data está em `dias_fechado.ativo = 1`.
- Bloqueia duplicidade (mesmo usuário/data).
- Calcula valor pelo grupo do usuário (`grupo_valor`) ou config global; se `fora_do_horario=true`, usa `valor_fora_horario`.
- **Side-effect:** dispara `enviarNotificacaoReserva()` (WhatsApp + email) se habilitado.

---

### 3.4 `POST /api/almoco/cancelar_reserva_propria.php`

Cancela a reserva do próprio usuário.

**Body (JSON ou form-data):**
```json
{ "data": "2026-06-16" }
```

**Sucesso:**
```json
{ "status": "ok", "mensagem": "Reserva cancelada com sucesso" }
```

**Erros:** `Data não informada`, `Formato de data inválido`, `Reserva não encontrada`, `Não é mais possível cancelar esta reserva` (passou do horário limite no dia atual), `Usuário não autenticado`.

**Regras:** só cancela reserva do próprio usuário. Para hoje, bloqueia se já passou de `hora_limite` (config).

---

### 3.5 `GET /api/almoco/listar_reservas_usuario.php`

Lista reservas próprias num período.

**Query:**
- `data_inicio` (opcional, default = primeiro dia do mês atual) — `YYYY-MM-DD`.
- `data_fim` (opcional, default = último dia do mês atual) — `YYYY-MM-DD`.

**Sucesso:**
```json
{
  "status": "ok",
  "reservas": [
    {
      "id": 123,
      "data": "2026-06-16",
      "valor": 15.0,
      "status": "Futura",
      "pode_excluir": true
    }
  ],
  "resumo": { "quantidade": 1, "valor_total": 15.0 }
}
```

**Campos por reserva:**
- `id` (int) — id da reserva.
- `data` (string `YYYY-MM-DD`) — data da reserva.
- `valor` (float) — valor cobrado.
- `status` (enum string) — `"Atual"` (hoje, ainda dentro do horário) | `"Futura"` (data ainda não chegou) | `"Finalizada"` (passou do horário ou data já passou).
- `pode_excluir` (bool) — se ainda é possível cancelar (true para `Atual` e `Futura`).

**Erros:** `Usuário não logado`, `Método não permitido`, `Erro: <mensagem>`.

---

### 3.6 `GET /api/almoco/verificar_horario_adicional.php`

Verifica disponibilidade e valor para reserva de **dependente**.

**Query:**
- `id_dependente` (**obrigatório**) — int.
- `data` (opcional, default = hoje) — `YYYY-MM-DD`.
- `tipo` (opcional, default `presencial`) — `presencial` | `marmitex`.

**Sucesso:**
```json
{
  "status": "ok",
  "mensagem": "Reserva pode ser feita",
  "dependente": { "id": 5, "nome": "João Silva Filho", "cobrar": 0 },
  "valores": {
    "valor_refeicao": 15.0,
    "valor_marmitex": 0.0,
    "valor_normal_refeicao": 15.0,
    "valor_normal_marmitex": 0.0,
    "valor_fora_horario": 30.0,
    "fora_do_horario": false
  },
  "horario": { "hora_atual": "08:30:15", "horario_limite": "09:00", "fora_do_horario": false }
}
```

⚠️ `horario.hora_atual` vem em `HH:MM:SS` (não `HH:MM` como em outros endpoints). Faça `substring(0, 5)` se for exibir.

**Erros:** `ID do dependente não informado`, `Tipo de refeição inválido`, `Reservas para marmitex estão desabilitadas no sistema.`, `Formato de data inválido`, `Não é possível reservar para datas passadas`, `Não é possível reservar com mais de 30 dias de antecedência`, `Dependente não encontrado ou não pertence ao usuário`, `Já existe uma reserva adicional para este dependente nesta data`.

**Regras:**
- Marmitex pode estar globalmente desabilitado (`marmitex_habilitado` em `configuracoes`).
- Dependente com `cobrar = 1` (≤ 12 anos) → valores 0.0.
- Mesmas regras de janela temporal de `verificar_horario`.

---

### 3.7 `POST /api/almoco/reservar_adicional.php`

Cria reserva adicional para um dependente.

**Body (JSON ou form-data):**
```json
{
  "data": "2026-06-16",
  "quantidade": 1,
  "detalhe": "",
  "tipo": "presencial",
  "dependente": 5,
  "fora_do_horario": false
}
```

**Sucesso:** `{ "status": "ok" }`.

**Erros:** `Usuário não autenticado.`, `Dados inválidos.`, `Reservas para marmitex estão desabilitadas no sistema.`, `Dependente inválido.`, `Usuário sem grupo de valor associado.`, `Erro ao salvar reserva adicional.`.

**Observação:** o backend re-valida tudo (dependente do usuário, marmitex, valor por idade).

---

### 3.8 `GET /api/almoco/listar_reservas_adicionais_usuario.php`

Lista reservas adicionais do usuário num período.

**Query:**
- `data_inicio`, `data_fim` (opcionais, default = mês atual).
- `dependente` (opcional) — filtrar por id do dependente.

**Sucesso:**
```json
{
  "status": "ok",
  "reservas": [
    {
      "id": 456,
      "data": "2026-06-16",
      "quantidade": 1,
      "tipo": "presencial",
      "detalhe": "",
      "valor_total": 15.0,
      "data_cadastro": "16/06/2026 08:12",
      "dependente_id": 5,
      "dependente_nome": "João Silva Filho",
      "status": "Atual",
      "pode_excluir": true
    }
  ],
  "resumo": { "quantidade": 1, "valor_total": 15.0 }
}
```

**Campos por reserva:**
- `id` (int) — id da reserva.
- `data` (string `YYYY-MM-DD`).
- `quantidade` (int).
- `tipo` (string) — `presencial` | `marmitex`.
- `detalhe` (string) — texto livre.
- `valor_total` (float) — soma de `valor_refeicao + valor_marmitex` cobrado.
- `data_cadastro` (string `dd/mm/yyyy HH:MM`) — quando a reserva foi criada.
- `dependente_id` (int).
- `dependente_nome` (string).
- `status` (enum string) — `"Atual"` | `"Futura"` | `"Finalizada"` (mesma semântica de §3.5).
- `pode_excluir` (bool).

⚠️ Esse endpoint **não retorna** `valor_refeicao` e `valor_marmitex` separados, só o `valor_total`. Se o app precisar discriminar, faça a divisão localmente baseada em `tipo`.

---

### 3.9 `POST /api/almoco/excluir_reserva_adicional.php`

Exclui uma reserva adicional do próprio usuário.

**Body (JSON ou form-data):** `{ "id": 456 }`.

**Sucesso:** `{ "status": "ok", "mensagem": "Reserva adicional excluída com sucesso" }`.

**Erros:** `ID da reserva inválido`, `Reserva não encontrada`, `Usuário não logado`, `Método não permitido`, `Erro ao excluir reserva adicional`.

---

### 3.10 `GET /api/almoco/listar_adicionais.php`

Lista reservas adicionais do usuário **para o dia atual** (não é "tipos de adicional"). **Resposta crua, sem campo `status`** em sucesso.

**Query:** nenhum.

**Sucesso:**
```json
{
  "reservas": [
    {
      "id": 456,
      "data": "16/06/2026",
      "quantidade": 1,
      "tipo": "presencial",
      "detalhe": "",
      "data_cadastro": "16/06/2026 08:12:00",
      "valor_refeicao": 15.0,
      "valor_marmitex": 0.0,
      "pode_excluir": true,
      "nome_dependente": "João Silva Filho"
    }
  ],
  "quantidade_total": 1
}
```

**Erro:** `{ "status": "erro", "mensagem": "Usuário não autenticado" }`.

**Uso típico:** mostrar o "carrinho" de hoje na home, com botão excluir condicional a `pode_excluir`.

---

### 3.11 `POST /api/almoco/reservar_multiplo.php`

Cria reservas para um intervalo de datas (vários dias úteis). É o que o web mostra como "Reservar para vários dias". **Liberado para uso mobile.**

**Body (JSON ou form-data):**
```json
{
  "data_inicio": "2026-06-17",
  "data_fim": "2026-06-21",
  "dependentes": [],
  "fora_do_horario": false
}
```

- `data_inicio` / `data_fim` (string `YYYY-MM-DD`, **obrigatórios**).
- `dependentes` (array de int, opcional) — IDs de dependentes; se vazio/omitido, faz reservas para o próprio usuário em cada dia.
- `fora_do_horario` (bool, opcional) — vale só para o dia atual e só se já passou de `hora_limite`.

**Sucesso:**
```json
{
  "status": "ok",
  "sucessos": 4,
  "falhas": 1,
  "erros": ["Já existe reserva para 2026-06-18", "Refeitório fechado em 2026-06-20 (Feriado)"]
}
```

**Erros gerais:** `Datas não fornecidas`, `Formato de data inválido`, `Data de início deve ser anterior à data de fim`, `Usuário não logado`.

**Comportamento:**
- Pula automaticamente sábado/domingo.
- Pula datas marcadas em `dias_fechado` (refeitório fechado) — entram em `erros[]`.
- Pula datas em que já há reserva para o mesmo usuário — entram em `erros[]`.
- Para o dia atual, se passou de `hora_limite` E `fora_do_horario != true`, pula com erro.
- Valor cobrado: respeita o `grupo_valor` do usuário; `fora_do_horario` aplica `valor_fora_horario` só ao dia atual.

---

### 3.12 `GET /api/almoco/listar_entidades.php`

Lista as **entidades** (`Religiosa`, `Educação`, `Loja`) para popular o dropdown da tela admin **"Reservas de Departamento"**. **Apenas admin** (`categoria === "admin"`).

⚠️ Os dados desse endpoint são os MESMOS que `/api/frota/listar_entidades.php` (§7.6) — vêm da tabela `entidade`. A diferença é só o contexto: aqui a tela do almoço rotula como "Departamento" (causando confusão), na frota rotula como "Entidade". Use `almoco/listar_entidades.php` no fluxo de reserva de departamento do almoço; `frota/listar_entidades.php` no fluxo de retirada de veículo.

**Query:** nenhum.

**Sucesso:**
```json
{
  "status": "ok",
  "entidades": [
    { "id": 1, "nome": "Religiosa" },
    { "id": 2, "nome": "Educação" },
    { "id": 3, "nome": "Loja" }
  ]
}
```

**Erros:** `Acesso negado` (não admin), `Usuário não autenticado`.

---

### 3.13 `POST /api/almoco/reservar_departamento.php`

Cria uma reserva avulsa em nome de uma entidade (evento, recepção, etc.). **Apenas admin.**

**Body (JSON ou form-data):**
```json
{
  "entidade_id": 2,
  "quantidade": 25,
  "evento_motivo": "Capacitação Educação",
  "data": "2026-06-20"
}
```

Todos os campos obrigatórios. `data` em `YYYY-MM-DD`.

**Sucesso:** `{ "status": "ok", "mensagem": "Reserva de departamento criada com sucesso", "id": 789 }`.

**Erros:** `Todos os campos são obrigatórios`, `Formato de data inválido. Use YYYY-MM-DD`, `Entidade não encontrada`, `Acesso negado - apenas administradores podem fazer reservas de departamento`.

**Observação:** o `entidade_id` vem do dropdown populado por `/api/almoco/listar_entidades.php` (§3.12), **não** de `frota/departamentos.php`.

---

### 3.14 `POST /api/almoco/excluir_reserva_departamento.php`

Exclui uma reserva de departamento. **Apenas admin.**

**Body (JSON ou form-data):**
```json
{ "id": 789 }
```

**Sucesso:** `{ "status": "ok", "mensagem": "Reserva de departamento excluída com sucesso" }`.

**Erros:** `ID da reserva inválido`, `Reserva não encontrada`, `Acesso negado - apenas administradores podem excluir reservas de departamento`.

---

## 4. Módulo: Calendário

### 4.1 `GET /api/calendario/dados_culto.php`

Dados de presença em culto, dia a dia, para um mês.

**Query:**
- `mes` (opcional, default = mês atual) — int 1–12.
- `ano` (opcional, default = ano atual) — int.

**Sucesso:**
```json
{
  "status": "ok",
  "dados": {
    "2026-06-04": { "status": "presente", "justificativa": null, "houve_culto": true },
    "2026-06-05": { "status": "sem_dados", "justificativa": null, "houve_culto": false }
  },
  "resumo": { "total_presencas": 12, "total_faltas": 3, "total_justificativas": 1 }
}
```

**Valores possíveis de `status` por dia:** `sem_dados`, `falta`, `presente`, `atrasado`, `justificativa_aceita`, `justificativa_pendente`, `justificativa_rejeitada`, `sem_culto`, `nao_culto`.

**Erros:** `Erro ao buscar dados: ...`, `Usuário não autenticado`.

**Observações:**
- "Tem culto" só se a data é dia programado (`configuracoes_culto.dias_semana`, default `1,2,3,4,5`) **E** houve pelo menos uma presença real registrada de qualquer usuário naquela data.
- Justificativa tem prioridade sobre presença.
- Data futura sem registro vira `sem_dados`, nunca `falta`.

---

## 5. Módulo: Culto

### 5.1 `POST /api/culto/enviar_justificativa.php`

Envia uma justificativa de falta.

**Body (JSON ou form-data):**
```json
{
  "data_falta": "2026-06-04",
  "motivo": "Consulta médica",
  "observacoes": "Atestado anexo"
}
```

**Sucesso:** `{ "status": "ok", "mensagem": "Justificativa enviada com sucesso! Aguarde a análise do administrador." }`.

**Erros:** `Data da falta é obrigatória`, `Motivo da falta é obrigatório`, `Não é possível justificar faltas de datas futuras`, `Já existe uma justificativa para esta data`, `Não há registro de presença para esta data`, `Só é possível justificar faltas`.

**Comportamento:** se não há `presencas_culto` para o usuário+data, mas há para outros, cria um registro automático com `status='falta'` antes de inserir a justificativa.

---

### 5.2 `GET /api/culto/frequencia.php`

Frequência percentual do mês.

**Query:**
- `mes` (opcional, default = mês atual) — `YYYY-MM`.

**Sucesso:**
```json
{
  "status": "ok",
  "mes": "2026-06",
  "frequencia": {
    "percentual": 85.7,
    "presentes": 18,
    "atrasados": 2,
    "faltas": 1,
    "justificadas": 1,
    "total": 22
  },
  "dados": { "PRE": 18, "ATR": 2, "FAL": 1, "JUS": 1 }
}
```

**Erros:** `Formato de mês inválido. Use YYYY-MM`.

**Regra:** `percentual = (presentes + atrasados + justificadas) / total_dias_culto * 100`. Apenas justificativas **aprovadas** entram em `justificadas`; pendentes/rejeitadas contam como falta.

---

### 5.3 `GET /api/culto/historico_usuario.php`

Histórico detalhado por período.

**Query:**
- `mes` (opcional, default = mês atual) — `YYYY-MM`.
- `periodo` (opcional, default `mes`) — `mes` | `3meses` | `6meses` | `ano` | `todos`.

**Sucesso:**
```json
{
  "status": "ok",
  "presencas": [
    {
      "data": "2026-06-04",
      "horario_confirmacao": "07:42:13",
      "status": "presente",
      "tipo_confirmacao": "facial",
      "tem_culto": true,
      "tem_culto_programado": true,
      "justificativa": null
    }
  ],
  "estatisticas": {
    "total_presentes": 18,
    "total_atrasados": 2,
    "total_faltas": 1,
    "total_justificativas": 1
  },
  "periodo": { "inicio": "2026-06-01", "fim": "2026-06-30" },
  "dias_culto": ["2026-06-04", "2026-06-05", "..."]
}
```

Status de cada item: `presente`, `atrasado`, `falta`, `justificado`, `sem-presenca`. Quando há justificativa associada, o item traz `justificativa: { id, motivo, observacoes, status, data_aprovacao, observacoes_admin }`. **Quando não há justificativa, o campo `justificativa` é `null`** — o app deve testar antes de acessar `presenca.justificativa.motivo`.

---

### 5.4 `GET /api/culto/listar_faltas_usuario.php`

Lista de faltas (com filtro de status).

**Query:**
- `data_inicio` (opcional, default `2020-01-01`).
- `data_fim` (opcional, default = hoje).
- `filtro_status` (opcional) — `falta` | `pendente` | `aprovada` | `rejeitada`.

**Sucesso:**
```json
{
  "status": "ok",
  "faltas": [
    {
      "data": "2026-04-22",
      "status": "falta",
      "motivo": "Doente",
      "observacoes": "Atestado anexo",
      "status_justificativa": "pendente",
      "tipo_falta": "implícita"
    }
  ],
  "estatisticas": { "faltas": 3, "pendentes": 1, "aprovadas": 1, "rejeitadas": 0, "total": 5 }
}
```

`tipo_falta`: `implícita` (sem registro de presença), `explícita` (registro com `status='falta'`), `justificada` (com justificativa).

---

## 6. Módulo: Dependentes

### 6.1 `GET /api/dependentes/listar.php`

Lista dependentes do usuário logado.

**Query:**
- `usuario_id` (opcional, default = usuário logado) — só admin usa.

**Sucesso:**
```json
{
  "status": "ok",
  "dados": [
    {
      "id": 5,
      "nome": "João Silva Filho",
      "parentesco": "Filho",
      "data_nascimento": "2010-05-15",
      "idade": 16,
      "foto_base64": null,
      "cobrar": 0
    }
  ]
}
```

**Campos:**
- `cobrar` (int) — `1` significa **não cobra** (dependente ≤ 12 anos); `0` significa **cobra** (> 12 anos). Use esse campo direto, não recalcule pela idade.

**Observação:** lista apenas dependentes ativos. `foto_base64` pode ser `null` ou uma string base64 grande (sem prefixo `data:image/`).

---

### 6.2 `POST /api/dependentes/criar.php`

Cria dependente. **Exige categoria `admin`.**

**Body (JSON ou form-data):**
```json
{
  "usuario_id": 123,
  "nome": "João Silva Filho",
  "parentesco": "Filho",
  "nascimento_dependente": "2010-05-15",
  "foto_base64": "<opcional, base64 com ou sem prefixo>"
}
```

Aceita também `nascimento` no lugar de `nascimento_dependente`, e `$_FILES['foto']` em form-data.

**Sucesso:** `{ "status": "ok", "mensagem": "Dependente criado com sucesso", "id": 42 }`.

**Erros:** `Acesso negado`, `Dados obrigatórios não fornecidos`, `Erro ao criar dependente: ...`.

**Regra de idade:** ≤ 12 anos → `cobrar = 1` (não cobra); > 12 → `cobrar = 0`.

---

### 6.3 `POST /api/dependentes/editar.php`

Edita dependente. **Admin pode editar qualquer; usuário só edita os próprios.**

**Body (JSON ou form-data):**
```json
{
  "id": 5,
  "nome": "João Silva Filho",
  "parentesco": "Filho",
  "nascimento_dependente": "2010-05-15",
  "foto_base64": "<opcional>"
}
```

**Sucesso:** `{ "status": "ok", "mensagem": "Dependente atualizado com sucesso" }`.

**Erros:** `ID do dependente inválido`, `Nome e parentesco são obrigatórios`, `Dependente não encontrado`, `Acesso negado - você só pode editar seus próprios dependentes`.

**Observação:** se a foto base64 for > 1 MB, o backend redimensiona para 800×600 JPEG q=80 antes de salvar (via GD). Use isso a favor — o app não precisa redimensionar antes.

---

### 6.4 `POST /api/dependentes/excluir.php`

Exclui dependente (soft delete: `UPDATE ativo=0`). Mesma regra de admin/dono de `editar`.

**Body (JSON ou form-data):** `{ "id": 5 }`.

**Sucesso:** `{ "status": "ok", "mensagem": "Dependente excluído com sucesso" }`.

**Erros:** `ID do dependente inválido`, `Dependente não encontrado`, `Acesso negado - você só pode excluir seus próprios dependentes`.

---

## 7. Módulo: Frota

### 7.1 `GET /api/frota/listar_veiculos.php`

Lista veículos da frota.

**Query:**
- `status` (opcional) — `disponivel` | `em_uso` | `manutencao` | `inativo`.
- `incluir_inativos` (opcional) — `"1"` para incluir `ativo=0`.

**Sucesso:**
```json
{
  "status": "ok",
  "veiculos": [
    {
      "id": 1,
      "placa": "ABC1234",
      "modelo": "Strada",
      "marca": "Fiat",
      "ano": 2023,
      "cor": "Branca",
      "km_atual": 15230,
      "status": "disponivel",
      "ativo": 1,
      "foto_veiculo": "uploads/frota/veiculo_1.jpg",
      "observacoes": null,
      "usuario_atual": null
    }
  ],
  "total": 1
}
```

`usuario_atual` é o nome de quem está usando agora (quando `status: "em_uso"`).

---

### 7.2 `GET /api/frota/minha_utilizacao.php`

Retorna a utilização **em andamento** do usuário, se houver.

**Sucesso (com veículo em uso):**
```json
{
  "status": "ok",
  "tem_veiculo": true,
  "utilizacao": {
    "id": 12,
    "id_veiculo": 3,
    "placa": "ABC1234",
    "modelo": "Strada",
    "marca": "Fiat",
    "cor": "Branca",
    "entidade": "AOM Matriz",
    "data_saida": "2026-06-16 08:30:00",
    "data_saida_formatada": "16/06/2026 08:30",
    "km_saida": 15000,
    "destino": "Cuiabá Centro",
    "motivo": "Reunião",
    "tempo_uso": "2h 15min"
  }
}
```

**Sucesso (sem veículo):** `{ "status": "ok", "tem_veiculo": false, "utilizacao": null }`.

**Uso típico:** decidir se mostra tela de "retirar" ou "devolver" na home da frota.

---

### 7.3 `GET /api/frota/meu_historico.php`

Histórico de utilizações do usuário.

**Query:**
- `estatisticas=1` (opcional) — se enviado, retorna apenas o bloco `estatisticas` e encerra (não lista as utilizações).
- `dias` (opcional, int) — últimas N dias.
- `status` (opcional) — `em_andamento` | `finalizado`.

**Sucesso (modo estatísticas):**
```json
{
  "status": "ok",
  "estatisticas": {
    "total_viagens": 15,
    "km_total": 1240,
    "tempo_total": "38h 12min",
    "mes_atual": 3,
    "valor_km": 2.5,
    "valor_total_locacao": 3100
  }
}
```

**Sucesso (modo lista, default):**
```json
{
  "status": "ok",
  "utilizacoes": [
    {
      "id": 12,
      "placa": "ABC1234",
      "modelo": "Strada",
      "marca": "Fiat",
      "entidade": "AOM Matriz",
      "departamento": "TI",
      "data_saida": "2026-05-10 08:30:00",
      "data_saida_formatada": "10/05/2026 08:30",
      "data_entrada": "2026-05-10 14:15:00",
      "data_entrada_formatada": "10/05/2026 14:15",
      "km_saida": 15000,
      "km_entrada": 15080,
      "km_percorrido": 80,
      "tempo_formatado": "5h 45min",
      "destino": "Cuiabá Centro",
      "motivo": "Reunião",
      "status": "finalizado",
      "valor_km": 2.5,
      "valor_locacao": 200
    }
  ],
  "total": 1
}
```

LIMIT 100 nas utilizações.

---

### 7.4 `POST /api/frota/registrar_saida.php`

Registra a retirada de um veículo. **Aceita apenas JSON.**

⚠️ `id_entidade` e `id_departamento` são **campos diferentes** (ver §1.11). Entidade = unidade de negócio (`Religiosa`/`Educação`/`Loja`), Departamento = setor (`TI`/`RH`/etc). A tela do app **precisa de dois dropdowns separados**.

**Body (JSON):**
```json
{
  "id_veiculo": 3,
  "id_entidade": 1,
  "id_departamento": 2,
  "km_saida": 15000,
  "destino": "Cuiabá Centro",
  "motivo": "Reunião",
  "observacoes_saida": "",
  "foto_selfie": "data:image/jpeg;base64,...",
  "foto_km": "data:image/jpeg;base64,...",
  "foto_veiculo1": "data:image/jpeg;base64,...",
  "foto_veiculo2": null,
  "foto_veiculo3": null,
  "checklist": {
    "pneus_ok": 1,
    "farois_ok": 1,
    "documentos_ok": 1,
    "limpeza_ok": 1,
    "nivel_combustivel": "3/4",
    "avarias_encontradas": ""
  }
}
```

`foto_selfie` e `foto_km` são **obrigatórias**. `foto_veiculoX` e `checklist` opcionais.

**Sucesso:** `{ "status": "ok", "mensagem": "Veículo retirado com sucesso", "utilizacao_id": 42 }`.

**Erros:** `Dados inválidos`, `Veículo/Entidade/Departamento/KM/Destino/Motivo não informado(a)`, `Fotos obrigatórias não enviadas`, `Veículo não encontrado`, `Veículo não está disponível`, `Você já possui um veículo em uso`.

**Observação:** o backend usa `post_max_size=50M` para acomodar as fotos.

---

### 7.5 `POST /api/frota/registrar_entrada.php`

Registra a devolução do veículo. **Aceita apenas JSON.**

**Body (JSON):**
```json
{
  "id_utilizacao": 42,
  "km_entrada": 15080,
  "observacoes_entrada": "",
  "foto_selfie": "data:image/jpeg;base64,...",
  "foto_km": "data:image/jpeg;base64,...",
  "foto_veiculo1": "data:image/jpeg;base64,...",
  "checklist": {
    "limpeza_ok": 1,
    "nivel_combustivel": "1/2",
    "avarias_encontradas": ""
  }
}
```

`foto_selfie` e `foto_km` obrigatórias. `km_entrada >= km_saida`.

**Sucesso:**
```json
{
  "status": "ok",
  "mensagem": "Veículo devolvido com sucesso",
  "km_percorrido": 80,
  "tempo_uso": "5h 45min",
  "whatsapp_enviado": true
}
```

**Erros:** `Dados inválidos`, `Utilização não informada`, `KM de entrada não informado`, `Fotos obrigatórias não enviadas`, `Utilização não encontrada ou não pertence a você`, `KM de entrada não pode ser menor que o KM de saída`.

**Side-effect:** gera PDF de comprovante (mPDF) e envia ao telefone do usuário via WhatsApp. Falha de envio **não** quebra a devolução (`whatsapp_enviado` indica o resultado).

---

### 7.6 `GET /api/frota/listar_entidades.php`

Lista as entidades (matriz, filiais) para popular o dropdown `id_entidade` no `registrar_saida`.

**Query:** nenhum.

**Sucesso:**
```json
{
  "status": "ok",
  "entidades": [
    { "id": 1, "nome": "AOM Matriz" },
    { "id": 2, "nome": "AOM Filial Norte" }
  ]
}
```

**Erro:** `{ "status": "erro", "mensagem": "Usuário não autenticado" }` ou `Erro ao listar entidades: ...`.

---

### 7.7 `GET /api/frota/departamentos.php`

Lista os departamentos da frota para popular o dropdown `id_departamento` no `registrar_saida`.

**Query:**
- `apenas_ativos` (opcional) — `1` para retornar só `ativo = 1` (recomendado para o dropdown). Default: traz todos.

**Sucesso:**
```json
{
  "status": "ok",
  "departamentos": [
    {
      "id": 2,
      "nome": "TI",
      "descricao": "Tecnologia da Informação",
      "ativo": 1,
      "criado_em_fmt": "10/01/2026 09:00",
      "atualizado_em_fmt": "10/01/2026 09:00"
    }
  ]
}
```

**Erro:** `{ "status": "erro", "mensagem": "Usuário não autenticado" }`.

**Observação:** o mesmo arquivo aceita POST/DELETE para CRUD de departamentos, mas esses caminhos exigem permissão `frota_departamentos` (admin) — **não use no app**, mantenha somente o GET.

---

## 8. Módulo: Usuários

### 8.1 `GET /api/usuarios/buscar_perfil.php`

Retorna o perfil do usuário autenticado.

**Sucesso:**
```json
{
  "status": "ok",
  "usuario": {
    "id": 123,
    "nome": "Fulano de Tal",
    "email": "fulano@aom.org.br",
    "telefone": "65999990000",
    "foto_base64": "<base64 sem prefixo>",
    "id_valor": 2,
    "entidade_id": 1,
    "grupo_nome": "Funcionário",
    "entidade_nome": "AOM Matriz"
  }
}
```

**Observação:** `foto_base64` pode ser muito grande. O app deve renderizar com prefixo `data:image/jpeg;base64,` na frente.

---

### 8.2 `POST /api/usuarios/atualizar_perfil.php`

Atualiza dados do perfil próprio.

**Body (JSON ou form-data):**
```json
{
  "nome": "Fulano de Tal",
  "email": "fulano@aom.org.br",
  "telefone": "65999990000",
  "senha": "novasenha123",
  "senha_confirma": "novasenha123",
  "foto_base64": "<opcional>"
}
```

`senha` e `foto_base64` opcionais (só atualiza se vier).

**Sucesso:** `{ "status": "ok", "mensagem": "Perfil atualizado com sucesso" }`.

**Erros:** `Nome e email são obrigatórios`, `Email inválido`, `Email já está sendo usado por outro usuário`, `As senhas não coincidem`, `A senha deve ter pelo menos 6 caracteres`.

**Regras:** senha armazenada com `password_hash(PASSWORD_DEFAULT)`. Email único.

---

### 8.3 `POST /api/usuarios/atualizar_foto.php`

Atualiza só a foto.

**Body (JSON ou form-data):**
```json
{ "foto_base64": "data:image/jpeg;base64,..." }
```

Aceita `foto` ou `foto_base64`. Prefixo `data:image/...;base64,` removido pelo backend.

**Sucesso:** `{ "status": "ok", "mensagem": "Foto atualizada com sucesso", "foto_base64": "<base64 sem prefixo>" }`.

**Erros:** `Foto não fornecida`, `Erro ao atualizar foto`.

---

## 9. Módulo: Notificações Push (FCM)

O backend envia push pelo **Firebase Cloud Messaging HTTP v1** usando a Service Account configurada pelo admin (tela `/painel/notificacoes_push.php`). Esta seção tem duas partes: (9.0) o passo a passo do Firebase + setup do projeto, e (9.1/9.2) os endpoints mobile.

---

### 9.0 Setup do Firebase — passo a passo

**Convenções de nomes do projeto desta organização** (use exatamente estes nomes; se mudar, ajuste em todos os pontos):

| Item | Valor a usar |
|---|---|
| Nome do projeto no Firebase | `Intranet AOM` |
| Project ID (Firebase) | `intranet-aom` (Firebase pode acrescentar sufixo aleatório se já existir) |
| **Android applicationId** (`build.gradle`) | `br.org.aom.intranet` |
| **iOS Bundle Identifier** (Xcode) | `br.org.aom.intranet` |
| Nome do app exibido | `Intranet AOM` |
| Categoria (Android) | Productivity |

#### 9.0.1 Criar o projeto no Firebase Console

1. Acesse <https://console.firebase.google.com> com a conta Google da organização.
2. Clique em **"Adicionar projeto"** → nome **`Intranet AOM`** → continuar.
3. Desabilite Google Analytics (não é necessário pra push). → **Criar projeto**.
4. Anote o **Project ID** que o Firebase atribuiu — você vai precisar dele.

#### 9.0.2 Adicionar app Android

1. Na home do projeto Firebase, clique no ícone **Android**.
2. **Nome do pacote Android**: `br.org.aom.intranet` (exatamente este — tem que casar com o `applicationId` do `build.gradle`).
3. **Apelido do app**: `Intranet AOM (Android)`.
4. Ignore o SHA-1 nesta etapa (não é exigido para push; só pra login com Google).
5. Clique **Registrar app** → baixe o arquivo **`google-services.json`**.
6. Coloque o arquivo em **`android/app/google-services.json`** do projeto do app (no nível do módulo `app`, NÃO na raiz).
7. No `android/build.gradle` (project-level), adicione o classpath:
   ```gradle
   classpath 'com.google.gms:google-services:4.4.2'
   ```
8. No `android/app/build.gradle` (módulo), no final do arquivo:
   ```gradle
   apply plugin: 'com.google.gms.google-services'
   ```
9. Adicione a dependência do Firebase Messaging:
   ```gradle
   implementation platform('com.google.firebase:firebase-bom:33.5.1')
   implementation 'com.google.firebase:firebase-messaging'
   ```
10. No `AndroidManifest.xml`, garanta a permissão (Android 13+):
    ```xml
    <uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
    ```

#### 9.0.3 Adicionar app iOS

1. Na home do projeto Firebase, clique em **"Adicionar app" → iOS**.
2. **Bundle ID**: `br.org.aom.intranet` (exatamente este — tem que casar com o que está no Xcode).
3. **Apelido do app**: `Intranet AOM (iOS)`.
4. Clique **Registrar app** → baixe o arquivo **`GoogleService-Info.plist`**.
5. Arraste o arquivo para o projeto Xcode na pasta `Runner/` (ou equivalente) — marque "Copy items if needed" e adicione ao target.
6. No Xcode → **Signing & Capabilities** do target Runner → **+ Capability**:
   - Adicione **Push Notifications**.
   - Adicione **Background Modes** e marque "Remote notifications".
7. Adicione no `Podfile`:
   ```ruby
   pod 'Firebase/Messaging'
   ```
   Rode `pod install`.

#### 9.0.4 Subir a chave APNs para o Firebase (obrigatório pra iOS funcionar)

iOS NÃO recebe push direto via FCM — o Firebase precisa repassar pro APNs (servidor de push da Apple). Para isso você precisa:

1. Acesse <https://developer.apple.com/account/resources/authkeys/list> e crie uma **APNs Authentication Key** (formato `.p8`).
2. Anote o **Key ID** (mostrado ao criar) e o **Team ID** (em Membership).
3. Baixe o arquivo `.p8`.
4. No Firebase Console → ⚙️ Configurações do projeto → aba **Cloud Messaging** → seção **APNs** → **Upload**:
   - Cole o conteúdo do `.p8`.
   - Preencha **Key ID** e **Team ID**.
5. Pronto — push pra iOS começa a funcionar.

#### 9.0.5 Baixar a Service Account (chave do servidor) e colar no painel admin

A **Service Account JSON** é o que o **backend deste sistema** usa para enviar push. Diferente do `google-services.json` (que é credencial do app), a Service Account é credencial do **servidor**.

1. Firebase Console → ⚙️ **Configurações do projeto** → aba **Contas de serviço**.
2. Clique em **"Gerar nova chave privada"** → confirme → vai baixar um `.json`.
3. Abra esse `.json` num editor → copie o conteúdo **inteiro** (de `{` a `}`).
4. No site, vá em **Painel Administrativo → Notificações Push** (URL: `/painel/notificacoes_push.php`).
5. Cole no campo **"Service Account JSON"** → marque **"Notificações push ativas"** → **Salvar**.
6. O painel deve mostrar **Project ID** e **Client e-mail** como confirmação visual. O arquivo é gravado em `/var/backups/presenca/firebase/service_account.json` (fora do webroot, chmod 640 root:www-data) e nunca mais é exibido.

> ⚠️ A Service Account JSON contém uma **chave privada RSA**. Trate como senha — não cole em chat, e-mail, repositório git. O painel admin é o único lugar autorizado a recebê-la.

#### 9.0.6 Fluxo no app (resumo do que a IA precisa gerar)

```
[App inicia] → solicita permissão de notificação ao usuário.
[Login OK]   → SDK Firebase.getToken() devolve o FCM token.
             → POST /api/mobile/notifications/register.php
                Body: {fcm_token, plataforma:"android"|"ios", modelo_dispositivo, versao_app}
                Header: Authorization: Bearer <access_token do login>

[onTokenRefresh dispara] → repete o register.php com o token novo.

[Logout] → POST /api/mobile/notifications/unregister.php
           Body: {fcm_token}
           Header: Authorization: Bearer <access_token>
         → APAGAR token local e access_token / refresh_token guardados.
```

**Snippets sugeridos por stack** (a IA do app escolhe conforme a linguagem):

Flutter (`firebase_messaging` 15.x):
```dart
await FirebaseMessaging.instance.requestPermission();
final fcmToken = await FirebaseMessaging.instance.getToken();
await api.post('/api/mobile/notifications/register.php', {
  'fcm_token': fcmToken,
  'plataforma': Platform.isIOS ? 'ios' : 'android',
  'modelo_dispositivo': '<modelo>',
  'versao_app': '1.0.0',
});
FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
  await api.post('/api/mobile/notifications/register.php', { 'fcm_token': newToken, /* ... */ });
});
```

React Native (`@react-native-firebase/messaging`):
```ts
await messaging().requestPermission();
const fcmToken = await messaging().getToken();
await api.post('/api/mobile/notifications/register.php', {
  fcm_token: fcmToken, plataforma: Platform.OS, /* ... */
});
messaging().onTokenRefresh(async (newToken) => { /* repete */ });
```

Android nativo (Kotlin):
```kotlin
FirebaseMessaging.getInstance().token.addOnSuccessListener { token ->
    api.post("/api/mobile/notifications/register.php",
        mapOf("fcm_token" to token, "plataforma" to "android", "modelo_dispositivo" to Build.MODEL, "versao_app" to BuildConfig.VERSION_NAME))
}
```

iOS nativo (Swift):
```swift
Messaging.messaging().token { token, error in
  guard let token = token else { return }
  // POST /api/mobile/notifications/register.php
}
```

#### 9.0.7 Validação ponta-a-ponta

Para conferir que tudo está funcionando:
1. App instalado, logado, registrou o token (cheque `notificacoes_push_dispositivos` no banco — deve ter 1 linha do usuário com `ativo=1`).
2. No painel admin → seção **Enviar teste** → e-mail do usuário + título + mensagem → **Enviar teste**.
3. Resultado esperado: alerta verde "Enviado para 1/1 dispositivo(s)" + a notificação chega no celular em alguns segundos.
4. Se aparecer **"UNREGISTERED"** ou **"INVALID_ARGUMENT"**: o backend já desativou o token automaticamente — o app precisa reabrir e re-registrar (o `onTokenRefresh` resolve sozinho na próxima sessão).

---

### 9.0.8 Payload do push e roteamento por `data.tipo`

Toda push enviada pelo backend tem essa forma (formato FCM v1, recebido no SDK do app):

```json
{
  "notification": { "title": "...", "body": "..." },
  "data": { "tipo": "<um dos valores abaixo>", "...outros campos opcionais...": "..." }
}
```

⚠️ **`data` é string-only no FCM**. Números viram strings (`"123"`), objetos viram JSON-string. O app deve fazer parse de qualquer campo que precise como número/objeto.

**Tipos enviados automaticamente pelo backend (use para roteamento):**

| Evento | `data.tipo` | Quando dispara |
|---|---|---|
| Reserva própria confirmada | `reserva_propria` | usuário fez sua reserva pelo app/web |
| Reserva adicional (dependente) confirmada | `reserva_adicional` | usuário reservou para um dependente |
| Reserva em lote (vários dias) confirmada | `reserva_multipla` | reservar_multiplo.php (§3.11) |
| Reserva cancelada | `reserva_cancelada` | cancelar_reserva_propria ou admin |
| Justificativa de culto aprovada | `justificativa_aprovada` | admin aprovou no painel |
| Justificativa de culto rejeitada | `justificativa_rejeitada` | admin rejeitou no painel |
| Lembrete diário ("você ainda não reservou") | `lembrete_reserva` | cron diário às 8h, dias úteis |
| Disparo manual pelo painel admin | qualquer (default `teste_painel`) | botão "Enviar teste" do painel |

**Roteamento sugerido no app (pseudo-código):**

```javascript
function onPushReceived(message) {
  const tipo = message.data?.tipo ?? '';
  if (tipo.startsWith('reserva_'))      return router.go('/almoco/historico');
  if (tipo === 'lembrete_reserva')      return router.go('/almoco/reservar');
  if (tipo.startsWith('justificativa_'))return router.go('/culto/justificativas');
  return router.go('/');  // default home
}
```

**`data` customizado pelo painel admin:** a tela `/painel/notificacoes_push.php` tem um textarea **"Dados customizados (JSON)"** que aceita qualquer objeto plano (chaves e valores em string). Útil para o admin testar deep-links — ex.: `{"tipo":"reserva_propria","reserva_id":"123"}`. O app deve tolerar campos desconhecidos sem quebrar.

**Pedir mais campos pro backend:** hoje os disparos automáticos mandam só `tipo`. Se o app precisar de deep-link mais preciso (ex.: `reserva_id` na confirmação de reserva, `data_falta` na justificativa), o backend pode adicionar — só pedir.

---

### Endpoints mobile

O fluxo do app é simples:

1. Após o login bem-sucedido (uma vez), pegue o **FCM token** do dispositivo (via SDK do Firebase).
2. Chame `POST /api/mobile/notifications/register.php` enviando o token.
3. Pronto — o usuário começa a receber push (se o admin tiver ativado).
4. No logout, chame `POST /api/mobile/notifications/unregister.php` para parar de receber.

Tokens FCM mudam ocasionalmente (reinstalação do app, limpeza de dados). O SDK do Firebase tem um listener `onTokenRefresh` — sempre que disparar, chame `register.php` de novo. O endpoint é idempotente (faz UPSERT por `fcm_token` único).

### 9.1 `POST /api/mobile/notifications/register.php`

Registra ou atualiza o token FCM do dispositivo associado ao usuário autenticado.

**Body (JSON):**
```json
{
  "fcm_token": "fhT8a3K...string longa do Firebase...",
  "plataforma": "android",
  "modelo_dispositivo": "Pixel 7",
  "versao_app": "1.0.0"
}
```

**Campos:**
- `fcm_token` (string, **obrigatório**) — pelo menos 20 caracteres. Vem do SDK Firebase (`getToken()` no Android/iOS).
- `plataforma` (string, opcional, default `"android"`) — `android` | `ios` | `web`.
- `modelo_dispositivo` (string, opcional) — ex.: `"Pixel 7"`, `"iPhone 15"`.
- `versao_app` (string, opcional) — versão do app (ex.: `"1.2.3"`).

**Sucesso:** `{ "status": "ok", "mensagem": "Dispositivo registrado" }`.

**Erros:** `fcm_token inválido`, `Usuário não autenticado`, `Erro ao registrar token: ...`.

**Observações:**
- Idempotente: se o token já existe, atualiza usuário/plataforma/modelo/versão e reativa.
- Chame uma vez após o login E sempre que o `onTokenRefresh` disparar.

---

### 9.2 `POST /api/mobile/notifications/unregister.php`

Desativa um token FCM (chame no logout).

**Body (JSON):**
```json
{ "fcm_token": "fhT8a3K..." }
```

**Sucesso:** `{ "status": "ok", "mensagem": "Dispositivo desregistrado" }`.

**Erros:** `fcm_token é obrigatório`, `Usuário não autenticado`.

**Observação:** só desativa se o token pertence ao usuário autenticado (proteção contra desregistrar dispositivos alheios). Faz soft-disable (`ativo=0`), não deleta.

---

## 10. Módulo: Utilitários

### 10.1 `GET /api/dias_fechado/verificar.php`

Verifica se uma data específica está marcada como "refeitório fechado". **Não exige autenticação** — é um endpoint público para o app conferir antes de oferecer reserva.

**Query:**
- `data` (opcional, default = hoje) — `YYYY-MM-DD`.

**Sucesso (data fechada):**
```json
{
  "status": "ok",
  "data": "2026-06-15",
  "esta_fechado": true,
  "detalhes": { "motivo": "Dia dos namorados", "observacoes": "" }
}
```

**Sucesso (data aberta):**
```json
{
  "status": "ok",
  "data": "2026-06-16",
  "esta_fechado": false,
  "detalhes": null
}
```

**Uso recomendado pelo app:** antes de abrir o seletor de data no fluxo de reserva, chame esse endpoint para cada data que vai habilitar. Se `esta_fechado` for `true`, desabilite a opção (a `reservar.php` vai recusar de qualquer forma com mensagem do motivo).

---

## 11. Tabela resumo (cola para o app)

| # | Endpoint | Método | Auth | Formato sucesso |
|---|----------|--------|------|-----------------|
| Auth |
| 2.1 | `/api/mobile/auth/login.php` | POST | — | `{success, data:{token,refresh_token,user}}` |
| 2.2 | `/api/mobile/auth/refresh.php` | POST | Bearer (refresh) | `{success, data:{token,refresh_token}}` |
| 2.3 | `/api/mobile/auth/logout.php` | POST | Bearer | `{success, message}` |
| Almoço |
| 3.1 | `/api/almoco/verificar_horario.php` | GET | Bearer | `{status:"sucesso", ...valores}` |
| 3.2 | `/api/almoco/status_reserva.php` | GET | Bearer | `{reservou_hoje, hora_excedida, ...}` (sem `status`) |
| 3.3 | `/api/almoco/reservar.php` | POST | Bearer | `{status:"ok", mensagem, valor_aplicado}` |
| 3.4 | `/api/almoco/cancelar_reserva_propria.php` | POST | Bearer | `{status:"ok", mensagem}` |
| 3.5 | `/api/almoco/listar_reservas_usuario.php` | GET | Bearer | `{status:"ok", reservas[], resumo}` |
| 3.6 | `/api/almoco/verificar_horario_adicional.php` | GET | Bearer | `{status:"ok", dependente, valores, horario}` |
| 3.7 | `/api/almoco/reservar_adicional.php` | POST | Bearer | `{status:"ok"}` |
| 3.8 | `/api/almoco/listar_reservas_adicionais_usuario.php` | GET | Bearer | `{status:"ok", reservas[], resumo}` |
| 3.9 | `/api/almoco/excluir_reserva_adicional.php` | POST | Bearer | `{status:"ok", mensagem}` |
| 3.10 | `/api/almoco/listar_adicionais.php` | GET | Bearer | `{reservas[], quantidade_total}` (sem `status`) |
| 3.11 | `/api/almoco/reservar_multiplo.php` | POST | Bearer | `{status:"ok", sucessos, falhas, erros[]}` |
| 3.12 | `/api/almoco/listar_entidades.php` | GET | Bearer (admin) | `{status:"ok", entidades[]}` |
| 3.13 | `/api/almoco/reservar_departamento.php` | POST | Bearer (admin) | `{status:"ok", mensagem, id}` |
| 3.14 | `/api/almoco/excluir_reserva_departamento.php` | POST | Bearer (admin) | `{status:"ok", mensagem}` |
| Calendário |
| 4.1 | `/api/calendario/dados_culto.php` | GET | Bearer | `{status:"ok", dados, resumo}` |
| Culto |
| 5.1 | `/api/culto/enviar_justificativa.php` | POST | Bearer | `{status:"ok", mensagem}` |
| 5.2 | `/api/culto/frequencia.php` | GET | Bearer | `{status:"ok", frequencia, dados}` |
| 5.3 | `/api/culto/historico_usuario.php` | GET | Bearer | `{status:"ok", presencas[], estatisticas, ...}` |
| 5.4 | `/api/culto/listar_faltas_usuario.php` | GET | Bearer | `{status:"ok", faltas[], estatisticas}` |
| Dependentes |
| 6.1 | `/api/dependentes/listar.php` | GET | Bearer | `{status:"ok", dados[]}` |
| 6.2 | `/api/dependentes/criar.php` | POST | Bearer (admin) | `{status:"ok", mensagem, id}` |
| 6.3 | `/api/dependentes/editar.php` | POST | Bearer (admin/dono) | `{status:"ok", mensagem}` |
| 6.4 | `/api/dependentes/excluir.php` | POST | Bearer (admin/dono) | `{status:"ok", mensagem}` (soft delete) |
| Frota |
| 7.1 | `/api/frota/listar_veiculos.php` | GET | Bearer | `{status:"ok", veiculos[], total}` |
| 7.2 | `/api/frota/minha_utilizacao.php` | GET | Bearer | `{status:"ok", tem_veiculo, utilizacao}` |
| 7.3 | `/api/frota/meu_historico.php` | GET | Bearer | `{status:"ok", utilizacoes[], total}` OU `{status:"ok", estatisticas}` |
| 7.4 | `/api/frota/registrar_saida.php` | POST (JSON) | Bearer | `{status:"ok", mensagem, utilizacao_id}` |
| 7.5 | `/api/frota/registrar_entrada.php` | POST (JSON) | Bearer | `{status:"ok", mensagem, km_percorrido, tempo_uso, whatsapp_enviado}` |
| 7.6 | `/api/frota/listar_entidades.php` | GET | Bearer | `{status:"ok", entidades[]}` |
| 7.7 | `/api/frota/departamentos.php` | GET | Bearer | `{status:"ok", departamentos[]}` |
| Usuários |
| 8.1 | `/api/usuarios/buscar_perfil.php` | GET | Bearer | `{status:"ok", usuario}` |
| 8.2 | `/api/usuarios/atualizar_perfil.php` | POST | Bearer | `{status:"ok", mensagem}` |
| 8.3 | `/api/usuarios/atualizar_foto.php` | POST | Bearer | `{status:"ok", mensagem, foto_base64}` |
| Notificações Push |
| 9.1 | `/api/mobile/notifications/register.php` | POST | Bearer | `{status:"ok", mensagem}` |
| 9.2 | `/api/mobile/notifications/unregister.php` | POST | Bearer | `{status:"ok", mensagem}` |
| Utilitários |
| 10.1 | `/api/dias_fechado/verificar.php` | GET | — (público) | `{status:"ok", data, esta_fechado, detalhes}` |

---

## 12. Checklist para o app (lembretes que evitam bugs)

1. **Sempre cheque `success`/`status` do JSON, não o HTTP status** — o backend devolve 200 mesmo em erro nos endpoints de negócio.
2. **Aceite `"ok"` e `"sucesso"` como sucesso** no formato B. Use uma helper centralizada para parsear: `success = body.success === true || body.status === "ok" || body.status === "sucesso"`.
3. **Trate respostas sem `status`** (`status_reserva.php`, `listar_adicionais.php`) como sucesso quando o HTTP é 200 e os campos esperados existem.
4. **Renove o token automaticamente** quando receber mensagem indicando token inválido/expirado (ou proativamente perto dos 24h).
5. **Não pré-redimensione fotos** para `dependentes/editar.php` — o backend faz se passar de 1 MB. Mas pré-redimensione para `frota/registrar_*.php` se quiser evitar timeout (post_max 50M é o teto).
6. **`fora_do_horario` é um booleano que altera o valor cobrado.** Mostre o aviso ao usuário antes de mandar `true`.
7. **`dias_fechado`**: o backend recusa reservas para datas marcadas como fechado (ex.: feriado). Antes de mostrar o calendário, considere usar `/api/dias_fechado/verificar.php?data=YYYY-MM-DD` (público) para já desabilitar a data na UI.
8. **Marmitex pode estar desabilitado globalmente** — `verificar_horario_adicional` devolve o erro específico. Esconda o botão Marmitex se a primeira chamada retornar isso.
9. **Categoria do usuário** vem no `data.user.categoria` do login — use para decidir telas (admin vê tudo; demais respeitam regras de dependentes etc.).
10. **Timestamps em UTC-4 (`America/Cuiaba`)** no backend. Use `timestamp` ISO 8601 retornado pelo MobileResponse como referência.

---

## 13. Inconsistências conhecidas (do código atual)

Aviso só para a IA entender que não são bugs do app — são particularidades do backend que o app precisa absorver:

- `verificar_horario.php` usa `status: "sucesso"`; todos os outros endpoints de almoço usam `status: "ok"`. **Sucesso é qualquer um dos dois.**
- `status_reserva.php` e `listar_adicionais.php` retornam **sem** o campo `status` no caminho de sucesso.
- `dependentes/criar.php` usa `idade <= 12` para definir `cobrar=1`; `dependentes/editar.php` usa `idade < 12`. A regra "real" segundo `verificar_horario_adicional` é `<= 12`.
- O campo `observacoes_admin` em `listar_faltas_usuario.php` traz `observacoes` da justificativa, não realmente um campo de admin. Documente como "observação adicional".

---

**Endpoints totais documentados: 39** (3 auth + 14 almoço + 1 calendário + 4 culto + 4 dependentes + 7 frota + 3 usuários + 2 push + 1 utilitário).

**Nome oficial do app mobile:** `Intranet AOM` (não "Presença AOM" — o web continua sendo Presença AOM, só o app foi renomeado em 2026-06-16). Package Android e iOS Bundle ID: `br.org.aom.intranet`. Projeto Firebase: `intranet-aom`.

**Última atualização:** 2026-06-16 (auditoria contra código real + frontend web + push notifications via FCM)

Novidades nesta versão:
- **Renomeação do app para "Intranet AOM"** (era "Presença AOM"). Project Firebase: `intranet-aom`, package/bundle: `br.org.aom.intranet`. Aplicado em todo o §9.0 e mensagens default.
- **§3.11 `reservar_multiplo.php`** agora mobile. Resolve a tela "Reservar para vários dias" que aparecia como "em desenvolvimento" no app.
- **§3.12-3.14 fluxo admin "Reservas de Departamento"** agora mobile: `listar_entidades.php`, `reservar_departamento.php`, `excluir_reserva_departamento.php`. Resolve o bug do dropdown que mostrava TI/RH/Financeiro (dados de `frota_departamentos`) quando devia mostrar Religiosa/Educação/Loja (tabela `entidade`).
- **§1.11 dropdown table** atualizada com regra clara: "Departamento" no almoço = entidades; "Departamento" na frota = setores. O sistema usa o mesmo rótulo para coisas diferentes — esse é o ponto de confusão histórico.
- **Push integrado nos fluxos de notificação existentes**: ao confirmar/cancelar reserva, aprovar/rejeitar justificativa e no lembrete diário, o sistema agora dispara push em paralelo ao WhatsApp/email. Silencioso se push não está configurado ou usuário sem dispositivos. Sem mudança de contrato nos endpoints existentes.
- **§9 Notificações Push (FCM)**: dois novos endpoints mobile — `register.php` (registra token FCM após login) e `unregister.php` (no logout). Painel admin em `/painel/notificacoes_push.php` para configurar a Service Account do Firebase e testar envios.
- **§9.0 Setup Firebase passo a passo**: nome de pacote canônico (`br.org.aom.intranet`), Bundle ID iOS, instruções de Firebase Console (Android + iOS), upload da chave APNs, geração da Service Account, dependências de Gradle/Podfile, permissões de manifest, snippets Flutter/RN/nativo. A IA do app deve seguir este passo a passo literalmente para criar o projeto Firebase consistente com o backend.

Correções nesta versão:
- §3.5 `listar_reservas_usuario.php`: response shape corrigido — campos reais são `{id, data, valor, status, pode_excluir}` com enum `status: "Atual"|"Futura"|"Finalizada"`. O doc anterior listava campos que não existem (`valor_refeicao`, `horario_confirmacao`, `tipo_confirmacao`, `fora_horario`).
- §3.8 `listar_reservas_adicionais_usuario.php`: response shape corrigido — `valor_total` único (não `valor_refeicao`/`valor_marmitex` separados), `status` enum (não `"ativa"`), e backend agora também devolve `dependente_id`.
- §3.6: `horario.hora_atual` documentado corretamente como `HH:MM:SS`; adicionado erro `Já existe uma reserva adicional para este dependente nesta data`.
- §6.1 `dependentes/listar.php`: backend agora devolve `cobrar` (era usado pelo web mas faltava no response).
- §5.3 `historico_usuario.php`: deixado explícito que `justificativa` pode ser `null`.
- Nova §9 `dias_fechado/verificar.php` documentada (utilitário público).
- §1.9 (escopo — estoque é web-only), §1.10 (gating de módulos, com regra para culto) e §1.11 (dropdowns).
- §2.1 `login.php`: response agora inclui `user.culto` (flag de participação no culto) — sem isso o app não tinha como gatear corretamente o menu de culto.
- Refletido `dias_fechado` em `reservar.php`/`reservar_multiplo.php`/`acesso_especial/criar_reserva.php` (commits `8852ef3`, `b96d437`).
