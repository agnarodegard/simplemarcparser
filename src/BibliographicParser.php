<?php namespace Scriptotek\SimpleMarcParser;

use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

class BibliographicParser {

    public function __construct() {

    }

    private function parseAuthority(&$node, &$out) {
        $authority = $node->text('marc:subfield[@code="0"]');
        if (!empty($authority)) {
            $out['authority'] = $authority;
            $asplit = explode(')', $authority);
            if (substr($authority, 1, 8) === 'NO-TrBIB') {
                $out['bibsys_identifier'] = substr($authority, strpos($authority, ')') + 1);
            }
        }
    }

    public function parseRelationship($node)
    {
        $rel = array();

        $x = preg_replace('/\(.*?\)/', '', $node->text('marc:subfield[@code="w"]'));
        if (!empty($x)) $rel['id'] = $x;

        $x = $node->text('marc:subfield[@code="t"]');
        if (!empty($x)) $rel['title'] = $x;

        $x = $node->text('marc:subfield[@code="g"]');
        if (!empty($x)) $rel['related_parts'] = $x;

        $x = $node->text('marc:subfield[@code="x"]');
        if (!empty($x)) $rel['issn'] = $x;

        $x = $node->text('marc:subfield[@code="z"]');
        if (!empty($x)) $rel['isbn'] = $x;

        return $rel;
    }

    public function parse(QuiteSimpleXmlElement $record) {

        $output = array();

        $output['id'] = $record->text('marc:controlfield[@tag="001"]');
        $output['authors'] = array();
        $output['subjects'] = array();
        $output['series'] = array();
        $output['electronic'] = false;
        $output['is_series'] = false;
        $output['is_multivolume'] = false;
        $output['fulltext'] = array();
        $output['classifications'] = array();
        $output['notes'] = array();

        foreach ($record->xpath('marc:datafield') as $node) {
            $marcfield = intval($node->attributes()->tag);
            switch ($marcfield) {
                /*
                case 8:                                                             // ???
                    $output['form'] = $node->text('marc:subfield[@code="a"]');
                    break;
                */

                // 010 - Library of Congress Control Number (NR)
                case 10:
                    $output['lccn'] = $node->text('marc:subfield[@code="a"]');
                    break;

                // 020 - International Standard Book Number (R)
                case 20:                                                            // Test added
                    $isbn = $node->text('marc:subfield[@code="a"]');
                    $isbn = preg_replace('/^([0-9\-xX]+).*$/', '\1', $isbn);
                    if (empty($isbn)) break;
                    if (!isset($output['isbn'])) $output['isbn'] = array();
                    array_push($output['isbn'], $isbn);
                    break;

                // 082 - Dewey Decimal Classification Number (R)
                case 82:                                                            // Test?
                    if (!isset($output['classifications'])) $output['classifications'] = array();
                    $cl = array('system' => 'dewey');

                    $map = array(
                        'a' => array('number', '^.*?([0-9.]+)\/?([0-9.]*).*$', '\1\2'),
                        '2' => 'edition',
                        'q' => 'assigning_agency'
                    );
                    foreach ($map as $key => $val) {
                        $t = $node->text('marc:subfield[@code="' . $key . '"]');
                        if (!is_array($val)) $val = array($val);
                        if (count($val) > 2) $t = preg_replace('/' . $val[1] . '/', $val[2], $t);
                        if (!empty($t)) $cl[$val[0]] = $t;
                    }

                    $output['classifications'][] = $cl;
                    break;

                /*
                case 89:
                    if (!isset($output['klass'])) $output['klass'] = array();
                    $klass = $node->text('marc:subfield[@code="a"]');
                    $klass = preg_replace('/[^0-9.]/', '', $klass);
                    foreach ($output['klass'] as $kitem) {
                        if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                            continue 3;
                        }
                    }
                    array_push($output['klass'], array('kode' => $klass, 'system' => 'dewey'));
                    break;
                */

                case 100:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                        'role' => 'main'
                    );
                    $this->parseAuthority($node, $author);

                    $output['authors'][] = $author;
                    break;

                case 110:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                        'role' => 'corporate'
                    );
                    $this->parseAuthority($node, $author);

                    $output['authors'][] = $author;
                    break;

                case 130:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                        'role' => 'uniform'
                    );
                    $this->parseAuthority($node, $author);

                    $output['authors'][] = $author;
                    break;

