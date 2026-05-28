<?php
// Evita acceso directo al archivo
if (!defined('ABSPATH')) exit;

// Shortcodes
add_shortcode('preguntas_diarias', 'ejb_mostrar_preguntas_diarias');
add_shortcode('respuestas_anteriores', 'ejb_mostrar_respuestas_anteriores');
add_shortcode('detalle_pregunta', 'ejb_mostrar_detalle_pregunta');
add_shortcode('clasificacion', 'ejb_mostrar_clasificacion');
add_shortcode('totes_les_respostes', 'ejb_mostrar_totes_les_respostes');
add_shortcode('totes_les_respostes_2025', 'ejb_mostrar_totes_les_respostes_2025');


// Hooks
//add_action('init', 'ejb_procesar_respuestas_diarias');

// Inicio de las funciones personalizadas

/**
 * Mostra les preguntes diàries disponibles per respondre
 * - Obté les preguntes publicades avui
 * - Comprova si l'usuari ja ha respost
 * - Mostra el formulari per respondre si encara no ho ha fet
 * - Té en compte la data límit per respondre segons el dia de la setmana
 */
function ejb_mostrar_preguntas_diarias()
{
    if ( ! is_user_logged_in() ) {
        return '<div class="pregunta-card"><p>Has de <a href="' . wp_login_url( get_permalink() ) . '">iniciar sessió</a> per poder participar en el joc.</p></div>';
    }
    $timezone = wp_timezone();
    $ahora = new DateTime('now', $timezone);
    $dia_semana = (int) $ahora->format('w'); // 0 = diumenge, 6 = dissabte

    // Determinar la data de les preguntes a mostrar
    if ($dia_semana === 0) {
        // Diumenge: mostrar les preguntes del dissabte
        $fecha_objetivo = new DateTime('saturday this week', $timezone);
    } elseif ($dia_semana >= 1 && $dia_semana <= 6) {
        // Dilluns a dissabte: mostrar les preguntes del dia
        $fecha_objetivo = new DateTime('today', $timezone);
    } else {
        return '<div class="pregunta-card"><span>No hi ha preguntes disponibles avui.</span></div>';
    }

    $fecha_inicio = $fecha_objetivo->format('Y-m-d 00:00:00');
    $fecha_fin    = $fecha_objetivo->format('Y-m-d 23:59:59');

    $preguntas = get_posts(array(
        'post_type' => 'pregunta',
        'posts_per_page' => get_option('ejb_num_preguntas_diarias', 3),
        'date_query' => array(
            array(
                'after'     => $fecha_inicio,
                'before'    => $fecha_fin,
                'inclusive' => true,
            )
        ),
        'meta_query' => array(
            array(
                'key'     => '_ejb_pregunta_nula',
                'compare' => 'NOT EXISTS'
            )
        ),
        'orderby' => 'date',
        'order'   => 'ASC'
    ));

    ob_start();

    if ($preguntas) {
        $user_id = get_current_user_id();
        $respuestas_previas = get_user_meta($user_id, 'respuestas_preguntas', true);
        if (!is_array($respuestas_previas)) {
            $respuestas_previas = [];
        }

        foreach ($preguntas as $pregunta) {
            $pregunta_id = $pregunta->ID;
            $fecha_publicacion = get_the_date('Y-m-d', $pregunta_id);
            $fecha_limite_str = ejb_calcular_fecha_limite($fecha_publicacion);

            $ahora_obj = new DateTime('now', $timezone);
            $limite_obj = new DateTime($fecha_limite_str, $timezone);

            if ($ahora_obj > $limite_obj) {
                echo '<p class="hora-pasada">L\'hora límit per a respondre a aquesta pregunta ha passat.</p>';
                continue;
            }

            if (isset($respuestas_previas[$pregunta_id])) {
                echo '<div class="pregunta-card ya-respondida">';
                echo '<h2>' . esc_html($pregunta->post_title) . '</h2>';
                echo '<p class="mensaje-ya-respondida">Ja has respost aquesta pregunta.</p>';
                echo '</div>';
                continue;
            }

            $opciones = array(
                get_field('opcio_1', $pregunta_id),
                get_field('opcio_2', $pregunta_id),
                get_field('opcio_3', $pregunta_id),
            );

            echo '<div class="pregunta-card">';
            echo '<h2>' . esc_html($pregunta->post_title) . '</h2>';

            echo '<form method="POST" class="pregunta-form">';
            echo '<div class="opciones-grid">';
            foreach ($opciones as $index => $opcion) {
                echo '<input type="radio" id="opcion_' . $pregunta_id . '_' . $index . '" name="respuesta_' . $pregunta_id . '" value="' . ($index + 1) . '">';
                echo '<label for="opcion_' . $pregunta_id . '_' . $index . '">' . esc_html($opcion) . '</label>';
            }
            echo '</div>';
            echo '<input type="hidden" name="pregunta_id_' . $pregunta_id . '" value="' . esc_attr($pregunta_id) . '">';
            echo '<button type="submit" name="enviar_respuesta_' . $pregunta_id . '">Enviar resposta</button>';
            echo '</form>';
            echo '</div>';
        }

        return ob_get_clean();
    } else {
        return '<div class="pregunta-card"><span>No hi ha preguntes disponibles avui o ja ha passat l\'hora límit.</span></div>';
    }
}




