<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Tests\Import\Data;

use EBlick\ContaoOpenImmoImport\Import\Data\Normalizer;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\SerializerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Ujamii\OpenImmo\API\Immobilie;
use Ujamii\OpenImmo\API\Openimmo;
use Ujamii\OpenImmo\Handler\DateTimeHandler;

class NormalizerTest extends TestCase
{
    /**
     * @dataProvider provideXmlFixtures
     */
    public function testColumnsMatch(string $file): void
    {
        $normalizer = new Normalizer();

        $objectFieldTypes = $this->getFieldTypes('cc_fiba_objekte');
        $unmapped = $objectFieldTypes;

        foreach ($this->readFile($file) as $immobilie) {
            $objectData = $normalizer->normalize('foo-123', $immobilie);

            foreach ($objectData->getObjectProperties() as $column => $value) {
                self::assertArrayHasKey($column, $objectFieldTypes, "Column '$column' must exist.");

                $typeInfo = $objectFieldTypes[$column];

                if (null === $value) {
                    self::assertTrue($typeInfo['nullable'], "Column '$column' must be nullable.");
                } else {
                    self::assertSame($type = $typeInfo['type'], \gettype($value), "Column '$column' must be of type '$type'.");
                }

                unset($unmapped[$column]);
            }
        }

        $unmappedWithoutExcluded = array_diff(
            array_keys($unmapped),
            [
                // Basic metadata
                'id', 'pid', 'tstamp', 'date_create', 'sorting', 'ptable', 'alias',
                'property_number', 'betreuer', 'inactive',
                // Resources
                'image', 'gallery', 'ordersrc_gallery', 'gallery_fullsize',
                'expose', 'ordersrc_expose',
                'dokuments', 'ordersrc_dokuments',
                // Static defaults
                'published', 'top_object', 'notelist', 'quelle', 'protection_usergroup',
                'inactive',
            ]
        );

        self::assertSame([], $unmappedWithoutExcluded, 'No unmapped entries should exist.');
    }

    /**
     * @dataProvider provideSamples
     */
    public function testParsesData(string $fileName, string $expectedObjectId, array $expectedObjectProperties, array $expectedAgentData, string $expectedImageFile, array $expectedGalleryFiles): void
    {
        $file = Path::join(__DIR__.'/../../Fixtures', $fileName);

        $immobilie = iterator_to_array($this->readFile($file))[0];

        $result = (new Normalizer())->normalize('foo-123', $immobilie);

        self::assertSame($expectedObjectId, $result->getObjectId());
        self::assertSameKeyValuePairs($expectedObjectProperties, $result->getObjectProperties());
        self::assertSameKeyValuePairs($expectedAgentData, $result->getAgentData());
        self::assertSame($expectedImageFile, $result->getTitleImage());
        self::assertSameKeyValuePairs($expectedGalleryFiles, $result->getGalleryImages());
    }

    public function provideXmlFixtures(): \Generator
    {
        $xmlFixtures = (new Finder())
            ->in(__DIR__.'/../../Fixtures')
            ->name('*.xml')
        ;

        foreach ($xmlFixtures as $fileInfo) {
            yield $fileInfo->getFilenameWithoutExtension() => [
                Path::canonicalize($fileInfo->getPathname()),
            ];
        }
    }