                // 245 : Title Statement (NR)
                case 245:
                    $output['title'] = rtrim($node->text('marc:subfield[@code="a"]'), " \t\n\r\0\x0B:-");
                    $output['subtitle'] = $node->text('marc:subfield[@code="b"]');
                    if (preg_match('/elektronisk ressurs/', $node->text('marc:subfield[@code="h"]'))) {
                        $output['electronic'] = true;
                    }

                    // $n : Number of part/section of a work (R)
                    $part_no = $node->text('marc:subfield[@code="n"]');
                    if ($part_no !== '') $output['part_no'] = $part_no;

                    // $p : Name of part/section of a work (R)
                    $part_name = $node->text('marc:subfield[@code="p"]');
                    if ($part_name !== '') $output['part_name'] = $part_name;

                    // $h : Medium (NR)
                    $medium = $node->text('marc:subfield[@code="h"]');
                    if ($medium !== '') $output['medium'] = $medium;

                    break;

                case 250:
                    $output['edition'] = $node->text('marc:subfield[@code="a"]');
                    break;

                case 260:
                    $output['publisher'] = $node->text('marc:subfield[@code="b"]');
                    $y = preg_replace('/^.*?([0-9]{4}).*$/', '\1', current($node->xpath('marc:subfield[@code="c"]')));
                    $output['year'] = $y ? intval($y) : null;
                    break;

                case 300:
                    $output['extent'] = $node->text('marc:subfield[@code="a"]');
                    preg_match(
                        '/([0-9]+) (s.|p.|pp.)/',
                        $node->text('marc:subfield[@code="a"]'),
                        $matches
                    );
                    if ($matches) {
                        $output['pages'] = $matches[1];
                    }
                    break;

                /*
                case 490:
                    $serie = array(
                        'title' => $node->text('marc:subfield[@code="a"]'),
                        'volume' => $node->text('marc:subfield[@code="v"]')
                    );
                    $output['series'][] = $serie;
                    break;
                */

                // 500 : General Note (R)
                case 500:

                    // $a - General note (NR)
                    $output['notes'][] = $node->text('marc:subfield[@code="a"]');
                    break;

                case 505:

                    // <datafield tag="520" ind1=" " ind2=" ">
                    //     <subfield code="a">"The conceptual changes brought by modern physics are important, radical and fascinating, yet they are only vaguely understood by people working outside the field. Exploring the four pillars of modern physics - relativity, quantum mechanics, elementary particles and cosmology - this clear and lively account will interest anyone who has wondered what Einstein, Bohr, Schro&#x308;dinger and Heisenberg were really talking about. The book discusses quarks and leptons, antiparticles and Feynman diagrams, curved space-time, the Big Bang and the expanding Universe. Suitable for undergraduate students in non-science as well as science subjects, it uses problems and worked examples to help readers develop an understanding of what recent advances in physics actually mean"--</subfield>
                    //     <subfield code="c">Provided by publisher.</subfield>
                    // </datafield>
                    $output['contents'] = $node->text('marc:subfield[@code="a"]');
                    break;

                case 520:

                    // <datafield tag="520" ind1=" " ind2=" ">
                    //     <subfield code="a">"The conceptual changes brought by modern physics are important, radical and fascinating, yet they are only vaguely understood by people working outside the field. Exploring the four pillars of modern physics - relativity, quantum mechanics, elementary particles and cosmology - this clear and lively account will interest anyone who has wondered what Einstein, Bohr, Schro&#x308;dinger and Heisenberg were really talking about. The book discusses quarks and leptons, antiparticles and Feynman diagrams, curved space-time, the Big Bang and the expanding Universe. Suitable for undergraduate students in non-science as well as science subjects, it uses problems and worked examples to help readers develop an understanding of what recent advances in physics actually mean"--</subfield>
                    //     <subfield code="c">Provided by publisher.</subfield>
                    // </datafield>
                    $output['summary'] = array(
                        'assigning_source' => $node->text('marc:subfield[@code="c"]'),
                        'text' => $node->text('marc:subfield[@code="a"]')
                    );
                    break;

                // 580 : Complex Linking Note (R)
                case 580:

                    if ($record->has('marc:datafield[@tag="780"]')) {
                        $output['preceding'] = isset($output['preceding']) ? $output['preceding'] : array();
                        $output['preceding']['note'] = $node->text('marc:subfield[@code="a"]');

                    } else if ($record->has('marc:datafield[@tag="785"]')) {
                        $output['succeeding'] = isset($output['succeeding']) ? $output['succeeding'] : array();
                        $output['succeeding']['note'] = $node->text('marc:subfield[@code="a"]');

                    } else if ($record->has('marc:datafield[@tag="773"]')) {
                        $output['part_of'] = isset($output['part_of']) ? $output['part_of'] : array();
                        $output['part_of']['note'] = $node->text('marc:subfield[@code="a"]');
                    }
                    break;

