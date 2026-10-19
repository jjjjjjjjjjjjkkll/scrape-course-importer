<?php
/*
Plugin Name: Scrape Course Importer
Description: Import course data from your own site pages and export them to ZIP/JSON for local backup.
Version: 1.0.0
Author: Copilot
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sci_register_course_post_type');
function sci_register_course_post_type() {
    if (post_type_exists('course')) {
        return;
    }

    register_post_type('course', array(
        'labels' => array(
            'name' => __('Cursos', 'sci'),
            'singular_name' => __('Curso', 'sci'),
            'add_new_item' => __('Adicionar novo curso', 'sci'),
            'edit_item' => __('Editar curso', 'sci'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'menu_icon' => 'dashicons-welcome-learn-more',
        'show_in_rest' => true,
    ));

    if (!taxonomy_exists('course_category')) {
        register_taxonomy('course_category', array('course'), array(
            'labels' => array(
                'name' => __('Categorias de Cursos', 'sci'),
                'singular_name' => __('Categoria', 'sci'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
        ));
    }
}

add_action('admin_menu', 'sci_register_admin_menu');
function sci_register_admin_menu() {
    add_menu_page(
        __('Importador & Exportador de Cursos', 'sci'),
        __('Cursos', 'sci'),
        'manage_options',
        'sci-course-importer',
        'sci_render_admin_page',
        'dashicons-upload',
        26
    );
}

function sci_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    if (!empty($_GET['sci_status'])) {
        $status = sanitize_text_field(wp_unslash($_GET['sci_status']));
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html($status) . '</p></div>';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Importador & Exportador de Cursos', 'sci'); ?></h1>
        <?php echo $notice; ?>

        <h2><?php esc_html_e('Importar cursos', 'sci'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sci_import_courses_nonce', 'sci_import_courses_nonce'); ?>
            <input type="hidden" name="action" value="sci_import_courses">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="sci_source_url"><?php esc_html_e('URL da categoria', 'sci'); ?></label></th>
                    <td>
                        <input type="url" id="sci_source_url" name="sci_source_url" class="regular-text" placeholder="https://seu-site.org/categoria/" />
                        <p class="description"><?php esc_html_e('URL pública da categoria com os cursos.', 'sci'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_item_selector">Item selector</label></th>
                    <td>
                        <input type="text" id="sci_item_selector" name="item_selector" class="regular-text" value="article, .post, .course-item" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_title_selector">Title selector</label></th>
                    <td>
                        <input type="text" id="sci_title_selector" name="title_selector" class="regular-text" value="h2 a, .entry-title a, .title a" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_excerpt_selector">Excerpt selector</label></th>
                    <td>
                        <input type="text" id="sci_excerpt_selector" name="excerpt_selector" class="regular-text" value=".excerpt, .entry-summary, p" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_image_selector">Image selector</label></th>
                    <td>
                        <input type="text" id="sci_image_selector" name="image_selector" class="regular-text" value="img, .thumb img, .post-thumbnail img" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_next_selector">Next page selector</label></th>
                    <td>
                        <input type="text" id="sci_next_selector" name="next_page_selector" class="regular-text" value=".next a, .pagination a.next, .nav-next a" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_batch">Batch</label></th>
                    <td>
                        <input type="number" id="sci_batch" name="batch" min="1" max="200" value="20" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_throttle">Throttle (ms)</label></th>
                    <td>
                        <input type="number" id="sci_throttle" name="throttle" min="0" max="10000" value="500" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sci_dry_run">Dry run</label></th>
                    <td>
                        <label><input type="checkbox" id="sci_dry_run" name="dry_run" value="1" /> <?php esc_html_e('Executar sem importar de fato', 'sci'); ?></label>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Executar importação', 'sci')); ?>
        </form>

        <hr />

        <h2><?php esc_html_e('Exportar para ZIP', 'sci'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sci_export_zip_nonce', 'sci_export_zip_nonce'); ?>
            <input type="hidden" name="action" value="sci_export_courses_zip">
            <?php submit_button(__('Exportar cursos em ZIP', 'sci')); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_sci_import_courses', 'sci_handle_import');
function sci_handle_import() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'sci'));
    }

    check_admin_referer('sci_import_courses_nonce', 'sci_import_courses_nonce');

    $source_url = isset($_POST['sci_source_url']) ? esc_url_raw(wp_unslash($_POST['sci_source_url'])) : '';
    if (empty($source_url)) {
        wp_safe_redirect(add_query_arg('sci_status', urlencode(__('Informe a URL da categoria.', 'sci')), admin_url('admin.php?page=sci-course-importer')));
        exit;
    }

    $result = sci_import_from_category(array(
        'start_url' => $source_url,
        'item_selector' => isset($_POST['item_selector']) ? sanitize_text_field(wp_unslash($_POST['item_selector'])) : 'article, .post, .course-item',
        'title_selector' => isset($_POST['title_selector']) ? sanitize_text_field(wp_unslash($_POST['title_selector'])) : 'h2 a, .entry-title a, .title a',
        'excerpt_selector' => isset($_POST['excerpt_selector']) ? sanitize_text_field(wp_unslash($_POST['excerpt_selector'])) : '.excerpt, .entry-summary, p',
        'image_selector' => isset($_POST['image_selector']) ? sanitize_text_field(wp_unslash($_POST['image_selector'])) : 'img, .thumb img, .post-thumbnail img',
        'next_selector' => isset($_POST['next_page_selector']) ? sanitize_text_field(wp_unslash($_POST['next_page_selector'])) : '.next a, .pagination a.next, .nav-next a',
        'batch' => isset($_POST['batch']) ? intval($_POST['batch']) : 20,
        'throttle' => isset($_POST['throttle']) ? intval($_POST['throttle']) : 500,
        'dry_run' => !empty($_POST['dry_run']),
    ));

    $status = sprintf(__('Importação concluída: %d importados, %d ignorados, %d erros.', 'sci'), $result['imported'], $result['skipped'], $result['errors']);
    wp_safe_redirect(add_query_arg('sci_status', urlencode($status), admin_url('admin.php?page=sci-course-importer')));
    exit;
}

add_action('admin_post_sci_export_courses_zip', 'sci_handle_export_zip');
function sci_handle_export_zip() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'sci'));
    }

    check_admin_referer('sci_export_zip_nonce', 'sci_export_zip_nonce');

    $export_dir = wp_upload_dir()['basedir'] . '/sci-export';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }

    $query = new WP_Query(array(
        'post_type' => 'course',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ));

    $rows = array();
    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'full') : '';

            $rows[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'source_url' => get_post_meta($post->ID, '_sci_source_url', true),
                'image_url' => $image_url,
            );
        }
    }

    $json_path = $export_dir . '/courses.json';
    file_put_contents($json_path, wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $zip_path = $export_dir . '/courses-export.zip';
    $zip = new ZipArchive();
    $zip_created = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zip_created === true) {
        $zip->addFile($json_path, 'courses.json');
        $zip->close();
    }

    $download_url = wp_upload_dir()['baseurl'] . '/sci-export/courses-export.zip';
    wp_safe_redirect(add_query_arg('sci_status', urlencode(__('ZIP exportado: ' . $download_url, 'sci')), admin_url('admin.php?page=sci-course-importer')));
    exit;
}

function sci_import_from_category($opts = array()) {
    $defaults = array(
        'start_url' => '',
        'item_selector' => 'article, .post, .course-item',
        'title_selector' => 'h2 a, .entry-title a, .title a',
        'excerpt_selector' => '.excerpt, .entry-summary, p',
        'image_selector' => 'img, .thumb img, .post-thumbnail img',
        'next_selector' => '.next a, .pagination a.next, .nav-next a',
        'batch' => 20,
        'throttle' => 500,
        'dry_run' => true,
    );

    $o = wp_parse_args($opts, $defaults);
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $visited = array();

    $url = $o['start_url'];
    $count = 0;

    while (!empty($url) && $count < $o['batch']) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array('User-Agent' => 'ScrapeCourseImporter/1.0'),
        ));

        if (is_wp_error($response)) {
            $errors++;
            break;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $errors++;
            break;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $body);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = $xpath->query(sci_css_to_xpath($o['item_selector']));

        if ($items && $items->length > 0) {
            foreach ($items as $item) {
                if ($count >= $o['batch']) {
                    break 2;
                }

                $title = sci_extract_text($xpath, $item, $o['title_selector']);
                if (empty($title)) {
                    $skipped++;
                    $count++;
                    continue;
                }

                $link = sci_extract_attr($xpath, $item, $o['title_selector'], 'href');
                if (empty($link)) {
                    $link = sci_extract_attr($xpath, $item, 'a', 'href');
                }

                $excerpt = sci_extract_html($xpath, $item, $o['excerpt_selector']);
                $image = sci_extract_attr($xpath, $item, $o['image_selector'], 'src');
                if (empty($image)) {
                    $image = sci_extract_attr($xpath, $item, $o['image_selector'], 'data-src');
                }

                $link = !empty($link) ? sci_make_absolute_url($link, $url) : $link;
                $image = !empty($image) ? sci_make_absolute_url($image, $url) : $image;

                if (sci_find_existing_course_by_url($link) || sci_find_existing_course_by_title($title)) {
                    $skipped++;
                    $count++;
                    continue;
                }

                if ($o['dry_run']) {
                    $imported++;
                    $count++;
                    continue;
                }

                $post_id = wp_insert_post(array(
                    'post_type' => 'course',
                    'post_status' => 'publish',
                    'post_title' => wp_strip_all_tags($title),
                    'post_content' => $excerpt,
                    'post_excerpt' => wp_trim_words(wp_strip_all_tags($excerpt), 20),
                ));

                if (is_wp_error($post_id) || !$post_id) {
                    $errors++;
                    $count++;
                    continue;
                }

                if (!empty($link)) {
                    update_post_meta($post_id, '_sci_source_url', esc_url_raw($link));
                }

                if (!empty($image)) {
                    $img_id = sci_download_and_attach_image($image, $post_id);
                    if ($img_id) {
                        set_post_thumbnail($post_id, $img_id);
                    }
                }

                $imported++;
                $count++;

                if ($o['throttle'] > 0) {
                    usleep(intval($o['throttle']) * 1000);
                }
            }
        }

        $next = sci_extract_next_page($xpath, $o['next_selector']);
        if (!empty($next)) {
            $next_url = sci_make_absolute_url($next, $url);
            if (in_array($next_url, $visited, true)) {
                break;
            }
            $visited[] = $url;
            $url = $next_url;
        } else {
            break;
        }
    }

    return array(
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
    );
}

function sci_find_existing_course_by_url($url) {
    if (empty($url)) {
        return false;
    }

    $query = new WP_Query(array(
        'post_type' => 'course',
        'meta_query' => array(
            array(
                'key' => '_sci_source_url',
                'value' => esc_url_raw($url),
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
        'fields' => 'ids',
    ));

    return !empty($query->posts);
}

function sci_find_existing_course_by_title($title) {
    if (empty($title)) {
        return false;
    }

    $query = new WP_Query(array(
        'post_type' => 'course',
        'title' => $title,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ));

    return !empty($query->posts);
}

function sci_extract_text($xpath, $context, $selector) {
    $query = sci_css_to_xpath($selector, true);
    if (empty($query)) {
        return trim($context->textContent);
    }

    $nodes = $xpath->query($query, $context);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    $parts = array();
    foreach ($nodes as $node) {
        $parts[] = trim($node->textContent);
    }

    return trim(implode(' ', array_filter($parts)));
}

function sci_extract_html($xpath, $context, $selector) {
    $query = sci_css_to_xpath($selector, true);
    if (empty($query)) {
        return '';
    }

    $nodes = $xpath->query($query, $context);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    $html = '';
    foreach ($nodes as $node) {
        $html .= $node->ownerDocument->saveHTML($node);
    }

    return $html;
}

function sci_extract_attr($xpath, $context, $selector, $attr) {
    $query = sci_css_to_xpath($selector, true);
    if (empty($query)) {
        return '';
    }

    $nodes = $xpath->query($query, $context);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    foreach ($nodes as $node) {
        if ($node->hasAttribute($attr)) {
            return $node->getAttribute($attr);
        }
    }

    return '';
}

function sci_extract_next_page($xpath, $selector) {
    $query = sci_css_to_xpath($selector);
    if (empty($query)) {
        return '';
    }

    $nodes = $xpath->query($query);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    $node = $nodes->item(0);
    if ($node && $node->hasAttribute('href')) {
        return $node->getAttribute('href');
    }

    return '';
}

function sci_css_to_xpath($selector, $relative = false) {
    if (empty($selector)) {
        return '';
    }

    $selector = trim($selector);
    $parts = preg_split('/\s*,\s*/', $selector);
    $selector = $parts[0];

    $tokens = preg_split('/\s+/', trim($selector));
    $xpaths = array();

    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }

        $tag = '*';
        $classes = array();
        $id = '';

        if (preg_match('/^([a-zA-Z0-9_-]+)?(?:\.([a-zA-Z0-9_-]+))?(?:#([a-zA-Z0-9_-]+))?$/', $token, $matches)) {
            if (!empty($matches[1])) {
                $tag = $matches[1];
            }
            if (!empty($matches[2])) {
                $classes[] = $matches[2];
            }
            if (!empty($matches[3])) {
                $id = $matches[3];
            }
        } else {
            if ($token[0] === '.') {
                $classes[] = substr($token, 1);
            } elseif ($token[0] === '#') {
                $id = substr($token, 1);
            } else {
                $tag = $token;
            }
        }

        $conditions = array();
        if (!empty($id)) {
            $conditions[] = "@id = '$id'";
        }
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
            }
        }

        $node = $tag;
        if (!empty($conditions)) {
            $node .= '[' . implode(' and ', $conditions) . ']';
        }
        $xpaths[] = $node;
    }

    $xpath = '//' . implode('/', $xpaths);
    if ($relative) {
        $xpath = '.' . substr($xpath, 1);
    }

    return $xpath;
}

