<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor;

use kuriousagency\emaileditor\services\Emails as EmailsService;
use kuriousagency\emaileditor\variables\EmailEditorVariable;
use kuriousagency\emaileditor\elements\Email as EmailElement;

use Craft;
use yii\base\Event;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\services\Elements;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\commerce\Plugin as Commerce;
use craft\commerce\events\MailEvent;
use craft\commerce\services\Emails as CommerceEmails;
use craft\mail\Mailer;
//-- Remove below when removing field creation--//
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\redactor\Field;
use craft\models\FieldGroup;
use craft\models\FieldLayout; 
use craft\models\FieldLayoutTab;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 *
 * @property  EmailsService $emails
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class EmailEditor extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * EmailEditor::$plugin
     *
     * @var EmailEditor
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';
    public $hasCpSection = true;

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * EmailEditor::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'emails' => EmailsService::class,
        ]);

        // Register our site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'email-editor/email';
            }
        );

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['email-editor'] = 'email-editor/email/index';
                $event->rules['email-editor/settings'] = 'email-editor/email/settings';
                $event->rules['email-editor/settings/email/new'] = 'email-editor/email/edit-settings';
                $event->rules['email-editor/settings/email/<id:\d+>'] = 'email-editor/email/edit-settings';
                $event->rules['email-editor/email/<id:\d+>'] = 'email-editor/email/edit-content';
                $event->rules['email-editor/test/<id:\d+>'] = 'email-editor/email/send';
                $event->rules['email-editor/delete/<id:\d+>'] = 'email-editor/email/delete';
            }
        );

        // Register our elements
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = EmailElement::class;
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('emailEditor', EmailEditorVariable::class);
            }
        );

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );
        
        // Listen + override commerce emails
        Event::on(
            CommerceEmails::class, 
            CommerceEmails::EVENT_BEFORE_SEND_MAIL,
            function(MailEvent $e) {
                //Get the Email Element Associated with the Event
                $email = EmailEditor::$plugin->emails->getAllEmailsByHandle('commerceEmail'.$e->commerceEmail->id);
                //Create Commerce Specific Variables
                $variables = [
                    'order' => $e->order,
                    'orderHistory' => $e->orderHistory,
                    'recipient' => $e->order->shippingAddress->firstName,
                ];
                //Prepare Email
                $e->craftEmail = EmailEditor::$plugin->emails->beforeSendPrep($email,$variables,$e->craftEmail);
                if ($e->craftEmail == false) {
                    Craft::$app->getSession()->setError("Unable to send email");
                }
        });

        // Listen + overide non-commerce emails
        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_SEND,
            function(Event $event) {
				//Craft::dd($event);
                if ($event->message->key != null){
                    //Get the Email Element Associated with the Event
                    $handle = lcfirst(str_replace('_', '', ucwords($event->message->key, '_')));
                    $email = EmailEditor::$plugin->emails->getAllEmailsByHandle($handle);
                    // Create Variables from existing variables
                    $variables = $event->message->variables;
					//Prepare email
                    $event->message = EmailEditor::$plugin->emails->beforeSendPrep($email,$variables,$event->message);
                }
            }
        );



        


/**
 * Logging in Craft involves using one of the following methods:
 *
 * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
 * Craft::info(): record a message that conveys some useful information.
 * Craft::warning(): record a warning message that indicates something unexpected has happened.
 * Craft::error(): record a fatal error that should be investigated as soon as possible.
 *
 * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
 *
 * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
 * the category to the method (prefixed with the fully qualified class name) where the constant appears.
 *
 * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
 * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
 *
 * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
 */
        Craft::info(
            Craft::t(
                'email-editor',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getCpNavItem(): array
    {
        $user = Craft::$app->getUser();
        $admin = $user->isAdmin;
        $item = parent::getCpNavItem();
        $item['subnav']['emails'] = ['label' => 'Emails', 'url' => 'email-editor'];
        if ($admin){
            $item['subnav']['settings'] = ['label' => 'Settings', 'url' => 'email-editor/settings/'];
        }
        return $item;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * return \craft\base\Model|null
     */
    //protected function createSettingsModel()
    //{
    //    return new Settings();
    //}

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    public function getSettingsResponse()
    {   
        return Craft::$app->controller->redirect('email-editor/settings');
    }
    /**
     * Populates db with fields and layouts for testing (To be removed)
     * Creates email elements from existing system emails and pre-existing
     * commerce emails.
     *
     */
    protected function afterInstall()
    {   
        //-----------Creating Email Fields -----------//
            //create field group
            $groupModel = new FieldGroup();
            $groupModel->name = 'Email Editor';
            Craft::$app->fields->saveGroup($groupModel);
            $groups = Craft::$app->fields->getAllGroups();
            foreach($groups as $group) {
                if($group->name != 'Email Editor') {
                    continue;
                }
                $groupModel = $group;
            }
            $layout = Craft::$app->fields->getLayoutByType(EmailElement::class);
            if ($layout->id == null) {
                $layout = new FieldLayout;
                $layout->type = EmailElement::class; 
            }
            //Create Field - Promo
            $promoField = new Lightswitch();
            $promoField->groupId = $groupModel->id;
            $promoField->name = 'Promotional Section';
            $promoField->instructions = 'Included promotional section in email template';
            $promoField->handle = 'emailPromo';
            Craft::$app->fields->saveField($promoField);
        //Create Field - Content
            $redactor = Craft::$app->plugins->getPlugin('redactor');
            if ($redactor){
                $contentField = new Field();
            } else {
                $contentField = new PlainText();
            }
            $contentField->groupId = $groupModel->id;
            $contentField->name = 'Email Content';
            $contentField->instructions = 'Add email body content here';
            $contentField->handle = 'emailContent';
            Craft::$app->fields->saveField($contentField);

            Craft::$app->fields->refreshFields();

            //Save Layout
            $layout->setFields($groupModel->getFields());        
            Craft::$app->fields->saveLayout($layout);

            //Not 100% what this bit is doing but should automatically add the fields to emails
            $tabModel = new FieldLayoutTab();
            $tabModel->setLayout($layout);
            $tabModel->name = 'Email Editor';
            
            $fields = $groupModel->getFields();
            $tabModel->setFields($layout->getFields());
            $layoutTabs = $layout->getTabs();

            $layoutTabs[] = $tabModel;
            $layout->setTabs($layoutTabs);
        // --------- End of field imports ------------//

        // Import System Emails
        $systemEmails = Craft::$app->getSystemMessages()->getAllMessages();
        foreach ($systemEmails as $systemEmail) {
            $email = new EmailElement;
            //$email->fieldLayoutId = $layout->id;
            $email->subject = $systemEmail->subject;
            $email->handle = lcfirst(str_replace('_', '', ucwords($systemEmail->key, '_')));
            $email->enabled = 1;
            $email->emailType = 'system';
            $email->title = str_replace('_', ' ', ucwords($systemEmail->key, '_'));
            $email->template = '_emails/system';
            $email->emailContent = $systemEmail->body;
            Craft::$app->elements->saveElement($email);
        }
		//Import Commerce Emails
		if (Craft::$app->plugins->isPluginInstalled('commerce')) {
            $commerceEmails = Commerce::getInstance()->getEmails()->getAllEmails();
            $this->emails->importCommerceEmails($commerceEmails);
		}
    }
}
