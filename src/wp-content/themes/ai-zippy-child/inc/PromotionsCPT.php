<?php

namespace AiZippyChild\Inc;

defined('ABSPATH') || exit;

class PromotionsCPT
{
    public static function init()
    {
        add_action('init', [self::class, 'register_cpt']);
    }

    public static function register_cpt()
    {
        $labels = [
            'name'                  => _x('Promotions', 'Post Type General Name', 'ai-zippy'),
            'singular_name'         => _x('Promotion', 'Post Type Singular Name', 'ai-zippy'),
            'menu_name'             => __('Promotions', 'ai-zippy'),
            'name_admin_bar'        => __('Promotion', 'ai-zippy'),
            'archives'              => __('Promotion Archives', 'ai-zippy'),
            'attributes'            => __('Promotion Attributes', 'ai-zippy'),
            'parent_item_colon'     => __('Parent Promotion:', 'ai-zippy'),
            'all_items'             => __('All Promotions', 'ai-zippy'),
            'add_new_item'          => __('Add New Promotion', 'ai-zippy'),
            'add_new'               => __('Add New', 'ai-zippy'),
            'new_item'              => __('New Promotion', 'ai-zippy'),
            'edit_item'             => __('Edit Promotion', 'ai-zippy'),
            'update_item'           => __('Update Promotion', 'ai-zippy'),
            'view_item'             => __('View Promotion', 'ai-zippy'),
            'view_items'            => __('View Promotions', 'ai-zippy'),
            'search_items'          => __('Search Promotion', 'ai-zippy'),
            'not_found'             => __('Not found', 'ai-zippy'),
            'not_found_in_trash'    => __('Not found in Trash', 'ai-zippy'),
            'featured_image'        => __('Background Image', 'ai-zippy'),
            'set_featured_image'    => __('Set background image', 'ai-zippy'),
            'remove_featured_image' => __('Remove background image', 'ai-zippy'),
            'use_featured_image'    => __('Use as background image', 'ai-zippy'),
            'insert_into_item'      => __('Insert into promotion', 'ai-zippy'),
            'uploaded_to_this_item' => __('Uploaded to this promotion', 'ai-zippy'),
            'items_list'            => __('Promotions list', 'ai-zippy'),
            'items_list_navigation' => __('Promotions list navigation', 'ai-zippy'),
            'filter_items_list'     => __('Filter promotions list', 'ai-zippy'),
        ];
        $args = [
            'label'                 => __('Promotion', 'ai-zippy'),
            'description'           => __('Promotions for the homepage grid/slider', 'ai-zippy'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail', 'excerpt'],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-star-filled',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => true,
            'capability_type'       => 'page',
            'show_in_rest'          => true, // Important for Gutenberg
        ];
        register_post_type('promotion', $args);
    }
}
