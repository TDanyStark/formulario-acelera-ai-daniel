# Brief — Plugin a medida: Curso ACELERA (WordPress + LearnDash)

## Contexto

Plugin hecho a medida para un curso específico de la academia (WordPress + LearnDash).

- **ID del curso:** `16242`
- **Modo del curso:** libre (cualquier clase es accesible por defecto)
- **Orden del curso:** ver `/orden_curso.md`
- **Código fuente de LearnDash de referencia:** `/sfwd-lms`

### Estructura del curso (5 módulos + bienvenida)

| Sección | Nombre |
|---|---|
| Bienvenida | Bienvenida (4 clases) |
| Módulo 1 | Decisión de Emigrar |
| Módulo 2 | Empresa y Emprendimiento |
| Módulo 3 | Profesional |
| Módulo 4 | Reubicación y Softlanding |
| Módulo 5 | Inversión / Patrimonio |

---

## Requerimiento 1 — Bienvenida obligatoria (gate de acceso)

- El módulo **Bienvenida** debe completarse obligatoriamente antes de avanzar.
- Las clases de los Módulos 1 a 5 **no deben ser accesibles** mientras el usuario no haya terminado las **4 clases de Bienvenida**.
- El resto del curso permanece en modo libre una vez superado el gate.

---

## Requerimiento 2 — Sidebar con acordeones

- Cambiar la forma en que LearnDash renderiza las secciones del listado izquierdo.
- Del **Módulo 2 en adelante**, las secciones deben verse como **acordeones desplegables**, para evitar que se muestren demasiadas clases a la vez.

---

## Requerimiento 3 — Formulario ACELERA (clase 4 de Bienvenida)

> Especificación completa: `/Especificacion_Formulario_ACELERA.md`

### Naturaleza

- Se inserta vía **shortcode** en la clase 4.
- **Evaluar si requiere IA o no — preferiblemente NO.** Lo importante es que al final ordene los 4 módulos por nivel de importancia según las respuestas del usuario.
- **Renombrado por usuario:** los módulos se renumeran a nivel de ese usuario (ej.: si para él el Módulo 5 es el primero, debe verse como "Módulo 1").

### Comportamiento

- Una vez respondido, **cada vez que el usuario visite esa sección verá el resultado** (su orden de ejecución del curso).
- Siempre disponible la opción **Resetear** para empezar de cero.

### Datos del usuario

- El formulario pide **nombre y correo**.
- Como solo lo ve un usuario logueado, **precargar nombre y email desde WordPress**, pero **permitir que el usuario los edite**.

### UX

- Súper intuitivo, **por pasos**: una pregunta a la vez.
- Botones: **Siguiente**, **Omitir** y **Atrás**.

### Preguntas

- Las preguntas van **hardcodeadas en el código** (sin constructor). Si cambian, se editan directamente en el código.

### Email al completar

- Al completar el formulario, enviar un correo al usuario con **el orden establecido según sus respuestas**.
- El correo debe permitir **clic directo para ir a la clase**.
- **No mostrar todas las clases**: solo los módulos. Al hacer clic en un módulo, llevar a la **primera clase de ese módulo**.

---

## Requerimiento 4 — Persistencia + integración con Clientify (CRM)

### Base de datos

- Guardar las respuestas del formulario en la base de datos, **asociadas al usuario** que lo respondió.

### Clientify

Documentación: https://newapi.clientify.com/

**Paso 1 — Crear el contacto** (responde el `id`):

```
POST https://api-plus.clientify.com/v2/contacts/
```

```json
{
  "first_name": "",
  "last_name": "",
  "owner": "info@cafecitoconcata.com",
  "phone": "",
  "email": "",
  "tags": ["ARRAY_DESDE_CONFIGURACION"],
  "contact_source": "plugin_acelera_da",
  "marketing_status": 2
}
```

**Paso 2 — Crear una nota con las respuestas:**

```
POST https://api-plus.clientify.com/v2/contacts/{id_del_contacto}/note/
```

```json
{
  "comment": "Aquí van las respuestas. Debe ser HTML básico: saltos de línea con <br>, negritas, etc.",
  "name": "Formulario Acelera PRO VERSION"
}
```

### Admin

- El plugin debe tener una **sección en el admin de WordPress** para editar estos campos: API key, owner, tags, etc.

---

## Requerimiento 5 — Feedback con LLM por módulo

- Conexión con un LLM: debe permitir **Claude o ChatGPT** (ambas API keys configurables en los ajustes del plugin).
- En la **última clase de cada módulo** se colocará un feedback generado por el LLM.
- El **prompt de cada módulo** debe ser configurable desde los ajustes del plugin (es el que le dice al LLM qué respuesta dar).
- El feedback **solo se ejecuta la primera vez** que se abre esa clase (cachear el resultado para no hacer llamadas repetidas al LLM).

---

## Archivos de referencia

| Archivo | Contenido |
|---|---|
| `/orden_curso.md` | Orden completo del curso |
| `/Especificacion_Formulario_ACELERA.md` | Especificación del formulario |
| `/sfwd-lms` | Código fuente de LearnDash para integración correcta |
