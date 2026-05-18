<?php

namespace AiZippy\Api;

use WC_Product_Query;
use Zippy_Booking\Src\Services\Zippy_Booking_Helper;

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

		// Get products by menu
		register_rest_route('ai-zippy/v1', '/products/menu/(?P<menu_id>\d+)', [
			'methods' => 'GET',
			'callback' => [self::class, 'getProductsByMenu'],
			'permission_callback' => '__return_true',
			'args' => [
				'menu_id' => [
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric($param);
					},
				],
			],
		]);

		// Get categories by menu
		register_rest_route('ai-zippy/v1', '/categories/menu/(?P<menu_id>\d+)', [
			'methods' => 'GET',
			'callback' => [self::class, 'getCategoriesByMenu'],
			'permission_callback' => '__return_true',
		]);

		// Get sub-categories
		register_rest_route('ai-zippy/v1', '/categories/sub/(?P<parent_slug>[a-zA-Z0-9-_]+)', [
			'methods' => 'GET',
			'callback' => [self::class, 'getSubCategories'],
			'permission_callback' => '__return_true',
		]);

		// Get session info
	}

	/**
	 * Get most ordered products (by order count)
	 * 
	 * Params:
	 * - limit: Number of products (default: 4)
	 * - category: Filter by category ID or SLUG
	 * - stock_status: instock|outofstock (default: instock)
	 */
	public static function getMostOrdered(\WP_REST_Request $request)
	{
		global $wpdb;

		$limit = (int) $request->get_param('limit') ?: 4;
		$category_param = $request->get_param('category');
		$category_id = 0;

		if (is_numeric($category_param)) {
			$category_id = (int) $category_param;
		} elseif (!empty($category_param)) {
			$term = get_term_by('slug', $category_param, 'product_cat');
			if ($term) {
				$category_id = $term->term_id;
			}
		}

		$stock_status = $request->get_param('stock_status') ?: 'instock';
		$page = (int) $request->get_param('page') ?: 1;
		$per_page = (int) $request->get_param('per_page') ?: $limit;
		$offset = ($page - 1) * $per_page;
		$search = $request->get_param('search');

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

		// Search filter
		if (!empty($search)) {
			$query .= $wpdb->prepare(" AND p.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
		}

		// Filter by category
		if ($category_id > 0) {
			$category_ids = self::getCategoryIdsWithChildren($category_id);
			$placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
			$query .= $wpdb->prepare(
				" AND p.ID IN (
					SELECT tr.object_id 
					FROM {$wpdb->prefix}term_relationships tr
					JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id IN ($placeholders)
				)",
				$category_ids
			);
		}

		// Filter by disabled products (Menus)
		if (class_exists('\Zippy_Booking\Src\Services\Zippy_Booking_Helper')) {
			$disabled_ids = \Zippy_Booking\Src\Services\Zippy_Booking_Helper::handle_check_disabled_products();
			if (!empty($disabled_ids)) {
				$placeholders = implode(',', array_fill(0, count($disabled_ids), '%d'));
				$query .= $wpdb->prepare(" AND p.ID NOT IN ($placeholders)", $disabled_ids);
			}
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
			if (!$wc_product)
				continue;

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

	public static function getCategories(\WP_REST_Request $request)
	{
		global $wpdb;

		$menus = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}zippy_menus WHERE status = 1 ORDER BY id ASC");

		if (empty($menus)) {
			return new \WP_REST_Response([], 200);
		}

		$formatted = array_map(function ($menu) {
			return [
				'id' => (int) $menu->id,
				'name' => sanitize_text_field($menu->name),
				'slug' => sanitize_title($menu->name),
				'count' => 0,
				'image' => wc_placeholder_img_src(),
			];
		}, $menus);

		return new \WP_REST_Response(array_values($formatted), 200);
	}

	public static function getProductsByMenu(\WP_REST_Request $request)
	{
		global $wpdb;

		$menu_id = (int) $request->get_param('menu_id');
		$category_id = (int) $request->get_param('category');
		$limit = (int) $request->get_param('limit') ?: 4;
		$page = (int) $request->get_param('page') ?: 1;
		$per_page = (int) $request->get_param('per_page') ?: $limit;
		$offset = ($page - 1) * $per_page;
		$search = $request->get_param('search');
		// Calculate the target delivery date for this menu
		$menu = $wpdb->get_row($wpdb->prepare("SELECT days_of_week FROM {$wpdb->prefix}zippy_menus WHERE id = %d", $menu_id));
		$target_date = current_time('Y-m-d');

		if ($menu && !empty($menu->days_of_week)) {
			$days = json_decode($menu->days_of_week, true);

			// If the menu is marked as not available, return empty products
			if (!empty($days) && isset($days[0]['is_available']) && (int)$days[0]['is_available'] === 0) {
				return new \WP_REST_Response([
					'products' => [],
					'total' => 0,
					'pages' => 0,
					'message' => 'Menu is currently unavailable'
				], 200);
			}

			if (!empty($days) && isset($days[0]['weekday'])) {
				$menu_weekday = (int) $days[0]['weekday']; // 1 (Mon) - 7 (Sun)
				$current_weekday = (int) current_time('N');

				$days_until = ($menu_weekday - $current_weekday + 7) % 7;
				$target_date = date('Y-m-d', strtotime("+$days_until days", strtotime(current_time('Y-m-d'))));
			}
		}

		// Query products in menu
		$query = "
			SELECT 
				p.ID,
				p.post_title as name,
				p.post_content as description,
				pm.meta_value as price
			FROM {$wpdb->prefix}posts p
			JOIN {$wpdb->prefix}zippy_menu_products zmp ON p.ID = zmp.id_product
			LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_regular_price'
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND zmp.id_menu = %d
			AND zmp.status = 1
			AND zmp.from_date IS NOT NULL AND zmp.from_date <= %s
			AND zmp.to_date IS NOT NULL AND zmp.to_date >= %s
		";

		$params = [$menu_id, $target_date, $target_date];

		// Category filter
		if ($category_id > 0) {
			$category_ids = self::getCategoryIdsWithChildren($category_id);
			$placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
			$query .= " AND p.ID IN (
				SELECT tr.object_id 
				FROM {$wpdb->prefix}term_relationships tr
				JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.term_id IN ($placeholders)
			)";
			$params = array_merge($params, $category_ids);
		}

		// Search filter
		if (!empty($search)) {
			$query .= " AND p.post_title LIKE %s";
			$params[] = '%' . $wpdb->esc_like($search) . '%';
		}

		// Filter by disabled products (Menus)
		if (class_exists('Zippy_Booking_Helper')) {
			$disabled_ids = \Zippy_Booking\Src\Services\Zippy_Booking_Helper::handle_check_disabled_products($target_date);
			if (!empty($disabled_ids)) {
				$placeholders_not_in = implode(',', array_fill(0, count($disabled_ids), '%d'));
				$query .= " AND p.ID NOT IN ($placeholders_not_in)";
				$params = array_merge($params, $disabled_ids);
			}
		}

		$query .= " ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;



		$results = $wpdb->get_results($wpdb->prepare($query, ...$params));

		if (empty($results)) {
			return new \WP_REST_Response([], 200);
		}

		$products = [];
		foreach ($results as $product) {
			$wc_product = wc_get_product($product->ID);
			if (!$wc_product)
				continue;

			$products[] = [
				'id' => (int) $product->ID,
				'name' => sanitize_text_field($product->name),
				'price' => floatval($product->price) ?: 0,
				'regular_price' => floatval($wc_product->get_regular_price()) ?: 0,
				'sale_price' => floatval($wc_product->get_sale_price()),
				'description' => wp_trim_words($product->description, 20),
				'image' => self::getProductImage($product->ID),
				'url' => get_permalink($product->ID),
				'menu_id' => $menu_id,
			];
		}

		return new \WP_REST_Response($products, 200);
	}

	public static function getCategoriesByMenu(\WP_REST_Request $request)
	{
		global $wpdb;

		$menu_id = (int) $request->get_param('menu_id');
		// Calculate the target delivery date for this menu
		$menu = $wpdb->get_row($wpdb->prepare("SELECT days_of_week FROM {$wpdb->prefix}zippy_menus WHERE id = %d", $menu_id));
		$target_date = current_time('Y-m-d');

		if ($menu && !empty($menu->days_of_week)) {
			$days = json_decode($menu->days_of_week, true);
			if (!empty($days) && isset($days[0]['weekday'])) {
				$menu_weekday = (int) $days[0]['weekday'];
				$current_weekday = (int) current_time('N');
				$days_until = ($menu_weekday - $current_weekday + 7) % 7;
				$target_date = date('Y-m-d', strtotime("+$days_until days", strtotime(current_time('Y-m-d'))));
			}
		}

		// Query to get categories that have products in this menu on this day
		$query = $wpdb->prepare(
			"
			SELECT DISTINCT t.term_id as id, t.name, t.slug
			FROM {$wpdb->prefix}terms t
			JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
			JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			JOIN {$wpdb->prefix}zippy_menu_products zmp ON tr.object_id = zmp.id_product
			JOIN {$wpdb->prefix}posts p ON p.ID = zmp.id_product
			WHERE tt.taxonomy = 'product_cat'
			AND p.post_type = 'product'
			AND p.post_status = 'publish'
			AND zmp.id_menu = %d
			AND zmp.status = 1
			AND zmp.from_date IS NOT NULL AND zmp.from_date <= %s
			AND zmp.to_date IS NOT NULL AND zmp.to_date >= %s
			",
			$menu_id,
			$target_date,
			$target_date
		);

		// Filter by disabled products
		if (class_exists('Zippy_Booking_Helper')) {
			$disabled_ids = \Zippy_Booking\Src\Services\Zippy_Booking_Helper::handle_check_disabled_products($target_date);
			if (!empty($disabled_ids)) {
				$placeholders_not_in = implode(',', array_fill(0, count($disabled_ids), '%d'));
				$query .= $wpdb->prepare(" AND p.ID NOT IN ($placeholders_not_in)", $disabled_ids);
			}
		}

		$query .= " ORDER BY t.name ASC";

		$results = $wpdb->get_results($query);

		// Format results
		$categories = array_map(function ($cat) {
			return [
				'id' => (int) $cat->id,
				'name' => sanitize_text_field($cat->name),
				'slug' => $cat->slug,
			];
		}, $results);

		return new \WP_REST_Response($categories, 200);
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

	public static function getSubCategories(\WP_REST_Request $request)
	{
		$parent_slug = $request->get_param('parent_slug');
		if (empty($parent_slug)) {
			return new \WP_REST_Response([], 200);
		}

		$parent_term = get_term_by('slug', $parent_slug, 'product_cat');
		if (!$parent_term) {
			return new \WP_REST_Response([], 200);
		}

		$children = get_terms([
			'taxonomy' => 'product_cat',
			'parent' => $parent_term->term_id,
			'hide_empty' => false,
		]);

		if (is_wp_error($children) || empty($children)) {
			return new \WP_REST_Response([], 200);
		}

		$categories = array_map(function ($cat) {
			return [
				'id' => (int) $cat->term_id,
				'name' => sanitize_text_field($cat->name),
				'slug' => $cat->slug,
				'image' => self::getCategoryImage($cat->term_id),
			];
		}, $children);

		return new \WP_REST_Response($categories, 200);
	}

	/**
	 * Get current session info
	 */
	public static function getSessionInfo(\WP_REST_Request $request)
	{
		if (class_exists('\Zippy_Booking\Utils\Zippy_Session_Handler')) {
			$session = new \Zippy_Booking\Utils\Zippy_Session_Handler();
			return new \WP_REST_Response([
				'date' => $session->get('date'),
				'order_mode' => $session->get('order_mode'),
			], 200);
		}

		return new \WP_REST_Response([
			'date' => null,
			'order_mode' => null,
		], 200);
	}
}
