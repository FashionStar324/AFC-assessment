<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Partner_CPT {

	public static function register(): void {
		self::register_post_type();
		self::register_taxonomy();
	}

	private static function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Partners', 'post type general name', 'partner-directory' ),
			'singular_name'         => _x( 'Partner', 'post type singular name', 'partner-directory' ),
			'menu_name'             => _x( 'Partners', 'admin menu', 'partner-directory' ),
			'add_new'               => __( 'Add New', 'partner-directory' ),
			'add_new_item'          => __( 'Add New Partner', 'partner-directory' ),
			'edit_item'             => __( 'Edit Partner', 'partner-directory' ),
			'new_item'              => __( 'New Partner', 'partner-directory' ),
			'view_item'             => __( 'View Partner', 'partner-directory' ),
			'search_items'          => __( 'Search Partners', 'partner-directory' ),
			'not_found'             => __( 'No partners found.', 'partner-directory' ),
			'not_found_in_trash'    => __( 'No partners found in Trash.', 'partner-directory' ),
			'all_items'             => __( 'All Partners', 'partner-directory' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'partners' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-building',
			'supports'            => [ 'title', 'thumbnail', 'revisions' ],
		];

		register_post_type( 'partner', $args );
	}

	private static function register_taxonomy(): void {
		$labels = [
			'name'              => _x( 'Partner Categories', 'taxonomy general name', 'partner-directory' ),
			'singular_name'     => _x( 'Partner Category', 'taxonomy singular name', 'partner-directory' ),
			'search_items'      => __( 'Search Partner Categories', 'partner-directory' ),
			'all_items'         => __( 'All Partner Categories', 'partner-directory' ),
			'edit_item'         => __( 'Edit Partner Category', 'partner-directory' ),
			'update_item'       => __( 'Update Partner Category', 'partner-directory' ),
			'add_new_item'      => __( 'Add New Partner Category', 'partner-directory' ),
			'new_item_name'     => __( 'New Partner Category Name', 'partner-directory' ),
			'menu_name'         => __( 'Categories', 'partner-directory' ),
			'not_found'         => __( 'No partner categories found.', 'partner-directory' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'partner-category' ],
			'query_var'         => true,
		];

		register_taxonomy( 'partner_category', 'partner', $args );
	}
}
