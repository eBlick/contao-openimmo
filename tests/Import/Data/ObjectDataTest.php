<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Tests\Import\Data;

use EBlick\ContaoOpenImmoImport\Import\Data\ObjectData;
use EBlick\ContaoOpenImmoImport\Import\Data\ResourceType;
use PHPUnit\Framework\TestCase;

class ObjectDataTest extends TestCase
{
    public function testCreateAndReadObjectData(): void
    {
        $data = new ObjectData(
            'anbieter-nr',
            'object-id',
            [
                'foo' => 'bar',
                'bar' => 42,
                'baz' => null,
            ],
            [
                'agent' => 'data',
            ],
            [
                'image1' => ResourceType::galleryImage,
                'image2' => ResourceType::galleryImage,
                'image3' => ResourceType::titleImage,
                'image4' => ResourceType::galleryImage,
                'expose' => ResourceType::document,
                'other' => ResourceType::other,
            ]
        );

        self::assertSame('anbieter-nr', $data->getAnbieterNr());
        self::assertSame('object-id', $data->getObjectId());

        self::assertSame(
            [
                'foo' => 'bar',
                'bar' => 42,
                'baz' => null,
            ],
            $data->getObjectProperties()
        );

        self::assertSame(
            [
                'agent' => 'data',
            ],
            $data->getAgentData()
        );

        self::assertSame(['image1', 'image2', 'image3', 'image4', 'expose', 'other'], $data->getResourceFiles());
        self::assertSame('image3', $data->getTitleImage());
        self::assertSame(['image1', 'image2', 'image4'], $data->getGalleryImages());
        self::assertSame(['expose'], $data->getDocuments());
        self::assertSame(['other'], $data->getOtherAttachments());
    }
}
