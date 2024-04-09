<?php

namespace WP_ClickUp_Sync\Helpers\Settings_Page;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Helper for rendering settings
 */
class Base extends \WP_ClickUp_Sync\Helpers\Base {

	/**
	 * Page information
	 */
	private array $page = [];

	/**
	 * List of settings sections
	 */
	private array $settings_sections = [];

	/**
	 * List of settings subsections
	 */
	private array $settings_subsections = [];

	/**
	 * List of settings fields
	 */
	private array $settings_fields = [];

	private \WP_ClickUp_Sync\Helpers\Options\Base $Options;

	/**
	 * Field validate status
	 * Updated from the validate_callback function, passed to the field.
	 *
	 * @var array [
	 *   $option_name: 'error'|'warning'|'success'|'info',
	 */
	private array $field_validate_status = [];

	/**
	 * Returns active section
	 */
	public string $active_section = '';


	public function __construct() {
		$this->Options = new \WP_ClickUp_Sync\Helpers\Options\Base();

		$this->add_js();
		$this->add_css();
	}

	/**
	 * Get option value
	 *
	 * @param string $option_name
	 * @param string|false $option_key
	 * @param string|array|false $default
	 *
	 * @return string|array|false
	 */
	public function get_option( string $option_name, string|false $option_key = false, mixed $default = false ): string|array|false {
		return $this->Options->get( $option_name, $option_key, $default );
	}

	/**
	 * Add JS file
	 */
	private function add_js(): void {
		add_action( 'wp_enqueue_scripts', function() {
			wp_enqueue_script( 'wp-clickup-sync-menu-page-base', WP_CLICKUP_SYNC_URL . '/includes/Helpers/Settings_Page/base.js', [], false, true );
		});
	}

	/**
	 * Add CSS file
	 */
	private function add_css(): void {
		add_action( 'wp_enqueue_scripts', function() {
			wp_enqueue_style( 'wp-clickup-sync-menu-page-base', WP_CLICKUP_SYNC_URL . '/includes/Helpers/Settings_Page/base.css' );
		});
	}

	/**
	 * Set submenu page
	 *
	 * @param array $page
	 */
	public function add_submenu_page( array $page ): void {
		// Set default values
		if ( ! isset( $page['callback'] ) ) {
			$page['callback'] = [ $this, 'render_submenu_page' ];
		}

		// Set page
		$this->page = $page;
	}

	/**
	 * Load the submenu page
	 *
	 * @return $this
	 */
	public function load_submenu_page(): static {
		$parent_slug = $this->page['parent_slug'];
		$page_title  = $this->page['page_title'];
		$menu_title  = $this->page['menu_title'];
		$capability  = $this->page['capability'];
		$menu_slug   = $this->page['menu_slug'];
		$callback    = $this->page['callback'] ?? '';
		$position    = $this->page['position'] ?? null;

		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position );

