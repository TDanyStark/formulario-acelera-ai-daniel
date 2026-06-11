# 📋 Especificación: Formulario de Diagnóstico ACELERA

## Propósito del formulario

Este formulario alimenta un motor de IA que clasifica a cada cliente en una o varias de las **5 rutas de ACELERA**:

1. **Ruta Migratoria**
2. **Ruta Softlanding Familiar**
3. **Ruta Profesional**
4. **Ruta Empresa / Emprendimiento**
5. **Ruta Inversión / Patrimonio**

Cada pregunta captura "señales" que la IA pondera para puntuar las rutas. Un cliente puede activar varias a la vez (ej. empresario que migra con familia = Empresa + Softlanding + Migratoria). El formulario está diseñado para que **cualquier pregunta que no aplique al cliente se pueda saltar**, mediante lógica condicional.

**Regla de diseño general:** orden de lo general a lo específico; nada se pregunta dos veces; ninguna pregunta queda "a medio pedir" (los datos relacionados se capturan juntos).

---

## BLOQUE 0 · Datos de contacto
*(Sin lógica de ruta. Se muestra siempre.)*

**P0.1 — Nombre completo** · *(texto, obligatorio)*
**P0.2 — Correo electrónico** · *(email, obligatorio)*
**P0.3 — Celular / WhatsApp** · *(teléfono, obligatorio)*
**P0.4 — Ciudad y país donde vives hoy** · *(texto, obligatorio)*
**P0.5 — Nacionalidad** · *(texto, obligatorio)*
**P0.6 — Fecha de nacimiento** · *(selector de fecha, obligatorio)*

> **Por qué:** La edad importa para rutas de Inversión (perfil patrimonial) y Profesional (años de experiencia esperables). La nacionalidad influye en qué tipos de visa están disponibles.

---

## BLOQUE 1 · La pregunta que ordena todo lo demás
*(Se muestra siempre. Dispara la visibilidad de bloques posteriores.)*

**P1.1 — ¿Cuál es tu objetivo principal en este momento?** *(multi-selección, máx. 2, obligatorio)*
- Resolver mi situación migratoria / conseguir una visa
- Mudarme con mi familia y establecernos bien (colegios, casa, ciudad)
- Conseguir empleo o ejercer mi profesión en EE.UU.
- Crear, traer o hacer crecer un negocio en EE.UU.
- Invertir capital o proteger mi patrimonio en EE.UU.
- Todavía no lo tengo claro, necesito orientación

> **Por qué:** Es la señal directa de auto-clasificación; le da a la IA el peso inicial de cada ruta. La opción "no lo tengo claro" marca a quien necesita diagnóstico abierto y no debe ser forzado a un solo carril. Se permite **máximo 2** selecciones porque muchos clientes tienen objetivos combinados.
> **Disparador:** esta respuesta controla qué bloques condicionales aparecen después (CV, bloques profesional/negocio/inversión).

**P1.2 — ¿En qué horizonte de tiempo quieres lograrlo?** *(selección única, obligatorio)*
- Ya estoy en EE.UU. y necesito avanzar ya
- En los próximos 3-6 meses
- En 6-12 meses
- En más de un año / explorando aún

> **Por qué:** La urgencia diferencia a un cliente "listo para ejecutar" de uno "explorando". Alimenta nivel de prioridad y tipo de acompañamiento. Urgencia alta + objetivo migratorio = Ruta Migratoria con máxima prioridad.

---

## BLOQUE 2 · Situación migratoria
*(Se muestra siempre — todas las rutas necesitan este contexto.)*

**P2.1 — ¿Dónde vives actualmente?** *(selección única, obligatorio)*
- Ya estoy en EE.UU.
- En mi país de origen
- En un tercer país

**P2.2 — ¿Cuál es tu estatus migratorio hoy?** *(selección única, obligatorio)*
- Ciudadano / residente (Green Card)
- Tengo visa vigente (trabajo, estudio, turismo, etc.)
- En proceso / trámite migratorio abierto
- Sin estatus ni proceso iniciado
- Prefiero no decir

