<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'product_review'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('rating', 'sentiment_score')");
