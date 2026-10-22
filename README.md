# SaborPro

**Sistema Inteligente de Pedidos e Gestão para Restaurante / Cafetaria**
PHP puro + MySQL + CSS3 + Vanilla JavaScript. Sem frameworks, sem CDNs, sem dependências externas.

A aplicação está dividida em **duas áreas totalmente distintas**:

| Área | Ficheiros | Tema | Acesso |
|---|---|---|---|
| 🌤️ **Site público do restaurante** | `index.php` | Claro e acolhedor (`#FDFBF7` / `#1C1917`) | Livre — encomendas sem registo |
| 🌙 **Portal de gestão** | `gestao.php`, `dashboard.php`, `mesas.php`, `ementa.php`, `pedidos.php`, `cozinha.php` | Escuro e profissional (`#0F172A`) | Apenas utilizadores autenticados |

---

## 1. Estrutura do projeto

```
SaborPro/
├── base_dados.sql            # Criação da BD (utf8mb4) + dados de demonstração
├── ligacao.php               # Ligação MySQLi + sessão + helpers + migração idempotente
├── index.php                 # SITE PÚBLICO: hero, história, ementa, carrinho sem login
├── gestao.php                # PORTAL: login da equipa + painel principal
├── dashboard.php             # Atalho para o painel de gestão (mesmo conteúdo)
├── mesas.php                 # SIMULADOR DE PLANTA DE SALA 2D (mesas clicáveis)
├── ementa.php                # CRUD de itens e categorias + etiquetas + destaque
├── pedidos.php               # Ecrã POS interno (service de mesa)
├── cozinha.php               # KDS — ecrã da cozinha com avanço de estado em 1 clique
├── pedidos_publicos.php      # Endpoint JSON dos pedidos do site público (sem login)
├── logout.php                # Encerramento seguro de sessão
├── api/
│   ├── pedido.php            # Registo de pedidos do POS interno
│   └── mesa.php              # API da planta 2D (detalhe, adicionar, fechar conta)
├── includes/
│   ├── cabecalho.php         # Cabeçalho/nav do portal (tema escuro)
│   ├── rodape.php            # Rodapé do portal
│   ├── painel_resumo.php     # Conteúdo do dashboard (partilhado por gestao/dashboard)
│   └── icones.php            # Ícones SVG 100% inline (sem bibliotecas)
├── css/estilo.css            # Design system com DUAS zonas de estilo
└── js/main.js                # Carrinhos, modais, alertas, validação, filtros, toast
```

## 2. Como está implementado o "duplo tema"

O CSS define o **tema público** em `:root` (claro/quente) e o **tema de gestão** num bloco
`.tema-gestao` que sobrescreve todas as variáveis (cores, sombras, fundos das tabelas, etc.).
Cada página aplica a zona correspondente no `<body>`:

```html
<body class="tema-publico">   <!-- site do cliente -->
<body class="tema-gestao">    <!-- portal de gestão -->
```

As restantes páginas do portal usam `includes/cabecalho.php`, que já aplica `tema-gestao`.

## 3. Site público (cliente)

- **Hero** com saudação, horário, moração e botão *«Ver Ementa & Pedir»*.
- **A Nossa História** — filosofia da cozinha, produto local e valores.
- **Ementa digital** — cartões com cor por categoria (Entradas = Laranja, Pratos = Vermelho,
  Bebidas = Azul, Sobremesas = Dourado), etiquetas (`Vegetariano`, `Novidade`, `Escolha do Chef`,
  `Picante`, `Sem Glúten`) e botão **+** animado.
- **Carrinho flutuante** (gaveta lateral animada) **sem login**:
  - escolha entre **Takeaway** e **Na Mesa** (com número da mesa);
  - nome, telefone e observações;
  - envio via `pedidos_publicos.php` → o pedido entra directamente na cozinha (estado `Pendente`);
  - notificação de sucesso animada (toast).
- **Acesso Reservado** discreto no cabeçalho e no rodapé → abre o login do portal.

## 4. Portal de gestão

- `gestao.php` — sem sessão mostra o **ecrã de login** (tema escuro); com sessão, o **painel principal**:
  Total Faturado Hoje, Pedidos Ativos, Ocupação de Mesas, Prato Mais Vendido, últimas vendas
  (distinguindo Takeaway de Na Mesa) e distribuição por estado.
- `mesas.php` — **planta da sala em 2D** com mesas redondas e retangulares posicionadas em coordenadas
  percentuais, côzinha/balcão/varanda desenhados, cores dinâmicas por estado e **luz pulsante**
  nas mesas ocupadas. Clicar numa mesa abre um modal com a conta, atalho para adicionar itens e
  botão **Fechar conta**.
- `ementa.php` — CRUD completo, etiquetas, destaque do chef, disponibilidade e preços.
- `pedidos.php` — POS interno com filtro de categorias e carrinho.
- `cozinha.php` — KDS com 1 clique: *Iniciar Preparação → Marcar como Pronto → Servir/Entregar*.

## 5. Instalação no Laragon

1. Inicie **Apache** e **MySQL** no Laragon.
2. Importe `base_dados.sql`:
   - phpMyAdmin → Importar; **ou**
   - ```bash
     mysql -u root --default-character-set=utf8mb4 < base_dados.sql
     ```
3. Aceda a `http://localhost/SaborPro/`.

> **Nota:** importe sempre com `--default-character-set=utf8mb4` (ou pelo phpMyAdmin) para não
> corromper os acentos. O ficheiro já inclui `SET NAMES utf8mb4;`.

### Migração automática

`ligacao.php` executa uma migração **idempotente** que adiciona as colunas novas
(`ementa.etiquetas`, `ementa.destaque`, `pedidos.tipo_pedido`, `pedidos.cliente_nome`,
`pedidos.cliente_telefone`, `pedidos.observacoes`) e a mesa `Balcão` (nº 0) caso ainda não existam.
Assim, bases de dados instaladas antes da refatorização continuam a funcionar.

## 6. Contas de demonstração

| Perfil | E-mail | Palavra-passe |
|---|---|---|
| Administração | `admin@saborpro.pt` | `123456` |
| Empregado (mesa) | `empregado@saborpro.pt` | `123456` |
| Cozinha (KDS) | `cozinha@saborpro.pt` | `123456` |

## 7. Migrar para servidor remoto

Edite o array `$CFG_REMOTO` em `ligacao.php` (host `lab.csmiguel.pt`, utilizador, password, base).
A deteção é automática: `localhost` / `127.0.0.1` → credenciais locais; qualquer outro host →
credenciais do servidor remoto. Também é possível forçar via `SABORPRO_DB_HOST`,
`SABORPRO_DB_USER`, `SABORPRO_DB_PASS` e `SABORPRO_DB_BASE`.

## 8. Notas técnicas

- Todos os SQL usam **prepared statements** (mysqli) com `utf8mb4`.
- Os preços dos pedidos são sempre recalculados **no servidor** a partir da tabela `ementa`.
- Senhas com `password_hash()` / `password_verify()`; `session_regenerate_id(true)` no login.
- Ícones: SVG inline em `includes/icones.php` — nenhuma dependência externa.
- O pedido público é gravado na mesma tabela `pedidos`, com `tipo_pedido = 'Takeaway' | 'Na Mesa'`,
  e aparece imediatamente no ecrã da cozinha.
