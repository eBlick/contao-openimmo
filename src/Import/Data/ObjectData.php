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
    /**
     * @param array<string, int|string|null> $objectProperties
     * @param array<string, int|string|null> $agentData
     * @param array<string, ResourceType>    $resourceData
     */
    public function __construct(
        private readonly string $anbieterNr,
        private readonly string $objectId,
        private readonly array $objectProperties,
        private readonly array $agentData,
        private readonly array $resourceData,
    ) {
    }

    public function getAnbieterNr(): string
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

    public function getResourceFiles(): array
    {
        return array_keys($this->resourceData);
    }

    public function getTitleImage(): string|null
    {
        return $this->getResourcesOfType(ResourceType::titleImage)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getGalleryImages(): array
    {
        return $this->getResourcesOfType(ResourceType::galleryImage);
    }

    /**
     * @return list<string>
     */
    public function getDocuments(): array
    {
        return $this->getResourcesOfType(ResourceType::document);
    }

    /**
     * @return list<string>
     */
    public function getOtherAttachments(): array
    {
        return $this->getResourcesOfType(ResourceType::other);
    }

    private function getResourcesOfType(ResourceType $type): array
    {
        return array_keys(
            array_filter(
                $this->resourceData,
                static fn (ResourceType $t): bool => $t === $type,
            )
        );
    }
}
