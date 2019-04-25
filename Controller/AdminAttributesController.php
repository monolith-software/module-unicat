<?php

namespace Monolith\Module\Unicat\Controller;

use Smart\CoreBundle\Controller\Controller;
use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Symfony\Component\HttpFoundation\Request;

class AdminAttributesController extends Controller
{
    /**
     * @param Request    $request
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, $configuration)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);

        $group = $request->query->get('group', 'ALL');

        if ($group == 'ALL') {
            $attributes = $em->getRepository(UnicatAttribute::class)->findBy([
                'configuration' => $ucm->getConfiguration(),
            ], ['position' => 'ASC']);
        } else {
            $attributes = $em->getRepository(UnicatAttribute::class)->findByGroupsNames($ucm-> getConfiguration(), [$group]);
        }

        return $this->render('@UnicatModule/AdminAttributes/index.html.twig', [
            'attributes'    => $attributes,
            'group'         => $group,
        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createGroupAction(Request $request, $configuration)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getAttributesGroupCreateForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                $ucm->updateAttributesGroup($form->getData());
                $this->addFlash('success', 'Группа атрибутов создана');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/create_group.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request, $configuration)
    {
        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);

        $form   = $ucm->getAttributeCreateForm();

        $configuration = $ucm->getConfiguration();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->has('cancel') and $form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration->getName()]);
            }

            if ($form->isValid()) {
                $unicat->createAttribute($form->getData());
                $this->addFlash('success', 'Атрибут создан');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration->getName()]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     * @param int        $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, $configuration, $name)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);

        $attribute = $em->getRepository(UnicatAttribute::class)->findOneBy(['name' => $name, 'configuration' => $unicat->getCurrentConfiguration()]);

        $form   = $ucm->getAttributeEditForm($attribute);

        $configuration = $ucm->getConfiguration();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->has('cancel') and $form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration->getName()]);
            }

            if ($form->has('update') and $form->get('update')->isClicked() and $form->isValid()) {
                $unicat->updateAttribute($form->getData());
                $this->addFlash('success', 'Атрибут обновлён');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration->getName()]);
            }

            if ($form->has('delete') and $form->get('delete')->isClicked()) {
                $unicat->deleteAttribute($form->getData());
                $this->addFlash('success', 'Атрибут удалён');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration->getName()]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
