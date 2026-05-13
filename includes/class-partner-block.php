<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Partner_Block {

	public static function register(): void {
		self::register_block();
	}

	public static function register_block(): void {
		$asset = require PARTNER_DIR_PATH . 'build/index.asset.php';

		wp_register_script(
			'partner-directory-block-editor',
			PARTNER_DIR_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		wp_register_style(
			'partner-directory-block-style',
			PARTNER_DIR_URL . 'build/style-index.css',
			[],
			PARTNER_DIR_VERSION
		);

		register_block_type(
			PARTNER_DIR_PATH . 'blocks/partner-grid/block.json',
			[
				'render_callback' => [ self::class, 'render' ],
			]
		);
	}

	public static function render( array $attributes ): string {
		$category = isset( $attributes['category'] ) ? sanitize_title( $attributes['category'] ) : '';
		$per_page = isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 12;
		$columns  = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
		$columns  = max( 1, min( 6, $columns ) );

		$query_args = [
			'post_type'      => 'partner',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
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

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<div class="partner-grid__empty">' .
				esc_html__( 'No partners found.', 'partner-directory' ) .
				'</div>';
		}

		$wrapper_attrs = get_block_wrapper_attributes( [
			'class' => 'partner-grid partner-grid--cols-' . $columns,
		] );

		$html = '<ul ' . $wrapper_attrs . '>';

		foreach ( $query->posts as $post ) {
			$html .= self::render_card( $post );
		}

		$html .= '</ul>';

		wp_reset_postdata();

		return $html;
	}

	private static function render_card( WP_Post $post ): string {
		$logo_id     = (int) get_post_meta( $post->ID, '_partner_logo_id', true );
		$website_url = get_post_meta( $post->ID, '_partner_website_url', true );
		$categories  = wp_get_post_terms( $post->ID, 'partner_category' );

		$logo_html = '';
		if ( $logo_id ) {
			$logo_html = wp_get_attachment_image(
				$logo_id,
				'medium',
				false,
				[
					'alt'     => esc_attr( $post->post_title ),
					'loading' => 'lazy',
				]
			);
		} else {
			$logo_html = '<div class="partner-card__logo--placeholder">' .
				esc_html__( 'No logo', 'partner-directory' ) .
				'</div>';
		}

		$cats_html = '';
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$cats_html = '<div class="partner-card__categories">';
			foreach ( $categories as $term ) {
				$cats_html .= '<span class="partner-card__category-tag">' .
					esc_html( $term->name ) .
					'</span>';
			}
			$cats_html .= '</div>';
		}

		$link_open  = '';
		$link_close = '';
		if ( ! empty( $website_url ) ) {
			$link_open  = '<a href="' . esc_url( $website_url ) . '" class="partner-card__link" target="_blank" rel="noopener noreferrer">';
			$link_close = '</a>';
		} else {
			$link_open  = '<div class="partner-card__link">';
			$link_close = '</div>';
		}

		return '<li class="partner-card">' .
			$link_open .
			'<div class="partner-card__logo">' . $logo_html . '</div>' .
			'<span class="partner-card__name">' . esc_html( $post->post_title ) . '</span>' .
			$cats_html .
			$link_close .
			'</li>';
	}
}