		return $this;
	}

	/**
	 * Add Settings Section
	 *
	 * @param array $section = array{
	 *   'id': string, // Section ID. Recommended to set to the Option Name.
	 *   'title': string, // Section title
	 *   'callback'?: callable, // Callback function to render the section.
	 *   'page'?: string, // Page slug. Must match the page slug of the page to which the section is added.
	 *   'args'?: array{ # Optional. Array of arguments to pass to the callback function.
	 *     'before_section'?: string, // HTML to display before section
	 *     'after_section'?: string, // HTML to display after section
	 *     'section_class'?: string, // Class to add to section
	 * }
	 *
	 */
	public function add_settings_section( array $settings_section ): static {
		// Require the page to be initialized
		if ( empty( $this->page ) ) {
			return $this;
		}

		// Require values
		if (
			empty( $settings_section['id'] ) ||
			empty( $settings_section['title'] )
		) {
			return $this;
		}

		// Add defaults
		$settings_section = wp_parse_args( $settings_section, [
			'callback' => [ $this, 'render_settings_section' ],
			'page'     => $this->page['menu_slug'],
			'args'     => [],
		] );

		$this->settings_sections[] = $settings_section;

		// Update active section marker
		// - If this is the first section, set it as active
		if ( empty( $this->active_section ) ) {
			$this->active_section = $settings_section['id'];
		} // - If the section is set in the URL, and it matches this section, set this section as active
		else if ( isset( $_GET['section'] ) && $_GET['section'] === $settings_section['id'] ) {
			$this->active_section = $settings_section['id'];
		}

		return $this;
	}

	/**
	 * Add Settings Subsection. This is a non-WordPress feature.
	 *
	 * @param array $settings_subsection = array{
	 *   'id': string, // Subsection ID. Must be unique to all subsections on the page.
	 *   'title': string, // Subsection title
	 * }
	 *
	 * @return $this
	 */
	public function add_settings_subsection( array $settings_subsection ): static {
		// Require the page to be initialized
		if ( empty( $this->page ) ) {
			return $this;
		}

		// Require values
		if (
			empty( $settings_subsection['id'] ) ||
			empty( $settings_subsection['title'] )
		) {
			return $this;
		}

		$this->settings_subsections[] = $settings_subsection;

		return $this;
	}

	/**
	 * Add Settings Field. The [ 'args': [ 'class': string ] ] setting is unavailable, as it's used by the subsection
	 * feature.
	 *
	 * @param array $settings_field = array{
	 *   'id': string, // Field ID. Must be unique to section.
	 *   'title': string, // Field title.
	 *   'callback'?: callable, // Callback function to render the field.
	 *   'page'?: string, // Page slug. Must match the page slug of the page to which the section is added.
	 *   'section'?: string, // Section ID. Defaults to the last Settings Section, or if no sections, "default".
	 *   'subsection:?: string, // Subsection ID. Defaults to ''. This is a non-WordPress feature.
	 *   'args': array{
	 *     'render'?: string, // Render type. Refer to render_settings_field().
	 *     'option_name'?: string, //Option name to use for this field.
	 *     'option_key'?: string, // Option key to use for this field.
	 *     'default_value'?: string, // The default value of the option.
	 *     'value'?: string, // The value of the option. Defaults to default_value.
	 *     'available_values'?: array, // The available values for the option. Only used for select fields.
	 *     'description'?: string, // Field description.
	 *     'label_for'?: string, // The label for the field. Defaults to the best guess.
	 *     // Callback to render a message. A string return is equal to ['status' => 'info', 'message' => $return].
	 *     'validate_callback'?: callable ( string $field_value, array $validate_callback_args ): false|string|array{
	 *       'status': 'error'|'warning'|'success'|'info',
	 *       'message': string,
	 *     },
	 *     'validate_callback_args'?: array, // Arguments to pass to the status callback.
	 *   }
	 * }
	 *
	 * @return $this
	 */
	public function add_settings_field( array $settings_field ): static {
		// Require the page to be initialized
		if ( empty( $this->page ) ) {
			return $this;
		}

		// Require values
		if (
			empty( $settings_field['title'] )
		) {
			return $this;
		}

		// Add defaults
		$default_callback = [ $this, 'render_settings_field' ];
		$settings_field   = wp_parse_args( $settings_field, [
			'callback'   => $default_callback,
			'page'       => $this->page['menu_slug'],
			'section'    => ! empty( $this->settings_sections ) ? $this->settings_sections[ count( $this->settings_sections ) - 1 ]['id'] : 'default',
			'subsection' => ! empty( $this->settings_subsections ) ? $this->settings_subsections[ count( $this->settings_subsections ) - 1 ]['id'] : '',
			'args'       => [
				'render' => 'text',
			],
		] );

		// Generate field id
		if ( isset( $settings_field['args']['option_name'], $settings_field['args']['option_key'] ) ) {
			// { $option_name }__{ $option_key }
			$settings_field['id'] = $settings_field['args']['option_name'] . '__' . $settings_field['args']['option_key'];
		} elseif ( isset( $settings_field['args']['option_name'] ) ) {
			// { $option_name }
			$settings_field['id'] = $settings_field['args']['option_name'];
		} else {
			//{ $page['menu_slug'] }__field-{ $field_count }
			// Add 2 to the settings_field count, to account for 1) array starting with 0, and 2) the current field not yet added to the count
			$field_count          = empty( $this->settings_fields ) ? 1 : count( $this->settings_fields ) + 2;
			$settings_field['id'] = $this->page['menu_slug'] . '__field-' . $field_count;
		}

		// Guess whether to add a label or not
		// If the label_for is not set, and the default callback set
		# Add it for non-text fields
		if ( ! isset( $settings_fields['label_for'] ) && $settings_field['callback'] === $default_callback && $settings_field['args']['render'] !== 'text' ) {
			$settings_field['args']['label_for'] = $settings_field['id'];
		}

		// Pass Field ID to the args
		$settings_field['args']['id'] = $settings_field['id'];

		// Add a class if the subsection value is set
		$settings_field['args']['class'] = ! empty( $settings_field['subsection'] ) ? 'subsection subsection-' . $settings_field['subsection'] : '';

		$this->settings_fields[] = $settings_field;

		return $this;
	}

	public function load_settings_sections(): void {
		foreach ( $this->settings_sections as $settings_section ) {
			add_settings_section( $settings_section['id'], $settings_section['title'], $settings_section['callback'], $settings_section['page'], $settings_section['args'] );
		}
	}

	/**
	 * Load Settings Fields
	 *
	 * @return void
	 */
	public function load_settings_fields(): void {
		foreach ( $this->settings_fields as $settings_field ) {
			add_settings_field( $settings_field['id'], $settings_field['title'], $settings_field['callback'], $settings_field['page'], $settings_field['section'], $settings_field['args'] );
		}
	}

	public function render_submenu_page(): static {
		global $wp_settings_sections;

		$has_subsections_class = ! empty( $this->settings_subsections ) ? ' has-subsections' : '';

		echo '<div class="wrap' . $has_subsections_class . '">';
		echo '<h1>' . $this->page['page_title'] . '</h1>';

		// Render tabs
		$active_tab_id = false;
		if ( $this->settings_sections ) {
			$active_tab = $this->active_section ?? $this->settings_sections[0]['id'];
			echo '<h2 class="wp-clickup-sync nav-tab-wrapper">';
			foreach ( $this->settings_sections as $key => $settings_section ) {
				$tab_id    = $settings_section['id'];
				$tab_title = $settings_section['title'];
				$tab_url   = admin_url( 'admin.php?page=' . $this->page['menu_slug'] . '&section=' . $tab_id );
				$active    = $active_tab === $tab_id ? 'nav-tab-active' : '';
				if ( $active_tab === $tab_id ) {
					$active_tab_id = $tab_id;
				}
				echo '<a href="' . $tab_url . '" class="nav-tab ' . $active . '">' . $tab_title . '</a>';
			}
			echo '</h2>';

		}

		// Render Subsections
		echo $this->get_settings_subsections_menu( $active_tab_id );

		// Render settings
		echo '<form class="wp-clickup-sync settings-form" method="post" action="options.php">';

		// If there are settings sections, render them, otherwise render the entire page
		if ( $this->settings_sections ) {
			foreach ( $this->settings_sections as $key => $settings_section ) {
				$tab_id = $settings_section['id'];
				if ( isset( $active_tab ) && $active_tab === $tab_id ) {
					$option_group = $this->page['menu_slug'] . '_' . $settings_section['id'];

					// If there is a section callback, render that, otherwise render the fields
					$section = $wp_settings_sections[ $this->page['menu_slug'] ][ $tab_id ] ?? [];
					settings_fields( $option_group );
					if ( isset( $section['callback'] ) ) {
						// Unless otherwise specified, this performs a callback to $this->render_settings_section
						call_user_func( $section['callback'], $section );
					} else {
						// If the callback is set to false, renders the fields using the default WordPress settings
						do_settings_sections( $this->page['menu_slug'] );
					}
				}
			}
		} else {
			// If no sections, render the page
			settings_fields( $this->page['menu_slug'] );
			do_settings_sections( $this->page['menu_slug'] );
		}

		submit_button();
		echo '</form>';
		echo '</div>';


		return $this;
	}

	/**
	 * Generate Settings Subsection Menu
	 *
	 * @param array|false $section_id Optional Section ID
	 *
	 * @return string
	 */
	private function get_settings_subsections_menu( string|false $section_id = false ): string {
		// Get all requested subsections
		$requested_subsection_ids = [];
		foreach ( $this->settings_fields as $settings_field ) {
			if ( $section_id && $settings_field['section'] !== $section_id ) {
				continue;
			}
			if ( ! empty( $settings_field['subsection'] ) ) {
				$requested_subsection_ids[] = $settings_field['subsection'];
			}
		}

		if ( empty( $requested_subsection_ids ) ) {
			return '';
		}

		// Get all available subsections
		$subsections = [];
		foreach ( $this->settings_subsections as $settings_subsection ) {
			if ( in_array( $settings_subsection['id'], $requested_subsection_ids, true ) ) {
				$subsections[] = $settings_subsection;
			}
		}


		// Render subsections
		$output = '';

		$total  = count( $subsections );
		$i      = 0;
		$output .= '<div class="wp-clickup-sync subsection-menu settings-subsections wp-clearfix" aria-label="Secondary menu">';
		$output .= '<ul class="subsubsub">';
		foreach ( $subsections as $subsection ) {
			$i ++;
			$subsection_id    = $subsection['id'];
			$subsection_title = $subsection['title'];
			$divider          = $i < $total ? '<span class="divider">|</span>' : '';
			$active_class     = $i === 1 ? ' current' : '';

			if ( ! in_array( $subsection_id, $requested_subsection_ids, true ) ) {
				continue;
			}

			$output .= '<li>';
			$output .= '<a href="#subsection-' . $subsection_id . '" class="link' . $active_class . '" data-target-subsection="subsection-' . $subsection_id . '">' . $subsection_title . '</a>' . $divider;
			$output .= '</li>';
		}
		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render Settings Section
	 *
	 * @param array $section
	 *
	 * @return void
	 */
	public function render_settings_section( $args ): void {
		global $wp_settings_fields;

		// Values available by default
		$id       = $args['id'];
		$title    = $args['title'];
		$callback = $args['callback'];

		// Values passed by the user
		$before_section = $args['before_section'] ?? '';
		$after_section  = $args['after_section'] ?? '';
		$section_class  = $args['section_class'] ? ' ' . $args['section_class'] : '';

		// If no fields are available, return
		if ( ! isset( $wp_settings_fields[ $this->page['menu_slug'] ][ $id ] ) ) {
			return;
		}

		echo '<div class="before-section' . $section_class . '">' . $before_section . '</div>';
		echo '<table class="form-table ' . $id . $section_class . '">';
		do_settings_fields( $this->page['menu_slug'], $id );
		echo '</table>';
		echo '<div class="after-section' . $section_class . '">' . $after_section . '</div>';
	}

	/**
	 * Render a settings field
	 *
	 * @param $args array{
	 *   'render'?: string, // The type of field to render. See options below.
	 *   'option_name'?: string, // The name of the option to load/save.
	 *   'option_key'?: string, // The key of the option to load/save.
	 *   'default_value'?: string, // The default value of the option.
	 *   'value'?: string, // The value of the option. Defaults to default_value.
	 *   'available_values'?: array{ key: value }, // Only used for select fields (key:value = name:label).
	 *   'description'?: string, // Field description.
	 *   // Callback to render a message. A string return is equal to ['code' => 'info', 'message' => $return].
	 *   'validate_callback'?: callable ( array $id, array $args, string $id, string $value): false|string|array{
	 *     'code': 'error'|'warning'|'success'|'info',
	 *     'message': string,
	 *   }
	 * }
	 */
	public function render_settings_field( array $args ): static {
		// Load values
		$id                     = $args['id']; // Gets passed automatically
		$render                 = $args['render'] ?? 'text';
		$option_name            = $args['option_name'] ?? '';
		$option_key             = $args['option_key'] ?? '';
		$default_value          = $args['default_value'] ?? '';
		$value                  = $args['value'] ?? $default_value;
		$available_values       = $args['available_values'] ?? [];
		$description            = $args['description'] ?? '';
		$validate_callback      = $args['validate_callback'] ?? false;
		$validate_callback_args = $args['validate_callback_args'] ?? [];

		// Load any existing values from the database. Generate the name attribute.
		$name = '';
		if ( $option_name ) {
			if ( $option_key ) {
				$value = $this->get_option( $option_name, $option_key, $default_value );
				$name  = $option_name . '[' . $option_key . ']';
			} else {
				$value = $this->get_option( $option_name, false, $default_value );
				$name  = $option_name;
			}
		}

		// Process validate callback
		$validate = $validate_callback ? $validate_callback( $id, $value, $validate_callback_args ) : [];
		// If message is string, convert it to an array
		if ( $validate && is_string( $validate ) ) {
			$validate = [
				'status'  => 'info',
				'message' => $validate,
			];
		}
		$validate_status  = $validate['status'] ?? '';
		$validate_message = $validate['message'] ?? '';
		// Update the field status
		$this->update_field_validate_status( $id, $validate_status );

		// Option
		switch ( $render ) {
			case 'text':
				echo $value;
				break;
			case 'input-text-fixed':
				# Unlike input-text-readonly, this field is not submitted, so it cannot be overwritten via $_POST.
				echo '<input type="text" id="' . $id . '" class="regular-text" name="" value="' . $value . '" readonly />';
				break;
			case 'input-text-readonly':
				echo '<input type="text" id="' . $id . '" class="regular-text" name="' . $name . '" value="' . $value . '" readonly />';
				break;
			case 'input-text':
				echo '<input type="text" id="' . $id . '" class="regular-text" name="' . $name . '" value="' . $value . '" />';
				break;
			case 'input-number':
				echo '<input type="number" id="' . $id . '" class="regular-text" name="' . $name . '" value="' . $value . '" />';
				break;
			case 'input-password':
				echo '<input type="password" id="' . $id . '" class="regular-text" name="' . $name . '" placeholder="' . str_repeat( '*', strlen( $value ) ) . '" value="" />';
				break;
			case 'select':
				echo '<select id="' . $id . '" name="' . $name . '">';
				$options = '';
				foreach ( $available_values as $available_value => $available_label ) {
					$selected =  htmlspecialchars($value, ENT_QUOTES, 'UTF-8' ) === (string) $available_value ? ' selected' : '';
					$options  .= '<option value="' . $available_value . '"' . $selected . '>' . $available_label . '</option>';
				}
				echo $options;
				echo '</select>';
				break;
			case 'select-optional':
				echo '<select id="' . $id . '" name="' . $name . '">';
				$options                      = '';
				$existing_option_was_selected = false;
				foreach ( $available_values as $available_value => $available_label ) {
					$selected = htmlspecialchars($value, ENT_QUOTES, 'UTF-8' ) === (string) $available_value ? ' selected' : '';
					if ( $selected ) {
						$existing_option_was_selected = true;
					}
					$options .= '<option value="' . $available_value . '"' . $selected . '>' . $available_label . '</option>';
				}
				$default_value_selected = $existing_option_was_selected ? '' : ' selected';
				echo '<option value="" ' . $default_value_selected . '>' . __( '— Select —' ) . '</option>';
				echo $options;
				echo '</select>';
				break;

			default:
				// Require a valid option type
				$this->show_message( "Unrecognized render option: \"{$render}\".", 'error' );
		}

		echo $validate_status && $validate_message ? '<div class="regular-text"><p class="notice notice-' . $validate_status . ' inline">' . $validate_message . '</p></div>' : '';
		echo $description ? '<p class="description regular-text">' . $description . '</p>' : '';

		return $this;
	}

	/**
	 * Update field status code.
	 *
	 * @param string $field_id
	 * @param string $status_code 'error'|'warning'|'success'|'info'
	 *
	 * @return static
	 */
	public function update_field_validate_status( string $field_id, string $validate_status ): static {
		$this->field_validate_status[ $field_id ] = $validate_status;

		return $this;
	}

	/**
	 * Get field status code.
	 *
	 * @param string $field_id
	 *
	 * @return string|false
	 */
	public function get_field_validate_status( string $field_id ): string|false {
		return $this->field_validate_status[ $field_id ] ?? false;
	}

	/**
	 * Register the settings
	 * Loop through all fields to find all option_names and sections.
	 * If part of a section, register it as a group of the section, otherwise register it a page.
	 *
	 * @return void
	 */
	public function register_settings(): static {
		$settings_fields = $this->settings_fields;

		$register_settings = [];

		foreach ( $settings_fields as $settings_field ) {
			if ( empty( $settings_field['args']['option_name'] ) ) {
				continue;
			}

			$option_group = $settings_field['page'];
			$option_name  = $settings_field['args']['option_name'];
			if ( ! empty( $settings_field['section'] ) && $settings_field['section'] !== 'default' ) {
				$option_group                         .= '_' . $settings_field['section'];
				$register_settings[ $option_group ][] = $option_name;
			}
		}

		foreach ( $register_settings as $option_group => $option_names ) {
			foreach ( $option_names as $option_name ) {
				register_setting( $option_group, $option_name );
			}
		}

		return $this;
	}

	public function render(): static {
		add_action( 'admin_menu', [ $this, 'load_submenu_page' ] );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'load_settings_sections' ) );
		add_action( 'admin_init', array( $this, 'load_settings_fields' ) );

		return $this;
	}
}
