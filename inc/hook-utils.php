<?php
/**
 * Hook filter utility
 * 
 * Description: Utility to remove filters that cannot be removed with WP standard functions from action hooks/filter hooks
 * 
 * Version: 0.6.0 
 * Author: enomoto@celtislab
 * Author URI: https://celtislab.net/
 * License: GPLv2
 * 
 */

namespace celtisdl\hfu060;

defined( 'ABSPATH' ) || exit;
/*
 Example of use
 1. Remove plugin instance class hooks
  use celtisdl\hfu060\Hook_util;
  $hook = Hook_util::filter_id('wp-multibyte-patch/wp-multibyte-patch.php', 'multibyte_patch::sanitize_file_name');
  Hook_util::remove_hook('sanitize_file_name', $hook, 10);

 2. Remove plugin anonymous functions (closures) hooks
  $hook = Hook_util::filter_id('celtispack/modules/json-ld/json-ld.php', 'closure', 10, 1);
  Hook_util::remove_hook('wp_head', $hook, 10);   
*/

class Hook_util {

	function __construct() {}
    
    public static function is_enable() {
        return class_exists('\ReflectionFunction');
    }

    //=============================================================
    //Get information about specified hook filter
    // $action_name : hook name
    // $target_priority : Hooked priority (targets all priorities if unspecified)
    //=============================================================
    public static function get_hook($action_name, $target_priority=null) {
        global $wp_filter;
        $hooks = array();
        if ( is_object( $wp_filter[$action_name] ) ) {
            foreach($wp_filter[$action_name]->callbacks as $priority => $callbacks ){
                if($target_priority !== null && $priority != absint($target_priority))
                    continue;
                foreach ($callbacks as $key => $filter) {
                    $type = $filter_id = '';
                    $hook = self::hook_inf($priority, $filter, $type, $filter_id);
                    if(!empty($hook)){
                        $hooks[$type][$filter_id] = $hook;
                    }
                }
            }
        }
        return $hooks;
    }  

    //=============================================================
    //Removes the hook with the specified filter identification ID
    // $action_name : hook name
    // $remove_ids : ID for identifying filters to be removed (separate with commas if multiple)
    // $target_priority : Hooked priority (targets all priorities if unspecified)
    //=============================================================
    public static function remove_hook($action_name, $remove_ids, $target_priority=null) {
        global $wp_filter;
        if ( is_object( $wp_filter[$action_name] ) && !empty($remove_ids) ) {
            foreach($wp_filter[$action_name]->callbacks as $priority => $callbacks ){
                if($target_priority !== null && $priority != absint($target_priority))
                    continue;
                foreach ($callbacks as $key => $filter) {
                    $type = $filter_id = '';
                    $hook = self::hook_inf($priority, $filter, $type, $filter_id);
                    if(!empty($hook) && false !== strpos($remove_ids, $filter_id)){
                        unset( $wp_filter[$action_name]->callbacks[$priority][$key] );
                    }
                }
            }
        }
    }     
    
    /*---------------------------------------------------------------
     * ID generation for hook filter identification
     * 
     * $file : PHP file name calling the target hook filter
     *          For Plugin, relative path from plugin slug.        eg: jetpack/class.jetpack-gutenberg.php
     *          For Theme, the relative path from theme slug name  eg: twentytwenty/functions.php
     *          Otherwise, the path is relative to ABSPATH.
     * $callback : Target hook filter callback function
     *          For global functions, specify the function name
     *          For anonymous functions (closures), specify 'closure'
     *          For class methods, specify 'class name::method name'
     * $priority : target hooked priority
     * $accepted_args : Number of arguments that the target hook filter function can take
     * $staticvarkeys: For closure, static variable name array.  eg: add_action('wp_head', function() use(&$svar1, &$svar2)... => array('svar1','svar2') 
     */    
    public static function filter_id( $file, $callback, $priority=10, $accepted_args=1, $staticvarkeys=array() ) {
        $strvarkeys = serialize($staticvarkeys);
        return( md5( "{$file}_{$callback}_{$priority}_{$accepted_args}_{$strvarkeys}" ) );
    }

    //Parsing hooked filter information
    private static function hook_inf($priority, $filter, &$type, &$filter_id) {
        $hook_inf = array();
        try {                
            $callback = '';
            $accepted_args = 1;                
            if ( isset( $filter['function'] ) ) {
                if ( isset( $filter['accepted_args'] ) ) {
                    $accepted_args = absint($filter['accepted_args']);                
                }
                $ref = null;
                $staticvarkeys = array();
                if (is_string( $filter['function'] )){
                    $callback = $filter['function'];    //global function
                    $ref = new \ReflectionFunction( $filter['function'] );

                } elseif(is_object( $filter['function'] )){
                    $callback = 'closure';              //closure object
                    $ref = new \ReflectionFunction( $filter['function'] );
                    //get closure use static variable 
                    $var = $ref->getStaticVariables();
                    if(is_array($var)){
                        $staticvarkeys = array_keys($var);
                    }

                } elseif(is_array( $filter['function'] )){
                    if (is_string( $filter['function'][0] )){   //static class
                        $class = $filter['function'][0];
                        $func = $filter['function'][1];
                        $callback = "$class::$func";
                        $ref = new \ReflectionMethod( $class, $func);
                        
                    } elseif(is_object( $filter['function'][0] )){ //instance class
                        $class = get_class( $filter['function'][0] ); 
                        $func = $filter['function'][1];
                        $callback = "$class::$func";
                        $ref = new \ReflectionMethod( $class, $func);
                    }                    
                } 
                if(is_object($ref)){
                    $file = wp_normalize_path( $ref->getFileName() );
                    $rootdir = wp_normalize_path( ABSPATH );
                    $plugin_root = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
                    $theme_root  = wp_normalize_path( get_theme_root() ) . '/';
                    if ( strpos($file, $plugin_root) !== false) {
                        $type = 'plugins';
                        $file = str_ireplace( $plugin_root, '', $file);
                    } elseif ( strpos($file, $theme_root) !== false) {
                        $type = 'themes';
                        $file = str_ireplace( $theme_root, '', $file);
                    } else {
                        $type = 'core';
                        $file = str_ireplace( $rootdir, '', $file);
                    }
                    $filter_id = self::filter_id($file, $callback, $priority, $accepted_args, $staticvarkeys);
                    $hook_inf = array('file' => $file, 'callback' => $callback, 'priority' => $priority, 'args' => $accepted_args, 'staticvarkeys' => $staticvarkeys);
                }
            }
        } catch ( Exception $e ) {
            return null;
        }
        return $hook_inf;
    }    
}
