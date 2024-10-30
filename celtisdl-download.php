<?php

defined( 'ABSPATH' ) || exit;

use celtislab\v1_2\Celtis_sqlite;

class CELTISDL_download {
    
    /**
     * Download password input form
     *
     * @param int|WP_Post $post Post ID or WP_Post object
     * @return string HTML content for password form for password protected post.
     */
    static function get_password_form( $post, $title, $notice = '' ) {
        $post  = get_post( $post );
        //wp-login.php login_form_postpass action hook
        $output = '<style>#celtisdl-password-form[data-notice]:after{ content:attr(data-notice) "\A"; display:block; padding:.5em 1em; color:#c00; background-color:#ffebe8;} #celtisdl-password-form p {font-size: 1em;} input[type=password]{padding: 0.5em 2px 0.5em 6px;} input[type=submit]{padding: 0.5em 1.5em; cursor:pointer;}</style>';     
        $output .= '<h1>' . esc_html__('Download', 'celtis-simple-download') . esc_html(" &ldquo;{$title}&rdquo;") . '</h1>';
        $output .= '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" id="celtisdl-password-form" class="post-password-form" method="post" ';
        if(!empty($notice)) {
            $output .= 'data-notice="' . esc_html($notice) . '"';
        }       
        $output .= '>';
        $output .= '<p>' . esc_html__( 'Please enter password to download:', 'celtis-simple-download' ) . '</p>';
        $output .= '<p><label for="pwbox-' . absint($post->ID) . '">' . esc_html__( 'Password:' ) . ' <input name="post_password" id="pwbox-' . absint($post->ID) . '" type="password" spellcheck="false" size="32" oninput="document.querySelector(\'#celtisdl-password-form\').removeAttribute(\'data-notice\')" /></label> <input type="submit" name="Submit" value="' . esc_attr_x( 'Enter', 'post password form' ) . '" /></p>';
        $output .= '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'celtisdl_download_password' ) . '" />';
        $output .= '<input type="hidden" name="celtisdl_expires" value="180" />';
        $output .= '</form>';
        
        if ( !empty($_COOKIE['wp-celtisdl_' . COOKIEHASH ]) ) {
            $referer = esc_url( sanitize_text_field($_COOKIE['wp-celtisdl_' . COOKIEHASH ]));
        } else {
            $rewrite_slug = get_option( 'celtisdl_rewrite_slug', 'dl');                
            $referer = wp_get_raw_referer();
            if(strpos($referer, "/$rewrite_slug/" ) !== false || strpos($referer, "?$rewrite_slug=" ) !== false ){
                $referer = ''; //maybe direct download link url request 
            }  
        }       
        if(strpos($referer, home_url() ) !== false && strpos($referer, '.php' ) === false){
            // Only download requests from own site Back link Display
            $output .= '<p><a href="' . esc_url($referer) . '">' . esc_html__( '&laquo; Back' ) . '</a></p>';
        } else {
            //If an external site or direct download URL is specified, a link to the home page will be displayed.
            $output .= '<p><a href="' . home_url() . '">' . esc_html__( '&laquo; Home' ) . '</a></p>';
        }           

        //Customization hooks for displaying advertisements, etc.
        return apply_filters( 'celtisdl_password_form_custom', $output, $post );
    }

    //password input form allowed tags
    public static function get_allowed_tags() {    
        global $allowedposttags;
        $allowed_tags = $allowedposttags;
        // style,form,input,select タグを許可
        $allowed_tags['style']  = array();                
        $allowed_tags['form']   = array( 'action' => true, 'accept' => true, 'accept-charset' => true, 'enctype' => true, 'method' => true, 'name' => true,	'target' => true, 'data-notice' => true );                
        $allowed_tags['input']  = array( 'type' => true, 'name' => true, 'value' => true, 'checked' => true, 'oninput' => true, 'size' => true, 'spellcheck' => true );
        $allowed_tags['select'] = array( 'name' => true, 'multiple' => true );
        $allowed_tags['option'] = array( 'value' => true, 'selected' => true );        
        $allowed_tags['button'] = array( 'href' => true, 'onclick' => true);
        $allowed_tags['a']['onclick'] = true;
        $allowed_tags = array_map( '_wp_add_global_attributes', $allowed_tags );
        return $allowed_tags;
    }
    
