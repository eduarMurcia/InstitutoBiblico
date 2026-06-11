<?php
// ─────────────────────────────────────────
// includes/notificaciones.php
// Sistema de notificaciones interno + email opcional
// ─────────────────────────────────────────

/**
 * Crea una notificación interna para el admin.
 * Si el admin tiene notif_email=1, también envía correo.
 *
 * @param string $tipo     'comentario' | 'entrega' | 'sistema'
 * @param string $titulo   Título corto visible en la campana
 * @param string $mensaje  Cuerpo detallado
 * @param string $url      Enlace de acción (relativo a /lms/)
 */
function crear_notificacion(string $tipo, string $titulo, string $mensaje, string $url = ''): void {
    // Reutilizamos la conexión si ya existe, o abrimos una nueva
    $conn = conectar();

    // Guardar en BD
    $s = $conn->prepare(
        "INSERT INTO notificaciones (tipo, titulo, mensaje, url) VALUES (?,?,?,?)"
    );
    $s->bind_param("ssss", $tipo, $titulo, $mensaje, $url);
    $s->execute();
    $s->close();

    // Buscar admins con notif_email activo
    $admins = $conn->query(
        "SELECT email, nombre FROM usuarios WHERE rol='admin' AND notif_email=1 AND activo=1"
    );
    if ($admins && $admins->num_rows > 0) {
        while ($admin = $admins->fetch_assoc()) {
            _enviar_email_notif($admin['email'], $admin['nombre'], $titulo, $mensaje, $url);
        }
    }
}

/**
 * Cuenta notificaciones no leídas (para la campana del navbar).
 */
function contar_notificaciones_sin_leer(): int {
    $conn = conectar();
    $r = $conn->query("SELECT COUNT(*) AS n FROM notificaciones WHERE leida=0");
    return $r ? (int)$r->fetch_assoc()['n'] : 0;
}

/**
 * Marca todas las notificaciones como leídas.
 */
function marcar_todas_leidas(): void {
    $conn = conectar();
    $conn->query("UPDATE notificaciones SET leida=1 WHERE leida=0");
}

/**
 * Envía email usando mail() nativo de PHP.
 * El hosting debe tener mail() configurado (la mayoría lo tienen).
 */
function _enviar_email_notif(string $to, string $nombre, string $titulo, string $mensaje, string $url): void {
    $from    = 'no-responder@institutobiblicobautistacolombia.com';
    $subject = '=?UTF-8?B?' . base64_encode('[IBB] ' . $titulo) . '?=';

    $url_completa = $url ? 'https://institutobiblicobautistacolombia.com/lms/' . ltrim($url, '/') : '';

    $body = "Hola {$nombre},\n\n"
          . "{$mensaje}\n\n"
          . ($url_completa ? "Ver en la plataforma:\n{$url_completa}\n\n" : '')
          . "—\nInstituto Bíblico Bautista\nhttps://institutobiblicobautistacolombia.com/lms/";

    $headers  = "From: Instituto Bíblico Bautista <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($to, $subject, $body, $headers);
}

/**
 * Crea una notificación interna para un estudiante específico.
 * Si el estudiante tiene notif_email=1, también envía email.
 */
function notificar_estudiante(int $uid, string $tipo, string $titulo, string $mensaje, string $url = ''): void {
    $conn = conectar();

    // Guardar en BD
    $s = $conn->prepare("INSERT INTO notificaciones_estudiante (usuario_id, tipo, titulo, mensaje, url) VALUES (?,?,?,?,?)");
    $s->bind_param("issss", $uid, $tipo, $titulo, $mensaje, $url);
    $s->execute(); $s->close();

    // Email si tiene activado
    $s = $conn->prepare("SELECT email, nombre, notif_email FROM usuarios WHERE id=?");
    $s->bind_param("i", $uid); $s->execute();
    $u = $s->get_result()->fetch_assoc(); $s->close();

    if ($u && $u['notif_email']) {
        _enviar_email_notif($u['email'], $u['nombre'], $titulo, $mensaje, $url);
    }
}

/**
 * Cuenta notificaciones no leídas de un estudiante.
 */
function contar_notif_estudiante(int $uid): int {
    $conn = conectar();
    $r = $conn->query("SELECT COUNT(*) AS n FROM notificaciones_estudiante WHERE usuario_id=$uid AND leida=0");
    return $r ? (int)$r->fetch_assoc()['n'] : 0;
}

/**
 * Marca todas las notificaciones del estudiante como leídas.
 */
function marcar_notif_estudiante_leidas(int $uid): void {
    $conn = conectar();
    $conn->query("UPDATE notificaciones_estudiante SET leida=1 WHERE usuario_id=$uid AND leida=0");
}
