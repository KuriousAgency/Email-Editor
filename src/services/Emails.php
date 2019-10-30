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
use kuriousagency\emaileditor\elements\Email;
use kuriousagency\emaileditor\records\Email as EmailRecord;

use Craft;
use craft\base\Component;
use craft\elements\actions\Delete;
use craft\mail\Message;
use craft\db\Query;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use yii\helpers\Markdown;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;

/**
 * Emails Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Emails extends Component
{
    // Public Methods
    // =========================================================================

/**
     * Get an email by its ID.
     *
     * @param int $id
     * @return Email|null
     */
    public function getEmailById($id)
    {
        if (!$id) {
            return null;
        }
        $query = Email::find();
        $query->id($id);

        return $query->one();
    }

    /**
     * Get an email by its handle.
     *
     * @param string $handle
     * @return Email|null
     */
    public function getEmailByHandle($handle)
    {
        if (!$handle) {
            return null;
        }
        $query = Email::find();
        $query->handle($handle);

        return $query->one();
    }

    /**
     * Get all emails.
     *
     * @return Email[]
     */
    public function getAllEmails(): array
    {
        $emailQuery = Email::find();
        $emails = $emailQuery->all();
        return $emails;
    }

    /**
     * Delete an email by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteEmailById($id): bool
    {
        $email = Email::findOne($id);
        if ($email) {
            Craft::$app->getElements()->deleteElement($email);
            return true;
        }
        return false;
    }
    /**
     * Get email by handle.
     *
     * @param string $handle
     * @return Email
     */
    public function getAllEmailsByHandle(string $handle)
    {
        if (!$handle) {
            return null;
        }
        $query = Email::find();
        $query->handle($handle);
        return $query->one();
    }
    /**
     * Get emails by type.
     *
     * @param string $type
     * @return Email[]
     */
    public function getAllEmailsByType(string $type): array
    {
        $emailQuery = Email::find();
        $results = $emailQuery
            ->where(['emaileditor_email.emailType' => $type])
            ->all();
        return $results;
    }
    /**
     * Import emails created by the commerce plugin.
     *
     * @param string $type
     * @return Email[]
     */
    public function importCommerceEmails($commerceEmails)
    {
        foreach ($commerceEmails as $commerceEmail) {
            $check = EmailEditor::$plugin->emails->getAllEmailsByHandle('commerceEmail'.$commerceEmail->id);
            if ($check == null){
                $email = new Email;
                $email->subject = $commerceEmail->subject;
                $email->handle = 'commerceEmail'.$commerceEmail->id;
                $email->enabled = $commerceEmail->enabled;
                $email->emailType = 'commerce';
                $email->title = $commerceEmail->name;
                $email->template = $commerceEmail->templatePath;
                //$email->emailContent = '';
                Craft::$app->elements->saveElement($email);
            }
        }
    }
    /**
     * Send test emails
     * 
     * @param Email
     * @param User
     */
    public function sendTestEmail($user, $email): bool
    {   
		//Create custom variables for the CP test action
		$settings = Craft::$app->systemSettings->getSettings('email');
		
        $variables = [];
        $variables['title'] = $email->title;
        $variables['user'] = $user;
        $variables['settings'] = $settings;
		$variables['link'] = UrlHelper::baseSiteUrl();
		$variables['handle'] = $email->handle;

		if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
			$variables['order'] = Order::find()->isCompleted()->inReverse()->one();
        }

        if (Craft::$app->plugins->isPluginInstalled('insiders') && Craft::$app->plugins->isPluginEnabled('insiders')) {
			$variables['subscription'] = Subscription::find()->inReverse()->one();
        }
        
        //Create and run the send prep service
        $message = new Message(); 
        $message = $this->beforeSendPrep($email,$variables,$message);
        //Check that the email prep was successful
        if ($message == false){   
            return false;
        } else {
            //Set custom properties for test emails
            $message->setFrom([Craft::parseEnv($settings['fromEmail']) => Craft::parseEnv($settings['fromName'])]);
			$message->setTo($user->email);
			$message->variables = $variables;
            Craft::$app->mailer->send($message);
            return true;
        }
    }

    /**
     * Send email function -- takes care of generic intercepting and sending emails
     * from the system and commerce.
     */
    public function beforeSendPrep(Email $email, $variables, $message)
    {
        //Check and set the template mode to site
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        $variables['title'] = $email->title;
        if (Craft::$app->globals->getSetByHandle('email')) {
            $emailFooter = Craft::$app->globals->getSetByHandle('email')->fieldValues['styledBody'];
            $variables['emailFooter'] = $view->renderString($emailFooter, $variables);
        }
        // Craft::dd($variables);
        //Create Subject inc. variables - we do this first to allow for empty body fields, or hardcoded email content.
        try {
            $message->setSubject($view->renderString($email->subject, $variables));
        } catch (\Exception $e) {
            if ($email->emailType == 'commerce'){
                $error = Craft::t('site', 'Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}”', [
                    'email' => $email->title,
                    'order' => $variables['order']->getShortNumber(),
                    'message' => $e->getMessage()
                ]);
            } else {
                $error = Craft::t('email-editor', 'Email template parse error for email “{email}” in “Subject:”. To: “{to}”. Template error: “{message}”', [
                    'email' => $message->key,
                    'to' => $message->getTo(),
                    'message' => $e->getMessage()
				]);
            }
            Craft::error($error, __METHOD__);

            $view->setTemplateMode($oldTemplateMode);

            return false;
        }
        //Create Variables for the template - empty fieldlayoutId no longer stops an email being sent.
        if (!($email->fieldLayoutId == null)){
            $fields = Craft::$app->fields->getLayoutById($email->fieldLayoutId)->getFields();
            foreach ($fields as $field){
                if (get_class($field) == 'craft\\redactor\\Field'){
                    $variables[$field->handle] = Template::raw(Markdown::process($view->renderString($email[$field->handle], $variables)));
                } elseif(get_class($field) == 'benf\\neo\\Field') {
                    $renderedNeo = $email[$field->handle]->all();
                    foreach($renderedNeo as $block){
                        if ($block->styledBody) {
                            $block->styledBody = Template::raw(Markdown::process($view->renderString($block->styledBody, $variables)));
                        }
                    }
                    $variables[$field->handle] = $renderedNeo;
                }
                else {
                    $variables[$field->handle] = $email[$field->handle];
                }
            }
            // if (isset($bodyField) and !empty($email[$bodyField])){ 
            //     $variables[$bodyField] = Template::raw(Markdown::process($view->renderString($email[$bodyField], $variables)));
            // }
        }
        //Create Body inc. variables       
        try {
            $htmlBody = $view->renderTemplate($email->template, $variables);
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            if ($email->emailType == 'commerce'){
                $error = Craft::t('site', 'Email template parse error for email “{email}” in “Body:”. Order: “{order}”. Template error: “{message}”', [
                    'email' => $email->title,
                    'order' => $variables['order']->getShortNumber(),
                    'message' => $e->getMessage()
                ]);
            } else {
                $error = Craft::t('email-editor', 'Email template parse error for email “{email}” in “Body:”. to: “{to}”. Template error: “{message}”', [
					'email' => $message->key,
					'user' => $message->getTo(),
                    'to' => $message->getTo(),
                    'message' => $e->getMessage()
                ]);
            }
            Craft::error($error, __METHOD__);
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }
        //Set template mode as we found it
        $view->setTemplateMode($oldTemplateMode);
        return $message;
    }

    public function updateCommerce($email) {
        $id = preg_replace('/[^0-9]/', '', $email->handle);
        $comEmail = Commerce::getInstance()->getEmails()->getEmailByID($id);
        if ($comEmail) {
            $comEmail->name = $email->title;
            $comEmail->subject = $email->subject;
            $comEmail->templatePath = $email->template;
            $comEmail->enabled = $email->enabled;
            Commerce::getInstance()->getEmails()->saveEmail($comEmail);
        }
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
                'emails.name',
                'emails.subject',
                'emails.handle',
                'emails.emailType',
                'emails.template',
            ])
            ->orderBy('name')
            ->from(['{{%emaileditor_email}} emails']);
    }
}
