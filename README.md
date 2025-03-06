# Search It API Extension

Eine flexible, konfigurierbare API-Erweiterung für das REDAXO AddOn "Search It", die Suchanfragen über eine REST-API ermöglicht.

## Einleitung

Diese Erweiterung bietet:

1. Programmatischen Zugriff auf Search It über eine API
2. Registrierbare Suchkonfigurationen für verschiedene Anwendungsfälle
3. Flexible Ausgabeformate (JSON, XML)
4. Sichere Standardkonfiguration mit anpassbaren Suchbereichen

## Installation

1. Kopieren Sie die Datei `rex_api_search_it_search.php` in das Verzeichnis `lib/` Ihres Projekt-AddOns
2. Registrieren Sie gewünschte Suchkonfigurationen in der `boot.php` Ihres AddOns
3. Leeren Sie den REDAXO-Cache

### Schritt für Schritt Anleitung

1. **Datei erstellen/kopieren:**
   ```
   /redaxo/src/addons/mein_projekt_addon/lib/rex_api_search_it_search.php
   ```

2. **Suchkonfigurationen in Ihrer boot.php registrieren:**
   ```php
   // In /redaxo/src/addons/mein_projekt_addon/boot.php
   
   // Nur ausführen, wenn die Klasse existiert (nach Installation)
   if (class_exists('rex_api_search_it_search')) {
       // Standardkonfiguration
       rex_api_search_it_search::registerSearchConfiguration('default', function (search_it $search_it) {
           // Standardeinstellungen (sucht in allen indizierten Inhalten)
       });
       
       // Artikelsuche
       rex_api_search_it_search::registerSearchConfiguration('articles', function (search_it $search_it) {
           // Nur in Artikeln suchen
           $search_it->setSearchMode('like');
           // Nur Online-Artikel
           $search_it->setWhere("status = 1");
       });
   }
   ```

3. **REDAXO-Cache leeren** über das Backend unter System > Wartung oder über die Konsole:
   ```
   redaxo/bin/console cache:clear
   ```

## Plaintext-Konfiguration für saubere Teaser

Für optimale Suchergebnisse und saubere Teaser-Texte ohne HTML-Artefakte sollten Sie die Plaintext-Einstellungen in Search It konfigurieren:

1. Gehen Sie zu **Search It > Plaintext**
2. Aktivieren Sie **Plaintext aktivieren**
3. Konfigurieren Sie die **CSS-Selektoren** zur Entfernung von HTML-Elementen:
   ```
   script,style,head,header,footer,nav,aside,div.uk-animation-fade,div.module,div.sidebar,div.navigation,div.meta
   ```

4. Fügen Sie **Reguläre Ausdrücke** hinzu, um spezifische Code-Fragmente zu entfernen:
   ```
   ~\b(div|cls|id|class|uk-[\w-]+)\s*[:;]\s*[^;]*;?~
   
   ~\s{2,}~
    
   ~<<.*?>>~
    
   ~\[\[.*?\]\]~
   ```

5. Setzen Sie die Reihenfolge der Verarbeitung (Drag & Drop):
   - Selektoren (zuerst)
   - Regex
   - Textile parsen (falls verwendet)
   - HTML-Tags entfernen (zuletzt)

6. Aktivieren Sie **HTML-Tags entfernen**
7. Aktivieren Sie **Standard-Plaintext-Konvertierung durchführen**
8. Speichern Sie die Einstellungen
9. Erstellen Sie den Suchindex neu unter **Search It > Wartung**

## API-Nutzung

### URL-Aufruf

Die API kann über verschiedene URL-Parameter angesprochen werden:

```
https://www.ihre-website.de/index.php?rex-api-call=search_it_search&search=suchbegriff&key=articles&format=json
```

Parameter:
- `search`: Der Suchbegriff (erforderlich)
- `key`: Die zu verwendende Suchkonfiguration (optional, Standard: "default")
- `format`: Das Ausgabeformat (optional, Standard: "json", Alternativen: "xml")

### PHP-Integration

```php
// URL aufbauen
$url = 'index.php?rex-api-call=search_it_search&search=' . urlencode($suchbegriff) . '&key=articles';

// Mit cURL abrufen
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

// JSON dekodieren
$data = json_decode($result, true);

// Ergebnisse verarbeiten
if ($data['count'] > 0) {
    foreach ($data['hits'] as $hit) {
        echo '<h3><a href="' . $hit['url'] . '">' . $hit['name'] . '</a></h3>';
        echo '<p>' . $hit['highlightedtext'] . '</p>';
    }
} else {
    echo 'Keine Ergebnisse gefunden.';
}
```

