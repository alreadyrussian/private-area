<?php

namespace private_area;
require_once PLUGIN_DIR . '/includes/class-info.php';
require_once PLUGIN_DIR . '/inc/class-shortcode.php';
require_once PLUGIN_DIR . '/inc/class-shortcode-form.php';

// Class Plugin

class Private_area {

    private $versione;

    function __construct(){
        // si scrivono gli hook in questa parte
        $this->versione = Info::VERSIONE;
        // 1) save custom post type
        add_action('init', array($this,'private_area_crea_contenuti'));
        // 2) force private custom post type
        add_filter('wp_insert_post_data', array($this, 'private_area_force_type_private'));
        // 3) subscriber can read private post
        $this -> private_area_subscriber();
        // 4) remove private from title
        add_filter('the_title', array($this,'private_area_clean_title'));
        // 5) hide bar for every user except admin
        add_filter('show_admin_bar', array($this,'private_area_hide_bar'), PHP_INT_MAX);
        // 6) metabox for the private custom post type page
        add_action("admin_init", array($this,'private_area_users_meta_init'));
        // 7) update meta box
        add_action('save_post', array($this,'private_area_save_userlist'));
        // 8) upload image for form
        add_action('custom_form_upload', array($this, 'private_area_form_single'));
        // 9) show document only in area-riservata-custom-page after the content
        add_filter('the_content',array($this,'private_area_get_document_user'));
        // 10) delete the attachment from the custom post page
        add_action('wp', array($this,'private_area_delete_attachment'));
        // 11) check if it is private custom post type page
        add_action('wp', array($this,'private_area_check_user_login'));
        
        
    }


