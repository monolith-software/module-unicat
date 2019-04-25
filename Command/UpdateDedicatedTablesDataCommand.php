<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat\Command;

use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Model\AbstractValueModel;
use Monolith\Module\Unicat\Model\ItemModel;
use Smart\CoreBundle\Utils\OutputWritelnTrait;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateDedicatedTablesDataCommand extends ContainerAwareCommand
{
    use OutputWritelnTrait;

    protected function configure()
    {
        $this
            ->setName('smart:unicat:update-dedicated-tables-data')
            //->setDescription('Temporary commant tool.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;  // для OutputWritelnTrait
        $this->output = $output; // для OutputWritelnTrait
        $this->startTime = microtime(true);

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $count = 0;

        foreach ($em->getRepository(UnicatConfiguration::class)->findAll() as $unicatConfiguration) {
            $ucm = $this->getContainer()->get('unicat')->getConfigurationManager($unicatConfiguration->getId());

            $this->outputWriteln($unicatConfiguration->getName());

            $attributes = [];
            /** @var UnicatAttribute $attribute */
            foreach ($em->getRepository(UnicatAttribute::class)->findAll() as $attribute) {
                if ($attribute->isDedicatedTable()
                    and $attribute->getConfiguration() == $unicatConfiguration
                    and $attribute->isEnabled()
                ) {
                    $attributes[$attribute->getName()] = $attribute;
                }
            }

            /** @var ItemModel $item */
            foreach ($ucm->getItemRepository()->findAll() as $item) {
                /** @var UnicatAttribute $attr */
                foreach ($attributes as $attr) {
                    $method = "getAttr{$attr->getName()}Value";

                    $attrValue = $item->$method();

                    $serializedValue = $item->getAttr($attr->getName());

                    if ($attrValue instanceof AbstractValueModel) {
                        $dedicatedValue = $attrValue->getValue();

                        if (is_double($dedicatedValue)) {
                            $serializedValue = (float) $serializedValue;
                        }

                        if (intval($serializedValue)) {
                            $serializedValue = intval($serializedValue);
                            $dedicatedValue = intval($dedicatedValue);
                        }

                        if (is_bool($serializedValue)) {
                            $dedicatedValue = (bool) $dedicatedValue;
                        }

                    } else {
                        $dedicatedValue = null;
                    }

                    // Найдено несовпадение значений.
                    if ($dedicatedValue !== $serializedValue) {
                        $count++;

                        $msg = "$count) item: <info>{$item->getId()}</info>, attr: <comment>{$attr->getName()}</comment>, serialized value: <comment>$serializedValue</comment>, dedicated value: <comment>$dedicatedValue</comment>";

                        if ($serializedValue === null) {
                            $em->remove($attrValue);

                            $msg .= ' -> <error>Removed</error>';
                        } elseif ($dedicatedValue === null) {
                            $entityValueClass = $attr->getValueClassNameWithNameSpace();

                            /* @var AbstractValueModel $attrValue */
                            $attrValue = new $entityValueClass();
                            $attrValue->setItem($item);
                            $attrValue->setValue($serializedValue);

                            $em->persist($attrValue);

                            $msg .= ' -> <info>Updated</info>';
                        } else {
                            $attrValue->setValue($serializedValue);

                            $msg .= ' -> <info>Updated</info>';
                        }

                        $em->flush($attrValue);

                        $this->outputWriteln($msg);
                    }
                }

            }
        }

        $this->outputWriteln('Processed '.$count.' attributes.');
        $this->writeProfileInfo();
    }
}
