---
name: Instituto Bíblico Bautista — LMS
description: Plataforma de formación bíblica online para estudiantes de una iglesia bautista colombiana
colors:
  navy: "#000a23"
  navy-light: "#001440"
  gold: "#d19309"
  gold-light: "#F3C332"
  gold-pale: "#f5edd0"
  azul: "#99b8db"
  azul-pale: "#e4eff6"
  azul-deep: "#5b86b4"
  bg-paper: "#FAF7F0"
  bg-card: "#ffffff"
  bg-sidebar: "#f0ece0"
  text-ink: "#1a1228"
  text-soft: "#4a4560"
  text-muted: "#8a8699"
  success: "#27ae60"
  danger: "#c0392b"
typography:
  display:
    fontFamily: "Roboto, sans-serif"
    fontSize: "clamp(2rem, 5vw, 3.2rem)"
    fontWeight: 300
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Roboto, sans-serif"
    fontSize: "clamp(1.5rem, 3vw, 2.2rem)"
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Roboto, sans-serif"
    fontSize: "1.15rem"
    fontWeight: 500
    lineHeight: 1.3
  body:
    fontFamily: "Roboto, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.7
  label:
    fontFamily: "Roboto, sans-serif"
    fontSize: "0.82rem"
    fontWeight: 500
    lineHeight: 1.4
  caption:
    fontFamily: "Roboto, sans-serif"
    fontSize: "0.78rem"
    fontWeight: 400
    lineHeight: 1.4
  scripture:
    fontFamily: "IM Fell English, Georgia, serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.7
rounded:
  sm: "6px"
  lg: "12px"
  pill: "100px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "28px"
  xl: "32px"
  2xl: "48px"
components:
  button-primary:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.navy}"
    rounded: "{rounded.sm}"
    padding: "0.6rem 1.5rem"
  button-primary-hover:
    backgroundColor: "#b87d00"
    textColor: "{colors.navy}"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.navy}"
    rounded: "{rounded.sm}"
    padding: "0.6rem 1.5rem"
  button-outline-hover:
    backgroundColor: "{colors.navy}"
    textColor: "#f5f0e8"
  button-ghost:
    backgroundColor: "rgba(0,10,35,0.06)"
    textColor: "{colors.text-soft}"
    rounded: "{rounded.sm}"
    padding: "0.6rem 1.5rem"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "0.6rem 1.5rem"
  card:
    backgroundColor: "{colors.bg-card}"
    rounded: "{rounded.lg}"
    padding: "1.75rem"
  input:
    backgroundColor: "{colors.bg-paper}"
    textColor: "{colors.text-ink}"
    rounded: "{rounded.sm}"
    padding: "0.65rem 1rem"
---

# Design System: Instituto Bíblico Bautista — LMS

## 1. Overview

**Creative North Star: "La Cátedra Bautista"**

Esta interfaz existe para que la relación pastor-estudiante pueda ocurrir en la pantalla sin perder su peso. No es una plataforma de cursos — es una cátedra digital. El pastor enseña, el estudiante escucha, responde y recibe retroalimentación. Cada elemento — el color, la tipografía, los espacios — debe reflejar que lo que ocurre aquí tiene valor doctrinal y relacional, no solo educativo.

El sistema rechaza tres trampas activamente: la frialdad del LMS corporativo (Coursera, Udemy), el branding exagerado de la mega-iglesia americana, y la genericidad del SaaS de 2023 (navy + purple + gradients). El navy profundo y el oro no son colores de marca elegidos al azar — son la combinación más cercana a tinta y oro en página: institución y autoridad sin pretensión.

Las animaciones existen y saben callar. Los formularios de examen responden con calidez discreta — el estudiante que responde una pregunta sobre las Escrituras merece sentir que la interfaz lo reconoce. Cada acción tiene peso porque lo que se hace aquí importa; el diseño debe reflejar eso sin dramatismo.

**Key Characteristics:**
- Navy dominante como superficie estructural; oro como voz de acción — el color es jerarquía, no decoración
- Roboto Light (300) para títulos grandes: gesto editorial, no corporativo
- Sombras mínimas en reposo, sombras funcionales en hover y focus
- Motion suave (ease 0.15s–0.2s), nunca elástico ni llamativo
- Accesible en móvil con conexión lenta — la velocidad de carga es parte del diseño
- El formulario de examen es un momento de atención, no un interrogatorio

## 2. Colors

La paleta es una triada restringida: un azul-negro institucional, un oro de autoridad, y un celeste que acompaña. Los neutros son crema-papel, no blanco puro.