**P2.3 — ¿Qué tipo de visa o proceso tienes?** *(texto corto)*
> **CONDICIONAL:** mostrar solo si P2.2 = "Tengo visa vigente" **o** "En proceso / trámite abierto".
> **Por qué:** No tiene sentido pedir el tipo de visa a quien dijo no tener ninguna. Saltarla evita fricción.

**P2.4 — ¿Tienes permiso legal para trabajar, facturar o firmar contratos en EE.UU. hoy?** *(selección única, obligatorio)*
- Sí, sin restricciones
- Sí, pero con limitaciones
- No
- No estoy seguro

> **Por qué:** Es un **dato de capacidad, no un veredicto.** Captura qué puede hacer legalmente hoy, sin asumir qué necesita. (Ver P2.5: el permiso solo se vuelve bloqueador si el objetivo requiere operar directamente.)

**P2.5 — ¿Tu objetivo requiere que TÚ vivas, trabajes u operes directamente en EE.UU.?** *(selección única, obligatorio)*
- Sí, necesito estar allá y operar yo mismo/a
- Parcialmente (viajo, pero no me mudo del todo)
- No, puedo lograrlo desde mi país / a través de terceros
- No estoy seguro/a

> **Por qué:** Esta pregunta decide si el permiso (P2.4) es un bloqueador o no. Solo cuando P2.5 = "operar yo mismo" **y** P2.4 = "No", la Ruta Migratoria se vuelve prerequisito. Un inversionista pasivo (P2.5 = "desde mi país / terceros") puede responder "No" en el permiso sin que eso dispare la Ruta Migratoria.

**P2.6 — ¿Tienes abogado de inmigración acompañándote?** *(selección única, obligatorio)*
- Sí
- No
- Tuve, pero ya no

> **Por qué:** Detecta quién necesita ese recurso. "No" + urgencia alta = necesidad inmediata de la red migratoria.

---

## BLOQUE 3 · Familia y mudanza
*(Se muestra siempre; las preguntas internas son condicionales. Alimenta Ruta Softlanding.)*

**P3.1 — ¿Vas a migrar (o migraste) con tu familia?** *(selección única, obligatorio)*
- Solo/a
- Con mi pareja
- Con mi pareja e hijos
- Con hijos (sin pareja)
- Ya estamos todos en EE.UU.

> **Por qué:** Disparador maestro de la Ruta Softlanding Familiar. Migrar con hijos multiplica las necesidades (colegios, vivienda familiar, comunidad).

**P3.2 — Agrega a tus hijos** *(repetidor: por cada hijo → iniciales o nombre, edad, ¿estudia actualmente? Sí/No)*
> **CONDICIONAL:** mostrar solo si P3.1 incluye hijos ("Con mi pareja e hijos" o "Con hijos sin pareja").
> **Por qué:** La edad determina nivel escolar (preescolar vs. universidad) y el tipo de acompañamiento educativo. El repetidor captura cuántos y sus edades de una sola vez, evitando preguntas separadas.

**P3.3 — ¿Qué modalidad educativa prefieren para tus hijos en EE.UU.?** *(multi-selección)*
- Colegio público
- Colegio privado
- Colegio bilingüe / inmersión en español
- Homeschooling / educación en casa
- Educación virtual / online
- Aún no lo hemos decidido, necesitamos orientación

> **CONDICIONAL:** mostrar solo si P3.1 incluye hijos.
> **Por qué:** Captura la preferencia de modalidad. Homeschooling cambia por completo el acompañamiento (no buscas colegios, buscas currículos y regulación estatal). "Aún no decidido" es señal de necesidad de orientación educativa.

**P3.4 — ¿Qué es lo más importante al elegir su colegio?** *(multi-selección)*
- Nivel académico / ranking
- Idioma (bilingüe / español disponible)
- Cercanía a la vivienda
- Costo / colegio público gratuito
- Programas especiales (deportes, arte, necesidades especiales)

> **CONDICIONAL:** mostrar solo si P3.1 incluye hijos **y** P3.3 **no** es exclusivamente "Homeschooling".
> **Por qué:** No tiene sentido preguntar qué buscar en un colegio a quien educará en casa. Insumo directo para el equipo de softlanding.

