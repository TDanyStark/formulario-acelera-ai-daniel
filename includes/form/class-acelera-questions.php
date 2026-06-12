<?php

/**
 * Question definitions for the ACELERA diagnostic form.
 *
 * Single source of truth for every question, option and conditional rule.
 * Mirrors `Especificacion_Formulario_ACELERA.md` (Bloques 0–7) — never add
 * or remove questions here without updating that spec first.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 */

/**
 * Hardcoded ACELERA form definition + conditional engine + server validation.
 *
 * ## Question schema
 *
 * Every item returned by {@see Acelera_Questions::all()} has this shape:
 *
 *     [
 *       'id'        => 'p1_1',          // lowercase snake, unique
 *       'block'     => 1,               // 0–7 (6 covers 6-A/6-B/6-C + CV)
 *       'type'      => 'text|email|tel|date|single|multi|scale|repeater|file|textarea',
 *       'label'     => '…',             // Spanish, verbatim from the spec
 *       'required'  => true|false,      // only questions marked "obligatorio"
 *       'max'       => int|null,        // multi: max selections; scale: max value
 *       'options'   => [ ['value'=>'slug','label'=>'…'], … ] | null,
 *       'show_if'   => null | <condition>,
 *       'skippable' => true|false,      // shows the "Omitir" button
 *     ]
 *
 * Optional extra keys: 'prefill' ('display_name'|'user_email' — B0 only),
 * 'help' (descriptive sub-text), 'min' (scale), 'subfields' (repeater),
 * 'accept' + 'max_size_mb' (file).
 *
 * ## Condition schema (`show_if`)
 *
 * A condition is either a simple rule:
 *
 *     [ 'question' => 'p2_2', 'op' => 'in', 'value' => ['visa_vigente','en_proceso'] ]
 *
 * or a compound rule (nestable):
 *
 *     [ 'any' => [ <condition>, <condition>, … ] ]   // logical OR
 *     [ 'all' => [ <condition>, <condition>, … ] ]   // logical AND
 *
 * Supported operators:
 *
 * - `eq`       — answer equals value. For multi answers (arrays) it is true
 *                when the array contains the value.
 * - `in`       — value is a list. Scalar answer: true when the answer is in
 *                the list. Array answer (multi): true when the intersection
 *                is non-empty ("includes any of").
 * - `gte`      — numeric: (float) answer >= (float) value. Unanswered → false.
 * - `not_only` — for multi answers: false ONLY when the answer is exactly
 *                one selection equal to value (e.g. P3.3 = solo
 *                "homeschooling"); true otherwise, including unanswered.
 *
 * `show_if === null` means the question is always visible.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Questions {

	/**
	 * Ordered list of every question in the form (Bloques 0–7).
	 *
	 * @since  1.0.0
	 * @return array Ordered array of question definitions (see class docblock).
	 */
	public static function all(): array {
		$si_no = array(
			array( 'value' => 'si', 'label' => 'Sí' ),
			array( 'value' => 'no', 'label' => 'No' ),
		);

		// Reusable conditions.
		$cond_hijos = array(
			'question' => 'p3_1',
			'op'       => 'in',
			'value'    => array( 'pareja_hijos', 'hijos_sin_pareja' ),
		);
		$cond_pareja = array(
			'question' => 'p3_1',
			'op'       => 'in',
			'value'    => array( 'con_pareja', 'pareja_hijos' ),
		);
		$cond_bloque_6a = array(
			'question' => 'p5_2',
			'op'       => 'in',
			'value'    => array( 'empleado', 'independiente', 'sin_actividad' ),
		);
		$cond_bloque_6b = array(
			'question' => 'p5_2',
			'op'       => 'in',
			'value'    => array( 'dueno_negocio', 'idea_negocio' ),
		);
		$cond_ya_vende = array(
			'question' => 'p6b_1',
			'op'       => 'in',
			'value'    => array( 'vendo_irregular', 'vendo_constante', 'replicar_eeuu' ),
		);

		return array(

			/* ------------------------------------------------------------
			 * BLOQUE 0 · Datos de contacto (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p0_1',
				'block'     => 0,
				'type'      => 'text',
				'label'     => 'Nombre completo',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
				'prefill'   => 'display_name',
			),
			array(
				'id'        => 'p0_2',
				'block'     => 0,
				'type'      => 'email',
				'label'     => 'Correo electrónico',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
				'prefill'   => 'user_email',
			),
			array(
				'id'        => 'p0_3',
				'block'     => 0,
				'type'      => 'tel',
				'label'     => 'Celular / WhatsApp',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p0_4',
				'block'     => 0,
				'type'      => 'text',
				'label'     => 'Ciudad y país donde vives hoy',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p0_5',
				'block'     => 0,
				'type'      => 'text',
				'label'     => 'Nacionalidad',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p0_6',
				'block'     => 0,
				'type'      => 'date',
				'label'     => 'Fecha de nacimiento',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 1 · Objetivo (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p1_1',
				'block'     => 1,
				'type'      => 'multi',
				'label'     => '¿Cuál es tu objetivo principal en este momento?',
				'required'  => true,
				'max'       => 2,
				'options'   => array(
					array( 'value' => 'migratorio', 'label' => 'Resolver mi situación migratoria / conseguir una visa' ),
					array( 'value' => 'familia', 'label' => 'Mudarme con mi familia y establecernos bien (colegios, casa, ciudad)' ),
					array( 'value' => 'empleo', 'label' => 'Conseguir empleo o ejercer mi profesión en EE.UU.' ),
					array( 'value' => 'negocio', 'label' => 'Crear, traer o hacer crecer un negocio en EE.UU.' ),
					array( 'value' => 'invertir', 'label' => 'Invertir capital o proteger mi patrimonio en EE.UU.' ),
					array( 'value' => 'no_claro', 'label' => 'Todavía no lo tengo claro, necesito orientación' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p1_2',
				'block'     => 1,
				'type'      => 'single',
				'label'     => '¿En qué horizonte de tiempo quieres lograrlo?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'ya_en_eeuu', 'label' => 'Ya estoy en EE.UU. y necesito avanzar ya' ),
					array( 'value' => 'en_3_6_meses', 'label' => 'En los próximos 3-6 meses' ),
					array( 'value' => 'en_6_12_meses', 'label' => 'En 6-12 meses' ),
					array( 'value' => 'mas_de_1_ano', 'label' => 'En más de un año / explorando aún' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 2 · Situación migratoria (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p2_1',
				'block'     => 2,
				'type'      => 'single',
				'label'     => '¿Dónde vives actualmente?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'ya_en_eeuu', 'label' => 'Ya estoy en EE.UU.' ),
					array( 'value' => 'pais_origen', 'label' => 'En mi país de origen' ),
					array( 'value' => 'tercer_pais', 'label' => 'En un tercer país' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p2_2',
				'block'     => 2,
				'type'      => 'single',
				'label'     => '¿Cuál es tu estatus migratorio hoy?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'ciudadano_residente', 'label' => 'Ciudadano / residente (Green Card)' ),
					array( 'value' => 'visa_vigente', 'label' => 'Tengo visa vigente (trabajo, estudio, turismo, etc.)' ),
					array( 'value' => 'en_proceso', 'label' => 'En proceso / trámite migratorio abierto' ),
					array( 'value' => 'sin_estatus', 'label' => 'Sin estatus ni proceso iniciado' ),
					array( 'value' => 'prefiero_no_decir', 'label' => 'Prefiero no decir' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p2_3',
				'block'     => 2,
				'type'      => 'text',
				'label'     => '¿Qué tipo de visa o proceso tienes?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'question' => 'p2_2',
					'op'       => 'in',
					'value'    => array( 'visa_vigente', 'en_proceso' ),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p2_4',
				'block'     => 2,
				'type'      => 'single',
				'label'     => '¿Tienes permiso legal para trabajar, facturar o firmar contratos en EE.UU. hoy?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_sin_restricciones', 'label' => 'Sí, sin restricciones' ),
					array( 'value' => 'si_con_limitaciones', 'label' => 'Sí, pero con limitaciones' ),
					array( 'value' => 'no', 'label' => 'No' ),
					array( 'value' => 'no_seguro', 'label' => 'No estoy seguro' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p2_5',
				'block'     => 2,
				'type'      => 'single',
				'label'     => '¿Tu objetivo requiere que TÚ vivas, trabajes u operes directamente en EE.UU.?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'operar_yo_mismo', 'label' => 'Sí, necesito estar allá y operar yo mismo/a' ),
					array( 'value' => 'parcialmente', 'label' => 'Parcialmente (viajo, pero no me mudo del todo)' ),
					array( 'value' => 'desde_mi_pais', 'label' => 'No, puedo lograrlo desde mi país / a través de terceros' ),
					array( 'value' => 'no_seguro', 'label' => 'No estoy seguro/a' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p2_6',
				'block'     => 2,
				'type'      => 'single',
				'label'     => '¿Tienes abogado de inmigración acompañándote?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si', 'label' => 'Sí' ),
					array( 'value' => 'no', 'label' => 'No' ),
					array( 'value' => 'tuve_ya_no', 'label' => 'Tuve, pero ya no' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 3 · Familia y mudanza
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p3_1',
				'block'     => 3,
				'type'      => 'single',
				'label'     => '¿Vas a migrar (o migraste) con tu familia?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'solo', 'label' => 'Solo/a' ),
					array( 'value' => 'con_pareja', 'label' => 'Con mi pareja' ),
					array( 'value' => 'pareja_hijos', 'label' => 'Con mi pareja e hijos' ),
					array( 'value' => 'hijos_sin_pareja', 'label' => 'Con hijos (sin pareja)' ),
					array( 'value' => 'todos_en_eeuu', 'label' => 'Ya estamos todos en EE.UU.' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p3_2',
				'block'     => 3,
				'type'      => 'repeater',
				'label'     => 'Agrega a tus hijos',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => $cond_hijos,
				'skippable' => true,
				'subfields' => array(
					array(
						'key'      => 'nombre',
						'type'     => 'text',
						'label'    => 'Iniciales o nombre',
						'required' => true,
					),
					array(
						'key'      => 'edad',
						'type'     => 'number',
						'label'    => 'Edad',
						'required' => true,
						'min'      => 0,
						'max'      => 99,
					),
					array(
						'key'      => 'estudia',
						'type'     => 'single',
						'label'    => '¿Estudia actualmente?',
						'required' => true,
						'options'  => $si_no,
					),
				),
			),
			array(
				'id'        => 'p3_3',
				'block'     => 3,
				'type'      => 'multi',
				'label'     => '¿Qué modalidad educativa prefieren para tus hijos en EE.UU.?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'publico', 'label' => 'Colegio público' ),
					array( 'value' => 'privado', 'label' => 'Colegio privado' ),
					array( 'value' => 'bilingue', 'label' => 'Colegio bilingüe / inmersión en español' ),
					array( 'value' => 'homeschooling', 'label' => 'Homeschooling / educación en casa' ),
					array( 'value' => 'virtual', 'label' => 'Educación virtual / online' ),
					array( 'value' => 'no_decidido', 'label' => 'Aún no lo hemos decidido, necesitamos orientación' ),
				),
				'show_if'   => $cond_hijos,
				'skippable' => true,
			),
			array(
				'id'        => 'p3_4',
				'block'     => 3,
				'type'      => 'multi',
				'label'     => '¿Qué es lo más importante al elegir su colegio?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'nivel_academico', 'label' => 'Nivel académico / ranking' ),
					array( 'value' => 'idioma', 'label' => 'Idioma (bilingüe / español disponible)' ),
					array( 'value' => 'cercania', 'label' => 'Cercanía a la vivienda' ),
					array( 'value' => 'costo', 'label' => 'Costo / colegio público gratuito' ),
					array( 'value' => 'programas_especiales', 'label' => 'Programas especiales (deportes, arte, necesidades especiales)' ),
				),
				'show_if'   => array(
					'all' => array(
						$cond_hijos,
						array(
							'question' => 'p3_3',
							'op'       => 'not_only',
							'value'    => 'homeschooling',
						),
					),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p3_5',
				'block'     => 3,
				'type'      => 'single',
				'label'     => '¿Tu pareja también va a trabajar o generar ingresos en EE.UU.?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_clave', 'label' => 'Sí, es clave para nuestro sustento' ),
					array( 'value' => 'si_no_indispensable', 'label' => 'Sí, pero no es indispensable' ),
					array( 'value' => 'no_por_ahora', 'label' => 'No por ahora' ),
				),
				'show_if'   => $cond_pareja,
				'skippable' => true,
			),
			array(
				'id'        => 'p3_6',
				'block'     => 3,
				'type'      => 'single',
				'label'     => '¿Tienes mascotas que viajarían contigo?',
				'required'  => false,
				'max'       => null,
				'options'   => $si_no,
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p3_7',
				'block'     => 3,
				'type'      => 'text',
				'label'     => '¿Cuántas y de qué tipo?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'question' => 'p3_6',
					'op'       => 'eq',
					'value'    => 'si',
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p3_8',
				'block'     => 3,
				'type'      => 'text',
				'label'     => '¿Algún miembro de la familia tiene alguna condición médica o necesidad especial que debamos considerar?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => true,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 4 · Destino y llegada (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p4_1',
				'block'     => 4,
				'type'      => 'single',
				'label'     => '¿Tienes claro a qué estado o ciudad de EE.UU. quieres llegar?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'definido', 'label' => 'Sí, ya lo tengo definido' ),
					array( 'value' => 'algunas_opciones', 'label' => 'Tengo algunas opciones' ),
					array( 'value' => 'necesito_ayuda', 'label' => 'No, necesito ayuda para decidir' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p4_2',
				'block'     => 4,
				'type'      => 'text',
				'label'     => '¿Cuál(es)?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'question' => 'p4_1',
					'op'       => 'in',
					'value'    => array( 'definido', 'algunas_opciones' ),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p4_3',
				'block'     => 4,
				'type'      => 'multi',
				'label'     => '¿Qué pesa más para ti al elegir dónde vivir?',
				'required'  => false,
				'max'       => 3,
				'options'   => array(
					array( 'value' => 'trabajo', 'label' => 'Oportunidades de trabajo' ),
					array( 'value' => 'colegios', 'label' => 'Calidad de colegios' ),
					array( 'value' => 'costo_vida', 'label' => 'Costo de vida' ),
					array( 'value' => 'comunidad_hispana', 'label' => 'Comunidad hispana / cultural' ),
					array( 'value' => 'clima', 'label' => 'Clima' ),
					array( 'value' => 'cercania_familia', 'label' => 'Cercanía a familia/conocidos' ),
					array( 'value' => 'negocio_inversion', 'label' => 'Oportunidades de negocio/inversión' ),
				),
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p4_4',
				'block'     => 4,
				'type'      => 'single',
				'label'     => '¿Tienes red de apoyo en EE.UU. (familia, amigos, contactos)?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'solida', 'label' => 'Sí, sólida' ),
					array( 'value' => 'limitada', 'label' => 'Algo, pero limitada' ),
					array( 'value' => 'sin_red', 'label' => 'No, llegaría sin red' ),
				),
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p4_5',
				'block'     => 4,
				'type'      => 'text',
				'label'     => 'Cuéntanos brevemente quién y cómo te apoyaría',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'question' => 'p4_4',
					'op'       => 'in',
					'value'    => array( 'solida', 'limitada' ),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p4_6',
				'block'     => 4,
				'type'      => 'single',
				'label'     => '¿Cuál es tu nivel de inglés?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'basico', 'label' => 'Básico / nulo' ),
					array( 'value' => 'intermedio', 'label' => 'Intermedio' ),
					array( 'value' => 'avanzado', 'label' => 'Avanzado / fluido' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 5 · Perfil profesional y económico (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p5_1',
				'block'     => 5,
				'type'      => 'text',
				'label'     => '¿Cuál es tu profesión o formación?',
				'required'  => true,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p5_2',
				'block'     => 5,
				'type'      => 'single',
				'label'     => '¿Cuál es tu situación laboral o de actividad hoy?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'empleado', 'label' => 'Empleado/a (relación de dependencia)' ),
					array( 'value' => 'independiente', 'label' => 'Independiente / freelance' ),
					array( 'value' => 'dueno_negocio', 'label' => 'Dueño/a de un negocio en marcha' ),
					array( 'value' => 'idea_negocio', 'label' => 'Tengo una idea de negocio pero aún no la ejecuto' ),
					array( 'value' => 'inversiones', 'label' => 'Vivo de inversiones / rentas' ),
					array( 'value' => 'sin_actividad', 'label' => 'Sin actividad económica por ahora' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),
			array(
				'id'        => 'p5_3',
				'block'     => 5,
				'type'      => 'single',
				'label'     => '¿De cuánto capital dispones HOY para tu proyecto en EE.UU.?',
				'required'  => true,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'menos_10k', 'label' => 'Menos de $10.000 USD' ),
					array( 'value' => '10k_50k', 'label' => '$10.000 – $50.000 USD' ),
					array( 'value' => '50k_150k', 'label' => '$50.000 – $150.000 USD' ),
					array( 'value' => '150k_500k', 'label' => '$150.000 – $500.000 USD' ),
					array( 'value' => 'mas_500k', 'label' => 'Más de $500.000 USD' ),
					array( 'value' => 'prefiero_no_decir', 'label' => 'Prefiero no decir por ahora' ),
				),
				'show_if'   => null,
				'skippable' => false,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 6-A · Perfil EMPLEO / PROFESIONAL
			 * Condicional: P5.2 = empleado / independiente / sin actividad.
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p6a_1',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Qué buscas principalmente en lo profesional?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'conseguir_empleo', 'label' => 'Conseguir empleo en mi área' ),
					array( 'value' => 'validar_titulo', 'label' => 'Validar / homologar mi título' ),
					array( 'value' => 'reconvertirme', 'label' => 'Reconvertirme a otra industria' ),
					array( 'value' => 'crecer_empresa', 'label' => 'Crecer en la empresa donde ya estoy' ),
				),
				'show_if'   => $cond_bloque_6a,
				'skippable' => true,
			),
			array(
				'id'        => 'p6a_2',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Has trabajado tu red de contactos profesional (networking) en EE.UU.?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_activamente', 'label' => 'Sí, activamente' ),
					array( 'value' => 'algo', 'label' => 'Algo' ),
					array( 'value' => 'no_se_empezar', 'label' => 'No, no sé por dónde empezar' ),
				),
				'show_if'   => $cond_bloque_6a,
				'skippable' => true,
			),
			array(
				'id'        => 'p6a_3',
				'block'     => 6,
				'type'      => 'text',
				'label'     => '¿Qué habilidades o certificaciones te gustaría adquirir para mejorar tu empleabilidad?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => $cond_bloque_6a,
				'skippable' => true,
			),
			array(
				'id'        => 'p6a_4',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Tienes perfil de LinkedIn?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_actualizado', 'label' => 'Sí, y está actualizado y en inglés (o bilingüe)' ),
					array( 'value' => 'si_desactualizado', 'label' => 'Sí, pero está desactualizado o solo en español' ),
					array( 'value' => 'no_tengo', 'label' => 'No tengo / no lo uso' ),
				),
				'show_if'   => $cond_bloque_6a,
				'skippable' => true,
			),
			array(
				'id'        => 'p6a_5',
				'block'     => 6,
				'type'      => 'text',
				'label'     => 'Pega el enlace de tu perfil de LinkedIn',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'all' => array(
						$cond_bloque_6a,
						array(
							'question' => 'p6a_4',
							'op'       => 'in',
							'value'    => array( 'si_actualizado', 'si_desactualizado' ),
						),
					),
				),
				'skippable' => true,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 6-B · Perfil NEGOCIO / EMPRENDIMIENTO
			 * Condicional: P5.2 = dueño de negocio / idea de negocio.
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p6b_1',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿En qué punto está tu negocio?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'solo_idea', 'label' => 'Solo es una idea' ),
					array( 'value' => 'producto_sin_ventas', 'label' => 'Tengo el producto/servicio pero aún no vendo' ),
					array( 'value' => 'vendo_irregular', 'label' => 'Ya vendo, pero de forma irregular' ),
					array( 'value' => 'vendo_constante', 'label' => 'Vendo de forma constante y quiero escalar' ),
					array( 'value' => 'replicar_eeuu', 'label' => 'Quiero replicar en EE.UU. un negocio que ya tengo en mi país' ),
				),
				'show_if'   => $cond_bloque_6b,
				'skippable' => true,
			),
			array(
				'id'        => 'p6b_2',
				'block'     => 6,
				'type'      => 'textarea',
				'label'     => 'En 1-2 frases: ¿cuál es tu negocio y a quién le vendes?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => $cond_bloque_6b,
				'skippable' => true,
			),
			array(
				'id'        => 'p6b_3',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Cuánto factura tu negocio al mes en promedio (USD)?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'aun_no_facturo', 'label' => 'Aún no facturo' ),
					array( 'value' => 'menos_1k', 'label' => 'Menos de $1.000' ),
					array( 'value' => '1k_5k', 'label' => '$1.000 – $5.000' ),
					array( 'value' => '5k_20k', 'label' => '$5.000 – $20.000' ),
					array( 'value' => 'mas_20k', 'label' => 'Más de $20.000' ),
				),
				'show_if'   => array(
					'all' => array( $cond_bloque_6b, $cond_ya_vende ),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p6b_4',
				'block'     => 6,
				'type'      => 'single',
				'label'     => 'De cada $100 que entran, ¿cuánto te queda después de pagar todo?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'no_lo_se', 'label' => 'No lo sé' ),
					array( 'value' => 'menos_20', 'label' => 'Menos de $20' ),
					array( 'value' => '20_40', 'label' => 'Entre $20 y $40' ),
					array( 'value' => 'mas_40', 'label' => 'Más de $40' ),
				),
				'show_if'   => array(
					'all' => array( $cond_bloque_6b, $cond_ya_vende ),
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p6b_5',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Tu negocio depende 100% de ti para funcionar?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_se_detiene', 'label' => 'Sí, sin mí se detiene' ),
					array( 'value' => 'parcialmente', 'label' => 'Parcialmente' ),
					array( 'value' => 'funciona_sin_mi', 'label' => 'No, funciona sin mí' ),
				),
				'show_if'   => $cond_bloque_6b,
				'skippable' => true,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 6-C · Perfil INVERSIÓN / PATRIMONIO
			 * Condicional: P5.2 = inversiones O P5.3 ≥ $150k.
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p6c_1',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Qué te interesa principalmente?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'residencia_inversion', 'label' => 'Obtener residencia/visa a través de inversión (EB-5, E-2)' ),
					array( 'value' => 'bienes_raices', 'label' => 'Comprar propiedades / bienes raíces' ),
					array( 'value' => 'diversificar', 'label' => 'Diversificar/proteger mi patrimonio fuera de mi país' ),
					array( 'value' => 'invertir_sin_operar', 'label' => 'Invertir en un negocio existente sin operarlo' ),
				),
				'show_if'   => self::condition_bloque_6c(),
				'skippable' => true,
			),
			array(
				'id'        => 'p6c_2',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿Has invertido antes fuera de tu país?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'si_experiencia', 'label' => 'Sí, tengo experiencia' ),
					array( 'value' => 'primera_vez', 'label' => 'No, sería la primera vez' ),
				),
				'show_if'   => self::condition_bloque_6c(),
				'skippable' => true,
			),
			array(
				'id'        => 'p6c_3',
				'block'     => 6,
				'type'      => 'single',
				'label'     => '¿En qué horizonte quieres mover ese capital?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'inmediato', 'label' => 'Inmediato (ya tengo el capital listo)' ),
					array( 'value' => 'en_6_12_meses', 'label' => 'En los próximos 6-12 meses' ),
					array( 'value' => 'explorando', 'label' => 'Explorando opciones aún' ),
				),
				'show_if'   => self::condition_bloque_6c(),
				'skippable' => true,
			),

			/* ------------------------------------------------------------
			 * CARGA DE CV
			 * Condicional: P1.1 incluye migratorio/empleo O P5.2 =
			 * empleado/independiente. Opcional y saltable.
			 * ---------------------------------------------------------- */
			array(
				'id'          => 'cv_upload',
				'block'       => 6,
				'type'        => 'file',
				'label'       => 'Sube tu hoja de vida (CV) — PDF o Word',
				'help'        => 'La usamos para evaluar tu perfil profesional y, si aplica, tu elegibilidad para visas basadas en talento o méritos (como EB-2 NIW o B1). Si no la tienes a mano, puedes saltarte este paso y enviarla después.',
				'required'    => false,
				'max'         => null,
				'options'     => null,
				'show_if'     => array(
					'any' => array(
						array(
							'question' => 'p1_1',
							'op'       => 'in',
							'value'    => array( 'migratorio', 'empleo' ),
						),
						array(
							'question' => 'p5_2',
							'op'       => 'in',
							'value'    => array( 'empleado', 'independiente' ),
						),
					),
				),
				'skippable'   => true,
				'accept'      => array( 'pdf', 'doc', 'docx' ),
				'max_size_mb' => 10,
			),

			/* ------------------------------------------------------------
			 * BLOQUE 7 · Compromiso y cierre (siempre visible)
			 * ---------------------------------------------------------- */
			array(
				'id'        => 'p7_1',
				'block'     => 7,
				'type'      => 'textarea',
				'label'     => '¿Qué resultado concreto necesitas lograr en los próximos 90 días para sentir que valió la pena?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p7_2',
				'block'     => 7,
				'type'      => 'scale',
				'label'     => 'Del 1 al 5, ¿qué tan listo/a estás para ejecutar y tomar acción?',
				'required'  => false,
				'min'       => 1,
				'max'       => 5,
				'options'   => null,
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p7_3',
				'block'     => 7,
				'type'      => 'single',
				'label'     => '¿Cómo conociste a ACELERA / Cafecito con Cata?',
				'required'  => false,
				'max'       => null,
				'options'   => array(
					array( 'value' => 'redes', 'label' => 'Redes sociales' ),
					array( 'value' => 'recomendacion', 'label' => 'Recomendación' ),
					array( 'value' => 'webinar', 'label' => 'Webinar / evento' ),
					array( 'value' => 'busqueda_web', 'label' => 'Búsqueda web' ),
					array( 'value' => 'egresado', 'label' => 'Ya soy egresado/a' ),
					array( 'value' => 'otro', 'label' => 'Otro' ),
				),
				'show_if'   => null,
				'skippable' => true,
			),
			array(
				'id'        => 'p7_4',
				'block'     => 7,
				'type'      => 'text',
				'label'     => '¿En qué programa participaste antes?',
				'required'  => false,
				'max'       => null,
				'options'   => null,
				'show_if'   => array(
					'question' => 'p7_3',
					'op'       => 'eq',
					'value'    => 'egresado',
				),
				'skippable' => true,
			),
			array(
				'id'        => 'p7_5',
				'block'     => 7,
				'type'      => 'single',
				'label'     => '¿Autorizas el registro fotográfico/grabación de tu sesión de diagnóstico?',
				'required'  => true,
				'max'       => null,
				'options'   => $si_no,
				'show_if'   => null,
				'skippable' => false,
			),
		);
	}

	/**
	 * Compound visibility condition shared by every Bloque 6-C question.
	 *
	 * Activates by self-declaration (P5.2 = inversiones) OR by high capital
	 * (P5.3 ≥ $150k), per the spec note.
	 *
	 * @since  1.0.0
	 * @return array Condition (see class docblock).
	 */
	private static function condition_bloque_6c(): array {
		return array(
			'any' => array(
				array(
					'question' => 'p5_2',
					'op'       => 'eq',
					'value'    => 'inversiones',
				),
				array(
					'question' => 'p5_3',
					'op'       => 'in',
					'value'    => array( '150k_500k', 'mas_500k' ),
				),
			),
		);
	}

	/**
	 * Get a single question definition by ID.
	 *
	 * @since  1.0.0
	 * @param  string $id Question ID, e.g. 'p2_3'.
	 * @return array|null The question definition or null when unknown.
	 */
	public static function get( $id ): ?array {
		foreach ( self::all() as $question ) {
			if ( $question['id'] === $id ) {
				return $question;
			}
		}

		return null;
	}

	/**
	 * Server-side mirror of the JS conditional engine.
	 *
	 * Returns every question whose `show_if` condition passes for the given
	 * answers. Result preserves form order and is keyed by question ID.
	 *
	 * @since  1.0.0
	 * @param  array $answers Answers keyed by question ID.
	 * @return array<string, array> Visible question definitions keyed by ID.
	 */
	public static function visible_questions( array $answers ): array {
		$visible = array();

		foreach ( self::all() as $question ) {
			if ( self::evaluate_condition( $question['show_if'], $answers ) ) {
				$visible[ $question['id'] ] = $question;
			}
		}

		return $visible;
	}

	/**
	 * Evaluate a `show_if` condition against a set of answers.
	 *
	 * See the class docblock for the full condition schema (simple rules
	 * with eq|in|gte|not_only operators, compound any/all rules, nestable).
	 *
	 * @since  1.0.0
	 * @param  array|null $cond    Condition or null (always visible).
	 * @param  array      $answers Answers keyed by question ID.
	 * @return bool True when the condition passes (question visible).
	 */
	public static function evaluate_condition( $cond, array $answers ): bool {
		if ( null === $cond || array() === $cond ) {
			return true;
		}

		// Compound OR.
		if ( isset( $cond['any'] ) && is_array( $cond['any'] ) ) {
			foreach ( $cond['any'] as $sub ) {
				if ( self::evaluate_condition( $sub, $answers ) ) {
					return true;
				}
			}

			return false;
		}

		// Compound AND.
		if ( isset( $cond['all'] ) && is_array( $cond['all'] ) ) {
			foreach ( $cond['all'] as $sub ) {
				if ( ! self::evaluate_condition( $sub, $answers ) ) {
					return false;
				}
			}

			return true;
		}

		if ( ! isset( $cond['question'], $cond['op'] ) ) {
			return false;
		}

		$value  = isset( $cond['value'] ) ? $cond['value'] : null;
		$answer = isset( $answers[ $cond['question'] ] ) ? $answers[ $cond['question'] ] : null;

		switch ( $cond['op'] ) {
			case 'eq':
				if ( is_array( $answer ) ) {
					return in_array( $value, $answer, true );
				}

				return null !== $answer && (string) $answer === (string) $value;

			case 'in':
				$haystack = is_array( $value ) ? $value : array( $value );

				if ( is_array( $answer ) ) {
					return array() !== array_intersect( $answer, $haystack );
				}

				return null !== $answer && in_array( (string) $answer, array_map( 'strval', $haystack ), true );

			case 'gte':
				if ( null === $answer || is_array( $answer ) || ! is_numeric( $answer ) ) {
					return false;
				}

				return (float) $answer >= (float) $value;

			case 'not_only':
				// False only when the answer is exactly one selection equal
				// to $value (e.g. P3.3 = solo homeschooling). Unanswered or
				// mixed selections pass.
				if ( is_array( $answer ) && 1 === count( $answer ) ) {
					return (string) reset( $answer ) !== (string) $value;
				}

				return true;
		}

		return false;
	}

	/**
	 * Validate and sanitize a full set of submitted answers.
	 *
	 * Mirrors the frontend rules server-side: required visible questions
	 * must be answered, option values must be legal, formats (email, tel,
	 * date, scale) are enforced, multi max selections respected and the
	 * children repeater shape checked. Answers for hidden or unknown
	 * questions are silently discarded.
	 *
	 * @since  1.0.0
	 * @param  array $answers Raw answers keyed by question ID.
	 * @return array|WP_Error Sanitized answers keyed by question ID, or a
	 *                        WP_Error whose codes are question IDs with
	 *                        field-level messages.
	 */
	public static function validate_answers( array $answers ) {
		$errors    = new WP_Error();
		$sanitized = array();

		foreach ( self::visible_questions( $answers ) as $id => $question ) {
			$raw = isset( $answers[ $id ] ) ? $answers[ $id ] : null;

			if ( self::is_empty_answer( $raw ) ) {
				if ( $question['required'] ) {
					$errors->add( $id, sprintf( 'La pregunta "%s" es obligatoria.', $question['label'] ) );
				}

				continue;
			}

			$clean = self::sanitize_answer( $question, $raw, $errors );

			if ( null !== $clean ) {
				$sanitized[ $id ] = $clean;
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $sanitized;
	}

	/**
	 * Whether a raw answer counts as "not answered".
	 *
	 * @since  1.0.0
	 * @param  mixed $raw Raw answer value.
	 * @return bool
	 */
	private static function is_empty_answer( $raw ): bool {
		if ( null === $raw ) {
			return true;
		}

		if ( is_string( $raw ) && '' === trim( $raw ) ) {
			return true;
		}

		if ( is_array( $raw ) && array() === $raw ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitize a single answer according to its question type.
	 *
	 * Adds field-level messages to $errors and returns null on failure.
	 *
	 * @since  1.0.0
	 * @param  array    $question Question definition.
	 * @param  mixed    $raw      Raw answer.
	 * @param  WP_Error $errors   Error collector (codes = question IDs).
	 * @return mixed|null Sanitized value or null when invalid.
	 */
	private static function sanitize_answer( array $question, $raw, WP_Error $errors ) {
		$id = $question['id'];

		switch ( $question['type'] ) {
			case 'text':
				return sanitize_text_field( (string) $raw );

			case 'textarea':
				return sanitize_textarea_field( (string) $raw );

			case 'email':
				$email = sanitize_email( (string) $raw );

				if ( ! is_email( $email ) ) {
					$errors->add( $id, 'El correo electrónico no es válido.' );
					return null;
				}

				return $email;

			case 'tel':
				$tel    = sanitize_text_field( (string) $raw );
				$digits = preg_replace( '/[^0-9]/', '', $tel );

				if ( ! preg_match( '/^\+?[0-9 ()\-\.]{7,20}$/', $tel ) || strlen( $digits ) < 7 ) {
					$errors->add( $id, 'El teléfono no es válido (mínimo 7 dígitos; se permiten +, espacios y guiones).' );
					return null;
				}

				return $tel;

			case 'date':
				$date = sanitize_text_field( (string) $raw );

				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m )
					|| ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
					$errors->add( $id, 'La fecha no es válida (formato esperado AAAA-MM-DD).' );
					return null;
				}

				return $date;

			case 'single':
				$value = (string) ( is_array( $raw ) ? reset( $raw ) : $raw );

				if ( ! self::is_legal_option( $question, $value ) ) {
					$errors->add( $id, 'La opción seleccionada no es válida.' );
					return null;
				}

				return $value;

			case 'multi':
				$values = is_array( $raw ) ? array_values( $raw ) : array( $raw );
				$values = array_map( 'strval', $values );

				foreach ( $values as $value ) {
					if ( ! self::is_legal_option( $question, $value ) ) {
						$errors->add( $id, 'Una de las opciones seleccionadas no es válida.' );
						return null;
					}
				}

				if ( ! empty( $question['max'] ) && count( $values ) > (int) $question['max'] ) {
					$errors->add( $id, sprintf( 'Puedes seleccionar máximo %d opciones.', (int) $question['max'] ) );
					return null;
				}

				return array_values( array_unique( $values ) );

			case 'scale':
				$min = isset( $question['min'] ) ? (int) $question['min'] : 1;
				$max = isset( $question['max'] ) ? (int) $question['max'] : 5;

				if ( ! is_numeric( $raw ) || (int) $raw < $min || (int) $raw > $max ) {
					$errors->add( $id, sprintf( 'El valor debe estar entre %d y %d.', $min, $max ) );
					return null;
				}

				return (int) $raw;

			case 'repeater':
				return self::sanitize_repeater( $question, $raw, $errors );

			case 'file':
				// The actual upload happens via the /upload-cv endpoint;
				// here the answer is the resulting URL (cv_url).
				$url = esc_url_raw( (string) $raw );

				if ( '' === $url ) {
					$errors->add( $id, 'La URL del archivo no es válida.' );
					return null;
				}

				return $url;
		}

		// Unknown type: drop defensively.
		return null;
	}

	/**
	 * Validate the children repeater (P3.2) shape.
	 *
	 * Expected: array of items, each [ 'nombre' => string, 'edad' => int,
	 * 'estudia' => 'si'|'no' ].
	 *
	 * @since  1.0.0
	 * @param  array    $question Question definition (with 'subfields').
	 * @param  mixed    $raw      Raw answer.
	 * @param  WP_Error $errors   Error collector.
	 * @return array|null Sanitized rows or null when invalid.
	 */
	private static function sanitize_repeater( array $question, $raw, WP_Error $errors ) {
		$id = $question['id'];

		if ( ! is_array( $raw ) ) {
			$errors->add( $id, 'El formato del listado no es válido.' );
			return null;
		}

		$rows = array();

		foreach ( array_values( $raw ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$errors->add( $id, sprintf( 'El hijo #%d no tiene un formato válido.', $index + 1 ) );
				return null;
			}

			$nombre  = isset( $item['nombre'] ) ? sanitize_text_field( (string) $item['nombre'] ) : '';
			$edad    = isset( $item['edad'] ) ? $item['edad'] : null;
			$estudia = isset( $item['estudia'] ) ? (string) $item['estudia'] : '';

			if ( '' === $nombre ) {
				$errors->add( $id, sprintf( 'Falta el nombre o iniciales del hijo #%d.', $index + 1 ) );
				return null;
			}

			if ( ! is_numeric( $edad ) || (int) $edad < 0 || (int) $edad > 99 ) {
				$errors->add( $id, sprintf( 'La edad del hijo #%d no es válida.', $index + 1 ) );
				return null;
			}

			if ( ! in_array( $estudia, array( 'si', 'no' ), true ) ) {
				$errors->add( $id, sprintf( 'Indica si el hijo #%d estudia actualmente (sí/no).', $index + 1 ) );
				return null;
			}

			$rows[] = array(
				'nombre'  => $nombre,
				'edad'    => (int) $edad,
				'estudia' => $estudia,
			);
		}

		return $rows;
	}

	/**
	 * Whether a value is a legal option of a single/multi question.
	 *
	 * @since  1.0.0
	 * @param  array  $question Question definition.
	 * @param  string $value    Candidate value.
	 * @return bool
	 */
	private static function is_legal_option( array $question, $value ): bool {
		if ( empty( $question['options'] ) ) {
			return false;
		}

		foreach ( $question['options'] as $option ) {
			if ( (string) $option['value'] === (string) $value ) {
				return true;
			}
		}

		return false;
	}

}
