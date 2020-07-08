<?php

// Class di attivazione
namespace private_area;

class ShortcodeForm {

	function __construct() {
		add_shortcode( 'privatepage-form', array( $this, 'private_area_custom_form' ) );
	}

	public function private_area_custom_form() {
		global $post;

		if ( is_single() && 'area-riservata' == $post->post_type ) {

			$form = "
                <form method='post' name='my-form' enctype='multipart/form-data'>
                    <div>
                        <label for='file'> Select File
                        </label>  
                        <input type='file' name='uploadfile'>
                        <input type='submit' name='btnSubmit' value='upload_file'>
                    </div>
                </form>";

			$form .= do_action( 'custom_form_upload' );

			// show both custom form and uploads
			// $form .= do_action('show_document_specific_user');
			return $form;

		}
	}



}

$shortcodeForm = new ShortcodeForm();