### Primary
- **Azul de Concilio** (#000a23): El navy profundo es la superficie dominante — navbar, headings, estado activo de sidebar, bordes de énfasis. Connota autoridad institucional sin agresividad. Aparece también en el texto del botón primary (sobre oro).
- **Oro del Púlpito** (#d19309): El acento primario. Aparece en botones de acción principal, barra de progreso, estado focus de inputs, icono de la marca y borde inferior del navbar. Es la voz del maestro — visible cuando hay algo que hacer o notar.

### Secondary
- **Ámbar de Leccionario** (#F3C332): Versión luminosa del oro. Aparece en el subtítulo de la marca y en la barra de progreso (extremo derecho del gradiente). Nunca como texto sobre fondo claro — el contraste es insuficiente.
- **Celeste Reformado** (#5b86b4): Azul medio para acentos secundarios — badges de información, texto de instructor en la landing. No compite con el oro; tiene otro registro emocional.

### Tertiary
- **Cielo Claro** (#e4eff6): Superficie de fondo para las cards de cursos en la landing pública. Hover invierte a Azul de Concilio — el contenedor se vuelve navy al recibir atención.

### Neutral
- **Papel de Biblia** (#FAF7F0): Background del cuerpo y del main content. Crema cálida que sugiere papel sin llegar a amarillo.
- **Pergamino** (#f5edd0): Gold-pale. Hover de opciones de examen, estado activo del sidebar. Más cálido que el Papel.
- **Marfil de Tarjeta** (#ffffff): Cards, inputs en focus. Solo en superficies elevadas sobre el Papel.
- **Humo de Sidebar** (#f0ece0): Background del sidebar. Distingue visualmente la zona de navegación del área de contenido.
- **Tinta Profunda** (#1a1228): Color base de texto. Casi negro con matiz violeta-marino. Se usa en todos los textos primarios.
- **Tinta Suave** (#4a4560): Texto secundario — párrafos, labels, metadatos. La mayor parte de la lectura recae en este color.
- **Polvo de Tinta** (#8a8699): Texto terciario — timestamps, contadores, placeholders. Información de contexto que puede leerse si se necesita.

### Named Rules
**La Regla de Una Voz.** El Oro del Púlpito aparece en ≤15% de cualquier pantalla. Su escasez es su autoridad. No se usa para decorar — se usa para guiar. Si todo es dorado, nada lo es.

**La Regla del Papel.** El background nunca es blanco puro. El Papel de Biblia (#FAF7F0) es la única superficie de body. El blanco (#fff) está reservado para cards elevadas y inputs en focus — superficies que deben parecer más cercanas al usuario que el fondo.

## 3. Typography

**Font principal:** Roboto (Light 300, Regular 400, Medium 500, Bold 700, Black 900)
**Serif reservada:** IM Fell English, Georgia — exclusiva para citas y versículos bíblicos

**Character:** Una sola familia en todo el rango de pesos. La apuesta por Roboto Light para los títulos principales es la elección editorial que distingue este sistema de un portal genérico. La serif IM Fell English es un material tipográfico que aguarda en reserva; su aparición en una cita escritural tiene peso precisamente porque no aparece en ningún otro contexto.

### Hierarchy
- **Display** (Light 300, clamp(2rem, 5vw, 3.2rem), lh 1.15, ls -0.02em): Títulos hero y headings principales de sección. El peso ligero a tamaño grande es el gesto editorial distintivo del sistema.
- **Headline** (Regular 400, clamp(1.5rem, 3vw, 2.2rem), lh 1.2, ls -0.01em): Títulos de página dentro del dashboard — nombre del curso, título de examen.
- **Title** (Medium 500, 1.15rem, lh 1.3): Headings de cards, títulos de módulo, encabezados de pregunta.
- **Body** (Regular 400, 1rem, lh 1.7): Todo el texto de lectura. Máximo 70ch en columnas de contenido. La line-height 1.7 no es generosa — es funcional para la lectura de preguntas de examen y descripciones de lección.
- **Label / UI** (Medium 500, 0.82rem, lh 1.4): Botones, etiquetas de formulario, tabs, links de sidebar. Texto que acompaña una acción.
- **Caption / Meta** (Regular 400, 0.78rem, lh 1.4, color text-muted): Timestamps, contadores, metadata secundaria. No compite; informa.
- **Scripture** (IM Fell English italic, 1rem, lh 1.7): Solo para versículos bíblicos citados. Nunca en elementos funcionales de interfaz.

### Named Rules
**La Regla de la Reserva Serif.** IM Fell English no aparece en ningún elemento de UI — solo en citas bíblicas explícitas. Su impacto tipográfico depende de su rareza.

**La Regla del Peso Editorial.** Los h1 son Light (300). Aumentar el peso en headings los convierte en marketing. La autoridad institucional no grita — ocupa espacio.

## 4. Elevation

El sistema usa sombras sutiles, no estructurales. La profundidad se expresa primariamente a través de diferencias de superficie (Humo de Sidebar vs. Papel de Biblia vs. Marfil de Tarjeta) y color de borde, con sombras que responden a la interacción.

### Shadow Vocabulary
- **Ambient** (`0 2px 20px rgba(0,10,35,0.08)`): Cards en reposo. Sombra difusa apenas perceptible — separa superficies sin dramatizar.
- **Hover** (`0 6px 32px rgba(0,10,35,0.12)`): Cards y contenedores interactivos al recibir hover. Sube discretamente. La elevación no es dramática — es el feedback de algo que responde.
- **Gold Focus Glow** (`0 0 0 3px rgba(209,147,9,0.12)`): Exclusivo para inputs en focus. El aura dorada alrededor del campo activo es la única sombra con color del sistema.
- **Button Action** (`0 4px 16px rgba(209,147,9,0.3)`): Aparece en el hover del botón primary. El resplandor dorado bajo el botón activo es la señal de acción más intensa del sistema.

### Named Rules
**La Regla de la Elevación Funcional.** Las sombras responden a estados (hover, focus); no decoran el reposo. Un card en reposo tiene sombra ambient mínima. El drama visual está reservado para la interacción.

## 5. Components

El espíritu es *firme pero accesible*. Las transiciones son suaves (0.15s–0.2s ease), nunca elásticas. Los formularios de examen no son interrogatorios — los estados hover son cálidos. Cada acción tiene peso porque importa, no porque sea dramática.

### Buttons
- **Shape:** Bordes levemente redondeados (6px). No son píldoras — la curvatura existe pero no domina la forma.
- **Primary:** Fondo Oro del Púlpito (#d19309), texto Azul de Concilio (#000a23), padding 0.6rem 1.5rem. La combinación oro sobre navy produce el mayor contraste del sistema — señala la acción más importante.
- **Hover / Focus:** Primary sube levemente (translateY -1px), oscurece el oro (#b87d00), añade button-action shadow. ease 0.2s.
- **Outline:** Borde 1.5px navy, texto navy, fondo transparente. Hover: rellena con navy, texto crema (#f5f0e8). Para acciones secundarias de igual jerarquía.
- **Ghost:** Fondo rgba(navy,0.06), borde 1px, texto text-soft. Acciones de baja jerarquía — filtros, controles secundarios.
- **Danger:** Rojo (#c0392b), texto blanco. Exclusivo para acciones destructivas. No se usa decorativamente.
- **Small (.btn-sm):** Padding 0.35rem 0.9rem, font-size 0.75rem — para espacios reducidos en tablas y cards.

### Cards
- **Corner Style:** Redondeadas (12px) — más suaves que los botones, comunican contenedor en lugar de acción.
- **Background:** Marfil de Tarjeta (#fff).
- **Shadow Strategy:** Sombra Ambient en reposo; Hover al interactuar.
- **Border:** 1px rgba(navy,0.1) en reposo; gold border (rgba(209,147,9,0.35)) en hover.
- **Internal Padding:** 1.75rem cards principales; 1.5rem cards secundarias.
- **Variante `card-gold-top`:** Borde superior 3px oro — para la card de resultado de examen y elementos con énfasis superior.

### Inputs / Fields
- **Style:** Fondo Papel de Biblia (#FAF7F0), borde 1.5px translúcido, radius 6px, padding 0.65rem 1rem.
- **Focus:** Borde cambia a Oro del Púlpito; aura Gold Focus Glow. El focus es el momento más importante en el formulario de examen — debe ser reconocible y cálido.
- **Placeholder:** Color text-muted (#8a8699). Verificar contraste mínimo 4.5:1 sobre el Papel.
- **Textarea:** min-height 100px, resize vertical. Las respuestas abiertas de examen necesitan espacio real.
- **Labels:** 0.78rem, Medium 500, color text-soft. Siempre sobre el campo, nunca dentro de él.

### Navigation
- **Navbar:** Azul de Concilio (#000a23), 68px, borde inferior 2px oro, sticky top 0. El borde dorado es la única presencia del oro en una superficie estructural permanente.
- **Nav links:** Texto rgba(255,255,255,0.92). Hover: contenedor translúcido rgba(255,255,255,0.10) — el texto no cambia de color en hover, solo aparece el fondo.
- **Btn-nav:** Botón dorado en la barra — la única acción de conversión del navbar, contrasta con los links planos.
- **Mobile (≤768px):** Hamburger de tres líneas en oro. El menú se despliega verticalmente sobre el contenido.

### Sidebar
- **Background:** Humo de Sidebar (#f0ece0), borde derecho 1px.
- **Links inactivos:** color text-soft; hover background rgba(navy,0.05).
- **Link activo:** Background Pergamino (#f5edd0), texto #7a5500, borde izquierdo 2px oro. El borde izquierdo activo es el patrón actual del sistema — en iteraciones futuras considerar un bloque de color completo sin franja lateral.
- **Section labels:** 0.65rem, Bold 700, tracking 0.14em, uppercase, color text-muted. Casi invisibles; solo agrupan.

### Badges
- **Gold** (bg #d19309, text #000a23): Aprobación, estado activo destacado.
- **Teal** (bg azul-pale, text azul-deep): Información, en progreso.
- **Success** (bg green-tinted, text green): Completado.
- **Gray** (bg sutil, text text-muted): Inactivo, neutral.
- **Shape:** Píldora (100px radius) — el único elemento del sistema con forma de cápsula completa.

### Progress Bar
- **Track:** rgba(navy,0.08) — discreta, casi invisible.
- **Fill:** Gradiente horizontal Oro del Púlpito → Ámbar de Leccionario. El gradiente aquí es funcional: indica dirección de avance.
- **Height:** 8px, radius 100px. **Label:** 0.75rem, color oro, text-right.

### Exam Result Score
Componente signature. Card con `card-gold-top`, borde 2px oro, padding 2.5rem, shadow-lg, text-center. El score se muestra en 4.5rem Roboto color oro — el momento de mayor peso visual del sistema. El resultado de un examen bíblico merece esa escala.

## 6. Do's and Don'ts

### Do:
- **Do** usar Roboto Light (300) para h1 — es el gesto editorial que distingue este sistema de un portal genérico. El impacto viene del tamaño y el espacio, no del peso.
- **Do** reservar el Oro del Púlpito (#d19309) para acciones principales y señales de atención: botón primary, borde del navbar, focus de inputs, barra de progreso, badge de aprobación.
- **Do** usar transiciones de 0.15s–0.2s con ease estándar para todos los cambios de estado. Motion suave y breve; nunca elástico.
- **Do** mantener line-height 1.7 para el body text — el espacio entre líneas es accesibilidad para leer preguntas de examen en pantalla pequeña.
- **Do** verificar contraste mínimo 4.5:1 en todo texto body. Especialmente text-soft (#4a4560) sobre Papel de Biblia (#FAF7F0) y placeholders sobre fondo de input.
- **Do** reservar IM Fell English exclusivamente para versículos bíblicos citados — nunca en UI funcional.
- **Do** testear todo diseño nuevo en 375px de ancho antes de considerarlo completo. Los estudiantes usan celulares de gama media-baja en Colombia.

### Don't:
- **Don't** diseñar como un LMS corporativo estilo Coursera o Udemy. Sin gamificación, sin streaks de días seguidos, sin "23.456 estudiantes inscritos". Esto es un instituto bíblico.
- **Don't** usar producción visual estilo mega-iglesia americana: videos de fondo, efectos de luz dramáticos, marketing emocional de gran escala. El peso del contenido bíblico no necesita amplificación visual.
- **Don't** aplicar el patrón SaaS genérico de 2023: navy + white + gradients purple/teal. El sistema tiene identidad propia — no mezclarla con el lenguaje visual de herramientas de productividad.
- **Don't** tratar la interfaz como un portal gubernamental gris. Los estados hover deben existir. Los inputs en focus deben ser cálidos. El estudiante merece retroalimentación visual.
- **Don't** usar `border-left` mayor a 1px como único indicador de estado en cards. `.alert`, `.entrega-card.pendiente`, `.com-card.pendiente` y `.sidebar-link.active` ya usan este patrón — son candidatos a refactor. El estado debe comunicarse con color de fondo completo.
- **Don't** subir h1 a font-weight 700 o 900 "para que impacte". El impacto viene del tamaño clamp y del espacio en blanco, no del grosor. Light (300) grande es el lenguaje del sistema.
- **Don't** usar gradient text (`background-clip: text`), glassmorphism, o tarjetas de métricas hero con número grande + stat grid. Están prohibidos.
- **Don't** usar blanco puro (#fff) como background de body o de grandes superficies. El Papel de Biblia (#FAF7F0) es la superficie base del sistema.
