<?php
// Evita acceso directo al archivo
if (!defined('ABSPATH')) exit;

/**
 * 1. Crear la tabla de base de datos para las respuestas del desempate.
 * Se ejecutará al iniciar el panel de administración si la versión cambia o no existe.
 */
function ejb_crear_tabla_desempat() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'joc_desempat_respostes';
    $charset_collate = $wpdb->get_charset_collate();

    // Verificamos si la tabla ya existe para no lanzar el dbDelta si no hace falta,
    // o simplemente usamos get_option('ejb_db_desempat_version').
    $version_db = '1.0';
    if (get_option('ejb_db_desempat_version') !== $version_db) {
        $sql = "CREATE TABLE {$table_name} (
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
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('ejb_db_desempat_version', $version_db);
    }
}
add_action('admin_init', 'ejb_crear_tabla_desempat');

/**
 * 2. Registrar Submenús de Administración
 * Importante: La prioridad 20 asegura que se ejecute DESPUÉS de que el menú padre 'ejb-configuracio' sea registrado
 * en el archivo principal, evitando que WordPress lo interprete como una URL externa y provoque un 404.
 */
add_action('admin_menu', 'ejb_afegir_submenus_desempat', 20);
function ejb_afegir_submenus_desempat() {
    // Configuración del Desempate
    add_submenu_page(
        'ejb-configuracio', // Parent slug
        'Desempat', 
        'Desempat', 
        'manage_options', 
        'ejb-desempat', 
        'ejb_mostrar_pagina_desempat'
    );

    // Revisión de Respuestas
    add_submenu_page(
        'ejb-configuracio',
        'Respostes Desempat', 
        'Respostes Desempat', 
        'manage_options', 
        'ejb-respostes-desempat', 
        'ejb_mostrar_pagina_respostes_desempat'
    );
}

/**
 * 3. Pantalla de Configuración del Desempate
 */
function ejb_mostrar_pagina_desempat() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['ejb_guardar_desempat'])) {
        check_admin_referer('ejb_desempat_verify');
        
        update_option('joc_desempat_actiu', isset($_POST['joc_desempat_actiu']) ? '1' : '0');
        update_option('joc_desempat_data_publicacio', sanitize_text_field($_POST['joc_desempat_data_publicacio']));
        update_option('joc_desempat_pregunta_1', sanitize_textarea_field($_POST['joc_desempat_pregunta_1']));
        update_option('joc_desempat_pregunta_2', sanitize_textarea_field($_POST['joc_desempat_pregunta_2']));
        update_option('joc_desempat_pregunta_3', sanitize_textarea_field($_POST['joc_desempat_pregunta_3']));
        update_option('joc_desempat_text_intro', sanitize_textarea_field($_POST['joc_desempat_text_intro']));
        update_option('joc_desempat_text_gracies', sanitize_textarea_field($_POST['joc_desempat_text_gracies']));
        
        echo '<div class="updated notice"><p>Configuració del desempat guardada correctament.</p></div>';
    }

    $actiu = get_option('joc_desempat_actiu', '0');
    $data_publicacio = get_option('joc_desempat_data_publicacio', '');
    $pregunta_1 = get_option('joc_desempat_pregunta_1', '');
    $pregunta_2 = get_option('joc_desempat_pregunta_2', '');
    $pregunta_3 = get_option('joc_desempat_pregunta_3', '');
    $text_intro = get_option('joc_desempat_text_intro', 'L\'hora de la veritat ha arribat. Respon a les 3 preguntes obertes per desempatar.');
    $text_gracies = get_option('joc_desempat_text_gracies', 'Gràcies per les teves respostes! Les hem guardat correctament.');

    ?>
    <div class="wrap ejb-admin-wrap">
        <h1 class="ejb-admin-title">🏆 Configuració del Desempat</h1>
        
        <div class="ejb-admin-sections">
            <section class="ejb-admin-section">
                <div class="ejb-admin-section-head">
                    <h2>Dades del Desempat</h2>
                    <p>Configura les preguntes, els textos d'introducció i el moment en què estarà disponible el formulari per als jugadors amb un 100% d'encerts.</p>
                </div>

                <div class="card ejb-admin-card ejb-admin-card-wide" style="width: 100%;">
                    <h2 class="title">Configuració general</h2>
                    <form method="post" class="ejb-desempat-config-form">
                        <?php wp_nonce_field('ejb_desempat_verify'); ?>
                        
                        <div class="ejb-form-group">
                            <label for="joc_desempat_actiu">Activar Desempat:</label>
                            <div>
                                <input type="checkbox" name="joc_desempat_actiu" id="joc_desempat_actiu" value="1" <?php checked($actiu, '1'); ?> />
                                <span class="description">Marcar per fer visible el formulari (sempre que es compleixi la data).</span>
                            </div>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_data_publicacio">Data y Hora de Publicació:</label>
                            <div>
                                <input type="datetime-local" name="joc_desempat_data_publicacio" id="joc_desempat_data_publicacio" value="<?php echo esc_attr($data_publicacio); ?>" class="regular-text" />
                                <p class="description">El formulari no es mostrarà abans d'aquesta data i hora.</p>
                            </div>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_text_intro">Text introductori:</label>
                            <textarea name="joc_desempat_text_intro" id="joc_desempat_text_intro" rows="3" class="large-text"><?php echo esc_textarea($text_intro); ?></textarea>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_pregunta_1">Pregunta 1:</label>
                            <textarea name="joc_desempat_pregunta_1" id="joc_desempat_pregunta_1" rows="3" class="large-text"><?php echo esc_textarea($pregunta_1); ?></textarea>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_pregunta_2">Pregunta 2:</label>
                            <textarea name="joc_desempat_pregunta_2" id="joc_desempat_pregunta_2" rows="3" class="large-text"><?php echo esc_textarea($pregunta_2); ?></textarea>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_pregunta_3">Pregunta 3:</label>
                            <textarea name="joc_desempat_pregunta_3" id="joc_desempat_pregunta_3" rows="3" class="large-text"><?php echo esc_textarea($pregunta_3); ?></textarea>
                        </div>

                        <div class="ejb-form-group">
                            <label for="joc_desempat_text_gracies">Text d'agraïment (després d'enviar):</label>
                            <textarea name="joc_desempat_text_gracies" id="joc_desempat_text_gracies" rows="3" class="large-text"><?php echo esc_textarea($text_gracies); ?></textarea>
                        </div>

                        <div style="margin-top: 20px;">
                            <?php submit_button('Guardar Configuració', 'primary', 'ejb_guardar_desempat', false); ?>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
    <?php
}

