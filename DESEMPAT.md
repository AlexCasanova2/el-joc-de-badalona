# Plan de acción para implementar el desempate en el plugin existente de WordPress

## Contexto general

El proyecto es un juego en WordPress. Las preguntas normales del juego están guardadas como Custom Post Type `pregunta`. La información de los usuarios y sus puntos se guarda en `wp_users` y `wp_usermeta`.

Hay metadatos de usuario relacionados con el juego, entre ellos:

* `puntos_totales`
* `respuestas_preguntas`
* `respuestas_detalladas`
* posibles metas de respuestas pendientes

Además, algunas preguntas del juego pueden estar marcadas como nulas mediante el metadato:

```php
_ejb_pregunta_nula = si
```

Esto es muy importante: **las preguntas nulas no deben contar para calcular el 100% de aciertos del usuario**.

El objetivo es añadir al plugin una funcionalidad de desempate con 3 preguntas abiertas de texto libre.

---

# Objetivo funcional

Crear una funcionalidad de desempate dentro del plugin actual de WordPress.

La funcionalidad debe permitir:

1. Configurar desde el administrador las 3 preguntas de desempate.
2. Publicar el desempate automáticamente en la web a partir de una fecha y hora configurada.
3. Mostrar un formulario con 3 campos de respuesta libre.
4. Permitir que cualquier usuario logueado pueda responder, independientemente de sus errores.
5. Guardar las respuestas en base de datos.
6. Guardar la fecha y hora exacta del envío.
7. Guardar una foto del estado del jugador en el momento de enviar:

   * puntos actuales
   * total de preguntas válidas del juego
   * si tiene el 100% de aciertos
8. Crear una pantalla de administración para revisar las respuestas.
9. Permitir filtrar los usuarios que tienen el 100% de aciertos.
10. Ordenar las respuestas por hora de envío para determinar los ganadores.

---

# Regla crítica sobre preguntas nulas

El cálculo del 100% debe ignorar las preguntas marcadas como nulas.

Una pregunta se considera nula si en `wp_postmeta` tiene:

```php
meta_key = '_ejb_pregunta_nula'
meta_value = 'si'
```

Por tanto, el total de preguntas válidas debe calcularse contando solo las preguntas publicadas de tipo `pregunta` que **no** tengan `_ejb_pregunta_nula = si`.

Ejemplo:

* Total preguntas publicadas: 51
* Preguntas nulas: 2
* Total preguntas válidas: 49

Si un usuario tiene `puntos_totales = 49`, entonces tiene el 100%.

No se debe comparar contra el total bruto de preguntas publicadas si hay preguntas nulas.

---

# Estructura recomendada

## 1. Crear una tabla personalizada para las respuestas del desempate

Crear una tabla nueva en la activación o actualización del plugin:

```sql
CREATE TABLE {$wpdb->prefix}joc_desempat_respostes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  resposta_1 TEXT NULL,
  resposta_2 TEXT NULL,
  resposta_3 TEXT NULL,
  punts_totals INT NOT NULL DEFAULT 0,
  total_preguntes_valides INT NOT NULL DEFAULT 0,
  te_100_percent TINYINT(1) NOT NULL DEFAULT 0,
  estat_revisio VARCHAR(20) NOT NULL DEFAULT 'pendent',
  observacions TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY user_id (user_id),
  KEY te_100_percent (te_100_percent),
  KEY created_at (created_at),
  KEY estat_revisio (estat_revisio)
) {$charset_collate};
```

Notas:

* `UNIQUE KEY user_id` evita que un usuario responda más de una vez.
* `estat_revisio` servirá para marcar manualmente respuestas como:

  * `pendent`
  * `correcte`
  * `incorrecte`
* No se validarán automáticamente las respuestas del desempate porque son campos de texto libre.

---

# 2. Crear opciones de configuración

Guardar la configuración del desempate en `wp_options`.

