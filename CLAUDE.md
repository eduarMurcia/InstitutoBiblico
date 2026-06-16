# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Instituto Bíblico Bautista — LMS** is a PHP/MySQL learning management system for a Baptist ministry. Students access audio-based Bible courses, take module exams, and receive certificates. Admins manage courses, users, exams, and grade open-answer submissions.

No build system, no package manager, no test suite. This is plain PHP deployed directly to a cPanel shared hosting environment.

## Local Development

There is no local dev server setup documented. Development is done by editing files directly and deploying via FTP or cPanel File Manager. To test locally you need a PHP + MySQL environment (XAMPP, Laragon, etc.) with the DB credentials configured.

## Database

### Connection

`config/db.php` is a one-liner that delegates to a file **outside** `public_html`:

```
require_once __DIR__ . '/../../lms_config/db.php';
```

The real credentials live at `../lms_config/db.php` (two levels up, outside `open_basedir`). The example structure is in `config/db.example.php`.

The external `lms_config/db.php` defines a `conectar()` function that returns a `mysqli` connection. Every page that needs the DB calls `$conn = conectar();` and closes it explicitly with `$conn->close()`.

### Schema setup

- **Fresh install:** run `config/install_completo.sql` in phpMyAdmin (v8, June 2026 — the canonical schema).
- **Incremental migrations:** `config/actualizacion.sql` adds columns/tables for features added after v1 (PDF attachments, open-answer questions, notifications, password resets, Google OAuth). Run only on existing installs.
- **Fix scripts:** `config/fix_modulos.sql`, `config/actualizacion_apellido.sql` for one-off repairs.

### Key tables

| Table | Purpose |
|---|---|
| `usuarios` | Students + admins (`rol` = `estudiante`/`admin`) |
| `cursos → modulos → lecciones` | Content hierarchy |
| `progreso` | Per-user, per-lesson completion |
| `examenes → preguntas` | Exams per module |
| `resultados_examen` | Scores; `pendiente_revision=1` for open/file answers awaiting grading |
| `respuestas_examen` | Individual question answers (text, file, calificacion) |
| `certificados` | Issued on course completion |
| `comentarios` | Per-lesson student comments + pastor replies |
| `notificaciones` | Admin in-app + email notifications (bell icon in admin panel) |
| `notificaciones_estudiante` | Per-student in-app notifications for graded exams / answered comments |
| `invitaciones` | Invitation codes for student registration |
| `password_resets` | One-time tokens for password reset emails (1 hr expiry, `used` flag) |

## Architecture

### Request flow

Every page starts with:
```php
require_once 'includes/auth.php';   // session_start(), helper functions
require_once 'config/db.php';       // delegates to external lms_config/db.php
requerir_login();                   // or requerir_admin() for admin pages
```

`includes/auth.php` provides: `esta_logueado()`, `es_admin()`, `requerir_login()`, `requerir_admin()`, `sanitizar()`, `redirigir()`, `obtener_base()`.

All user-facing output passes through `sanitizar()` (= `htmlspecialchars(strip_tags(trim(...)))`).

Session keys written at login: `$_SESSION['usuario_id']` (int), `$_SESSION['rol']` (`'estudiante'`|`'admin'`), `$_SESSION['nombre']` (string), `$_SESSION['email']` (string).

### SQL pattern

All queries use **MySQLi prepared statements** when binding user-supplied strings. Integer values from `$_GET`/`$_POST` are cast with `(int)` and may be interpolated directly.

```php
// String input — always prepare
$s = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
$s->bind_param("s", $email);
$s->execute();
$res = $s->get_result();
$s->close();

// Integer-only — cast and interpolate
$row = $conn->query("SELECT * FROM cursos WHERE id=" . (int)$_GET['id'])->fetch_assoc();
```

`sanitizar()` is for HTML output only — it is not a substitute for prepared statements.

### Directory layout

