<?php
/*
Plugin Name: Sort Plugin
Description: A WordPress plugin to sort documents or posts — starter template.
Version: 1.0
Author: Puja Kundu
*/

if (!defined('ABSPATH')) {
    exit;
}

// Include the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-sort-plugin.php';

function run_sort_plugin() {
    $plugin = new Sort_Plugin();
    $plugin->run();
}
run_sort_plugin();
