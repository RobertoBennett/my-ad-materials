<?php
/* ====================================
 * Plugin Name: My Ad Materials
 * Description: Это мощный инструмент для управления рекламой, который превращает любой контент в привлекательный рекламный банер. 
 * Plugin URI: https://yoursite.com/
 * Version: 1.3
 * Author: Robert Bennett
 * Text Domain: My Ad Materials
 * ==================================== */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Гарантируем раннюю загрузку критически важных функций
add_action('plugins_loaded', 'register_ad_material_post_type', 1);
add_action('plugins_loaded', 'create_analytics_table', 1);

// Функция создания таблицы аналитики
function create_analytics_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ad_analytics';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ad_id mediumint(9) NOT NULL,
        action varchar(20) NOT NULL,
        referrer text,
        url text,
        ip_address varchar(45),
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        site_id varchar(50) DEFAULT '',
        PRIMARY KEY (id),
        KEY ad_id (ad_id),
        KEY action (action),
        KEY created_at (created_at),
        KEY site_id (site_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Функция проверки и создания таблицы при необходимости
function maybe_create_analytics_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ad_analytics';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_analytics_table();
    }
}

// Регистрация кастомного типа записи для рекламных материалов
function register_ad_material_post_type() {
    register_post_type('ad_material', array(
        'labels' => array(
            'name' => '📢 Рекламные материалы',
            'singular_name' => 'Рекламный материал',
            'menu_name' => 'Реклама',
            'add_new' => 'Добавить',
            'add_new_item' => 'Добавить материал',
            'edit_item' => 'Редактировать',
            'new_item' => 'Новый материал',
            'view_item' => 'Просмотр',
            'search_items' => 'Поиск',
            'not_found' => 'Не найдено',
            'not_found_in_trash' => 'Корзина пуста',
            'all_items' => 'Все материалы'
        ),
        'public' => true,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => false,
        'capability_type' => 'post',
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-megaphone',
        'supports' => array('title', 'editor', 'thumbnail'),
        'show_in_rest' => false,
        'can_export' => true,
        'delete_with_user' => false,
        'map_meta_cap' => true,
        'exclude_from_search' => true,
        'show_in_nav_menus' => false
    ));
}

// Регистрация кастомного типа записи для сайтов рекламы
function register_ad_site_post_type() {
    register_post_type('ad_site', array(
        'labels' => array(
            'name' => '🌐 Сайты для рекламы',
            'singular_name' => 'Сайт',
            'menu_name' => 'Сайты для рекламы',
            'add_new' => 'Добавить сайт',
            'add_new_item' => 'Добавить новый сайт',
            'edit_item' => 'Редактировать сайт',
            'new_item' => 'Новый сайт',
            'view_item' => 'Просмотр сайта',
            'search_items' => 'Поиск сайтов',
            'not_found' => 'Сайты не найдены',
            'not_found_in_trash' => 'Корзина пуста',
            'all_items' => 'Все сайты'
        ),
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=ad_material',
        'query_var' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => 26,
        'supports' => array('title'),
        'show_in_rest' => false,
        'can_export' => true,
        'delete_with_user' => false
    ));
}

