<?php
// includes/historico.php
// Histórico académico por curso (usado por perfil.php y api/historial_pdf.php)
//
// Reglas:
// - Solo se listan cursos que el estudiante ha iniciado (lecciones o exámenes).
// - La nota de cada examen es el MEJOR intento calificado (puntaje_final si existe).
// - La nota del curso es el promedio de las notas de sus exámenes presentados.
// - Estado del curso:
//     Completado : todas las lecciones + todos los exámenes presentados y aprobados
//     Reprobado  : todo presentado, pero algún examen no aprobado y sin intentos restantes
//     En curso   : cualquier otro caso (incluye pendientes de revisión o reintentos posibles)

function historico_por_curso(mysqli $conn, int $uid): array {
    // Lecciones por curso
    $s = $conn->prepare("
        SELECT c.id, c.titulo,
               COUNT(DISTINCT l.id) AS total_lecciones,
               COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS lecciones_comp
        FROM cursos c
        LEFT JOIN modulos m ON m.curso_id=c.id
        LEFT JOIN lecciones l ON l.modulo_id=m.id
        LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
        WHERE c.publicado=1
        GROUP BY c.id ORDER BY c.orden, c.id
    ");
    $s->bind_param("i",$uid); $s->execute();
    $cursos = [];
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
        $c['examenes'] = [];
        $cursos[$c['id']] = $c;
    }
    $s->close();

    // Exámenes por curso con mejor intento del estudiante
    $s = $conn->prepare("
        SELECT c.id AS curso_id,
               e.id AS examen_id, e.titulo AS examen, e.puntaje_minimo, e.max_intentos,
               COUNT(re.id) AS intentos,
               MAX(CASE WHEN re.pendiente_revision=0 THEN COALESCE(re.puntaje_final, re.puntaje) END) AS mejor,
               MAX(re.aprobado) AS aprobado,
               SUM(CASE WHEN re.pendiente_revision=1 THEN 1 ELSE 0 END) AS pendientes,
               MAX(re.fecha) AS ultima_fecha
        FROM examenes e
        JOIN modulos mo ON mo.id=e.modulo_id
        JOIN cursos c   ON c.id=mo.curso_id
        LEFT JOIN resultados_examen re ON re.examen_id=e.id AND re.usuario_id=?
        WHERE c.publicado=1
        GROUP BY e.id
        ORDER BY c.orden, c.id, e.id
    ");
    $s->bind_param("i",$uid); $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $e) {
        if (isset($cursos[$e['curso_id']])) {
            $cursos[$e['curso_id']]['examenes'][] = $e;
        }
    }
    $s->close();

    // Cálculo por curso
    $historico = [];
    foreach ($cursos as $c) {
        $exs          = $c['examenes'];
        $total_ex     = count($exs);
        $presentados  = array_filter($exs, fn($e) => (int)$e['intentos'] > 0);
        $n_present    = count($presentados);
        $aprobados_ex = array_filter($presentados, fn($e) => (int)$e['aprobado'] === 1);
        $hay_pend     = (bool)array_filter($presentados, fn($e) => (int)$e['pendientes'] > 0 && !$e['aprobado']);

        // Nota del curso: promedio de mejores intentos calificados
        $califs = array_filter(array_column($presentados, 'mejor'), fn($v) => $v !== null);
        $nota   = count($califs) ? round(array_sum($califs) / count($califs), 1) : null;

        // ¿Inició el curso?
        $inicio = ((int)$c['lecciones_comp'] > 0) || $n_present > 0;
        if (!$inicio) continue;

        // Estado
        $lecciones_ok = (int)$c['total_lecciones'] > 0
                     && (int)$c['lecciones_comp'] === (int)$c['total_lecciones'];
        $todo_presentado = $total_ex === 0 || $n_present === $total_ex;

        if ($lecciones_ok && $todo_presentado && count($aprobados_ex) === $n_present && !$hay_pend) {
            $estado = 'Completado';
        } else {
            // Reprobado: presentó todo, hay exámenes perdidos sin intentos restantes
            $perdido_sin_intentos = array_filter($presentados, function($e) {
                return !(int)$e['aprobado']
                    && (int)$e['pendientes'] === 0
                    && (int)$e['max_intentos'] > 0
                    && (int)$e['intentos'] >= (int)$e['max_intentos'];
            });
            $estado = ($lecciones_ok && $todo_presentado && $perdido_sin_intentos)
                      ? 'Reprobado' : 'En curso';
        }

        $historico[] = [
            'curso_id'        => $c['id'],
            'curso'           => $c['titulo'],
            'total_lecciones' => (int)$c['total_lecciones'],
            'lecciones_comp'  => (int)$c['lecciones_comp'],
            'total_examenes'  => $total_ex,
            'presentados'     => $n_present,
            'aprobados'       => count($aprobados_ex),
            'nota'            => $nota,
            'estado'          => $estado,
            'examenes'        => $exs,
        ];
    }

    return $historico;
}

// Resumen global a partir del histórico
function historico_resumen(array $historico): array {
    $completados = count(array_filter($historico, fn($c) => $c['estado'] === 'Completado'));
    $ex_pres = array_sum(array_column($historico, 'presentados'));
    $ex_apro = array_sum(array_column($historico, 'aprobados'));
    $notas   = array_filter(array_column($historico, 'nota'), fn($v) => $v !== null);
    $promedio = count($notas) ? round(array_sum($notas) / count($notas), 1) : 0;
    return [
        'cursos_completados' => $completados,
        'examenes_aprobados' => $ex_apro,
        'examenes_presentados' => $ex_pres,
        'promedio' => $promedio,
    ];
}
