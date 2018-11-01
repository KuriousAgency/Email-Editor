<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\models;

use kuriousagency\emaileditor\EmailEditor;

use Craft;
use craft\base\Model;
use craft\behaviours\FieldLayoutBehaviour;
use craft\models\FieldLayout;

/**
 * Email Model
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Email extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Email Id
     * 
     * @var int
     */
    public $id = null;

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['id','fieldLayoutId'], 'number', 'integerOnly' => true],
        ];
    }

    // /**
    //  * @return FieldLayout
    //  */
    // public function getFieldLayout(): getFieldLayout
    // {
    //     $behavior = $this->getBehavior('fieldLayout');
    //     return $behavior->getFieldLayout();
    // }
    // public function behaviors(): array
    // {
    //     return [
    //         'fieldlayout' => [
    //             'class' => FieldLayoutBehavior::class,
    //             'elementType' => EmailEditor::class,
    //             'idAttribute' => 'fieldLayoutId'
    //         ]
    //     ];
    // }
}