// Регистрация API endpoints
add_action('rest_api_init', function() {
    // Endpoint для получения рекламных материалов
    register_rest_route('custom/v1', '/ads', array(
        'methods' => 'GET',
        'callback' => 'get_ad_materials',
        'permission_callback' => '__return_true',
        'args' => array(
            'category' => array(
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'limit' => array(
                'default' => 1,
                'sanitize_callback' => 'absint'
            ),
            'site_id' => array(
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Endpoint для аналитики
    register_rest_route('custom/v1', '/analytics', array(
        'methods' => 'POST',
        'callback' => 'save_ad_analytics',
        'permission_callback' => '__return_true'
    ));
    
    // Endpoint для получения статистики (для админки)
    register_rest_route('custom/v1', '/analytics/stats', array(
        'methods' => 'GET',
        'callback' => 'get_ad_analytics_stats',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
});

// Функция получения рекламных материалов
function get_ad_materials($request) {
    $category = $request->get_param('category') ?: 'all';
    $limit = intval($request->get_param('limit')) ?: 1;
    $site_id = $request->get_param('site_id') ?: '';
    
    $args = array(
        'post_type' => 'ad_material',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'rand'
    );
    
    if ($category !== 'all') {
        $args['meta_query'] = array(
            array(
                'key' => 'ad_category',
                'value' => $category,
                'compare' => '='
            )
        );
    }
    
    $posts = get_posts($args);
    $ads = array();
    
    foreach ($posts as $post) {
        // Фильтрация по сайтам
        $allowed_sites = get_post_meta($post->ID, 'ad_allowed_sites', true) ?: [];
        
        // Если материал привязан к сайтам и site_id не входит в список - пропускаем
        if (!empty($allowed_sites)) {
            if (!empty($site_id) && !in_array($site_id, $allowed_sites)) {
                continue; 
            }
        }
        
        $image_url = get_the_post_thumbnail_url($post->ID, 'medium');
        if (!$image_url) {
            // Создаем placeholder изображение если нет картинки
            $image_url = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg width="300" height="150" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100%" height="100%" fill="#4CAF50"/>
                    <text x="50%" y="50%" font-family="Arial" font-size="18" fill="white" text-anchor="middle" dy=".3em">' . 
                    esc_html($post->post_title) . '</text>
                </svg>'
            );
        }
        
        $ads[] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => wp_trim_words($post->post_content, 20),
            'link' => get_post_meta($post->ID, 'ad_link', true) ?: '#',
            'image' => $image_url,
            'category' => get_post_meta($post->ID, 'ad_category', true) ?: 'general'
        );
    }
    
    // Если нет рекламных материалов, возвращаем тестовый
    if (empty($ads)) {
        $ads[] = array(
            'id' => 0,
            'title' => 'Реклама Заблокирована',
            'description' => 'Ваша рекламная компания заблокирована, в связи с несоответствием требований о размещении рекламы.',
            'link' => home_url(),
            'image' => 'data:image/svg+xml;base64,' . base64_encode(
                '<svg width="auto" height="150" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100%" height="100%" fill="#f32020"/>
                    <text x="50%" y="50%" font-family="Arial Black" font-size="16" fill="white" text-anchor="middle" dy=".3em">Реклама Заблокирована</text>
                </svg>'
            ),
            'category' => 'test'
        );
    }
    
    return rest_ensure_response($ads);
}

// Функция сохранения аналитики
function save_ad_analytics($request) {
    $data = json_decode($request->get_body(), true);
    
    if (!$data || !isset($data['action']) || !isset($data['ad_id'])) {
        return new WP_Error('invalid_data', 'Неверные данные', array('status' => 400));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ad_analytics';
    
    // Проверяем существование таблицы
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_analytics_table();
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'ad_id' => intval($data['ad_id']),
            'action' => sanitize_text_field($data['action']),
            'referrer' => esc_url_raw($data['referrer'] ?? ''),
            'url' => esc_url_raw($data['url'] ?? ''),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => current_time('mysql'),
            'site_id' => sanitize_text_field($data['site_id'] ?? '')
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Ошибка сохранения в базу данных', array('status' => 500));
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Статистика сохранена',
        'id' => $wpdb->insert_id
    ));
}

// Функция получения статистики
function get_ad_analytics_stats($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ad_analytics';
    
    $days = intval($request->get_param('days')) ?: 30;
    
    $stats = $wpdb->get_results($wpdb->prepare("
        SELECT 
            ad_id,
            site_id,
            COUNT(CASE WHEN action = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN action = 'click' THEN 1 END) as clicks,
            ROUND(
                CASE 
                    WHEN COUNT(CASE WHEN action = 'impression' THEN 1 END) > 0 
                    THEN COUNT(CASE WHEN action = 'click' THEN 1 END) * 100.0 / COUNT(CASE WHEN action = 'impression' THEN 1 END)
                    ELSE 0 
                END, 2
            ) as ctr
        FROM $table_name 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY ad_id, site_id
        ORDER BY impressions DESC
    ", $days));
    
    // Добавляем информацию о постах и сайтах
    foreach ($stats as &$stat) {
        if ($stat->ad_id > 0) {
            $post = get_post($stat->ad_id);
            $stat->title = $post ? $post->post_title : 'Удаленный пост';
        } else {
            $stat->title = 'Тестовая реклама';
        }
        
        // Получаем название сайта по ID
        if ($stat->site_id) {
            $site_post = get_post($stat->site_id);
            $stat->site_name = $site_post ? $site_post->post_title : 'Неизвестный сайт';
        } else {
            $stat->site_name = 'Все сайты';
        }
    }
    
    return rest_ensure_response($stats);
}

// Очистка связанных данных при удалении рекламного материала
function cleanup_ad_material_data($post_id) {
    if (get_post_type($post_id) !== 'ad_material') {
        return;
    }
    
    global $wpdb;
    
    // Удаляем статистику для этого материала
    $table_name = $wpdb->prefix . 'ad_analytics';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $deleted = $wpdb->delete(
            $table_name,
            array('ad_id' => $post_id),
            array('%d')
        );
        
        if ($deleted !== false) {
            error_log("Deleted $deleted analytics records for ad material $post_id");
        }
    }
    
    // Удаляем мета-данные
    delete_post_meta($post_id, 'ad_link');
    delete_post_meta($post_id, 'ad_category');
    delete_post_meta($post_id, 'ad_allowed_sites');
}

// Хуки для очистки данных при удалении
add_action('before_delete_post', 'cleanup_ad_material_data');
add_action('wp_trash_post', 'cleanup_ad_material_data');
add_action('deleted_post', 'cleanup_ad_material_data');

// Функция принудительного удаления через GET запрос
function handle_force_delete_ad_material() {
    // Проверяем nonce для безопасности
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'force_delete_ad_material')) {
        wp_die('Ошибка безопасности');
    }
    
    if (!isset($_GET['post_id']) || !current_user_can('delete_posts')) {
        wp_die('Нет прав для удаления');
    }
    
    $post_id = intval($_GET['post_id']);
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'ad_material') {
        wp_die('Неверный материал');
    }
    
    // Очищаем связанные данные
    cleanup_ad_material_data($post_id);
    
    // Удаляем пост принудительно
    $deleted = wp_delete_post($post_id, true);
    
    if ($deleted) {
        wp_redirect(admin_url('edit.php?post_type=ad_material&deleted=1'));
    } else {
        wp_redirect(admin_url('edit.php?post_type=ad_material&error=1'));
    }
    exit;
}

// Обработка GET запроса для принудительного удаления
function check_force_delete_request() {
    if (isset($_GET['action']) && $_GET['action'] === 'force_delete_ad_material') {
        handle_force_delete_ad_material();
    }
}
add_action('admin_init', 'check_force_delete_request');

// Функция добавления кнопок удаления
function add_force_delete_button() {
    global $post_type, $pagenow;
    
    if ($post_type === 'ad_material' && $pagenow === 'edit.php') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Добавляем кнопку принудительного удаления для каждой строки
            $('.row-actions').each(function() {
                var $actions = $(this);
                var $editLink = $actions.find('.edit a');
                
                if ($editLink.length > 0) {
                    var href = $editLink.attr('href');
                    var postId = href.match(/post=(\d+)/);
                    
                    if (postId && postId[1]) {
                        var nonce = '<?php echo wp_create_nonce('force_delete_ad_material'); ?>';
                        var forceDeleteUrl = '<?php echo admin_url('edit.php?post_type=ad_material'); ?>&action=force_delete_ad_material&post_id=' + postId[1] + '&_wpnonce=' + nonce;
                        var forceDeleteHtml = ' | <span class="force-delete"><a href="' + forceDeleteUrl . '" onclick="return confirm(\'Вы уверены, что хотите окончательно удалить этот материал? Это действие нельзя отменить!\')" style="color: #a00;">Удалить навсегда</a></span>';
                        $actions.append(forceDeleteHtml);
                    }
                }
            });
            
            // Исправляем стандартное удаление
            $('.row-actions .trash a, .row-actions .delete a').on('click', function(e) {
                if (!confirm('Вы уверены, что хотите удалить этот материал?')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Добавляем опцию массового удаления
            $('select[name="action"], select[name="action2"]').each(function() {
                if ($(this).find('option[value="force_delete_bulk"]').length === 0) {
                    $(this).append('<option value="force_delete_bulk">Удалить навсегда</option>');
                }
            });
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'add_force_delete_button');

// Обработка массового удаления
function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'force_delete_bulk') {
        return $redirect_to;
    }
    
    if (!current_user_can('delete_posts')) {
        return $redirect_to;
    }
    
    $deleted_count = 0;
    
    foreach ($post_ids as $post_id) {
        if (get_post_type($post_id) === 'ad_material') {
            // Очищаем связанные данные
            cleanup_ad_material_data($post_id);
            
            // Принудительно удаляем пост
            if (wp_delete_post($post_id, true)) {
                $deleted_count++;
            }
        }
    }
    
    $redirect_to = add_query_arg('bulk_deleted', $deleted_count, $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-ad_material', 'handle_bulk_actions', 10, 3);

// Уведомления об удалении
function ad_material_deletion_notice() {
    if (isset($_GET['deleted']) && isset($_GET['post_type']) && $_GET['post_type'] === 'ad_material') {
        $deleted_count = intval($_GET['deleted']);
        if ($deleted_count > 0) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Успешно удалено материалов: ' . $deleted_count . '</p>';
            echo '</div>';
        }
    }
    
    if (isset($_GET['bulk_deleted']) && isset($_GET['post_type']) && $_GET['post_type'] === 'ad_material') {
        $deleted_count = intval($_GET['bulk_deleted']);
        if ($deleted_count > 0) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Массово удалено материалов: ' . $deleted_count . '</p>';
            echo '</div>';
        }
    }
    
    if (isset($_GET['error']) && isset($_GET['post_type']) && $_GET['post_type'] === 'ad_material') {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>Ошибка при удалении материала.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'ad_material_deletion_notice');

// Принудительное исправление прав для удаления
function fix_ad_material_delete_cap($caps, $cap, $user_id, $args) {
    if ($cap === 'delete_post' && isset($args[0])) {
        $post = get_post($args[0]);
        if ($post && $post->post_type === 'ad_material') {
            if (current_user_can('delete_posts')) {
                return array('delete_posts');
            }
        }
    }
    return $caps;
}
add_filter('map_meta_cap', 'fix_ad_material_delete_cap', 10, 4);

// Добавление мета-полей для рекламных материалов
function add_ad_material_meta_boxes() {
    add_meta_box(
        'ad_material_settings',
        '⚙️ Настройки рекламы',
        'ad_material_meta_box_callback',
        'ad_material',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_ad_material_meta_boxes');

function ad_material_meta_box_callback($post) {
    wp_nonce_field('ad_material_meta_box', 'ad_material_meta_box_nonce');
    
    $link = get_post_meta($post->ID, 'ad_link', true);
    $category = get_post_meta($post->ID, 'ad_category', true);
    $allowed_sites = get_post_meta($post->ID, 'ad_allowed_sites', true) ?: [];
    
    // Получаем все созданные сайты для рекламы
    $sites = get_posts([
        'post_type' => 'ad_site',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);

    ?>

    <style>
        .ad-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .ad-meta-table th {
            text-align: left;
            padding: 10px;
            width: 150px;
            vertical-align: top;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        .ad-meta-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .ad-meta-input {
            width: 100%;
            max-width: 500px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .ad-meta-select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            min-width: 200px;
        }
        .ad-instructions {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 3px;
            padding: 15px;
            margin: 15px 0;
        }
        .ad-instructions h4 {
            margin-top: 0;
            color: #0073aa;
        }
        .ad-instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .ad-code-example {
            background: white;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .ad-sites-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 5px;
        }
        .ad-sites-list label {
            display: block;
            margin-bottom: 5px;
        }
    </style>
    
    <table class="ad-meta-table">
        <tr>
            <th><label for="ad_link">Ссылка для перехода:</label></th>
            <td>
                <input type="url" 
                       id="ad_link" 
                       name="ad_link" 
                       value="<?php echo esc_attr($link); ?>" 
                       class="ad-meta-input"
                       placeholder="https://example.com" 
                       required />
                <p class="description">Полная ссылка с http:// или https://</p>
            </td>
        </tr>
        <tr>
            <th><label for="ad_category">Категория:</label></th>
            <td>
                <select id="ad_category" name="ad_category" class="ad-meta-select">
                    <option value="general" <?php selected($category, 'general'); ?>>Общая</option>
                    <option value="relationships" <?php selected($category, 'relationships'); ?>>Отношения</option>
                    <option value="health" <?php selected($category, 'health'); ?>>Здоровье</option>
                    <option value="lifestyle" <?php selected($category, 'lifestyle'); ?>>Образ жизни</option>
                    <option value="adult" <?php selected($category, 'adult'); ?>>18+</option>
                </select>
                <p class="description">Выберите категорию для таргетинга рекламы</p>
            </td>
        </tr>
        <tr>
            <th><label>Доступ на сайтах:</label></th>
            <td>
                <p class="description">Выберите сайты, где разрешён показ этого материала. Если ничего не выбрано, материал будет показываться на всех сайтах.</p>
                <div class="ad-sites-list">
                    <?php foreach ($sites as $site) : 
                        $checked = in_array($site->ID, $allowed_sites) ? 'checked' : '';
                    ?>
                        <label>
                            <input type="checkbox" 
                                   name="ad_allowed_sites[]" 
                                   value="<?php echo $site->ID; ?>" 
                                   <?php echo $checked; ?>> 
                            <?php echo esc_html($site->post_title); ?>
                            (ID: <?php echo $site->ID; ?>)
                        </label>
                    <?php endforeach; ?>
                    
                    <?php if (empty($sites)) : ?>
                        <p>Сайты не найдены. <a href="<?php echo admin_url('post-new.php?post_type=ad_site'); ?>">Добавьте сайты</a> для показа рекламы.</p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
    
    <div class="ad-instructions">
        <h4>📋 Инструкция по созданию рекламного материала:</h4>
        <ul>
            <li><strong>Заголовок:</strong> Введите привлекательный заголовок (отображается как ссылка)</li>
            <li><strong>Описание:</strong> Добавьте краткое описание в основном поле контента</li>
            <li><strong>Изображение:</strong> Установите миниатюру через кнопку "Установить миниатюру"</li>
            <li><strong>Ссылка:</strong> Обязательно укажите полную ссылку для перехода</li>
            <li><strong>Категория:</strong> Выберите подходящую категорию для показа нужной аудитории</li>
            <li><strong>Сайты:</strong> Укажите сайты, где разрешено показывать этот материал</li>
        </ul>
        
        <h4>🔗 Примеры использования скрипта:</h4>
        
        <p><strong>Базовое встраивание:</strong></p>
        <div class="ad-code-example">
&lt;script&gt;
window.adSiteId = "ID_САЙТА";
&lt;/script&gt;
&lt;script src="<?php echo home_url('/wp-content/plugins/my-ad-materials/js/ad-inline.js'); ?>" 
        data-api-url="<?php echo home_url('/wp-json/custom/v1/ads'); ?>"
        data-width="300px" 
        data-height="250px"&gt;&lt;/script&gt;
        </div>
        
        <p><strong>С категорией:</strong></p>
        <div class="ad-code-example">
&lt;script&gt;
window.adSiteId = "ID_САЙТА";
&lt;/script&gt;
&lt;script src="<?php echo home_url('/wp-content/plugins/my-ad-materials/js/ad-inline.js'); ?>" 
        data-api-url="<?php echo home_url('/wp-json/custom/v1/ads'); ?>"
        data-width="100%" 
        data-height="200px"
        data-category="<?php echo esc_attr($category ?: 'general'); ?>"&gt;&lt;/script&gt;
        </div>
        
        <p><strong>С ротацией:</strong></p>
        <div class="ad-code-example">
&lt;script&gt;
window.adSiteId = "ID_САЙТА";
&lt;/script&gt;
&lt;script src="<?php echo home_url('/wp-content/plugins/my-ad-materials/js/ad-inline.js'); ?>" 
        data-api-url="<?php echo home_url('/wp-json/custom/v1/ads'); ?>"
        data-width="728px" 
        data-height="90px"
        data-auto-rotate="true"
        data-rotate-interval="5000"
        data-limit="5"&gt;&lt;/script&gt;
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Проверка заполнения ссылки при сохранении
        $('form#post').on('submit', function(e) {
            var link = $('#ad_link').val();
            if (!link || link.trim() === '') {
                alert('Пожалуйста, укажите ссылку для перехода!');
                $('#ad_link').focus();
                e.preventDefault();
                return false;
            }
            
            if (!link.match(/^https?:\/\/.+/)) {
                alert('Ссылка должна начинаться с http:// или https://');
                $('#ad_link').focus();
                e.preventDefault();
                return false;
            }
        });
        
        // Автоматическое добавление http:// если не указан протокол
        $('#ad_link').on('blur', function() {
            var link = $(this).val().trim();
            if (link && !link.match(/^https?:\/\//)) {
                $(this).val('https://' + link);
            }
        });
    });
    </script>
    <?php
}

// Сохранение мета-полей
function save_ad_material_meta_box($post_id) {
    if (!isset($_POST['ad_material_meta_box_nonce'])) return;
    if (!wp_verify_nonce($_POST['ad_material_meta_box_nonce'], 'ad_material_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['ad_link'])) {
        update_post_meta($post_id, 'ad_link', esc_url_raw($_POST['ad_link']));
    }
    
    if (isset($_POST['ad_category'])) {
        update_post_meta($post_id, 'ad_category', sanitize_text_field($_POST['ad_category']));
    }
    
    // Сохранение выбранных сайтов
    if (isset($_POST['ad_allowed_sites'])) {
        $allowed_sites = array_map('intval', $_POST['ad_allowed_sites']);
        update_post_meta($post_id, 'ad_allowed_sites', $allowed_sites);
    } else {
        update_post_meta($post_id, 'ad_allowed_sites', []);
    }
}
add_action('save_post', 'save_ad_material_meta_box');

// Добавление мета-полей для сайтов рекламы
function add_ad_site_meta_boxes() {
    add_meta_box(
        'ad_site_settings',
        '🔧 Настройки сайта',
        'ad_site_meta_box_callback',
        'ad_site',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_ad_site_meta_boxes');

function ad_site_meta_box_callback($post) {
    wp_nonce_field('ad_site_meta_box', 'ad_site_meta_box_nonce');
    
    $site_id = $post->ID;
    $site_domain = get_post_meta($post->ID, 'ad_site_domain', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="ad_site_domain">Домен сайта:</label></th>
            <td>
                <input type="url" 
                       id="ad_site_domain" 
                       name="ad_site_domain" 
                       value="<?php echo esc_attr($site_domain); ?>" 
                       class="regular-text"
                       placeholder="https://example.com" 
                       required />
                <p class="description">Полный URL сайта где будет показываться реклама</p>
            </td>
        </tr>
        <tr>
            <th>ID сайта:</th>
            <td>
                <code><?php echo $site_id; ?></code>
                <p class="description">Используйте этот ID в параметре site_id при вставке рекламы</p>
            </td>
        </tr>
        <tr>
            <th>Пример кода:</th>
            <td>
                <textarea readonly rows="4" style="width:100%;font-family:monospace;">
&lt;script&gt;
window.adSiteId = "<?php echo $site_id; ?>";
&lt;/script&gt;
&lt;script src="<?php echo plugins_url('js/ad-inline.js', __FILE__); ?>" 
        data-api-url="<?php echo home_url('/wp-json/custom/v1/ads'); ?>" 
        data-width="300px" 
        data-height="250px"&gt;&lt;/script&gt;</textarea>
                <p class="description">Скопируйте этот код для вставки на сайт</p>
            </td>
        </tr>
    </table>
    <?php
}

// Сохранение мета-полей сайта
function save_ad_site_meta_box($post_id) {
    if (!isset($_POST['ad_site_meta_box_nonce'])) return;
    if (!wp_verify_nonce($_POST['ad_site_meta_box_nonce'], 'ad_site_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['ad_site_domain'])) {
        update_post_meta($post_id, 'ad_site_domain', esc_url_raw($_POST['ad_site_domain']));
    }
}
add_action('save_post', 'save_ad_site_meta_box');

// Добавление страницы статистики в админку
function add_ad_analytics_menu() {
    add_submenu_page(
        'edit.php?post_type=ad_material',
        'Статистика рекламы',
        '📊 Статистика',
        'manage_options',
        'ad-analytics',
        'show_ad_analytics_page'
    );
}
add_action('admin_menu', 'add_ad_analytics_menu');

function show_ad_analytics_page() {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    
    echo '<div class="wrap">';
    echo '<h1>📊 Статистика рекламы</h1>';
    
    // Фильтр по периоду
    echo '<div style="margin: 20px 0;">';
    echo '<label for="days-filter">Период: </label>';
    echo '<select id="days-filter" onchange="window.location.href=\'?post_type=ad_material&page=ad-analytics&days=\' + this.value">';
    echo '<option value="7"' . selected($days, 7, false) . '>7 дней</option>';
    echo '<option value="30"' . selected($days, 30, false) . '>30 дней</option>';
    echo '<option value="90"' . selected($days, 90, false) . '>90 дней</option>';
    echo '</select>';
    echo '</div>';
    
    // Получаем статистику через API
    $request = new WP_REST_Request('GET', '/custom/v1/analytics/stats');
    $request->set_param('days', $days);
    $response = get_ad_analytics_stats($request);
    $stats = $response->get_data();
    
    if (empty($stats)) {
        echo '<div class="notice notice-info"><p>Статистика пока отсутствует. Добавьте рекламные материалы и разместите скрипты на сайтах.</p></div>';
        echo '<h3>🔗 API Endpoints:</h3>';
        echo '<ul>';
        echo '<li><strong>Получение рекламы:</strong> <code>' . home_url('/wp-json/custom/v1/ads') . '</code></li>';
        echo '<li><strong>Отправка статистики:</strong> <code>' . home_url('/wp-json/custom/v1/analytics') . '</code></li>';
        echo '</ul>';
        
        echo '<h3>📝 Пример использования скрипта:</h3>';
        echo '<textarea readonly style="width: 100%; height: 100px; font-family: monospace;">';
        echo '&lt;script&gt;' . "\n";
        echo 'window.adSiteId = "' . get_current_blog_id() . '";' . "\n";
        echo '&lt;/script&gt;' . "\n";
        echo '&lt;script src="' . home_url('/wp-content/plugins/my-ad-materials/js/ad-inline.js') . '" ';
        echo 'data-api-url="' . home_url('/wp-json/custom/v1/ads') . '" ';
        echo 'data-width="300px" ';
        echo 'data-height="250px"&gt;&lt;/script&gt;';
        echo '</textarea>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Материал</th>';
        echo '<th>Сайт</th>';
        echo '<th>Показы</th>';
        echo '<th>Клики</th>';
        echo '<th>CTR (%)</th>';
        echo '<th>Действия</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($stats as $stat) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($stat->title) . '</strong></td>';
            echo '<td>' . esc_html($stat->site_name) . '</td>';
            echo '<td>' . intval($stat->impressions) . '</td>';
            echo '<td>' . intval($stat->clicks) . '</td>';
            echo '<td>' . floatval($stat->ctr) . '%</td>';
            echo '<td>';
            if ($stat->ad_id > 0) {
                echo '<a href="' . get_edit_post_link($stat->ad_id) . '">Редактировать</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '</div>';
}

// Добавление колонок в список рекламных материалов
function add_ad_material_columns($columns) {
    $columns['ad_category'] = 'Категория';
    $columns['ad_link'] = 'Ссылка';
    $columns['ad_sites'] = 'Доступные сайты';
    $columns['ad_stats'] = 'Статистика (30 дней)';
    return $columns;
}
add_filter('manage_ad_material_posts_columns', 'add_ad_material_columns');

function fill_ad_material_columns($column, $post_id) {
    switch ($column) {
        case 'ad_category':
            $category = get_post_meta($post_id, 'ad_category', true);
            $categories = array(
                'general' => 'Общая',
                'relationships' => 'Отношения',
                'health' => 'Здоровье',
                'lifestyle' => 'Образ жизни',
                'adult' => '18+'
            );
            echo $category ? esc_html($categories[$category] ?? ucfirst($category)) : 'Не указана';
            break;
        case 'ad_link':
            $link = get_post_meta($post_id, 'ad_link', true);
            if ($link) {
                $short_link = strlen($link) > 30 ? substr($link, 0, 30) . '...' : $link;
                echo '<a href="' . esc_url($link) . '" target="_blank" title="' . esc_attr($link) . '">' . esc_html($short_link) . '</a>';
            } else {
                echo '<span style="color: red;">Не указана</span>';
            }
            break;
        case 'ad_sites':
            $allowed_sites = get_post_meta($post_id, 'ad_allowed_sites', true) ?: [];
            if (empty($allowed_sites)) {
                echo '<span style="color: #4CAF50;">Все сайты</span>';
            } else {
                $site_names = [];
                foreach ($allowed_sites as $site_id) {
                    $site = get_post($site_id);
                    if ($site) {
                        $site_names[] = esc_html($site->post_title);
                    }
                }
                echo implode(', ', $site_names);
            }
            break;
        case 'ad_stats':
            global $wpdb;
            $table_name = $wpdb->prefix . 'ad_analytics';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $stats = $wpdb->get_row($wpdb->prepare("
                    SELECT 
                        COUNT(CASE WHEN action = 'impression' THEN 1 END) as impressions,
                        COUNT(CASE WHEN action = 'click' THEN 1 END) as clicks
                    FROM $table_name 
                    WHERE ad_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ", $post_id));
                
                if ($stats) {
                    echo '👁️ ' . intval($stats->impressions) . '<br>';
                    echo '🖱️ ' . intval($stats->clicks);
                    if ($stats->impressions > 0) {
                        $ctr = round(($stats->clicks / $stats->impressions) * 100, 2);
                        echo '<br><small>CTR: ' . $ctr . '%</small>';
                    }
                } else {
                    echo 'Нет данных';
                }
            } else {
                echo 'Таблица не создана';
            }
            break;
    }
}
add_action('manage_ad_material_posts_custom_column', 'fill_ad_material_columns', 10, 2);

// Добавление колонок для сайтов рекламы
function add_ad_site_columns($columns) {
    return array(
        'cb' => $columns['cb'],
        'title' => 'Название',
        'site_id' => 'ID сайта',
        'site_domain' => 'Домен',
        'date' => 'Дата'
    );
}
add_filter('manage_ad_site_posts_columns', 'add_ad_site_columns');

function fill_ad_site_columns($column, $post_id) {
    switch ($column) {
        case 'site_id':
            echo $post_id;
            break;
        case 'site_domain':
            $domain = get_post_meta($post_id, 'ad_site_domain', true);
            echo $domain ? '<a href="'.esc_url($domain).'" target="_blank">'.esc_html($domain).'</a>' : '—';
            break;
    }
}
add_action('manage_ad_site_posts_custom_column', 'fill_ad_site_columns', 10, 2);

// Добавление CSS для админки
function ad_material_admin_styles() {
    global $post_type;
    if ($post_type == 'ad_material') {
        echo '<style>
            .wp-list-table .column-ad_stats { width: 120px; }
            .wp-list-table .column-ad_category { width: 100px; }
            .wp-list-table .column-ad_link { width: 200px; }
            .wp-list-table .column-ad_sites { width: 200px; }
        </style>';
    } elseif ($post_type == 'ad_site') {
        echo '<style>
            .wp-list-table .column-site_id { width: 100px; }
            .wp-list-table .column-site_domain { width: 300px; }
        </style>';
    }
}
add_action('admin_head', 'ad_material_admin_styles');

//---------------------------------------------
// Хуки активации / деактивации / удаления
//---------------------------------------------
register_activation_hook(__FILE__, 'mam_activate_plugin');
register_deactivation_hook(__FILE__, 'mam_deactivate_plugin');
register_uninstall_hook(__FILE__, 'mam_uninstall_plugin');

// Функция активации плагина
function mam_activate_plugin() {
    // Регистрируем типы записей
    register_ad_material_post_type();
    register_ad_site_post_type();
    
    // Создаем таблицу статистики
    create_analytics_table();
    
    // Сбрасываем правила ЧПУ
    flush_rewrite_rules();
    
    // Добавляем версию плагина
    add_option('mam_plugin_version', '1.2beta');
}

// Функция деактивации плагина
function mam_deactivate_plugin() {
    // Сбрасываем правила ЧПУ
    flush_rewrite_rules();
}

// Функция удаления плагина
function mam_uninstall_plugin() {
    global $wpdb;
    
    // Удаляем все рекламные материалы
    $posts = get_posts(array(
        'post_type' => 'ad_material',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ));
    
    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }
    
    // Удаляем все сайты рекламы
    $sites = get_posts(array(
        'post_type' => 'ad_site',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ));
    
    foreach ($sites as $site_id) {
        wp_delete_post($site_id, true);
    }
    
    // Удаляем таблицу статистики
    $table_name = $wpdb->prefix . 'ad_analytics';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Удаляем опции
    delete_option('mam_plugin_version');
    
    // Сбрасываем правила ЧПУ
    flush_rewrite_rules();
}

// Инициализация типов записей на хуке 'init'
add_action('init', function() {
    register_ad_material_post_type();
    register_ad_site_post_type();
});

// Проверка таблицы при загрузке плагина
add_action('plugins_loaded', 'maybe_create_analytics_table');