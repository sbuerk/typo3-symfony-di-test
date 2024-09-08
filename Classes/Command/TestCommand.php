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

namespace SBUERK\DiTests\Command;

use SBUERK\DiTests\Services\ServiceFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'di:test')]
final class TestCommand extends Command
{
    public function __construct(
        private ServiceFactoryInterface $serviceFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');

        $output->writeln(sprintf('ServiceFactoryInterface implemetnation: %s', $this->serviceFactory::class));
        $output->writeln('');

        $someFactory = $this->serviceFactory->create('some');
        $output->writeln(sprintf('Service class for context "some": %s', $someFactory::class));
        $output->writeln('Service->ping() result: ' . $someFactory->ping());
        $output->writeln('');

        $secondService = $this->serviceFactory->create('second');
        $output->writeln(sprintf('Service class for context "second": %s', $secondService::class));
        $output->writeln('Service->ping() result: ' . $secondService->ping());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
