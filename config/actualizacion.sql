-- ─────────────────────────────────────────
-- Ministerio de Imprenta RV 1865 — LMS
-- ACTUALIZACIÓN v2 — Ejecutar en phpMyAdmin
-- (Solo si ya tienes install.sql corriendo)
-- ─────────────────────────────────────────

-- 1. PDF en lecciones
ALTER TABLE `lecciones`
  ADD COLUMN IF NOT EXISTS `archivo_pdf` VARCHAR(255) DEFAULT NULL;

-- 2. Tipo de pregunta
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `tipo`
  ENUM('multiple','verdadero_falso','abierta','archivo') DEFAULT 'multiple';

-- 3. Tabla de respuestas (todos los tipos)
CREATE TABLE IF NOT EXISTS `respuestas_examen` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`        INT NOT NULL,
  `examen_id`         INT NOT NULL,
  `pregunta_id`       INT NOT NULL,
  `respuesta_texto`   TEXT,
  `archivo_respuesta` VARCHAR(255) DEFAULT NULL,
  `calificacion`      DECIMAL(5,2) DEFAULT NULL,
  `comentario_pastor` TEXT,
  `revisado`          TINYINT(1) DEFAULT 0,
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unico_respuesta` (`usuario_id`, `pregunta_id`),
  FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`pregunta_id`)  REFERENCES `preguntas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`examen_id`)    REFERENCES `examenes`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Columnas extra en resultados_examen
ALTER TABLE `resultados_examen`
  ADD COLUMN IF NOT EXISTS `pendiente_revision` TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `puntaje_final`      DECIMAL(5,2) DEFAULT NULL;

-- ─────────────────────────────────────────
-- ACTUALIZACIÓN v9 — Notificaciones para estudiantes
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notificaciones_estudiante` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`  INT NOT NULL,
  `tipo`        ENUM('comentario_respondido','examen_calificado') NOT NULL,
  `titulo`      VARCHAR(150) NOT NULL,
  `mensaje`     TEXT,
  `url`         VARCHAR(255) DEFAULT NULL,
  `leida`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────
-- ACTUALIZACIÓN v10 — Reset contraseña + Google OAuth
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(100) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `google_id`    VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `avatar_url`   VARCHAR(500) DEFAULT NULL;
