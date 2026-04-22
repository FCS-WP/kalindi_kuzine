<?php

namespace AiZippy\Api;

use WC_Product_Query;

defined('ABSPATH') || exit;

/**
 * Most Ordered Products API
 * 
 * Fetches products sorted by order count
 * Endpoint: GET /wp-json/ai-zippy/v1/most-ordered
 */
class MostOrderedApi
{
	public static function register(): void
	{
		add_action('rest_api_init', [self::class, 'registerRoute']);
	}

	public static function registerRoute(): void
	{
		// Most ordered products
		register_rest_route('ai-zippy/v1', '/most-ordered', [
			'methods' => 'GET',
			'callback' => [self::class, 'getMostOrdered'],
			'permission_callback' => '__return_true',
		]);

		// Get all categories
		register_rest_route('ai-zippy/v1', '/categories', [
			'methods' => 'GET',
			'callback' => [self::class, 'getCategories'],
			'permission_callback' => '__return_true',
		]);

		// Get products by category
		register_rest_route('ai-zippy/v1', '/products/category/(?P<category_id>\d+)', [
			'methods' => 'GET',
			'callback' => [self::class, 'getProductsByCategory'],
			'permission_callback' => '__return_true',
			'args' => [
				'category_id' => [
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric($param);
					},
				],
			],
		]);
	}

	/**
	 * Get most ordered products (by order count)
	 * 
	 * Params:
	 * - limit: Number of products (default: 4)
	 * - category: Filter by category ID
	 * - stock_status: instock|outofstock (default: instock)
	 */
	public static function getMostOrdered(\WP_REST_Request $request)
	{
		global $wpdb;

		$limit = (int) $request->get_param('limit') ?: 4;
		$category = (int) $request->get_param('category') ?: 0;
		$stock_status = $request->get_param('stock_status') ?: 'instock';
		$page = (int) $request->get_param('page') ?: 1;
		$per_page = (int) $request->get_param('per_page') ?: $limit;

		$offset = ($page - 1) * $per_page;

		// Query to get products by order count
		$query = "
			SELECT 
				p.ID,
				p.post_title as name,
				p.post_content as description,
				pm.meta_value as price,
				(
					SELECT COUNT(DISTINCT oi.order_id)
					FROM {$wpdb->prefix}woocommerce_order_items oi
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
					WHERE oi.order_item_type = 'line_item'
					AND oim.meta_key = '_product_id'
					AND oim.meta_value = p.ID
					AND oi.order_item_id IN (
						SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta
						WHERE meta_key = '_qty'
					)
				) as order_count
			FROM {$wpdb->prefix}posts p
			LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_regular_price'
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
		";

		// Filter by category
		if ($category > 0) {
			$category_ids = self::getCategoryIdsWithChildren($category);
			$placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
			$query .= $wpdb->prepare(
				" AND p.ID IN (
					SELECT tr.object_id 
					FROM {$wpdb->prefix}term_relationships tr
					JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id IN ($placeholders)
				)",
				...$category_ids
			);
		}

		// Filter by stock status
		if ($stock_status === 'instock') {
			$query .= " AND p.ID IN (
				SELECT post_id FROM {$wpdb->prefix}postmeta
				WHERE meta_key = '_stock_status' AND meta_value = 'instock'
			)";
		}

		$query .= " ORDER BY order_count DESC LIMIT " . intval($per_page) . " OFFSET " . intval($offset);

		$results = $wpdb->get_results($query);

		if (empty($results)) {
			return new \WP_REST_Response([], 200);
		}

		// Format products for frontend
		$products = [];
		foreach ($results as $product) {
			$wc_product = wc_get_product($product->ID);
			if (!$wc_product) continue;

			$products[] = [
				'id' => (int) $product->ID,
				'name' => sanitize_text_field($product->name),
				'price' => floatval($product->price) ?: 0,
				'regular_price' => floatval($wc_product->get_regular_price()) ?: 0,
				'sale_price' => floatval($wc_product->get_sale_price()) ?: null,
				'description' => wp_trim_words($product->description, 20),
				'image' => self::getProductImage($product->ID),
				'order_count' => (int) $product->order_count,
				'url' => get_permalink($product->ID),
			];
		}

		return new \WP_REST_Response($products, 200);
	}

	private static function getProductImage($product_id): string
	{
		$image_id = get_post_thumbnail_id($product_id);

		if ($image_id) {
			$image = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
			return $image[0] ?? '';
		}

		return wc_placeholder_img_src();
	}

	/**
	 * Get all product categories
	 */
	public static function getCategories(\WP_REST_Request $request)
	{
		$categories = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => true,
			'parent' => 0,
			// 'number' => 10,
			'orderby' => 'name',
		]);

		if (is_wp_error($categories) || empty($categories)) {
			return new \WP_REST_Response([], 200);
		}

		$formatted = array_map(function ($cat) {
			return [
				'id' => (int) $cat->term_id,
				'name' => sanitize_text_field($cat->name),
				'slug' => sanitize_text_field($cat->slug),
				'count' => (int) $cat->count,
				'image' => self::getCategoryImage($cat->term_id),
			];
		}, $categories);

		return new \WP_REST_Response(array_values($formatted), 200);
	}

	/**
	 * Get products by category ID
	 */
	public static function getProductsByCategory(\WP_REST_Request $request)
	{
		global $wpdb;

		$category_id = (int) $request->get_param('category_id');
		$limit = (int) $request->get_param('limit') ?: 4;
		$page = (int) $request->get_param('page') ?: 1;
		$per_page = (int) $request->get_param('per_page') ?: $limit;

		$offset = ($page - 1) * $per_page;

		$category_ids = self::getCategoryIdsWithChildren($category_id);
		$placeholders = implode(',', array_fill(0, count($category_ids), '%d'));

		// Query products in category
		$query = $wpdb->prepare(
			"
			SELECT 
				p.ID,
				p.post_title as name,
				p.post_content as description,
				pm.meta_value as price
			FROM {$wpdb->prefix}posts p
			LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_regular_price'
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND p.ID IN (
				SELECT tr.object_id 
				FROM {$wpdb->prefix}term_relationships tr
				JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.term_id IN ($placeholders)
			)
			ORDER BY p.post_date DESC
			LIMIT %d OFFSET %d
			",
			...array_merge($category_ids, [$per_page, $offset])
		);

		$results = $wpdb->get_results($query);

		if (empty($results)) {
			return new \WP_REST_Response([], 200);
		}

		$products = [];
		foreach ($results as $product) {
			$wc_product = wc_get_product($product->ID);
			if (!$wc_product) continue;

			$products[] = [
				'id' => (int) $product->ID,
				'name' => sanitize_text_field($product->name),
				'price' => floatval($product->price) ?: 0,
				'regular_price' => floatval($wc_product->get_regular_price()) ?: 0,
				'sale_price' => floatval($wc_product->get_sale_price()),
				'description' => wp_trim_words($product->description, 20),
				'image' => self::getProductImage($product->ID),
				'url' => get_permalink($product->ID),
			];
		}

		return new \WP_REST_Response($products, 200);
	}

	private static function getCategoryImage(int $category_id): string
	{
		$thumbnail_id = get_term_meta($category_id, 'thumbnail_id', true);

		if ($thumbnail_id) {
			$image = wp_get_attachment_image_src($thumbnail_id, 'woocommerce_thumbnail');
			return $image[0] ?? '';
		}

		return wc_placeholder_img_src();
	}

	/**
	 * Get category IDs including children
	 */
	private static function getCategoryIdsWithChildren(int $category_id): array
	{
		$term_ids = [$category_id];
		$children = get_term_children($category_id, 'product_cat');

		if (!is_wp_error($children) && !empty($children)) {
			$term_ids = array_merge($term_ids, $children);
		}

		return array_unique(array_map('intval', $term_ids));
	}
}
