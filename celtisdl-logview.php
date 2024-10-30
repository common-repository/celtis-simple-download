<?php

defined( 'ABSPATH' ) || exit;

use celtislab\v1_2\Celtis_sqlite;

class CELTISDL_log {   
  
    public function __construct() {
    }

    static function admin_log_css() {
    ?>
    <style type="text/css">
    h3.sub-title > a.button { vertical-align: baseline; margin-left: 16px; }
    .log-filter-opt h4, .wrap-celtisdl-stat h4, .wrap-celtisdl-log h4 { margin: 1.33em 0 .6em;}
    .log-filter-opt { margin: 8px 0;}
    .log-filter-opt > span { margin-left: 16px;}
    .wrap-celtisdl-log { overflow-x:auto;}
    .celtisdl-table thead, .celtisdl-table tbody { display: block;}
    .celtisdl-stat-body { overflow-y:scroll; height:22vh; }
    .celtisdl-log-body { overflow-y:scroll; height:45vh; }
    .celtisdl-log-body tr.stat-fail { background-color:#ffebe8; }    
    .celtisdl-log-body .over-hide { white-space:nowrap; overflow: hidden; }
    .celtisdl-log-body .celtisdl-title { font-weight: bold; }
    .small-size { min-width: 56px; max-width: 56px;}
    .normal-size { min-width: 96px; max-width: 96px;}
    .large-size  { min-width: 240px; max-width: 240px;}
    .widefat div.align-right { text-align: right; margin-right: 32px;}
    .celtisdl-log-nav { text-align: center; margin: 12px auto;}    
    .celtisdl-log-nav input.tiny-text { margin: 0 5px; text-align: center;}
    </style>
    <?php 
    }
    
    static function admin_log_scripts() {
        wp_enqueue_script( 'celtisdl-logviewjs', plugins_url( 'js/celtisdl-logview.js', __FILE__ ), array('wp-i18n') );
    }
    
    static function ajax_celtisdl_log() {
        if (!current_user_can('publish_posts'))
            wp_die(-1);
        check_ajax_referer("celtisdl_log_nav");

        $logpage = (isset($_POST['log_page']))? absint($_POST['log_page']) : 0;
        //Local Date => utc convert
        $timezone = wp_timezone_string();
        $sdate = sanitize_text_field($_POST['s_date']) . ' 00:00:00';
        if(strtotime($sdate) !== false){
            $date = new DateTime( "$sdate", new DateTimeZone($timezone));
            $date->setTimeZone( new DateTimeZone('utc'));
            $sdate = $date->format("Y-m-d H:i:s");
        }        
        $edate = sanitize_text_field($_POST['e_date']) . ' 23:59:59';
        if(strtotime($edate) !== false){
            $date = new DateTime( "$edate", new DateTimeZone($timezone));
            $date->setTimeZone( new DateTimeZone('utc'));
            $edate = $date->format("Y-m-d H:i:s");
        }
        $stats = array();
        $logs  = array();
        $log_db = new Celtis_sqlite( CELTISDL_LOG_FILE );
        if(!empty($log_db) && $log_db->is_open() && $log_db->is_table_exist( 'log' )){
            $stats  = self::get_stat($log_db, $sdate, $edate);
            $logs   = self::get_log($log_db, $sdate, $edate, $logpage);
            $log_db->close();
        }
                
        $shtml = self::stat2html($stats);                    
        $lhtml = self::log2html($logs);
        $response = array();
        $response['success'] = true;
        $response['stat'] = wp_kses_post($shtml);
        $response['log']  = wp_kses_post($lhtml);
        $response['page'] = (string)$logpage;
        $response['msg']  = (!empty($logs))? '' : esc_html__('Specified log does not exist.', 'celtis-simple-download');
        
        ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア
        wp_send_json( $response );
    }
    
    /**
     * Stat/Log data get
     */
    //Date filter
    static function _query_where_date(&$q_prm, $sdate=false, $edate=false) {
        $where   = '';
        if($sdate){
            $where = "WHERE date >= ? ";
            $q_prm[] = $sdate;
            if($edate){
                $where .= "AND date <=  ? ";
                $q_prm[] = $edate;                            
            }
        } elseif($edate){
            $where .= "WHERE date <=  ? ";
            $q_prm[] = $edate;                        
        }
        return $where;
    }

    // popular download
    // SELECT blogid, postid, attachid, file_name, SUM( result ) FROM log WHERE date >= '2024-01-11 00:00:00' AND date <= '2024-01-12 23:59:59' GROUP BY blogid, postid, attachid ORDER BY SUM( result ) DESC LIMIT 0, 100    
    static function get_stat($log_db, $sdate=false, $edate=false) {
        $q_prm   = array();
        $where   = self::_query_where_date($q_prm, $sdate, $edate);
        $q_prm[] = 0;
        $stats   = $log_db->sql_get_results("SELECT blogid, postid, attachid, title, file_name, SUM(result) AS sum_result FROM log {$where} GROUP BY blogid, postid, attachid ORDER BY sum_result DESC LIMIT ?, 100", $q_prm );
        return $stats;
    }
    
    static function get_log($log_db, $sdate=false, $edate=false, $logpage=0) {
        $q_prm   = array();
        $where   = self::_query_where_date($q_prm, $sdate, $edate);
        $offset  = (!empty($logpage))? absint($logpage) * 100 : 0;
        $q_prm[] = $offset;
        $logs    = $log_db->sql_get_results("SELECT * FROM log {$where} ORDER BY date DESC LIMIT ?, 100", $q_prm );
        return $logs;
    }

    static function stat2html( $stats ) {
        $html = '';
        if(empty($stats))
            return ''; 
        
        foreach ($stats as $stat) {
            if(is_object($stat)){
                if(absint( $stat->sum_result ) > 0){
                    $shtml = '<tr>';
                    $shtml .= '<td><div class="small-size celtisdl-count align-right">' . absint( $stat->sum_result ) . '</div></td>';                
                    $shtml .= '<td><div class="large-size celtisdl-title">' . esc_html( $stat->title ) . '</div></td>';                
                    $shtml .= '<td><div class="large-size celtisdl-file">' . wp_basename( esc_html( $stat->file_name )) . '</div></td>';                
                    $shtml .= '</tr>';

                    $html .= apply_filters( 'celtisdl_stat_content_custom', $shtml, $stat );
                }
            }
        }
        return $html;
    }
    
    static function log2html( $logs ) {
        if(empty($logs))
            return '';

        $html = '';
        $timezone = wp_timezone_string();
        foreach ($logs as $log) {
            if(is_object($log)){
                $logdate = new DateTime( $log->date );
                $logdate->setTimeZone( new DateTimeZone( $timezone ));
                
                $user = '';
                if(!empty($log->user)){
                    $uobj = get_userdata( absint( $log->user ) );
                    $user = (!empty($uobj))? $uobj->display_name : sprintf("%d", absint( $log->user ));
                }
                $exppw = '';
                if(!empty($log->exppw)){
                    $exppw = "PW:{$log->exppw}";
                }
            
                $shtml = (!empty($log->result))? '<tr>' : '<tr class="stat-fail">';
                $shtml .= '<td><div class="normal-size celtisdl-date">' . esc_html($logdate->format('Y-m-d H:i:s')) . '</div></td>';
                $shtml .= '<td><div class="large-size"><div class="celtisdl-title">' . esc_html( $log->title ) . '</div><div class="celtisdl-file">' . wp_basename( esc_html( $log->file_name )) . '</div></div></td>';                
                $shtml .= '<td><div class="large-size"><div class="celtisdl-exppw">' . esc_html( $exppw ) . '</div><div class="celtisdl-info">' . esc_html( $log->stat_info ) . '</div></div></td>';
                $shtml .= '<td><div class="normal-size celtisdl-user">' . esc_html( $user ) . '</div></td>';
                $shtml .= '<td><div class="normal-size celtisdl-ip">' . esc_html( $log->ip ) . '</div></td>';
                $shtml .= '<td><div class="celtisdl-ua over-hide">' . esc_html( $log->user_agent ) . '</div><div class="celtisdl-referer over-hide">' . esc_url( $log->referer ) . '</div></div></td>';
                $shtml .= '</tr>';

                $html .= apply_filters( 'celtisdl_log_content_custom', $shtml, $log );                
            }
        }
        return $html;
    }
    
    static function log_table() {
        $ajax_nonce = wp_create_nonce('celtisdl_log_nav');
        $timezone  = wp_timezone_string();
        $localdate = new DateTime("now", new DateTimeZone($timezone));
        $s_day   = $localdate->format('Y-m-d');
        $e_day   = $localdate->format('Y-m-d');
        $s_month = $localdate->format('Y-m-01');
        $e_month = $localdate->format('Y-m-d');
        $s_year  = $localdate->format('Y-01-01');
        $e_year  = $localdate->format('Y-m-d');                

        $stats = array();
        $logs  = array();
        $log_db = new Celtis_sqlite( CELTISDL_LOG_FILE );
        if(!empty($log_db) && $log_db->is_open() && $log_db->is_table_exist( 'log' )){
            $stats  = self::get_stat($log_db, $s_month . ' 00:00:00', $e_month . ' 23:59:59');
            $logs   = self::get_log($log_db,  $s_month . ' 00:00:00', $e_month . ' 23:59:59');
            $log_db->close();
        }
        ?>
        <div class="log-filter-opt">
          <h4><?php echo esc_html__('Log Filter', 'celtis-simple-download'); ?></h4>
          <span>
              <a class="button" href="#" onclick='Set_filter_date("<?php echo esc_html($s_day)  ?>","<?php echo esc_html($e_day);  ?>");'><?php esc_html_e('Today', 'celtis-simple-download'); ?></a>
              <a class="button" href="#" onclick='Set_filter_date("<?php echo esc_html($s_month)?>","<?php echo esc_html($e_month);?>");'><?php esc_html_e('This Month', 'celtis-simple-download'); ?></a>
              <a class="button" href="#" onclick='Set_filter_date("<?php echo esc_html($s_year) ?>","<?php echo esc_html($e_year); ?>");'><?php esc_html_e('This Year', 'celtis-simple-download'); ?></a>
          </span>
          <span><?php esc_html_e('Start: ', 'celtis-simple-download'); ?><input name="celtisdl_log_start" type="Date" value="<?php echo esc_html($s_month) ?>"></span>
          <span><?php esc_html_e('End: ', 'celtis-simple-download'); ?><input name="celtisdl_log_end" type="Date" value="<?php echo esc_html($e_month) ?>"></span>
          <span><a class="button ajax-submit celtisdl-log-filter" href="#celtisdl-log" onclick='Apply_celtisdl_log_nav("<?php echo esc_html($ajax_nonce) ?>");return false;'><?php esc_html_e('View', 'celtis-simple-download'); ?></a></span>
        </div>    
        <div class="wrap-celtisdl-stat">
            <h4><?php echo esc_html__('Popular download', 'celtis-simple-download'); ?></h4>
            <table class="celtisdl-table widefat">
                <thead><tr>
                        <th><div class="small-size align-right">Times</div></th>
                        <th><div class="large-size">Title</div></th>
                        <th><div class="large-size">File</div></th>
                       </tr>
                </thead>
                <tbody id="celtisdl-stat" class="celtisdl-stat-body">
                    <?php
                    $html  = self::stat2html($stats);                    
                    echo wp_kses_post($html);
                    ?>
                </tbody>
            </table>
        </div>    
        <div class="wrap-celtisdl-log">
            <h4><?php echo esc_html__('Log', 'celtis-simple-download'); ?></h4>
            <table class="widefat celtisdl-table">
                <thead><tr>
                        <th><div class="normal-size">Date</div></th>
                        <th><div class="large-size">Title / File</div></th>
                        <th><div class="large-size">Stat</div></th>
                        <th><div class="normal-size">User</div></th>
                        <th><div class="normal-size">IP</div></th>
                        <th><div>UserAgent/Referer</div></th>
                       </tr>
                </thead>
                <tbody id="celtisdl-log" class="celtisdl-log-body"> 
                    <?php
                    $html = self::log2html($logs);
                    echo wp_kses_post($html);
                    ?>
                </tbody>
            </table>
        </div>
        <div class="celtisdl-log-nav">
            <span class="hide-if-no-js">
              <a class="button ajax-submit celtisdl-log-prev" href="#celtisdl-log" style="width: 80px;" onclick='Apply_celtisdl_log_nav("<?php echo esc_html($ajax_nonce) ?>","prev");return false;'><?php esc_html_e('< Prev', 'celtis-simple-download'); ?></a>
              <input type="text" readonly id="celtisdl-log-page" class="tiny-text" value="0" />
              <a class="button ajax-submit celtisdl-log-next" href="#celtisdl-log" style="width: 80px;" onclick='Apply_celtisdl_log_nav("<?php echo esc_html($ajax_nonce) ?>","next");return false;'><?php esc_html_e('Next >', 'celtis-simple-download'); ?></a>
            </span>
        </div>
        <?php        
    }
    
    static function log_view() {
        ?>
        <h2><?php esc_html_e('Simple Download with password', 'celtis-simple-download'); ?></h2>
        <div>
            <h3 class="sub-title">
              <?php esc_html_e('Download log', 'celtis-simple-download'); ?>
              <?php if( current_user_can( 'manage_options' ) ) { ?>
                <a class="button ajax-submit celtisdl-log-export" href="<?php echo esc_url(wp_nonce_url('admin.php?page=celtisdl-log-view&amp;action=export',"celtisdl_log_export")); ?>" ><?php esc_html_e('Export Log (sqlite)', 'celtis-simple-download'); ?></a>
                <a class="button ajax-submit celtisdl-log-reset"  href="<?php echo esc_url(wp_nonce_url('admin.php?page=celtisdl-log-view&amp;action=reset', "celtisdl_log_reset")); ?>" onclick="return confirm('<?php esc_html_e('Simple Download with password\nClick OK to reset all log items.', 'celtis-simple-download'); ?>');"><?php esc_html_e('Reset Log', 'celtis-simple-download'); ?></a>              
              <?php } ?>
            </h3>
            <?php self::log_table(); ?>     
        </div>
        <?php        
    }
}