**P3.5 — ¿Tu pareja también va a trabajar o generar ingresos en EE.UU.?** *(selección única)*
- Sí, es clave para nuestro sustento
- Sí, pero no es indispensable
- No por ahora

> **CONDICIONAL:** mostrar solo si P3.1 incluye pareja ("Con mi pareja" o "Con mi pareja e hijos").
> **Por qué:** Si la pareja necesita trabajar, puede activar una segunda Ruta Profesional dentro del mismo núcleo. También afecta el cálculo de presión financiera.

**P3.6 — ¿Tienes mascotas que viajarían contigo?** *(selección única)*
- Sí
- No

**P3.7 — ¿Cuántas y de qué tipo?** *(texto corto)*
> **CONDICIONAL:** mostrar solo si P3.6 = "Sí".
> **Por qué:** Detalle logístico real de softlanding (reubicar mascotas es un dolor común). Bajo peso en clasificación, alto valor de servicio.

**P3.8 — ¿Algún miembro de la familia tiene alguna condición médica o necesidad especial que debamos considerar?** *(texto corto, opcional — saltar si no aplica)*
> **Por qué:** Insumo de softlanding (cercanía a hospitales, seguros). Sensible, por eso opcional y saltable.

---

## BLOQUE 4 · Destino y llegada
*(Se muestra siempre. Alimenta Softlanding y contextualiza las demás rutas.)*

**P4.1 — ¿Tienes claro a qué estado o ciudad de EE.UU. quieres llegar?** *(selección única, obligatorio)*
- Sí, ya lo tengo definido
- Tengo algunas opciones
- No, necesito ayuda para decidir

**P4.2 — ¿Cuál(es)?** *(texto corto)*
> **CONDICIONAL:** mostrar solo si P4.1 = "Sí, ya lo tengo definido" **o** "Tengo algunas opciones".
> **Por qué:** "No sé a dónde" es necesidad fuerte de orientación/softlanding. "Ya lo tengo" permite preparar recursos locales concretos.

**P4.3 — ¿Qué pesa más para ti al elegir dónde vivir?** *(multi-selección o top 3)*
- Oportunidades de trabajo
- Calidad de colegios
- Costo de vida
- Comunidad hispana / cultural
- Clima
- Cercanía a familia/conocidos
- Oportunidades de negocio/inversión

> **Por qué:** Las prioridades revelan la ruta implícita: quien prioriza "negocio/inversión" se inclina a Empresa/Inversión; quien prioriza "colegios" refuerza Softlanding.

**P4.4 — ¿Tienes red de apoyo en EE.UU. (familia, amigos, contactos)?** *(selección única)*
- Sí, sólida
- Algo, pero limitada
- No, llegaría sin red

**P4.5 — Cuéntanos brevemente quién y cómo te apoyaría** *(texto corto)*
> **CONDICIONAL:** mostrar solo si P4.4 = "Sí, sólida" o "Algo, pero limitada".
> **Por qué:** Quien llega sin red necesita softlanding completo; quien tiene red sólida puede acelerar otras rutas. Las dos preguntas antiguas ("conocidos que te apoyen" + "red de apoyo") quedan unificadas aquí.

**P4.6 — ¿Cuál es tu nivel de inglés?** *(selección única, obligatorio)*
- Básico / nulo
- Intermedio
- Avanzado / fluido

> **Por qué:** Filtro transversal. Inglés bajo es barrera para Ruta Profesional (empleabilidad) y debe señalarse como área de desarrollo en cualquier ruta.

---

## BLOQUE 5 · Perfil profesional y económico
*(Se muestra siempre. Es la bisagra que separa Profesional / Empresa / Inversión.)*

**P5.1 — ¿Cuál es tu profesión o formación?** *(texto corto, obligatorio)*

**P5.2 — ¿Cuál es tu situación laboral o de actividad hoy?** *(selección única, obligatorio)*
- Empleado/a (relación de dependencia)
- Independiente / freelance
- Dueño/a de un negocio en marcha
- Tengo una idea de negocio pero aún no la ejecuto
- Vivo de inversiones / rentas
- Sin actividad económica por ahora

