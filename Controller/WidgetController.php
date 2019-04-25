<?php

namespace Monolith\Module\Unicat\Controller;

use Monolith\Bundle\CMSBundle\Entity\Node;
use Monolith\Bundle\CMSBundle\Module\CacheTrait;
use Monolith\Bundle\CMSBundle\Module\NodeTrait;
use Monolith\Module\Unicat\Model\TaxonModel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WidgetController extends Controller
{
    use CacheTrait;
    use NodeTrait;
    use UnicatTrait;

    /** @var  int */
    protected $configuration_id;

    /**
     * @param Request  $request
     * @param string   $css_class
     * @param int      $depth
     * @param string   $template
     * @param bool     $selected_inheritance
     * @param int|null $taxonomy
     *
     * @return Response
     */
    public function taxonTreeAction(
        Request $request,
        Node $node,
        $css_class = null,
        $depth = null,
        $template = 'knp_menu.html.twig',
        $selected_inheritance = false,
        $taxonomy = null
    ) {
        // Хак для Menu\RequestVoter
        $request->attributes->set('__selected_inheritance', $selected_inheritance);

        // @todo cache
        $taxonTree = $this->renderView('@UnicatModule/taxon_tree.html.twig', [
            'taxonClass'    => $this->unicat->getTaxonClass(),
            'css_class'     => $css_class,
            'depth'         => $depth,
            'routeName'     => 'unicat.index',
            'taxonomy'      => empty($taxonomy) ? $this->unicat->getDefaultTaxonomy() : $this->unicat->getTaxonomy($taxonomy),
            'template'      => $template,
        ]);

        $request->attributes->remove('__selected_inheritance');

        return new Response($taxonTree);
    }

    /**
     * @param null    $taxonomy
     *
     * @return JsonResponse
     */
    public function getTaxonsJsonAction($taxonomy = null)
    {
        $taxonomy = empty($taxonomy) ? $this->unicat->getDefaultTaxonomy() : $this->unicat->getTaxonomy($taxonomy);
        $taxons = [];

        if (!empty($taxonomy)) {
            $data = $this->unicat->getTaxonRepository()->findBy(['taxonomy' => $taxonomy], ['position' => 'ASC', 'id' => 'ASC']);
            /** @var TaxonModel $taxon */
            foreach ($data as $taxon) {
                $taxons[$taxon->getSlug()] = $taxon->getTitle();
            }
        }

        return new JsonResponse($taxons);
    }

    /**
     * @param array $unicatRequest
     *
     * @return Response
     */
    public function getItemsAction(Node $node, array $unicatRequest)
    {
        $ucm = $this->get('unicat')->getConfigurationManager($this->configuration_id);
        $unicatItems = $ucm->getData($unicatRequest);

        return $this->render('@UnicatModule/index.html.twig', [
            'mode'          => 'list',
            'attributes'    => $this->unicat->getAttributes(),
            'configuration' => $this->unicat->getConfiguration(),
            'lastTaxon'     => null,
            'childenTaxons' => null,
            'pagerfanta'    => $unicatItems['items'],
            'slug'          => null,
        ]);
    }
}
