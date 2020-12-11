<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\services;

use kuriousagency\emaileditor\EmailEditor;
use kuriousagency\emaileditor\models\Email;
use kuriousagency\emaileditor\records\Email as EmailRecord;

use Craft;

use craft\base\Component;

use craft\db\Query;

use craft\elements\Entry;

use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use craft\mail\Message;

/**
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Emails extends Component
{
    // Public Methods
    // =========================================================================

    public function getEmailById($id)
    {
        $query = $this->_createEmailQuery()
            ->where(['id' => $id])
            ->one();
        if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getEmailByKey($key)
    {
        $query = $this->_createEmailQuery()
            ->where(['systemMessageKey' => $key])
            ->one();
        if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getAllMessages(): array
    {
        $emails = [];
        $systemEmails = Craft::$app->getSystemMessages()->getAllMessages();
        foreach ($systemEmails as $systemEmail) {
            $emails[$systemEmail->key] = ucwords(str_replace('_',' ',$systemEmail->key));
        }
        $emails += $this->getAllCommerceEmails();
        return $emails;
    }

    public function getAllCommerceEmails()
    {
        $emails = [];
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $commerceEmails = \craft\commerce\Plugin::getInstance()->getEmails()->getAllEmails();
            if ($commerceEmails) {
                foreach ($commerceEmails as $commerceEmail) {
                    $emails['commerceEmail'.$commerceEmail->id] = $commerceEmail->name;
                }
            }
        } 
        return $emails;
    }

    public function getSystemMessageByKey($key)
    {
        foreach(Craft::$app->getSystemMessages()->getAllMessages() as $message) {
            if ($message['key'] == $key) {
                return $message;
            }
        }
        return null;
    }

    public function saveEmail($model)
    {
        if ($model->id) {
            $record = EmailRecord::findOne($model->id);
            if (!$record) {
                $record = new EmailRecord();            }
        } else {
            return false;
        }
        $record->id = $model->id;
        $record->systemMessageKey = $model->systemMessageKey;
        $record->subject =  $model->subject;
        $record->testVariables = $model->testVariables ?? $record->testVariables ?? '';
        $record->save();
        return true;
    }
    
    public function sendTestEmail($user, $id): bool
    {   
		$settings = Craft::$app->systemSettings->getSettings('email');
        $entry = Entry::find()->id($id)->one();
        $email = $this->getEmailById($id);

        $variables['entry'] = $entry;
        $variables['recipient'] = $user;

        $testVariables = Json::decodeIfJson($email->testVariables);
        if ($testVariables) {
            foreach ($testVariables as $key => $value) {
                $variables[$key] = $value;
            }
        }
        

        $message = new Message;
        $message->setFrom([Craft::parseEnv($settings['fromEmail']) => Craft::parseEnv($settings['fromName'])]);
        $message->setTo($user->email);

        $message = $this->buildEmail($entry,$message,$email,$variables);

        if ($message == false){   
            return false;
        } else {
            Craft::$app->mailer->send($message);
            return true;
        }
    }

    public function buildEmail(Entry $entry, Message $message, Email $email, Array $variables)
    {
        if (!$this->_createSubjectLine($email,$variables,$message)) {
            return false;
        }
        
        if (!$this->_createBody($entry,$variables,$message,$email)) {
            return false;
        }
        return $message;
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving Emails.
     *
     * @return Query
     */
    private function _createEmailQuery(): Query
    {
        return (new Query())
            ->select([
                'emails.id',
                'emails.systemMessageKey',
                'emails.subject',
                'emails.testVariables'
            ])
            ->orderBy('id')
            ->from(['{{%emaileditor_email}} emails']);
    }

    private function _createSubjectLine($email,$variables,$message)
    {
        $view = Craft::$app->getView();
        $subject = $view->renderString($email->subject, $variables, $view::TEMPLATE_MODE_SITE);

        try {
            $message->setSubject($subject);
        } catch (\Exception $e) {
            $error = Craft::t('email-editor', 'Email template parse error for email “{email}” in “Subject:”. To: “{to}”. Template error: “{message}”', [
                'email' => $message->key,
                'to' => $message->getTo(),
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
            return false;
        }
            
        return true;
    }

    private function _createBody($entry,$variables,$message,$email)
    {
        $view = Craft::$app->getView();
        $siteSettings = Craft::$app->getSections()->getSectionSiteSettings($entry->sectionId);
        foreach ($siteSettings as $setting) {
            if ($setting['siteId'] == Craft::$app->getSites()->getCurrentSite()->id ) {
                $template = $setting['template'];
            }
        }
        if (!$template) {
            return false;
        }
        
        try {
            $htmlBody = $view->renderTemplate($template, $variables, $view::TEMPLATE_MODE_SITE);
            // Lets double render incase the user has any {variable} stuff in there.
            try {
                $htmlBody = $view->renderString($htmlBody,$variables,$view::TEMPLATE_MODE_SITE);

            } catch (\Exception $e) {
                $error = Craft::t('email-editor', 'Email template parse error for email {email}. Failed to render content variables. Template error: {message}', [
                    'email' => $email->systemMessageKey,
                    'message' => $e->getMessage()
                ]);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            $error = Craft::t('email-editor', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $email->systemMessageKey,
                'message' => $e->getMessage()
            ]);
            Craft::dd($e);
            return false;
        }
           
        return true;
    }
}