```
/               → Student-facing pages (index.php, dashboard.php, cursos.php, examen.php, perfil.php, olvide.php, registro_profesor.php)
/admin/         → Admin panel (index.php, cursos.php, usuarios.php, examenes.php, entregas.php, comentarios.php, notificaciones.php, invitaciones.php)
/api/           → Protected file servers (audio.php, pdf.php, certificado.php, historial_pdf.php, examen_imprimible.php, archivo_respuesta.php, logout.php, google_auth.php)
/includes/      → Shared PHP: auth.php, iconos.php, estudiante_layout.php, admin_sidebar.php, portada_curso.php, historico.php, inspiracion.php, notificaciones.php, invitaciones.php
/config/        → db.php, SQL files
/css/           → styles.css (single stylesheet, CSS custom properties)
/uploads/       → audio/, pdf/, portadas/, respuestas/ — user-uploaded files, each with .htaccess blocking direct access
/font/          → FPDF font JSON files
fpdf.php        → PDF generation library (bundled)
diagnostico.php → Dev-only DB/folder health check — delete from server after use
```

### Exam question types

`preguntas.tipo` can be `multiple` (4 options, auto-graded), `verdadero_falso` (auto-graded), `abierta` (open text, manually graded), or `archivo` (file upload, manually graded). When a submission contains `abierta` or `archivo` questions, `resultados_examen.pendiente_revision` is set to `1` and the exam appears in `admin/entregas.php` for the pastor to grade. Once all open questions are graded, the system recalculates the final score and notifies the student via `notificar_estudiante()`.

### Notifications

`includes/notificaciones.php` provides two independent systems:
- `crear_notificacion(tipo, titulo, mensaje, url)` — writes to `notificaciones` (admin bell) and optionally emails all admins with `notif_email=1`.
- `notificar_estudiante(uid, tipo, titulo, mensaje, url)` — writes to `notificaciones_estudiante` and optionally emails the student if `notif_email=1`.

Both systems use PHP's native `mail()` with `=?UTF-8?B?...?=` subject encoding. No third-party mailer library is used.

### PDF generation

PDFs (certificates, academic history, printable exams) use the bundled **FPDF** library (`fpdf.php`). The `font/` directory contains pre-encoded font metrics (Helvetica, Times variants). All FPDF output uses `iconv('UTF-8','windows-1252//TRANSLIT//IGNORE', ...)` to convert strings before passing them to FPDF, because FPDF does not support UTF-8 natively.

Pattern used in every PDF file:
```php
function u($s) { return iconv('UTF-8','windows-1252//TRANSLIT//IGNORE', $s ?? ''); }
// then: $pdf->Cell(..., u($someString));
```

### Layout helpers

`includes/estudiante_layout.php` provides `estudiante_navbar(string $activa, array $extra)` and `estudiante_sidebar(string $activa)` — call these at the top of every student-facing page. Admin pages use `includes/admin_sidebar.php` which provides `admin_navbar(string $activa)` and `admin_sidebar(string $activa)` instead.

### Icons

`includes/iconos.php` exports a single function `icono(string $name, string $class = 'ico'): string` that returns inline SVG. All icons are custom-drawn SVGs stored as a static array. Add new icons there; never add emoji or new external icon libraries. The Tabler icons webfont is already imported in `styles.css` (`@tabler/icons-webfont@2.47.0`) and `ti-*` CSS classes are used in some pages — these are acceptable but prefer the `icono()` SVG system for new icons.

### CSS

Single file `css/styles.css` using CSS custom properties (`--navy`, `--azul`, `--bg`, `--text-soft`, `--radius-lg`, etc.). No preprocessor, no framework.

## Design and product context

`DESIGN.md` — design token reference (colors, typography scale, component specs). Use it when adding new UI elements to stay consistent with the established system.

`PRODUCT.md` — product context: user persona (mobile-first Colombian church members), brand personality (formal/doctrinal), and design principles. Consult before making UI decisions.

## Google OAuth

`api/google_auth.php` handles OAuth callback. The client ID/secret live in `api/google_auth.example.php` (template) and the real config follows the same out-of-`public_html` pattern. See `config/actualizacion.sql` for the `google_id` / `avatar_url` columns added to `usuarios`.

## Deployment

Target: shared cPanel hosting, PHP 7.4+, MySQL 5.7+. Upload files to `public_html/`. The `uploads/` subdirectories need chmod 755. The `lms_config/` credentials folder must sit **outside** `public_html`.

Default admin after fresh install: `admin@ministerio.com` / `Admin1234!` — change immediately.
