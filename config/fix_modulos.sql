-- ════════════════════════════════════════════════════════════
-- Corrección: cursos sin módulo
-- Crea un "Módulo 1" para todo curso que no tenga ninguno,
-- lo que permite asignarles lecciones y exámenes.
-- ════════════════════════════════════════════════════════════

-- PASO 0 — VISTA PREVIA (no modifica nada):
-- muestra qué cursos están sin módulo y recibirían uno.
SELECT c.id, c.titulo
FROM cursos c
LEFT JOIN modulos m ON m.curso_id = c.id
WHERE m.id IS NULL;

-- PASO 1 — Crear el módulo faltante:
INSERT INTO modulos (curso_id, titulo, orden)
SELECT c.id, 'Módulo 1', 1
FROM cursos c
LEFT JOIN modulos m ON m.curso_id = c.id
WHERE m.id IS NULL;

-- PASO 2 — Verificación:
SELECT c.titulo AS curso, m.titulo AS modulo
FROM cursos c JOIN modulos m ON m.curso_id = c.id
ORDER BY c.id;
