<?php
/**
 * Google Scholar Connector für Wissens-Bot
 * Sucht wissenschaftliche Artikel in Google Scholar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_Scholar_Connector {
    
    private $search_url = 'https://scholar.google.com/scholar';
    
    /**
     * Sucht nach Artikeln in Google Scholar
     * 
     * Hinweis: Google Scholar hat keine offizielle API.
     * Diese Implementierung nutzt Web-Scraping als Fallback.
     * Für produktive Umgebungen wird die Verwendung von SerpAPI empfohlen.
     * 
     * @param string $query Suchbegriff
     * @param int $max_results Maximale Anzahl Ergebnisse
     * @return array|WP_Error Array mit Artikeln oder Fehler
     */
    public function search($query, $max_results = 5) {
        // Prüfe ob SerpAPI Key konfiguriert ist (optional)
        $serpapi_key = get_option('wissens_bot_serpapi_key');
        
        if (!empty($serpapi_key)) {
            return $this->search_via_serpapi($query, $max_results, $serpapi_key);
        }
        
        // Fallback: Einfaches Scraping (limitiert, aber funktional)
        return $this->search_via_scraping($query, $max_results);
    }
    
    /**
     * Suche über SerpAPI (empfohlen, aber kostenpflichtig)
     */
    private function search_via_serpapi($query, $max_results, $api_key) {
        $url = 'https://serpapi.com/search.json';
        
        $params = array(
            'engine' => 'google_scholar',
            'q' => $query,
            'num' => $max_results,
            'api_key' => $api_key
        );
        
        $url = add_query_arg($params, $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['organic_results'])) {
            return array();
        }
        
        return $this->parse_serpapi_results($data['organic_results']);
    }
    
    /**
     * Parst SerpAPI Ergebnisse
     */
    private function parse_serpapi_results($results) {
        $articles = array();
        
        foreach ($results as $result) {
            $article = array(
                'source' => 'Google Scholar',
                'type' => 'scientific_article'
            );
            
            if (isset($result['title'])) {
                $article['title'] = $result['title'];
            }
            
            if (isset($result['link'])) {
                $article['url'] = $result['link'];
            }
            
            if (isset($result['snippet'])) {
                $article['abstract'] = $result['snippet'];
            }
            
            if (isset($result['publication_info']['authors'])) {
                $authors = array();
                foreach ($result['publication_info']['authors'] as $author) {
                    $authors[] = $author['name'];
                }
                $article['authors'] = implode(', ', $authors);
            }
            
            if (isset($result['publication_info']['summary'])) {
                $article['journal'] = $result['publication_info']['summary'];
            }
            
            if (isset($result['inline_links']['cited_by']['total'])) {
                $article['citations'] = $result['inline_links']['cited_by']['total'];
            }
            
            $articles[] = $article;
        }
        
        return $articles;
    }
    
    /**
     * Suche via Web Scraping (Fallback)
     * 
     * ACHTUNG: Web-Scraping von Google Scholar verstößt möglicherweise gegen deren ToS.
     * Nur für Entwicklung/Testing verwenden. Für Produktion SerpAPI nutzen!
     */
    private function search_via_scraping($query, $max_results) {
        $params = array(
            'q' => $query,
            'num' => $max_results,
            'hl' => 'de'
        );
        
        $url = add_query_arg($params, $this->search_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        return $this->parse_scholar_html($html);
    }
    
    /**
     * Parst HTML von Google Scholar
     */
    private function parse_scholar_html($html) {
        $articles = array();
        
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Finde alle Artikel-Container
            $result_nodes = $xpath->query('//div[@class="gs_ri"]');
            
            foreach ($result_nodes as $node) {
                $article = array(
                    'source' => 'Google Scholar',
                    'type' => 'scientific_article'
                );
                
                // Titel
                $title_nodes = $xpath->query('.//h3[@class="gs_rt"]//a', $node);
                if ($title_nodes->length > 0) {
                    $article['title'] = trim($title_nodes->item(0)->nodeValue);
                    $article['url'] = $title_nodes->item(0)->getAttribute('href');
                }
                
                // Snippet/Abstract
                $snippet_nodes = $xpath->query('.//div[@class="gs_rs"]', $node);
                if ($snippet_nodes->length > 0) {
                    $article['abstract'] = trim($snippet_nodes->item(0)->nodeValue);
                }
                
                // Autoren und Publikationsinfo
                $author_nodes = $xpath->query('.//div[@class="gs_a"]', $node);
                if ($author_nodes->length > 0) {
                    $info_text = $author_nodes->item(0)->nodeValue;
                    $parts = explode(' - ', $info_text);
                    
                    if (count($parts) > 0) {
                        $article['authors'] = trim($parts[0]);
                    }
                    if (count($parts) > 1) {
                        $article['journal'] = trim($parts[1]);
                    }
                    if (count($parts) > 2) {
                        $article['date'] = trim($parts[2]);
                    }
                }
                
                // Zitationen
                $cite_nodes = $xpath->query('.//div[@class="gs_fl"]//a[contains(text(), "Zitiert")]', $node);
                if ($cite_nodes->length > 0) {
                    $cite_text = $cite_nodes->item(0)->nodeValue;
                    if (preg_match('/(\d+)/', $cite_text, $matches)) {
                        $article['citations'] = $matches[1];
                    }
                }
                
                if (!empty($article['title'])) {
                    $articles[] = $article;
                }
            }
        } catch (Exception $e) {
            error_log('Google Scholar Parse Error: ' . $e->getMessage());
        }
        
        return $articles;
    }
    
    /**
     * Alternative: Semantic Scholar API (kostenlos und legal)
     * Diese API ist eine gute Alternative zu Google Scholar
     */
    public function search_semantic_scholar($query, $max_results = 5) {
        $url = 'https://api.semanticscholar.org/graph/v1/paper/search';
        
        $params = array(
            'query' => $query,
            'limit' => $max_results,
            'fields' => 'title,abstract,authors,year,citationCount,url,venue,publicationDate'
        );
        
        $url = add_query_arg($params, $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data'])) {
            return array();
        }
        
        $articles = array();
        
        foreach ($data['data'] as $paper) {
            $article = array(
                'source' => 'Semantic Scholar',
                'type' => 'scientific_article'
            );
            
            if (isset($paper['title'])) {
                $article['title'] = $paper['title'];
            }
            
            if (isset($paper['abstract'])) {
                $article['abstract'] = $paper['abstract'];
            }
            
            if (isset($paper['authors']) && is_array($paper['authors'])) {
                $authors = array();
                foreach ($paper['authors'] as $author) {
                    $authors[] = $author['name'];
                }
                $article['authors'] = implode(', ', $authors);
            }
            
            if (isset($paper['year'])) {
                $article['date'] = $paper['year'];
            }
            
            if (isset($paper['venue'])) {
                $article['journal'] = $paper['venue'];
            }
            
            if (isset($paper['citationCount'])) {
                $article['citations'] = $paper['citationCount'];
            }
            
            if (isset($paper['url'])) {
                $article['url'] = $paper['url'];
            }
            
            $articles[] = $article;
        }
        
        return $articles;
    }
}
