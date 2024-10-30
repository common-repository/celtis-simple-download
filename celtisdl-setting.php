<?php

defined( 'ABSPATH' ) || exit;

use celtislab\v1_2\Celtis_sqlite;

if(!class_exists('\celtisdl\hfu060\Hook_util')){
    require_once( __DIR__ . '/inc/hook-utils.php');            
}
use celtisdl\hfu060\Hook_util;
        
class CELTISDL_setting {
    
    static $m_opt;

    public function __construct() {
        self::$m_opt = CELTISDL_manager::$m_option;
        
        if (defined('DOING_AJAX')){
            add_action('wp_ajax_celtisdl_op_update',  array( 'CELTISDL_setting', 'ajax_op_update'));        
            add_action('wp_ajax_celtisdl_add_new',    array( 'CELTISDL_setting', 'ajax_add_new'));        
            add_action('wp_ajax_celtisdl_regist_file',array( 'CELTISDL_setting', 'ajax_regist_file'));                  
            add_action('wp_ajax_celtisdl_log_nav',    array( 'CELTISDL_log', 'ajax_celtisdl_log'));        
        } else {
            add_action( 'admin_init', array('CELTISDL_setting', 'action_posts'));                         
            if( current_user_can( 'publish_posts' ) ) {
                add_action( 'admin_menu', function(){
                    $page = add_menu_page('Simple Download with password', esc_html__('Downloads', 'celtis-simple-download'), 'publish_posts', 'celtisdl-manage-page', array('CELTISDL_setting', 'manage_page'), 'dashicons-download', 31);
                    add_submenu_page( 'celtisdl-manage-page', 'All Downloads', esc_html__('All Downloads', 'celtis-simple-download'), 'publish_posts', 'celtisdl-manage-page', array('CELTISDL_setting', 'manage_page') );
                    add_action('admin_print_styles-' .  $page, array('CELTISDL_setting', 'admin_css'));
                    add_action('admin_print_scripts-' . $page, array('CELTISDL_setting', 'admin_scripts'));
                    //Log only when sqlite is enabled
                    $log_db = new Celtis_sqlite( CELTISDL_LOG_FILE );
                    if(!empty($log_db) && $log_db->is_open()){
                        $log_db->close();
                        $logpage = add_submenu_page( 'celtisdl-manage-page', 'Log View', esc_html__('Log', 'celtis-simple-download'), 'publish_posts', 'celtisdl-log-view', array('CELTISDL_log', 'log_view') );
                        add_action('admin_print_styles-' .  $logpage, array('CELTISDL_log', 'admin_log_css'));
                        add_action('admin_print_scripts-' . $logpage, array('CELTISDL_log', 'admin_log_scripts'));
                    }
                });             
            }
        }

        //カスタム投稿タイプ cs_download の添付ファイルアップロードパスを celtis-simple-download サブディレクトリへ変更
        add_filter( 'upload_dir', function( $pathdata ){
            if(isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['_wpnonce'] )), 'media-form' )){                
                if(isset( $_POST['type'] ) && 'cs_download' === $_POST['type']){            
                    if ( empty( $pathdata['subdir'] ) ) {
                        $pathdata['path']   = $pathdata['path'] . '/celtis-simple-download';
                        $pathdata['url']    = $pathdata['url'] . '/celtis-simple-download';
                        $pathdata['subdir'] = '/celtis-simple-download';
                    } else {
                        $new_subdir = '/celtis-simple-download' . $pathdata['subdir'];
                        $pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
                        $pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
                        $pathdata['subdir'] = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['subdir'] );
                    }
                }
            }
            return $pathdata;                    
        });
        
        //管理者ユーザーなら unfiltered_upload 権限を許可（ロールへの許可も必要）
        add_filter( 'map_meta_cap', function( $caps, $cap, $user_id, $args ) {
            if($cap === 'unfiltered_upload' && is_array($caps)){
                if(is_super_admin( $user_id )){
                    $m_opt = CELTISDL_manager::$m_option;
                    if(!empty($m_opt['unfiltered_upload']) ){
                        if($caps[0] === 'do_not_allow' ){
                            $caps[0] = $cap;
                        }
                    } else {
                        if($caps[0] === 'unfiltered_upload' ){
                            $caps[0] = 'do_not_allow';
                        }                        
                    }
                }                
            }        
            return $caps;
        }, 10, 4);
                
        if(Hook_util::is_enable()){
            //ダウンロード用のアップロードファイルは wp-multibyte-patch のファイル名 md5 サニタイズから除外
            if(class_exists('multibyte_patch')){
                add_filter( 'sanitize_file_name', function( $filename, $filename_raw){
                    if(isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['_wpnonce'] )), 'media-form' )){                
                        if(isset( $_POST['type'] ) && 'cs_download' === $_POST['type']){                    
                            if ( class_exists('multibyte_patch_ext') ) {
                                //locale:ja
                                $hook = Hook_util::filter_id('wp-multibyte-patch/wp-multibyte-patch.php', 'multibyte_patch_ext::sanitize_file_name');
                            } else {
                                $hook = Hook_util::filter_id('wp-multibyte-patch/wp-multibyte-patch.php', 'multibyte_patch::sanitize_file_name');
                            }                
                            Hook_util::remove_hook('sanitize_file_name', $hook, 10);
                        }
                    }
                    return $filename;
                }, 1, 2);
            }
        }
        
        add_filter( 'big_image_size_threshold', array('CELTISDL_setting', 'big_image_size'), 10, 4 );                                   
    }
    
    static function admin_css() {
        wp_enqueue_style("wp-jquery-ui-dialog");
    ?>
    <style type="text/css">
span.document-link { font-size:13px; margin-left:16px; }        
.celtisdl-post-table thead, .celtisdl-post-table tbody { display:block;}   
.celtisdl-post-table tbody { overflow-y:auto; min-height:300px; height:50vh;}
.celtisdl-post-table tr { display:flex;}   
.widefat th, .widefat td { padding:6px 8px;}
table.widefat { margin-bottom:10px;}
.c-postname { width:12%;}
.c-summary  { width:36%;}
.c-password { width:10%;}
.c-shortcode{ width:32%;}
.c-editlink { width:10%;}
.celtisdl-file-info { display:flex; overflow:hidden; line-height:1.5; color:#646970;}
.celtisdl-file-info .thumbnail { margin:0 10px 0 0;}
.widefat td.c-postname { font-size:15px; font-weight:bold;}
.widefat .celtisdl-active td { background-color:#f0fcfe;}
.widefat .celtisdl-active td.c-postname { border-left:4px solid #00a0d2; }
.widefat p.memo-text { margin:2px; font-size:13px; line-height:1.5em; background-color:#fbf7dc;}
.c-password .pwskip { color:#008000; }
.celtisdl-submit { padding-bottom:1em; }
.celtisdl-submit span { margin-left:2em;}
#celtisdl-post-edit-form .widefat th { width:20%; pointer-events:none; user-select:none;}
#celtisdl-post-edit-form[input-error]:after { content:attr(data-notice) "\A"; width:auto; display:block; font-size:13px; border-width:1px; border-style:solid; padding:.5em 1em; border-radius:3px; background-color:#ffebe8; border-color:#c00; white-space:pre;}
#celtisdl-post-edit-form.hide { display: none; }
@media screen and (max-width: 782px){ .regular-text { max-width:100%; width:100%;}}
@media screen and (max-width: 476px){ .widefat td, .widefat th { padding:8px 4px;}}
#celtisdl-post-edit-items { border:none; }
img.contain { object-fit:contain;}
input[readonly].celtisdl-code { width:100%; padding:0 4px; line-height:1.6; min-height:20px; border:none; margin-bottom:1px; background-color:transparent;}
input[type="text"]::placeholder, textarea::placeholder {color:#aaa;}
.c-editlink {word-break:auto-phrase;}
.c-editlink > span { padding:4px 0; }
.c-editlink > span:first-child { margin-right:1.5em;}
.c-editlink a.delete {color:#a00;}
.celtisdl-extended-option select { vertical-align:baseline; }
.celtisdl-extended-option details { border:1px solid #c3c4c7;}
.celtisdl-extended-option details table.widefat { border:none; border-top:1px solid #c3c4c7;}
.celtisdl-extended-option summary {font-size:15px; padding:.7em; background:#fff; cursor:pointer;}
    </style>
    <?php 
        do_action( 'celtisdl_additional_admin_css' );       
    }
    
    static function admin_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_media();

        wp_enqueue_script( 'celtisdl-adminjs', plugins_url( 'js/celtisdl-admin.js', __FILE__ ), array('jquery') );
        $user = wp_get_current_user();
        $localization = array(
            'unfiltered'  => ( !$user || !$user->exists() || !$user->has_cap( 'unfiltered_upload' ))? false: true,
            'L_dlgtitle'  => esc_html__("Select Download File", 'celtis-simple-download'),
            'L_inpnotice' => esc_html__("There is data that has not been entered yet. Fields marked with * are required data.", 'celtis-simple-download'),
            'L_save'      => esc_html__("Save", 'celtis-simple-download'),
            'L_exit'      => esc_html__("Cancel", 'celtis-simple-download'),
        );        
        wp_localize_script('celtisdl-adminjs', 'extopt', $localization );

        do_action( 'celtisdl_additional_admin_script' );              
    }

    /**
     * dropdown list
     *
     * @param string $name - HTML field name
     * @param array  $items - array of (key => description) to display.  If description is itself an array, only the first column is used
     * @param string $selected - currently selected value
     * @param mixed  $args - arguments to modify the display
     */
    static function dropdown($name, $items, $selected, $args = null, $display = false) {
        $defaults = array(
            'id' => $name,
            'none' => false,
            'class' => null,
            'multiple' => false,
        );

        if (!is_array($items))
            return;

        if (empty($items))
            $items = array();

        // Items is in key => value format.  If value is itself an array, use only the 1st column
        foreach ($items as $key => &$value) {
            if (is_array($value))
                $value = array_shift($value);
        }

        extract(wp_parse_args($args, $defaults));

        // If 'none' arg provided, prepend a blank entry
        if ($none) {
            if ($none === true)
                $none = '&nbsp;';
            $items = array('' => $none) + $items;    // Note that array_merge() won't work because it renumbers indexes!
        }

        if (!$id)
            $id = $name;

        $name  = ($name) ? ' name="' . esc_html($name) . '"' : '';
        $id    = ($id)   ? ' id="'   . esc_html($id)   . '"' : '';
        $class = ($class)? ' class="'. esc_html($class). '"' : '';
        $multiple = ($multiple) ? ' multiple="multiple"' : '';

        $html  = '<select' . $name . $id . $class . $multiple  .'>';
        foreach ((array) $items as $key => $label) {
            $html .= '<option value="' . esc_html($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        if($display){
            echo wp_kses( $html, array(
                'select' => array( 'name' => true, 'id' => true, 'class' => true, 'multiple' => true ), 
                'option' => array( 'value' => true, 'selected' => true )
                ));
        } else {
            return $html;
        }
    }

    //===========================================================================================
    //Image files uploaded from this plugin will not be resized even if they are large images.
    static function big_image_size( $threshold, $imagesize, $file, $attachment_id) {
        if(!empty($file) && strpos( $file, 'celtis-simple-download/') !== false){
            return false;
        }
        return $threshold;
    }   
     
    //===========================================================================================
    //wp_ajax called function
    //===========================================================================================
    static function ajax_op_update() {
        check_ajax_referer("celtisdl_op_update");
        if (isset($_POST['action']) && $_POST['action'] === 'celtisdl_op_update') {
            if( !current_user_can( 'manage_options' ) )
                wp_die(-1);
                
            $rewrite_slug = get_option( 'celtisdl_rewrite_slug', 'dl');
            $new_slug     = (isset($_POST['rewrite_slug']))? sanitize_text_field( $_POST['rewrite_slug'] ) : $rewrite_slug;
            $reload       = false;
            if($new_slug !== $rewrite_slug){
                update_option( 'celtisdl_rewrite_slug', $new_slug );
                CELTISDL_lib::register_post_type( $new_slug );
                flush_rewrite_rules();
                $reload = true;
            }

            $unfiltered = ( !empty($_POST['unfiltered_upload']) && sanitize_text_field( $_POST['unfiltered_upload'] ) == 'true') ? 1 : 0 ;
            if(self::$m_opt['unfiltered_upload'] != $unfiltered){
                if(is_super_admin()){
                    $admin = (is_multisite())? 'super' : 'administrator';
                    $role  = get_role( $admin );
                    if($unfiltered){
                        $role->add_cap( 'unfiltered_upload' );                    
                    } else {
                        $role->remove_cap( 'unfiltered_upload' );                    
                    }
                    $reload = true;
                }
            }
            self::$m_opt['unfiltered_upload'] = $unfiltered;
            
            $rurls = (!empty($_POST['pwskip_refurl'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", esc_textarea( _sanitize_text_fields($_POST['pwskip_refurl'], true))))) : '';
            if(!empty($rurls)){
                $rurls = implode("\n", $rurls);
            }
            self::$m_opt['pwskip_refurl'] = $rurls;
            
            $domains = (!empty($_POST['prevent_dl_domain'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", esc_textarea( _sanitize_text_fields($_POST['prevent_dl_domain'], true))))) : '';
            if(!empty($domains)){
                $domains = implode("\n", $domains);
            }
            self::$m_opt['prevent_dl_domain'] = $domains;            
            update_option( 'celtisdl_option', self::$m_opt );

            $response = array();
            $response['success'] = true;
            $response['reload']  = $reload;
            $response['rewrite_slug']      = esc_html($new_slug);
            $response['unfiltered_upload'] = absint($unfiltered);
            $response['pwskip_refurl']     = esc_html($rurls);
            $response['prevent_dl_domain'] = esc_html($domains);

            ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア
            wp_send_json( $response );
        } 
        wp_die(0);
    }
    
    static function ajax_add_new() {
        check_ajax_referer("celtisdl_post_edit");
        if (isset($_POST['action']) && $_POST['action'] === 'celtisdl_add_new') {
            if( !current_user_can( 'publish_posts' ) )
                wp_die(-1);

            global $wpdb;
            $title = 'new-download';
            $post  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE (post_type = 'cs_download' AND post_title = %s )", $title ));
            if(!empty($post)){
                $post_id = $post->ID;
            } else {
                $new_post = array(
                    'post_type'     => 'cs_download',
                    'post_title'    => 'new-download'
                );
                $post_id = wp_insert_post($new_post);                
            }            
            if(!empty($post_id)) {
                $response = array();
                $response['success'] = true;
                $response['pid'] = $post_id;

                ob_end_clean();
                wp_send_json( $response );
            }
        } 
        wp_die(0);
    }
    
    static function ajax_regist_file() {
        check_ajax_referer("celtisdl_post_edit");
        if (isset($_POST['action']) && $_POST['action'] === 'celtisdl_regist_file') {
            if( !current_user_can( 'publish_posts' ) )
                wp_die(-1);

            $post_id = (isset($_POST['post_id']))? absint( $_POST['post_id']) : 0;
            $post = (array)get_post( $post_id );
            if(!empty($post) && isset($_POST['post_title']) && $post['post_type'] === 'cs_download' ){
                //custom post data update
                $post['ID'] = $post_id;
                if(preg_match('/^[0-9A-Za-z_\-]+$/', sanitize_text_field( $_POST['post_title'] ), $ttl)){
                    if($post['post_title'] !== $ttl[0]){
                        //post_name には wp_unique_post_slug() のより post_title から生成したユニークなスラッグがセットされるが
                        //title が変更されたときにはリセットして再設定させる
                        $post['post_name'] = '';
                    }
                    $post['post_title'] = $ttl[0];
                }
                $post['post_status']    = (isset($_POST['post_status']))?   sanitize_text_field( $_POST['post_status'] ) : 'draft';
                $post['post_excerpt']   = (isset($_POST['post_excerpt']))?  sanitize_text_field( $_POST['post_excerpt'] ) : '';
                $post['post_password']  = (isset($_POST['post_password']))? sanitize_text_field( $_POST['post_password']) : '';
                if(!in_array($post['post_status'], array('publish', 'draft')) || empty($post['post_password'])){
                    //パスワードは必須なので未指定なら公開不可
                    $post['post_status']  = 'draft' ;
                }
                $updated = wp_update_post( $post );
                
                //post meta data update
                $meta = get_post_meta( $post_id, '_cs_download_data', true );                
                if(empty($meta)) {
                    $meta = array();
                }
                $attach_id = (isset($_POST['attach_id']))? absint($_POST['attach_id']) : 0;
                $a_post = (array)get_post( $attach_id );
                $attach_parent = (!empty($a_post['post_parent']))? absint($a_post['post_parent']) : 0;
                if(!empty($a_post['ID']) && empty($attach_parent)){
                    //post に添付されていないファイルなら紐づける
                    $a_post['post_parent'] = $post_id;
                    wp_update_post( $a_post );
                }                
                $meta['attach_id']      = $attach_id;
                $meta['attach_parent']  = $attach_parent;
                $meta['attach_mime']    = (isset($_POST['attach_mime']))?    sanitize_text_field($_POST['attach_mime']) : '';
                $meta['attach_name']    = CELTISDL_lib::get_attach_file_name( $attach_id, $meta['attach_mime'] );
                $meta['attach_icon']    = (isset($_POST['attach_icon']))?    esc_url( sanitize_text_field($_POST['attach_icon'])) : '';
                $meta['attach_fmtsize'] = (isset($_POST['attach_fmtsize']))? sanitize_text_field($_POST['attach_fmtsize']) : '';
                $meta['attach_imgratio']= (isset($_POST['attach_imgratio']))?sanitize_text_field($_POST['attach_imgratio']) : '';
                $meta['pwskip_capa']    = (isset($_POST['pwskip_capa']))?    sanitize_text_field($_POST['pwskip_capa']) : '';
                update_post_meta( $post_id, '_cs_download_data', $meta );
                
                $response = array();
                $response['success'] = true;
                $response['pid'] = $post_id;

                ob_end_clean();
                wp_send_json( $response );
            }            
        } 
        wp_die(0);
    }
    
    /***************************************************************************
     * Settings
     **************************************************************************/
    static function action_posts() {
        if( !empty($_GET['page']) && $_GET['page'] === 'celtisdl-manage-page' ) {
            if(!empty($_GET['action']) && $_GET['action'] === 'delete') {
                check_admin_referer( 'celtisdl_post_delete' );                
                if( !empty($_GET['id'])){
                    $postid = absint($_GET['id']);
                    if( current_user_can( 'delete_post', $postid )) {
                        wp_delete_post( $postid, true );
                        wp_safe_redirect(admin_url('admin.php?page=celtisdl-manage-page'));
                    }
                }
                exit;
            }                
        } elseif( !empty($_GET['page']) && $_GET['page'] === 'celtisdl-log-view' ) {
            if (!empty($_GET['action']) && $_GET['action'] === 'reset') {
                check_admin_referer( 'celtisdl_log_reset' );
                if( current_user_can( 'manage_options' ) ) {
                    CELTISDL_lib::log_db_create( CELTISDL_LOG_FILE, true );
                    wp_safe_redirect(admin_url('admin.php?page=celtisdl-log-view'));
                }
                exit;
            } elseif (!empty($_GET['action']) && $_GET['action'] === 'export') {
                check_admin_referer( 'celtisdl_log_export' );
                if( current_user_can( 'manage_options' ) ) {
                    $file_path = CELTISDL_LOG_FILE;
                    $file_name = 'download-log-' . date_i18n( 'Y-m-d' ) . '.db';
                    ob_end_clean();

                    header( 'Content-Description: File Transfer');
                    header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
                    header( 'Content-Type: application/octet-stream' );
                    header( 'X-Content-Type-Options: nosniff');
                    header( 'Expires: 0' );
                    header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
                    header( 'Pragma: public' );
                    header( 'Content-Length: ' . filesize( $file_path ) );
                    // WP_Filesystem class does not have a readfile method ($wp_filesystem->readfile()) to directly output file contents.
                    readfile( $file_path );
                    @flush(); 
                }
                exit;                    
            }
        }
    }
    
    //Custom post cs_download data registration dialog
    private static function _post_edit_items( $post=null  ) {
        $defaults = array(
            'post_id'        => 0,
            'post_title'     => '',
            'post_name'      => '',
            'post_excerpt'   => '',
            'post_status'    => 'draft',
            'post_type'      => 'cs_download',
            'post_password'  => '',
            'attach_id'      => 0,
            'attach_parent'  => 0,
            'attach_name'    => '',
            'attach_icon'    => '',
            'attach_fmtsize' => '',
            'attach_mime'    => '',
            'attach_imgratio'=> '',
            'pwskip_capa'    => '',        
            'file_date'      => ''            
        );
        $item = array();
        if(is_object($post)){
            $item['post_id']        = $post->post_id;
            $item['post_title']     = $post->post_title;
            $item['post_name']      = $post->post_name;
            $item['post_excerpt']   = $post->post_excerpt;
            $item['post_status']    = $post->post_status;
            $item['post_type']      = $post->post_type;
            $item['post_password']  = $post->post_password;

            $meta = (!empty($post->meta_value))? (array)maybe_unserialize($post->meta_value) : array();
            $item['attach_id']       = (!empty($meta['attach_id']))?      absint($meta['attach_id']) : 0;
            $item['attach_parent']   = (!empty($meta['attach_parent']))?  absint($meta['attach_parent']) : 0;
            $item['attach_name']     = (!empty($meta['attach_name']))?    sanitize_text_field($meta['attach_name']) : '';
            $item['attach_icon']     = (!empty($meta['attach_icon']))?    esc_url( sanitize_text_field($meta['attach_icon'])) : '';
            $item['attach_fmtsize']  = (!empty($meta['attach_fmtsize']))? sanitize_text_field($meta['attach_fmtsize']) : '';
            $item['attach_mime']     = (!empty($meta['attach_mime']))?    sanitize_text_field($meta['attach_mime']) : '';
            $item['attach_imgratio'] = (!empty($meta['attach_imgratio']))?sanitize_text_field($meta['attach_imgratio']) : '';
            $item['pwskip_capa']     = (!empty($meta['pwskip_capa']))?    sanitize_text_field($meta['pwskip_capa']) : '';

            $upload  = wp_upload_dir();
            $actfile = is_file( $upload['basedir'] . '/' . $item['attach_name'] );
            $item['file_date'] = ($actfile)? gmdate("Y-m-d H:i", filemtime( $upload['basedir'] . '/' . $item['attach_name'] )) : '';
        }        
        $item = wp_parse_args( $item, $defaults );
        return $item;
    }
    
    //Custom post cs_download Registered data list display
    public static function posts_display( ) {
        global $wpdb;
        $mnglist = array();
        $metakey = '_cs_download_data';
        if(current_user_can( 'edit_others_posts' )){
            //SELECT * FROM wp_posts AS post INNER JOIN wp_postmeta AS meta ON ( post.ID = meta.post_id ) WHERE ( post_type = 'cs_download' AND meta.meta_key = '_cs_download_data' ) ORDER BY post_name ASC
            $mnglist = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} AS post INNER JOIN {$wpdb->postmeta} AS meta ON (post.ID = meta.post_id) WHERE (post_type = 'cs_download' AND meta.meta_key = %s ) ORDER BY post_name ASC", $metakey ), OBJECT_K);
        } else {
            $user   = wp_get_current_user();
            $userid = (!empty($user->ID))? absint($user->ID) : 0;
            if(!empty($userid)){
                $mnglist = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} AS post INNER JOIN {$wpdb->postmeta} AS meta ON (post.ID = meta.post_id) WHERE (post_author = %d AND post_type = 'cs_download' AND meta.meta_key = %s ) ORDER BY post_name ASC", $userid, $metakey ), OBJECT_K);                
            }
        }
    ?>
    <div class="wrap">
        <form method="post" autocomplete="off">
            <table class="widefat celtisdl-post-table">
              <thead>
                <tr>
                  <th class="c-postname"><?php  esc_html_e('Name', 'celtis-simple-download'); ?></th>
                  <th class="c-summary" ><?php  esc_html_e('Summary', 'celtis-simple-download'); ?></th>
                  <th class="c-password"><?php  esc_html_e('Password / Skip', 'celtis-simple-download'); ?></th>
                  <th class="c-shortcode"><?php esc_html_e('ShortCode / URL', 'celtis-simple-download'); ?></th>
                  <th class="c-editlink"></th>
                </tr>
              </thead>
              <tbody>
              <?php if (!empty($mnglist)) {
                $upload = wp_upload_dir();
                foreach ($mnglist as $pobj) {                    
                    if(empty($pobj->post_id || empty($pobj->meta_value))){
                        continue;                        
                    }
                    $item = self::_post_edit_items( $pobj );

                    $actfile = is_file( $upload['basedir'] . '/' . $item['attach_name'] );
                    $active  = '';
                    if( $item['post_status'] === 'publish' && $actfile){
                        $active = 'celtisdl-active';
                    }
                    ?>
                    <tr id="celtisdl-post-<?php echo absint($pobj->post_id) . '" class="' . esc_html($active); ?>">
                      <td class="c-postname"><?php echo esc_html($item['post_title']); ?></td>
                      <td class="c-summary">
                        <div class="celtisdl-file-info">
                          <div class="thumbnail thumbnail-application">
                          <?php if(!empty($item['attach_icon'])){ ?>                   
                            <img src="<?php echo esc_url($item['attach_icon']) ?>" class="icon contain" width="48" height="48" alt="">
                          <?php } ?>
                          </div>
                          <div class="details">
                            <div class="summary">
                                <?php echo esc_html($item['post_excerpt']); ?>
                            </div>
                            <div class="filename">
                            <?php if($actfile){
                                echo esc_html($item['attach_name']) . ' (' . esc_html($item['attach_fmtsize']) . ')';
                                if(!empty($item['attach_parent']) && absint($item['attach_parent']) !== absint($item['post_id'])){
                                    echo '<div class="description">' . esc_html__('[Uploaded to another post]', 'celtis-simple-download') . '</div>';                          
                                }
                            } else {
                                echo '<span class="file-error">' . esc_html($item['attach_name']) . '</span>';
                            } ?>   
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="c-password">
                        <div class="password">
                            <?php 
                            echo esc_html($item['post_password']);
                            do_action( 'celtisdl_password_util_link', $item ); 
                            ?>
                        </div>
                        <div class="pwskip"><?php echo esc_html($item['pwskip_capa']); ?></div>
                      </td>
                      <td class="c-shortcode">
                        <?php
                        //shortcode [celtisdl_download id=xxxx]
                        //URL : plain     - example.com/[wordpress]/?csdl=example-slug
                        //      not plain - example.com/[wordpress]/csdl/example-slug/
                        $url = CELTISDL_lib::get_permalink( $item['post_name'], 'cs_download' );
                        $scd = (!empty($url))? '[celtisdl_download id="' . absint($pobj->post_id) . '"]': '';
                        ?>
                        <div><input type="text" name="celtisdl-shortcode" class="celtisdl-code" value='<?php echo esc_html($scd); ?>' readonly onfocus="this.select()" /></div>    
                        <div><input type="text" name="celtisdl-linkurl" class="celtisdl-code" value="<?php echo esc_url($url); ?>" readonly onfocus="this.select()" /></div>
                      </td>
                      <td class="c-editlink">
                        <?php //jsonデータ内の " ダブルクオテーションをエスケープしないと正常にJSを呼び出せない
                        $item['attach_name'] = wp_basename($item['attach_name']); //ファイル名のみにする
                        $escjsondt = addslashes(wp_json_encode($item));
                        $nonce = wp_create_nonce('celtisdl_post_edit');
                        ?>
                        <span><a href="#" onclick='CELTISDL_post_edit("<?php echo absint($pobj->post_id) ?>","<?php echo esc_html($nonce); ?>","<?php echo esc_html($escjsondt); ?>");return false;'><?php esc_html_e('Edit', 'celtis-simple-download'); ?></a></span>
                        <span><a class="delete" href="<?php echo esc_url(wp_nonce_url('admin.php?page=celtisdl-manage-page&amp;action=delete&amp;id=' . absint($pobj->post_id), "celtisdl_post_delete")); ?>" onclick="return confirm('<?php esc_html_e('Simple Download with password\nClick OK to delete it.', 'celtis-simple-download'); ?>');" ><?php esc_html_e('Delete', 'celtis-simple-download'); ?></a></span>                          
                      </td>
                    </tr>
                    <?php
                }
              } 
              ?>
              </tbody>
            </table>
            <div class="celtisdl-button-wrap">
                <span><input type="submit" name="celtisdl_add_new" class="button button-primary" value="<?php esc_html_e('Add New', 'celtis-simple-download') ?>" onclick="CELTISDL_add_new('<?php echo esc_html(wp_create_nonce('celtisdl_post_edit')); ?>');return false;" /></span>
            </div>
        </form>
    </div>
    <div id="celtisdl-post-edit-dialog" title="<?php esc_html_e('Download File', 'celtis-simple-download') ?>" style="display : none;">
        <div id="celtisdl-loading"></div>
        <form id="celtisdl-post-edit-form" data-notice="">
          <table id="celtisdl-post-edit-items" class="widefat">
            <?php
            $item = self::_post_edit_items();
            if($item['post_title'] === 'new-download'){
                $item['post_title'] = '';
            }
            if($item['post_status'] === 'publish'){
                $active   = 'checked';
                $deactive = '';
            } else {
                $active   = '';
                $deactive = 'checked';            
            }                
            ?>                
            <tbody>
              <tr valign="top">
                <th scope="row"><?php esc_html_e('Name (*)', 'celtis-simple-download') ?></th>
                <td>
                  <input type="text" class="medium-text" name="post_title" required aria-required="true" pattern="^[0-9A-Za-z_\-]+$" value="<?php echo esc_html($item['post_title']); ?>" oninput="CELTISDL_validinput(this)" />
                  <p class="memo-text"><?php esc_html_e('Name will be used as a unique slug in the download URL. (valid characters are half-width alphanumeric characters, hyphens, and underscores)', 'celtis-simple-download') ?></p>
                </td>
              </tr>
              <tr valign="top">
                 <th scope="row"><?php esc_html_e('Summary', 'celtis-simple-download') ?></th>
                 <td>
                   <input type="text" class="large-text" name="post_excerpt" value="<?php echo esc_html($item['post_excerpt']); ?>" />
                 </td>
               </tr>
               <tr valign="top">
                 <th scope="row"><?php esc_html_e('Register file (*)', 'celtis-simple-download') ?></th>
                 <td>
                   <div class="attachment-info">
                     <div id="celtisdl_file_thumb" class="thumbnail thumbnail-application">
                       <?php if(!empty($item['attach_icon'])){ ?>                   
                         <img src="<?php echo esc_url($item['attach_icon']) ?>" class="icon" alt="">
                       <?php } ?>
                     </div>
                     <div class="details">
                       <div id="celtisdl_file_name" class="filename">
                         <?php 
                         if(!empty($item['file_date'])){
                             echo esc_html($item['attach_name']);                        
                         } else {
                             echo '<span class="file-error">' . esc_html($item['attach_name']) . '</span>';                        
                         }
                         ?>
                       </div>
                       <div id="celtisdl_file_date" class="uploaded"><?php echo esc_html($item['file_date']); ?></div>
                       <div id="celtisdl_file_size" class="file-size"><?php echo esc_html($item['attach_fmtsize']); ?></div>
                       <div id="celtisdl_img_ratio" class="dimensions"><?php echo esc_html($item['attach_imgratio']); ?></div>                     
                     </div>
                   </div>
                   <input id="celtisdl_attach_id" type="hidden" name="celtisdl_attach_id" value="<?php echo absint($item['attach_id']); ?>" />
                   <input id="celtisdl_attach_mime" type="hidden" name="celtisdl_attach_mime" value="<?php echo esc_html($item['attach_mime']); ?>" />
                   <input id="celtisdl_attach_icon" type="hidden" name="celtisdl_attach_icon" value="<?php echo esc_url($item['attach_icon']); ?>" />                
                   <p class="description"><?php esc_html_e('Click `Select File` button to upload/select the file to register', 'celtis-simple-download') ?></p>
                   <p><input id="celtisdl_uploader_button" type="button" class="button" value="<?php esc_html_e('Select File', 'celtis-simple-download') ?>" /></p>
                 </td>
               </tr>
               <tr valign="top">
                 <th scope="row"><?php esc_html_e('Download Activate', 'celtis-simple-download') ?></th>
                 <td>
                   <fieldset>            
                     <div class="dlg-select-item"><label><input type="radio" name="p-mode" value="draft" <?php echo esc_html($deactive); ?> /><?php esc_html_e('Deactive', 'celtis-simple-download') ?></label></div>
                     <div class="dlg-select-item"><label><input type="radio" name="p-mode" value="publish" <?php echo esc_html($active); ?> /><?php esc_html_e('Active', 'celtis-simple-download') ?></label></div>
                     <div class="dlg-sub-item"><?php esc_html_e('Password (*) ', 'celtis-simple-download') ?><input type="text" name="p-password" required aria-required="true" value="<?php echo esc_html($item['post_password']); ?>" />
                       <p class="memo-text"><?php esc_html_e('To prevent fraud, a download password is required. However, specific users can be excluded with password-skip option.', 'celtis-simple-download') ?></p>             
                     </div>
                   </fieldset>
                 </td>
               </tr>
               <tr valign="top">
                 <th scope="row"><?php esc_html_e('Password skip by user', 'celtis-simple-download') ?></th>
                 <td>
                   <input type="text"  name="celtisdl_pwskip_capa" value="<?php echo esc_html($item['pwskip_capa']); ?>" />
                   <p class="memo-text"><?php esc_html_e('No password is required for logged in user with ', 'celtis-simple-download') ?><a target="_blank" rel="noopener" href="https://wordpress.org/documentation/article/roles-and-capabilities/"><?php esc_html_e('capabilities', 'celtis-simple-download') ?></a><br><?php esc_html_e('( Set `read` for all logged in users )', 'celtis-simple-download') ?></p>
                 </td>
               </tr>
            </tbody>                
          </table>
        </form>
    </div>
    <?php
    }

    static function manage_page() {
        $plugin_info = get_file_data(__DIR__ . '/celtis-simple-download.php', array('Version' => 'Version'), 'plugin');        
        ?>
        <h2><?php esc_html_e('Simple Download with password', 'celtis-simple-download'); ?>
            <span style='font-size: 13px; margin-left:12px;'><?php echo esc_html( "Version {$plugin_info['Version']}"); ?></span>
            <span class="document-link"><a target="_blank" rel="noopener" href="<?php esc_html_e('https://celtislab.net/en/wp-plugin-celtis-simple-download/', 'celtis-simple-download'); ?>">Document</a></span>            
        </h2>      
        <?php 
        self::posts_display();

        //extended option settings
        if( current_user_can( 'manage_options' ) ) {
            $rewrite_slug = get_option( 'celtisdl_rewrite_slug', 'dl');
            ?>
            <div class="celtisdl-extended-option wrap">
                <details>
                <summary><?php esc_html_e('Extended option', 'celtis-simple-download'); ?></summary>            
                <form method="post" autocomplete="off">
                  <table id="celtisdl-setting-items" class="widefat">
                    <tbody>
                      <tr valign="top">
                        <th scope="row"><?php esc_html_e('Download links endpoint', 'celtis-simple-download'); ?></th>
                        <td>
                          <input type="text" class="medium-text" name="celtisdl-rewrite-slug" value="<?php echo esc_html($rewrite_slug); ?>"  />
                          <p class="memo-text"><?php esc_html_e('Note: Endpoint slug changes will update all download link URLs', 'celtis-simple-download') ?></p>
                        </td>
                      </tr>
                      <tr valign="top">
                        <th scope="row"><?php esc_html_e('Password skip by refere', 'celtis-simple-download'); ?></th>
                        <td>
                          <p><?php esc_html_e('Referrer verification - No password is required to download from a specific page of this site. (register URLs separated by line breaks)', 'celtis-simple-download'); ?><br />
                            <div><textarea name="celtisdl-pwskip-refurl" rows="3" class="large-text code" placeholder="Referrer eg. https://example.com/checkout/"><?php echo esc_html(self::$m_opt['pwskip_refurl']); ?></textarea></div>
                          </p>                
                        </td>
                      </tr>
                      <tr valign="top">
                        <th scope="row"><?php esc_html_e('Prevent downloads from domain', 'celtis-simple-download'); ?></th>
                        <td>
                          <p><?php esc_html_e('Referrer domain verification - Prevent downloads from registered url domains. (register url domain separated by line breaks)', 'celtis-simple-download'); ?><br />
                            <div><textarea name="celtisdl-prevent-dl-domain" rows="3" class="large-text code" placeholder="Domain eg. https://example.com"><?php echo esc_html(self::$m_opt['prevent_dl_domain']); ?></textarea></div>
                          </p>                
                        </td>
                      </tr>
                      <tr valign="top">
                        <th scope="row"><label><?php esc_html_e('Unfilter Upload file types', 'celtis-simple-download'); ?></label></th>
                        <td><label><input type="checkbox" name="celtisdl-unfiltered-upload" value="1" <?php checked(CELTISDL_manager::$m_option['unfiltered_upload'], 1, true); ?> /> <?php esc_html_e('Remove upload file types filtering for site administrators', 'celtis-simple-download'); ?></label></td>
                      </tr>                      
                    </tbody>                      
                  </table>
                  <div class="celtisdl-submit">
                    <span><input type="submit" name="celtisdl_op_uodate" class="button button-primary" value="<?php esc_html_e('Save', 'celtis-simple-download') ?>" onclick="CELTISDL_op_update('<?php echo esc_html(wp_create_nonce('celtisdl_op_update')); ?>');return false;" /></span>
                  </div>                    
                </form>
            </details>
            </div>
            <?php        
            do_action( 'celtisdl_additional_options' );
        }       
    }
}
