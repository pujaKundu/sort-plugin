<?php

class Sort_Plugin {

    public function run() {
        add_action('init', [$this, 'register_docs_post_type']);
        add_action('category_add_form_fields', [$this, 'add_category_order_field']);
        add_action('category_edit_form_fields', [$this, 'edit_category_order_field']);
        add_action('created_category', [$this, 'save_category_order_field']);
        add_action('edited_category', [$this, 'save_category_order_field']);
        add_filter('get_terms', [$this, 'order_categories_by_order_field'], 10, 3);

        // Admin UI
        add_action('admin_menu', [$this, 'add_documentation_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_sort_scripts']);
        add_action('wp_ajax_save_docs_order', [$this, 'save_docs_order']);

        // Frontend
        add_filter('the_content', [$this, 'display_all_categories_with_docs']); 
    add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']); 
    }

    public function register_docs_post_type() {
        $labels = [
            'name' => 'Documentations',
            'singular_name' => 'Documentation',
            'menu_name' => 'Documentations',
            'name_admin_bar' => 'Documentation',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New',
            'new_item' => 'New Documentation',
            'edit_item' => 'Edit Documentation',
            'view_item' => 'View Documentation',
            'all_items' => 'Documentations',
            'search_items' => 'Search Documentations',
            'not_found' => 'No documentations found.',
            'not_found_in_trash' => 'No documentations found in Trash.',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'docs'],
            'show_in_rest' => true,
            'taxonomies' => ['category', 'post_tag'],
        ];

        register_post_type('docs', $args);
    }

    public function add_category_order_field() {
        ?>
        <div class="form-field">
            <label for="category_order"><?php _e('Category Order', 'textdomain'); ?></label>
            <input type="number" name="category_order" id="category_order" value="" min="0" />
        </div>
        <?php
    }

    public function edit_category_order_field($term) {
        $order = get_term_meta($term->term_id, 'category_order', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="category_order"><?php _e('Category Order', 'textdomain'); ?></label></th>
            <td>
                <input type="number" name="category_order" id="category_order" value="<?php echo esc_attr($order); ?>" min="0" />
            </td>
        </tr>
        <?php
    }

    public function save_category_order_field($term_id) {
        if (isset($_POST['category_order'])) {
            update_term_meta($term_id, 'category_order', intval($_POST['category_order']));
        }
    }

    public function order_categories_by_order_field($terms, $taxonomies, $args) {
        if (is_array($taxonomies) && in_array('category', $taxonomies) && !empty($args['orderby']) && $args['orderby'] === 'category_order') {
            usort($terms, function ($a, $b) {
                $order_a = (int) get_term_meta($a->term_id, 'category_order', true);
                $order_b = (int) get_term_meta($b->term_id, 'category_order', true);
                return $order_a - $order_b;
            });
        }
        return $terms;
    }

    public function add_documentation_page() {
        add_submenu_page(
            'edit.php?post_type=docs',
            'Documentation Sort',
            'Sort Docs',
            'manage_options',
            'documentation-sort',
            [$this, 'render_documentation_page']
        );
    }

    public function render_documentation_page() {
        $categories = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'category_order'
        ]);
        ?>
        <div class="wrap">
    <h1>Documentation Sort</h1>
    <div id="sortable-accordion">
        <?php foreach ($categories as $category): ?>
            <div class="accordion-group" data-id="<?php echo esc_attr($category->term_id); ?>">
                <h3><?php echo esc_html($category->name); ?></h3>
                <div>
                    <ul class="docs-sortable" data-category-id="<?php echo esc_attr($category->term_id); ?>">
                        <?php
                        $docs = get_posts([
                            'post_type' => 'docs',
                            'posts_per_page' => -1,
                            'post_status' => 'publish',
                            'orderby' => 'menu_order',
                            'order' => 'ASC',
                            'tax_query' => [[
                                'taxonomy' => 'category',
                                'field' => 'term_id',
                                'terms' => $category->term_id,
                            ]],
                        ]);
                        foreach ($docs as $doc): ?>
                            <li data-id="<?php echo esc_attr($doc->ID); ?>" style="margin:5px;padding:5px;border:1px solid #ccc;background:#fff;">
                                <?php echo esc_html($doc->post_title); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button id="save-docs-order" class="button button-primary" style="margin-top:15px;">Save Order</button>
    <div id="save-message" style="margin-top:10px;"></div>
</div>
        <?php
    }

    public function enqueue_sort_scripts($hook) {
        if ($hook !== 'docs_page_documentation-sort') return;

        wp_enqueue_script('jquery-ui-accordion');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('docs-sort-js', plugin_dir_url(__FILE__) . '../assets/js/docs-sort.js', ['jquery', 'jquery-ui-sortable', 'jquery-ui-accordion'], null, true);
        wp_localize_script('docs-sort-js', 'docsSortAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('docs_sort_nonce')
        ]);
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }

    public function save_docs_order() {
        if (!current_user_can('manage_options') || !check_ajax_referer('docs_sort_nonce', 'nonce', false)) {
            wp_send_json_error('Unauthorized');
        }

        $orders = $_POST['orders'] ?? [];
        foreach ($orders as $group) {
            $docs = $group['docs'] ?? [];
            foreach ($docs as $index => $doc_id) {
                wp_update_post([
                    'ID' => intval($doc_id),
                    'menu_order' => $index,
                ]);
            }
        }

        $sortedCategories = $_POST['sortedCategories'] ?? [];
        foreach ($sortedCategories as $index => $term_id) {
            update_term_meta(intval($term_id), 'category_order', $index);
        }

        wp_send_json_success('Order saved successfully');
    }

    public function display_all_categories_with_docs($content) {
    if (is_singular('docs') && in_the_loop() && is_main_query()) {
        $categories = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'orderby'    => 'meta_value_num',
            'meta_key'   => 'category_order',
        ]);

        if (!empty($categories) && !is_wp_error($categories)) {
            $output = '<hr><h2>All Categories & Documentation</h2>';

            foreach ($categories as $category) {
                $output .= '<h3>' . esc_html($category->name) . '</h3>';

                $docs = new WP_Query([
                    'post_type'      => 'docs',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'category',
                            'field'    => 'term_id',
                            'terms'    => $category->term_id,
                        ],
                    ],
                ]);

                if ($docs->have_posts()) {
                    $output .= '<ul>';
                    while ($docs->have_posts()) {
                        $docs->the_post();
                        $output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
                    }
                    $output .= '</ul>';
                } else {
                    $output .= '<p>No docs in this category.</p>';
                }

                wp_reset_postdata();
            }

            $content .= $output;
        }
    }

    return $content;
}

}
