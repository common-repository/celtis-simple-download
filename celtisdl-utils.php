<?php

defined( 'ABSPATH' ) || exit;

use celtislab\v1_2\Celtis_sqlite;

class CELTISDL_lib {
    
    //Custom Post Type Create
    //登録データは独自の設定画面内にリスト表示するので最小限の設定
    static function register_post_type( $slug = '' ) {
        $args = array( 'public' => false );
        if(empty($slug)){
            $args[ 'rewrite' ] = array( 'with_front' => true );
        } else {
            $args[ 'rewrite' ] = array( 'slug' => $slug, 'with_front' => false );            
        }        
        $args = apply_filters( 'celtisdl_before_post_type_register', $args );
        
        //add custom post type - cs_download
        register_post_type( 'cs_download', $args );
    }
    
    //Custom Post and meta data Delete
    static function delete_post_data() {
        global $wpdb;
        $metakey = '_cs_download_data';
        $dl_pids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} AS post INNER JOIN {$wpdb->postmeta} AS meta ON (post.ID = meta.post_id) WHERE post.post_type = 'cs_download' AND meta.meta_key = %s ", $metakey ));
        if ( is_array( $dl_pids ) && ! empty( $dl_pids ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE post, meta FROM {$wpdb->posts} AS post INNER JOIN {$wpdb->postmeta} AS meta ON (post.ID = meta.post_id) WHERE post.post_type = 'cs_download' AND meta.meta_key = %s ", $metakey ));
        }
    }

	static function get_attach_file_name( $attach_id, $mime='' ) {
        $file_name = get_post_meta( $attach_id, '_wp_attached_file', true );
        if(strpos( $mime, 'image/') !== false){
            //メタデータに original_image(元画像)が定義されているときはファイルは元画像にする
            $meta = get_post_meta( $attach_id, '_wp_attachment_metadata', true );
            if ( ! empty( $meta['original_image'] ) ) {
                $file_name = preg_replace("#(/?)([^/]+?)$#", "$1{$meta['original_image']}", $file_name);
            }
        }
        return $file_name;
    }

    static function get_permalink( $post_name, $post_type ) {
        global $wp_rewrite;
        $post_link = '';
        if(!empty($post_name)){
            $post_link = $wp_rewrite->get_extra_permastruct( $post_type );
            if ( ! empty( $post_link ) ) {
                $post_link = str_replace( "%$post_type%", $post_name, $post_link );
            } else {
                $endpoint  = get_option( 'celtisdl_rewrite_slug', 'dl');
                $post_link = add_query_arg( $post_type, $post_name, '' );
                $post_link = str_replace( "?$post_type=", "?$endpoint=", $post_link );
            }
            $post_link = home_url( user_trailingslashit( $post_link ) );
        }
        return $post_link;
    }

    //Download Button shortcode
    //[celtisdl_download id="10"] 
    static function download_shortcode( $atts ) {
        global $wpdb;
        
        wp_enqueue_style( 'celtisdl-style' );

        extract( shortcode_atts( array('id' => 0 ), $atts));
        $id = absint($id);   
        $html = '';
        $post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} AS post INNER JOIN {$wpdb->postmeta} AS meta ON (post.ID = meta.post_id) WHERE (post.ID = %d AND post_type = 'cs_download' AND meta.meta_key = '_cs_download_data' )", $id ));
        if(!empty($post)){
            $meta = (!empty($post->meta_value))? (array)maybe_unserialize($post->meta_value) : array();
            $attach_name     = (!empty($meta['attach_name']))?    sanitize_text_field($meta['attach_name']) : '';
            $attach_fmtsize  = (!empty($meta['attach_fmtsize']))? sanitize_text_field($meta['attach_fmtsize']) : '';

            $file_name  = wp_basename( $attach_name );
            //$plink = get_post_permalink($id); 
            $link = CELTISDL_lib::get_permalink( $post->post_name, 'cs_download' );
          
            $html .= '<div class="button celtisdl-button wp-block-button aligncenter" >';
            $html .=   '<a class="celtisdl-button__link wp-block-button__link" href="' . esc_url($link) . '" rel="nofollow">';
            $html .=   esc_html__('Download', 'celtis-simple-download') . esc_html(" &ldquo;{$post->post_title}&rdquo;");
            $html .=   '<small style="display:block;">' . esc_html($file_name . ' - ' . $attach_fmtsize) . '</small>';
            $html .=   '</a>';                
            $html .= '</div>';                
        }
        
        $html = apply_filters( 'celtisdl_download_button_custom', $html, $post );        
        return $html;
    }

    //Download postpass expires change - ref:get_password_form()
    static function post_password_expires($expires) {
        if (did_action( 'login_form_postpass' ) !== 0 ) {
            if(isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['_wpnonce'] )), 'celtisdl_download_password' )){                
                if(isset($_POST['celtisdl_expires'])){
                    $expires = time() + absint($_POST['celtisdl_expires']);

                    do_action( 'celtisdl_expires_postpass' );
                }                
            }            
        }
        return $expires;
    }

    static function log_basedir() {
        $log_basedir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        $log_basedir = apply_filters( 'celtisdl_log_base_dir', $log_basedir );        
        return wp_normalize_path($log_basedir);
    }
    
    static function log_db_create( $dbfile, $recreate = false ) {
        $log_db = new Celtis_sqlite( $dbfile );
        if(!empty($log_db) && $log_db->is_open()){
            if($log_db->is_table_exist('log') === false || $recreate){
                $create_log_table = "CREATE TABLE log (
                    date datetime,
                    blogid int NOT NULL, 
                    postid int NOT NULL,
                    attachid int NOT NULL,
                    result int NOT NULL,
                    title text,
                    file_name text,
                    stat_info text,
                    user text,
                    exppw text,
                    ip text,
                    user_agent text,
                    referer text
                );";
                $log_db->command("PRAGMA synchronous = OFF");
                $log_db->command("PRAGMA journal_mode = DELETE");
                if($log_db->beginTransaction( 'IMMEDIATE' )){
                    try {
                        if($recreate){
                            $log_db->sql_exec( "DROP TABLE IF EXISTS log" );
                        }               
                        $log_db->sql_exec( $create_log_table );
                        $log_db->sql_exec( "CREATE INDEX date ON log (date);" );
                        $log_db->sql_exec( "CREATE INDEX dlid ON log (blogid,postid,attachid);" );
                        $log_db->commit();
                    } 
                    catch (Exception $e) {
                        $errmsg = $e->getMessage();
                        $log_db->rollback();
                    }                       
                }             
            }
            if($recreate){
                $log_db->command( 'VACUUM' );                        
            }
            $log_db->close();
        }
    }
}