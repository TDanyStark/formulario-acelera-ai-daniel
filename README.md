# Formulario Acelara AI Daniel

Plugin de WordPress que añade un formulario integrado con **LearnDash**. El formulario funciona como una **clase dentro de un curso**: a partir de las respuestas del usuario, define la **ruta** (módulo del curso) que más le conviene. Los datos del formulario se envían a **Clientify**, el CRM de la organización.

- **Versión:** 1.0.0
- **Autor:** Daniel Amado
- **Autor URI:** https://danielamado.com
- **Licencia:** GPL-2.0+
- **Text Domain:** `formulario-acelara-ai-daniel`

## Descripción

Este plugin entrega una experiencia de formulario dentro de un curso de LearnDash. El curso contiene varios **módulos**, donde cada módulo representa una **ruta** posible para el usuario. El formulario evalúa las respuestas y recomienda/activa la ruta más adecuada para cada estudiante.

Adicionalmente, la información capturada se sincroniza con **Clientify (CRM)** para gestión comercial y de marketing.

### Conceptos clave

| Concepto | Descripción |
|----------|-------------|
| **Curso (LearnDash)** | Contenedor principal donde vive el formulario como una clase. |
| **Módulos = Rutas** | Cada módulo del curso es una ruta posible para el usuario. |
| **Formulario** | Recoge respuestas y determina la ruta recomendada. |
| **Clientify (CRM)** | Destino de los datos del formulario para seguimiento comercial. |

## Requisitos

- WordPress 6.0 o superior
- PHP 7.4 o superior
- Plugin **LearnDash** activo
- Cuenta y API de **Clientify**

## Instalación

1. Copia la carpeta `formulario-acelara-ai-daniel` en `wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Configura las credenciales de Clientify en los ajustes del plugin.

## Estructura del proyecto

```
formulario-acelara-ai-daniel/
├── formulario-acelara-ai-daniel.php   # Archivo principal (header + bootstrap)
├── includes/
│   └── class-faad-plugin.php          # Clase principal (carga de hooks)
├── admin/                             # Lógica y assets del panel de administración
├── public/
│   ├── css/faad-public.css            # Estilos del front-end
│   └── js/faad-public.js              # Scripts del front-end
├── languages/                         # Archivos de traducción (.pot / .po / .mo)
├── README.md
└── .gitignore
```

## Hoja de ruta (pendiente)

- [ ] Definir el shortcode / bloque del formulario.
- [ ] Lógica de evaluación de respuestas → ruta recomendada.
- [ ] Integración con LearnDash (asignación de módulo/ruta).
- [ ] Conexión con la API de Clientify (creación/actualización de contactos).
- [ ] Pantalla de ajustes para credenciales de Clientify.
- [ ] Internacionalización (archivo `.pot`).

## Desarrollo

El plugin sigue una estructura por capas inspirada en el WordPress Plugin Boilerplate, con un prefijo `faad_` / `FAAD_` para funciones, constantes y clases.

## Licencia

GPL-2.0+. Ver el encabezado del archivo principal para más detalles.