Opciones recomendadas:

```php
joc_desempat_actiu
joc_desempat_data_publicacio
joc_desempat_pregunta_1
joc_desempat_pregunta_2
joc_desempat_pregunta_3
joc_desempat_text_intro
joc_desempat_text_gracies
```

Ejemplo de uso:

```php
$actiu = get_option('joc_desempat_actiu');
$data_publicacio = get_option('joc_desempat_data_publicacio');
$pregunta_1 = get_option('joc_desempat_pregunta_1');
$pregunta_2 = get_option('joc_desempat_pregunta_2');
$pregunta_3 = get_option('joc_desempat_pregunta_3');
```

---

# 3. Crear pantalla de administración: Joc > Desempat

Añadir una página en el admin de WordPress para configurar el desempate.

Campos necesarios:

* Activar/desactivar desempate.
* Fecha y hora de publicación.
* Pregunta 1.
* Pregunta 2.
* Pregunta 3.
* Texto introductorio.
* Texto de confirmación después de enviar.

La pantalla debe guardar los datos con `update_option()`.

Debe usar:

```php
current_user_can('manage_options')
check_admin_referer()
sanitize_text_field()
sanitize_textarea_field()
```

---

# 4. Crear shortcode para mostrar el formulario

Crear un shortcode:

```php
[joc_desempat]
```

El shortcode debe hacer lo siguiente:

1. Comprobar si el desempate está activo.
2. Comprobar si ya ha llegado la fecha/hora de publicación.
3. Comprobar si el usuario está logueado.
4. Comprobar si el usuario ya ha enviado respuesta.
5. Si todo es correcto, mostrar las 3 preguntas y el formulario.

Ejemplo de lógica:

```php
function joc_desempat_shortcode() {
    if (get_option('joc_desempat_actiu') !== '1') {
        return '';
    }

    $data_publicacio = get_option('joc_desempat_data_publicacio');

    if (current_time('timestamp') < strtotime($data_publicacio)) {
        return '<p>La pregunta de desempat encara no està disponible.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>Has d’iniciar sessió per respondre el desempat.</p>';
    }

    $user_id = get_current_user_id();

    if (joc_desempat_user_ja_ha_respost($user_id)) {
        return '<p>Ja has enviat les teves respostes de desempat.</p>';
    }

    ob_start();

    // Renderizar formulario aquí.

    return ob_get_clean();
}
add_shortcode('joc_desempat', 'joc_desempat_shortcode');
```

---

# 5. Función para saber si el usuario ya respondió

```php
function joc_desempat_user_ja_ha_respost($user_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'joc_desempat_respostes';

    return (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
            $user_id
        )
    );
}
```

---

# 6. Calcular total de preguntas válidas

Esta función es clave.

Debe contar todas las preguntas publicadas de tipo `pregunta`, excluyendo las que tengan `_ejb_pregunta_nula = si`.

Ejemplo recomendado:

```php
function joc_get_total_preguntes_valides() {
    $query = new WP_Query([
        'post_type'      => 'pregunta',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => '_ejb_pregunta_nula',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_ejb_pregunta_nula',
                'value'   => 'si',
                'compare' => '!=',
            ],
        ],
    ]);

    return (int) count($query->posts);
}
```

Importante:

* No contar preguntas nulas.
* No usar un número fijo como 51.
* El total debe ser dinámico.
* Si el juego solo debe contar preguntas de una fecha concreta o campaña concreta, añadir también esa condición en la query.

---

# 7. Calcular si un usuario tiene el 100%

Usar `puntos_totales` de `wp_usermeta`.

```php
function joc_user_te_100_percent($user_id) {
    $punts_totals = (int) get_user_meta($user_id, 'puntos_totales', true);
    $total_valides = joc_get_total_preguntes_valides();

    if ($total_valides <= 0) {
        return false;
    }

    return $punts_totals >= $total_valides;
}
```

