<?php 
/*
Plugin Name: WebePartners
Plugin URI: https://www.worzala.pl/webepartners
Description: 
Version: 0.0.1
Author: PawelWorzala
Author URI: https://www.worzala.pl
Text Domain: w
Generated By: http://ensuredomains.com
*/

// If this file is called directly, abort. //
if ( ! defined( 'WPINC' ) ) {die;} // end if

// Let's Initialize Everything
if ( file_exists( plugin_dir_path( __FILE__ ) . 'core-init.php' ) ) {
require_once( plugin_dir_path( __FILE__ ) . 'core-init.php' );
}