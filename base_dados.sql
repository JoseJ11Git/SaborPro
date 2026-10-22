-- =============================================================
--  SaborPro  ::  Sistema Inteligente de Pedidos e Gestão
--  Base de Dados MySQL/MariaDB  ::  utf8mb4
--  Compatible com Laragon (localhost) e servidor remoto
-- =============================================================

DROP DATABASE IF EXISTS saborpro;
CREATE DATABASE saborpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE saborpro;

-- Garante que os acentos são interpretados corretamente durante a importação
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -------------------------------------------------------------
-- Tabela: utilizadores
-- tipo: 'admin' | 'empregado' | 'cozinha'
-- -------------------------------------------------------------
CREATE TABLE utilizadores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(80)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    palavra_passe VARCHAR(255) NOT NULL,
    tipo          ENUM('admin','empregado','cozinha') NOT NULL DEFAULT 'empregado',
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: mesas
-- estado: 'Livre' | 'Ocupada' | 'Reservada'
-- -------------------------------------------------------------
CREATE TABLE mesas (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    numero    INT NOT NULL UNIQUE,
    capacidade INT NOT NULL DEFAULT 4,
    estado    ENUM('Livre','Ocupada','Reservada') NOT NULL DEFAULT 'Livre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: categorias
-- icone_svg: path SVG inline (nome do ícone) para render JS
-- -------------------------------------------------------------
CREATE TABLE categorias (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(60) NOT NULL,
    icone_svg VARCHAR(40) DEFAULT 'prato'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: ementa
-- -------------------------------------------------------------
CREATE TABLE ementa (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nome        VARCHAR(120) NOT NULL,
    descricao   VARCHAR(255) DEFAULT NULL,
    preco       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    disponivel  TINYINT(1) NOT NULL DEFAULT 1,
    etiquetas   VARCHAR(120) DEFAULT NULL,   -- ex.: 'vegetariano,novidade'
    destaque    TINYINT(1) NOT NULL DEFAULT 0, -- recomendação do chef
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ementa_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE CASCADE,
    INDEX idx_ementa_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: pedidos
-- estado: 'Pendente' | 'Em Preparação' | 'Pronto' | 'Entregue'
-- -------------------------------------------------------------
CREATE TABLE pedidos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id      INT NOT NULL,
    utilizador_id INT NOT NULL,
    total        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado       ENUM('Pendente','Em Preparação','Pronto','Entregue') NOT NULL DEFAULT 'Pendente',
    tipo_pedido  ENUM('Takeaway','Na Mesa') NOT NULL DEFAULT 'Na Mesa',
    cliente_nome VARCHAR(80) DEFAULT NULL,
    cliente_telefone VARCHAR(30) DEFAULT NULL,
    observacoes  VARCHAR(255) DEFAULT NULL,
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_mesa FOREIGN KEY (mesa_id)
        REFERENCES mesas(id) ON DELETE CASCADE,
    CONSTRAINT fk_pedido_utilizador FOREIGN KEY (utilizador_id)
        REFERENCES utilizadores(id) ON DELETE CASCADE,
    INDEX idx_pedido_estado (estado),
    INDEX idx_pedido_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: itens_pedido
-- -------------------------------------------------------------
CREATE TABLE itens_pedido (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id     INT NOT NULL,
    ementa_id     INT NOT NULL,
    quantidade    INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_ementa FOREIGN KEY (ementa_id)
        REFERENCES ementa(id) ON DELETE CASCADE,
    INDEX idx_item_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  DADOS DE TESTE
--  Nota: palavra_passe de todos = "123456"
-- =============================================================

-- Utilizadores ------------------------------------------------
-- hash bcrypt de '123456'
INSERT INTO utilizadores (nome, email, palavra_passe, tipo) VALUES
('Ana Ribeiro',      'admin@saborpro.pt',   '$2y$10$M2AkXsPX9yMM1pYjhlOVwOO5Tn87zXEGmNgWS0AVFzHedZQd0Wxtm', 'admin'),
('Bruno Costa',      'empregado@saborpro.pt','$2y$10$M2AkXsPX9yMM1pYjhlOVwOO5Tn87zXEGmNgWS0AVFzHedZQd0Wxtm', 'empregado'),
('Chef Miguel',      'cozinha@saborpro.pt', '$2y$10$M2AkXsPX9yMM1pYjhlOVwOO5Tn87zXEGmNgWS0AVFzHedZQd0Wxtm', 'cozinha');

-- Mesas --------------------------------------------------------
-- A mesa 0 é o "Balcão" (usada para pedidos Takeaway do site público)
INSERT INTO mesas (numero, capacidade, estado) VALUES
(0, 1, 'Livre'),
(1, 2, 'Ocupada'),
(2, 4, 'Livre'),
(3, 4, 'Livre'),
(4, 6, 'Ocupada'),
(5, 8, 'Reservada'),
(6, 2, 'Livre');

-- Categorias ---------------------------------------------------
INSERT INTO categorias (nome, icone_svg) VALUES
('Entradas',  'entrada'),
('Pratos',    'prato'),
('Sobremesas','doce'),
('Bebidas',   'bebida');

-- Ementa -------------------------------------------------------
-- etiquetas: 'vegetariano' | 'novidade' | 'picante' | 'sem-gluten' (separadas por vírgula)
-- destaque: 1 = recomendação do chef
INSERT INTO ementa (categoria_id, nome, descricao, preco, disponivel, etiquetas, destaque) VALUES
(1, 'Bruschetta de Tomate',        'Pão rústico, tomate confitado, manjericão e azeite',        5.50, 1, 'vegetariano', 0),
(1, 'Croquetes de Camarão',        'Camarão crocante com aioli de limão',                        7.90, 1, 'novidade', 0),
(2, 'Bacalhau à Lagareiro',        'Batatas fritas, azeitonas, coentros e molho de vinho tinto', 16.50, 1, 'chef', 1),
(2, 'Frango do Campo Grelhado',    'Peito de frango marinado, salada fresca e arroz de erva',    13.90, 1, '', 0),
(2, 'Risotto de Fungos',           'Risotto cremoso com portobelo e parmesão ralado',           14.90, 1, 'vegetariano,chef', 0),
(3, 'Mousse de Chocolate',         'Mousse aveludada de chocolate 70% com mirtilos',             4.80, 1, 'vegetariano', 0),
(3, 'Leite Cremoso',               'Leite doce cremoso com caramelo e flor de sal',              3.50, 1, 'vegetariano', 0),
(4, 'Café Expresso',               'Café curto, encorpado, torra fina da casa',                  1.40, 1, '', 0),
(4, 'Limonada de Hortelã',         'Limão fresco, hortelã e água com gás',                       3.20, 1, 'novidade,vegetariano', 0);

-- Pedidos de demonstração ---------------------------------------
INSERT INTO pedidos (mesa_id, utilizador_id, total, estado, tipo_pedido, cliente_nome, cliente_telefone, criado_em) VALUES
((SELECT id FROM mesas WHERE numero = 1), 2, 24.40, 'Pendente',       'Na Mesa',  'Cliente Mesa 1', '912345678', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
((SELECT id FROM mesas WHERE numero = 4), 2, 22.90, 'Em Preparação',  'Na Mesa',  'Cliente Mesa 4', '912345679', DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
((SELECT id FROM mesas WHERE numero = 0), 2,  4.80, 'Pendente',       'Takeaway', 'Maria Silva',   '912345680', DATE_SUB(NOW(), INTERVAL 3 MINUTE));

INSERT INTO itens_pedido (pedido_id, ementa_id, quantidade, preco_unitario) VALUES
(1, 8,  2, 1.40),
(1, 2,  1, 7.90),
(1, 3,  1, 16.50),
(1, 9,  1, 3.20),
(2, 4,  1, 13.90),
(2, 6,  1, 4.80),
(2, 8,  1, 1.40),
(2, 9,  1, 3.20),
(3, 6,  1, 4.80);
