<?php

/**
 * Search It API Class
 * Allows programmatic access to Search It's search functionality with registerable search configurations
 */
class rex_api_search_it_search extends rex_api_function
{
    protected $published = true;
    
    // Storage for registered search configurations
    protected static $searchConfigurations = [];
    
    // Default secure configuration
    protected static $defaultConfig = [
        'maxResults' => 20,
        'defaultLogicalMode' => 'and',
        'defaultSearchMode' => 'like',
        'enableSimilarWords' => true,
        'defaultSimilarWordsMode' => 1, // SEARCH_IT_SIMILARWORDS_SOUNDEX
    ];
    
    /**
     * Register a new search configuration
     * 
     * @param string $key The search key identifier
     * @param callable $configFunction Function that configures the search_it instance
     */
    public static function registerSearchConfiguration($key, callable $configFunction)
    {
        self::$searchConfigurations[$key] = $configFunction;
    }
    
    /**
     * Set default configuration options
     * 
     * @param array $config Configuration options
     */
    public static function setDefaultConfig(array $config)
    {
        self::$defaultConfig = array_merge(self::$defaultConfig, $config);
    }
    
    /**
     * Main execution method for the API class
     */
    public function execute()
    {
        // Set default response format
        $format = rex_request('format', 'string', 'json');
        
        // Get search parameters
        $searchTerm = rex_request('search', 'string', '');
        $searchKey = rex_request('key', 'string', 'default');
        
        // Check if search term is provided
        if (empty($searchTerm)) {
            // Direkte Ausgabe mit exit für JSON
            if ($format === 'json') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No search term provided']);
                exit;
            }
            return new rex_api_result(true, ['error' => 'No search term provided']);
        }
        
        try {
            // Initialize Search It with language
            $search_it = new search_it(rex_clang::getCurrentId());
            
            // Apply base configuration
            $this->applyBaseConfiguration($search_it);
            
            // Apply registered search configuration if exists
            if (isset(self::$searchConfigurations[$searchKey])) {
                $configFunction = self::$searchConfigurations[$searchKey];
                $configFunction($search_it);
            }
            
            // Perform the search
            $result = $search_it->search($searchTerm);
            
            // Format and return the result
            return $this->formatResult($result, $format);
            
        } catch (Exception $e) {
            // Direkte Ausgabe mit exit für JSON
            if ($format === 'json') {
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            return new rex_api_result(true, ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Apply the base configuration to the search_it instance
     * 
     * @param search_it $search_it The search_it instance
     */
    protected function applyBaseConfiguration($search_it)
    {
        // Set logical mode (AND/OR)
        $search_it->setLogicalMode(self::$defaultConfig['defaultLogicalMode']);
        
        // Set search mode (like/match)
        $search_it->setSearchMode(self::$defaultConfig['defaultSearchMode']);
        
        // Set similar words mode if enabled
        if (self::$defaultConfig['enableSimilarWords']) {
            $search_it->setSimilarWordsMode(self::$defaultConfig['defaultSimilarWordsMode']);
        } else {
            $search_it->setSimilarWordsMode(0); // Disable similar words
        }
        
        // Set maximum results
        $search_it->setLimit([0, self::$defaultConfig['maxResults']]);
    }
    
    /**
     * Formats the search result according to the requested format
     * 
     * @param array $result Search result array
     * @param string $format Desired output format
     * @return rex_api_result Formatted API result
     */
    protected function formatResult($result, $format)
    {
        // Process the result to make it more API-friendly
        $processedResult = [
            'query' => $result['searchterm'],
            'count' => $result['count'],
            'hits' => [],
            'time' => $result['time']
        ];
        
        // Process hits for a cleaner API response
        foreach ($result['hits'] as $hit) {
            $processedHit = [
                'id' => $hit['id'],
                'fid' => $hit['fid'],
                'type' => $hit['type'],
                'teaser' => $hit['teaser'],
                'highlightedtext' => $hit['highlightedtext']
            ];
            
            // Add article-specific information
            if ($hit['type'] === 'article') {
                try {
                    if (is_numeric($hit['fid'])) {
                        $article = rex_article::get($hit['fid'], $hit['clang']);
                        if ($article) {
                            $processedHit['url'] = $article->getUrl();
                            $processedHit['name'] = $article->getName();
                        }
                    }
                } catch (Exception $e) {
                    // Ignore errors getting article data
                }
            }
            
            // Add media-specific information
            if ($hit['type'] === 'file' && !empty($hit['filename'])) {
                $processedHit['filename'] = $hit['filename'];
                $processedHit['url'] = rex_url::media($hit['filename']);
            }
            
            $processedResult['hits'][] = $processedHit;
        }
        
        // Format the response according to the requested format
        switch ($format) {
            case 'xml':
                $xml = new SimpleXMLElement('<search_it_results/>');
                $xml->addChild('query', $processedResult['query']);
                $xml->addChild('count', $processedResult['count']);
                $xml->addChild('time', $processedResult['time']);
                
                $hitsNode = $xml->addChild('hits');
                foreach ($processedResult['hits'] as $hit) {
                    $hitNode = $hitsNode->addChild('hit');
                    foreach ($hit as $key => $value) {
                        if (is_array($value)) {
                            $valuesNode = $hitNode->addChild($key);
                            foreach ($value as $k => $v) {
                                $valuesNode->addChild($k, htmlspecialchars($v));
                            }
                        } else {
                            $hitNode->addChild($key, htmlspecialchars($value));
                        }
                    }
                }
                
                $response = $xml->asXML();
                header('Content-Type: application/xml');
                echo $response;
                exit;
                
            case 'json':
            default:
                // Direkte JSON-Ausgabe mit exit
                header('Content-Type: application/json');
                echo json_encode($processedResult);
                exit;
        }
    }
}

