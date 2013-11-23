SimpleMarcParser
===============

[![Build Status](https://travis-ci.org/danmichaelo/simplemarcparser.png?branch=master)](https://travis-ci.org/danmichaelo/simplemarcparser)
[![Coverage Status](https://coveralls.io/repos/danmichaelo/simplemarcparser/badge.png?branch=master)](https://coveralls.io/r/danmichaelo/simplemarcparser?branch=master)
[![Latest Stable Version](https://poser.pugx.org/danmichaelo/simplemarcparser/version.png)](https://packagist.org/packages/danmichaelo/simplemarcparser)
[![Total Downloads](https://poser.pugx.org/danmichaelo/simplemarcparser/downloads.png)](https://packagist.org/packages/danmichaelo/simplemarcparser)


`SimpleMarcParser` is currently a minimal MARC21/XML parser for use with `QuiteSimpleXMLElement`.

## Example:

```php
require_once('vendor/autoload.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement,
    Danmichaelo\SimpleMarcParser\BibliographicParser;

$data = file_get_contents('http://sru.bibsys.no/search/biblio?' . http_build_query(array(
	'version' => '1.2',
	'operation' => 'searchRetrieve',
	'recordSchema' => 'marcxchange',
	'query' => 'bs.isbn="0-521-43291-x"'
)));

$doc = new QuiteSimpleXMLElement($data);
$doc->registerXPathNamespaces(array(
        'srw' => 'http://www.loc.gov/zing/srw/',
        'marc' => 'http://www.loc.gov/MARC21/slim',
        'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
    ));

$parser = new BibliographicParser;
$record = $parser->parse($doc->first('/srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData/marc:record'));

print $record['title'] . $record['subtitle'];

foreach ($record['subjects'] as $subject) {
	print $subject['term'] . '(' . $subject['system'] . ')';
}
```
