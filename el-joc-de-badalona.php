<?php
/*
Plugin Name: El Joc de Badalona
Description: Gestiona preguntes diàries, respostes, i classificacions pels usuaris dins del Joc de Badalona.
Version: 1.0
Author: Tandem Projects
Author URI: https://tandemprojects.cat
Text Domain: joc-badalona
Domain Path: /languages
*/

// Evita acceso directo al archivo
if (!defined('ABSPATH')) exit;

// Define constantes útiles
define('EJB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EJB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Cargar archivo con funciones principales
require_once EJB_PLUGIN_PATH . 'includes/preguntas-functions.php';
require_once EJB_PLUGIN_PATH . 'includes/desempat-functions.php';

// Afegir explícitament activació i desactivació del cron a l'arxiu principal
register_activation_hook(__FILE__, 'ejb_activar_cron');
register_deactivation_hook(__FILE__, 'ejb_desactivar_cron');

function ejb_activar_cron() {
    if (!wp_next_scheduled('ejb_actualizar_puntos_diarios')) {
        wp_schedule_event(strtotime('midnight -2 hours'), 'daily', 'ejb_actualizar_puntos_diarios');
    }
}


function ejb_desactivar_cron() {
    wp_clear_scheduled_hook('ejb_actualizar_puntos_diarios');
}

// Incloure explicitament el hook d'execució del cron
add_action('ejb_actualizar_puntos_diarios', 'ejb_procesar_respuestas_pendientes');


// Afegir menú al panell d'administració
add_action('admin_menu', 'ejb_afegir_pagina_admin');

function ejb_afegir_pagina_admin() {
    add_menu_page(
        'Configuració El Joc de Badalona', // Títol de la pàgina
        'Joc de Badalona',                 // Text al menú
        'manage_options',                  // Capacitat requerida
        'ejb-configuracio',                // Slug
        'ejb_mostrar_pagina_config',       // Funció per mostrar contingut
        'dashicons-awards',	           // Icona
        80                                 // Posició al menú
    );
}


function ejb_mostrar_pagina_config() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Guardar configuracions normals
    if (isset($_POST['ejb_guardar_config'])) {
        check_admin_referer('ejb_config_verify');
        update_option('ejb_num_preguntas_diarias', intval($_POST['num_preguntas_diarias']));
        update_option('ejb_hora_limite_respuestas', sanitize_text_field($_POST['hora_limite_respuestas']));
        echo '<div class="updated notice"><p>Configuració guardada correctament!</p></div>';
    }

    // Restablir puntuacions
//     if (isset($_POST['ejb_reset_puntuacions'])) {
//         check_admin_referer('ejb_reset_verify');
//         $usuaris = get_users();
    
//         foreach ($usuaris as $usuari) {
//             // Eliminar puntuacions totals
//             delete_user_meta($usuari->ID, 'puntos_totales');
    
//             // Eliminar respostes pendents i anteriors
//             $usermeta = get_user_meta($usuari->ID);
//             foreach ($usermeta as $meta_key => $value) {
//                 if (strpos($meta_key, 'respuesta_pregunta_') === 0 || strpos($meta_key, 'respuesta_pendiente_') === 0) {
//                     delete_user_meta($usuari->ID, $meta_key);
//                 }
//             }
//         }
    
//         // Opcional: resetejar també comptadors totals de preguntes
//         $preguntas = get_posts(['post_type' => 'pregunta', 'numberposts' => -1]);
//         foreach ($preguntas as $pregunta) {
//             update_post_meta($pregunta->ID, 'total_respuestas', 0);
//             update_post_meta($pregunta->ID, 'respuestas_correctas', 0);
//         }
    
//         echo '<div class="updated notice"><p>✅ S\'han restablert totes les puntuacions i respostes dels usuaris.</p></div>';
//     }

    /* ──────────────────────────────────────────────────────────────
     * Restablir puntuacions (NOMÉS sistema nou)
     * ──────────────────────────────────────────────────────────────*/
    if ( isset( $_POST['ejb_reset_puntuacions'] ) ) {
        check_admin_referer( 'ejb_reset_verify' );
        
        foreach ( get_users() as $usuari ) {
            
            /* Punts totals */
            delete_user_meta( $usuari->ID, 'puntos_totales' );
            
            /* Array de respostes pendents/processades */
            delete_user_meta( $usuari->ID, 'respuestas_preguntas' );
            
            /* (Opcional) Historial detallat */
            delete_user_meta( $usuari->ID, 'respuestas_detalladas' );
        }
        
        /* Reiniciar comptadors de cada pregunta */
        $preguntas = get_posts( [
            'post_type'   => 'pregunta',
            'numberposts' => -1
        ] );
        foreach ( $preguntas as $pregunta ) {
            update_post_meta( $pregunta->ID, 'total_respuestas',     0 );
            update_post_meta( $pregunta->ID, 'respuestas_correctas', 0 );
        }
        
        echo '<div class="updated notice"><p>✅ S\'han restablert totes les puntuacions i respostes dels usuaris.</p></div>';
    }
    


    // Obtenir valors actuals
    $num_preguntas_diarias = get_option('ejb_num_preguntas_diarias', 4);
    $hora_limite_respuestas = get_option('ejb_hora_limite_respuestas', '23:59');
    ?>

<div class="wrap ejb-admin-wrap">
<h1 class="ejb-admin-title">⚙️ Configuració El Joc de Badalona</h1>

    <div class="ejb-admin-sections">
    <section class="ejb-admin-section">
        <div class="ejb-admin-section-head">
            <h2>Configuració i manteniment</h2>
            <p>Gestiona els paràmetres generals del joc i executa processos interns des d’un únic panell.</p>
        </div>

        <div class="ejb-admin-layout-main">
        
        <!-- Configuració general -->
        <div class="card ejb-admin-card ejb-admin-card-primary" style="flex: 1 1 300px; padding: 20px;">
            <h2 class="title">Configuració general</h2>
            <form method="post">
                <?php wp_nonce_field('ejb_config_verify'); ?>
                <div class="ejb-form-group">
                    <label for="num_preguntas_diarias">Número de preguntes diàries:</label>
                    <input type="number" name="num_preguntas_diarias" id="num_preguntas_diarias" value="<?php echo esc_attr($num_preguntas_diarias); ?>" min="1" max="10" class="regular-text" />
                </div>

                <div class="ejb-form-group">
                    <label for="hora_limite_respuestas">Hora límit per respondre (24h):</label>
                    <input type="time" name="hora_limite_respuestas" id="hora_limite_respuestas" value="<?php echo esc_attr($hora_limite_respuestas); ?>" class="regular-text" />
                </div>

                <?php submit_button('Guardar configuració', 'primary', 'ejb_guardar_config'); ?>
            </form>
        </div>

        <div class="ejb-admin-actions-grid">
            <!-- Restablir puntuacions -->
            <div class="card ejb-admin-card ejb-admin-card-compact" style="flex: 1 1 300px; padding: 20px;">
                <h2 class="title">🔄 Restablir puntuacions del joc</h2>
                <p>Aquesta acció esborrarà totes les puntuacions dels usuaris i no es podrà desfer.</p>
                <form method="post">
                    <?php wp_nonce_field('ejb_reset_verify'); ?>
                    <?php submit_button('⚠️ Restablir puntuacions', 'delete', 'ejb_reset_puntuacions', false, ['onclick' => "return confirm('Segur que vols restablir totes les puntuacions?');"]); ?>
                </form>
            </div>

            <!-- Exportar resultats -->
            <div class="card ejb-admin-card ejb-admin-card-compact" style="flex: 1 1 300px; padding: 20px;">
                <h2 class="title">📥 Exportar resultats del joc</h2>
                <p>Exporta les puntuacions actuals dels usuaris en un arxiu CSV.</p>
                <form method="post">
                    <?php wp_nonce_field('ejb_export_verify'); ?>
                    <?php submit_button('📥 Exportar CSV', 'secondary', 'ejb_export_csv'); ?>
                </form>
            </div>
            
            <div class="card ejb-admin-card ejb-admin-card-compact" style="flex:1 1 300px;padding:20px;">
                <h2 class="title">🚀 Processar respostes pendents ara</h2>
                <p>Executa el càlcul de punts i ranking immediatament.</p>
                <form method="post">
                    <?php wp_nonce_field( 'ejb_forzar_cron_verify' ); ?>
                    <?php submit_button( 'Processar ara', 'primary', 'ejb_forzar_cron' ); ?>
                </form>
            </div>
            
            <!-- Recalcular puntuacions -->
            <div class="card ejb-admin-card ejb-admin-card-compact" style="flex:1 1 300px;padding:20px;">
                <h2 class="title">🔢 Recalcular puntuacions</h2>
                <p>Torna a sumar punts i estadístiques a partir de les respostes guardades.</p>
                <form method="post">
                    <?php wp_nonce_field( 'ejb_recalc_verify' ); ?>
                    <?php submit_button(
                        '🔄 Recalcular ara',
                        'secondary',
                        'ejb_recalcular_puntuacions', 
                        false,
                        [ 'onclick' => "return confirm('Segur que vols recalcular totes les puntuacions?');" ]
                    ); ?>
                </form>
            </div>
        </div>
        </div>
    </section>

<?php
// --- procesar al enviar ----------------------------------------------
if ( isset( $_POST['ejb_forzar_cron'] ) && current_user_can('manage_options') ) {
    check_admin_referer( 'ejb_forzar_cron_verify' );
    ejb_procesar_respuestas_pendientes();
    echo '<div class="updated notice"><p>✅ S\'han processat totes les respostes pendents.</p></div>';
}

if ( isset( $_POST['ejb_recalcular_puntuacions'] ) && current_user_can('manage_options') ) {
    check_admin_referer( 'ejb_recalc_verify' );
    ejb_recalcular_puntuacions();
    echo '<div class="updated notice"><p>✅ S\'han processat totes les puntuacions.</p></div>';
}

	
	// Parte del formulario para buscar y anular pregunta
if (isset($_POST['ejb_anular_pregunta'])) {
    check_admin_referer('ejb_anular_pregunta_verify');
    $pregunta_id = intval($_POST['pregunta_a_anular']);
    if(get_post_type($pregunta_id) == 'pregunta') {
        update_post_meta($pregunta_id, '_ejb_pregunta_nula', 'si');
        echo '<div class="updated notice"><p>✅ Pregunta marcada com a nul·la correctament.</p></div>';
    } else {
        echo '<div class="error notice"><p>❌ Pregunta no vàlida o no trobada.</p></div>';
    }
}
?>
    <section class="ejb-admin-section">
        <div class="ejb-admin-section-head">
            <h2>Gestió de preguntes</h2>
            <p>Anul·la o reactiva preguntes de manera controlada quan hi hagi incidències o canvis de criteri.</p>
        </div>

        <div class="ejb-admin-layout-secondary">
    <div class="card ejb-admin-card ejb-admin-card-wide" style="padding: 20px; margin-bottom: 30px;">

        <h3>⚠️ Anular Pregunta</h3>
        <form method="post">
            <?php wp_nonce_field('ejb_anular_pregunta_verify'); ?>
            
            <input type="text" id="buscar_pregunta" placeholder="Busca per títol..." style="width:300px;">
            <select name="pregunta_a_anular" id="pregunta_a_anular" required style="width:300px;">
                <option value="">Selecciona la pregunta a anular...</option>
                <?php 
                $preguntas = get_posts(['post_type' => 'pregunta', 'numberposts' => -1]);
                foreach ($preguntas as $pregunta) {
                    echo '<option value="'.$pregunta->ID.'">'.$pregunta->post_title.'</option>';
                }
                ?>
            </select>

            <input type="submit" name="ejb_anular_pregunta" class="button button-primary" value="Marcar com a nul·la">
        </form>
    </div>
<script>
jQuery(document).ready(function($) {
    $('#buscar_pregunta').on('keyup', function(){
        var valor = $(this).val().toLowerCase();
        $('#pregunta_a_anular option').filter(function(){
            $(this).toggle($(this).text().toLowerCase().indexOf(valor) > -1)
        });
    });
});
</script>

	 <?php
	
	// Para reactivar preguntas anuladas
if (isset($_POST['ejb_reactivar_pregunta'])) {
    check_admin_referer('ejb_reactivar_pregunta_verify');
    $pregunta_id = intval($_POST['pregunta_a_reactivar']);
    delete_post_meta($pregunta_id, '_ejb_pregunta_nula');
    echo '<div class="updated notice"><p>✅ Pregunta reactivada correctament.</p></div>';
}
?>
<div class="card ejb-admin-card ejb-admin-card-wide">
    <h3>🔄 Reactivar Pregunta anul·lada</h3>
    <form method="post">
        <?php wp_nonce_field('ejb_reactivar_pregunta_verify'); ?>
        <select name="pregunta_a_reactivar" required style="width:300px;">
            <option value="">Selecciona la pregunta a reactivar...</option>
            <?php 
            $preguntas_anuladas = get_posts(['post_type' => 'pregunta', 'numberposts' => -1, 'meta_key' => '_ejb_pregunta_nula', 'meta_value' => 'si']);
            foreach ($preguntas_anuladas as $pregunta) {
                echo '<option value="'.$pregunta->ID.'">'.$pregunta->post_title.'</option>';
            }
            ?>
        </select>

        <input type="submit" name="ejb_reactivar_pregunta" class="button button-secondary" value="Reactivar pregunta">
    </form>
</div>
        </div>
    </section>

    <!-- GUIA DE SHORTCODES -->
    <section class="ejb-admin-section">
        <div class="ejb-admin-section-head">
            <h2><span class="dashicons dashicons-editor-code"></span> Guia d'Ús i Shortcodes</h2>
            <p>Utilitza els següents codis (shortcodes) per mostrar diferents parts del joc a les pàgines de la web.</p>
        </div>
        <div class="card ejb-admin-card ejb-admin-card-wide" style="width: 100%;">
            <table class="wp-list-table widefat fixed striped" style="font-size:14px;">
                <thead>
                    <tr>
                        <th style="width: 25%;">Shortcode</th>
                        <th style="width: 75%;">Descripció</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><code>[preguntas_diarias]</code></strong></td>
                        <td>Mostra el formulari de les preguntes actives del dia actual perquè els jugadors puguin respondre. S'oculta quan expira el límit.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[respuestas_anteriores]</code></strong></td>
                        <td>Mostra el resum de percentatge d'encerts i resultats del dia anterior.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[clasificacion]</code></strong></td>
                        <td>Genera la taula de classificació top 100 de jugadors amb els seus punts totals. Destaca la posició de l'usuari actual si està loguejat.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[detalle_pregunta]</code></strong></td>
                        <td>Mostra el detall de la resposta correcta i percentatges. S'utilitza a la pàgina de detall (single post) del Custom Post Type <code>pregunta</code>.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[totes_les_respostes]</code></strong></td>
                        <td>Mostra un llistat de l'arxiu històric de preguntes finalitzades de l'edició 2026.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[totes_les_respostes_2025]</code></strong></td>
                        <td>Mostra l'arxiu històric de l'edició 2025.</td>
                    </tr>
                    <tr>
                        <td><strong><code>[joc_desempat]</code></strong></td>
                        <td>Mostra el formulari especial de 3 preguntes obertes per al desempat final. (S'activa des del menú <em>Desempat</em>).</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>

<?php
}


add_action('admin_init', 'ejb_processar_exportacio_csv');

function ejb_processar_exportacio_csv() {
    if (isset($_POST['ejb_export_csv'])) {
        if (!current_user_can('manage_options')) return;
        check_admin_referer('ejb_export_verify');

        // Preparar CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=resultats-joc-badalona.csv');

        // Obrir sortida CSV
        $output = fopen('php://output', 'w');

        // Capçaleres CSV
        fputcsv($output, ['Usuari', 'Email', 'Puntuació']);

        // Obtenir usuaris i puntuacions
        $usuaris = get_users(['orderby' => 'meta_value_num', 'meta_key' => 'puntos_totales', 'order' => 'DESC']);
        foreach ($usuaris as $usuari) {
            $puntuacio = get_user_meta($usuari->ID, 'puntos_totales', true);
            fputcsv($output, [$usuari->user_login, $usuari->user_email, $puntuacio ? $puntuacio : 0]);
        }

        fclose($output);
        exit;
    }
}

// Registrar estilos del admin
add_action('admin_enqueue_scripts', 'ejb_admin_styles', 100);
function ejb_admin_styles($hook) {
    // Cargamos los estilos en cualquier página del plugin (configuración, desempat, respuestas)
    if (strpos($hook, 'ejb-') === false) {
        return;
    }

    $css_file = plugin_dir_path(__FILE__) . 'assets/css/admin.css';
    if (!file_exists($css_file)) {
        return;
    }

    wp_enqueue_style(
        'ejb-admin-styles',
        plugins_url('assets/css/admin.css', __FILE__),
        array(),
        filemtime($css_file)
    );
}

// Registrar acciones AJAX
add_action('wp_ajax_ejb_guardar_respuesta_ajax', 'ejb_guardar_respuesta_ajax');
add_action('wp_ajax_nopriv_ejb_guardar_respuesta_ajax', 'ejb_guardar_respuesta_ajax');

// Registrar scripts
function ejb_enqueue_scripts() {
    // Scripts
    wp_enqueue_script(
        'lucide',
        'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
        array(),
        null,
        true
    );
    wp_add_inline_script(
        'lucide',
        'document.addEventListener("DOMContentLoaded", function () { if (window.lucide) { window.lucide.createIcons(); } });'
    );

    wp_enqueue_script('jquery');
    wp_enqueue_script('ejb-ajax', plugins_url('/js/ejb-ajax.js', __FILE__), ['jquery'], '1.0', true);
    wp_localize_script('ejb-ajax', 'ejb_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ejb-ajax-nonce')
    ]);

    // Estilos
    wp_enqueue_style('ejb-estils', plugins_url('/styles.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'ejb_enqueue_scripts');
