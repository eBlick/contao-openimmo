<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import\Data;

use Ujamii\OpenImmo\API\Anhang;

/**
 * @see Anhang for resource classification available by the API
 */
enum ResourceType
{
    case titleImage;
    case galleryImage;
    case document; // used for documents, such as a pdf expose
    case other;
}