### JavaScript-Integration

```javascript
// Suchformular-Ereignis abfangen
document.getElementById('search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const searchTerm = document.getElementById('search-input').value;
    const searchConfig = document.getElementById('search-config').value;
    
    // API-Anfrage
    fetch(`index.php?rex-api-call=search_it_search&search=${encodeURIComponent(searchTerm)}&key=${searchConfig}`)
        .then(response => response.json())
        .then(data => {
            const resultsContainer = document.getElementById('search-results');
            
            if (data.count > 0) {
                let html = `<h2>${data.count} Ergebnis${data.count > 1 ? 'se' : ''} für "${data.query}"</h2>`;
                html += '<ul class="search-results-list">';
                
                data.hits.forEach(hit => {
                    html += '<li>';
                    if (hit.url) {
                        html += `<h3><a href="${hit.url}">${hit.name || hit.filename || 'Ergebnis'}</a></h3>`;
                    } else {
                        html += `<h3>${hit.type} #${hit.fid}</h3>`;
                    }
                    html += `<p>${hit.highlightedtext}</p>`;
                    html += '</li>';
                });
                
                html += '</ul>';
                resultsContainer.innerHTML = html;
            } else {
                resultsContainer.innerHTML = `<p>Keine Ergebnisse für "${data.query}" gefunden.</p>`;
            }
        })
        .catch(error => {
            console.error('Fehler bei der Suche:', error);
            document.getElementById('search-results').innerHTML = '<p>Bei der Suche ist ein Fehler aufgetreten.</p>';
        });
});
```

## Demos

### 1. Einfaches Suchformular mit Live-Ergebnissen

```html
<!DOCTYPE html>
<html>
<head>
    <title>Search It API Demo</title>
    <style>
        .search-container {
            max-width: 800px;
            margin: 30px auto;
            font-family: Arial, sans-serif;
        }
        .search-form {
            display: flex;
            margin-bottom: 20px;
        }
        .search-input {
            flex-grow: 1;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }
        .search-button {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        .result-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .result-item h3 {
            margin-top: 0;
        }
        .result-item a {
            color: #2A5DB0;
            text-decoration: none;
        }
        .result-item a:hover {
            text-decoration: underline;
        }
        .highlight {
            background-color: #FFEB3B;
            padding: 0 2px;
        }
    </style>
</head>
<body>
    <div class="search-container">
        <h1>Search It API Demo</h1>
        
        <div class="search-form">
            <input type="text" id="search-input" class="search-input" placeholder="Suchbegriff eingeben...">
            <button id="search-button" class="search-button">Suchen</button>
        </div>
        
        <div id="search-results"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const searchButton = document.getElementById('search-button');
        const resultsContainer = document.getElementById('search-results');
        
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        function performSearch() {
            const query = searchInput.value.trim();
            
            if (query.length < 3) {
                resultsContainer.innerHTML = '<p>Bitte geben Sie mindestens 3 Zeichen ein.</p>';
                return;
            }
            
            resultsContainer.innerHTML = '<p>Suche läuft...</p>';
            
            fetch(`index.php?rex-api-call=search_it_search&search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.count === 0) {
                        resultsContainer.innerHTML = `<p>Keine Ergebnisse für "${query}" gefunden.</p>`;
                        return;
                    }
                    
                    let html = `<h2>${data.count} Ergebnis${data.count !== 1 ? 'se' : ''} für "${data.query}" (${data.time.toFixed(3)} Sekunden)</h2>`;
                    
                    data.hits.forEach(hit => {
                        html += `<div class="result-item">`;
                        
                        if (hit.type === 'article' && hit.url) {
                            html += `<h3><a href="${hit.url}">${hit.name}</a></h3>`;
                        } else if (hit.type === 'file' && hit.filename) {
                            html += `<h3><a href="${hit.url}">${hit.filename}</a></h3>`;
                        } else {
                            html += `<h3>${hit.type} #${hit.fid}</h3>`;
                        }
                        
                        html += `<p>${hit.highlightedtext}</p>`;
                        html += `</div>`;
                    });
                    
                    resultsContainer.innerHTML = html;
                })
                .catch(error => {
                    resultsContainer.innerHTML = '<p>Bei der Suche ist ein Fehler aufgetreten.</p>';
                    console.error('Suchfehler:', error);
                });
        }
    });
    </script>
