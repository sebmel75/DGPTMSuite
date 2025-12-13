<?php
/**
 * PubMed Connector für Wissens-Bot
 * Sucht wissenschaftliche Artikel in PubMed
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_PubMed_Connector {
    
    private $base_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/';
    private $email = ''; // Optional: Ihre E-Mail für NCBI
    
    public function __construct() {
        // Email aus WordPress-Einstellungen holen
        $this->email = get_option('admin_email');
    }
    
    /**
     * Sucht nach Artikeln in PubMed
     * 
     * @param string $query Suchbegriff
     * @param int $max_results Maximale Anzahl Ergebnisse
     * @return array|WP_Error Array mit Artikeln oder Fehler
     */
    public function search($query, $max_results = 5) {
        // Schritt 1: IDs der relevanten Artikel holen
        $ids = $this->search_ids($query, $max_results);
        
        if (is_wp_error($ids) || empty($ids)) {
            return $ids;
        }
        
        // Schritt 2: Details zu den Artikeln abrufen
        $articles = $this->fetch_article_details($ids);
        
        return $articles;
    }
    
    /**
     * Sucht nach Artikel-IDs in PubMed
     */
    private function search_ids($query, $max_results) {
        $search_url = $this->base_url . 'esearch.fcgi';
        
        $params = array(
            'db' => 'pubmed',
            'term' => $query,
            'retmax' => $max_results,
            'retmode' => 'json',
            'sort' => 'relevance'
        );
        
        if (!empty($this->email)) {
            $params['email'] = $this->email;
        }
        
        $url = add_query_arg($params, $search_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['esearchresult']['idlist'])) {
            return array();
        }
        
        return $data['esearchresult']['idlist'];
    }
    
    /**
     * Holt Details zu Artikeln anhand ihrer IDs
     */
    private function fetch_article_details($ids) {
        if (empty($ids)) {
            return array();
        }
        
        $fetch_url = $this->base_url . 'efetch.fcgi';
        
        $params = array(
            'db' => 'pubmed',
            'id' => implode(',', $ids),
            'retmode' => 'xml'
        );
        
        if (!empty($this->email)) {
            $params['email'] = $this->email;
        }
        
        $url = add_query_arg($params, $fetch_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $xml = wp_remote_retrieve_body($response);
        
        return $this->parse_pubmed_xml($xml);
    }
    
    /**
     * Parst PubMed XML-Antwort
     */
    private function parse_pubmed_xml($xml) {
        $articles = array();
        
        try {
            $doc = new DOMDocument();
            @$doc->loadXML($xml);
            
            $xpath = new DOMXPath($doc);
            $article_nodes = $xpath->query('//PubmedArticle');
            
            foreach ($article_nodes as $article_node) {
                $article = $this->parse_article_node($article_node, $xpath);
                if (!empty($article)) {
                    $articles[] = $article;
                }
            }
        } catch (Exception $e) {
            error_log('PubMed XML Parse Error: ' . $e->getMessage());
        }
        
        return $articles;
    }
    
    /**
     * Parst einen einzelnen Artikel-Node
     */
    private function parse_article_node($node, $xpath) {
        $article = array(
            'source' => 'PubMed',
            'type' => 'scientific_article'
        );
        
        // PMID
        $pmid_nodes = $xpath->query('.//PMID', $node);
        if ($pmid_nodes->length > 0) {
            $article['pmid'] = $pmid_nodes->item(0)->nodeValue;
            $article['url'] = 'https://pubmed.ncbi.nlm.nih.gov/' . $article['pmid'] . '/';
        }
        
        // Titel
        $title_nodes = $xpath->query('.//ArticleTitle', $node);
        if ($title_nodes->length > 0) {
            $article['title'] = $title_nodes->item(0)->nodeValue;
        }
        
        // Abstract
        $abstract_nodes = $xpath->query('.//Abstract/AbstractText', $node);
        if ($abstract_nodes->length > 0) {
            $abstract = '';
            foreach ($abstract_nodes as $abstract_node) {
                $label = $abstract_node->getAttribute('Label');
                $text = $abstract_node->nodeValue;
                
                if (!empty($label)) {
                    $abstract .= $label . ': ';
                }
                $abstract .= $text . "\n\n";
            }
            $article['abstract'] = trim($abstract);
        }
        
        // Autoren
        $author_nodes = $xpath->query('.//AuthorList/Author', $node);
        $authors = array();
        foreach ($author_nodes as $author_node) {
            $lastname = $xpath->query('.//LastName', $author_node);
            $forename = $xpath->query('.//ForeName', $author_node);
            
            if ($lastname->length > 0) {
                $author_name = $lastname->item(0)->nodeValue;
                if ($forename->length > 0) {
                    $author_name .= ' ' . $forename->item(0)->nodeValue;
                }
                $authors[] = $author_name;
            }
        }
        if (!empty($authors)) {
            $article['authors'] = implode(', ', $authors);
        }
        
        // Journal
        $journal_nodes = $xpath->query('.//Journal/Title', $node);
        if ($journal_nodes->length > 0) {
            $article['journal'] = $journal_nodes->item(0)->nodeValue;
        }
        
        // Publikationsdatum
        $year_nodes = $xpath->query('.//PubDate/Year', $node);
        $month_nodes = $xpath->query('.//PubDate/Month', $node);
        
        if ($year_nodes->length > 0) {
            $date = $year_nodes->item(0)->nodeValue;
            if ($month_nodes->length > 0) {
                $date = $month_nodes->item(0)->nodeValue . ' ' . $date;
            }
            $article['date'] = $date;
        }
        
        // Keywords/MeSH Terms
        $mesh_nodes = $xpath->query('.//MeshHeading/DescriptorName', $node);
        $keywords = array();
        foreach ($mesh_nodes as $mesh_node) {
            $keywords[] = $mesh_node->nodeValue;
        }
        if (!empty($keywords)) {
            $article['keywords'] = implode(', ', $keywords);
        }
        
        // DOI
        $doi_nodes = $xpath->query('.//ArticleId[@IdType="doi"]', $node);
        if ($doi_nodes->length > 0) {
            $article['doi'] = $doi_nodes->item(0)->nodeValue;
        }
        
        return $article;
    }
    
    /**
     * Sucht nach verwandten Artikeln
     */
    public function get_related_articles($pmid, $max_results = 5) {
        $link_url = $this->base_url . 'elink.fcgi';
        
        $params = array(
            'dbfrom' => 'pubmed',
            'db' => 'pubmed',
            'id' => $pmid,
            'cmd' => 'neighbor',
            'retmode' => 'json'
        );
        
        $url = add_query_arg($params, $link_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Extrahiere verwandte IDs
        if (isset($data['linksets'][0]['linksetdbs'][0]['links'])) {
            $related_ids = array_slice($data['linksets'][0]['linksetdbs'][0]['links'], 0, $max_results);
            return $this->fetch_article_details($related_ids);
        }
        
        return array();
    }
}
