<?php
/**
 * Email Editor plugin for Craft CMS 3.x
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
use craft\commerce\events\EmailEvent;
use craft\commerce\services\Emails as CommerceEmails;
use craft\mail\Mailer;


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
 * @package   Email Editor
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
        
        $this->_registerEvents();
        // Register our CP routes
        
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

    // Protected Methods
    // =========================================================================

    /**
     * Creates all the event listeners
     */
    private function _registerEvents()
    {
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
						//$email->emailContent = $systemEmail->body;
						Craft::$app->elements->saveElement($email);
					}
					//Import Commerce Emails
					if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
						$commerceEmails = Commerce::getInstance()->getEmails()->getAllEmails();
						$this->emails->importCommerceEmails($commerceEmails);
					}
                }
            }
        );
        
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            // Listen + override commerce emails
            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_BEFORE_SEND_MAIL,
                function(MailEvent $e) {
                    //Get the Email Element Associated with the Event
                    $email = EmailEditor::$plugin->emails->getAllEmailsByHandle('commerceEmail'.$e->commerceEmail->id);
                    //Create Commerce Specific Variables
                    $toEmailArr = array_keys($e->craftEmail->getTo());
                    $toEmail = array_pop($toEmailArr);
                    $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail) ?? ['email' => $toEmail,'firstName'];
                    $order = $e->order;
                    if (!$user) {
                        $user = [
                            'email' => $toEmail,
                            'firstName' => $order->shippingAddress->firstName ?? $order->billingAddress->firstName ?? $toEmail,
                            'lastName' => $order->shippingAddress->lastName ?? $order->billingAddress->lastName ?? $toEmail
                        ];
                    }
                    $variables = [
                        'order' => $order,
                        'orderHistory' => $e->orderHistory,
                        'user' => $user
                    ];
                    //Prepare Email
                    $e->craftEmail = EmailEditor::$plugin->emails->beforeSendPrep($email,$variables,$e->craftEmail);
                    if ($e->craftEmail == false) {
                        Craft::$app->getSession()->setError("Unable to send email");
                    }
            });

            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_BEFORE_DELETE_EMAIL,
                function(EmailEvent $e) {
                    $email = EmailEditor::$plugin->emails->getEmailByHandle('commerceEmail'.$e->email->id);
                    if ($email) {
                        EmailEditor::$plugin->emails->deleteEmailById($email->id);
                    }
                }
            );

            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_AFTER_SAVE_EMAIL,
                function(EmailEvent $e) {
                    $email = EmailEditor::$plugin->emails->getEmailByHandle('commerceEmail'.$e->email->id);
                    if ($email) {
                        $email->title = $e->email->name;
                        $email->template = $e->email->templatePath;
                        $email->subject = $e->email->subject;
                        $email->enabled = $e->email->enabled;
                        Craft::$app->elements->saveElement($email);
                    }
                }
            );
        }

        // Listen + overide non-commerce emails
        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_SEND,
            function(Event $event) {
		
				$messageVariables = $event->message->variables ? $event->message->variables : [];
                $toEmailArr = array_keys($event->message->getTo());
                $toEmail = array_pop($toEmailArr);
                $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail);
                if (!$user) {
                    $user = [
                        'email' => $toEmail,
                        'firstName' => $order->shippingAddress->firstName ?? $order->billingAddress->firstName ?? $toEmail,
                        'lastName' => $order->shippingAddress->lastName ?? $order->billingAddress->lastName ?? $toEmail
                    ];
                }
                if ($event->message->key != null || array_key_exists('handle',$messageVariables)) {
					//Get the Email Element Associated with the Event
					if ($event->message->key != null) {
						$handle = lcfirst(str_replace('_', '', ucwords($event->message->key, '_')));
					} else {
						$handle = $event->message->variables['handle'];
					}
                    $email = EmailEditor::$plugin->emails->getAllEmailsByHandle($handle);
					// Create Variables from existing variables
					if($email) {
                        $variables = $event->message->variables;
                        if (!array_key_exists('user',$variables)){
                            $variables['user'] = $user;
                        }
						if ($event->message->key == 'test_email') {
							$variables['settings'] = Craft::$app->systemSettings->getSettings('email');
						}
                        //Prepare email
                        $event->message = EmailEditor::$plugin->emails->beforeSendPrep($email,$variables,$event->message);
					}
                }
            }
        );
    }
}
