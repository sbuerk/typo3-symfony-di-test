<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace SBUERK\DiTests\Services;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsAlias(id: ServiceInterface::class, public: true)]
#[Autoconfigure(public: true)]
final readonly class DefaultService implements ServiceInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function ping(): string
    {
        return __CLASS__;
    }
}