</body>
</html>
```

### 2. Autocomplete-Suchfeld

```html
<!DOCTYPE html>
<html>
<head>
    <title>Search It Autocomplete Demo</title>
    <style>
        .autocomplete-container {
            position: relative;
            max-width: 600px;
            margin: 50px auto;
            font-family: Arial, sans-serif;
        }
        .autocomplete-input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
        }
        .autocomplete-result {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .autocomplete-result:last-child {
            border-bottom: none;
        }
        .autocomplete-result:hover {
            background-color: #f5f5f5;
        }
        .autocomplete-title {
            font-weight: bold;
            color: #2A5DB0;
        }
        .autocomplete-excerpt {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .autocomplete-excerpt strong {
            background-color: #FFEB3B;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="autocomplete-container">
        <h1>Search It Autocomplete</h1>
        <input type="text" id="autocomplete-input" class="autocomplete-input" placeholder="Suchbegriff eingeben...">
        <div id="autocomplete-results" class="autocomplete-results"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('autocomplete-input');
        const resultsContainer = document.getElementById('autocomplete-results');
        
        let debounceTimer;
        
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            
            const query = this.value.trim();
            
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            debounceTimer = setTimeout(() => {
                fetch(`index.php?rex-api-call=search_it_search&search=${encodeURIComponent(query)}&key=quick_search`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.count === 0) {
                            resultsContainer.innerHTML = '<div class="autocomplete-result">Keine Ergebnisse gefunden</div>';
                        } else {
                            let html = '';
                            
                            // Maximal 5 Ergebnisse anzeigen
                            const displayCount = Math.min(data.count, 5);
                            
                            for (let i = 0; i < displayCount; i++) {
                                const hit = data.hits[i];
                                html += '<div class="autocomplete-result" data-url="' + (hit.url || '#') + '">';
                                html += '<div class="autocomplete-title">' + (hit.name || hit.filename || 'Ergebnis #' + hit.id) + '</div>';
                                html += '<div class="autocomplete-excerpt">' + hit.highlightedtext + '</div>';
                                html += '</div>';
                            }
                            
                            resultsContainer.innerHTML = html;
                            
                            // Event-Listener für Klicks auf Ergebnisse
                            document.querySelectorAll('.autocomplete-result').forEach(item => {
                                item.addEventListener('click', function() {
                                    window.location.href = this.getAttribute('data-url');
                                });
                            });
                        }
                        
                        resultsContainer.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Autocomplete-Fehler:', error);
                        resultsContainer.style.display = 'none';
                    });
            }, 300); // 300ms Verzögerung, um zu viele Anfragen zu vermeiden
        });
        
        // Schließen der Ergebnisse bei Klick außerhalb
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                resultsContainer.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>
```

### 3. REDAXO-Modul für erweiterte Suche

```php
<?php
// Input
if (rex::isBackend()) {
    echo '
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                <label>Minimale Zeichenanzahl</label>
                <input class="form-control" type="text" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]" />
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                <label>Suchergebnisse pro Seite</label>
                <input class="form-control" type="text" name="REX_INPUT_VALUE[2]" value="REX_VALUE[2]" />
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                <label>Suchkonfigurationen (kommagetrennt)</label>
                <input class="form-control" type="text" name="REX_INPUT_VALUE[3]" value="REX_VALUE[3]" />
                <small class="help-block">z.B. default,articles,news,products</small>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                <label>Zielartikel für Ergebnisse (leer = aktueller Artikel)</label>
                <input class="form-control" type="text" name="REX_INPUT_VALUE[4]" value="REX_VALUE[4]" />
            </div>
        </div>
    </div>
    ';
}
?>

<?php
// Output
// Standardwerte
$minChars = (int)"REX_VALUE[1]" ?: 3;
$perPage = (int)"REX_VALUE[2]" ?: 10;
$configKeys = "REX_VALUE[3]" ? explode(',', "REX_VALUE[3]") : ['default'];
$targetArticleId = "REX_VALUE[4]" ?: rex_article::getCurrentId();

