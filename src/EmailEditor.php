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
use kuriousagency\emaileditor\models\Settings;
use kuriousagency\emaileditor\models\Email;

use Craft;

use craft\base\Plugin;

use craft\elements\Entry;

use craft\events\ElementEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;

use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

use craft\mail\Mailer;

use craft\services\Elements;
use craft\services\Plugins;
use craft\services\SystemMessages;
use craft\services\UserPermissions;

use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;

use craft\commerce\services\Emails as CommerceEmails;

use yii\base\Event;

/**
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
    public static $plugin;

    public $schemaVersion = '2.0.0';
    public $hasCpSection = false;
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'emails' => EmailsService::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['/email-editor/test/<id:\d+>'] = 'email-editor/email/send';
            }
        );
        
        $this->_registerPermissions();
        $this->_registerHooks();
        $this->_registerEvents();

        // Craft Commerce Emails work differently to Craft Emails
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $this->_registerCommerceEvents();
        }
        
        Craft::info(
            Craft::t(
                'email-editor',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    
    // Protected Methods
    // =========================================================================

    protected function createSettingsModel()
    {
       return new Settings();
    }

    protected function settingsHtml()
    {
        $sections = [];
        foreach (Craft::$app->getSections()->getAllSections() as $section) {
            $sections[$section->id] = $section->name;
        }

        return \Craft::$app->getView()->renderTemplate(
            'email-editor/settings',
            [ 
                'settings' => $this->getSettings(),
                'sections' => $sections
            ]
        );
    }

    // Private Methods
    // =========================================================================

    private function _registerPermissions()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Email Editor'] = [
                    'setTestVariables' => [
                        'label' => 'Set Test Variables',
                    ],
                    'manageSettings' => [
                        'label' => 'Manage Plugin Settings'
                    ],
                    'createEmails' => [
                        'label' => 'Create New Emails'
                    ],
                    'testEmails' => [
                        'label' => 'Test Emails'
                    ]
                ];
            }
        );
    }

    private function _registerHooks()
    {
        Craft::$app->view->hook('cp.entries.edit.details', function(array &$context) {
			$view = Craft::$app->getView();
            $entry = $context['entry'];

            if($entry->sectionId == $this->getSettings()->sectionId)
            {
                $options = $this->emails->getAllMessages();
                $currentMessage = $this->emails->getEmailById($entry->id);

                return $view->renderTemplate(
                    'email-editor/details',
                    [
                        'entry'=>$entry,
                        'subject' => $currentMessage ? $currentMessage->subject : null,
                        'options'=>$options,
                        'selectedOption'=> $currentMessage ? $currentMessage->systemMessageKey : null,
                        'testUrl' => UrlHelper::prependCpTrigger('email-editor/test/'.$entry->id),
                    ]
                );

            }
			return;
        });

        if (Craft::$app->user->checkPermission('setTestVariables')) {       
            Craft::$app->view->hook('cp.entries.edit.content', function(array &$context) {
                $view = Craft::$app->getView();
                $entry = $context['entry'];

                if($entry->sectionId == $this->getSettings()->sectionId)
                {
                    $currentMessage = $this->emails->getEmailById($entry->id);

                    return $view->renderTemplate(
                        'email-editor/variables',
                        [
                            'testVariables' => $currentMessage ? $currentMessage->testVariables : ''
                        ]
                    );

                }
                return;
            });
        }
    }

    private function _registerEvents()
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT, 
            function(ElementEvent $e) {
                if (ElementHelper::isDraftOrRevision($e->element)) {
                    return;
                }
                if ($e->element instanceof Entry && $e->element->sectionId == $this->getSettings()->sectionId)
                {
                    $request = Craft::$app->getRequest();

                    $email = new Email();
                    $email->id = $e->element->id;
                    $email->systemMessageKey = $request->getBodyParam('emailKey');
                    $email->subject = $request->getBodyParam('subject');
                    $testVariables = $request->getBodyParam('testVariables');
                    if ($testVariables && !Json::isJsonObject($testVariables)) {
                        $testVariables = '';
                    }
                    $email->testVariables = $testVariables;
                    $this->emails->saveEmail($email);
                    return;
                }
            }
        );

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $e) {
                if ($e->templateMode == View::TEMPLATE_MODE_SITE) {
                    if (array_key_exists('entry',$e->variables) && $e->variables['entry']->sectionId == $this->getSettings()->sectionId && Craft::$app->user->checkPermission('testEmails')) {
                        // Looks like we are testing an email entry
                        $email = EmailEditor::$plugin->emails->getEmailById($e->variables['entry']->id);
                        $e->variables['testVariables'] = Json::decodeIfJson($email->testVariables);
                        $e->variables['variables']['testVariables'] = Json::decodeIfJson($email->testVariables);
                    }
                }
            }
        );

        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_SEND,
            function(Event $event) {
                if ($event->message->key != null) {
                    $email = EmailEditor::$plugin->emails->getEmailByKey($event->message->key);
                    if ($email) {  
                        $messageVariables = $event->message->variables ? $event->message->variables : [];
                        $toEmailArr = array_keys($event->message->getTo());
                        $toEmail = array_pop($toEmailArr);
                        $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail);
                        if (!$user) {
                            $user = [
                                'email' => $toEmail,
                                'firstName' => explode('@',$toEmail)[0],
                                'friendlyName' => explode('@',$toEmail)[0]
                            ];
                        }
                        $entry = Entry::find()->id($email->id)->orderBy('postDate desc')->one();
                        if($entry) {
                            $variables = $event->message->variables;
                            $variables['recipient'] = $user;
                            $variables['entry'] = $entry;
                            $event->message = EmailEditor::$plugin->emails->buildEmail($entry,$event->message,$email,$variables);
                        }
                    }
                }
            }
        ); 
        
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                if ($customEmails = $this->getSettings()->customEmails) {
                    foreach ($customEmails as $customEmail) {
                        $event->messages[] = $customEmail;
                    }
                }
            }
        );
    }

    private function _registerCommerceEvents()
    {
        if (class_exists('craft\\commerce\\services\\Emails')) {
            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_BEFORE_SEND_MAIL,
                function(\craft\commerce\events\MailEvent $e) {
                    //Get the Email Editor Model Associated with the Commerce Email Event
                    $email = EmailEditor::$plugin->emails->getEmailByKey('commerceEmail'.$e->commerceEmail->id);
    
                    if ($email) {  
                        $toEmailArr = array_keys($e->craftEmail->getTo());
                        $toEmail = array_pop($toEmailArr);
                        $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail);
                        if (!$user) {
                            $user = [
                                'email' => $toEmail,
                                'firstName' => $e->order->billingAddress->firstName ?? explode('@',$toEmail)[0],
                                'friendlyName' => $e->order->billingAddress->firstName ?? explode('@',$toEmail)[0]
                            ];
                        }
                        $entry = Entry::find()->id($email->id)->orderBy('postDate desc')->one();
                        if($entry) {
                            $variables['recipient'] = $user;
                            $variables['entry'] = $entry;
                            $variables['order'] = $e->order;
                            $variables['orderHistory'] = $e->orderHistory;
                            $e->craftEmail = EmailEditor::$plugin->emails->buildEmail($entry,$e->craftEmail,$email,$variables);
                        }
                    }
                }
            );
        }
    }
}
