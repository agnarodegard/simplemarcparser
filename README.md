SimpleMarcParser
===============

[![Build Status](https://travis-ci.org/scriptotek/simplemarcparser.png?branch=master)](https://travis-ci.org/scriptotek/simplemarcparser)
[![Coverage Status](https://coveralls.io/repos/scriptotek/simplemarcparser/badge.png?branch=master)](https://coveralls.io/r/scriptotek/simplemarcparser?branch=master)
[![Latest Stable Version](https://poser.pugx.org/scriptotek/simplemarcparser/version.png)](https://packagist.org/packages/scriptotek/simplemarcparser)
[![Total Downloads](https://poser.pugx.org/scriptotek/simplemarcparser/downloads.png)](https://packagist.org/packages/scriptotek/simplemarcparser)


`SimpleMarcParser` is currently a minimal MARC21/XML parser for use with `QuiteSimpleXMLElement`,
with support for the MARC21 Bibliographic, Authority and Holdings formats.

## Example:

```php
require_once('vendor/autoload.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement,
    Scriptotek\SimpleMarcParser\Parser;

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

$parser = new Parser;
$record = $parser->parse($doc->first('/srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData/marc:record'));

print $record->title;

foreach ($record->subjects as $subject) {
	print $subject['term'] . '(' . $subject['system'] . ')';
}
```

# Normalization

Some light normalization is done.

 - title (300 $a) and subtitle (300 $b) is combined into a single field `title` separated by ` : `.
 - year is converted to a integer by extracting the first four digit integer found (`c2013` → `2013`, `2009 [i.e. 2008]` → `2009` (not sure about this one..))
 - `pages` is a numeric value extracted from 300 $a. The raw value is stored in `extent`
 - …
