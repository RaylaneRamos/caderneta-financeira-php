-- caderneta SQL schema (MySQL)
CREATE DATABASE IF NOT EXISTS caderneta DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE caderneta;

-- usuarios
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- entradas (receitas)
CREATE TABLE IF NOT EXISTS entradas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  categoria VARCHAR(120),
  descricao TEXT,
  data DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- saidas (despesas)
CREATE TABLE IF NOT EXISTS saidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  categoria VARCHAR(120),
  descricao TEXT,
  data DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- example user (password: senha123)
INSERT INTO usuarios (name, email, password_hash) VALUES
('Usuário Exemplo', 'teste@local.test', '$2y$10$e0NR5s3j1rjVewI8F2n/0Oq3kG5g3u0f9vQf3bQpYbS1rYvQfV1yO');
-- The hash above corresponds to 'senha123' (bcrypt). If import fails on strict modes, create user via register.php
