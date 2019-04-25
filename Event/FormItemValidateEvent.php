<?php

namespace Monolith\Module\Unicat\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormInterface;

class FormItemValidateEvent extends Event
{
    /** @var  FormInterface */
    protected $form;

    /**
     * ItemValidateEvent constructor.
     *
     * @param FormInterface $form
     */
    public function __construct(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * @return FormInterface
     */
    public function getForm(): FormInterface
    {
        return $this->form;
    }
}