function sci_make_absolute_url($url, $base) {
    if (empty($url)) {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        $scheme = wp_parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }

    $base_url = wp_parse_url($base);
    if (!$base_url) {
        return $url;
    }

    if (strpos($url, '/') === 0) {
        $scheme = !empty($base_url['scheme']) ? $base_url['scheme'] : 'https';
        $host = !empty($base_url['host']) ? $base_url['host'] : '';
        return $scheme . '://' . $host . $url;
    }

    $path = !empty($base_url['path']) ? $base_url['path'] : '/';
    $dir = rtrim(dirname($path), '/');
    $scheme = !empty($base_url['scheme']) ? $base_url['scheme'] : 'https';
    $host = !empty($base_url['host']) ? $base_url['host'] : '';
    return $scheme . '://' . $host . $dir . '/' . ltrim($url, '/');
}

function sci_download_and_attach_image($image_url, $post_id = 0) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return 0;
    }

    $file_array = array(
        'name' => basename(parse_url($image_url, PHP_URL_PATH)),
        'tmp_name' => $tmp,
    );

    $id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($id)) {
        @unlink($tmp);
        return 0;
    }

    return $id;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sci', function($args, $assoc_args) {
        if (empty($args) || $args[0] !== 'import') {
            WP_CLI::error('Use: wp sci import --url="https://example.com/categoria"');
            return;
        }

        $url = isset($assoc_args['url']) ? $assoc_args['url'] : '';
        if (empty($url)) {
            WP_CLI::error('Informe a URL: --url="https://example.com/categoria"');
            return;
        }

        $result = sci_import_from_category(array(
            'start_url' => $url,
            'item_selector' => !empty($assoc_args['item_selector']) ? $assoc_args['item_selector'] : 'article, .post, .course-item',
            'title_selector' => !empty($assoc_args['title_selector']) ? $assoc_args['title_selector'] : 'h2 a, .entry-title a, .title a',
            'excerpt_selector' => !empty($assoc_args['excerpt_selector']) ? $assoc_args['excerpt_selector'] : '.excerpt, .entry-summary, p',
            'image_selector' => !empty($assoc_args['image_selector']) ? $assoc_args['image_selector'] : 'img, .thumb img, .post-thumbnail img',
            'next_selector' => !empty($assoc_args['next_selector']) ? $assoc_args['next_selector'] : '.next a, .pagination a.next, .nav-next a',
            'batch' => !empty($assoc_args['batch']) ? intval($assoc_args['batch']) : 20,
            'throttle' => !empty($assoc_args['throttle']) ? intval($assoc_args['throttle']) : 500,
            'dry_run' => !empty($assoc_args['dry_run']),
        ));

        WP_CLI::success(sprintf('Import result: imported=%d skipped=%d errors=%d', $result['imported'], $result['skipped'], $result['errors']));
    });
}