                case 650:
                    $ind2 = $node->attr('ind2');
                    $emne = $node->text('marc:subfield[@code="a"]');

                      // topical, geographic, chronological, or form aspects
                      $tmp = array('subdivisions' => array());

                      $term = trim($emne, '.');
                      if ($term !== '') $tmp['term'] = $term;

                      $vocabularies = array(
                          '0' => 'lcsh',
                          '1' => 'lccsh', // LC subject headings for children's literature
                          '2' => 'mesh', // Medical Subject Headings
                          '3' => 'atg', // National Agricultural Library subject authority file (?)
                          // 4 : unknown
                          '5' => 'cash', // Canadian Subject Headings
                          '6' => 'rvm', // Répertoire de vedettes-matière
                      );

                      $voc = $node->text('marc:subfield[@code="2"]');
                      if (isset($vocabularies[$ind2])) {
                          $tmp['vocabulary'] = $vocabularies[$ind2];
                      } else if (!empty($voc)) {
                          $tmp['vocabulary'] = $voc;
                      }

                      $topical = $node->text('marc:subfield[@code="x"]');
                      if ($topical !== '') $tmp['subdivisions']['topical'] = trim($topical, '.');

                      $chrono = $node->text('marc:subfield[@code="y"]');
                      if ($chrono !== '') $tmp['subdivisions']['chronological'] = $chrono;

                      $geo = $node->text('marc:subfield[@code="z"]');
                      if ($geo !== '') $tmp['subdivisions']['geographic'] = $geo;

                      $form = $node->text('marc:subfield[@code="v"]');
                      if ($form !== '') $tmp['subdivisions']['form'] = $form;

                      array_push($output['subjects'], $tmp);
                    break;

