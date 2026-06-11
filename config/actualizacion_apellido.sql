-- ════════════════════════════════════════════════════════════
-- Migración: nombre y apellido separados
-- Instituto Bíblico Bautista — LMS
-- Ejecutar en phpMyAdmin, en este orden.
-- ════════════════════════════════════════════════════════════

-- PASO 0 (opcional pero recomendado) — VISTA PREVIA:
-- Ejecuta SOLO este SELECT primero para ver cómo quedaría cada
-- usuario después de la separación. No modifica nada.
SELECT
  id,
  nombre                                            AS nombre_actual,
  SUBSTRING_INDEX(nombre, ' ', 1)                   AS nuevo_nombre,
  CASE WHEN LOCATE(' ', nombre) > 0
       THEN TRIM(SUBSTRING(nombre, LOCATE(' ', nombre) + 1))
       ELSE '' END                                  AS nuevo_apellido
FROM usuarios
ORDER BY id;

-- ────────────────────────────────────────────────────────────
-- PASO 1 — Agregar la columna apellido
-- ────────────────────────────────────────────────────────────
ALTER TABLE `usuarios`
  ADD COLUMN `apellido` VARCHAR(100) NOT NULL DEFAULT '' AFTER `nombre`;

-- ────────────────────────────────────────────────────────────
-- PASO 2 — Separar los nombres existentes por el primer espacio
-- "Jhon Pérez García" → nombre="Jhon", apellido="Pérez García"
-- "Eduar" (sin espacio) → nombre="Eduar", apellido=""
-- ────────────────────────────────────────────────────────────
UPDATE `usuarios`
SET `apellido` = CASE WHEN LOCATE(' ', nombre) > 0
                      THEN TRIM(SUBSTRING(nombre, LOCATE(' ', nombre) + 1))
                      ELSE '' END,
    `nombre`   = SUBSTRING_INDEX(nombre, ' ', 1);

-- ────────────────────────────────────────────────────────────
-- PASO 3 — Verificación final
-- ────────────────────────────────────────────────────────────
SELECT id, nombre, apellido, email FROM usuarios ORDER BY id;
