<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import\Data;

use Contao\StringUtil;

class ObjectData
{
    public const IMAGE_TYPE_TITLE = true;
    public const IMAGE_TYPE_GALLERY = false;

    /**
     * @param array<string, int|string|null> $objectProperties
     * @param array<string, int|string|null> $agentData
     * @param array<string, bool>            $imageData
     */
    public function __construct(
        private string $anbieterNr,
        private string $objectId,
        private array $objectProperties,
        private array $agentData,
        private array $imageData,
    ) {
    }

    public function getAnbieterNr()
    {
        return $this->anbieterNr;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getObjectAlias(): string
    {
        return StringUtil::generateAlias($this->objectProperties['objekttitel'] ?? $this->objectId);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    public function getAgentData(): array
    {
        return $this->agentData;
    }

    public function getImageFiles(): array
    {
        return array_keys($this->imageData);
    }

    public function getMainImage(): string|null
    {
        $candidates = array_filter(
            $this->imageData,
            static fn (bool $type): bool => self::IMAGE_TYPE_TITLE === $type,
        );

        return array_key_first($candidates);
    }

    public function getGalleryImages(): array
    {
        return array_keys(
            array_filter(
                $this->imageData,
                static fn (bool $type): bool => self::IMAGE_TYPE_GALLERY === $type,
            )
        );
    }
}