/**
 * Calcula la data límit per respondre una pregunta
 * - De dilluns a divendres: fins les 23:59 del mateix dia
 * - Dissabte: fins les 23:59 del diumenge
 * - Diumenge: no hi ha preguntes noves
 */
function ejb_calcular_fecha_limite($fecha_publicacion)
{
    $timezone = wp_timezone();
    $fecha = new DateTime($fecha_publicacion, $timezone);
    $dia_semana = (int) $fecha->format('w');

    if ($dia_semana >= 1 && $dia_semana <= 5) {
        // Dilluns a divendres → límit aquell mateix dia a les 23:59
        $fecha->setTime(23, 59, 59);
    } elseif ($dia_semana === 6) {
        // Dissabte → límit el diumenge a les 23:59
        $fecha->modify('sunday this week')->setTime(23, 59, 59);
    } else {
        // Diumenge (no es publiquen noves, però per seguretat)
        $fecha->setTime(23, 59, 59);
    }

    return $fecha->format('Y-m-d H:i:s');
}



/**
 * Processa les respostes enviades pels usuaris
 * - Guarda les respostes com a pendents
 * - Incrementa el comptador de respostes
 * - Evita respostes duplicades
 */
function ejb_procesar_respuestas_diarias()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = get_current_user_id();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'pregunta_id_') === 0) {
                $pregunta_id = intval($value);

                if (isset($_POST['respuesta_' . $pregunta_id])) {
                    $respuesta_usuario = intval($_POST['respuesta_' . $pregunta_id]);
                    
                    // Desa la resposta de l'usuari (això ja gestiona detalls i comptadors)
                    ejb_registrar_respuesta( $user_id, $pregunta_id, $respuesta_usuario );
                }
            }
        }
    }
}

// /**
//  * Programa l'actualització del ranking
//  * - S'executa cada dia a mitjanit
//  */
// function ejb_programar_evento_cron()
// {
//     if (!wp_next_scheduled('ejb_actualizar_puntos_diarios')) {
//         // Programar para que se ejecute todos los días a medianoche
//         wp_schedule_event(strtotime('midnight'), 'daily', 'ejb_actualizar_puntos_diarios');
//     }
// }

// /**
//  * Elimina la programació del cron quan es desactiva el plugin
//  */
// function ejb_desprogramar_evento_cron()
// {
//     wp_clear_scheduled_hook('ejb_actualizar_puntos_diarios');
// }

/**
 * Processa totes les respostes pendents
 * - Comprova si és dissabte (no processa)
 * - Actualitza els punts dels usuaris
 * - Actualitza els comptadors de respostes correctes
 * - Elimina les respostes pendents ja processades
 */
function ejb_procesar_respuestas_pendientes() {
    
    ejb_recalcular_puntuacions();
    
//     foreach ( get_users() as $usuario ) {
//         $user_id = $usuario->ID;
        
        
//         /* -----------------------------------------------------------------
//          * 2) Array "respuestas_preguntas"
//          * -----------------------------------------------------------------*/
//         $array = get_user_meta( $user_id, 'respuestas_preguntas', true );
//         if ( ! is_array( $array ) ) { continue; }
        
//         $cambios = false;
//         foreach ( $array as $pid => &$datos ) {
//             if ( empty( $datos['estado'] ) || $datos['estado'] !== 'pendiente' ) { continue; }
            
//             ejb_puntuar_respuesta( $user_id, $pid, intval( $datos['respuesta'] ) );
            
//             $datos['estado'] = 'procesada';
//             $cambios         = true;
//         }
//         if ( $cambios ) {
//             update_user_meta( $user_id, 'respuestas_preguntas', $array );
//         }
//     }
}

/**
 * Suma punts i actualitza comptadors per a una resposta concreta
 */
function ejb_puntuar_respuesta( $user_id, $pregunta_id, $respuesta_usuario ) {
    
    $respuesta_correcta = intval( get_field( 'respuesta_correcta', $pregunta_id ) );
    
    // Incrementar total de respostes de la pregunta
    $total = (int) get_post_meta( $pregunta_id, 'total_respuestas', true ) + 1;
    update_post_meta( $pregunta_id, 'total_respuestas', $total );
    
    // Punts i respostes correctes
    if ( $respuesta_usuario === $respuesta_correcta ) {
        $puntos = (int) get_user_meta( $user_id, 'puntos_totales', true ) + 1;
        update_user_meta( $user_id, 'puntos_totales', $puntos );
        
        $correctas = (int) get_post_meta( $pregunta_id, 'respuestas_correctas', true ) + 1;
        update_post_meta( $pregunta_id, 'respuestas_correctas', $correctas );
    }
}

/**
 * Recalcula totes les puntuacions i estadístiques
 * – Reinicia comptadors de preguntes i usuaris
 * – Elimina qualsevol meta 'respuesta_pregunta_*'
 * – Torna a sumar punts a partir de l'array 'respuestas_preguntas'
 */
