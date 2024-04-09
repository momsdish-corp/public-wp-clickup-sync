<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Page_Dashboard {

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		}
	}

	public static function add_page(): void {
		// Do both add_menu_page & add_submenu_page, to make this subpage the default page.
		add_menu_page(
			'ClickUp Sync',
			'ClickUp Sync',
			'manage_options',
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-dashboard',
			[ __CLASS__, 'page' ],
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MDAiIGhlaWdodD0iODAwIiB2aWV3Qm94PSItMyAwIDI0IDI0IiBmaWxsPSJub25lIj48cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik03Ljc0OCAxNy44NjlsLS4zMDgtLjMwOGExLjUgMS41IDAgMSAxIDIuMTIxLTIuMTIxbDMgM2ExLjUgMS41IDAgMCAxIDAgMi4xMjFsLTMgM2ExLjUgMS41IDAgMSAxLTIuMTIxLTIuMTIxbC41MDEtLjUwMUMzLjQ2OSAyMC40MTQgMCAxNi42MTIgMCAxMmE4Ljk3IDguOTcgMCAwIDEgLjkzNy00LjAwMiAxLjUgMS41IDAgMSAxIDIuNjg2IDEuMzM1QTUuOTcgNS45NyAwIDAgMCAzIDEyYzAgMi44ODQgMi4wMzUgNS4yOTMgNC43NDggNS44Njl6TTkuMTIzIDYuMDAxbC40MzguNDM4YTEuNSAxLjUgMCAxIDEtMi4xMjEgMi4xMjFsLTMtM2ExLjUgMS41IDAgMCAxIDAtMi4xMjFsMy0zYTEuNSAxLjUgMCAxIDEgMi4xMjEgMi4xMjFsLS40NC40NEE5IDkgMCAwIDEgMTggMTJjMCAxLjQ4LS4zNTkgMi45MTMtMS4wMzYgNC4xOTZhMS41IDEuNSAwIDEgMS0yLjY1My0xLjRDMTQuNzYxIDEzLjk0MiAxNSAxMi45OSAxNSAxMmE2IDYgMCAwIDAtNS44NzctNS45OTl6Ii8+PC9zdmc+',
			95
		);
		add_submenu_page(
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-dashboard',
			'Dashboard',
			'Dashboard',
			'manage_options',
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-dashboard',
			[ __CLASS__, 'page' ],
			0
		);
	}

	public static function page(): void {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ClickUp Sync Dashboard', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></h1>
        </div>
		<?php
	}
}

Page_Dashboard::init();