> **Por qué:** **Pregunta-bisagra del bloque económico.** Separa con precisión: "Empleado" → Profesional; "Dueño de negocio" o "Idea sin ejecutar" → Empresa; "Vivo de inversiones" → Inversión. Determina qué bloque profundo se muestra después (6-A, 6-B o 6-C).
> **Disparador:** controla la visibilidad de los Bloques 6-A / 6-B / 6-C.

**P5.3 — ¿De cuánto capital dispones HOY para tu proyecto en EE.UU.?** *(selección única, obligatorio)*
- Menos de $10.000 USD
- $10.000 – $50.000 USD
- $50.000 – $150.000 USD
- $150.000 – $500.000 USD
- Más de $500.000 USD
- Prefiero no decir por ahora

> **Por qué:** Señal decisiva entre rutas. Capital alto ($150k+) habilita Ruta Inversión (E-2, EB-5) y Empresa robusta; capital bajo orienta a Profesional o Empresa bootstrap. Por **rangos** (no monto exacto) para que sea fácil y menos invasivo. "Prefiero no decir" reduce abandono.
> **Nota para ajuste:** los cortes deberían mapear a los umbrales reales de las visas que trabajan (E-2 ≈ $100k-200k, EB-5 ≈ $800k+). Confirmar si conviene afinar los rangos.

---

## BLOQUE 6-A · Perfil EMPLEO / PROFESIONAL
> **CONDICIONAL — todo el bloque:** mostrar solo si P5.2 = "Empleado", "Independiente / freelance" o "Sin actividad económica".

**P6A.1 — ¿Qué buscas principalmente en lo profesional?** *(selección única)*
- Conseguir empleo en mi área
- Validar / homologar mi título
- Reconvertirme a otra industria
- Crecer en la empresa donde ya estoy

**P6A.2 — ¿Has trabajado tu red de contactos profesional (networking) en EE.UU.?** *(selección única)*
- Sí, activamente
- Algo
- No, no sé por dónde empezar

**P6A.3 — ¿Qué habilidades o certificaciones te gustaría adquirir para mejorar tu empleabilidad?** *(texto corto, opcional)*

**P6A.4 — ¿Tienes perfil de LinkedIn?** *(selección única)*
- Sí, y está actualizado y en inglés (o bilingüe)
- Sí, pero está desactualizado o solo en español
- No tengo / no lo uso

> **Por qué:** LinkedIn es el canal principal de empleabilidad en EE.UU. y sustento documental para visas como EB-2 NIW (demuestra reconocimiento profesional). Un perfil débil o solo en español es un área de desarrollo inmediata. La respuesta ya indica si necesita optimización, sin pregunta extra.

**P6A.5 — Pega el enlace de tu perfil de LinkedIn** *(texto corto, opcional)*
> **CONDICIONAL:** mostrar solo si P6A.4 = "Sí…" (cualquiera de las dos opciones con LinkedIn).

> **Por qué este bloque es condicional:** A un empresario consolidado no le sirve hablar de "conseguir empleo". Solo se muestra al perfil profesional/empleado, manteniendo el formulario corto para los demás.

---

## BLOQUE 6-B · Perfil NEGOCIO / EMPRENDIMIENTO
> **CONDICIONAL — todo el bloque:** mostrar solo si P5.2 = "Dueño/a de un negocio en marcha" o "Tengo una idea de negocio pero aún no la ejecuto".

**P6B.1 — ¿En qué punto está tu negocio?** *(selección única)*
- Solo es una idea
- Tengo el producto/servicio pero aún no vendo
- Ya vendo, pero de forma irregular
- Vendo de forma constante y quiero escalar
- Quiero replicar en EE.UU. un negocio que ya tengo en mi país

> **Por qué:** Define etapa de madurez = tipo de acompañamiento empresarial. Reemplaza varias preguntas dispersas de los formularios viejos ("¿ya operas?", "¿hace cuánto?", "¿etapa?") en una sola escalera clara.