function ejb_recalcular_puntuacions() {
    
  
    /* ────────────────────────────────────────────────────────────
     * 1. Reiniciar comptadors de preguntes
     * ────────────────────────────────────────────────────────────*/
    $preguntas = get_posts( [
        'post_type'   => 'pregunta',
        'numberposts' => -1,
        'fields'      => 'ids',
    ] );
    foreach ( $preguntas as $pid ) {
        update_post_meta( $pid, 'total_respuestas',     0 );
        update_post_meta( $pid, 'respuestas_correctas', 0 );
    }
    
    /* ────────────────────────────────────────────────────────────
     * 2. Usuaris: neteja, reinici i re-suma
     * ────────────────────────────────────────────────────────────*/
    foreach ( get_users() as $usuari ) {
        
        
        
        $user_id = $usuari->ID;
        
        /* a) Eliminar metas legacy 'respuesta_pregunta_*' */
        foreach ( get_user_meta( $user_id ) as $meta_key => $value ) {
            if ( strpos( $meta_key, 'respuesta_pregunta_' ) === 0 ) {
                delete_user_meta( $user_id, $meta_key );
            }
        }
        
        /* b) Reiniciar punts */
        update_user_meta( $user_id, 'puntos_totales', 0 );
        
        /* c) Recalcular a partir del nou array */
        $respuestas = get_user_meta( $user_id, 'respuestas_preguntas', true );
        if ( ! is_array( $respuestas ) ) {
            continue;
        }
        
        $punts = 0;
        foreach ( $respuestas as $pid => $datos ) {
            
            // Format flexible: array o valor simple
            $respuesta_usuario = is_array( $datos )
            ? intval( $datos['respuesta'] ?? 0 )
            : intval( $datos );
            
            if ( ! $respuesta_usuario || get_post_type( $pid ) !== 'pregunta' ) {
                continue;
            }

            // Si la pregunta ha estat anul·lada per l'administrador, no es té en compte per punts ni estadístiques
            if ( get_post_meta( $pid, '_ejb_pregunta_nula', true ) === 'si' ) {
                continue;
            }
            // Ignorar només si és un array amb un estat explícitament buit (prevenció d'errors)
            // Això permet que les dades legacy (que no són arrays) siguin processades correctament.
            if ( is_array( $datos ) && isset( $datos['estado'] ) && $datos['estado'] === '' ) {
                continue;
            }
            
            $respuesta_correcta = intval( get_field( 'respuesta_correcta', $pid ) );
            
            /* ── Comptadors de la pregunta ───────────────────── */
            $total = (int) get_post_meta( $pid, 'total_respuestas', true ) + 1;
            update_post_meta( $pid, 'total_respuestas', $total );
            
            if ( $respuesta_usuario === $respuesta_correcta ) {
                $punts++;
                
                $correctes = (int) get_post_meta( $pid, 'respuestas_correctas', true ) + 1;
                update_post_meta( $pid, 'respuestas_correctas', $correctes );
                
            } else {
                // Comprovació de "Pregunta de Regal" dinàmica (camp ACF: ejb_regalo)
                $es_regalo = get_post_meta( $pid, 'ejb_regalo', true );
                
                if ( $es_regalo ) {
                    $punts++;
                    $correctes = (int) get_post_meta( $pid, 'respuestas_correctas', true ) + 1;
                    update_post_meta( $pid, 'respuestas_correctas', $correctes );
                }
            }
            
        }
        
        /* d) Desa punts finals de l'usuari */
        update_user_meta( $user_id, 'puntos_totales', $punts );
    }
}



/**
 * Mostra les respostes de les preguntes del dia anterior
 * - Mostra la pregunta, opcions i resposta correcta
 * - Calcula i mostra el percentatge d'encerts
 * - Els dissabtes no es mostren preguntes; les de divendres es mostren diumenge
 */
