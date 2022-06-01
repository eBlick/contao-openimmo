<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Tests\Import\Data;

use EBlick\ContaoOpenImmoImport\Import\Data\OpenImmoArchive;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class OpenImmoArchiveTest extends TestCase
{
    public function testThrowsIfArchiveDoesNotContainAnXMLFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Archive ".*" does not contain a \.xml file\./');

        new OpenImmoArchive(Path::canonicalize(__DIR__.'/../../Fixtures/file_with_resource.zip'));
    }

    public function testReadsDataFromArchive(): void
    {
        $archive = new OpenImmoArchive(Path::canonicalize(__DIR__.'/../../Fixtures/file_with_xml_and_resource.zip'));

        self::assertSame(['bar.jpg'], $archive->getResourceFiles());
        self::assertSame('eBlick Medienberatung', $archive->getOpenImmoData()->getAnbieter()[0]->getFirma());
    }
}
