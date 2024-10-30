<?php
/*
	Plugin Name: Simple Download with password
	Plugin URI:  https://celtislab.net/en/wp-plugin-celtis-simple-download/
	Description: Simple, easy, lightweight file download with password protection
	Author: enomoto@celtislab
    Author URI: https://celtislab.net/
	Requires at least: 6.2
	Tested up to: 6.5
    Requires PHP: 7.4 
	Version: 0.8.0
    License: GPLv2
	Text Domain: celtis-simple-download  
    Domain Path: /languages/
*/

defined( 'ABSPATH' ) || exit;

if(!class_exists('\celtislab\v1_2\Celtis_sqlite')){
    require_once( __DIR__ . '/inc/sqlite-utils.php');
}
use celtislab\v1_2\Celtis_sqlite;

require_once( __DIR__ . '/celtisdl-utils.php');

define( 'CELTISDL_LOG_FILE', CELTISDL_lib::log_basedir() . '/celtis-simple-download/log.db' );

if(is_admin()) {
    register_activation_hook( __FILE__,   'celtisdl_plugin_activation' );
    register_deactivation_hook( __FILE__, 'celtisdl_plugin_deactivation' );
    register_uninstall_hook(__FILE__,     'celtisdl_plugin_uninstall');     
}

//プラグイン有効時の処理
//カスタム投稿タイプの初期登録ではパーマリンク設定と保存をするため flush_rewrite_rules 関数を呼び出す
function celtisdl_plugin_activation() {
    global $wp_filesystem;
    require_once( ABSPATH . 'wp-admin/includes/file.php');
    
    //log file (sqlite)
    wp_mkdir_p( CELTISDL_lib::log_basedir() . '/celtis-simple-download' );
    $htaccess = CELTISDL_lib::log_basedir() . '/celtis-simple-download/.htaccess';
    if (WP_Filesystem( $htaccess )) {
        if (!$wp_filesystem->exists( $htaccess )) {
            $wp_filesystem->put_contents( $htaccess, "Deny from all\n", FS_CHMOD_FILE);
        }
    }
    CELTISDL_lib::log_db_create( CELTISDL_LOG_FILE );
    
    //uploads/celtis-simple-download (multisite eg. uploads/sites/2/celtis-simple-download) path
    $upload = wp_upload_dir();
    wp_mkdir_p($upload['basedir'] . '/celtis-simple-download' );
    $index = $upload['basedir'] . '/celtis-simple-download/index.php';
    if (WP_Filesystem( $index )) {
        if (!$wp_filesystem->exists( $index )) {             
            $wp_filesystem->put_contents( $index, '// Silence is golden', FS_CHMOD_FILE);
        }
    }
    $htaccess = $upload['basedir'] . '/celtis-simple-download/.htaccess';
    if (WP_Filesystem( $htaccess )) {
        if (!$wp_filesystem->exists( $htaccess )) {
            $wp_filesystem->put_contents( $htaccess, "Order Deny,Allow\nDeny from all\n<FilesMatch '-\d+x\d+\.(jpg|jpeg|png|gif|webp)$'>\nOrder Allow,Deny\nAllow from all\n</FilesMatch>\n", FS_CHMOD_FILE);
        }
    }

    CELTISDL_lib::register_post_type( 'dl' );
    flush_rewrite_rules();
}

//プラグイン無効時の処理
function celtisdl_plugin_deactivation() {
    unregister_post_type( 'cs_download' );    
}

//プラグイン削除時の処理
function celtisdl_plugin_uninstall() {
    delete_option( 'celtisdl_rewrite_slug' );
    delete_option( 'celtisdl_option' );
    CELTISDL_lib::delete_post_data();
}

class CELTISDL_manager {

    static $m_option;
    static $dl_postid;

