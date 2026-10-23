-- =============================================================
--  SaborPro  ::  Sistema Inteligente de Pedidos e Gestão
--  Base de Dados MySQL/MariaDB  ::  utf8mb4
--  Compatível com Laragon (localhost) e servidor remoto
-- =============================================================

DROP DATABASE IF EXISTS saborpro;
CREATE DATABASE saborpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE saborpro;

-- Garante que os acentos são interpretados corretamente durante a importação
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -------------------------------------------------------------
-- Tabela: utilizadores
-- Demonstração: apenas uma conta de administração.
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
    etiquetas   VARCHAR(120) DEFAULT NULL,     -- ex.: 'vegetariano,novidade'
    destaque    TINYINT(1) NOT NULL DEFAULT 0, -- recomendação do chef
    imagem_url  VARCHAR(500) DEFAULT NULL,     -- fotografia real (Unsplash)
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
--  DADOS DE DEMONSTRAÇÃO
--  Conta única: admin@saborpro.pt / 123456
-- =============================================================

-- Utilizadores ------------------------------------------------
-- hash bcrypt de '123456'
INSERT INTO utilizadores (nome, email, palavra_passe, tipo) VALUES
('Ana Ribeiro', 'admin@saborpro.pt', '$2y$10$M2AkXsPX9yMM1pYjhlOVwOO5Tn87zXEGmNgWS0AVFzHedZQd0Wxtm', 'admin');

-- Mesas --------------------------------------------------------
-- A mesa 0 é o "Balcão" (usada para pedidos Takeaway do site público)
INSERT INTO mesas (numero, capacidade, estado) VALUES
(0, 1, 'Livre'),
(1, 2, 'Ocupada'),
(2, 4, 'Livre'),
(3, 4, 'Livre'),
(4, 6, 'Ocupada'),
(5, 8, 'Reservada'),
(6, 4, 'Livre');

-- Categorias ---------------------------------------------------
INSERT INTO categorias (nome, icone_svg) VALUES
('Entradas',   'entrada'),
('Pratos',     'prato'),
('Sobremesas', 'doce'),
('Bebidas',    'bebida');

-- Ementa -------------------------------------------------------
-- etiquetas: 'vegetariano' | 'novidade' | 'chef' | 'picante' | 'sem-gluten'
-- destaque: 1 = recomendação do chef
-- imagem_url: fotografia real Unsplash (verificada, HTTP 200 / image/jpeg)
INSERT INTO ementa (categoria_id, nome, descricao, preco, disponivel, etiquetas, destaque, imagem_url) VALUES
(1, 'Bruschetta de Tomate',      'Pão rústico tostado, tomate confitado, manjericão e azeite virgem', 5.50, 1, 'vegetariano', 0,
 'https://images.unsplash.com/photo-1547592180-85f173990554?w=900&q=80&auto=format&fit=crop'),
(1, 'Croquetes de Camarão',      'Camarão crocante com aioli de limão e pimenta rosa',                7.90, 1, 'novidade', 0,
 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=900&q=80&auto=format&fit=crop'),
(2, 'Bacalhau à Lagareiro',      'Lombo de bacalhau, batata a murro, azeitonas e molho de vinho tinto', 16.50, 1, 'chef,sem-gluten', 1,
 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=900&q=80&auto=format&fit=crop'),
(2, 'Frango do Campo Grelhado',  'Peito de frango marinado, salada fresca e arroz de ervas',         13.90, 1, '', 0,
 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=900&q=80&auto=format&fit=crop'),
(2, 'Risotto de Fungos',         'Risotto cremoso com portobelo e parmesão ralado na hora',          14.90, 1, 'vegetariano,chef', 1,
 'https://images.unsplash.com/photo-1488900128323-21503983a07e?w=900&q=80&auto=format&fit=crop'),
(2, 'Hambúrguer Gourmet',        'Carne de novilho, queijo curado, cebola caramelizada e batata rústica', 12.50, 1, 'chef', 1,
 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=900&q=80&auto=format&fit=crop'),
(2, 'Tagliatelle al Tartufo',    'Massa fresca, creme de trufa, parmesão e pimenta preta',           15.90, 1, 'novidade,vegetariano', 0,
 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=900&q=80&auto=format&fit=crop'),
(2, 'Salada do Mercado',         'Folhas da estação, tomate, pepino, sementes e vinagrete de mostarda', 9.90, 1, 'vegetariano,sem-gluten', 0,
 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=900&q=80&auto=format&fit=crop'),
(3, 'Mousse de Chocolate',       'Mousse aveludada de chocolate 70% com mirtilos frescos',            4.80, 1, 'vegetariano', 0,
 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=900&q=80&auto=format&fit=crop'),
(3, 'Leite Cremoso',             'Leite doce cremoso com caramelo e flor de sal',                     3.50, 1, 'vegetariano,sem-gluten', 0,
 'https://images.unsplash.com/photo-1495147466023-ac5c588e2e94?w=900&q=80&auto=format&fit=crop'),
(4, 'Café Expresso',             'Café curto, encorpado, torra fina da casa',                         1.40, 1, '', 0,
 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=900&q=80&auto=format&fit=crop'),
(4, 'Limonada de Hortelã',       'Limão fresco, hortelã do quintal e água com gás',                   3.20, 1, 'novidade,vegetariano', 0,
 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=900&q=80&auto=format&fit=crop');

-- Pedidos de demonstração ---------------------------------------
INSERT INTO pedidos (mesa_id, utilizador_id, total, estado, tipo_pedido, cliente_nome, cliente_telefone, criado_em) VALUES
((SELECT id FROM mesas WHERE numero = 1), 1, 30.40, 'Pendente',      'Na Mesa',  'Cliente Mesa 1', '912345678', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
((SELECT id FROM mesas WHERE numero = 4), 1, 23.30, 'Em Preparação', 'Na Mesa',  'Cliente Mesa 4', '912345679', DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
((SELECT id FROM mesas WHERE numero = 0), 1,  4.80, 'Pendente',      'Takeaway', 'Maria Silva',    '912345680', DATE_SUB(NOW(), INTERVAL 3 MINUTE));

INSERT INTO itens_pedido (pedido_id, ementa_id, quantidade, preco_unitario) VALUES
(1, 11, 2, 1.40),
(1, 2,  1, 7.90),
(1, 3,  1, 16.50),
(1, 12, 1, 3.20),
(2, 4,  1, 13.90),
(2, 9,  1, 4.80),
(2, 11, 1, 1.40),
(2, 12, 1, 3.20),
(3, 9,  1, 4.80);
