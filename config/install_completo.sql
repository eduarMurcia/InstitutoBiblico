-- ═════════════════════════════════════════════════════════
-- Instituto Bíblico Bautista — LMS
-- install_completo.sql — Instalación limpia completa
-- Versión: v8 (junio 2026)
-- ═════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Usuarios ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`       VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `rol`          ENUM('admin','estudiante') DEFAULT 'estudiante',
  `activo`       TINYINT(1) DEFAULT 1,
  `notif_email`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Cursos ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cursos` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `titulo`         VARCHAR(200) NOT NULL,
  `descripcion`    TEXT,
  `imagen`         VARCHAR(255) DEFAULT NULL,
  `color_portada`  VARCHAR(7) DEFAULT '#1a2744',
  `icono_portada`  VARCHAR(10) DEFAULT '📖',
  `instructor`     VARCHAR(150) DEFAULT NULL,
  `orden`          INT DEFAULT 0,
  `publicado`      TINYINT(1) DEFAULT 0,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Módulos ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `modulos` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `curso_id`  INT NOT NULL,
  `titulo`    VARCHAR(200) NOT NULL,
  `orden`     INT DEFAULT 0,
  FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Lecciones ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lecciones` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `modulo_id`    INT NOT NULL,
  `titulo`       VARCHAR(200) NOT NULL,
  `descripcion`  TEXT,
  `archivo_audio` VARCHAR(255) DEFAULT NULL,
  `archivo_pdf`  VARCHAR(255) DEFAULT NULL,
  `duracion`     VARCHAR(20) DEFAULT NULL,
  `orden`        INT DEFAULT 0,
  FOREIGN KEY (`modulo_id`) REFERENCES `modulos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Progreso ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `progreso` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`       INT NOT NULL,
  `leccion_id`       INT NOT NULL,
  `completado`       TINYINT(1) DEFAULT 0,
  `fecha_completado` DATETIME DEFAULT NULL,
  UNIQUE KEY `unico_progreso` (`usuario_id`, `leccion_id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leccion_id`) REFERENCES `lecciones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Comentarios ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `leccion_id`       INT NOT NULL,
  `usuario_id`       INT NOT NULL,
  `mensaje`          TEXT NOT NULL,
  `respuesta_pastor` TEXT DEFAULT NULL,
  `fecha_respuesta`  DATETIME DEFAULT NULL,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`leccion_id`) REFERENCES `lecciones`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Exámenes ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `examenes` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `modulo_id`       INT NOT NULL,
  `titulo`          VARCHAR(200) NOT NULL,
  `descripcion`     TEXT,
  `puntaje_minimo`  INT DEFAULT 60,
  `max_intentos`    INT DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`modulo_id`) REFERENCES `modulos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Preguntas ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `preguntas` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `examen_id`          INT NOT NULL,
  `pregunta`           TEXT NOT NULL,
  `tipo`               ENUM('multiple','verdadero_falso','abierta','archivo') DEFAULT 'multiple',
  `opcion_a`           VARCHAR(500) DEFAULT NULL,
  `opcion_b`           VARCHAR(500) DEFAULT NULL,
  `opcion_c`           VARCHAR(500) DEFAULT NULL,
  `opcion_d`           VARCHAR(500) DEFAULT NULL,
  `respuesta_correcta` VARCHAR(10) DEFAULT NULL,
  `orden`              INT DEFAULT 0,
  FOREIGN KEY (`examen_id`) REFERENCES `examenes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Resultados de examen ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `resultados_examen` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`         INT NOT NULL,
  `examen_id`          INT NOT NULL,
  `puntaje`            DECIMAL(5,2) DEFAULT 0,
  `puntaje_final`      DECIMAL(5,2) DEFAULT NULL,
  `aprobado`           TINYINT(1) DEFAULT 0,
  `pendiente_revision` TINYINT(1) DEFAULT 0,
  `archivo_general`    VARCHAR(255) DEFAULT NULL,
  `fecha`              DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`examen_id`) REFERENCES `examenes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Respuestas de examen (por pregunta) ───────────────────
CREATE TABLE IF NOT EXISTS `respuestas_examen` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`        INT NOT NULL,
  `examen_id`         INT NOT NULL,
  `pregunta_id`       INT NOT NULL,
  `respuesta_texto`   TEXT,
  `archivo_respuesta` VARCHAR(255) DEFAULT NULL,
  `calificacion`      DECIMAL(5,2) DEFAULT NULL,
  `comentario_pastor` TEXT DEFAULT NULL,
  `revisado`          TINYINT(1) DEFAULT 0,
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unico_respuesta` (`usuario_id`, `pregunta_id`),
  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`examen_id`)   REFERENCES `examenes`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Certificados ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `certificados` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `curso_id`   INT NOT NULL,
  `numero`     VARCHAR(20) NOT NULL,
  `fecha`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unico_certificado` (`usuario_id`, `curso_id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`curso_id`)   REFERENCES `cursos`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notificaciones ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `tipo`       ENUM('comentario','entrega','sistema') NOT NULL DEFAULT 'sistema',
  `titulo`     VARCHAR(120) NOT NULL,
  `mensaje`    TEXT,
  `url`        VARCHAR(255) DEFAULT NULL,
  `leida`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Usuario admin inicial ─────────────────────────────────
-- Contraseña: admin123 (cambiar inmediatamente)
INSERT IGNORE INTO `usuarios` (nombre, email, password, rol)
VALUES ('Administrador', 'admin@institutobiblicobautistacolombia.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ── Notificaciones del estudiante ─────────────────────────
CREATE TABLE IF NOT EXISTS `notificaciones_estudiante` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `tipo`       ENUM('comentario_respondido','examen_calificado') NOT NULL,
  `titulo`     VARCHAR(150) NOT NULL,
  `mensaje`    TEXT,
  `url`        VARCHAR(255) DEFAULT NULL,
  `leida`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Recuperación de contraseña ────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(100) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
