<?php
/**
 * Cinnebar.
 *
 * @package Cinnebar
 * @subpackage Model
 * @author $Author$
 * @version $Id$
 */

/**
 * Model.
 *
 * @package Cinnebar
 * @subpackage Model
 * @version $Id$
 */
class Model extends RedBean_SimpleModel
{
    /**
     * Defines the validation mode to throw an exception.
     */
    const VALIDATION_MODE_EXCEPTION = 1;

    /**
     * Defines the validation mode to store an valid or invalid state with the bean.
     */
    const VALIDATION_MODE_IMPLICIT = 2;

    /**
     * Defines the validation mode to simply return the result of a validation.
     */
    const VALIDATION_MODE_EXPLICIT = 4;

    /**
     * Container for the validators.
     *
     * @var array
     */
    protected $validators = array();

    /**
     * Holds the validation mode where 1 = Exception, 2 = Implicit attribute, 4 = Explicit.
     * Affects all beans.
     *
     * @var int
     */
    protected static $validation_mode = self::VALIDATION_MODE_EXCEPTION;

    /**
     * Container for the converters.
     *
     * @var array
     */
    protected $converters = array();

    /**
     * Container for the errors.
     *
     * @var array
     */
    protected $errors = array();

    /**
     * Holds the auto tag status.
     *
     * @var bool
     */
    protected $auto_tag = false;