También crear una función que devuelva todos los datos para guardar la foto del usuario:

```php
function joc_get_estat_punts_usuari($user_id) {
    $punts_totals = (int) get_user_meta($user_id, 'puntos_totales', true);
    $total_valides = joc_get_total_preguntes_valides();

    return [
        'punts_totals'             => $punts_totals,
        'total_preguntes_valides'  => $total_valides,
        'te_100_percent'           => ($total_valides > 0 && $punts_totals >= $total_valides) ? 1 : 0,
    ];
}
```

---

# 8. Guardar las respuestas del desempate

Al enviar el formulario:

1. Validar nonce.
2. Comprobar usuario logueado.
3. Comprobar que el usuario no haya respondido antes.
4. Sanitizar las 3 respuestas.
5. Obtener `puntos_totales`.
6. Calcular total de preguntas válidas excluyendo nulas.
7. Calcular `te_100_percent`.
8. Insertar en la tabla.

Ejemplo:

```php
function joc_desempat_processar_formulari() {
    if (!isset($_POST['joc_desempat_nonce']) || !wp_verify_nonce($_POST['joc_desempat_nonce'], 'joc_desempat_enviar')) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    if (joc_desempat_user_ja_ha_respost($user_id)) {
        return;
    }

    $resposta_1 = isset($_POST['resposta_1']) ? sanitize_textarea_field($_POST['resposta_1']) : '';
    $resposta_2 = isset($_POST['resposta_2']) ? sanitize_textarea_field($_POST['resposta_2']) : '';
    $resposta_3 = isset($_POST['resposta_3']) ? sanitize_textarea_field($_POST['resposta_3']) : '';

    $estat = joc_get_estat_punts_usuari($user_id);

    global $wpdb;

    $table = $wpdb->prefix . 'joc_desempat_respostes';

    $wpdb->insert(
        $table,
        [
            'user_id'                 => $user_id,
            'resposta_1'              => $resposta_1,
            'resposta_2'              => $resposta_2,
            'resposta_3'              => $resposta_3,
            'punts_totals'            => $estat['punts_totals'],
            'total_preguntes_valides' => $estat['total_preguntes_valides'],
            'te_100_percent'          => $estat['te_100_percent'],
            'estat_revisio'           => 'pendent',
            'created_at'              => current_time('mysql'),
        ],
        [
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
        ]
    );
}
add_action('init', 'joc_desempat_processar_formulari');
```

---

# 9. Formulario frontend

El formulario debe tener:

* Pregunta 1 + textarea.
* Pregunta 2 + textarea.
* Pregunta 3 + textarea.
* Nonce.
* Botón de enviar.

Ejemplo:

```php
<form method="post">
    <?php wp_nonce_field('joc_desempat_enviar', 'joc_desempat_nonce'); ?>

    <p><?php echo esc_html(get_option('joc_desempat_pregunta_1')); ?></p>
    <textarea name="resposta_1" required></textarea>

    <p><?php echo esc_html(get_option('joc_desempat_pregunta_2')); ?></p>
    <textarea name="resposta_2" required></textarea>

    <p><?php echo esc_html(get_option('joc_desempat_pregunta_3')); ?></p>
    <textarea name="resposta_3" required></textarea>

    <button type="submit">Enviar respostes</button>
</form>
```

---

# 10. Pantalla de administración para revisar respuestas

Crear una pantalla:

```text
Joc > Respostes desempat
```

Debe mostrar una tabla con:

* Usuario.
* Email.
* Respuesta 1.
* Respuesta 2.
* Respuesta 3.
* Puntos totales.
* Total preguntas válidas.
* ¿Tiene 100%?
* Estado de revisión.
* Fecha/hora de envío.
* Acciones.

Filtros:

* Todos.
* Solo usuarios con 100%.
* Pendientes.
* Correctas.
* Incorrectas.

Orden:

```sql
ORDER BY created_at ASC
```