**P6B.2 — En 1-2 frases: ¿cuál es tu negocio y a quién le vendes?** *(texto)*
> **Por qué:** El "pitch" en una sola pregunta abierta. La IA puede extraer industria, modelo y cliente de aquí sin necesidad de 4 preguntas separadas.

**P6B.3 — ¿Cuánto factura tu negocio al mes en promedio (USD)?** *(selección única)*
- Aún no facturo
- Menos de $1.000
- $1.000 – $5.000
- $5.000 – $20.000
- Más de $20.000

> **CONDICIONAL:** mostrar solo si P6B.1 indica que ya vende ("Ya vendo irregular", "Vendo constante", o "Replicar negocio existente").

**P6B.4 — De cada $100 que entran, ¿cuánto te queda después de pagar todo?** *(selección única)*
- No lo sé
- Menos de $20
- Entre $20 y $40
- Más de $40

> **CONDICIONAL:** mostrar solo si P6B.1 indica que ya vende (igual que P6B.3).
> **Por qué condicional:** Preguntar facturación y margen a quien "solo tiene una idea" genera frustración y datos vacíos. Se saltan automáticamente.

**P6B.5 — ¿Tu negocio depende 100% de ti para funcionar?** *(selección única)*
- Sí, sin mí se detiene
- Parcialmente
- No, funciona sin mí

> **Por qué:** Señal de madurez operativa / capacidad de delegar — clave para saber si está listo para escalar o necesita estructura primero.

---

## BLOQUE 6-C · Perfil INVERSIÓN / PATRIMONIO
> **CONDICIONAL — todo el bloque:** mostrar si P5.2 = "Vivo de inversiones / rentas" **O** P5.3 = "$150.000 – $500.000" o "Más de $500.000".
> *(Se activa por declaración O por capital alto, para capturar al inversionista aunque no se autoidentifique como tal.)*

**P6C.1 — ¿Qué te interesa principalmente?** *(selección única)*
- Obtener residencia/visa a través de inversión (EB-5, E-2)
- Comprar propiedades / bienes raíces
- Diversificar/proteger mi patrimonio fuera de mi país
- Invertir en un negocio existente sin operarlo

**P6C.2 — ¿Has invertido antes fuera de tu país?** *(selección única)*
- Sí, tengo experiencia
- No, sería la primera vez

**P6C.3 — ¿En qué horizonte quieres mover ese capital?** *(selección única)*
- Inmediato (ya tengo el capital listo)
- En los próximos 6-12 meses
- Explorando opciones aún

> **Por qué este bloque:** Distingue al que busca visa-por-inversión (cruce con Ruta Migratoria) del que solo quiere proteger patrimonio de forma pasiva (no necesita migrar).

---

## CARGA DE HOJA DE VIDA (CV)
> **CONDICIONAL — campo de subida de archivo:** mostrar si P1.1 incluye objetivo "migratorio" o "profesional" **O** P5.2 = "Empleado / Independiente / Profesional" **O** la intención apunta a visa de talento (EB-2 NIW / B1).
> *(Ubicar al final del Bloque 6-A o tras el Bloque 5, según el flujo que defina el constructor.)*

**Texto del campo:**

> **Sube tu hoja de vida (CV) — PDF o Word**
> *La usamos para evaluar tu perfil profesional y, si aplica, tu elegibilidad para visas basadas en talento o méritos (como EB-2 NIW o B1). Si no la tienes a mano, puedes saltarte este paso y enviarla después.*

> **Por qué condicional y opcional:** Pedir CV a un inversionista pasivo o a un cliente puramente familiar genera fricción innecesaria. Dejarlo opcional con "envíala después" evita abandono por no tener el archivo a mano — capturas el lead igual.
> **Nota técnica para el constructor:** confirmar el peso máximo permitido por archivo en SureForms y verificar que el backend de IA pueda **leer el contenido** del CV (no solo almacenar el enlace), ya que se necesita parsear el documento para evaluar elegibilidad EB-2 NIW / B1.

---

## BLOQUE 7 · Compromiso y cierre
*(Se muestra siempre.)*