    /**
     * Holds the default actions.
     *
     * @var array
     */
    protected $actions =  array(
        'index' => array('idle', 'expunge'),
        'add' => array('add', 'edit', 'index'),
        'edit' => array('edit', 'next_edit', 'prev_edit', 'index'),
        'delete' => array('index')
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Returns an array with attributes for lists.
     *
     * @param string (optional) $layout
     * @return array
     */
    public function getAttributes($layout = 'table')
    {
        return array();
        /*
        return array(
            array(
                'name' => 'id',
                'sort' => array(
                    'name' => $this->bean->getMeta('type').'.name'
                ),
                'filter' => array(
                    'tag' => 'number'
                )
            )
        );
        */
    }

    /**
     * Returns the attribute of the related bean.
     *
     * @param string $bean_attribute the first part is the bean, second the attribute
     * @return string
     */
    public function relatedOne($bean_attribute)
    {
        $parts = explode('_', $bean_attribute);
        if (!$this->bean->{$parts[0]}) {
            return '';
        }
        return $this->bean->{$parts[0]}->{$parts[1]};
    }

    /**
     * Returns a string representing a boolean state of an beans attribute.
     *
     * @param string $attribute name to represent as a true or false string
     * @return string
     */
    public function boolean($attribute)
    {
        if ($this->bean->{$attribute}) {
            return I18n::__('bool_true');
        }
        return I18n::__('bool_false');
    }

    /**
     * Returns a localized datetime string.
     *
     * @param string $attribute name to localize
     * @return string
     */
    public function localizedDateTime($attribute)
    {
        if (! Flight::setlocale()) {
            return $this->bean->{$attribute};
        }
        $templates = Flight::get('templates');
        return strftime($templates['datetime'], strtotime($this->bean->{$attribute}));
    }

    /**
     * Returns a localized date string.
     *
     * @param string $attribute name to localize
     * @return string
     */
    public function localizedDate($attribute)
    {
        if (! Flight::setlocale()) {
            return $this->bean->{$attribute};
        }
        $templates = Flight::get('templates');
        return strftime($templates['date'], strtotime($this->bean->{$attribute}));
    }

    /**
     * Returns a localized time string.
     *
     * @param string $attribute name to localize
     * @return string
     */
    public function localizedTime($attribute)
    {
        if (! Flight::setlocale()) {
            return $this->bean->{$attribute};
        }
        $templates = Flight::get('templates');
        return strftime($templates['time'], strtotime($this->bean->{$attribute}));
    }

    /**
     * Renders a decimal value nicely.
     *
     * @param string $attribute
     * @param int $decimals defaults to 3
     * @param string $decimal_point defaults to '.'
     * @param string $thousands_separator defaults to ','
     * @return string
     */
    public function decimal($attribute, $decimals = 3, $decimal_point = ',', $thousands_separator = '.')
    {
        if (! $this->bean->{$attribute}) {
            return '';
        }
        return number_format((float)$this->bean->{$attribute}, $decimals, $decimal_point, $thousands_separator);
    }

    /**
     * Returns the root bean of a hierarchy.
     *
     * If the optional parameter is set the last bean before the parent bean with
     * the given id will be returnded. Stop by sitesfolder id for example when you
     * want to cut the tree a certain level when building a simple cms based on domain.
     *
     * @uses getRoot() to return the domain up one level
     *
     * @param int (optional) $stop_id of the domain to cut the bubble up route
     * @return RedBeanPHP\OODBBean $root
     */
    public function getRoot($stop_id = 0)
    {
        if (! $this->bean->{$this->bean->getMeta('type')}) {
            return $this->bean;
        }
        if ($this->bean->{$this->bean->getMeta('type')}->getId() == $stop_id) {
            return $this->bean;
        }
        return $this->bean->{$this->bean->getMeta('type')}->getRoot($stop_id);
    }

    /**
     * Returns an array with direct descendents of this bean.
     *
     * @return array $children
     */
    public function getChildren()
    {
        $own = 'own'.ucfirst($this->bean->getMeta('type'));
        return $this->bean->{$own};
    }

    /**
     * Returns SQL string.
     *
     * Use with DISTINCT([table].id) to fetch all beans or use with COUNT(DISTINCT([table].id))
     * to get the total number of records matching the optional where clause.
     *
     * @param string (optional) $fields to select
     * @param string (optional) $where
     * @param string (optional) $order
     * @param int (optional) $offset
     * @param int (optional) $limit
     * @return string $sql
     */
    public function getSql($fields = 'id', $where = '1', $order = null, $offset = null, $limit = null)
    {
        $sql = <<<SQL
    	SELECT
    	    {$fields}
    	FROM
    	    {$this->bean->getMeta('type')}
    	WHERE
    	    {$where}
SQL;
        //add optional order by
        if ($order) {
            $sql .= " ORDER BY {$order}";
        }
        //add optional limit
        if ($offset || $limit) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        return $sql;
    }

    /**
     * Returns automatic keywords for this bean.
     *
     * @param array (optional) $tags which the user may has entered
     * @return array
     */
    public function keywords()
    {
        return array(
            $this->bean->getId()
        );
    }

    /**
     * Returns an array of possible actions.
     *
     * Overwrite this function on your bean models.
     *
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Sets an action.
     *
     * @param string $action
     * @param array $actions
     * @return void
     */
    public function setAction($action = '', $actions = array())
    {
        $this->actions[$action] = $actions;
        return null;
    }

    /**
     * Sets all actions.
     *
     * @param array $actions
     * @return void
     */
    public function setActions($actions = array())
    {
        $this->actions = $actions;
        return null;
    }

    /**
     * Expunge is an alias of R::trash().
     */
    public function expunge()
    {
        R::trash($this->bean);
    }

    /**
     * Returns or sets the auto tag flag.
     *
     * @param bool (optional) $switch
     * @return bool
     */
    public function autoTag($switch = null)
    {
        if ($switch !== null) {
            $this->auto_tag = $switch;
        }
        return $this->auto_tag;
    }

    /**
     * Returns or sets the auto info flag.
     *
     * @param bool (optional) $switch
     * @return bool
     */
    public function autoInfo($switch = null)
    {
        if ($switch !== null) {
            $this->auto_info = $switch;
        }
        return $this->auto_info;
    }

    /**
     * Returns a *i18n bean for this bean.
     *
     * A i18n bean means an internationalized version of a bean where the localizeable fields
     * are stored in a bean that extends the original beans name with the string 'i18n'.
     * If there is no i18n version for the asked language then the default language is
     * looked up and duplicated.
     *
     * @param string $language iso code of the wanted language
     * @return RedBean_OODBBean
     */
    public function i18n($language)
    {
        $i18nType = $this->bean->getMeta('type').'i18n';
        if (! $i18n = R::findOne($i18nType, $this->bean->getMeta('type').'_id = ? AND language = ?', array($this->bean->getId(), $language))) {
            $i18n = R::dispense($i18nType);
            $i18n->language = $language;
            $i18n->name = $this->bean->name;
        }
        return $i18n;
    }

    /**
     * Returns the translated word for a beans name attribute.
     *
     * @return string
     */
    public function translated()
    {
        return $this->bean->i18n(Flight::get('user')->getLanguage())->name;
    }

    /**
     * Update.
     */
    public function update()
    {
        $this->convert();
        $this->validate();
    }

    /**
     * This is called after the bean was updated.
     *
     * @return void
     */
    public function after_update()
    {
        if ($this->autoTag()) {
            $this->setAutoTags();
        }
    }

    /**
     * setAutoTags()
     *
     * @uses keywords()
     * @return array $tags
     */
    protected function setAutoTags()
    {
        if (! $this->bean->getId()) {
            return false;
        }
        $tags = array();
        foreach ($this->keywords() as $n=>$keyword) {
            if (trim($keyword) == '') {
                continue;
            }
            $tags[] = trim($keyword);
        }
        R::tag($this->bean, $tags);
        return $tags;
    }

    /**
     * Adds an error to the general errors or to a certain attribute if the optional parameter is set.
     *
     * @param string $errorText
     * @param string (optional) $attribute
     * @return void
     */
    public function addError($errorText, $attribute = '')
    {
        $this->errors[$attribute][] = $errorText;
    }

    /**
     * Returns the errors of this model.
     *
     * @return array $errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Returns true if model has errors.
     *
     * If the optional parameter is set a certain attribute is tested for having an error or not.
     *
     * @uses Cinnebar_Model::$errors
     * @param string (optional) $attribute
     * @return bool $hasErrorOrHasNoError
     */
    public function hasError($attribute = '')
    {
        if ($attribute === '') {
            return ! empty($this->errors);
        }
        return isset($this->errors[$attribute]);
    }

    /**
     * Alias for {@link hasError()} call without an special attribute.
     *
     * @return bool $hasErrorsOrNone
     */
    public function hasErrors()
    {
        return $this->hasError();
    }

    /**
     * Set the validation mode.
     *
     * This applies to all your beans at once.
     *
     * @param bool $mode
     */
    public function setValidationMode($mode)
    {
        self::$validation_mode = $mode;
    }

    /**
     * Returns the current validation mode.
     *
     * @return bool
     */
    public function getValidationMode()
    {
        return self::$validation_mode;
    }

    /**
     * Add a validator to the attribute.
     *
     * @param string $attribute
     * @param mixed $validator
     *
     * @return Model $this
     */
    public function addValidator($attribute, $validator)
    {
        if (! is_array($validator)) {
            $validator = array($validator);
        }
        foreach ($validator as $oneValidator) {
            $this->validators[$attribute][] = $oneValidator;
        }
        return $this;
    }

    /**
     * Returns true or false wether the model validates or not.
     *
     * @uses $invalid
     *
     * @return bool
     * @throws Exception_Validation if validation mode is set to exception (default)
     */
    public function validate()
    {
        if (isset($this->bean->invalid) && $this->bean->invalid) {
            $this->bean->invalid = false;
        }
        if (empty($this->validators)) {
            return true;
        }
        $suggest = true;
        foreach ($this->validators as $attribute => $attributeValidators) {
            foreach ($attributeValidators as $validator) {
                if (! $validator->validate($this->bean->$attribute)) {
                    $suggest = false;
                    $this->addError(I18n::__(strtolower(get_class($validator)).'_invalid'), $attribute);
                }
            }
        }
        if ($suggest === true) {
            return true;
        }
        //validation failed, react according to validation mode
        switch (self::$validation_mode) {
            case self::VALIDATION_MODE_EXCEPTION:
                throw new Exception_Validation("Invalid {$this->bean->getMeta('type')}#{$this->bean->getId()}");
                break;
            case self::VALIDATION_MODE_IMPLICIT:
                $this->bean->invalid = true;
                break;
            default:
                //nothing, only return false
        }
        return false;
    }

    /**
     * Add a converter to the attribute.
     *
     * @param string $attribute
     * @param mixed $converter
     *
     * @return Model $this
     */
    public function addConverter($attribute, $converter)
    {
        if (! is_array($converter)) {
            $converter = array($converter);
        }
        foreach ($converter as $oneConverter) {
            $this->converters[$attribute][] = $oneConverter;
        }
        return $this;
    }

    /**
     * Runs all converters of this model.
     *
     * @return void
     */
    public function convert()
    {
        if (empty($this->converters)) {
            return;
        }
        foreach ($this->converters as $attribute => $attributeConverters) {
            foreach ($attributeConverters as $converter) {
                $this->bean->$attribute = $converter->convert($this->bean->$attribute);
            }
        }
        return;
    }
}