    //--------------------------------------------------------
    // 1) create custom post type
    //--------------------------------------------------------
    public function private_area_crea_contenuti() {
        $labels = array(
            'name'               => __('Area Riservata'),
            'singular_name'      => __('Contenuto'),
            'add_new'            => __('Aggiungi Contenuto'),
            'add_new_item'       => __('Nuovo Contenuto'),
            'edit_item'          => __('Modifica Contenuto'),
            'new_item'           => __('Nuovo Contenuto'),
            'all_items'          => __('Elenco Contenuti'),
            'view_item'          => __('Visualizza Contenuti'),
            'search_items'       => __('Cerca Contenuto'),
            'not_found'          => __('Contenuto non trovato'),
            'not_found_in_trash' => __('Contenuto non trovato nel cestino'),
        );
    
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'rewrite'            => array('slug' => 'contenuti'),
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'supports'           => array(
                                    'title',
                                    'editor',
                                    'thumbnail',
                                    'comments'
                                    ),
    
        );
        register_post_type('area-riservata', $args);
        flush_rewrite_rules();
    }

    //--------------------------------------------------------
    // 2) force to be private custom post type
    //--------------------------------------------------------
    public function private_area_force_type_private($post)
    {
        if ($post['post_type'] == 'area-riservata') {
        
            if ($post['post_status'] == 'publish') {
                
                $post['post_status'] = 'private';
                
            } 
            
        }
        return $post;
    }


    //--------------------------------------------------------
    // 3) subscriber can read private post
    //--------------------------------------------------------
    public function private_area_subscriber(){
        
        $subRole = get_role( 'subscriber' );
        $subRole->add_cap( 'read_private_posts' );
        $subRole->add_cap( 'read_private_pages' );
        
    }

    //--------------------------------------------------------
    // 4) remove private from the title
    //--------------------------------------------------------
    function private_area_clean_title($titolo) {
        $titolo = esc_attr($titolo);
        $cerca = array(
            '#Privato:#'
        );
        $sostituisci = array(
            '-' // Sostituiamo la voce "Privato" con
        );
        $titolo = preg_replace($cerca, $sostituisci, $titolo);
        return $titolo;
    }


    //--------------------------------------------------------
    // 5) hide bar for everybody except admin
    //--------------------------------------------------------
    public function private_area_hide_bar(){
        if (current_user_can('administrator')) {
            return true;
        }
        return false;
    }


    //--------------------------------------------------------
    // 6) create metabox for custom post type
    //--------------------------------------------------------

    public function private_area_users_meta_init(){
        add_meta_box("users-meta", "Visibilità Utenti", array($this,"private_area_users"), "area-riservata", "normal", "high");
    }

    // lista degli utenti 
    public function private_area_users() {
        global $post;
    
        $custom = get_post_custom($post->ID);
        $users = isset($custom["users"][0]);
    
        $user_args  = array(
            // cerco solo gli utenti di tipo sottoscrittore
            'role' => 'Subscriber',
            'orderby' => 'display_name'
        );
        
        // creo la WP_User_Query object
        $wp_user_query = new \WP_User_Query($user_args);
        
        // richiamo i risultati
        $subscribers = $wp_user_query->get_results();
        
        // controllo i risultati
        if (!empty($subscribers))
        {
            // l'attributo di name è la chiave del customfield
            echo "<select name='users'>";
            
                echo '<option value="all">Tutti</option>';
                
            // loop che mostra tutti i sottoscrittori
            foreach ($subscribers as $subscriber){
            
                // richiamo i dati dei sottoscrittori
                $subscriber_info = get_userdata($subscriber->ID);
                $subscriber_id = get_post_meta($post->ID, 'users', true);
                
                if($subscriber_id == $subscriber_info->ID) { 
                    
                    $subscriber_selected = 'selected="selected"'; 
                    
                } else { 
                    
                    $subscriber_selected = ''; 
                }
                
                echo '<option value='.$subscriber_info->ID.' '.$subscriber_selected.'>'.$subscriber_info->display_name.'</option>';
            }
            echo "</select>";
        } else {
            echo 'No authors found';
        }
    
    }

    //--------------------------------------------------------
    // 7) update meta box
    //--------------------------------------------------------
    public function private_area_save_userlist(){
        global $post;
      
          if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
              return $post->ID;
          }
      
         update_post_meta($post->ID, "users", $_POST["users"]);
        
    }


    //--------------------------------------------------------
    // 8)  upload image for custom post type
    //--------------------------------------------------------
    
    public function private_area_form_single(){
        // carica il form per il front end di ogni custom post type per utenti verificati
        if(isset($_FILES['uploadfile']['name'])){
            
            global $current_user; // Use global
            $display_name_user = (get_userdata($current_user->ID)->display_name);
            
            if(!function_exists('wp_handle_upload')){
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $file = $_FILES['uploadfile'];

            $upload_overrides = array( 'test_form' => false);

            $uploaded_file = wp_handle_upload($file, $upload_overrides);

            
            if( isset( $uploaded_file ["file"] )  && !isset($uploaded_file["error"]) ) :
                global $post;
                // trim everything except the last slash from the url
                $pos = strrpos($uploaded_file['url'], '/');
                $id = $pos === false ? $uploaded_file['url'] : substr($uploaded_file['url'], $pos + 1);
                $removedot = strstr($id, '.', true);
                
                // get image 
                $file_name_and_location = $uploaded_file ["file"];
                // get the img name
                $file_title_for_media_library = $removedot;
                
                // load this kind of file -> tipo PRIVATE
                $attachment = array(
                    "post_mime_type" => $uploaded_file['type'],
                    "post_title" => addslashes( $file_title_for_media_library ),
                    "post_content" => "",
                    "post_status" => "private",
                    "post_parent" => $post->ID
                );

                /* if( ! is_null( $post )) {
                    if ( ! is_numeric( $post )) {
                        $post = $post->ID;
                    }
                    $attachment ['post_parent'] = $post;
                } */

                // link the array attachment with the file
                $id = wp_insert_attachment( $attachment, $file_name_and_location );
                require_once( ABSPATH."wp-admin/includes/image.php" );

                // generate the metadata for that image and update it
                $attach_data = wp_generate_attachment_metadata( $id, $file_name_and_location );
                wp_update_attachment_metadata( $id, $attach_data );
                
                echo "<meta http-equiv='refresh' content='0'>";

                return $_FILES['uploadfile']['name'] = '';
            else:
                echo $uploaded_file['error'];
            endif;
        }

        
    }

    
    //--------------------------------------------------------
    // 9) show documents
    //--------------------------------------------------------

    // show the document for specific pages and role
    public function private_area_get_document_user($content){
        global $post;
        global $current_user; // Use global
        $my_user_level = $current_user->user_level;
        $id_current_user = (get_userdata($current_user->ID)->ID);
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'private',
            // check what kind of file you want to upload
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'application/pdf' ),
            // every attachment from the media library will be shown
            'posts_per_page' => - 1,
        );
        $query_images = new \WP_Query( $args );
        $images = array();
        foreach ( $query_images->posts as $image) {
            if (is_single() && 'area-riservata' == $post->post_type) {
                // check if the author of the file is the current user
                if ($image->post_author == $id_current_user || 10 == $my_user_level) {
                    //apply_filters( 'the_content', $content );
                    $content .= '<li><a target="_self" href='.$image->guid.'>'.$image->post_title.'</a></li>
                    <form method="post"   enctype="multipart/form-data" >
                    <input type="hidden" name="name" value="'.$image->ID.'">
                    <input type="submit" name="submit" value="delete">
                    </form>';
                    

                    //$images[]= $image; --> wp_get_attachment_url()
                }
                // check if the id of the parent page is the actual id of the attachment and the user is the admin
            // quando carico un attachment, prende l'id della pagina da cui lo carico
            /* elseif($image->post_parent == $post->ID && $current_user->user_login == 'admin'){
                echo '<li><a target="_blank" href=' . wp_get_attachment_url($image->ID) . ' download>'. $image->post_title . '</a></li>';
            } */
            }     
            
        }
        return $content;

        //var_dump($images);
    }


    //--------------------------------------------------------
    // 10) delete attachment in the custom post page
    //--------------------------------------------------------
    public function private_area_delete_attachment(){
        global $post;
        if (is_single() && 'area-riservata' == $post->post_type) {
            if (isset($_POST['submit'])) {
                wp_delete_attachment($_POST['name']);
                echo "<meta http-equiv='refresh' content='0'>";
            }
        }
    }


    //--------------------------------------------------------
    // 11) check if it is a private custom post page
    //--------------------------------------------------------
    public function private_area_check_user_login(){
        global $post;
        global $current_user;
        $my_user_level = $current_user->user_level;
        if (is_singular('area-riservata')){
            // check if the current user has the same ID of the custom post type or admin level
            // and allow the user to see the post
            $check_meta = get_post_meta($post->ID, 'users', true);

            //------------------ CHECK IF user_id is the same as the metabox value for that page. -----------------------
            // If everything is ok, when save the custom post page
            // automatically the metabox gets the value(the ID) from the option value of the selection user option
            if ((get_userdata($current_user->ID)->ID) == $check_meta || 10 == $my_user_level) {
                return;  
            }
            else {
                die('Not allowed');
            }

            
        }
        // automatically Wp create an archive for custom post type
        // check if this page is the custom post type created
        if($post){
            if(is_post_type_archive($post->post_type) == 'area-riservata' && !is_user_logged_in()){
                // redirect to the home page
                // there is a specific page 
                wp_redirect(home_url());
            }
        }
        
    }
    
}