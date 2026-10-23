# SaborPro

**Sistema Inteligente de Pedidos e Gestão para Restaurante / Cafetaria**
PHP puro + MySQL + HTML5 + CSS3 + JavaScript (vanilla). Sem frameworks, sem NPM, sem CDNs.

A aplicação está dividida em **duas áreas com linguagens visuais distintas**:

| Área | Ficheiros | Tema | Acesso |
|---|---|---|---|
| 🍷 **Site público do restaurante** | `index.php`, `menu.php` | Claro de luxo — creme `#FAFAF9`, dourado `#D97706`, vinho `#991B1B` | Livre — encomendas sem registo |
| 🖥️ **Portal de gestão** | `gestao.php`, `dashboard.php`, `mesas.php`, `ementa.php`, `pedidos.php`, `cozinha.php` | Técnico claro — `#F1F5F9`, cartões brancos, sombras finas | Sessão autenticada |

---

## 1. Estrutura do projeto

```
SaborPro/
├── base_dados.sql            # BD (utf8mb4) + 12 pratos + pedidos e avaliações de demonstração
├── ligacao.php               # MySQLi + sessão + helpers UTF-8 + migração idempotente
├── index.php                 # SITE PÚBLICO: hero, história, ementa, avaliações, carrinho, PT/EN
├── menu.php                  # EMENTA DEDICADA (PT/EN) + pedido integrado (mesa ou takeaway)
├── gestao.php                # PORTAL: login + painel de operações
├── dashboard.php             # Atalho para o painel (mesmo conteúdo)
├── mesas.php                 # VISTA ÚNICA "MESAS" — mesas de madeira, número e estado
├── ementa.php                # CRUD de itens/categorias, etiquetas, destaque e fotografia
├── pedidos.php               # POS interno (serviço de mesa)
├── cozinha.php               # KDS — ecrã da cozinha com avanço de estado em 1 clique
├── pedidos_publicos.php      # Endpoint JSON dos pedidos do site (sem login)
├── logout.php                # Encerramento seguro de sessão
├── api/
│   ├── pedido.php            # Registo de pedidos do POS interno
│   ├── mesa.php              # Planta 2D: detalhe, adicionar item, fechar conta, mudar estado
│   ├── avaliacao.php         # Submissão pública de avaliações (publicada imediatamente)
│   ├── avaliacao_admin.php   # Moderação: publicar/ocultar e eliminar avaliações (sessão admin)
│   └── ementa_rapida.php     # Edição rápida de preço/stock (JSON, usada no painel)
├── includes/
│   ├── cabecalho.php         # Cabeçalho/nav do portal
│   ├── rodape.php            # Rodapé do portal
│   ├── painel_resumo.php     # Dashboard: KPIs, gráficos SVG, monitor de pedidos e avaliações
│   └── icones.php            # Ícones SVG inline + logótipo SaborPro + estrelas
├── css/estilo.css            # Design system com duas zonas de estilo + planta + gráficos
└── js/main.js                # Carrinhos, modais, PT/EN, avaliações, filtros, toast
```

## 2. Duplo tema

O CSS define o **tema público** em `:root` e o **tema de gestão** no bloco `.tema-gestao`,
que sobrescreve variáveis (cores, raios, sombras, fundo, tabelas). Cada página aplica a zona no `<body>`:

```html
<body class="tema-publico">   <!-- site do cliente -->
<body class="tema-gestao">    <!-- portal de gestão -->
```

Tipografia: títulos com pilha serifada (`Georgia`) no site público; sans-serif condensada no portal.
O fundo do portal tem uma grelha técnica subtil e os cartões usam `border: 1px solid #E2E8F0`
com sombra de 1 px.

## 3. Site público (cliente)

- **Logótipo SaborPro** em SVG inline: cloche estilizada em dourado `#D97706`,
  «Sabor» em tipografia serifada e etiqueta/badge «Pro».
- **Hero fotográfico** com overlay em gradiente, tipografia serifada, selo circular e botão
  *«Explorar Ementa»*; horário, morada e telefone em rodapé de hero.
