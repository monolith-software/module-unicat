<?php

namespace Monolith\Module\Unicat\Controller;

use Smart\CoreBundle\Controller\Controller;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Symfony\Component\HttpFoundation\Request;

class AdminTaxonomyController extends Controller
{
    /**
     * @param Request $request
     * @param string  $taxonomy_id
     * @param int     $id
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function taxonEditAction(Request $request, $taxonomy_name, $id, $configuration)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);

        $taxonomy  = $em->getRepository(UnicatTaxonomy::class)->findOneBy(['name' => $taxonomy_name, 'configuration' => $ucm->getConfiguration()]);
        $taxon     = $ucm->getTaxon($id);

        $form = $ucm->getTaxonEditForm($taxon);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToTaxonomyAdmin($taxonomy);
            }

            if ($form->get('update')->isClicked() and $form->isValid()) {
                $unicat->updateTaxon($form->getData());
                $this->addFlash('success', 'Таксон обновлён');

                return $this->redirectToTaxonomyAdmin($taxonomy);
            }

            if ($form->has('delete') and $form->get('delete')->isClicked()) {
                $unicat->deleteTaxon($form->getData());
                $this->addFlash('success', 'Таксон удалён');

                return $this->redirectToTaxonomyAdmin($taxonomy);
            }
        }

        return $this->render('@UnicatModule/AdminTaxonomy/taxon_edit.html.twig', [
            'taxon'         => $taxon,
            'form'          => $form->createView(),
            'taxonomy'      => $taxonomy,
        ]);
    }

    /**
     * @param string $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($configuration)
    {
        $configuration = $this->get('unicat')->getConfiguration($configuration);

        if (empty($configuration)) {
            return $this->render('@CMS/Admin/not_found.html.twig');
        }

        return $this->render('@UnicatModule/AdminTaxonomy/index.html.twig', [
            'configuration'     => $configuration,
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @param string|int $configuration
     * @param int|null $parent_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function taxonomyAction(Request $request, $name, $configuration, $parent_id = null)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $unicat     = $this->get('unicat'); // @todo перевести всё на $ucm.
        $ucm        = $unicat->getConfigurationManager($configuration);
        $taxonomy   = $em->getRepository(UnicatTaxonomy::class)->findOneBy(['name' => $name, 'configuration' => $ucm->getConfiguration()]);

        $parentTaxon = $parent_id ? $ucm->getTaxonRepository()->find($parent_id) : null;

        $form = $ucm->getTaxonCreateForm($taxonomy, [], $parentTaxon);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $unicat->createTaxon($form->getData());

                $this->addFlash('success', 'Таксон создан');

                return $this->redirectToTaxonomyAdmin($taxonomy);
            }
        }

        return $this->render('@UnicatModule/AdminTaxonomy/taxonomy.html.twig', [
            'form'      => $form->createView(),
            'taxonomy'  => $taxonomy,
        ]);
    }

    /**
     * @param Request $request
     * @param string $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request, $configuration)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getTaxonomyCreateForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.taxonomies_index', ['configuration' => $configuration]);
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                $ucm->updateTaxonomy($form->getData());
                $this->addFlash('success', 'Структура создана');

                return $this->redirectToRoute('unicat_admin.taxonomies_index', ['configuration' => $configuration]);
            }
        }

        return $this->render('@UnicatModule/AdminTaxonomy/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $name
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, $name, $configuration)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm = $this->get('unicat')->getConfigurationManager($configuration);

        $taxonomy  = $em->getRepository(UnicatTaxonomy::class)->findOneBy(['name' => $name, 'configuration' => $ucm->getConfiguration()]);

        $form = $ucm->getTaxonomyEditForm($ucm->getTaxonomy($taxonomy->getId()));

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.taxonomies_index', ['configuration' => $configuration]);
            }

            if ($form->get('update')->isClicked() and $form->isValid()) {
                $ucm->updateTaxonomy($form->getData());
                $this->addFlash('success', 'Структура обновлена');

                return $this->redirectToRoute('unicat_admin.taxonomies_index', ['configuration' => $configuration]);
            }
        }

        return $this->render('@UnicatModule/AdminTaxonomy/edit.html.twig', [
            'form'          => $form->createView(),
        ]);
    }

    /**
     * @param UnicatTaxonomy $taxonomy
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToTaxonomyAdmin(UnicatTaxonomy $taxonomy)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $url = $request->query->has('redirect_to')
            ? $request->query->get('redirect_to')
            : $this->generateUrl('unicat_admin.taxonomy', ['name' => $taxonomy->getName(), 'configuration' => $taxonomy->getConfiguration()->getName()]);

        return $this->redirect($url);
    }
}