                case 700:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                    );
                    $author['role'] = $node->text('marc:subfield[@code="4"]') 
                        ?: ($node->text('marc:subfield[@code="e"]') ?: 'added');

                    $this->parseAuthority($node, $author);

                    $dates = $node->text('marc:subfield[@code="d"]');
                    if (!empty($dates)) {
                        $author['dates'] = $dates;
                    }

                    $output['authors'][] = $author;
                    break;

                case 710:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                        'role' => 'added_corporate'
                    );
                    $this->parseAuthority($node, $author);

                    $output['authors'][] = $author;
                    break;

                // 773 : Host Item Entry (R)
                // See also: 580
                case 773:
                    $output['part_of'] = isset($output['part_of']) ? $output['part_of'] : array();
                    $output['part_of']['relationship'] = $node->text('marc:subfield[@code="i"]');
                    $output['part_of']['title'] = $node->text('marc:subfield[@code="t"]');
                    $output['part_of']['issn'] = $node->text('marc:subfield[@code="x"]');
                    $output['part_of']['id'] = preg_replace('/\(NO-TrBIB\)/', '', $node->text('marc:subfield[@code="w"]'));
                    $output['part_of']['volume'] = $node->text('marc:subfield[@code="v"]');
                    break;

                // 776 : Additional Physical Form Entry (R)
                case 776:
                        // <marc:datafield tag="776" ind1="0" ind2=" ">
                        //     <marc:subfield code="z">9781107602175</marc:subfield>
                        //     <marc:subfield code="w">(NO-TrBIB)132191512</marc:subfield>
                        // </marc:datafield>
                    $tmp = $this->parseRelationship($node);
                    $output['other_form'] = $tmp;
                    break;

                // 780 : Preceding Entry (R)
                // Information concerning the immediate predecessor of the target item
                case 780:
                    // <marc:datafield tag="780" ind1="0" ind2="0">
                    //     <marc:subfield code="w">(NO-TrBIB)920713874</marc:subfield>
                    //     <marc:subfield code="g">nr 80(1961)</marc:subfield>
                    // </marc:datafield>
                    $output['preceding'] = isset($output['preceding']) ? $output['preceding'] : array();

                    if (!isset($output['preceding']['items'])) {
                        $output['preceding']['items'] = array();
                    }
                    $output['preceding']['items'][] = $this->parseRelationship($node);

                    $ind2 = $node->attr('ind2');
                    $relationship_types = array(
                        '0' => 'Continues',
                        '1' => 'Continues in part',
                        '2' => 'Supersedes',
                        '3' => 'Supersedes in part',
                        '4' => 'Formed by the union of',  // ... and ...',
                        '5' => 'Absorbed',
                        '6' => 'Absorbed in part',
                        '7' => 'Separated from',
                    );
                    if (isset($relationship_types[$ind2])) {
                        $output['preceding']['relationship_type'] = $relationship_types[$ind2];
                    }

                    break;

                // 785 : Succeeding Entry (R)
                // Information concerning the immediate successor to the target item
                case 785:
                    // <marc:datafield tag="785" ind1="0" ind2="0">
                    //     <marc:subfield code="w">(NO-TrBIB)920713874</marc:subfield>
                    //     <marc:subfield code="g">nr 80(1961)</marc:subfield>
                    // </marc:datafield>
                    $output['succeeding'] = isset($output['succeeding']) ? $output['succeeding'] : array();

                    if (!isset($output['succeeding']['items'])) {
                        $output['succeeding']['items'] = array();
                    }
                    $output['succeeding']['items'][] = $this->parseRelationship($node);

                    $ind2 = $node->attr('ind2');
                    $relationship_types = array(
                        '0' => 'Continued by',
                        '1' => 'Continued in part by',
                        '2' => 'Superseded by',
                        '3' => 'Superseded in part by',
                        '4' => 'Absorbed by',
                        '5' => 'Absorbed in part by',
                        '6' => 'Split into',  // ... and ...',
                        '7' => 'Merged with',  // ... to form ...',
                        '8' => 'Changed back to',
                    );

                    if (isset($relationship_types[$ind2])) {
                        $output['succeeding']['relationship_type'] = $relationship_types[$ind2];
                    }
                    break;

                // 830 : Series Added Entry – Uniform Title (R)
                case 830:
                    $serie = array(
                        'title' => $node->text('marc:subfield[@code="a"]'),
                        'id' => preg_replace('/\(NO-TrBIB\)/', '', $node->text('marc:subfield[@code="w"]')),
                        'volume' => $node->text('marc:subfield[@code="v"]')
                    );
                    $output['series'][] = $serie;
                    break;

                case 856:
                case 956:
                    # MARC 21 uses field 856 for electronic "links", where you can have URLs for example covers images and/or blurbs.
                    # 956 ?

                        // <marc:datafield tag="856" ind1="4" ind2="2">
                        //     <marc:subfield code="3">Beskrivelse fra forlaget (kort)</marc:subfield>
                        //     <marc:subfield code="u">http://content.bibsys.no/content/?type=descr_publ_brief&amp;isbn=0521176832</marc:subfield>
                        // </marc:datafield>
                        // <marc:datafield tag="956" ind1="4" ind2="2">
                        //     <marc:subfield code="3">Omslagsbilde</marc:subfield>
                        //     <marc:subfield code="u">http://innhold.bibsys.no/bilde/forside/?size=mini&amp;id=9780521176835.jpg</marc:subfield>
                        //     <marc:subfield code="q">image/jpeg</marc:subfield>
                        // </marc:datafield>
                    $description = $node->text('marc:subfield[@code="3"]');
                    if (in_array($description, array('Cover image', 'Omslagsbilde'))) {
                        $output['cover_image'] = $node->text('marc:subfield[@code="u"]');

                        // Silly hack to get larger images from Bibsys:
                        $output['cover_image'] = str_replace('mini','stor',$output['cover_image']);
                        $output['cover_image'] = str_replace('LITE','STOR',$output['cover_image']);
                    }
                    if (in_array($description, array('Beskrivelse fra forlaget (kort)', 'Beskrivelse fra forlaget (lang)'))) {
                        $output['description'] = $node->text('marc:subfield[@code="u"]');
                    }
                    break;

                // 991 Kriterium für Sekundärsortierung (R) ???
                // Ref: http://ead.nb.admin.ch/web/marc21/dmarcb991.pdf
                // Hvor i BIBSYSMARC kommer dette fra?
                case 991:

                    // Multi-volume work (flerbindsverk), parts linked through 773 w
                    if ($node->text('marc:subfield[@code="a"]') == 'volumes') {
                        $output['is_multivolume'] = true;
                    }

                    // Series (serier), parts linked through 830 w
                    if ($node->text('marc:subfield[@code="a"]') == 'parts') {
                        $output['is_series'] = true;
                    }

                    break;

            }
        }
        return $output;
    }

}
