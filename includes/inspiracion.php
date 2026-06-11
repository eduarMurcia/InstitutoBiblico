<?php
// includes/inspiracion.php
// Banco de frases (para el título del hero) y versículos (texto inferior).
// Rotan automáticamente: cada día del año muestra el siguiente de la lista.
// Para cambiarlos o agregar más, edita los arrays de abajo.

// ── Frases del título: aparecen como "<Nombre>, <frase>" ──
$FRASES_HERO = [
    'sigue creciendo en la palabra',
    'continúa firme en la fe',
    'hoy es un buen día para aprender',
    'sigue adelante en tu formación',
    'que tu estudio dé buen fruto',
];

// ── Versículos del texto inferior (Reina-Valera, dominio público) ──
$VERSICULOS_HERO = [
    ['texto' => 'Lámpara es a mis pies tu palabra, y lumbrera a mi camino.', 'cita' => 'Salmo 119:105'],
    ['texto' => 'Todo lo puedo en Cristo que me fortalece.', 'cita' => 'Filipenses 4:13'],
    ['texto' => 'Esfuérzate y sé valiente; no temas ni desmayes.', 'cita' => 'Josué 1:9'],
    ['texto' => 'El principio de la sabiduría es el temor de Jehová.', 'cita' => 'Proverbios 9:10'],
    ['texto' => 'Encomienda a Jehová tu camino, y confía en él.', 'cita' => 'Salmo 37:5'],
];

function frase_del_dia(): string {
    global $FRASES_HERO;
    if (empty($FRASES_HERO)) return 'sigue creciendo en la palabra';
    return $FRASES_HERO[(int)date('z') % count($FRASES_HERO)];
}

function versiculo_del_dia(): array {
    global $VERSICULOS_HERO;
    if (empty($VERSICULOS_HERO)) return ['texto' => '', 'cita' => ''];
    return $VERSICULOS_HERO[(int)date('z') % count($VERSICULOS_HERO)];
}
