<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Partner_Meta {

	private const NONCE_ACTION = 'partner_meta_save';
	private const NONCE_FIELD  = 'partner_meta_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_partner', [ self::class, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_media' ] );
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'partner_details',
			__( 'Partner Details', 'partner-directory' ),
			[ self::class, 'render' ],
			'partner',
			'normal',
			'high'
		);
	}

	public static function enqueue_media( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'partner' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'partner-admin',
			PARTNER_DIR_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PARTNER_DIR_VERSION,
			true
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$logo_id     = (int) get_post_meta( $post->ID, '_partner_logo_id', true );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$website_url = get_post_meta( $post->ID, '_partner_website_url', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="partner_logo"><?php esc_html_e( 'Logo', 'partner-directory' ); ?></label>
				</th>
				<td>
					<div id="partner-logo-preview" style="margin-bottom:8px;">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:200px;max-height:200px;display:block;" alt="">
						<?php endif; ?>
					</div>
					<input type="hidden" id="partner_logo_id" name="partner_logo_id"
						value="<?php echo esc_attr( (string) $logo_id ); ?>">
					<button type="button" class="button" id="partner-logo-upload">
						<?php esc_html_e( 'Select / Upload Logo', 'partner-directory' ); ?>
					</button>
					<?php if ( $logo_id ) : ?>
						<button type="button" class="button" id="partner-logo-remove" style="margin-left:4px;">
							<?php esc_html_e( 'Remove', 'partner-directory' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="partner_website_url"><?php esc_html_e( 'Website URL', 'partner-directory' ); ?></label>
				</th>
				<td>
					<input type="url" id="partner_website_url" name="partner_website_url"
						value="<?php echo esc_attr( (string) $website_url ); ?>"
						class="regular-text"
						placeholder="https://example.com">
					<p class="description"><?php esc_html_e( 'Full URL including https://', 'partner-directory' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'partner' !== $post->post_type ) {
			return;
		}

		// Logo attachment ID — must be a valid attachment owned by the site.
		if ( isset( $_POST['partner_logo_id'] ) ) {
			$logo_id = absint( $_POST['partner_logo_id'] );

			if ( 0 === $logo_id ) {
				delete_post_meta( $post_id, '_partner_logo_id' );
			} elseif ( wp_attachment_is_image( $logo_id ) ) {
				update_post_meta( $post_id, '_partner_logo_id', $logo_id );
			}
		}

		// Website URL — validate it is a real URL before saving.
		if ( isset( $_POST['partner_website_url'] ) ) {
			$url = esc_url_raw( trim( wp_unslash( $_POST['partner_website_url'] ) ) );

			if ( '' === $url ) {
				delete_post_meta( $post_id, '_partner_website_url' );
			} elseif ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				update_post_meta( $post_id, '_partner_website_url', $url );
			}
		}
	}
}
