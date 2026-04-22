<?php

/**
 * AI Zippy Child Theme Functions
 *
 * Add project-specific customizations here.
 * The parent theme (ai-zippy) handles Vite assets and core setup.
 */

defined('ABSPATH') || exit;

// Include child theme classes
require_once get_stylesheet_directory() . '/inc/BlockRegistration.php';

// Register child theme blocks
\AiZippyChild\BlockRegistration::register();

/**
 * Enqueue child theme styles after parent.
 * Child theme SCSS is built by Vite into parent theme dist/css/child-style.css
 */
function ai_zippy_child_enqueue_assets(): void
{
    // Child theme custom styles (outputs to parent theme dist as child-style.css)
    $child_css = get_template_directory() . '/assets/dist/css/child-style.css';

    if (file_exists($child_css)) {
        wp_enqueue_style(
            'ai-zippy-child-style',
            get_template_directory_uri() . '/assets/dist/css/child-style.css',
            ['ai-zippy-theme-css-0'],
            filemtime($child_css)
        );
    }

    // About Us page styles (Load for About Us and About slugs)
    if (is_page('about-us') || is_page('about')) {
        $about_css_path = get_template_directory() . '/assets/dist/css/child-about-us.css';
        if (file_exists($about_css_path)) {
            wp_enqueue_style(
                'ai-zippy-about-us-style',
                get_template_directory_uri() . '/assets/dist/css/child-about-us.css',
                ['ai-zippy-child-style'],
                filemtime($about_css_path)
            );
        }
    }

    // Contact page specific styles
    if (is_page('contact-us') || is_page('contact')) {
        $contact_css_path = get_template_directory() . '/assets/dist/css/child-contact.css';
        if (file_exists($contact_css_path)) {
            wp_enqueue_style(
                'ai-zippy-contact-style',
                get_template_directory_uri() . '/assets/dist/css/child-contact.css',
                ['ai-zippy-child-style'],
                filemtime($contact_css_path)
            );
        }
    }

    // Blog page specific styles
    if (is_page('blog') || is_home()) {
        $blog_css = get_template_directory() . '/assets/dist/css/child-blog.css';
        if (file_exists($blog_css)) {
            wp_enqueue_style(
                'ai-zippy-blog-style',
                get_template_directory_uri() . '/assets/dist/css/child-blog.css',
                ['ai-zippy-child-style'],
                filemtime($blog_css)
            );
        }
    }
    // Account Modal assets
    $js_path = get_stylesheet_directory() . '/assets/js/account-modal.js';
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'ai-zippy-account-modal',
            get_stylesheet_directory_uri() . '/assets/js/account-modal.js',
            [],
            filemtime($js_path),
            true
        );

        wp_localize_script('ai-zippy-account-modal', 'ai_zippy_auth_obj', [
            'rest_url' => esc_url_raw(rest_url('ai-zippy/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'is_logged_in' => is_user_logged_in(),
            'my_account_url' => get_permalink(get_option('woocommerce_myaccount_page_id')) ?: home_url('/my-account/'),
        ]);
    }

    $css_path = get_stylesheet_directory() . '/assets/css/account-modal.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'ai-zippy-account-modal-style',
            get_stylesheet_directory_uri() . '/assets/css/account-modal.css',
            ['ai-zippy-child-style'],
            filemtime($css_path)
        );
    } else {
        // Fallback for CSS if the file doesn't exist yet but we want to enqueue it for later
        wp_enqueue_style(
            'ai-zippy-account-modal-style',
            get_stylesheet_directory_uri() . '/assets/css/account-modal.css',
            ['ai-zippy-child-style']
        );
    }
}
add_action('wp_enqueue_scripts', 'ai_zippy_child_enqueue_assets', 20);

/**
 * Register REST API Endpoints for Auth
 */
add_action('rest_api_init', function () {
    register_rest_route('ai-zippy/v1', '/login', [
        'methods' => 'POST',
        'callback' => 'ai_zippy_rest_login',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('ai-zippy/v1', '/register', [
        'methods' => 'POST',
        'callback' => 'ai_zippy_rest_register',
        'permission_callback' => '__return_true',
    ]);
});

function ai_zippy_rest_login($request)
{
    $creds = array();
    $creds['user_login'] = sanitize_text_field($request->get_param('username'));
    $creds['user_password'] = $request->get_param('password');
    $creds['remember'] = true;

    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        return new WP_Error('login_failed', $user->get_error_message(), ['status' => 403]);
    }

    return [
        'success' => true,
        'message' => __('Login successful!'),
    ];
}

function ai_zippy_rest_register($request)
{
    if (!class_exists('WooCommerce')) {
        return new WP_Error('no_woo', 'WooCommerce is not active', ['status' => 500]);
    }

    $email = sanitize_email($request->get_param('email'));
    $password = $request->get_param('password');
    $first_name = sanitize_text_field($request->get_param('first_name'));
    $last_name = sanitize_text_field($request->get_param('last_name'));

    // Use WooCommerce Registration Logic
    $customer_id = wc_create_new_customer($email, '', $password, [
        'first_name' => $first_name,
        'last_name' => $last_name,
    ]);

    if (is_wp_error($customer_id)) {
        return new WP_Error('registration_failed', $customer_id->get_error_message(), ['status' => 400]);
    }

    // Log the user in
    wp_set_current_user($customer_id);
    wp_set_auth_cookie($customer_id);

    return [
        'success' => true,
        'message' => __('Registration successful!'),
    ];
}