- **A Nossa História** — três fotografias reais (sala, cozinha, prato), texto editorial,
  assinatura da chef e indicadores (28 anos, 5 produtores, nota média real das avaliações).
- **Ementa digital** — cartões com fotografia real (Unsplash), preço sobre a imagem,
  selo *Recomendação do Chef*, etiquetas (`Vegetariano`, `Novidade`, `Sem Glúten`, `Picante`).
  Filtros por categoria e pesquisa em tempo real.
- **Página de ementa dedicada (`menu.php`)** — vista limpa e focada no cliente, com lista de
  pratos, procura, filtros por categoria e um resumo de pedido sempre à vista:
  - quantidades ajustáveis directamente em cada prato (stepper `− 0 +`);
  - **Na Mesa** (escolha do número da mesa) ou **Takeaway**;
  - nome, telefone e observações, totais e envio imediato para a cozinha;
  - nomes e descrições dos pratos totalmente bilingues (PT/EN), guardados em
    `ementa.nome_en` / `ementa.descricao_en`.
- **Avaliações publicadas de imediato** — média em estrelas (ex.: `4,8/5`), cartões de testemunhos
  e modal para o cliente deixar nome, 1–5 estrelas e comentário. A avaliação é gravada com
  `visivel = 1`, aparece **no topo da lista em tempo real** (sem recarregar) e actualiza a média
  e o contador; a equipa pode ocultá-la ou eliminá-la depois na gestão.
- **Seletor de idioma PT | EN** no cabeçalho (JavaScript + `localStorage`), traduzindo as
  frases principais do site e, na ementa, também os **nomes e descrições de todos os pratos**.
- **Carrinho flutuante** (gaveta lateral) **sem login**:
  - Takeaway ou Na Mesa (com número da mesa);
  - nome, telefone e observações;
  - envio via `pedidos_publicos.php` → entra directamente na cozinha como `Pendente`;
  - toast de confirmação com o total.
- **Acesso Reservado** discreto no cabeçalho e no rodapé → `gestao.php`.

As fotografias dos pratos vêm da coluna `ementa.imagem_url`. Se uma imagem falhar, o
`js/main.js` substitui-a por um marcador SVG local — o layout nunca quebra offline.

## 4. Portal de gestão

- `gestao.php` — ecrã de login em duas colunas (apresentação + cartão) e, após sessão, o painel.
- **Painel de operações**
  - KPIs: **Faturação do dia**, **Ocupação da sala (%)**, **Pedidos pendentes**, **Encomendas do site**;
  - **Monitor de pedidos do site** com barra pulsante e destaque azul nos takeaway por confirmar;
  - **Controlo de ementa** com preço editável em linha e interruptor de stock, gravando via
    `api/ementa_rapida.php` (JSON) sem recarregar a página;
  - **Gráficos leves** desenhados apenas com CSS Grid/Flexbox e SVG puro:
    **barras** de pedidos acumulados por hora do dia e **roscas** para a ocupação da sala
    e a distribuição dos 5 pratos mais vendidos (últimos 30 dias);
  - **Moderação de avaliações**: tabela com nota, comentário e estado; botões para
    publicar/ocultar e eliminar, via `api/avaliacao_admin.php` (JSON);
  - fluxo de pedidos por estado e últimas vendas;
  - actualização automática a cada 90 s, suspensa enquanto edita um campo ou tem um modal aberto.
- `mesas.php` — **vista única "Mesas"**, limpa e minimalista:
  - fundo branco `#FFFFFF` com grelha de pontos muito subtil e sem divisões de zona
    (foram removidas a varanda, o terraço, a cozinha, a entrada e o balcão);
  - **mesas de madeira** desenhadas em SVG (gradiente + veios) com o **número bem visível**
    ao centro e as cadeiras em tons de madeira;
  - **indicador circular de estado** junto a cada mesa: 🟢 verde *Livre*, 🔴 vermelho *Ocupada*
    (com pulsação) e 🟡 âmbar *Reservada*;
  - total activo sobre a mesa quando existe conta aberta;
  - clique abre a conta, permite adicionar pratos, alterar estado ou **fechar conta**.
