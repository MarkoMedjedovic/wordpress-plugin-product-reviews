<?php
/**
 * Plugin Name: Simple Reviews
 * Description: A simple WordPress plugin that registers a custom post type for product reviews and provides REST API support.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Reviews {
    public function __construct() {
        add_action('init', [$this, 'register_product_review_cpt']);  
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_shortcode('product_reviews' , [$this, 'display_product_reviews']);
 
    }

 
    public function register_product_review_cpt() {
        register_post_type('product_review', [
            'labels'      => [
                'name'          => 'Product Reviews',
                'singular_name' => 'Product Review'
            ],
            'public'      => true,
            'supports'    => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
            'capability_type' => 'post',
            'menu_position' => 5,
            'show_ui'       => true,
            'has_archive'   => true,
            'show_in_nav_menus' => true
        ]);
    }

    public function register_rest_routes() {

        //curl http://localhost:8080/wp-json/mock-api/v1/sentiment/

        register_rest_route('mock-api/v1', '/sentiment/', [
            'methods'  => 'POST',
            'callback' => [$this, 'analyze_sentiment'],
            'permission_callback' => '__return_true',
        ]);

        //curl http://localhost:8080/wp-json/mock-api/v1/review-history

        register_rest_route('mock-api/v1', '/review-history/', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_review_history'],
            'permission_callback' => '__return_true',
        ]);

        //curl http://localhost:8080/wp-json/mock-api/v1/outliers/

        register_rest_route('mock-api/v1', '/outliers/', [
            'methods'  => 'GET',
            'callback' => [$this, 'detect_outlier_reviews'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function analyze_sentiment($request) {
        $params = $request->get_json_params();
        $text = isset($params['text']) ? sanitize_text_field($params['text']) : '';
        
        if (empty($text)) {
            return new WP_Error('empty_text', 'No text provided for analysis.', ['status' => 400]);
        }

        $sentiment_scores = ['positive' => 0.9, 'negative' => 0.2, 'neutral' => 0.5];
        $random_sentiment = array_rand($sentiment_scores);
        return rest_ensure_response(['sentiment' => $random_sentiment, 'score' => $sentiment_scores[$random_sentiment]]);
    }

    public function get_review_history() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        
        $response = [];
        foreach ($reviews as $review) {
            $response[] = [
                'id'       => $review->ID,
                'title'    => $review->post_title,
                'sentiment'=> get_post_meta($review->ID, 'sentiment', true) ?? 'neutral',
                'score'    => get_post_meta($review->ID, 'sentiment_score', true) ?? 0.5,
            ];
        }

        return rest_ensure_response($response);
    }

    public function display_product_reviews() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $output = '<style>
            .review-positive { color: green; font-weight: bold; }
            .review-negative { color: red; font-weight: bold; }
        </style>';

        $output .= '<ul>';
        foreach ($reviews as $review) {
            $sentiment = get_post_meta($review->ID, 'sentiment', true) ?? 'neutral';
            $sentiment_score = get_post_meta($review->ID, 'sentiment_score', true) ?? '0.5';
            $class = ($sentiment === 'positive') ? 'review-positive' : (($sentiment === 'negative') ? 'review-negative' : '');
            $output .= "<li class='$class'>{$review->post_title} (Sentiment: $sentiment, Sentiment score: $sentiment_score)</li>";
        }
        $output .= '</ul>';

        return $output;
    }

    public function detect_outlier_reviews() {
        $reviews = get_posts([
            'post_type' => 'product_review',
            'posts_per_page' => -1,
        ]);

        if (empty($reviews)) {
            return rest_ensure_response([]);
        }

        $scores = [];
        foreach ($reviews as $review) {
            $scores[$review->ID] = (float) get_post_meta($review->ID, 'sentiment_score', true) ?? 0.5;
        }

        $mean = array_sum($scores) / count($scores);
        $threshold = 0.3;

        $outliers = [];
        foreach ($scores as $id => $score) {
            if (abs($score - $mean) > $threshold) {
                $outliers[] = [
                    'id' => $id,
                    'title' => get_the_title($id),
                    'score' => $score,
                    'sentiment' => get_post_meta($id, 'sentiment', true) ?? 'neutral',
                    'deviation' => round(abs($score - $mean), 3)
                ];
            }
        }

        return rest_ensure_response([
            'mean' => round($mean, 3),
            'outliers' => $outliers
        ]);
    }


}

new Simple_Reviews();