// Suchanfrage
$searchTerm = rex_request('search', 'string', '');
$page = max(1, rex_request('page', 'int', 1));
$config = rex_request('config', 'string', $configKeys[0]);

// Prüfen, ob der Config-Key erlaubt ist
if (!in_array($config, $configKeys)) {
    $config = $configKeys[0];
}

// Such-URL erstellen
$searchUrl = rex_getUrl($targetArticleId, '', ['search' => $searchTerm, 'config' => $config]);
?>

<div class="search-module">
    <form action="<?= $searchUrl ?>" method="get" class="search-form">
        <input type="hidden" name="article_id" value="<?= $targetArticleId ?>">
        
        <div class="search-inputs">
            <input type="text" name="search" value="<?= rex_escape($searchTerm) ?>" placeholder="Suchbegriff..." class="form-control" minlength="<?= $minChars ?>">
            
            <?php if (count($configKeys) > 1): ?>
            <select name="config" class="form-control">
                <?php foreach ($configKeys as $key): ?>
                <option value="<?= $key ?>" <?= $config === $key ? 'selected' : '' ?>><?= ucfirst($key) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="config" value="<?= $config ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary">Suchen</button>
        </div>
    </form>

    <?php if (strlen($searchTerm) >= $minChars): ?>
        <div class="search-results">
            <?php
            try {
                // API-Anfrage
                $api = rex_api_search_it_search::factory();
                $result = json_decode(@file_get_contents(rex::getServer() . 'index.php?rex-api-call=search_it_search&search=' . urlencode($searchTerm) . '&key=' . $config), true);
                
                if (!$result) {
                    echo '<div class="alert alert-danger">Fehler bei der Suche. Bitte versuchen Sie es später erneut.</div>';
                } elseif (isset($result['error'])) {
                    echo '<div class="alert alert-warning">' . rex_escape($result['error']) . '</div>';
                } elseif ($result['count'] == 0) {
                    echo '<div class="alert alert-info">Für Ihre Suche nach "' . rex_escape($searchTerm) . '" wurden keine Ergebnisse gefunden.</div>';
                } else {
                    // Paginierung berechnen
                    $totalResults = $result['count'];
                    $totalPages = ceil($totalResults / $perPage);
                    $offset = ($page - 1) * $perPage;
                    
                    // Nur die aktuelle Seite anzeigen
                    $pageResults = array_slice($result['hits'], $offset, $perPage);
                    
                    echo '<h2>' . $totalResults . ' Ergebnis' . ($totalResults != 1 ? 'se' : '') . ' für "' . rex_escape($searchTerm) . '"</h2>';
                    
                    foreach ($pageResults as $hit) {
                        echo '<div class="search-result">';
                        
                        // Titel und Link
                        if ($hit['type'] === 'article' && isset($hit['url'])) {
                            echo '<h3><a href="' . $hit['url'] . '">' . rex_escape($hit['name']) . '</a></h3>';
                        } elseif ($hit['type'] === 'file' && isset($hit['filename'])) {
                            echo '<h3><a href="' . $hit['url'] . '">' . rex_escape($hit['filename']) . '</a></h3>';
                        } else {
                            echo '<h3>Ergebnis #' . $hit['id'] . '</h3>';
                        }
                        
                        // Textauszug mit Hervorhebung
                        echo '<div class="search-excerpt">' . $hit['highlightedtext'] . '</div>';
                        
                        echo '</div>';
                    }
                    
                    // Paginierung anzeigen
                    if ($totalPages > 1) {
                        echo '<ul class="pagination">';
                        
                        // Zurück-Link
                        if ($page > 1) {
                            echo '<li><a href="' . rex_getUrl($targetArticleId, '', ['search' => $searchTerm, 'config' => $config, 'page' => $page - 1]) . '">&laquo; Zurück</a></li>';
                        } else {
                            echo '<li class="disabled"><span>&laquo; Zurück</span></li>';
                        }
                        
                        // Seitenzahlen
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == $page) {
                                echo '<li class="active"><span>' . $i . '</span></li>';
                            } else {
                                echo '<li><a href="' . rex_getUrl($targetArticleId, '', ['search' => $searchTerm, 'config' => $config, 'page' => $i]) . '">' . $i . '</a></li>';
                            }
                        }
                        
                        // Weiter-Link
                        if ($page < $totalPages) {
                            echo '<li><a href="' . rex_getUrl($targetArticleId, '', ['search' => $searchTerm, 'config' => $config, 'page' => $page + 1]) . '">Weiter &raquo;</a></li>';
                        } else {
                            echo '<li class="disabled"><span>Weiter &raquo;</span></li>';
                        }
                        
                        echo '</ul>';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Bei der Suche ist ein Fehler aufgetreten.</div>';
                // Fehler nur im Backend oder Debug-Modus anzeigen
                if (rex::isBackend() || rex::isDebugMode()) {
                    echo '<div class="alert alert-warning">' . rex_escape($e->getMessage()) . '</div>';
                }
            }
            ?>
        </div>
    <?php elseif ($searchTerm): ?>
        <div class="alert alert-warning">Bitte geben Sie mindestens <?= $minChars ?> Zeichen ein.</div>
    <?php endif; ?>
