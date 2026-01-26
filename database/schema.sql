SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS jwt_blocklist;
DROP TABLE IF EXISTS error_logs;
DROP TABLE IF EXISTS user_verifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS persons;

SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE persons (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  email VARCHAR(128) NOT NULL UNIQUE,
  phone VARCHAR(20),
  cpfcnpj VARCHAR(14) UNIQUE,
  avatar_url VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_cpfcnpj (cpfcnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE users (
  id BIGINT PRIMARY KEY,
  role_id BIGINT NOT NULL DEFAULT 1,
  password VARCHAR(255) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  is_verified BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id) REFERENCES persons(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  INDEX idx_role_id (role_id),
  INDEX idx_is_active (is_active),
  INDEX idx_is_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE user_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token VARCHAR(36) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_token (token),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE password_resets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  code VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_code (code),
  INDEX idx_expires_at (expires_at),
  UNIQUE INDEX idx_code_user_id_expires_at (code, user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE error_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  severity VARCHAR(50) NOT NULL,
  message TEXT NOT NULL,
  context JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  resolved_by BIGINT NULL DEFAULT NULL,
  INDEX idx_severity (severity),
  INDEX idx_created_at (created_at),
  INDEX idx_resolved_at (resolved_at),
  FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE jwt_blocklist (
  jti VARCHAR(255) NOT NULL PRIMARY KEY,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO roles (name, description) VALUES 
('customer', 'Cliente final que utiliza os serviços do sistema'),
('user', 'Funcionário que presta atendimento aos clientes'),
('admin', 'Administrador com acesso total ao sistema');

INSERT INTO persons (id, name, email, created_at, updated_at) VALUES 
(1, 'Admin User', 'admin@example.com', NOW(), NOW());

INSERT INTO users (id, role_id, password, is_active, is_verified, created_at, updated_at) VALUES 
(1, (SELECT id FROM roles WHERE name = 'admin'), '$2y$10$j6qZQjLtphDjG8Y5ZW0LDOu4TCxxE3K63CgCxw1f.hmC8lg.81i3e', TRUE, TRUE, NOW(), NOW());