Filtro principal para candidatos a premios buenos:

```sql
WHERE te_100_percent = 1
ORDER BY created_at ASC
```

---

# 11. Revisión manual de respuestas abiertas

Como las respuestas son de texto libre, el sistema no debe intentar validarlas automáticamente.

La organización revisará manualmente y podrá marcar cada respuesta como:

```text
pendent
correcte
incorrecte
```

Añadir acción en admin para actualizar `estat_revisio`.

Ejemplo:

```php
$wpdb->update(
    $table,
    [
        'estat_revisio' => 'correcte',
        'updated_at'    => current_time('mysql'),
    ],
    [
        'id' => $resposta_id,
    ],
    [
        '%s',
        '%s',
    ],
    [
        '%d',
    ]
);
```

---

# 12. Listado final de ganadores principales

Los 10 premios buenos principales saldrán de:

```sql
SELECT *
FROM wp_joc_desempat_respostes
WHERE te_100_percent = 1
AND estat_revisio = 'correcte'
ORDER BY created_at ASC
LIMIT 10;
```

Es decir:

1. Usuarios con 100% de aciertos en el juego normal.
2. Que hayan respondido el desempate.
3. Que la organización haya marcado como correctas sus respuestas.
4. Ordenados por hora de envío.
5. Los 10 primeros.

---

# 13. Consideraciones importantes

## No validar automáticamente el desempate

Las respuestas son de texto libre, por tanto la validación automática puede generar errores. Solo se guardan y se revisan manualmente.

## Guardar foto del usuario en el momento del envío

Aunque `puntos_totales` pueda cambiar después, se debe guardar en la tabla del desempate el estado del usuario al enviar.

Esto evita problemas si luego se modifica una pregunta, se anula otra o se recalculan puntos.

## Preguntas nulas

Las preguntas con `_ejb_pregunta_nula = si` no deben contar en el total de preguntas válidas.

Esto afecta directamente a:

```php
total_preguntes_valides
te_100_percent
```

## Un solo envío por usuario

Cada usuario debe poder enviar el desempate una sola vez.

Se recomienda usar `UNIQUE KEY user_id`.

## Seguridad

Implementar:

* `wp_nonce_field()`
* `wp_verify_nonce()`
* `sanitize_textarea_field()`
* `esc_html()`
* `current_user_can()` en admin
* `$wpdb->prepare()` en consultas
* Validación de usuario logueado

---

# 14. Resumen de implementación

Implementar en el plugin:

1. Crear tabla `wp_joc_desempat_respostes`.
2. Crear opciones en `wp_options` para configurar el desempate.
3. Crear pantalla admin `Joc > Desempat`.
4. Crear shortcode `[joc_desempat]`.
5. Mostrar formulario solo si:

   * desempate activo
   * fecha/hora alcanzada
   * usuario logueado
   * usuario no ha respondido antes
6. Al enviar:

   * guardar respuestas
   * guardar hora
   * guardar puntos actuales
   * calcular total de preguntas válidas excluyendo nulas
   * calcular si tiene 100%
7. Crear pantalla admin `Joc > Respostes desempat`.
8. Permitir filtrar por usuarios con 100%.
9. Permitir marcar manualmente como correcto/incorrecto.
10. Obtener ganadores filtrando por `te_100_percent = 1`, `estat_revisio = correcte` y ordenando por `created_at ASC`.

---

# Resultado esperado

Al finalizar, el WordPress debe permitir gestionar el desempate completo desde el plugin:

* La organización escribe las 3 preguntas.
* El sistema las publica automáticamente.
* Los usuarios responden desde la web.
* WordPress guarda las respuestas y la hora exacta.
* El sistema calcula si el usuario tenía el 100% de aciertos, ignorando preguntas nulas.
* La organización revisa manualmente las respuestas abiertas.
* El sistema permite obtener los primeros ganadores ordenados por tiempo.
