<?php
/**
 * REST API: WP_REST_Font_Families_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.5.0
 */

if ( class_exists( 'WP_REST_Font_Families_Controller' ) ) {
	return;
}

/**
 * Font Families Controller class.
 *
 * @since 6.5.0
 */
class WP_REST_Font_Families_Controller extends WP_REST_Posts_Controller {
	/**
	 * Whether the controller supports batching.
	 *
	 * @since 6.5.0
	 * @var false
	 */
	protected $allow_batch = false;

	/**
	 * Checks if a given request has access to font families.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- required by parent class
		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->read ) ) {
			return new WP_Error(
				'rest_cannot_read',
				__( 'Sorry, you are not allowed to access font families.', 'gutenberg' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to a font family.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Validates settings when creating or updating a font family.
	 *
	 * @since 6.5.0
	 *
	 * @param string          $value   Encoded JSON string of font family settings.
	 * @param WP_REST_Request $request Request object.
	 * @return false|WP_Error True if the settings are valid, otherwise a WP_Error object.
	 */
	public function validate_font_family_settings( $value, $request ) {
		$settings = json_decode( $value, true );

		// Check settings string is valid JSON.
		if ( null === $settings ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'font_family_settings parameter must be a valid JSON string.', 'gutenberg' ),
				array( 'status' => 400 )
			);
		}

		$schema   = $this->get_item_schema()['properties']['font_family_settings'];
		$required = $schema['required'];

		if ( isset( $request['id'] ) ) {
			// Allow sending individual properties if we are updating an existing font family.
			unset( $schema['required'] );

			// But don't allow updating the slug, since it is used as a unique identifier.
			if ( isset( $settings['slug'] ) ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'font_family_settings[slug] cannot be updated.', 'gutenberg' ),
					array( 'status' => 400 )
				);
			}
		}

		// Check that the font face settings match the theme.json schema.
		$has_valid_settings = rest_validate_value_from_schema( $settings, $schema, 'font_family_settings' );

		if ( is_wp_error( $has_valid_settings ) ) {
			$has_valid_settings->add_data( array( 'status' => 400 ) );
			return $has_valid_settings;
		}

		// Check that none of the required settings are empty values.
		foreach ( $required as $key ) {
			if ( isset( $settings[ $key ] ) && ! $settings[ $key ] ) {
				return new WP_Error(
					'rest_invalid_param',
					/* translators: %s: Font family setting key. */
					sprintf( __( 'font_family_settings[%s] cannot be empty.', 'gutenberg' ), $key ),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitizes the font family settings when creating or updating a font family.
	 *
	* @since 6.5.0
	 *
	 * @param string          $value   Encoded JSON string of font family settings.
	 * @param WP_REST_Request $request Request object.
	 * @return array                   Decoded array font family settings.
	 */
	public function sanitize_font_family_settings( $value ) {
		$settings = json_decode( $value, true );

		if ( isset( $settings['fontFamily'] ) ) {
			$settings['fontFamily'] = WP_Font_Family_Utils::format_font_family( $settings['fontFamily'] );
		}

		// Provide default for preview, if not provided.
		if ( ! isset( $settings['preview'] ) ) {
			$settings['preview'] = '';
		}

		return $settings;
	}

	/**
	 * Creates a single font family.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$settings = $request->get_param( 'font_family_settings' );

		// Check that the font family slug is unique.
		$query = new WP_Query(
			array(
				'post_type'              => $this->post_type,
				'posts_per_page'         => 1,
				'name'                   => $settings['slug'],
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $query->get_posts() ) ) {
			return new WP_Error(
				'rest_duplicate_font_family',
				/* translators: %s: Font family slug. */
				sprintf( __( 'A font family with slug "%s" already exists.', 'gutenberg' ), $settings['slug'] ),
				array( 'status' => 400 )
			);
		}

		return parent::create_item( $request );
	}

	/**
	 * Deletes a single font family.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for font families.
		if ( ! $force ) {
			return new WP_Error(
				'rest_trash_not_supported',
				/* translators: %s: force=true */
				sprintf( __( "Font faces do not support trashing. Set '%s' to delete.", 'gutenberg' ), 'force=true' ),
				array( 'status' => 501 )
			);
		}

		return parent::delete_item( $request );
	}

	/**
	 * Prepares a single font family output for response.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Post         $item    Post object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( rest_is_field_included( 'id', $fields ) ) {
			$data['id'] = $item->ID;
		}

		if ( rest_is_field_included( 'theme_json_version', $fields ) ) {
			$data['theme_json_version'] = 2;
		}

		if ( rest_is_field_included( 'font_faces', $fields ) ) {
			$data['font_faces'] = $this->get_font_face_ids( $item->ID );
		}

		if ( rest_is_field_included( 'font_family_settings', $fields ) ) {
			$data['font_family_settings'] = $this->get_settings_from_post( $item );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) ) {
			$links = $this->prepare_links( $item );
			$response->add_links( $links );
		}

		/**
		 * Filters the font family data for a REST API response.
		 *
		 * @since 6.5.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Post          $post     Font family post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'rest_prepare_wp_font_family', $response, $item, $request );
	}

		/**
	 * Retrieves the post's schema, conforming to JSON Schema.
	 *
	 * @since 6.5.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			// Base properties for every Post.
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the post.', 'default' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'theme_json_version'   => array(
					'description' => __( 'Version of the theme.json schema used for the typography settings.', 'gutenberg' ),
					'type'        => 'integer',
					'default'     => 2,
					'minimum'     => 2,
					'maximum'     => 2,
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'font_faces'           => array(
					'description' => __( 'The IDs of the child font faces in the font family.', 'gutenberg' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit', 'embed' ),
					'items'       => array(
						'type' => 'integer',
					),
				),
				// Font family settings come directly from theme.json schema
				// See https://schemas.wp.org/trunk/theme.json
				'font_family_settings' => array(
					'description'          => __( 'font-face declaration in theme.json format.', 'gutenberg' ),
					'type'                 => 'object',
					'context'              => array( 'view', 'edit', 'embed' ),
					'properties'           => array(
						'name'       => array(
							'description' => 'Name of the font family preset, translatable.',
							'type'        => 'string',
						),
						'slug'       => array(
							'description' => 'Kebab-case unique identifier for the font family preset.',
							'type'        => 'string',
						),
						'fontFamily' => array(
							'description' => 'CSS font-family value.',
							'type'        => 'string',
						),
						'preview'    => array(
							'description' => 'URL to a preview image of the font family.',
							'type'        => 'string',
						),
					),
					'required'             => array( 'name', 'slug', 'fontFamily' ),
					'additionalProperties' => false,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for the font family collection.
	 *
	 * @since 6.5.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		// Remove unneeded params.
		unset( $query_params['after'] );
		unset( $query_params['modified_after'] );
		unset( $query_params['before'] );
		unset( $query_params['modified_before'] );
		unset( $query_params['search'] );
		unset( $query_params['search_columns'] );
		unset( $query_params['status'] );

		$query_params['orderby']['default'] = 'id';
		$query_params['orderby']['enum']    = array( 'id', 'include' );

		/**
		 * Filters collection parameters for the font family controller.
		 *
		 * @since 6.5.0
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_wp_font_family_collection_params', $query_params );
	}

	/**
	 * Get the arguments used when creating or updating a font family.
	 *
	 * @since 6.5.0
	 *
	 * @return array Font family create/edit arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		if ( WP_REST_Server::CREATABLE === $method || WP_REST_Server::EDITABLE === $method ) {
			$properties = $this->get_item_schema()['properties'];
			return array(
				'theme_json_version'   => $properties['theme_json_version'],
				// When creating or updating, font_family_settings is stringified JSON, to work with multipart/form-data.
				// Font families don't currently support file uploads, but may accept preview files in the future.
				'font_family_settings' => array(
					'description'       => __( 'font-family declaration in theme.json format, encoded as a string.', 'gutenberg' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => array( $this, 'validate_font_family_settings' ),
					'sanitize_callback' => array( $this, 'sanitize_font_family_settings' ),
				),
			);
		}

		return parent::get_endpoint_args_for_item_schema( $method );
	}

	/**
	 * Get the child font face post IDs.
	 *
	 * @since 6.5.0
	 *
	 * @param int $font_family_id Font family post ID.
	 * @return int[] Array of child font face post IDs.
	 * .
	 */
	protected function get_font_face_ids( $font_family_id ) {
		$query = new WP_Query(
			array(
				'fields'                 => 'ids',
				'post_parent'            => $font_family_id,
				'post_type'              => 'wp_font_face',
				'posts_per_page'         => 99,
				'order'                  => 'ASC',
				'orderby'                => 'id',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $query->get_posts();
	}

	/**
	 * Prepares font family links for the request.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Post $post Post object.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ) {
		// Entity meta.
		$links = parent::prepare_links( $post );

		return array(
			'self'       => $links['self'],
			'collection' => $links['collection'],
			'font_faces' => $this->prepare_font_face_links( $post->ID ),
		);
	}

	/**
	 * Prepares child font face links for the request.
	 *
	 * @param int $font_family_id Font family post ID.
	 * @return array Links for the child font face posts.
	 */
	protected function prepare_font_face_links( $font_family_id ) {
		$font_face_ids = $this->get_font_face_ids( $font_family_id );
		$links         = array();
		foreach ( $font_face_ids as $font_face_id ) {
			$links[] = array(
				'embeddable' => true,
				'href'       => rest_url( $this->namespace . '/' . $this->rest_base . '/' . $font_family_id . '/font-faces/' . $font_face_id ),
			);
		}
		return $links;
	}

	/**
	 * Prepares a single font family post for create or update.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Post object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass();
		// Settings have already been decoded by ::sanitize_font_family_settings().
		$settings = $request->get_param( 'font_family_settings' );

		// This is an update and we merge with the existing font family.
		if ( isset( $request['id'] ) ) {
			$existing_post = $this->get_post( $request['id'] );
			if ( is_wp_error( $existing_post ) ) {
				return $existing_post;
			}

			$prepared_post->ID = $existing_post->ID;
			$existing_settings = $this->get_settings_from_post( $existing_post );
			$settings          = array_merge( $existing_settings, $settings );
		}

		$prepared_post->post_type   = $this->post_type;
		$prepared_post->post_status = 'publish';
		$prepared_post->post_title  = $settings['name'];
		$prepared_post->post_name   = sanitize_title( $settings['slug'] );

		// Remove duplicate information from settings.
		unset( $settings['name'] );
		unset( $settings['slug'] );

		$prepared_post->post_content = wp_json_encode( $settings );

		return $prepared_post;
	}

	/**
	 * Gets the font family's settings from the post.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_Post $post Font family post object.
	 * @return array Font family settings array.
	 */
	protected function get_settings_from_post( $post ) {
		$settings_json = json_decode( $post->post_content, true );

		// Default to empty strings if the settings are missing.
		return array(
			'name'       => isset( $post->post_title ) && $post->post_title ? $post->post_title : '',
			'slug'       => isset( $post->post_name ) && $post->post_name ? $post->post_name : '',
			'fontFamily' => isset( $settings_json['fontFamily'] ) && $settings_json['fontFamily'] ? $settings_json['fontFamily'] : '',
			'preview'    => isset( $settings_json['preview'] ) && $settings_json['preview'] ? $settings_json['preview'] : '',
		);
	}
}
