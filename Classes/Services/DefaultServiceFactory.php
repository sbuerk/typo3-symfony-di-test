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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(id: ServiceFactoryInterface::class)]
final readonly class DefaultServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        #[Autowire(service: DefaultService::class, lazy: true)]
        private ServiceInterface $defaultService,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return $this->defaultService;
    }
}