    public function __construct() {
        self::$m_option  = wp_parse_args( get_option('celtisdl_option', array()),  self::get_default_opt());
        self::$dl_postid = null;
      
        CELTISDL_lib::register_post_type( get_option( 'celtisdl_rewrite_slug', 'dl') );

        if (is_admin()) {            
            require_once( __DIR__ . '/celtisdl-setting.php');
            require_once( __DIR__ . '/celtisdl-logview.php');
            $setting = new CELTISDL_setting();
            
        } else {
            //WooCommerce に download url を登録すると失敗するのでダウンロードメソッドを redirect へ切り替える
            add_filter( 'woocommerce_file_download_method',  function( $method, $product_id, $path ){                
                $post_name = CELTISDL_manager::get_download_postname( $path );                
                if(!empty($post_name)){
                    global $wpdb;
                    $pid = absint( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'cs_download'", $post_name )));
                    if(!empty($pid)){
                        $method = 'redirect';                    
                    }
                }
                return $method;
            }, 10, 3 );
            
            self::$dl_postid = self::get_download_postid();
            if(!empty(self::$dl_postid)){
                //load_plugin_textdomain 呼び出し前にフックさせる
                add_filter( 'plugin_locale', array('CELTISDL_manager', 'referer_locale'), 10, 2 );
              
                require_once( __DIR__ . '/celtisdl-download.php');
                add_action( 'parse_request', array( 'CELTISDL_download', 'download_request' ) );
            } else {
                add_filter( 'post_password_expires', array( 'CELTISDL_lib', 'post_password_expires'), PHP_INT_MAX, 1 );

    			wp_register_style( 'celtisdl-style', plugins_url( 'style.css', __FILE__ ) );                
                add_shortcode( 'celtisdl_download', array( 'CELTISDL_lib', 'download_shortcode' ) );                
            }
        }
        load_plugin_textdomain('celtis-simple-download', false, basename( dirname( __FILE__ ) ).'/languages' );
    }
    
    public static function get_default_opt() {
        return array( 
            'unfiltered_upload' => 0,
            'pwskip_refurl'     => '',
            'prevent_dl_domain' => ''
        );            
    }

    //get download post name
    static function get_download_postname( $req_url ) {
        $req_url = wp_normalize_path( $req_url );
        $parse_url = wp_parse_url($req_url);
        $path = (!empty($parse_url['path']))? urldecode($parse_url['path']) : '';
        $query= (!empty($parse_url['query']))? urldecode($parse_url['query']) : '';
            
        $rewrite_slug = get_option( 'celtisdl_rewrite_slug', 'dl');
        $post_name = '';
        if(!empty($path) && strpos($path, "/$rewrite_slug/" ) !== false ){
            $sep = strpos($path, "/$rewrite_slug/" );
            if($sep !== false){
                $post_name = trim( substr($path, $sep + strlen("/$rewrite_slug/")), '/');
            }
        } elseif(!empty($query) && strpos($query, "$rewrite_slug" ) === 0 ){
            $sep = strpos($query, "$rewrite_slug=" );
            if($sep !== false){
                $post_name = substr($query, $sep + strlen("$rewrite_slug="));
            }            
        }
        return $post_name;
    }
    
    //get download post id
    //eg. plain: example.com/[wordpress]/?csdl=example-slug
    //not plain: example.com/[wordpress]/csdl/example-slug/
    static function get_download_postid() {
        $pid = 0;
		$req_method = (isset( $_SERVER['REQUEST_METHOD'] )) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';     
        if(in_array( $req_method, array( 'GET' )) && isset($_SERVER['REQUEST_URI'])){
            $post_name = self::get_download_postname( sanitize_text_field( wp_unslash($_SERVER['REQUEST_URI'])) );
            if(!empty($post_name)){
                global $wpdb, $post;
    			$pid = absint( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'cs_download'", $post_name )));
                if(!empty($pid)){
                    $post = get_post( $pid );
                }
            }
        }
        return $pid;
    }
    
	/**
	 * Filters the locale for download password form
	 *
	 * @param string $locale The locale.
	 */
    static function referer_locale( $locale, $domain ) {
        if($domain === 'celtis-simple-download') {
            $referer = wp_get_raw_referer();
            if(!empty($referer)) {            
                $rewrite_slug = get_option( 'celtisdl_rewrite_slug', 'dl');                
                if(empty($_COOKIE[ 'wp-celtisdl_' . COOKIEHASH ])){
                    if(strpos($referer, home_url() ) !== false && (strpos($referer, "/$rewrite_slug/" ) !== false || strpos($referer, "?$rewrite_slug=" ) !== false) ){                        
                        $referer = '';  //maybe password retry
                    }                      
                } else {
                    $ck_ref = esc_url( sanitize_text_field($_COOKIE['wp-celtisdl_' . COOKIEHASH ] ));
                    if(strpos($referer, home_url() ) !== false && $referer !== $ck_ref){
                        if(strpos($referer, "/$rewrite_slug/" ) !== false || strpos($referer, "?$rewrite_slug=" ) !== false ){
                            $referer = $ck_ref;  //maybe password retry
                        }                      
                    }
                }
                if(!empty($referer)){
                    $secure  = ( 'https' === wp_parse_url( $referer, PHP_URL_SCHEME ) );
                    setcookie( 'wp-celtisdl_' . COOKIEHASH, $referer, time() + 180, COOKIEPATH, COOKIE_DOMAIN, $secure );                                                
                    
                    //自サイトのみ refere ページを元にロケールを決める
                    if(strpos($referer, home_url() ) !== false) {            
                        $refid = url_to_postid( $referer );                
                        if(!empty($refid)){
                            //PLF locale を参照してロケールを設定
                            $c_locale = get_post_meta( $refid, '_locale', true );
                            if(!empty($c_locale)){
                                $locale = $c_locale;
                            }
                        }
                    }
                }
            }
        }
        return $locale;
    }
    
    public static function init() {
        $manager = new CELTISDL_manager;
    }
}
add_action( 'init', array( 'CELTISDL_manager', 'init' ) );
