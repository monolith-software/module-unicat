<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat\Command;

use Smart\CoreBundle\Utils\OutputWritelnTrait;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateEntitiesCommand extends ContainerAwareCommand
{
    use OutputWritelnTrait;

    protected function configure()
    {
        $this
            ->setName('smart:unicat:generate-entities')
            ->setDescription('Generate entities.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;  // для OutputWritelnTrait
        $this->output = $output; // для OutputWritelnTrait

        $this->getContainer()->get('unicat')->generateEntities();
    }
}
