<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\elements;

use kuriousagency\emaileditor\EmailEditor;
use kuriousagency\emaileditor\elements\db\EmailQuery;
use kuriousagency\emaileditor\records\Email as EmailRecord;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;


/**
 * Email Element
 *
 * Element is the base class for classes representing elements in terms of objects.
 *
 * @property FieldLayout|null      $fieldLayout           The field layout used by this element
 * @property array                 $htmlAttributes        Any attributes that should be included in the element’s DOM representation in the Control Panel
 * @property int[]                 $supportedSiteIds      The site IDs this element is available in
 * @property string|null           $uriFormat             The URI format used to generate this element’s URL
 * @property string|null           $url                   The element’s full URL
 * @property \Twig_Markup|null     $link                  An anchor pre-filled with this element’s URL and title
 * @property string|null           $ref                   The reference string to this element
 * @property string                $indexHtml             The element index HTML
 * @property bool                  $isEditable            Whether the current user can edit the element
 * @property string|null           $cpEditUrl             The element’s CP edit URL
 * @property string|null           $thumbUrl              The URL to the element’s thumbnail, if there is one
 * @property string|null           $iconUrl               The URL to the element’s icon image, if there is one
 * @property string|null           $status                The element’s status
 * @property Element               $next                  The next element relative to this one, from a given set of criteria
 * @property Element               $prev                  The previous element relative to this one, from a given set of criteria
 * @property Element               $parent                The element’s parent
 * @property mixed                 $route                 The route that should be used when the element’s URI is requested
 * @property int|null              $structureId           The ID of the structure that the element is associated with, if any
 * @property ElementQueryInterface $ancestors             The element’s ancestors
 * @property ElementQueryInterface $descendants           The element’s descendants
 * @property ElementQueryInterface $children              The element’s children
 * @property ElementQueryInterface $siblings              All of the element’s siblings
 * @property Element               $prevSibling           The element’s previous sibling
 * @property Element               $nextSibling           The element’s next sibling
 * @property bool                  $hasDescendants        Whether the element has descendants
 * @property int                   $totalDescendants      The total number of descendants that the element has
 * @property string                $title                 The element’s title
 * @property string|null           $serializedFieldValues Array of the element’s serialized custom field values, indexed by their handles
 * @property array                 $fieldParamNamespace   The namespace used by custom field params on the request
 * @property string                $contentTable          The name of the table this element’s content is stored in
 * @property string                $fieldColumnPrefix     The field column prefix this element’s content uses
 * @property string                $fieldContext          The field context this element’s content uses
 *
 * http://pixelandtonic.com/blog/craft-element-types
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Email extends Element
{
    // Public Properties
    // =========================================================================

    public $id;
    /**
     * Email Subject
     *
     * @var string
     */
    public $subject = 'Default Subject';

    /**
     * Email Template
     *
     * @var string
     */
    public $template = '_emails/custom';

    /**
     * Email CP handle
     *
     * @var string
     */
    public $handle = '';

    /**
     * Email Type
     *
     * @var string
     */
    public $emailType = '';

    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return Craft::t('email-editor', 'Email');
    }

    /**
     * Returns whether elements of this type will be storing any data in the `content`
     * table (tiles or custom fields).
     *
     * @return bool Whether elements of this type will be storing any data in the `content` table.
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * Returns whether elements of this type have traditional titles.
     *
     * @return bool Whether elements of this type have traditional titles.
     */
    public static function hasTitles(): bool
    {
        return true;
    }


    /**
     * enabled or disabled 
     */
    public static function hasStatuses(): bool
    {
        return true;
    }
    /**
     * Returns whether elements of this type have statuses.
     *
     * If this returns `true`, the element index template will show a Status menu
     * by default, and your elements will get status indicator icons next to them.
     *
     * Use [[statuses()]] to customize which statuses the elements might have.
     *
     * @return bool Whether elements of this type have statuses.
     * @see statuses()
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * Creates an [[ElementQueryInterface]] instance for query purpose.
     *
     * @return ElementQueryInterface The newly created [[ElementQueryInterface]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new EmailQuery(static::class);
    }

    /**
     * Defines the sources that elements of this type may belong to.
     *
     * @param string|null $context The context ('index' or 'modal').
     *
     * @return array The sources.
     * @see sources()
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => 'system',
                'label' => 'System',
                'criteria' => [
                    'emailType' => 'system',
                ]
            ],
        ];
        if (EmailEditor::$plugin->emails->getAllEmailsByType('custom')){
            $sources[] = [
                'key' => 'custom',
                'label' => 'Custom',
                'criteria' => [
                    'emailType' => 'custom',
                ]
            ];
        }
		if (Craft::$app->plugins->isPluginInstalled('commerce')) {
			$sources[] = [
                'key' => 'commerce',
                'label' => 'Commerce',
                'criteria' => [
                    'emailType' => 'commerce',
                ]
			];
		}
        return $sources;
    }
    /**
     * Defines the column titles on element listing page.
     *
     * @return array The column titles
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => 'Title',
            'subject' => 'Subject',        
            'emailType' => 'Type',
            'test' => 'Test',          
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        $user = Craft::$app->getUser();
        $admin = $user->isAdmin;
        switch ($attribute) {
            case 'test': {
                $id = $this->id;
                $url = 'email-editor/test/'.$id;
                $html = '<div class="buttons">
                            <a href="'.$url.'" class="btn icon submit">Test</a>
                        </div>';

                return $url ? $html : '';
            }
        }
        return parent::tableAttributeHtml($attribute);
    }
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
            ['template', 'required'],
        ];
    }

    /**
     * Returns whether the current user can edit the element.
     *
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * Returns the field layout used by this element.
     *
     * @return FieldLayout|null
     */
    public function getFieldLayout()
    {
        return Craft::$app->fields->getLayoutByType(Email::class.'\\'.$this->handle);
    }

    // Indexes, etc.
    // -------------------------------------------------------------------------

    /**
     * Returns the HTML for the element’s editor HUD.
     *
     * @return string The HTML for the editor HUD
     */
    public function getEditorHtml(): string
    {
        $html = Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('app', 'Title'),
                'siteId' => $this->siteId,
                'id' => 'title',
                'name' => 'title',
                'value' => $this->title,
                'errors' => $this->getErrors('title'),
                'first' => true,
                'autofocus' => true,
                'required' => true
            ],
        ]);
        $html .= Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => 'Subject',
                'siteId' => $this->siteId,
                'id' => 'subject',
                'name' => 'subject',
                'value' => $this->subject,
                'errors' => $this->getErrors('subject'),
                'first' => false,
                'autofocus' => false,
                'required' => true
            ]
        ]);

        $html .= parent::getEditorHtml();

        return $html;
    }
            

    // Events
    // -------------------------------------------------------------------------

    /**
     * Performs actions before an element is saved.
     *
     * @param bool $isNew Whether the element is brand new
     *
     * @return bool Whether the element should be saved
     */
    public function beforeSave(bool $isNew): bool
    {
        return true;
    }

    /**
     * Performs actions after an element is saved.
     *
     * @param bool $isNew Whether the element is brand new
     *
     * @return void
     */
    public function afterSave(bool $isNew)
    {   
        if (!$isNew) {
            $record = EmailRecord::findOne($this->id);
            if (!$record) {
                throw new Exception('Invalid Email ID: ' . $this->id);
            }
        } else {
            $record = new EmailRecord();
            $record->id = $this->id;
        }
        $record->subject = $this->subject;
        $record->template = $this->template;
        $record->handle = $this->handle;
        $record->emailType = $this->emailType;
        $record->save(false);
        $this->id = $record->id;
        return parent::afterSave($isNew);
    }

    /**
     * Performs actions before an element is deleted.
     *
     * @return bool Whether the element should be deleted
     */
    public function beforeDelete(): bool
    {
        return true;
    }

    /**
     * Performs actions after an element is deleted.
     *
     * @return void
     */
    public function afterDelete()
    {
    }

    /**
     * Performs actions before an element is moved within a structure.
     *
     * @param int $structureId The structure ID
     *
     * @return bool Whether the element should be moved within the structure
     */
    public function beforeMoveInStructure(int $structureId): bool
    {
        return true;
    }

    /**
     * Performs actions after an element is moved within a structure.
     *
     * @param int $structureId The structure ID
     *
     * @return void
     */
    public function afterMoveInStructure(int $structureId)
    {
    }
    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
        $url = UrlHelper::cpUrl('email-editor/email/' . $this->id);

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }
}
