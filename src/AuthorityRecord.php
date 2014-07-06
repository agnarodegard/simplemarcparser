<?php namespace Scriptotek\SimpleMarcParser;

use Illuminate\Support\Contracts\JsonableInterface;
use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;
use Carbon\Carbon;

class AuthorityRecord extends Record implements JsonableInterface {

    // http://www.loc.gov/marc/authority/ad008.html
    public static $cat_rules = array(
        'a' => 'Earlier rules',
        'b' => 'AACR 1',
        'c' => 'AACR 2',
        'd' => 'AACR 2 compatible',
        'z' => 'Other',
    );

    public static $vocabularies = array(
        'a' => 'lcsh',
        'b' => 'lccsh', // LC subject headings for children's literature
        'c' => 'mesh', // Medical Subject Headings
        'd' => 'atg', // National Agricultural Library subject authority file (?)
        'k' => 'cash', // Canadian Subject Headings
        'r' => 'aat', // Art and Architecture Thesaurus 
        's' => 'sears', // Sears List of Subject Heading 
        'v' => 'rvm', // Répertoire de vedettes-matière
    );

    public function __construct(QuiteSimpleXmlElement $data) {

        // 001: Control number
        $this->id = $data->text('marc:controlfield[@tag="001"]');

        // 003: MARC code for the agency whose system control number is 
        // contained in field 001 (Control Number)
        // See http://www.loc.gov/marc/authority/ecadorg.html
        $this->agency = $data->text('marc:controlfield[@tag="003"]');

        // 005: Modified
        $this->modified = $this->parseDateTime($data->text('marc:controlfield[@tag="005"]'));

        // 008: Extract *some* information
        $f008 = $data->text('marc:controlfield[@tag="008"]');
        $r = substr($f008, 10, 1);
        $this->cataloging = isset(self::$cat_rules[$r]) ? self::$cat_rules[$r] : null;
        $r = substr($f008, 11, 1);
        $this->vocabulary = isset(self::$vocabularies[$r]) ? self::$vocabularies[$r] : null;

        // 040: 
        $source = $data->first('marc:datafield[@tag="040"]');
        if ($source) {
            $this->catalogingAgency = $source->text('marc:subfield[@code="a"]') ?: null;
            $this->language = $source->text('marc:subfield[@code="b"]') ?: null;
            $this->transcribingAgency = $source->text('marc:subfield[@code="c"]') ?: null;
            $this->modifyingAgency = $source->text('marc:subfield[@code="d"]') ?: null;
            $this->vocabulary = $source->text('marc:subfield[@code="f"]') ?: $this->vocabulary;            
        }

        // 100: Personal name (NR)
        foreach ($data->xpath('marc:datafield[@tag="100"]') as $field) {
            $this->class = 'person';
            $this->name = $field->text('marc:subfield[@code="a"]');
            $spl = explode(', ', $this->name);
            if (count($spl) == 2) {
                $this->label = $spl[1] . ' ' . $spl[0];
            } else {
                $this->label = $this->name;
            }
            $bd = $field->text('marc:subfield[@code="d"]');
            $bd = explode('-', $bd);
            $this->birth = $bd[0] ?: null;
            $this->death = (count($bd) > 1 && $bd[1]) ? $bd[1] : null;
        }

        // 110: Corporate Name (NR)
        foreach ($data->xpath('marc:datafield[@tag="110"]') as $field) {
            $this->class = 'corporate';
            // TODO: ...
        }

        // 111: Meeting Name (NR)
        foreach ($data->xpath('marc:datafield[@tag="111"]') as $field) {
            $this->class = 'meeting';
            // TODO: ...
        }

        // 130: Uniform title: Not interested for now

        // 150: Topical Term (NR)
        foreach ($data->xpath('marc:datafield[@tag="150"]') as $field) {
            $this->class = 'topicalTerm';
            $this->term = $field->text('marc:subfield[@code="a"]');
            $label = $field->text('marc:subfield[@code="a"]');
            foreach ($field->xpath('marc:subfield[@code="x"]') as $s) { 
                $label .= ' : ' . $s;
            }
            foreach ($field->xpath('marc:subfield[@code="v"]') as $s) {
                $label .= ' : ' . $s;
            }
            foreach ($field->xpath('marc:subfield[@code="y"]') as $s) {
                $label .= ' : ' . $s;
            }
            foreach ($field->xpath('marc:subfield[@code="z"]') as $s) {
                $label .= ' : ' . $s;
            }
            $this->label = $label;
            // TODO: ...
        }

        // 151: Geographic Term (NR)
        // 155: Genre/form Term (NR)

        // 375: Gender (R)
        $genders = array();
        foreach ($data->xpath('marc:datafield[@tag="375"]') as $field) {
            $gender = $field->text('marc:subfield[@code="a"]');
            $start = $field->text('marc:subfield[@code="s"]');
            $end = $field->text('marc:subfield[@code="e"]');
            $genders[] = array(
                'value' => $gender,
                'from' => $start,
                'until' => $end,
            );
        }
        $this->genders = $genders;

        // Alias gender to the last value to make utilizing easier
        $this->gender = (count($this->genders) > 0)
            ? $this->genders[count($this->genders) - 1]['value']  // assume sane ordering for now
            : null;

        // 400: See From Tracing-Personal Name (R)
        $nameVariants = array();
        foreach ($data->xpath('marc:datafield[@tag="400"]') as $field) {
            $nameVariants[] = $field->text('marc:subfield[@code="a"]');
        }
        $this->nameVariants = $nameVariants;

        // TODO: rest

    }

}

/*
<?xml version="1.0"?>
<recordData>
    <marc:record xmlns:marc="info:lc/xmlns/marcxchange-v1" format="MARC21" type="Authority">
    <marc:leader>99999nz a2299999n 4500</marc:leader>
    <marc:controlfield tag="001">x90531735</marc:controlfield>
    <marc:controlfield tag="003">NO-TrBIB</marc:controlfield>
    <marc:controlfield tag="005">20090407000000.0</marc:controlfield>
    <marc:controlfield tag="008">090407n adznnaabn| |a|ana| </marc:controlfield>
    <marc:datafield tag="016" ind1="7" ind2=" ">
        <marc:subfield code="a">x90531735</marc:subfield>
        <marc:subfield code="2">NO-TrBIB</marc:subfield>
    </marc:datafield>
    <marc:datafield tag="040" ind1=" " ind2=" ">
        <marc:subfield code="a">NO-OsNB</marc:subfield>
        <marc:subfield code="b">nob</marc:subfield>
        <marc:subfield code="c">NO-TrBIB</marc:subfield>
        <marc:subfield code="f">noraf</marc:subfield>
    </marc:datafield>
    <marc:datafield tag="100" ind1="1" ind2=" ">
        <marc:subfield code="a">Bakke, Dagfinn</marc:subfield>
        <marc:subfield code="d">1933-</marc:subfield>
    </marc:datafield>
    <marc:datafield tag="375" ind1=" " ind2=" ">
        <marc:subfield code="a">male</marc:subfield>
    </marc:datafield>
    <marc:datafield tag="400" ind1="0" ind2=" ">
        <marc:subfield code="a">DAN</marc:subfield>
    </marc:datafield>
</marc:record>
</recordData>
*/