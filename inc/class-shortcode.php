<?php

// Class di attivazione
namespace private_area;

class Shortcode {

	function __construct() {
		add_shortcode( 'archivepage', array( $this, 'private_area_custom_template' ) );
	}



	public function private_area_custom_template() {
		$string = '';

		if ( is_user_logged_in() ) {
			// info dell'utente loggato
			global $current_user;
			$my_user = $current_user->user_login;
			$my_user_level = $current_user->user_level;
			$my_user_id = $current_user->ID;
			$string .= '<div><h1 style="text-align:center">Benvenuto, ' . $my_user . '</h1>';

			// se l'utente è l'admin mostro tutti i contenuti, altrimenti mostro quelli dell'utente associato al post
			if ( 10 == $my_user_level ) {
				// loop con tutti i contenuti
				$wpquery = new \WP_Query(
					array(
						'post_type' => 'area-riservata',
					)
				);
			} else {
				// loop con i contenuti del relativo utente + quelli contrassegnati come all
				$wpquery = new \WP_Query(
					array(
						'post_type' => 'area-riservata',
						'meta_query' => array(
							'relation' => 'OR',
							array(
								// qua avviene il check tra il metabox users e l'id dell'utente
								'key' => 'users',
								'value' => $my_user_id,
								'compare' => '=',
							),

							array(
								'key' => 'users',
								'value' => 'all',
								'compare' => '=',
							),
						),
					)
				);
			}

			if ( $wpquery->have_posts() ) :
				while ( $wpquery->have_posts() ) :
					$wpquery->the_post();

					// richiamo le info dell'utente associato al post
					global $post;
					$user_selected = get_post_meta( $post->ID, 'users', true );
					$user_info = get_userdata( $user_selected );

					$string .= '<h2> <a href="' . get_permalink() . '">' . get_the_title() . '</a></h2>';

					if ( $user_selected == 'all' ) {

						$string .= '<small><i> Contenuto per Tutti </i></small>';

					} else {

						$string .= '<small><i> Contenuto per l' . "' " . 'utente: "' . $user_info->user_login . '"</i></small>';
					}

			endwhile;
				wp_reset_postdata();
			else :

				$string .= '<div class="post">
                            <h3>Spiacenti, non ci sono contenuti...</h3>
                            </div>';

			endif;

				$string .= '<a style="display:block; width: 100%; text-align:center" href="' . wp_logout_url( get_home_url() ) . '" title="Logout">Logout</a>';
				return $string;

		} else {

			$args = array(
				'echo' => false,
			);
			$string .= '<div class="site-main"><h2>Login</h2>' . wp_login_form( $args ) . '</div>';
			return $string;
		}
	}


}

$shortcode = new Shortcode();
