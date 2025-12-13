<?php
/**
 * Website Connector für Wissens-Bot
 * Durchsucht die eigene Website (perfusiologie.de)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_Website_Connector {
    
    private $site_url;
    private $search_paths;
    
    public function __construct() {
        $this->site_url = get_option('wissens_bot_website_url', home_url());
        $this->search_paths = $this->get_search_paths();
    }
    
    /**
     * Durchsucht die eigene Website
     * 
     * @param string $query Suchbegriff
     * @param int $max_results Maximale Anzahl Ergebnisse
     * @return array Array mit Artikeln
     */
    public function search($query, $max_results = 5) {
        $results = array();
        
        // WordPress-eigene Suche nutzen
        $wp_results = $this->search_wordpress_posts($query, $max_results);
        
        if (!empty($wp_results)) {
            $results = array_merge($results, $wp_results);
        }
        
        return array_slice($results, 0, $max_results);
    }
    
    /**
     * Durchsucht WordPress-Posts und -Seiten
     */
    private function search_wordpress_posts($query, $max_results) {
        $results = array();
        
        // WP_Query für Suche
        $search_args = array(
            's' => $query,
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => $max_results,
            'orderby' => 'relevance',
            'order' => 'DESC'
        );
        
        // Keywords aus Einstellungen für bessere Relevanz
        $keywords = get_option('wissens_bot_topic_keywords');
        if (!empty($keywords)) {
            $keyword_array = array_map('trim', explode(',', $keywords));
            
            // Füge tax_query für bessere Filterung hinzu (wenn Tags/Kategorien genutzt werden)
            $search_args['tax_query'] = array(
                'relation' => 'OR',
            );
            
            foreach ($keyword_array as $keyword) {
                $search_args['tax_query'][] = array(
                    'taxonomy' => 'category',
                    'field' => 'name',
                    'terms' => $keyword,
                    'operator' => 'LIKE'
                );
            }
        }
        
        $search_query = new WP_Query($search_args);
        
        if ($search_query->have_posts()) {
            while ($search_query->have_posts()) {
                $search_query->the_post();
                
                $post_id = get_the_ID();
                $content = get_the_content();
                $excerpt = get_the_excerpt();
                
                // Entferne Shortcodes und HTML-Tags für besseren Content
                $clean_content = wp_strip_all_tags(strip_shortcodes($content));
                
                // Begrenze Content-Länge
                $content_excerpt = substr($clean_content, 0, 1000);
                
                $results[] = array(
                    'source' => 'perfusiologie.de',
                    'type' => 'website_article',
                    'title' => get_the_title(),
                    'content' => $content_excerpt,
                    'abstract' => !empty($excerpt) ? $excerpt : substr($clean_content, 0, 300),
                    'url' => get_permalink(),
                    'date' => get_the_date('Y-m-d'),
                    'author' => get_the_author(),
                    'categories' => $this->get_post_categories($post_id)
                );
            }
            wp_reset_postdata();
        }
        
        return $results;
    }
    
    /**
     * Holt Kategorien eines Posts
     */
    private function get_post_categories($post_id) {
        $categories = get_the_category($post_id);
        $cat_names = array();
        
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $cat_names[] = $category->name;
            }
        }
        
        return implode(', ', $cat_names);
    }
    
    /**
     * Definiert Suchpfade
     */
    private function get_search_paths() {
        return array(
            'posts' => '/blog/',
            'articles' => '/artikel/',
            'knowledge' => '/wissen/',
            'docs' => '/dokumentation/'
        );
    }
    
    /**
     * Sucht nach verwandten Artikeln basierend auf Kategorien/Tags
     */
    public function get_related_articles($post_id, $max_results = 5) {
        $results = array();
        
        $categories = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id);
        
        if (empty($categories) && empty($tags)) {
            return $results;
        }
        
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => $max_results,
            'post__not_in' => array($post_id),
            'orderby' => 'rand'
        );
        
        if (!empty($categories)) {
            $args['category__in'] = $categories;
        }
        
        if (!empty($tags)) {
            $tag_ids = array();
            foreach ($tags as $tag) {
                $tag_ids[] = $tag->term_id;
            }
            $args['tag__in'] = $tag_ids;
        }
        
        $related_query = new WP_Query($args);
        
        if ($related_query->have_posts()) {
            while ($related_query->have_posts()) {
                $related_query->the_post();
                
                $results[] = array(
                    'source' => 'perfusiologie.de',
                    'type' => 'website_article',
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'date' => get_the_date('Y-m-d')
                );
            }
            wp_reset_postdata();
        }
        
        return $results;
    }
    
    /**
     * Durchsucht spezifische Custom Post Types
     */
    public function search_custom_post_types($query, $post_types = array(), $max_results = 5) {
        if (empty($post_types)) {
            return array();
        }
        
        $results = array();
        
        $args = array(
            's' => $query,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $max_results,
            'orderby' => 'relevance'
        );
        
        $query_obj = new WP_Query($args);
        
        if ($query_obj->have_posts()) {
            while ($query_obj->have_posts()) {
                $query_obj->the_post();
                
                $results[] = array(
                    'source' => 'perfusiologie.de',
                    'type' => get_post_type(),
                    'title' => get_the_title(),
                    'content' => substr(wp_strip_all_tags(get_the_content()), 0, 1000),
                    'url' => get_permalink(),
                    'date' => get_the_date('Y-m-d')
                );
            }
            wp_reset_postdata();
        }
        
        return $results;
    }
}