	/**
	 * Filters whether a post requires the user to supply a password.
	 *
	 * @param bool    $required Whether the user needs to supply a password. True if password has not been
	 *                          provided or is incorrect, false if password has been supplied or is not required.
	 * @param WP_Post $post     Post data.
	 */
    static function post_password_required($required, $post) {
        $m_opt = CELTISDL_manager::$m_option;                
        if($required){
            //Download requests for logged-in users with Capability do not require a password
            if(is_user_logged_in()){
                $meta   = get_post_meta( $post->ID, '_cs_download_data', true );
                $pwskip = (!empty($meta['pwskip_capa']))? sanitize_text_field($meta['pwskip_capa']) : '';
                if(!empty($pwskip)){
                    $capas = array_filter( array_map("trim", explode(',', $pwskip)));
                    foreach ($capas as $cp) {
                        if (current_user_can($cp, $post->ID)){
                            $required = false;
                            break;
                        }
                    }
                }                
            }
        }
        if($required){
            //Download requests from registered pages (e.g., checkout page) do not require a password
            if ( !empty( $m_opt['pwskip_refurl'] )) {
                $referer = wp_get_referer();
                if(!empty($referer) && strpos($referer, home_url() ) !== false) {
                    $refpid = url_to_postid( $referer );
                    $urls = array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $m_opt['pwskip_refurl'])));
                    foreach ($urls as $url) {
                        $pid = url_to_postid($url);
                        if(!empty($pid) && $pid === $refpid){
                            $required = false;
                            break;
                        }
                    }
                }
            }
        }      
        if($required){
            $required = apply_filters( 'celtisdl_exppw_required', $required, $post );
        }
        if($required){            
            if( isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] )) {
                //maybe password mismatch
                add_filter( 'celtisdl_password_stat', function($stat){
                    if(empty($stat)){ 
                        $stat = 'Password error';
                        $secure = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );
                        setcookie( 'wp-postpass_' . COOKIEHASH, '', time() - 60, COOKIEPATH, COOKIE_DOMAIN, $secure );
                    }
                    return $stat;                    
                });                
            }
        }
        return $required;
    }

    static function clear_cookie() {
        if(!empty($_COOKIE['wp-celtisdl_' . COOKIEHASH ])){
            $secure  = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );
            setcookie( 'wp-celtisdl_' . COOKIEHASH, '', time() - 60, COOKIEPATH, COOKIE_DOMAIN, $secure );                            
            setcookie( 'wp-postpass_' . COOKIEHASH, '', time() - 60, COOKIEPATH, COOKIE_DOMAIN, $secure );

            do_action( 'celtisdl_postpass_clear' );           
        }
    }

    static function put_log( $postid, $title, $attachid, $file_name, $stat_info = '' ) {      
        $log_db = new Celtis_sqlite( CELTISDL_LOG_FILE );
        if(!empty($log_db) && $log_db->is_open() && $log_db->is_table_exist('log')){
            $now    = new DateTime("now", new DateTimeZone('utc')); //utc で保存して表示時にローカルタイムに変換
            $date   = $now->format('Y-m-d H:i:s');
            $blogid = get_current_blog_id();
            $result = (empty($stat_info))? 1 : 0;
            $user   = wp_get_current_user();
            $userid = (!empty($user->ID))? absint($user->ID) : 0;            
            $exppw  = apply_filters( 'celtisdl_exppw_indata', '' );
            $ip     = (!empty($_SERVER["REMOTE_ADDR"]))? sanitize_text_field($_SERVER["REMOTE_ADDR"]) : '';
            $user_agent = (!empty($_SERVER['HTTP_USER_AGENT']))? sanitize_text_field($_SERVER["HTTP_USER_AGENT"]) : '';
            $referer = '';
            if ( !empty($_COOKIE['wp-celtisdl_' . COOKIEHASH ]) ) {
                $referer = urldecode( esc_url( sanitize_text_field($_COOKIE['wp-celtisdl_' . COOKIEHASH ])) );
            } elseif( !empty($_SERVER['HTTP_REFERER']) ){
                $referer = urldecode( esc_url( sanitize_text_field($_SERVER['HTTP_REFERER'])) );                
            }           
            
            $res = $log_db->sql_exec("INSERT INTO log ( date, blogid, postid, attachid, result, title, file_name, stat_info, user, exppw, ip, user_agent, referer) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )", 
                array($date, $blogid, $postid, $attachid, $result, $title, $file_name, $stat_info, $userid, $exppw, $ip, $user_agent, $referer ));

            $log_db->close();
        }
        return;
    }
    
    //download main routine
	static function download_request() {
        
        add_filter( 'post_password_required', array( 'CELTISDL_download', 'post_password_required'), 9999, 2 );
        
        if(!empty(CELTISDL_manager::$dl_postid)){           
            $postid      = 0;
            $title       = '';
            $attachid    = 0;
            $file_name   = '';
            $stat_info   = '';
            $file_path   = '';
            $post = get_post(CELTISDL_manager::$dl_postid);
            if ( !empty($post->ID)) {
                $postid = $post->ID;
                $title = $post->post_title;

                $meta = get_post_meta( $post->ID, '_cs_download_data', true );
                $attachid  = (!empty($meta['attach_id']))? absint($meta['attach_id']) : 0;
                $file_name = (!empty($meta['attach_name']))? sanitize_text_field($meta['attach_name']) : '';
                $file_mime = (!empty($meta['attach_mime']))? sanitize_text_field($meta['attach_mime']) : '';

                $m_opt = CELTISDL_manager::$m_option;
                $prevent = false;
                if ( !empty( $m_opt['prevent_dl_domain'] )) {
                    $referer = wp_get_raw_referer();
                    if(!empty($referer) && strpos($referer, home_url() ) === false) {
                        $domains = array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $m_opt['prevent_dl_domain'])));                        
                        foreach ($domains as $v) {
                            $host = wp_parse_url( $v, PHP_URL_HOST );
                            if(!empty($host) && strpos($referer, "$host" ) !== false){
                                $prevent = true;
                                break;
                            }
                        }
                    }
                }
                if(!$prevent){
                    if($post->post_status === 'publish'){
                        $upload    = wp_upload_dir();
                        $file_path = $upload['basedir'] . '/' . $file_name;                        
                        $mime_type = (!empty($file_mime))? $file_mime : 'application/octet-stream';

                        if (is_readable( $file_path )) {
                            //パスワード必要有無及び wp-postpass_xxxx クッキーにセットされているパスワード照合
                            $pwd_required = post_password_required( $post->ID );
                            $stat_info = apply_filters( 'celtisdl_password_stat', $stat_info );                
                            if ( $pwd_required ) {
                                //パスワード用フォーム表示 - 入力後　wp-login.php でパスワードは wp-postpass_xxxx クッキーにセットされダウンロードURLへリダイレクトされる、
                                if(!empty($stat_info)) {
                                    self::put_log( $postid, $title, $attachid, $file_name, $stat_info );                                            
                                }
                                wp_die( wp_kses(self::get_password_form( $post->ID, $title, $stat_info ), self::get_allowed_tags()) , 'Download Password Required', array('response' => 401) );
                            }

                            if ( !headers_sent()) {
                                self::put_log( $postid, $title, $attachid, $file_name );                    
                                self::clear_cookie();
                                ob_end_clean();

                                //※firefox で pdf がブラウザ表示される場合は、ブラウザの設定 プログラムのファイルの種類と取り扱い方法を確認
                                // Portable Document Format (PDF) が firefoxで開く になっていると別タブにPDF表示されるのでこの動作を止めたい場合は ファイルを保存 に変更
                                // Chrome でも同様の設定で自動的に表示される場合があり

                                header( 'Content-Description: File Transfer');
                                header( 'Content-Disposition: attachment; filename="' . wp_basename( $file_path ) . '"' );
                                header( 'Content-Type: ' . $mime_type );
                                header( 'X-Content-Type-Options: nosniff');
                                header( 'Expires: 0' );
                                header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
                                header( 'Pragma: public' );
                                header( 'Content-Length: ' . filesize( $file_path ) );
                                readfile( $file_path );
                                @flush(); 
                                exit;

                            } else {
                                $stat_info = 'Headers sent';
                            }
                        } else {
                            $stat_info = 'File cannot be read';                        
                        }                    
                    } else {
                        $stat_info = 'File not published';
                    }
                } else {
                    $stat_info = 'Prevent domain';                    
                }
            } else {
                $stat_info = 'Invalid url';                
            }
            self::put_log( $postid, $title, $attachid, $file_name, $stat_info );
			wp_die( 'Download failure.', 'Not Found', array('response' => 404));                                    
		}
	}
}