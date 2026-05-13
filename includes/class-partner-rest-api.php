<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Partner_REST_API {

	private const NAMESPACE      = 'custom/v1';
	private const ROUTE          = '/partners';
	private const CACHE_TTL      = 3600;        // 1 hour
	private const RATE_LIMIT_MAX = 60;           // requests per window
	private const RATE_LIMIT_TTL = 60;           // window in seconds

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_action( 'save_post_partner', [ self::class, 'bust_cache' ] );
		add_action( 'deleted_post', [ self::class, 'bust_cache' ] );
		add_action( 'edited_partner_category', [ self::class, 'bust_cache' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_partners' ],
				'permission_callback' => '__return_true',
				'args'                => self::get_collection_params(),
			]
		);
	}

	private static function get_collection_params(): array {
		return [
			'category' => [
				'description'       => __( 'Filter by partner category slug.', 'partner-directory' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'per_page' => [
				'description'       => __( 'Maximum number of results to return (1–100).', 'partner-directory' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'page' => [
				'description'       => __( 'Page number.', 'partner-directory' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	public static function get_partners( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate_check = self::check_rate_limit( $request );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$category = $request->get_param( 'category' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$cache_key = self::build_cache_key( $category, $per_page, $page );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached, 200 );
			$response->header( 'X-Partner-Cache', 'HIT' );
			return $response;
		}

		$query_args = [
			'post_type'      => 'partner',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'no_found_rows'  => false,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $category ) ) {
			$query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'partner_category',
					'field'    => 'slug',
					'terms'    => $category,
				],
			];
		}

		$query   = new WP_Query( $query_args );
		$partners = [];

		foreach ( $query->posts as $post ) {
			$partners[] = self::format_partner( $post );
		}

		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		$data = [
			'partners'    => $partners,
			'total'       => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];

		set_transient( $cache_key, $data, self::CACHE_TTL );

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-Partner-Cache', 'MISS' );
		return $response;
	}

	private static function format_partner( WP_Post $post ): array {
		$logo_id  = (int) get_post_meta( $post->ID, '_partner_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : null;

		$categories = wp_get_post_terms( $post->ID, 'partner_category', [ 'fields' => 'all' ] );
		$cats       = [];

		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $term ) {
				$cats[] = [
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				];
			}
		}

		return [
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'logo_url'    => $logo_url ?: null,
			'website_url' => get_post_meta( $post->ID, '_partner_website_url', true ) ?: null,
			'categories'  => $cats,
			'link'        => get_permalink( $post->ID ),
		];
	}

	private static function build_cache_key( ?string $category, int $per_page, int $page ): string {
		$parts = [ 'partner_api', $per_page, $page ];
		if ( ! empty( $category ) ) {
			$parts[] = 'cat_' . $category;
		}
		return implode( '_', $parts );
	}

	public static function bust_cache(): void {
		global $wpdb;
		// Delete all transients with our prefix pattern.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_partner_api' ) . '%',
				$wpdb->esc_like( '_transient_timeout_partner_api' ) . '%'
			)
		);
	}

	private static function check_rate_limit( WP_REST_Request $request ): true|WP_Error {
		$ip  = self::get_client_ip( $request );
		$key = 'partner_rl_' . md5( $ip );

		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'too_many_requests',
				__( 'Rate limit exceeded. Please wait before making more requests.', 'partner-directory' ),
				[ 'status' => 429 ]
			);
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, self::RATE_LIMIT_TTL );
		} else {
			set_transient( $key, $count + 1, self::RATE_LIMIT_TTL );
		}

		return true;
	}

	private static function get_client_ip( WP_REST_Request $request ): string {
		// Only trust proxy headers if explicitly configured to do so.
		if ( defined( 'PARTNER_DIR_TRUST_PROXY' ) && PARTNER_DIR_TRUST_PROXY ) {
			$forwarded = $request->get_header( 'x_forwarded_for' );
			if ( $forwarded ) {
				return trim( explode( ',', $forwarded )[0] );
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
	}
}
