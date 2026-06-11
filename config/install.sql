-- ─────────────────────────────────────────
-- Ministerio de Imprenta RV 1865 — LMS
-- Esquema de base de datos
-- Ejecutar una sola vez en phpMyAdmin
-- ─────────────────────────────────────────

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Usuarios (estudiantes y administradores)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `rol` ENUM('estudiante','admin') DEFAULT 'estudiante',
  `activo` TINYINT(1) DEFAULT 1,
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cursos
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `imagen` VARCHAR(255) DEFAULT NULL,
  `orden` INT DEFAULT 0,
  `publicado` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Módulos (dentro de cada curso)
CREATE TABLE IF NOT EXISTS `modulos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `curso_id` INT NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `orden` INT DEFAULT 0,
  FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lecciones (dentro de cada módulo, con audio)
CREATE TABLE IF NOT EXISTS `lecciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modulo_id` INT NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `archivo_audio` VARCHAR(255) DEFAULT NULL,
  `duracion` VARCHAR(20) DEFAULT NULL,
  `orden` INT DEFAULT 0,
  FOREIGN KEY (`modulo_id`) REFERENCES `modulos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Progreso del estudiante por lección
CREATE TABLE IF NOT EXISTS `progreso` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `leccion_id` INT NOT NULL,
  `completado` TINYINT(1) DEFAULT 0,
  `fecha_completado` DATETIME DEFAULT NULL,
  UNIQUE KEY `unico_progreso` (`usuario_id`, `leccion_id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leccion_id`) REFERENCES `lecciones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exámenes por módulo
CREATE TABLE IF NOT EXISTS `examenes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modulo_id` INT NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `puntaje_minimo` INT DEFAULT 60,
  FOREIGN KEY (`modulo_id`) REFERENCES `modulos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preguntas del examen
CREATE TABLE IF NOT EXISTS `preguntas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `examen_id` INT NOT NULL,
  `pregunta` TEXT NOT NULL,
  `opcion_a` VARCHAR(255) NOT NULL,
  `opcion_b` VARCHAR(255) NOT NULL,
  `opcion_c` VARCHAR(255) NOT NULL,
  `opcion_d` VARCHAR(255) NOT NULL,
  `respuesta_correcta` ENUM('a','b','c','d') NOT NULL,
  `orden` INT DEFAULT 0,
  FOREIGN KEY (`examen_id`) REFERENCES `examenes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resultados de exámenes
CREATE TABLE IF NOT EXISTS `resultados_examen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `examen_id` INT NOT NULL,
  `puntaje` INT NOT NULL,
  `aprobado` TINYINT(1) DEFAULT 0,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`examen_id`) REFERENCES `examenes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comentarios / Foro por lección
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `leccion_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `mensaje` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`leccion_id`) REFERENCES `lecciones`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────
-- Admin inicial (contraseña: Admin1234!)
-- Cambia el email y contraseña después de instalar
-- ─────────────────────────────────────────
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`) VALUES
('Administrador', 'admin@ministerio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSD0AfD6y', 'admin');