/**
 * 4. Pantalla de Revisión de Respuestas
 */
function ejb_mostrar_pagina_respostes_desempat() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'joc_desempat_respostes';

    // Acciones de actualización
    if (isset($_POST['ejb_actualizar_estat'])) {
        check_admin_referer('ejb_estat_verify');
        $id_resposta = intval($_POST['id_resposta']);
        $nou_estat = sanitize_text_field($_POST['estat_revisio']);
        
        $wpdb->update(
            $table,
            ['estat_revisio' => $nou_estat, 'updated_at' => current_time('mysql')],
            ['id' => $id_resposta],
            ['%s', '%s'],
            ['%d']
        );
        echo '<div class="updated notice"><p>Estat actualitzat.</p></div>';
    }

    // Filtros
    $filtre = isset($_GET['filtre']) ? sanitize_text_field($_GET['filtre']) : '100_percent';
    
    $where = "1=1";
    if ($filtre === '100_percent') {
        $where .= " AND te_100_percent = 1";
    } elseif ($filtre === 'pendent') {
        $where .= " AND estat_revisio = 'pendent'";
    } elseif ($filtre === 'correcte') {
        $where .= " AND estat_revisio = 'correcte'";
    }

    $respostes = $wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY created_at ASC");

    ?>
    <div class="wrap ejb-admin-wrap">
        <h1 class="ejb-admin-title">📝 Respostes del Desempat</h1>
        
        <ul class="subsubsub">
            <li><a href="?page=ejb-respostes-desempat&filtre=tots" <?php echo $filtre==='tots'?'class="current"':''; ?>>Tots</a> | </li>
            <li><a href="?page=ejb-respostes-desempat&filtre=100_percent" <?php echo $filtre==='100_percent'?'class="current"':''; ?>>Només 100% encerts</a> | </li>
            <li><a href="?page=ejb-respostes-desempat&filtre=pendent" <?php echo $filtre==='pendent'?'class="current"':''; ?>>Pendents de revisar</a> | </li>
            <li><a href="?page=ejb-respostes-desempat&filtre=correcte" <?php echo $filtre==='correcte'?'class="current"':''; ?>>Correctes</a></li>
        </ul>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;">Usuari / Data</th>
                    <th style="width: 10%;">Estat Punts</th>
                    <th style="width: 25%;">Respostes</th>
                    <th style="width: 15%;">Revisió</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($respostes)): ?>
                    <tr><td colspan="4">No s'han trobat respostes.</td></tr>
                <?php else: ?>
                    <?php foreach ($respostes as $r): 
                        $user = get_userdata($r->user_id);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($user ? $user->user_login : 'Desconegut'); ?></strong><br/>
                            <small><?php echo esc_html($user ? $user->user_email : ''); ?></small><br/><br/>
                            <small>Enviat: <?php echo esc_html($r->created_at); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($r->punts_totals); ?> / <?php echo esc_html($r->total_preguntes_valides); ?> valides<br/>
                            <?php if ($r->te_100_percent): ?>
                                <span style="color: green; font-weight: bold;">✅ 100%</span>
                            <?php else: ?>
                                <span style="color: red;">❌ No 100%</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong>P1:</strong> <?php echo nl2br(esc_html($r->resposta_1)); ?><br/><hr style="margin:5px 0;">
                            <strong>P2:</strong> <?php echo nl2br(esc_html($r->resposta_2)); ?><br/><hr style="margin:5px 0;">
                            <strong>P3:</strong> <?php echo nl2br(esc_html($r->resposta_3)); ?>
                        </td>
                        <td>
                            Estat: <strong><?php echo esc_html(strtoupper($r->estat_revisio)); ?></strong>
                            <form method="post" style="margin-top:10px;">
                                <?php wp_nonce_field('ejb_estat_verify'); ?>
                                <input type="hidden" name="id_resposta" value="<?php echo esc_attr($r->id); ?>" />
                                <select name="estat_revisio">
                                    <option value="pendent" <?php selected($r->estat_revisio, 'pendent'); ?>>Pendent</option>
                                    <option value="correcte" <?php selected($r->estat_revisio, 'correcte'); ?>>Correcte</option>
                                    <option value="incorrecte" <?php selected($r->estat_revisio, 'incorrecte'); ?>>Incorrecte</option>
                                </select>
                                <br/><br/>
                                <button type="submit" name="ejb_actualizar_estat" class="button button-small">Actualitzar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * 5. Lógica para saber si el usuario ya respondió
 */