- `ementa.php` — CRUD completo com fotografia (URL + pré-visualização), etiquetas e destaque.
- `pedidos.php` — POS interno com filtro de categorias e carrinho.
- `cozinha.php` — KDS com 1 clique: *Iniciar Preparação → Pronto → Servir/Entregar*.

## 5. Instalação no Laragon

1. Inicie **Apache** e **MySQL** no Laragon.
2. Importe `base_dados.sql`:
   - phpMyAdmin → Importar; **ou**
   - ```bash
     mysql -u root --default-character-set=utf8mb4 < base_dados.sql
     ```
3. Aceda a `http://localhost/SaborPro/`.

> **Nota:** importe sempre com `--default-character-set=utf8mb4` para não corromper acentos.
> O ficheiro já inclui `SET NAMES utf8mb4;`.

### Migração automática

`ligacao.php` executa uma migração **idempotente** que acrescenta colunas novas
(`ementa.etiquetas`, `ementa.destaque`, `ementa.imagem_url`, `ementa.nome_en`,
`ementa.descricao_en`, `pedidos.tipo_pedido`, `pedidos.cliente_nome`,
`pedidos.cliente_telefone`, `pedidos.observacoes`), cria a tabela `avaliacoes`
(se ainda não existir) e a mesa `Balcão` (nº 0). Bases de dados antigas continuam a funcionar;
se as colunas inglesas estiverem vazias, o site mostra o texto português como fallback.

## 6. Conta de demonstração

| Perfil | E-mail | Palavra-passe |
|---|---|---|
| Administração | `admin@saborpro.pt` | `123456` |

Existe **apenas esta conta** na tabela `utilizadores` (demonstração).

## 7. Migrar para servidor remoto

Edite `$CFG_REMOTO` em `ligacao.php` (host, utilizador, password, base). A detecção é automática:
`localhost` / `127.0.0.1` → credenciais locais; qualquer outro host → servidor remoto.
Também pode forçar via `SABORPRO_DB_HOST`, `SABORPRO_DB_USER`, `SABORPRO_DB_PASS` e `SABORPRO_DB_BASE`.

## 8. Notas técnicas

- Todo o SQL usa **prepared statements** (mysqli) com `utf8mb4`; `ligacao.php` força
  `set_charset('utf8mb4')`, `mb_internal_encoding('UTF-8')` e um cabeçalho HTTP UTF-8.
- Todas as páginas HTML incluem `<meta charset="UTF-8">`; os dados acentuados são lidos e
  escritos sem perda de caracteres.
- Os preços dos pedidos são recalculados **no servidor** a partir da tabela `ementa`.
- Senhas com `password_hash()` / `password_verify()`; `session_regenerate_id(true)` no login.
- Ícones, logótipo, estrelas, planta de sala e gráficos: **SVG inline / CSS puro**,
  sem bibliotecas externas nem CDNs.
- Única dependência externa: fotografias de pratos alojadas no Unsplash (`images.unsplash.com`).
  Todas as URLs do seed foram verificadas (`HTTP 200`, `image/jpeg`).
- O pedido público é gravado na tabela `pedidos` com `tipo_pedido = 'Takeaway' | 'Na Mesa'`
  e aparece imediatamente no painel e no ecrã da cozinha.
- As avaliações públicas têm honeypot anti-spam e um limite de 45 s por sessão; são **publicadas
  imediatamente** com `visivel = 1` e podem ser ocultadas ou eliminadas na moderação do portal.
- O pedido da página `menu.php` usa o mesmo endpoint `pedidos_publicos.php`; os preços são
  recalculados no servidor e o pedido entra na cozinha como `Pendente`.
- `base_dados.sql` inclui as colunas `ementa.nome_en` / `ementa.descricao_en` já preenchidas
  para os 12 pratos de demonstração.
