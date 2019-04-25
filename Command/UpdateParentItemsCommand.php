<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat\Command;

use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Model\ItemModel;
use Smart\CoreBundle\Utils\OutputWritelnTrait;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateParentItemsCommand extends ContainerAwareCommand
{
    use OutputWritelnTrait;

    protected function configure()
    {
        $this
            ->setName('smart:unicat:update-parent-items')
            ->setDescription('Temporary commant tool.')
            ->setDefinition([
                new InputArgument('configuration_id', InputArgument::REQUIRED, 'Configuration ID'),
            ])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;  // для OutputWritelnTrait
        $this->output = $output; // для OutputWritelnTrait
        $this->startTime = microtime(true);

        $ucm = $this->getContainer()->get('unicat')->getConfigurationManager($input->getArgument('configuration_id'));

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $itemEntityClass = $ucm->getConfiguration()->getItemClass();

        $items = $em->getRepository($itemEntityClass)->findAll();

        /** @var ItemModel $item */
        foreach ($items as $item) {
            $groups = [];
            foreach ($item->getType()->getAttributesGroups() as $group) {
                $groups[] = $group->getName();
            }

            // Получение всех атрибутов типа записи.
            $attributes = $em->getRepository(UnicatAttribute::class)->findByGroupsNames($ucm->getConfiguration(), $groups);

            $item->setFirstParentAttributes([]);

            foreach ($attributes as $attribute) {
                if ($attribute->isType('unicat_item')) {
                    // Рекурсивное прописывание всех родителей.
                    if (!$attribute->isItemsTypeMany2many()) {
                        $item->addFirstParentAttribute($attribute->getName());
                        $this->updateParentItems($item, $item);
                    }
                }
            }

            $em->persist($item);
        }

        $em->flush();

        $this->outputWriteln('Processed '.count($items).' items.');
        $this->writeProfileInfo();
    }

    /**
     * Рекурсивное обновление вложенных родительских связей.
     *
     * @param ItemModel $baseItem
     * @param ItemModel $item
     */
    protected function updateParentItems(ItemModel $baseItem, ItemModel $item)
    {
        foreach ($item->getParentItems() as $attr => $parentItem) {
            $baseItem->setAttribute(str_replace('attr_', '', $attr), $parentItem);
            $this->updateParentItems($baseItem, $parentItem);
        }
    }
}
