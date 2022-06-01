<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Tests\Import\Data;

use EBlick\ContaoOpenImmoImport\Import\Data\ObjectData;
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
                'image1' => ObjectData::IMAGE_TYPE_GALLERY,
                'image2' => ObjectData::IMAGE_TYPE_GALLERY,
                'image3' => ObjectData::IMAGE_TYPE_TITLE,
                'image4' => ObjectData::IMAGE_TYPE_GALLERY,
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

        self::assertSame(['image1', 'image2', 'image3', 'image4'], $data->getImageFiles());
        self::assertSame('image3', $data->getMainImage());
        self::assertSame(['image1', 'image2', 'image4'], $data->getGalleryImages());
    }
}
