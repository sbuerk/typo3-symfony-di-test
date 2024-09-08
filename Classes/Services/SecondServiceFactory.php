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

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;

#[AsDecorator(decorates: ServiceFactoryInterface::class, priority: 0, onInvalid: SymfonyContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class SecondServiceFactory implements ServiceFactoryInterface
{
    public function __construct(
        #[Autowire(service: SecondService::class, lazy: true)]
        private ServiceInterface $secondService,
        #[AutowireDecorated]
        private ServiceFactoryInterface $serviceFactory,
    ) {}

    public function create(string $someData): ServiceInterface
    {
        return match ($someData) {
            'second' => $this->secondService,
            default => $this->serviceFactory->create($someData),
        };
    }
}
