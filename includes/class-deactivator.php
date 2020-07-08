<?php

// Class di attivazione
namespace private_area;

class Deactivator {
	public static function disattivazione() {
		function private_area_delete_post_type() {
			unregister_post_type( 'area-riservata' );
		}
		add_action( 'init', 'private_area_delete_post_type' );
		flush_rewrite_rules();
	}
}
