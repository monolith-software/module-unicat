<?php

namespace Monolith\Module\Unicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use Monolith\Bundle\CMSBundle\Container;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity(repositoryClass="Monolith\Module\Unicat\Entity\UnicatAttributeRepository")
 * @ORM\Table(name="unicat__attributes",
 *      indexes={
 *          @ORM\Index(columns={"is_enabled"}),
 *          @ORM\Index(columns={"show_in_admin"}),
 *          @ORM\Index(columns={"show_in_list"}),
 *          @ORM\Index(columns={"show_in_view"}),
 *          @ORM\Index(columns={"position"}),
 *      },
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(columns={"name", "configuration_id"}),
 *      },
 * )
 *
 * @UniqueEntity(fields={"name", "configuration"}, message="Имя атрибута должно быть уникальным.")
 */
class UnicatAttribute
{
    use ColumnTrait\Id;
    use ColumnTrait\IsEnabled;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Description;
    use ColumnTrait\Position;
    use ColumnTrait\TitleNotBlank;
    use ColumnTrait\FosUser;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @Assert\Regex(
     *      pattern="/^[a-z][a-z0-9_]+$/",
     *      htmlPattern="^[a-z][a-z0-9_]+$",
     *      message="Имя может состоять только из латинских букв в нижнем регистре, символов подчеркивания и цифр, но первый символ должен быть буква."
     * )
     *
     * @todo перевод сообщения
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=32)
     */
    protected $type;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $search_form_title;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $search_form_type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true, options={"default":"<p>"})
     */
    protected $open_tag;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true, options={"default":"</p>"})
     */
    protected $close_tag;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $is_dedicated_table;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $is_link;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $is_required;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":1})
     */
    protected $is_show_title;

    /**
     * Отсновной атрибут? Используется для фильтрации "основные" и "все" свойства.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":1})
     */
    protected $is_primary;

    /**
     * Отображать в списке администратора.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $show_in_admin;

    /**
     * Отображать в списке записей.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $show_in_list;

    /**
     * Отображать при просмотре записи.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $show_in_view;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    protected $params;

    /**
     * @var array
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $params_yaml;

    /**
     * Связывать items_type (если указан) как "много ко многим".
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $is_items_type_many2many;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="UnicatConfiguration", inversedBy="attributes")
     */
    protected $configuration;

    /**
     * Атрибут - колекция других типов записей.
     *
     * @var UnicatItemType|null
     *
     * @ORM\ManyToOne(targetEntity="UnicatItemType")
     */
    protected $items_type;

    /**
     * @var UnicatAttributesGroup[]|ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="UnicatAttributesGroup", inversedBy="attributes", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinTable(name="unicat__attributes_groups_relations",
     *      joinColumns={@ORM\JoinColumn(name="attribute_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="group_id", referencedColumnName="id")}
     * )
     */
    protected $groups;

    /**
     * @var string
     */
    protected $update_all_records_with_default_value;

    /**
     * UnicatAttribute constructor.
     */
    public function __construct()
    {
        $this->created_at       = new \DateTime();
        $this->groups           = new ArrayCollection();
        $this->is_dedicated_table = false;
        $this->is_enabled       = true;
        $this->is_link          = false;
        $this->is_required      = false;
        $this->is_show_title    = true;
        $this->is_primary       = true;
        $this->is_items_type_many2many = false;
        $this->show_in_view     = true;
        $this->params           = [];
        $this->params_yaml      = null;
        $this->position         = 0;
        $this->open_tag         = '<p>';
        $this->close_tag        = '</p>';
    }

    /**
     * @see getName
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getName();
    }

    /**
     * @return null|string
     */
    public function getValueClassName()
    {
        if ($this->is_dedicated_table) {
            $className = 'Value';

            foreach (explode('_', $this->name) as $namePart) {
                $className .= ucfirst($namePart);
            }

            return $className;
        }

        return null;
    }

    /**
     * Получить значение из списка.
     *
     * @param int|string $id
     *
     * @return int|null|string
     */
    public function getValueByChoice($id)
    {
        if (isset($this->params['form']['choices'])) {
            foreach ($this->params['form']['choices'] as $name => $value) {
                if ($value == $id) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Получение имени в формате CamelCase
     *
     * @return string
     */
    public function getNameCamelCase()
    {
        $str = '';
        foreach (explode('_', $this->getName()) as $val) {
            if (!empty($val)) {
                $str .= ucfirst($val);
            }
        }

        return $str;
    }

    /**
     * Получение вариантов выбора для типа choice
     *
     * @return array|bool
     */
    public function getChoices()
    {
        $choices = [];
        if (isset($this->params['form']['choices'])) {
            $choices = array_flip($this->params['form']['choices']);
        }

        return $choices;
    }

    /**
     * @return string
     */
    public function getValueClassNameWithNameSpace()
    {
        return $this->getConfiguration()->getEntitiesNamespace().$this->getValueClassName();
    }

    /**
     * @param bool $is_dedicated_table
     *
     * @return $this
     */
    public function setIsDedicatedTable($is_dedicated_table)
    {
        $this->is_dedicated_table = $is_dedicated_table;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDedicatedTable()
    {
        return $this->is_dedicated_table;
    }

    /**
     * @return bool
     */
    public function getIsDedicatedTable()
    {
        return $this->is_dedicated_table;
    }

    /**
     * @return bool
     */
    public function isIsLink()
    {
        return $this->is_link;
    }

    /**
     * @param bool $is_link
     *
     * @return $this
     */
    public function setIsLink($is_link)
    {
        $this->is_link = $is_link;

        return $this;
    }

    /**
     * @param bool $is_required
     *
     * @return $this
     */
    public function setIsRequired($is_required)
    {
        $this->is_required = $is_required;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsRequired()
    {
        return $this->is_required;
    }

    /**
     * @return bool
     */
    public function isIsShowTitle()
    {
        return $this->is_show_title;
    }

    /**
     * @param bool $is_show_title
     *
     * @return $this
     */
    public function setIsShowTitle($is_show_title)
    {
        $this->is_show_title = $is_show_title;

        return $this;
    }

    /**
     * @return bool
     */
    public function isIsPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * @param bool $is_primary
     *
     * @return $this
     */
    public function setIsPrimary($is_primary)
    {
        $this->is_primary = $is_primary;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsItemsTypeMany2many(): bool
    {
        return $this->is_items_type_many2many;
    }

    /**
     * @return bool
     */
    public function isItemsTypeMany2many(): bool
    {
        return $this->is_items_type_many2many;
    }

    /**
     * @param bool $is_items_type_many2many
     *
     * @return $this
     */
    public function setIsItemsTypeMany2many($is_items_type_many2many)
    {
        $this->is_items_type_many2many = $is_items_type_many2many;

        return $this;
    }

    /**
     * @return UnicatAttributesGroup[]|ArrayCollection
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * @param UnicatAttributesGroup[] $groups
     *
     * @return $this
     */
    public function setGroups($groups)
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params = [])
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return (null == $this->params) ? [] : $this->params;
    }

    /**
     * @return array
     */
    public function getParam($name)
    {
        if (!empty($this->params) and isset($this->params[$name])) {
            return $this->params[$name];
        } else {
            return [];
        }
    }

    /**
     * @param array $params_yaml
     *
     * @return $this
     */
    public function setParamsYaml($params_yaml)
    {
        $this->params_yaml = $params_yaml;

        $params = Yaml::parse($params_yaml);

        if (empty($params)) {
            $params = [];
        }

        $this->setParams($params);

        return $this;
    }

    /**
     * @return array
     */
    public function getParamsYaml()
    {
        return $this->params_yaml;
    }

    /**
     * @param bool $show_in_admin
     *
     * @return $this
     */
    public function setShowInAdmin($show_in_admin)
    {
        $this->show_in_admin = $show_in_admin;

        return $this;
    }

    /**
     * @return bool
     */
    public function getShowInAdmin()
    {
        return $this->show_in_admin;
    }

    /**
     * @param bool $show_in_list
     *
     * @return $this
     */
    public function setShowInList($show_in_list)
    {
        $this->show_in_list = $show_in_list;

        return $this;
    }

    /**
     * @return bool
     */
    public function getShowInList()
    {
        return $this->show_in_list;
    }

    /**
     * @param bool $show_in_view
     *
     * @return $this
     */
    public function setShowInView($show_in_view)
    {
        $this->show_in_view = $show_in_view;

        return $this;
    }

    /**
     * @return bool
     */
    public function getShowInView()
    {
        return $this->show_in_view;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isType($type)
    {
        return ($type === $this->type) ? true : false;
    }

    /**
     * @param string $mode
     *
     * @return bool
     */
    public function isShowIn($mode)
    {
        switch ($mode) {
            case 'view':
                return $this->show_in_view;
                break;
            case 'list':
                return $this->show_in_list;
                break;
            case 'admin':
                return $this->show_in_admin;
                break;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getOpenTag()
    {
        return $this->open_tag;
    }

    /**
     * @param string $open_tag
     *
     * @return $this
     */
    public function setOpenTag($open_tag)
    {
        $this->open_tag = $open_tag;

        return $this;
    }

    /**
     * @return string
     */
    public function getCloseTag()
    {
        return $this->close_tag;
    }

    /**
     * @param string $close_tag
     *
     * @return $this
     */
    public function setCloseTag($close_tag)
    {
        $this->close_tag = $close_tag;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdateAllRecordsWithDefaultValue()
    {
        return $this->update_all_records_with_default_value;
    }

    /**
     * @param mixed $update_all_records_with_default_value
     *
     * @return $this
     */
    public function setUpdateAllRecordsWithDefaultValue($update_all_records_with_default_value)
    {
        $this->update_all_records_with_default_value = $update_all_records_with_default_value;

        return $this;
    }

    /**
     * @param UnicatConfiguration $configuration
     *
     * @return $this
     */
    public function setConfiguration(UnicatConfiguration $configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @return UnicatItemType
     */
    public function getItemsType()
    {
        return $this->items_type;
    }

    /**
     * @param UnicatItemType $items_type
     *
     * @return $this
     */
    public function setItemsType($items_type)
    {
        $this->items_type = $items_type;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSearchFormTitle(): ?string
    {
        return $this->search_form_title;
    }

    /**
     * @param null|string $search_form_title
     *
     * @return $this
     */
    public function setSearchFormTitle(?string $search_form_title = null)
    {
        $this->search_form_title = $search_form_title;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSearchFormType(): ?string
    {
        return $this->search_form_type;
    }

    /**
     * @param null|string $search_form_type
     *
     * @return $this
     */
    public function setSearchFormType(?string $search_form_type = null)
    {
        $this->search_form_type = $search_form_type;

        return $this;
    }
}
