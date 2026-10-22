# SaborPro

**Sistema Inteligente de Pedidos e Gestão para Restaurante / Cafetaria**
PHP puro + MySQL + CSS3 + Vanilla JavaScript. Sem frameworks, sem CDNs, sem dependências externas.

---

## 1. Estrutura do projeto

```
SaborPro/
├── base_dados.sql          # Script de criação da BD (utf8mb4) + dados de demonstração
├── ligacao.php             # Ligação MySQLi + sessão + funções auxiliares
├── index.php               # Landing page + modal de login
├── dashboard.php           # Painel: estatísticas, faturamento, ocupação, últimas vendas
├── mesas.php               # Mapa visual de mesas (estados, abrir pedidos, liberar contas)
├── ementa.php              # CRUD de itens e categorias, preços e disponibilidade
├── pedidos.php             # Ecrã POS (filtro de categorias + carrinho)
├── cozinha.php             # KDS — ecrã da cozinha com avanço de estado em 1 clique
├── logout.php              # Encerramento seguro de sessão
├── api/pedido.php          # Endpoint JSON (registo de pedidos a partir do POS)
├── includes/
│   ├── cabecalho.php       # Cabeçalho e navegação comum
│   ├── rodape.php          # Rodapé comum
│   └── icones.php          # Ícones SVG 100% inline (sem bibliotecas)
├── css/estilo.css          # Design system completo (variáveis, grid, animações)
└── js/main.js              # Carrinho, modais, alertas, validação de formulários
```

## 2. Instalação no Laragon

1. Inicie os serviços **Apache** e **MySQL** no Laragon.
2. Importe `base_dados.sql`:
   - via phpMyAdmin (`http://localhost/phpmyadmin`) → Importar → escolher o ficheiro; **ou**
   - via consola:
     ```bash
     mysql -u root --default-character-set=utf8mb4 < base_dados.sql
     ```
3. Aceda a `http://localhost/SaborPro/` (ou `http://saborpro.test/`).

> **Nota importante:** importe sempre com `--default-character-set=utf8mb4` (ou pelo phpMyAdmin),
> caso contrário os acentos ficam corrompidos. O ficheiro já inclui `SET NAMES utf8mb4;`.

## 3. Contas de demonstração

| Perfil     | E-mail                    | Palavra-passe |
|------------|---------------------------|---------------|
| Administração | `admin@saborpro.pt`    | `123456`      |
| Empregado  | `empregado@saborpro.pt`   | `123456`      |
| Cozinha    | `cozinha@saborpro.pt`     | `123456`      |

## 4. Migrar para servidor remoto

Edite o array `$CFG_REMOTO` em `ligacao.php` (host `lab.csmiguel.pt`, utilizador, password e base).
A deteção é automática: se o endereço pedido for `localhost` / `127.0.0.1` são usadas as credenciais
locais; caso contrário são usadas as do servidor remoto. Também é possível forçar via variáveis de
ambiente: `SABORPRO_DB_HOST`, `SABORPRO_DB_USER`, `SABORPRO_DB_PASS`, `SABORPRO_DB_BASE`.

## 5. Fluxo de trabalho

1. **Mesas** — abre a conta de uma mesa (a mesa passa a `Ocupada`).
2. **Pedidos (POS)** — seleciona a mesa e os artigos; o total é calculado em tempo real.
3. **Cozinha (KDS)** — o pedido aparece como `Pendente`; o chef avança para `Em Preparação`,
   `Pronto` e `Entregue` com um clique (a mesa é libertada automaticamente).
4. **Painel** — faturamento do dia, pedidos ativos, ocupação de mesas e prato mais vendido.

## 6. Notas técnicas

- Todos os pedidos SQL usam **prepared statements** (`mysqli`), com `utf8mb4` e charset correto.
- Os preços são sempre validados **no servidor** a partir da tabela `ementa` (nunca são confiados
  ao cliente).
- As senhas são guardadas com `password_hash()` / verificadas com `password_verify()`.
- O `session_regenerate_id(true)` é usado no login para evitar fixação de sessão.
- Ícones: SVG inline em `includes/icones.php` (função `icone()`), sem qualquer dependência externa.