    public function provideSamples(): \Generator
    {
        yield 'demo_data1' => [
            'demo_data1.xml',

            // Object ID
            '0099_10_AB123',

            // Object properties
            [
                'abdatum' => '',
                'adresse' => '50.12345,6.12345',
                'adresse_city' => 'Aachen / Brand',
                'adresse_country' => 'DEU',
                'adresse_street' => 'Phantasiestr. 101',
                'adresse_zipcode' => '52078',
                'anzahl_badezimmer' => '2',
                'anzahl_balkone' => '',
                'anzahl_etagen' => '2',
                'anzahl_garagen' => '',
                'anzahl_schlafzimmer' => '3',
                'anzahl_stellplaetze' => '1',
                'anzahl_terrassen' => '',
                'anzahl_zimmer' => '6',
                'ausricht_balkon_terrasse' => null,
                'aussenprovision' => '3,57 % inkl. MwSt.',
                'ausstatt_beschr' => 'Das Objekt bietet eine variable Raumaufteilung mit vielen Nutzungsmöglichkeiten.',
                'ausstatt_kategorie' => '',
                'badewanne' => '',
                'baujahr' => 2002,
                'betriebskostennetto' => '',
                'bisdatum' => '',
                'boden' => null,
                'bueroflaeche' => '',
                'bundesland' => '',
                'distanzen_autobahn' => '2.00',
                'distanzen_bus' => '',
                'distanzen_einkaufsmöglichkeiten' => '',
                'distanzen_fernbahnhof' => '',
                'distanzen_flughafen' => '',
                'distanzen_grundschule' => '2.80',
                'distanzen_gymnasium' => '4.00',
                'distanzen_kindergarten' => '2.20',
                'distanzen_realschule' => '3.30',
                'distanzen_ubahn' => '',
                'distanzen_zentrum' => '1.50',
                'dreizeiler' => '',
                'dusche' => '',
                'endenergiebedarf' => '',
                'energieausweis' => '1',
                'energieausweis_typ' => 'VERBRAUCH',
                'energieeffizienzklasse' => '',
                'energieverbrauchkennwert' => '158.00',
                'etage' => '',
                'fahrstuhl' => null,
                'gartenflaeche' => '',
                'gartennutzung' => '',
                'gastroflaeche' => '',
                'gesamtflaeche' => '',
                'gueltig_bis' => '05.04.2022',
                'heizkosten' => '',
                'heizkosten_enthalten' => '',
                'heizungsart' => 'a:1:{i:0;s:9:"Fussboden";}',
                'innenprovision' => '',
                'kamin' => '',
                'kaufpreis' => '299000.50',
                'klimatisiert' => '',
                'kueche' => null,
                'ladenflaeche' => '',
                'lage' => 'Top Lage!',
                'lagerflaeche' => '',
                'mitwarmwasser' => '',
                'moebliert' => '',
                'nebenkosten' => '',
                'nutzflaeche' => '',
                'nutzungsart' => 'a:1:{i:0;s:6:"Wohnen";}',
                'objausstattung__unterkellert' => '1',
                'objektadresse_freigeben' => '1',
                'objektart' => 'Haus',
                'objektbeschreibung' => 'Das Kaufobjekt besticht durch seine überzeugende, solide Massivbauweise.',
                'objekttitel' => 'Schöne Immobilie in Aachen',
                'objekttyp' => 'Einfamilienhaus',
                'primaerenergietraeger' => '',
                'provisionspflichtig' => '',
                'raeume_veraenderbar' => '',
                'rollstuhlgerecht' => '',
                'sauna' => '',
                'sonstige_angaben' => 'Vereinbaren Sie noch heute Ihren persönlichen Besichtigungstermin!',
                'stellplatzart' => null,
                'stp_carport' => '',
                'stp_carport_preis' => '0.00',
                'stp_freiplatz' => '',
                'stp_freiplatz_preis' => '0.00',
                'stp_garage' => '',
                'stp_garage_preis' => '0.00',
                'stromwert' => '',
                'user' => 'zentrale@e-blick.de',
                'verfuegbar_ab' => 'ab sofort',
                'verkaufsflaeche' => '',
                'vermietbare_flaeche' => '',
                'waehrung' => 'EUR',
                'waermewert' => '',
                'wg_geeignet' => '',
                'wintergarten' => '',
                'wohnflaeche' => '160',
                'zustand' => 'GEPFLEGT',
            ],

            // Agent data
            [
                'anrede' => 'Firma',
                'firstname' => '',
                'lastname' => '',
                'email_direkt' => 'direkt@demoimmo.myonoffice.de',
                'tel_durchwahl' => '+49 987 654321',
                'external_id' => '117',
            ],

            // Image data
            'Titel.jpg',
            [
                'Essbereich.jpg',
                'Zimmer1.jpg',
                'Zimmer2.jpg',
                'Bad.jpg',
            ],
        ];
    }

    /**
     * Very naive parser to read from a file containing a normalized
     * "CREATE TABLE `…` (…)" statement.
     *
     * @return array<string, array{type: string, notnull: boolean}>
     */
    private function getFieldTypes(string $table): array
    {
        $extractTypeInfo = static function (string $definition): string {
            $patterns = [
                '/int(?: unsigned|\(\d+\))/' => 'integer',
                '/varchar\(\d+\)/' => 'string',
                '/blob/' => 'string',
                '/binary/' => 'string',
                '/char(?:\(\d+\))?/' => 'string',
                '/(tiny|medium|long|)text/' => 'string',
                '/decimal\(\d+, ?\d+\)/' => 'string',
            ];

            foreach ($patterns as $pattern => $type) {
                if (1 === preg_match($pattern, $definition)) {
                    return $type;
                }
            }

            throw new \RuntimeException(sprintf('Could not parse DDL type from definition "%s".', $definition));
        };

        $lines = explode(
            "\n",
            strtolower(
                file_get_contents(Path::canonicalize(__DIR__."/../../Fixtures/schema_$table.sql"))
            )
        );

        $fields = [];

        foreach ($lines as $line) {
            $line = trim($line, ' ,');

            if (
                !$line
                || str_contains($line, 'create table')
                || str_contains($line, 'key')
                || str_starts_with($line, '(')
                || str_starts_with($line, ')')
            ) {
                continue;
            }

            if (1 !== preg_match('/^\s*`?([\wäöü]+)`?\s*(.*)$/ui', $line, $matches)) {
                throw new \RuntimeException(sprintf('Could not parse DDL data from line "%s".', $line));
            }

            $fields[$matches[1]] = [
                'type' => $extractTypeInfo($matches[2]),
                'nullable' => !str_contains($matches[2], 'not null'),
            ];
        }

        return $fields;
    }

    /**
     * @return \Generator<Immobilie>
     */
    private function readFile(string $file): \Generator
    {
        $serializer = SerializerBuilder::create()
            ->configureHandlers(
                static function (HandlerRegistryInterface $registry): void {
                    $registry->registerSubscribingHandler(new DateTimeHandler());
                }
            )
            ->build()
        ;

        /** @var Openimmo::class $data */
        $data = $serializer->deserialize(file_get_contents($file), Openimmo::class, 'xml');

        foreach ($data->getAnbieter() as $anbieter) {
            foreach ($anbieter->getImmobilie() as $immobilie) {
                yield $immobilie;
            }
        }
    }

    private static function assertSameKeyValuePairs(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }
}