**P7.1 — ¿Qué resultado concreto necesitas lograr en los próximos 90 días para sentir que valió la pena?** *(texto)*
> **Por qué:** Revela expectativa real y urgencia. Insumo cualitativo para que la IA module el tono de la recomendación.

**P7.2 — Del 1 al 5, ¿qué tan listo/a estás para ejecutar y tomar acción?** *(escala 1-5)*
> **Por qué:** Filtra "soñadores" de "ejecutores". No cambia la ruta, pero sí la prioridad comercial y el tipo de seguimiento.

**P7.3 — ¿Cómo conociste a ACELERA / Cafecito con Cata?** *(selección única)*
- Redes sociales
- Recomendación
- Webinar / evento
- Búsqueda web
- Ya soy egresado/a
- Otro

**P7.4 — ¿En qué programa participaste antes?** *(texto corto)*
> **CONDICIONAL:** mostrar solo si P7.3 = "Ya soy egresado/a".

**P7.5 — ¿Autorizas el registro fotográfico/grabación de tu sesión de diagnóstico?** *(selección única, obligatorio)*
- Sí
- No

---

## ANEXO · Lógica del motor de IA (resumen para el agente)

La IA puntúa cada ruta de 0 a 100 combinando señales:

| Ruta | Señales principales |
|------|---------------------|
| **Migratoria** | P2.2 (sin estatus), P2.4 + P2.5 (sin permiso **y** necesita operar = bloqueador → prioridad 1), P1.2 (urgencia), P2.6 (sin abogado) |
| **Softlanding** | P3.1 (familia/hijos), P3.2-3.4 (colegios/modalidad), P4.1 (sin ciudad definida), P4.4 (sin red) |
| **Profesional** | P5.2 (empleado), Bloque 6-A completo, P4.6 (inglés), CV |
| **Empresa** | P5.2 (negocio/idea), Bloque 6-B completo |
| **Inversión** | P5.3 (capital alto), Bloque 6-C completo |

**Reglas especiales:**

- El permiso de trabajo (P2.4) **solo es bloqueador** cuando P2.5 = "necesito operar yo mismo". Un inversionista pasivo con "No" en permiso **no** dispara Ruta Migratoria.
- Una persona puede activar **varias rutas**; la IA debe entregar una ruta principal + rutas complementarias con su orden de prioridad. Ejemplo de salida: *"Tu ruta principal es **Empresa**, pero primero resuelve **Migratoria** (bloqueador: sin permiso de trabajo y necesitas operar tú) y en paralelo activa **Softlanding** porque migras con 2 hijos en edad escolar."*
- "No estoy seguro" en cualquier pregunta crítica → marcar para **revisión con asesor**, no asumir.

---

## RESUMEN DE CAMPOS CONDICIONALES (checklist para el constructor)

| Pregunta | Se muestra si… |
|----------|----------------|
| P2.3 (tipo de visa) | P2.2 = visa vigente o en proceso |
| P3.2 (repetidor hijos) | P3.1 incluye hijos |
| P3.3 (modalidad educativa) | P3.1 incluye hijos |
| P3.4 (características colegio) | P3.1 incluye hijos Y P3.3 ≠ solo homeschooling |
| P3.5 (pareja trabaja) | P3.1 incluye pareja |
| P3.7 (mascotas detalle) | P3.6 = Sí |
| P4.2 (cuál ciudad) | P4.1 = definido o algunas opciones |
| P4.5 (detalle red apoyo) | P4.4 = sólida o limitada |
| Bloque 6-A (profesional) | P5.2 = empleado / independiente / sin actividad |
| P6A.5 (link LinkedIn) | P6A.4 = tiene LinkedIn |
| Bloque 6-B (negocio) | P5.2 = dueño de negocio / idea |
| P6B.3 y P6B.4 (facturación/margen) | P6B.1 indica que ya vende |
| Bloque 6-C (inversión) | P5.2 = vive de inversiones O P5.3 ≥ $150k |
| Carga de CV | objetivo migratorio/profesional O P5.2 profesional/empleado |
| P7.4 (programa anterior) | P7.3 = egresado |