</div>

<style>
.search-module {
    max-width: 1200px;
    margin: 0 auto;
    font-family: sans-serif;
}
.search-form {
    margin-bottom: 30px;
}
.search-inputs {
    display: flex;
    gap: 10px;
}
.search-inputs input[type="text"] {
    flex-grow: 1;
}
.search-result {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #eee;
    border-radius: 5px;
    background: #f9f9f9;
}
.search-result h3 {
    margin-top: 0;
    margin-bottom: 10px;
}
.search-result h3 a {
    color: #2A5DB0;
    text-decoration: none;
}
.search-result h3 a:hover {
    text-decoration: underline;
}
.search-excerpt {
    font-size: 16px;
    line-height: 1.5;
}
.search-excerpt strong {
    background-color: #FFEB3B;
    font-weight: normal;
    padding: 0 2px;
}
.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 30px 0;
    justify-content: center;
}
.pagination li {
    margin: 0 5px;
}
.pagination li a,
.pagination li span {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #2A5DB0;
    text-decoration: none;
    display: block;
}
.pagination li.active span {
    background-color: #2A5DB0;
    color: white;
    border-color: #2A5DB0;
}
.pagination li.disabled span {
    color: #999;
    cursor: not-allowed;
}
</style>
```

## Registrierbare Suchkonfigurationen

In der `boot.php` Ihres AddOns können Sie beliebig viele Suchkonfigurationen registrieren:

```php
// Standardeinstellungen anpassen
rex_api_search_it_search::setDefaultConfig([
    'maxResults' => 30,
    'defaultLogicalMode' => 'or',
    'enableSimilarWords' => true
]);

// 1. Artikelsuche
rex_api_search_it_search::registerSearchConfiguration('articles', function (search_it $search_it) {
    // Nur in Artikeln suchen
    $search_it->setSearchMode('like');
    // Nur Online-Artikel
    $search_it->setWhere("status = 1");
});

// 2. Produktsuche
rex_api_search_it_search::registerSearchConfiguration('products', function (search_it $search_it) {
    // Nur in der Produkttabelle suchen
    $search_it = new search_it(rex_clang::getCurrentId());
    $search_it->searchInDbColumn('rex_product', 'name');
    $search_it->searchInDbColumn('rex_product', 'description');
    
    // Nur aktive Produkte mit Lagerbestand anzeigen
    $search_it->setWhere("status = 1 AND stock > 0");
    
    // Nach Relevanz und dann nach Name sortieren
    $search_it->setOrder([
        'RELEVANCE_SEARCH_IT' => 'DESC',
        'name' => 'ASC'
    ]);
});

// 3. URL-AddOn-Suche
rex_api_search_it_search::registerSearchConfiguration('urls', function (search_it $search_it) {
    // Sicherstellen, dass das URL-AddOn verfügbar ist
    if (rex_addon::get('url')->isAvailable()) {
        // Nur URLs eines bestimmten Profils durchsuchen
        $search_it->setWhere("profile_id = 2");
    }
});

// 4. Schnellsuche mit begrenzten Ergebnissen
rex_api_search_it_search::registerSearchConfiguration('quick_search', function (search_it $search_it) {
    // Optimierte Einstellungen für Schnellsuche/Autocomplete
    $search_it->setSearchMode('like');
    $search_it->setLimit([0, 5]);
    $search_it->setMaxTeaserChars(100);
});
```



## Abhängigkeiten

- REDAXO CMS >= 5.10.0
- Search It AddOn >= 6.9.0

## Lizenz

MIT

## Autor

Ihre Agentur oder Ihr Name
