<?php
/**
 * Class Builder Layouts and Layout Parts
 * Handle Layouts and Parts logic
 * @package themifyBuilder
 */
class Themify_Builder_Layouts {
	public $layout;
	public $layout_part;
	public $post_types = array();

	function __construct() {
		$this->register_layout();

		// Builder write panel
		add_filter( 'themify_do_metaboxes', array( $this, 'layout_write_panels' ), 11 );
		add_filter( 'themify_post_types', array( $this, 'extend_post_types' ) );
		add_action( 'add_meta_boxes_tbuilder_layout_part', array( $this, 'custom_meta_boxes' ) );

		add_action( 'wp_ajax_tfb_load_layout', array( $this, 'load_layout_ajaxify' ), 10 );
		add_action( 'wp_ajax_tfb_set_layout', array( $this, 'set_layout_ajaxify' ), 10 );
		add_action( 'wp_ajax_tfb_custom_layout_form', array( $this, 'custom_layout_form_ajaxify' ), 10 );
		add_action( 'wp_ajax_tfb_save_custom_layout', array( $this, 'save_custom_layout_ajaxify' ), 10 );

		add_filter( 'template_include', array( $this, 'template_singular_layout' ) );

		add_shortcode( 'themify_layout_part', array( $this, 'layout_part_shortcode' ) );

		// Quick Edit Links
		add_filter( 'post_row_actions', array( $this, 'row_actions' ) );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ) );
		add_action( 'admin_init', array( $this, 'duplicate_action' ) );

		add_action( 'admin_init', array( $this, 'cleanup_builtin_layouts' ) );
	}

	/**
	 * Register CPT
	 */
	function register_layout() {
		if ( ! class_exists( 'CPT' ) ) {
			include_once THEMIFY_BUILDER_LIBRARIES_DIR . '/' . 'CPT.php';
		}

		$public = ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ? true : false;

		// create a template custom post type
		$this->layout = new CPT( array(
			'post_type_name' => 'tbuilder_layout',
			'singular' => __('Layout', 'themify'),
			'plural' => __('Layouts', 'themify')
		), array(
			'supports' => array('title', 'thumbnail'),
			'exclude_from_search' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => false,
			'public' => $public
		));

		// define the columns to appear on the admin edit screen
		$this->layout->columns(array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'themify'),
			'thumbnail' => __('Thumbnail', 'themify'),
			'author' => __('Author', 'themify'),
			'date' => __('Date', 'themify')
		));

		// populate the thumbnail column
		$this->layout->populate_column('thumbnail', array( $this, 'populate_column_layout_thumbnail' ) );

		// use "pages" icon for post type
		$this->layout->menu_icon('dashicons-admin-page');

		// create a template custom post type
		$this->layout_part = new CPT( array(
			'post_type_name' => 'tbuilder_layout_part',
			'singular' => __('Layout Part', 'themify'),
			'plural' => __('Layout Parts', 'themify'),
			'slug' => 'tbuilder-layout-part'
		), array(
			'supports' => array('title', 'thumbnail'),
			'exclude_from_search' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => false,
			'public' => $public
		));

		// define the columns to appear on the admin edit screen
		$this->layout_part->columns(array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'themify'),
			'shortcode' => __('Shortcode', 'themify'),
			'author' => __('Author', 'themify'),
			'date' => __('Date', 'themify')
		));

		// populate the thumbnail column
		$this->layout_part->populate_column('shortcode', array( $this, 'populate_column_layout_part_shortcode' ) );

		// use "pages" icon for post type
		$this->layout_part->menu_icon('dashicons-screenoptions');

		$this->set_post_type_var( $this->layout->post_type_name );
		$this->set_post_type_var( $this->layout_part->post_type_name );
	}

	function set_post_type_var( $name ) {
		array_push( $this->post_types, $name );
	}

	function populate_column_layout_thumbnail( $column, $post ) {
		echo get_the_post_thumbnail( $post->ID, 'thumbnail');
	}

	function populate_column_layout_part_shortcode( $column, $post ) {
		echo sprintf( '[themify_layout_part id=%d]', $post->ID );
		echo '<br/>';
		echo sprintf( '[themify_layout_part slug=%s]', $post->post_name );
	}

	/**
	 * Metabox Panel
	 *
	 * @param $meta_boxes
	 * @return array
	 */
	function layout_write_panels( $meta_boxes ) {
		global $pagenow;

		if ( ! in_array( $pagenow, array( 'post-new.php', 'post.php' ) ) ) {
			return $meta_boxes;
		}

		$meta_settings = array(
			array(
				'name' 		=> 'post_image',
				'title' 	=> __('Layout Thumbnail', 'themify'),
				'description' => '',
				'type' 		=> 'image',
				'meta'		=> array()
			)
		);
			
		$all_meta_boxes = array();
		$all_meta_boxes[] = apply_filters( 'layout_write_panels_meta_boxes', array(
			'name'		=> __( 'Settings', 'themify' ),
			'id' 		=> 'layout-settings-builder',
			'options'	=> $meta_settings,
			'pages'    	=> $this->layout->post_type_name
		) );
		return array_merge( $meta_boxes, $all_meta_boxes);
	}

	/**
	 * Includes this custom post to array of cpts managed by Themify
	 * @param Array
	 * @return Array
	 */
	function extend_post_types( $types ) {
		$cpts = array( $this->layout->post_type_name, $this->layout_part->post_type_name );
		return array_merge( $types, $cpts );
	}

	/**
	 * Add meta boxes to layout and/or layout part screens.
	 *
	 * @param $post
	 */
	function custom_meta_boxes( $post ) {
		add_meta_box( 'layout-part-info', __( 'Using this Layout Part', 'themify' ), array( $this, 'layout_part_info' ), $this->layout_part->post_type_name, 'side', 'default' );
	}

	/**
	 * Displays information about this layout part.
	 */
	function layout_part_info() {
		$layout_part = get_post();
		echo '<div>' . __( 'To display this Layout Part, insert this shortcode:', 'themify' ) . '<br/><span class="code">[themify_layout_part id="' . esc_html( $layout_part->ID ) . '"]</span>';
		if ( ! empty( $layout_part->post_name ) ) {
			echo  __( 'or', 'themify' ) . '<br/><span class="code">[themify_layout_part slug="' . esc_html( $layout_part->post_name ) . '"]</span>';
		}
		echo '</div>';
	}

	/**
	 * Load list of available Templates
	 */
	function load_layout_ajaxify() {
		global $post;

		check_ajax_referer( 'tfb_load_nonce', 'nonce' );

		$layouts = array();

		// user-created layouts
		$posts = new WP_Query( array(
			'post_type' => $this->layout->post_type_name,
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		));
		if( $posts->have_posts() ) : while( $posts->have_posts() ) : $posts->the_post();
			$layouts[] = array(
				'title' => get_the_title(),
				'slug' => $post->post_name,
				'thumbnail' => has_post_thumbnail() ? get_the_post_thumbnail( 'thumbnail', array( 150, 150 ) ) : sprintf( '<img src="%s">', 'http://placehold.it/150x150' ),
				'prebuilt' => false
			);
		endwhile; endif;
		wp_reset_postdata();

		// builtin layouts
		$_layouts = include( THEMIFY_BUILDER_INCLUDES_DIR . '/data/layouts.php' );
		if( ! empty ( $_layouts ) ) {
			foreach( $_layouts as $layout ) {
				$layouts[] = array(
					'title' => $layout['title'],
					'slug' => $layout['data'],
					'thumbnail' => sprintf( '<img src="%s">', sprintf( $layout['thumb'], THEMIFY_BUILDER_URI . '/includes/data' ) ),
					'prebuilt' => true
				);
			}
		}

		include_once THEMIFY_BUILDER_INCLUDES_DIR . '/themify-builder-layout-lists.php';
		die();
	}

	/**
	 * Custom layout for Template / Template Part Builder Editor
	 */
	function template_singular_layout( $original_template ) {
		if ( is_singular( array( $this->layout->post_type_name, $this->layout_part->post_type_name ) ) ) {
			$templatefilename = 'template-builder-editor.php';
			
			$return_template = locate_template(
				array(
					trailingslashit( 'themify-builder/templates' ) . $templatefilename
				)
			);

			// Get default template
			if ( ! $return_template )
				$return_template = THEMIFY_BUILDER_TEMPLATES_DIR . '/' . $templatefilename;

			return $return_template;
		} else {
			return $original_template;
		}
	}

	/**
	 * Set template to current active builder
	 */
	function set_layout_ajaxify() {
		global $ThemifyBuilder;
		check_ajax_referer( 'tfb_load_nonce', 'nonce' );
		$template_slug = $_POST['layout_slug'];
		$current_builder_id = (int) $_POST['current_builder_id'];
		$builtin_layout = $_POST['builtin_layout'];
		$builder_data = '';

		if( $builtin_layout == 'yes' ) {
			$file = THEMIFY_BUILDER_INCLUDES_DIR . '/data/' . $template_slug;
			if( is_file( $file ) ) {
				$cache_dir = themify_get_cache_dir();
				$extract_file = $cache_dir['path'] . basename( $template_slug );
				WP_Filesystem();
				/* extract the file */
				if( 1 == unzip_file( $file, $extract_file ) ) {
					$extract_file = $cache_dir['path'] . basename( $template_slug ) . '/builder_data_export.txt';
					/* use include to read the file, seems safer than wp_filesystem */
					ob_start();
					include $extract_file;
					$builder_data = ob_get_clean();
				}
			}
		} else {
			$args = array(
				'name' => $template_slug,
				'post_type' => $this->layout->post_type_name,
				'post_status' => 'publish',
				'numberposts' => 1
			);
			$template = get_posts( $args );
			if ( $template ) {
				$builder_data = get_post_meta( $template[0]->ID, $ThemifyBuilder->get_meta_key(), true );
			}
		}
		$response = array();

		if ( ! empty( $builder_data ) ) {
			update_post_meta( $current_builder_id, $ThemifyBuilder->get_meta_key(), $builder_data );	
			$response['status'] = 'success';
			$response['msg'] = '';
		} else {
			$response['status'] = 'failed';
			$response['msg'] = __('Something went wrong', 'themify');
		}

		wp_send_json( $response );
		die();
	}

	/**
	 * Layout Part Shortcode
	 * @param array $atts 
	 * @return string
	 */
	function layout_part_shortcode( $atts ) {
		global $ThemifyBuilder;
		extract( shortcode_atts( array(
			'id' => '',
			'slug' => ''
		), $atts ));

		$args = array(
			'post_type' => $this->layout_part->post_type_name,
			'post_status' => 'publish',
			'numberposts' => 1
		);
		if ( ! empty( $slug ) ) $args['name'] = $slug;
		if ( ! empty( $id ) ) $args['p'] = $id;
		$template = get_posts( $args );
		$output = '';

		if ( $template ) {
			$builder_data = get_post_meta( $template[0]->ID, $ThemifyBuilder->get_meta_key(), true );
			if ( ! empty( $builder_data ) ) {
				$output = $ThemifyBuilder->retrieve_template( 'builder-layout-part-output.php', array( 'builder_output' => $builder_data, 'builder_id' => $template[0]->ID ), '', '', false );
			}
		}

		return $output;
	}

	/**
	 * Render Layout Form in lightbox
	 */
	function custom_layout_form_ajaxify() {
		check_ajax_referer( 'tfb_load_nonce', 'nonce' );
		$postid = (int) $_POST['postid'];

		$fields = array(
			array(
				'id' => 'layout_img_field',
				'type' => 'image',
				'label' => __('Image Preview', 'themify'),
				'class' => 'xlarge'
			),
			array(
				'id' => 'layout_title_field',
				'type' => 'text',
				'label' => __('Title', 'themify')
			)
		);
		
		include_once THEMIFY_BUILDER_INCLUDES_DIR . '/themify-builder-save-layout-form.php';
		die();
	}

	/**
	 * Save as Layout
	 */
	function save_custom_layout_ajaxify() {
		check_ajax_referer( 'tfb_load_nonce', 'nonce' );
		global $ThemifyBuilder;
		$data = array();
		$response = array(
			'status' => 'failed',
			'msg' => __('Something went wrong', 'themify')
		);

		if ( isset( $_POST['form_data'] ) )
			parse_str( $_POST['form_data'], $data );

		if ( isset( $data['postid'] ) && ! empty( $data['postid'] ) ) {
			$template = get_post( $data['postid'] );
			$title = isset( $data['layout_title_field'] ) && ! empty( $data['layout_title_field'] ) ? sanitize_text_field( $data['layout_title_field'] ) : $template->post_title . ' Layout';
			$builder_data = get_post_meta( $template->ID, $ThemifyBuilder->get_meta_key(), true );
			if ( ! empty( $builder_data ) ) {
				$new_id = wp_insert_post(array(
					'post_status' => 'publish',
					'post_type' => $this->layout->post_type_name,
					'post_author' => $template->post_author,
					'post_title' => $title,
				));

				update_post_meta( $new_id, $ThemifyBuilder->get_meta_key(), $builder_data );

				// Set image as Featured Image
				if ( isset( $data['layout_img_field_attach_id'] ) && ! empty( $data['layout_img_field_attach_id'] ) )
					set_post_thumbnail( $new_id, $data['layout_img_field_attach_id'] );

				$response['status'] = 'success';
				$response['msg'] = '';
			}
		}

		wp_send_json( $response );
	}

	/**
	 * Add custom link actions in post / page rows
	 * @param array $actions 
	 * @return array
	 */
	function row_actions( $actions ) {
		global $post;
		$builder_link = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( get_permalink( $post->ID ) . '#builder_active' ), __('Themify Builder', 'themify' ));
		if ( ( $this->layout->post_type_name == get_post_type() ) || ( $this->layout_part->post_type_name == get_post_type() ) ) {
			$actions['themify-builder-duplicate'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'post.php?post=' . $post->ID . '&action=duplicate_tbuilder' ), 'duplicate_themify_builder' ), __('Duplicate', 'themify') );
			$actions['themify-builder'] = $builder_link;
		} else {
			// print builder links on another post types
			$registered_post_types = themify_post_types();
			if ( in_array( get_post_type(), $registered_post_types ) ) 
				$actions['themify-builder'] = $builder_link;
		}

		return $actions;
	}

	/**
	 * Duplicate Post in Admin Edit page
	 */
	function duplicate_action() {
		if ( isset( $_GET['action'] ) && 'duplicate_tbuilder' == $_GET['action'] && wp_verify_nonce($_GET['_wpnonce'], 'duplicate_themify_builder') ) {
			global $themifyBuilderDuplicate;
			$postid = (int) $_GET['post'];
			$layout = get_post( $postid );

			$new_id = $themifyBuilderDuplicate->duplicate( $layout );
			delete_post_meta( $new_id, '_themify_builder_prebuilt_layout' );

			wp_redirect( admin_url( 'edit.php?post_type=' . get_post_type( $postid ) ) );
			exit;
		}
	}

	/**
	 * Get layouts cache dir
	 * @return array
	 */
	static public function get_cache_dir() {
		$upload_dir = wp_upload_dir();

		$dir_info = array(
			'path'   => $upload_dir['basedir'] . '/themify-builder/',
			'url'    => $upload_dir['baseurl'] . '/themify-builder/'
		);

		if( ! file_exists( $dir_info['path'] ) ) {
			mkdir( $dir_info['path'] );
		}

		return $dir_info;
	}

	/**
	 * Runs once and removes the builtin layout posts as no longer needed
	 *
	 * @since 1.5.1
	 */
	public function cleanup_builtin_layouts() {
		global $post;
		if( get_option( 'themify_builder_cleanup_builtin_layouts' ) == 'yes' )
			return;

		$posts = new WP_Query( array(
			'post_type' => $this->layout->post_type_name,
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'meta_key' => '_themify_builder_prebuilt_layout',
			'meta_value' => 'yes'
		));
		if( $posts->have_posts() ) : while( $posts->have_posts() ) : $posts->the_post();
			wp_delete_post( $post->ID, true );
		endwhile; endif;
		wp_reset_postdata();

		update_option( 'themify_builder_cleanup_builtin_layouts', 'yes' );
	}
}