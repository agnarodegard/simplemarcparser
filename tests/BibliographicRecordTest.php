<?php namespace Scriptotek\SimpleMarcParser;

require 'vendor/autoload.php';
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Carbon\Carbon;

class BibliographicRecordTest extends \PHPUnit_Framework_TestCase {

    private function parseRecordData($data)
    {
        $dom = new QuiteSimpleXMLElement('<?xml version="1.0"?>
            <marc:record xmlns:marc="info:lc/xmlns/marcxchange-v1" format="MARC21" type="Bibliographic">
                ' . $data . '
            </marc:record>');
        $dom->registerXPathNamespaces(array(
            'marc' => 'http://www.loc.gov/MARC21/slim'
        ));

        return new BibliographicRecord($dom);
    }

    public function testMaterialIsPrintedBook() {
        $out = $this->parseRecordData('
            <marc:leader>99999 am a2299999 c 4500</marc:leader>
            <marc:controlfield tag="001">131381679</marc:controlfield>
            <marc:controlfield tag="007">ta</marc:controlfield>
            <marc:controlfield tag="008">130916s2011                  000 u|eng d</marc:controlfield>
        ');

        $this->assertEquals('Book', $out->material);
        $this->assertFalse($out->electronic);
    }

    public function testMaterialIsElectronicBook() {
        $out = $this->parseRecordData('
            <marc:leader>99999 am a22999997c 4500</marc:leader>
            <marc:controlfield tag="001">133788229</marc:controlfield>
            <marc:controlfield tag="007">cr |||||||||||</marc:controlfield>
            <marc:controlfield tag="008">140313s2011            o     000 u|eng d</marc:controlfield>
        ');

        $this->assertEquals('Book', $out->material);
        $this->assertTrue($out->electronic);
    }

    public function testMaterialIsPrintedPeriodical() {
        $out = $this->parseRecordData('
            <marc:leader>99999 as a2299999 c 4500</marc:leader>
            <marc:controlfield tag="001">981315402</marc:controlfield>
            <marc:controlfield tag="007">ta</marc:controlfield>
            <marc:controlfield tag="008">130625uuuuuuuuu      p       |    0eng d</marc:controlfield>
        ');

        $this->assertEquals('Periodical', $out->material);
        $this->assertFalse($out->electronic);
    }

    public function testMaterialIsElectronicPeriodical() {
        $out = $this->parseRecordData('
            <marc:leader>99999 as a22999997c 4500</marc:leader>
            <marc:controlfield tag="001">080880762</marc:controlfield>
            <marc:controlfield tag="007">cr |||||||||||</marc:controlfield>
            <marc:controlfield tag="008">110404uuuuuuuuu      p o     |    0eng d</marc:controlfield>
        ');

        $this->assertEquals('Periodical', $out->material);
        $this->assertTrue($out->electronic);
    }

    public function testMaterialIsPrintedSeries() {
        $out = $this->parseRecordData('
            <marc:leader>99999 as a2299999 c 4500</marc:leader>
            <marc:controlfield tag="001">801065968</marc:controlfield>
            <marc:controlfield tag="007">ta</marc:controlfield>
            <marc:controlfield tag="008">051128uuuuuuuuu      m       |    0mul d</marc:controlfield>
        ');

        $this->assertEquals('Series', $out->material);
        $this->assertFalse($out->electronic);
    }

    public function testMarc001() {
        $out = $this->parseRecordData('
            <marc:controlfield tag="001">12149361x</marc:controlfield>
        ');

        $this->assertEquals('12149361x', $out->id);
    }

    public function testModified() {
        $out1 = $this->parseRecordData('
            <marc:controlfield tag="005">19970411</marc:controlfield>
        ');
        $out2 = $this->parseRecordData('
            <marc:controlfield tag="005">19940223151047.0</marc:controlfield>
        ');

        $this->assertEquals(Carbon::createFromDate(1997, 4, 11), $out1->modified);
        $this->assertEquals(Carbon::create(1994, 2, 23, 15, 10, 47), $out2->modified);
    }

    public function testCreated() {
        $out1 = $this->parseRecordData('
            <marc:controlfield tag="008">970411s1996 000 u|eng d</marc:controlfield>
        ');
        $out2 = $this->parseRecordData('
            <marc:controlfield tag="008">700101s1996 000 u|eng d</marc:controlfield>
        ');
        $out3 = $this->parseRecordData('
            <marc:controlfield tag="008">690101s1996 000 u|eng d</marc:controlfield>
        ');

        $this->assertEquals(Carbon::createFromDate(1997, 4, 11), $out1->created);
        $this->assertEquals(Carbon::createFromDate(1970, 1, 1), $out2->created);
        $this->assertEquals(Carbon::createFromDate(2069, 1, 1), $out3->created);
    }

    public function testMarc010() {
        $out = $this->parseRecordData('
            <marc:datafield tag="010" ind1=" " ind2=" ">
                <marc:subfield code="a">  2012011618</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('2012011618', $out->lccn);
    }

    public function testIsbn() {
        // Should strip off comments, but leave hyphens
        $out = $this->parseRecordData('
            <marc:datafield tag="020" ind1=" " ind2=" ">
                <marc:subfield code="a">978-8243005129 (ib.)</marc:subfield>
                <marc:subfield code="c">Nkr 339.00</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out->isbns);
        $this->assertEquals('978-8243005129', $out->isbns[0]);
    }

    public function testCanceledIsbn() {
        // 020 $z : Cancelled/invalid ISBN
        $out = $this->parseRecordData('
            <marc:datafield tag="020" ind1=" " ind2=" ">
                <marc:subfield code="z">9788243005129 (ib.)</marc:subfield>
                <marc:subfield code="c">Nkr 339.00</marc:subfield>
            </marc:datafield>
        ');

        $this->assertNull($out->isbns);
    }

    public function testIsbnWithX() {
        // Test that X-s are preserved
        $out = $this->parseRecordData('
            <marc:datafield tag="020" ind1=" " ind2=" ">
                <marc:subfield code="a">1-85723-457-X (h.)</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('1-85723-457-X', $out->isbns[0]);
    }

    public function testMarc082() {
        $out = $this->parseRecordData('
            <marc:datafield tag="082" ind1="0" ind2="4">
                <marc:subfield code="a">333.914/02[U]</marc:subfield>
                <marc:subfield code="2">23</marc:subfield>
            </marc:datafield>
        ');

        $klass = $out->classifications[0];
        $this->assertEquals('dewey', $klass['system']);
        $this->assertEquals('333.91402', $klass['number']);
        $this->assertEquals('23', $klass['edition']);
        $this->assertArrayNotHasKey('assigning_agency', $klass);
    }

    public function testMarc082b() {
        $out = $this->parseRecordData('
            <marc:datafield tag="082" ind1="7" ind2="4">
                <marc:subfield code="a">639.3</marc:subfield>
                <marc:subfield code="q">NO-OsNB</marc:subfield>
                <marc:subfield code="2">5/nor</marc:subfield>
            </marc:datafield>
        ');

        $klass = $out->classifications[0];
        $this->assertEquals('dewey', $klass['system']);
        $this->assertEquals('639.3', $klass['number']);
        $this->assertEquals('5/nor', $klass['edition']);
        $this->assertEquals('NO-OsNB', $klass['assigning_agency']);
    }


    public function testMarc100() {
        $out = $this->parseRecordData('
            <marc:datafield tag="100" ind1="1" ind2=" ">
                <marc:subfield code="a">Bjerkestrand, Bernt</marc:subfield>
                <marc:subfield code="d">1950-</marc:subfield>
                <marc:subfield code="0">(NO-TrBIB)x12001130</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out->authors);

        $el = $out->authors[0];
        $this->assertEquals('Bernt Bjerkestrand', $el['name']);
        $this->assertEquals('x12001130', $el['bibsys_identifier']);
    }

    public function testMarc100b() {
        $out = $this->parseRecordData('
            <marc:datafield tag="100" ind1="1" ind2=" ">
                <marc:subfield code="a">Bjerkestrand, Bernt</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out->authors);

        $el = $out->authors[0];
        $this->assertEquals('Bernt Bjerkestrand', $el['name']);
        $this->assertArrayNotHasKey('authority', $el);
    }

    public function testMarc110() {
        $out = $this->parseRecordData('
           <marc:datafield tag="110" ind1="2" ind2=" ">
                <marc:subfield code="a">Norge</marc:subfield>
                <marc:subfield code="b">Miljøverndepartementet</marc:subfield>
                <marc:subfield code="0">(NO-TrBIB)x90051067</marc:subfield>
            </marc:datafield>
        ');

        // TODO
    }

    public function testMarc245() {
        // Colon should be trimmed off title
        $out = $this->parseRecordData('
            <marc:datafield tag="245" ind1="1" ind2="0">
                <marc:subfield code="a">Evolusjon :</marc:subfield>
                <marc:subfield code="b">naturens kulturhistorie</marc:subfield>
                <marc:subfield code="c">Markus Lindholm</marc:subfield>
                <marc:subfield code="h">[videoopptak]</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('Evolusjon : naturens kulturhistorie', $out->title);
        $this->assertEquals('[videoopptak]', $out->medium);
    }

    public function testMarc245part() {
        $out1 = $this->parseRecordData('
            <marc:datafield tag="245" ind1="0" ind2="0">
                <marc:subfield code="a">No ordinary genius</marc:subfield>
            </marc:datafield>
        ');
        $out2 = $this->parseRecordData('
            <marc:datafield tag="245" ind1="0" ind2="0">
                <marc:subfield code="a">No ordinary genius</marc:subfield>
                <marc:subfield code="n">Part one</marc:subfield>
            </marc:datafield>
        ');
        $out3 = $this->parseRecordData('
            <marc:datafield tag="245" ind1="1" ind2="0">
                <marc:subfield code="a">Verehrte An- und Abwesende!</marc:subfield>
                <marc:subfield code="b">Originaltonaufnahmen 1921-1951</marc:subfield>
                <marc:subfield code="n">CD1</marc:subfield>
                <marc:subfield code="p">[1921-1941]</marc:subfield>
                <marc:subfield code="h">[lydopptak]</marc:subfield>
            </marc:datafield>
        ');

        $this->assertNull($out1->part_no);
        $this->assertNull($out1->part_name);
        $this->assertNull($out2->part_name);

        $this->assertEquals('Part one', $out2->part_no);
        $this->assertEquals('CD1', $out3->part_no);
        $this->assertEquals('[1921-1941]', $out3->part_name);
        $this->assertEquals('[lydopptak]', $out3->medium);
    }

    public function testMarc250() {
        $out = $this->parseRecordData('
           <marc:datafield tag="250" ind1=" " ind2=" ">
                <marc:subfield code="a">2. utg.</marc:subfield>
            </marc:datafield>
        ');

        // TODO
    }

    public function testMarc260c() {
        $out1 = $this->parseRecordData('
            <marc:datafield tag="260" ind1=" " ind2=" ">
                <marc:subfield code="c">c2013</marc:subfield>
            </marc:datafield>
        ');
        $out2 = $this->parseRecordData('
            <marc:datafield tag="260" ind1=" " ind2=" ">
                <marc:subfield code="c">2009 [i.e. 2008]</marc:subfield>
            </marc:datafield>
        ');
        $out3 = $this->parseRecordData('
            <marc:datafield tag="260" ind1=" " ind2=" ">
            </marc:datafield>
        ');

        $this->assertEquals(2013, $out1->year);
        $this->assertEquals(2009, $out2->year);
        $this->assertNull($out3->year);
    }

    public function testMarc300() {
        $out1 = $this->parseRecordData('
            <marc:datafield tag="300" ind1=" " ind2=" ">
                <marc:subfield code="a">353 s.</marc:subfield>
                <marc:subfield code="b">ill.</marc:subfield>
                <marc:subfield code="c">27 cm</marc:subfield>
            </marc:datafield>
        ');

        $out2 = $this->parseRecordData('
            <marc:datafield tag="300" ind1=" " ind2=" ">
                <marc:subfield code="a">1 videoplate (DVD-video) (1 t 36 min)</marc:subfield>
                <marc:subfield code="b">lyd, kol.</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('353 s.', $out1->extent);
        $this->assertEquals(353, $out1->pages);

        $this->assertEquals('1 videoplate (DVD-video) (1 t 36 min)', $out2->extent);
        $this->assertNull($out2->pages);
    }

    public function testMarc500() {
        $out = $this->parseRecordData('
            <marc:datafield tag="500" ind1=" " ind2=" ">
                <marc:subfield code="a">Forts.som: Acoustical imaging. 8(1980)</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out->notes);
        $this->assertEquals('Forts.som: Acoustical imaging. 8(1980)', $out->notes[0]);
    }

    public function testMarc650() {
        $out1 = $this->parseRecordData('
            <marc:datafield tag="650" ind1=" " ind2="7">
                <marc:subfield code="a">sjømat</marc:subfield>
                <marc:subfield code="z">Norge</marc:subfield>
                <marc:subfield code="2">tekord</marc:subfield>
            </marc:datafield>
        ');
        $out2 = $this->parseRecordData('
            <marc:datafield tag="650" ind1=" " ind2="0">
                <marc:subfield code="a">Optoelectronics industry</marc:subfield>
                <marc:subfield code="x">Directories.</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out1->subjects);
        $this->assertEquals('tekord', $out1->subjects[0]['vocabulary']);
        $this->assertEquals('sjømat', $out1->subjects[0]['term']);
        $this->assertEquals('Norge', $out1->subjects[0]['subdivisions']['geographic']);

        $this->assertCount(1, $out2->subjects);
        $this->assertEquals('lcsh', $out2->subjects[0]['vocabulary']);
        $this->assertEquals('Optoelectronics industry', $out2->subjects[0]['term']);
        $this->assertEquals('Directories', $out2->subjects[0]['subdivisions']['topical']);
    }

    public function testMarc700() {
        $out1 = $this->parseRecordData('
            <marc:datafield tag="700" ind1="1" ind2=" ">
                <marc:subfield code="a">Almås, Karl Andreas</marc:subfield>
                <marc:subfield code="d">1952-</marc:subfield>
                <marc:subfield code="e">red.</marc:subfield>
                <marc:subfield code="0">(NO-TrBIB)x90235102</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(1, $out1->authors);
        $this->assertEquals('Karl Andreas Almås', $out1->authors[0]['name']);
        $this->assertEquals('red.', $out1->authors[0]['role']);
        $this->assertEquals('1952-', $out1->authors[0]['dates']);
        $this->assertEquals('x90235102', $out1->authors[0]['bibsys_identifier']);
    }

    public function testMarc710() {
        // Here, 'dgg' is  'Degree granting institution' used 
        // http://www.loc.gov/marc/relators/relacode.html
        $out = $this->parseRecordData('
            <marc:datafield tag="710" ind1="2" ind2=" ">
                <marc:subfield code="a">Universitetet i Oslo</marc:subfield>
                <marc:subfield code="b">Det utdanningsvitenskapelige fakultet</marc:subfield>
                <marc:subfield code="4">dgg</marc:subfield>
                <marc:subfield code="0">(NO-TrBIB)x90921833</marc:subfield>
            </marc:datafield>
        ');

        // TODO
    }

    public function testMarc776() {
        $out = $this->parseRecordData('
            <marc:datafield tag="776" ind1="0" ind2=" ">
                <marc:subfield code="w">(NO-TrBIB)022991026</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('022991026', $out->other_form['id']);
    }

    public function testPreceding() {
        $out = $this->parseRecordData('
            <marc:datafield tag="780" ind1="0" ind2="0">
                <marc:subfield code="w">(NO-TrBIB)920713874</marc:subfield>
                <marc:subfield code="g">nr 80(1961)</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('Continues', $out->preceding['relationship_type']);
        $this->assertCount(1, $out->preceding['items']);
        $this->assertEquals('920713874', $out->preceding['items'][0]['id']);
        $this->assertEquals('nr 80(1961)', $out->preceding['items'][0]['related_parts']);
    }

    public function testComplexSucceeding() {
        $out = $this->parseRecordData('
            <marc:datafield tag="580" ind1=" " ind2=" ">
                <marc:subfield code="a">
                    Slått sammen med: Comments on astrophysics : a journal of critical discussion of the current literature, 18(1995/96) og: Comments on condensed matter physics : a journal of critical discussion of the current literature, 18(1998) og: Comments on nuclear and particle physics : a journal of critical discussion of the current literature, 22(1998) og: Comments on plasma physics and controlled fusion : a journal of critical discussion of the current literature, 18(1999) til: Comments on modern physics (trykt utg.), 1(1999)
                </marc:subfield>
            </marc:datafield>
            <marc:datafield tag="785" ind1="1" ind2="7">
                <marc:subfield code="w">(NO-TrBIB)841195196</marc:subfield>
                <marc:subfield code="g">18(1995/96)</marc:subfield>
            </marc:datafield>
            <marc:datafield tag="785" ind1="1" ind2="7">
                <marc:subfield code="w">(NO-TrBIB)864105495</marc:subfield>
                <marc:subfield code="g">18(1998)</marc:subfield>
            </marc:datafield>
            <marc:datafield tag="785" ind1="1" ind2="7">
                <marc:subfield code="w">(NO-TrBIB)852150393</marc:subfield>
                <marc:subfield code="g">22(1998)</marc:subfield>
            </marc:datafield>
            <marc:datafield tag="785" ind1="1" ind2="7">
                <marc:subfield code="w">(NO-TrBIB)812012682</marc:subfield>
                <marc:subfield code="g">18(1999)</marc:subfield>
            </marc:datafield>
            <marc:datafield tag="785" ind1="1" ind2="7">
                <marc:subfield code="w">(NO-TrBIB)990840832</marc:subfield>
                <marc:subfield code="g">1(1999)</marc:subfield>
            </marc:datafield>
        ');

        $this->assertEquals('Merged with', $out->succeeding['relationship_type']);
        $this->assertEquals(
            'Slått sammen med: Comments on astrophysics : a journal of critical discussion of the current literature, 18(1995/96) og: Comments on condensed matter physics : a journal of critical discussion of the current literature, 18(1998) og: Comments on nuclear and particle physics : a journal of critical discussion of the current literature, 22(1998) og: Comments on plasma physics and controlled fusion : a journal of critical discussion of the current literature, 18(1999) til: Comments on modern physics (trykt utg.), 1(1999)',
            $out->succeeding['note']
        );
        $this->assertCount(5, $out->succeeding['items']);
        $this->assertEquals('841195196', $out->succeeding['items'][0]['id']);
        $this->assertEquals('18(1995/96)', $out->succeeding['items'][0]['related_parts']);
        $this->assertEquals('990840832', $out->succeeding['items'][4]['id']);
        $this->assertEquals('1(1999)', $out->succeeding['items'][4]['related_parts']);
    }

    public function testMarc830() {
        $out = $this->parseRecordData('
            <marc:datafield tag="830" ind1=" " ind2=" ">
                <marc:subfield code="a">Physica mathematica Universitatis Osloensis</marc:subfield>
                <marc:subfield code="v">32</marc:subfield>
                <marc:subfield code="w">(NO-TrBIB)922367817</marc:subfield>
            </marc:datafield>
            <marc:datafield tag="830" ind1=" " ind2="0">
                <marc:subfield code="a">
                Report series (Universitetet i Oslo. Fysisk institutt) (trykt utg.)
                </marc:subfield>
                <marc:subfield code="v">94-13</marc:subfield>
                <marc:subfield code="w">(NO-TrBIB)812037006</marc:subfield>
            </marc:datafield>
        ');

        $this->assertCount(2, $out->series);
        $this->assertEquals('Physica mathematica Universitatis Osloensis', $out->series[0]['title']);
        $this->assertEquals('32', $out->series[0]['volume']);
        $this->assertEquals('922367817', $out->series[0]['id']);

        $this->assertEquals('Report series (Universitetet i Oslo. Fysisk institutt) (trykt utg.)', $out->series[1]['title']);
        $this->assertEquals('94-13', $out->series[1]['volume']);
        $this->assertEquals('812037006', $out->series[1]['id']);
    }

    public function testMarc956() {
        $out = $this->parseRecordData('
            <marc:datafield tag="956" ind1="4" ind2="2">
                <marc:subfield code="3">Omslagsbilde</marc:subfield>
                <marc:subfield code="u">http://innhold.bibsys.no/bilde/forside/?size=mini&amp;id=LITE_150154636.jpg</marc:subfield>
                <marc:subfield code="q">image/jpeg</marc:subfield>
            </marc:datafield>
        ');

        // TODO
    }

    public function testMarc991() {
        $out1 = $this->parseRecordData('');

        $out2 = $this->parseRecordData('
            <marc:datafield tag="991" ind1=" " ind2=" ">
                <marc:subfield code="a">parts</marc:subfield>
            </marc:datafield>
        ');

        $out3 = $this->parseRecordData('
            <marc:datafield tag="991" ind1=" " ind2=" ">
                <marc:subfield code="a">volumes</marc:subfield>
            </marc:datafield>
        ');

        $this->assertFalse($out1->is_series);
        $this->assertFalse($out1->is_multivolume);

        $this->assertTrue($out2->is_series);
        $this->assertFalse($out2->is_multivolume);

        $this->assertFalse($out3->is_series);
        $this->assertTrue($out3->is_multivolume);

    }

    public function testJson()
    {
        $rec1 = $this->parseRecordData('
            <marc:controlfield tag="001">12149361x</marc:controlfield>
        ');

        $expected = json_encode(
          array(
            'id' => '12149361x',
            'is_series' => false,
            'is_multivolume' => false,
          )
        );

        $this->assertJsonStringEqualsJsonString($expected, $rec1->toJson());
    }

}
