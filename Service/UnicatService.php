<?php

namespace Monolith\Module\Unicat\Service;

use Doctrine\Common\Persistence\ManagerRegistry;
use Monolith\Module\Unicat\Doctrine\UnicatEntityManager;
use SmartCore\Bundle\MediaBundle\Service\MediaCloudService;
use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Generator\DoctrineEntityGenerator;
use Monolith\Module\Unicat\Generator\DoctrineValueEntityGenerator;
use Monolith\Module\Unicat\Model\AbstractValueModel;
use Monolith\Module\Unicat\Model\ItemModel;
use Monolith\Module\Unicat\Model\TaxonModel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UnicatService
{
    use ContainerAwareTrait;

    /** @var \Doctrine\Common\Persistence\ManagerRegistry */
    protected $doctrine;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var UnicatEntityManager */
    protected $uem;

    /** @var  EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var \Symfony\Component\Form\FormFactoryInterface */
    protected $formFactory;

    /** @var \SmartCore\Bundle\MediaBundle\Service\CollectionService */
    protected $mc;

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    protected $securityToken;

    /** @var UnicatConfigurationManager[] */
    protected $ucm;

    /** @var UnicatConfiguration|null */
    protected $currentConfiguration;

    /** @var UnicatConfiguration|null */
    protected static $currentConfigurationStatic;

    /**
     * @param ManagerRegistry $doctrine
     * @param FormFactoryInterface $formFactory
     * @param MediaCloudService $mediaCloud
     * @param TokenStorageInterface $securityToken
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        ManagerRegistry $doctrine,
        UnicatEntityManager $unicatEntityManager,
        FormFactoryInterface $formFactory,
        MediaCloudService $mediaCloud,
        TokenStorageInterface $securityToken,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->currentConfiguration = null;
        $this->doctrine    = $doctrine;
        $this->em          = $doctrine->getManager();
        $this->uem         = $unicatEntityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->formFactory = $formFactory;
        $this->mc          = $mediaCloud;
        $this->securityToken = $securityToken;
    }

    /**
     * @param $object
     * @param bool $isFlush
     */
    protected function persist($object, $isFlush = false)
    {
        $this->em->persist($object);

        if ($isFlush) {
            $this->em->flush($object);
        }
    }

    /**
     * @param $object
     * @param bool $isFlush
     */
    protected function remove($object, $isFlush = false)
    {
        $this->em->remove($object);

        if ($isFlush) {
            $this->em->flush($object);
        }
    }

    /**
     * @param UnicatConfiguration $currentConfiguration
     *
     * @return $this
     */
    public function setCurrentConfiguration(UnicatConfiguration $currentConfiguration)
    {
        $this->currentConfiguration = $currentConfiguration;

        self::setCurrentConfigurationStatic($currentConfiguration);

        return $this;
    }

    /**
     * @return UnicatConfiguration|null
     */
    public function getCurrentConfiguration()
    {
        return $this->currentConfiguration;
    }

    /**
     * @return null|UnicatConfiguration
     */
    public static function getCurrentConfigurationStatic()
    {
        return self::$currentConfigurationStatic;
    }

    /**
     * @param null|UnicatConfiguration $currentConfigurationStatic
     *
     * @return $this
     */
    public static function setCurrentConfigurationStatic(UnicatConfiguration $currentConfigurationStatic)
    {
        self::$currentConfigurationStatic = $currentConfigurationStatic;
    }

    /**
     * @return UnicatConfigurationManager|null
     */
    public function getCurrentConfigurationManager()
    {
        return $this->currentConfiguration ? $this->getConfigurationManager($this->currentConfiguration->getId()) : null;
    }

    /**
     * @param string|int $configuration_id
     *
     * @return UnicatConfigurationManager|null
     */
    public function getConfigurationManager($configuration_id)
    {
        if (empty($configuration_id)) {
            return null;
        }

        $this->checkEntities();

        $configuration = $this->getConfiguration($configuration_id);

        if (empty($configuration)) {
            throw new \Exception('Конфигурации "'.$configuration_id.'" не существует');
        }

        $this->setCurrentConfiguration($configuration);

        if (!isset($this->ucm[$configuration->getId()])) {
            if ($configuration->getMediaCollection() instanceof \SmartCore\Bundle\MediaBundle\Entity\Collection) {
                $mc = $this->mc->getCollection($configuration->getMediaCollection()->getId());
            } else {
                $mc = $this->mc->getCollection(1); // @todo хак
            }

            $this->ucm[$configuration->getId()] = new UnicatConfigurationManager($this->doctrine, $this->uem, $this->formFactory, $configuration, $mc, $this->securityToken, $this->eventDispatcher);
        }

        return $this->ucm[$configuration->getId()];
    }

    /**
     * @param UnicatConfiguration|int $configuration
     *
     * @return UnicatAttribute[]
     *
     * @deprecated
     */
    public function getAttributes($configuration)
    {
        if ($configuration instanceof UnicatConfiguration) {
            $configuration = $configuration->getId();
        }

        return $this->getConfigurationManager($configuration)->getAttributes();
    }

    /**
     * @param UnicatConfiguration $configuration
     * @param int $id
     *
     * @return ItemModel|null
     *
     * @deprecated
     */
    public function getItem(UnicatConfiguration $configuration, $id)
    {
        return $this->em->getRepository($configuration->getItemClass())->find($id);
    }

    /**
     * @param UnicatConfiguration $configuration
     * @param array|null $orderBy
     *
     * @return ItemModel|null
     *
     * @deprecated
     */
    public function findAllItems(UnicatConfiguration $configuration, $orderBy = null)
    {
        return $this->em->getRepository($configuration->getItemClass())->findBy([], $orderBy);
    }

    /**
     * @param int|string $val
     *
     * @return UnicatConfiguration
     */
    public function getConfiguration($val)
    {
        $key = intval($val) ? 'id' : 'name';

        return $this->em->getRepository(UnicatConfiguration::class)->findOneBy([$key => $val]);
    }

    /**
     * @return UnicatConfiguration[]
     */
    public function allConfigurations()
    {
        return $this->em->getRepository(UnicatConfiguration::class)->findAll();
    }

    /**
     * @param int $id
     *
     * @return UnicatTaxonomy
     */
    public function getTaxonomy($id)
    {
        return $this->getTaxonomyRepository()->find($id);
    }

    /**
     * @param int $id
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getTaxonomyRepository()
    {
        return $this->em->getRepository(UnicatTaxonomy::class);
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     *
     * @todo события
     */
    public function createTaxon(TaxonModel $taxon)
    {
        $this->persist($taxon, true);

        return $this;
    }

    /**
     * Проверка на существование всех сущностей.
     */
    public function checkEntities()
    {
        $isMapped = true;

        foreach ($this->allConfigurations() as $configuration) {
            $entities = [
                'Taxon',
                'Item',
            ];

            foreach ($configuration->getAttributes() as $attribute) {
                if ($attribute->getIsDedicatedTable()) {

                    $entities[] = 'Value'.$attribute->getNameCamelCase();
                }
            }

            foreach ($entities as $entity) {
                $className = $configuration->getEntitiesNamespace().$entity;

                if (!class_exists($className)) {
                    $isMapped = false;

                    break 2;
                }
            }
        }

        if (!$isMapped) {
            $this->generateEntities();
        }
    }
    
    /**
     * Обновление сущностей.
     */
    public function generateEntities()
    {
        $filesystem = new Filesystem();
        /** @var \AppKernel $kernel */
        $entitiesDir = $this->container->get('kernel')->getBundle('SiteBundle')->getPath().'/Entity/Unicat';

        if (!is_dir($entitiesDir)) {
            $filesystem->mkdir($entitiesDir);
        }

        $user = is_object($token = $this->container->get('security.token_storage')->getToken()) ? $token->getUser() : null;

        foreach ($this->allConfigurations() as $configuration) {
            $generator = new DoctrineEntityGenerator();
            $generator->setSkeletonDirs($this->container->get('kernel')->getBundle('UnicatModuleBundle')->getPath().'/Resources/skeleton');
            $siteBundle = $this->container->get('kernel')->getBundle('SiteBundle');
            $targetDir  = $siteBundle->getPath().'/Entity/Unicat/'.ucfirst($configuration->getName());

            if (!is_dir($targetDir) and !@mkdir($targetDir, 0777, true)) {
                throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist and could not be created.', $targetDir));
            }

            $reflector = new \ReflectionClass($siteBundle);
            $namespace = $reflector->getNamespaceName().'\Entity\\Unicat\\'.ucfirst($configuration->getName());
            $configuration->setEntitiesNamespace($namespace.'\\');

            $generator->generate($targetDir, $namespace, $configuration);

            if (empty($configuration->getUser()) and !empty($user)) {
                $configuration->setUser($user);
            }

            $this->em->persist($configuration);
            $this->em->flush($configuration);
        }

        $application = new Application($this->container->get('kernel'));
        $application->setAutoExit(false);
        $applicationInput = new ArrayInput([
            'command' => 'doctrine:schema:update',
            '--force' => true,
            '--env'   => 'prod',
            '--no-debug' => true,
        ]);
        $applicationOutput = new BufferedOutput();
        $retval = $application->run($applicationInput, $applicationOutput);
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function createAttribute(UnicatAttribute $entity)
    {
        /*
        if ($entity->getIsDedicatedTable()) {
            $reflector = new \ReflectionClass($entity);
            //$targetDir = dirname($reflector->getFileName());
            $siteBundle = $this->container->get('kernel')->getBundle('SiteBundle');
            $targetDir  = $siteBundle->getPath().'/Entity/'.ucfirst($configuration->getName());

            $generator = new DoctrineValueEntityGenerator();
            $generator->setSkeletonDirs($this->container->get('kernel')->getBundle('UnicatModuleBundle')->getPath().'/Resources/skeleton');

            $generator->generate(
                $targetDir,
                $this->getCurrentConfiguration()->getName(),
                $entity->getType(),
                $entity->getValueClassName(),
                $reflector->getNamespaceName(),
                $entity->getName()
            );

            $application = new Application($this->container->get('kernel'));
            $application->setAutoExit(false);
            $applicationInput = new ArrayInput([
                'command' => 'doctrine:schema:update',
                '--force' => true,
            ]);
            $applicationOutput = new BufferedOutput();
            $retval = $application->run($applicationInput, $applicationOutput);

            $valueClass = $reflector->getNamespaceName().'\\'.$entity->getValueClassName();
        }
        */

        // Обновление для всех записей значением по умолчанию.
        $defaultValue = $entity->getUpdateAllRecordsWithDefaultValue();
        if (!empty($defaultValue) or $defaultValue === 0) {
            /** @var ItemModel $item */
            foreach ($this->findAllItems($entity->getConfiguration()) as $item) {
                // @todo поддержку других типов.
                switch ($entity->getType()) {
                    case 'checkbox':
                        $defaultValue = (bool) $defaultValue;
                        break;
                    default:
                        break;
                }

                $item->setAttribute($entity->getName(), $defaultValue);

                if ($entity->getIsDedicatedTable()) {
                    /** @var AbstractValueModel $value */
                    /* @todo
                    $value = new $valueClass();
                    $value
                        ->setItem($item)
                        ->setValue($defaultValue)
                    ;

                    $this->em->persist($value);
                    */
                }
            }
        }

        $entity->setConfiguration($this->getCurrentConfiguration());

        $this->em->persist($entity);
        $this->em->flush();

        $this->generateEntities();

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function updateTaxon(TaxonModel $taxon)
    {
        $properties = $taxon->getProperties();

        foreach ($properties as $propertyName => $propertyValue) {
            if ($propertyValue instanceof UploadedFile) {
                $fileId = $taxon->getTaxonomy()->getConfiguration()->getMediaCollection()->upload($propertyValue);
                $taxon->setProperty($propertyName, $fileId);
            }
        }

        $this->persist($taxon, true);

        return $this;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function updateAttribute(UnicatAttribute $entity)
    {
        $this->persist($entity, true);

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function deleteTaxon(TaxonModel $taxon)
    {
        throw new \Exception('@todo решить что сделать с вложенными Taxons, а также с сопряженными записями');

        $this->remove($taxon, true);

        return $this;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function deleteAttribute(UnicatAttribute $entity)
    {
        throw new \Exception('@todo надо решить как поступать с данными записей');

        $this->remove($entity, true);

        return $this;
    }
}