function ejb_mostrar_respuestas_anteriores()
{
    ob_start();
    echo '<div class="respuestas-container">';

    $timezone     = wp_timezone();
    $hoy          = new DateTime('now', $timezone);
    $dia_semana   = (int) $hoy->format('w'); // 0 = diumenge

    // Diumenge: avisar i acabar
    if ($dia_semana === 0) {
        echo '<div class="detalle-pregunta">';
        echo '<p class="no-preguntas">Les solucions es mostraran demà quan finalitzi el període de resposta.</p>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Definir data objectiu:
    // - Dilluns: mostrar dissabte anterior
    // - Dimarts–dissabte: mostrar "ahir"
    if ($dia_semana === 1) {
        $fecha_objetivo = new DateTime('last saturday', $timezone);
    } else {
        $fecha_objetivo = new DateTime('yesterday',    $timezone);
    }

    $preguntas = get_posts(array(
        'post_type'      => 'pregunta',
        'date_query'     => array(
            array(
                'year'  => (int) $fecha_objetivo->format('Y'),
                'month' => (int) $fecha_objetivo->format('m'),
                'day'   => (int) $fecha_objetivo->format('d'),
            )
        ),
        'posts_per_page' => 3
    ));

    if ($preguntas) {
        foreach ($preguntas as $pregunta) {
            $pregunta_id        = $pregunta->ID;
            $respuesta_correcta = get_field('respuesta_correcta', $pregunta_id);
            $opciones           = array(
                get_field('opcio_1', $pregunta_id),
                get_field('opcio_2', $pregunta_id),
                get_field('opcio_3', $pregunta_id),
            );

            $total_respuestas = (int) get_post_meta($pregunta_id, 'total_respuestas', true);
            $correctas        = (int) get_post_meta($pregunta_id, 'respuestas_correctas', true);
            $porcentaje       = $total_respuestas > 0
                               ? ($correctas / $total_respuestas) * 100
                               : 0;

            echo '<div class="detalle-pregunta">';
              echo '<div class="detalle-header">';
                echo '<h2>' . esc_html($pregunta->post_title) . '</h2>';
                echo '<div class="detalle-meta">';
                  echo '<span class="detalle-fecha"><i class="fas fa-calendar"></i> '
                     . esc_html(get_the_date('j \d\e F Y', $pregunta_id))
                     . '</span>';
                echo '</div>';
              echo '</div>';

              echo '<div class="detalle-estadisticas">';
                echo '<h3><i class="fas fa-chart-bar"></i> Estadístiques</h3>';
                echo '<p>Percentatge de respostes correctes: <strong>'
                   . number_format($porcentaje, 2) . '%</strong></p>';
                echo '<div class="barra-porcentaje">'
                   . '<div class="relleno" style="width: ' . esc_attr($porcentaje) . '%;"></div>'
                   . '</div>';
              echo '</div>';

              echo '<div class="boton-detalle-container">';
                echo '<a href="' . esc_url(get_permalink($pregunta_id))
                   . '" class="boton-detalle">'
                   . '<i class="fas fa-arrow-right"></i> Veure detall de la pregunta'
                   . '</a>';
              echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<div class="detalle-pregunta">';
        echo '<p class="no-preguntas">No hi ha preguntes per mostrar.</p>';
        echo '</div>';
    }

    echo '</div>';
    return ob_get_clean();
}

/**
 * Renderitza el llistat de preguntes publicades d'un any concret
 */
function ejb_mostrar_respostes_per_any($any)
{
    ob_start();
    echo '<div class="ejb-archivo-respostes">';

    $timezone     = wp_timezone();
    $hoy          = new DateTime('now', $timezone);
    $dia_semana   = (int) $hoy->format('w'); // 0 = diumenge

    // Determinar hasta qué fecha mostrar (preguntas cerradas)
    if ($dia_semana === 0) {
        // Domingo: las del sábado siguen activas, así que mostramos hasta el viernes
        $fecha_limite = new DateTime('last friday', $timezone);
    } else {
        // Otros días: mostramos hasta ayer
        $fecha_limite = new DateTime('yesterday', $timezone);
    }

    $preguntas = get_posts(array(
        'post_type'      => 'pregunta',
        'posts_per_page' => -1,
        'date_query'     => array(
            array(
                'year' => (int) $any,
            ),
            array(
                'before'    => $fecha_limite->format('Y-m-d 23:59:59'),
                'inclusive' => true,
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC', // Mostrar las más recientes primero
    ));

    if ($preguntas) {
        $preguntas_por_dia = [];

        // Agrupar per data de publicació
        foreach ($preguntas as $pregunta) {
            $fecha = get_the_date('Y-m-d', $pregunta);
            if (!isset($preguntas_por_dia[$fecha])) {
                $preguntas_por_dia[$fecha] = [];
            }
            $preguntas_por_dia[$fecha][] = $pregunta;
        }

        // Mostrar preguntes agrupades
        foreach ($preguntas_por_dia as $fecha => $lista_preguntas) {
            $fecha_formateada = date_i18n('l d \d\e F Y', strtotime($fecha));

            echo '<div class="archivo-dia-seccion">';
            echo '<h2 class="titulo-dia"><span data-lucide="calendar-days"></span> ' . esc_html($fecha_formateada) . '</h2>';
            
            echo '<div class="archivo-preguntas-grid">';

            foreach ($lista_preguntas as $pregunta) {
                $pregunta_id        = $pregunta->ID;
                $total_respuestas = (int) get_post_meta($pregunta_id, 'total_respuestas', true);
                $correctas        = (int) get_post_meta($pregunta_id, 'respuestas_correctas', true);
                $porcentaje       = $total_respuestas > 0
                                   ? ($correctas / $total_respuestas) * 100
                                   : 0;

                echo '<div class="archivo-pregunta-card">';
                  echo '<div class="archivo-card-header">';
                    echo '<h3>' . esc_html($pregunta->post_title) . '</h3>';
                  echo '</div>';

                  echo '<div class="archivo-card-stats">';
                    echo '<div class="archivo-stat-item">';
                      echo '<span class="stat-label">Percentatge d\'encerts</span>';
                      echo '<span class="stat-value">' . number_format($porcentaje, 2) . '%</span>';
                    echo '</div>';
                    echo '<div class="barra-porcentaje">'
                       . '<div class="relleno" style="width: ' . esc_attr($porcentaje) . '%;"></div>'
                       . '</div>';
                  echo '</div>';

                  echo '<div class="archivo-card-footer">';
                    echo '<a href="' . esc_url(get_permalink($pregunta_id)) . '" class="boton-detalle">';
                      echo '<span>Veure detall</span>';
                      echo '<span data-lucide="arrow-right"></span>';
                    echo '</a>';
                  echo '</div>';
                echo '</div>';
            }
            echo '</div>'; // grid
            echo '</div>'; // seccion
        }
    } else {
        echo '<div class="no-preguntas-archivo">';
        echo '<p><span data-lucide="info"></span> No hi ha preguntes per mostrar en aquest any.</p>';
        echo '</div>';
    }

    echo '</div>';
    return ob_get_clean();
}

/**
 * Mostra les preguntes de l'edició actual 2026
 */
function ejb_mostrar_totes_les_respostes()
{
    return ejb_mostrar_respostes_per_any(2026);
}

/**
 * Mostra les preguntes de l'edició 2025
 */
function ejb_mostrar_totes_les_respostes_2025()
{
    return ejb_mostrar_respostes_per_any(2025);
}



/**
 * Mostra el detall complet d'una pregunta
 * - Només permet veure preguntes d'abans d'avui
 * - Mostra totes les opcions amb la correcta destacada
 * - Mostra estadístiques de respostes
 */
function ejb_mostrar_detalle_pregunta()
{
    // Obtener el ID del post actual
    $pregunta_id = get_the_ID();

    // Verificar si es un post del tipo 'pregunta'
    if (get_post_type($pregunta_id) !== 'pregunta') {
        return '<p>No se puede mostrar la información de la pregunta.</p>';
    }

    // Obtenir data actual i data límit de la pregunta
    $timezone = wp_timezone();
    $avui_obj = new DateTime('now', $timezone);
    $fecha_publicacion = get_the_date('Y-m-d', $pregunta_id);
    
    $fecha_limite_str = ejb_calcular_fecha_limite($fecha_publicacion);
    $limite_obj = new DateTime($fecha_limite_str, $timezone);

    // Permetre veure només preguntes que ja han finalitzat
    if ($avui_obj <= $limite_obj) {
        return '<p class="pregunta-card">Aquesta pregunta encara està activa. El detall estarà disponible quan finalitzi el període de resposta.</p>';
    }

    // Obtener la información de la pregunta
    $titulo = get_the_title($pregunta_id);
    $fecha_publicacion = get_the_date('j \d\e F Y', $pregunta_id);
    $contenido = get_the_content($pregunta_id);
    $respuesta_correcta = get_field('respuesta_correcta', $pregunta_id);
    $opciones = array(
        get_field('opcio_1', $pregunta_id),
        get_field('opcio_2', $pregunta_id),
        get_field('opcio_3', $pregunta_id),
    );

    // Obtener el total de respuestas y respuestas correctas
    $total_respuestas = get_post_meta($pregunta_id, 'total_respuestas', true);
    $correctas = get_post_meta($pregunta_id, 'respuestas_correctas', true);

    // Calcular el porcentaje de respuestas correctas
    $porcentaje_correctas = 0;
    // Obtenir el total de respostes i respostes correctes assegurant-se que són numèriques
    $total_respuestas = (int) get_post_meta($pregunta_id, 'total_respuestas', true);
    $correctas = (int) get_post_meta($pregunta_id, 'respuestas_correctas', true);

    // Calcula el percentatge només si hi ha respostes
    if ($total_respuestas > 0) {
        $porcentaje_correctas = ($correctas / $total_respuestas) * 100;
    } else {
        $porcentaje_correctas = 0;
    }


    ob_start();

    echo '<div class="detalle-pregunta">';
    echo '<div class="detalle-header">';
    echo '<h2>' . esc_html($titulo) . '</h2>';
    echo '<div class="detalle-meta">';
    echo '<span class="detalle-fecha"><i class="fas fa-calendar"></i> ' . esc_html($fecha_publicacion) . '</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="detalle-contenido">';
    echo wpautop(wp_kses_post($contenido));
    echo '</div>';

    echo '<div class="detalle-opciones">';
    echo '<h3><i class="fas fa-list-ul"></i> Opcions de resposta</h3>';
    echo '<ul>';
    foreach ($opciones as $index => $opcion) {
        $clase_correcta = ($index + 1 == $respuesta_correcta) ? 'class="correcta"' : '';
        echo '<li ' . $clase_correcta . '>';
        echo '<span class="opcion-indice">' . ($index + 1) . '</span>';
        echo '<span class="opcion-texto">' . esc_html($opcion) . '</span>';
        if ($index + 1 == $respuesta_correcta) {
            echo '<span class="opcion-correcta"><i class="fas fa-check"></i></span>';
        }
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';

    echo '<div class="detalle-estadisticas">';
    echo '<div class="estadistica-item">';
    echo '<h3><i class="fas fa-chart-bar"></i> Estadístiques</h3>';
    echo '<p>Percentatge de respostes correctes: <strong>' . number_format($porcentaje_correctas, 2) . '%</strong></p>';
    echo '<div class="barra-porcentaje"><div class="relleno" style="width: ' . esc_attr($porcentaje_correctas) . '%;"></div></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

/**
 * Mostra la classificació dels usuaris
 * - Carrega la classificació de manera asíncrona
 * - Destaca la posició de l'usuari actual
 * - Mostra els punts de cada usuari
 */
/**
 * Obté el llistat d'usuaris ordenat per punts (i nickname com desempat)
 */
function ejb_get_ranking_users($number = 100) {
    return get_users( array(
        'number' => $number,
        'meta_query' => array(
            'relation' => 'AND',
            'puntos' => array(
                'key'   => 'puntos_totales',
                'type'  => 'NUMERIC',
            ),
            'nick' => array(
                'key'   => 'nickname',
            ),
        ),
        'orderby' => array(
            'puntos' => 'DESC',
            'nick'   => 'ASC',
        ),
    ) );
}

function ejb_mostrar_clasificacion()
{
    ob_start();

    // Obtenir l'ID de l'usuari actual
    $user_id = get_current_user_id();

    // Obtenir el rànquing utilitzant la funció unificada
    $usuarios = ejb_get_ranking_users(100);
    
//     $usuarios = get_users(array(
//         'meta_key' => 'puntos_totales',
//         'orderby' => array('meta_value_num' => 'DESC', 'user_nicename' => 'ASC'),
//         'number' => 100
//     ));
//     $usuarios = get_users(array(
//         'meta_key' => 'puntos_totales',
//         'orderby' => 'meta_value_num',
//         'order' => 'DESC',
//         'number' => 100 // Mostrar los 10 primeros
//     ));

    // Ordenar manualmente los empates por nombre de usuario (alfabético)
//     usort($usuarios, function($a, $b) {
//         $puntosA = (int) get_user_meta($a->ID, 'puntos_totales', true);
//         $puntosB = (int) get_user_meta($b->ID, 'puntos_totales', true);

//         if ($puntosA === $puntosB) {
//             return strcasecmp($a->user_login, $b->user_login); // orden alfabético
//         }
//         return $puntosB - $puntosA; // puntos descendente
//     });

    $posicion_usuario = null; // Variable per guardar la posició de l'usuari
	$usuario_actual = wp_get_current_user(); // Usuari actual


    echo '<div class="clasificacion-shell">';
    echo '<div class="clasificacion-header">';
    echo '<p class="clasificacion-subtitle">Consulta el rànquing actual dels participants amb més puntuació.</p>';
    echo '</div>';
    echo '<div class="clasificacion">';

    if ($usuarios) {
        echo '<ol class="ranking-list">';
        foreach ($usuarios as $index => $usuario) {
            $puntos = get_user_meta($usuario->ID, 'puntos_totales', true);
            $puntos = $puntos ? $puntos : 0;
            $es_top_3 = $index < 3;

            $clase_usuario = ($usuario->ID === $user_id) ? 'class="usuario-actual"' : '';
            echo '<li ' . $clase_usuario . '>';
            echo '<div class="num-rank' . ($es_top_3 ? ' top-rank' : '') . '">' . esc_html($index + 1) . '</div>';
            echo '<div class="name-rank">';
            echo '<span class="nombre-usuario">' . esc_html($usuario->user_login) . '</span>';
            echo '<span class="puntos">' . esc_html($puntos) . ' punts</span>';
            echo '</div>';
            echo '</li>';

            // Si l'usuari actual és el que estem mostrant, guardar la seva posició
            if ($usuario->ID === $user_id) {
                $posicion_usuario = $index + 1;
            }
        }
        echo '</ol>';
    } else {
        echo '<p>❌ No hi ha usuaris a la classificació.</p>';
    }

    

    echo '</div>'; // Cerrar el contenedor


    // Obtener todos los IDs ordenados por puntos
    $ids_rank = get_users([
        'fields'      => 'ID',
        'meta_key'    => 'puntos_totales',
        'orderby'     => 'meta_value_num',
        'order'       => 'DESC',
    ]);

    // Calcular la posició de l'usuari actual
    $posicio = array_search($user_id, $ids_rank);
    $posicio = ($posicio === false) ? null : $posicio + 1;

    // Obtenir punts i posició guardada de l'usuari actual
    $punts = (int) get_user_meta( $user_id, 'puntos_totales', true );
    

    // Mostrar la posició de l'usuari actual
    echo '<div class="posicion-usuario">';
    echo '<p class="current-rank-label"><span data-lucide="sparkles"></span><span>La teva posició</span></p>';
    echo '<div class="intermediate">';
    echo '<p class="ranking-user"><strong>' . esc_html($posicio) . '</strong></p>';
    echo '<p class="nom-user"><span class="current-user-name">' . esc_html($usuario_actual->user_login) . '</span><span class="current-user-points">' . esc_html($punts) . ' punts</span></p>';
    echo '</div>';
    echo '</div>';

	// Mostrar la posició de l'usuari actual
    /*if ($posicion_usuario !== null) {
        $puntos_usuario = get_user_meta($user_id, 'puntos_totales', true);
        $puntos_usuario = $puntos_usuario ? $puntos_usuario : 0;
        echo '<div class="posicion-usuario"><div class="intermediate"><p class="ranking-user"><strong>' . ($posicion_usuario <= 3 ? '🏅 ' : '') . esc_html($posicion_usuario) . '</strong></p><p class="nom-user"> '.esc_html($usuario_actual->user_login).' - ' . esc_html($puntos_usuario) . ' punts</p></div></div>';
    } else {
        echo '<p class="posicion-usuario">⚠ No hi ets al ranking.</p>';
    }*/
    return ob_get_clean();
}

// Función AJAX para cargar la clasificación
add_action('wp_ajax_ejb_cargar_clasificacion', 'ejb_cargar_clasificacion_ajax');
add_action('wp_ajax_nopriv_ejb_cargar_clasificacion', 'ejb_cargar_clasificacion_ajax');

/**
 * Processa la petició AJAX per carregar la classificació
 * - Verifica la seguretat amb nonce
 * - Obté i ordena els usuaris per punts
 * - Retorna el HTML amb la classificació
 */
function ejb_cargar_clasificacion_ajax() {
    // Verificar nonce
    check_ajax_referer('ejb_clasificacion_nonce', 'nonce');
    
    ob_start();
    
    // Obtener el ID del usuario actual
    $user_id = get_current_user_id();
    
    // Obtener el ranking utilizando la función unificada
    $usuarios = ejb_get_ranking_users(100);
    
    $posicion_usuario = null; // Variable para guardar la posición del usuario
    
    if ($usuarios) {
        echo '<ol class="ranking-list">';
        foreach ($usuarios as $index => $usuario) {
            $puntos = get_user_meta($usuario->ID, 'puntos_totales', true);
            $puntos = $puntos ? $puntos : 0;
            
            $clase_usuario = ($usuario->ID === $user_id) ? 'class="usuario-actual"' : '';
            echo '<li ' . $clase_usuario . '>';
            echo '<span>🏅 ' . esc_html($index + 1) . '. ' . esc_html($usuario->user_login) . '</span>';
            echo '<span>' . esc_html($puntos) . ' punts</span>';
            echo '</li>';
            
            // Si el usuario actual es el que estamos mostrando, guardar su posición
            if ($usuario->ID === $user_id) {
                $posicion_usuario = $index + 1;
            }
        }
        echo '</ol>';
    } else {
        echo '<p>❌ No hi ha usuaris a la classificació.</p>';
    }
    
    // Mostrar la posición del usuario actual
    if ($posicion_usuario !== null) {
        echo '<p class="posicion-usuario">📍 La teva posició actual: <strong>' . esc_html($posicion_usuario) . '</strong></p>';
    } else {
        echo '<p class="posicion-usuario">⚠ No hi ets al ranking.</p>';
    }
    
    $html = ob_get_clean();
    
    wp_send_json_success($html);
}


/**
 * Guarda una respuesta del usuario en todos los formatos necesarios
 */
function ejb_registrar_respuesta( $user_id, $pregunta_id, $respuesta_usuario ) {
    
    // 1. Array global de respuestas (para el front-end y el proceso del ranking)
    $respuestas_previas = get_user_meta( $user_id, 'respuestas_preguntas', true );
    if ( ! is_array( $respuestas_previas ) ) {
        $respuestas_previas = [];
    }
    
    $respuestas_previas[ $pregunta_id ] = [
        'respuesta' => $respuesta_usuario,
        'fecha'     => current_time( 'mysql' ),
        'estado'    => 'pendiente',             // clave para el cron
    ];
    update_user_meta( $user_id, 'respuestas_preguntas', $respuestas_previas );

    // 2. Guardar registro detallado de la respuesta (para historial del usuario)
    $opcion_seleccionada = get_field('opcio_' . $respuesta_usuario, $pregunta_id);
    $respuesta_correcta = get_field('respuesta_correcta', $pregunta_id);
    $es_correcta = (intval($respuesta_usuario) === intval($respuesta_correcta)) ? 1 : 0;
    $es_regalo = get_post_meta($pregunta_id, 'ejb_regalo', true);
    $punto_valido = ($es_correcta || $es_regalo) ? 1 : 0;

    $detalle_respuesta = array(
        'pregunta_id' => $pregunta_id,
        'opcion_seleccionada' => $opcion_seleccionada,
        'es_correcta' => $es_correcta, // Guardamos si la respuesta técnica fue correcta
        'es_regalo' => $es_regalo ? 1 : 0,
        'fecha_respuesta' => current_time('mysql'),
        'puntos_obtenidos' => $punto_valido
    );

    $respuestas_anteriores = get_user_meta($user_id, 'respuestas_detalladas', true);
    if (!is_array($respuestas_anteriores)) {
        $respuestas_anteriores = array();
    }
    $respuestas_anteriores[] = $detalle_respuesta;
    update_user_meta($user_id, 'respuestas_detalladas', $respuestas_anteriores);

    // 3. Incrementar el contador total de respuestas del post (estadísticas)
    $total_respuestas = (int) get_post_meta($pregunta_id, 'total_respuestas', true);
    update_post_meta($pregunta_id, 'total_respuestas', $total_respuestas + 1);

    // Si es correcta o es una pregunta de regalo, incrementamos el contador de correctas
    $es_regalo = get_post_meta($pregunta_id, 'ejb_regalo', true);
    if ($es_correcta || $es_regalo) {
        $respuestas_correctas = (int) get_post_meta($pregunta_id, 'respuestas_correctas', true);
        update_post_meta($pregunta_id, 'respuestas_correctas', $respuestas_correctas + 1);
    }

    return true;
}


/**
 * Processa la petició AJAX per guardar una respuesta
 * - Verifica la seguretat amb nonce
 * - Comprova si l'usuari ja ha respost
 * - Guarda la resposta com a pendent
 */
function ejb_guardar_respuesta_ajax() {
    // Verificar nonce
    if (!check_ajax_referer('ejb-ajax-nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad. Por favor, recarga la página e intenta de nuevo.');
        return;
    }

    // Verificar si el usuario está logueado
    if (!is_user_logged_in()) {
        wp_send_json_error('Debes iniciar sesión para responder.');
        return;
    }

    $user_id = get_current_user_id();
    $pregunta_id = isset($_POST['pregunta_id']) ? intval($_POST['pregunta_id']) : 0;
    $respuesta = isset($_POST['respuesta']) ? sanitize_text_field($_POST['respuesta']) : '';

   error_log('User: '.$user_id.' - Pregunta: '.$pregunta_id.' - Respuesta: '.$respuesta);
    
    if (!$pregunta_id || !$respuesta) {
        wp_send_json_error('Datos incompletos. Por favor, intenta de nuevo.');
        return;
    }

    // Verificar si el usuario ya ha respondido esta pregunta
    $respuestas_previas = get_user_meta($user_id, 'respuestas_preguntas', true);
    if (!is_array($respuestas_previas)) {
        $respuestas_previas = [];
    }

    if (isset($respuestas_previas[$pregunta_id])) {
        wp_send_json_error('Ya has respondido a esta pregunta.');
        return;
    }

    // ─── NUEVO: registrar la respuesta en todos los formatos ─────────
    ejb_registrar_respuesta( $user_id, $pregunta_id, $respuesta );
    
    // ─── Respuesta AJAX ───────────────────────────────────────────────
    wp_send_json_success( 'Resposta desada correctament' );
}

add_shortcode('ejb_perfil_usuari', 'ejb_mostrar_perfil_usuari');

/**
 * Mostra el perfil complet de l’usuari actual
 * – Inclou punts, posició al rànquing, % d’encerts
 * – Agafa les preguntes respostes tant del sistema “antic”
 *    (meta `respuesta_pregunta_…`) com del nou array
 *    `respuestas_preguntas`
 *
 * @return string HTML del perfil o missatge d’error
 */
function ejb_mostrar_perfil_usuari() {
    
    if ( ! is_user_logged_in() ) {
        return '<p>Has d\'estar loguejat per veure el teu perfil.</p>';
    }
    
    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *  Dades bàsiques de l’usuari
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    $user_id    = get_current_user_id();
    $user       = wp_get_current_user();
    
    $punts      = (int) get_user_meta( $user_id, 'puntos_totales', true );
    
    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *  Posició al rànquing
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    $ids_rank = get_users( [
        'fields'      => 'ID',
        'meta_key'    => 'puntos_totales',
        'orderby'     => 'meta_value_num',
        'order'       => 'DESC',
    ] );
    
    $posicio = array_search( $user_id, $ids_rank );
    $posicio = ( $posicio === false ) ? null : $posicio + 1;
    
    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *  Preguntes respostes (sumar els dos sistemes)
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    $preguntes_respostes = [];
    
//     // 1) Metes individuals “legacy”
//     foreach ( get_user_meta( $user_id ) as $meta_key => $value ) {
//         if ( strpos( $meta_key, 'respuesta_pregunta_' ) === 0 ) {
//             $preguntes_respostes[] = intval( str_replace( 'respuesta_pregunta_', '', $meta_key ) );
//         }
//     }
    
    // 2) Nou array “respuestas_preguntas”
    $array = get_user_meta( $user_id, 'respuestas_preguntas', true );
    if ( is_array( $array ) ) {
        $preguntes_respostes = array_merge( $preguntes_respostes, array_keys( $array ) );
    }
    
    $preguntes_respostes = array_unique( $preguntes_respostes );
    
    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *  Percentatge d’encerts
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    $total_respostes   = count( $preguntes_respostes );
    $percent_encerts   = $total_respostes ? ( $punts / $total_respostes ) * 100 : 0;
    
    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *  HTML de sortida
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    ob_start(); ?>

	    <div class="ejb-perfil-usuari">
	        <!-- Capçalera -->
	        <div class="perfil-header">
	            <h2>
	                <span>Hola, <?php echo esc_html( $user->user_login ); ?></span>
	            </h2>
	            <p class="email-usuario">
	                <span class="perfil-meta-icon" data-lucide="mail"></span>
	                <span><?php echo esc_html( $user->user_email ); ?></span>
	            </p>

	            <div class="perfil-acciones">
	                <a class="boton-cambiar-contrasena" href="/recuperar-contrasenya/">
	                    <span data-lucide="key"></span>
	                    <span>Canviar contrasenya</span>
	                </a>
	                <a class="btn-logout" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
	                    <span data-lucide="log-out"></span>
	                    <span>Tancar sessió</span>
	                </a>
	            </div>
	        </div>

	        <!-- Stats -->
	        <div class="perfil-stats">
	            <div class="stat-card">
	                <h3>
	                    <span data-lucide="trophy"></span>
	                    <span>Puntuació</span>
	                </h3>
	                <p class="stat-value"><?php echo esc_html( $punts ); ?> punts</p>
	            </div>
	            <div class="stat-card">
	                <h3>
	                    <span data-lucide="bar-chart-3"></span>
	                    <span>Posició</span>
	                </h3>
	                <p class="stat-value">
	                    <?php echo $posicio ? esc_html( $posicio ) . 'º' : 'No hi ets al ranking'; ?>
	                </p>
	            </div>
	            <div class="stat-card">
	                <h3>
	                    <span data-lucide="target"></span>
	                    <span>Percentatge d'encerts</span>
	                </h3>
	                <p class="stat-value"><?php echo number_format( $percent_encerts, 2 ); ?>%</p>
	            </div>
	        </div>

        <!-- Llista de preguntes respostes -->
        <!--<div class="perfil-preguntes">
            <h3>📋 Preguntes que has respost</h3>

            <?php
            $hi_ha_preguntes = false;
            foreach ( $preguntes_respostes as $pid ) :
                if ( get_post_status( $pid ) !== 'publish' ) { continue; }
                if ( ! $hi_ha_preguntes ) {
                    echo '<ul class="preguntes-lista">';
                    $hi_ha_preguntes = true;
                } ?>
                <li class="pregunta-item">
                    <a target="_blank" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">
                        <?php echo esc_html( get_the_title( $pid ) ); ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php
            if ( $hi_ha_preguntes ) {
                echo '</ul>';
            } else {
                echo '<p class="no-preguntes">No has respost cap pregunta encara.</p>';
            } ?>
        </div>-->
    </div>

    <?php
    return ob_get_clean();
}