function joc_desempat_user_ja_ha_respost($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'joc_desempat_respostes';
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
}

/**
 * 6. Calcular total de preguntas válidas (ignorar las nulas)
 */
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

/**
 * 7. Calcular estado y 100%
 */
function joc_get_estat_punts_usuari($user_id) {
    $punts_totals = (int) get_user_meta($user_id, 'puntos_totales', true);
    $total_valides = joc_get_total_preguntes_valides();

    return [
        'punts_totals'             => $punts_totals,
        'total_preguntes_valides'  => $total_valides,
        'te_100_percent'           => ($total_valides > 0 && $punts_totals >= $total_valides) ? 1 : 0,
    ];
}

/**
 * 8. Procesar formulario del frontend
 */
function joc_desempat_processar_formulari() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['joc_desempat_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['joc_desempat_nonce'], 'joc_desempat_enviar')) {
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
        ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
    );
    
    // Podemos guardar un transient de 5 mins o un post meta temporal para mostrar aviso de "exito" en la misma peticion,
    // pero como recargará la pagina, el shortcode detectará que ya ha respondido y mostrará el texto de gracias.
}
add_action('init', 'joc_desempat_processar_formulari');

/**
 * 9. Shortcode [joc_desempat]
 */
function joc_desempat_shortcode() {
    if (get_option('joc_desempat_actiu') !== '1') {
        return '';
    }

    $data_publicacio = get_option('joc_desempat_data_publicacio');
    
    if (empty($data_publicacio) || current_time('timestamp') < strtotime($data_publicacio)) {
        return '<p class="desempat-msg-info">La pregunta de desempat encara no està disponible.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p class="desempat-msg-info">Has d’iniciar sessió per respondre el desempat.</p>';
    }

    $user_id = get_current_user_id();

    if (joc_desempat_user_ja_ha_respost($user_id)) {
        $text_gracies = get_option('joc_desempat_text_gracies', 'Gràcies per les teves respostes! Les hem guardat correctament.');
        return '<div class="desempat-msg-success"><p>' . nl2br(esc_html($text_gracies)) . '</p></div>';
    }

    $text_intro = get_option('joc_desempat_text_intro', '');
    $pregunta_1 = get_option('joc_desempat_pregunta_1', '');
    $pregunta_2 = get_option('joc_desempat_pregunta_2', '');
    $pregunta_3 = get_option('joc_desempat_pregunta_3', '');

    ob_start();
    ?>
    <div class="desempat-container">
        <?php if (!empty($text_intro)): ?>
            <p class="desempat-intro"><?php echo nl2br(esc_html($text_intro)); ?></p>
        <?php endif; ?>
        
        <form method="post" class="desempat-form">
            <?php wp_nonce_field('joc_desempat_enviar', 'joc_desempat_nonce'); ?>
            
            <div class="desempat-group">
                <label for="resposta_1"><?php echo esc_html($pregunta_1); ?></label>
                <textarea name="resposta_1" id="resposta_1" rows="4" required></textarea>
            </div>

            <div class="desempat-group">
                <label for="resposta_2"><?php echo esc_html($pregunta_2); ?></label>
                <textarea name="resposta_2" id="resposta_2" rows="4" required></textarea>
            </div>

            <div class="desempat-group">
                <label for="resposta_3"><?php echo esc_html($pregunta_3); ?></label>
                <textarea name="resposta_3" id="resposta_3" rows="4" required></textarea>
            </div>

            <div class="desempat-submit">
                <button type="submit" class="boton-desempat" onclick="return confirm('Segur que vols enviar aquestes respostes? Un cop enviades no es podran modificar.');">Enviar respostes de desempat</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('joc_desempat', 'joc_desempat_shortcode');
