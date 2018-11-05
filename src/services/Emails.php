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
use craft\web\View;
use craft\db\Query;
use craft\helpers\Template;
use yii\helpers\Markdown;
use craft\commerce\elements\Order;

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
     * Get all emails.
     *
     * @return Email[]
     */
    public function getAllEmails(): array
    {
        // $rows = $this->_createEmailQuery()->all();

        // $emails = [];
        // foreach ($rows as $row) {
        //     $emails[] = new Email($row);
        // }

        $emailQuery = Email::find();
        $emails = $emailQuery->all();

        return $emails;
    }

    /**
     * Save an email.
     *
     * @param Email $model
     * @return bool
     * @throws \Exception
     */
    public function saveEmail(Email $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = EmailRecord::findOne($model->id);
            if (!$record) {
                throw new Exception(Craft::t('email-editor', 'No email exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new EmailRecord();
        }

        // if ($runValidation && !$model->validate()) {
        //     Craft::info('Email not saved due to validation error(s).', __METHOD__);

        //     return false;
        // }

        $record->name = $model->name;
        $record->subject = $model->subject;
        $record->type = $model->type;
        $record->handle = $model->handle;
        //$record->enabled = $model->enabled;
        $record->template = $model->template;
  

        //$fieldLayout = $model->getFieldLayout();
        //Craft::$app->getFields()->saveLayout($fieldLayout);
        //$model->fieldLayoutId = $fieldLayout->id;
        //$record->fieldLayoutId = $fieldLayout->id;

        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
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
    public function getAllEmailByHandle(string $handle)
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
    public function getAllEmailByType(string $type): array
    {
        $emailQuery = Email::find();
        $results = $emailQuery
            
            ->where(['emaileditor_email.emailType' => $type])
            ->all();

        // $emails = [];
        
        
        // foreach ($results as $row) {
        //     $emails[] = new Email($row);
        // }
        // Craft::dd($results);
        return $results;
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
        $variables['user'] = $user;
        $variables['settings'] = $settings;
		$variables['link'] = '<a href="https://plugin.test/admin">My Account</a>';

		if (Craft::$app->plugins->isPluginInstalled('commerce')) {
			$variables['order'] = Order::find()->inReverse()->one();
		}
        
        //Create and run the send prep service
        $message = new Message(); 
		$message = $this->beforeSendPrep($email,$variables,$message);
        
        //Check that the email prep was successful
        if ($message == false){   
            return false;
        } else {
            //Set custom properties for test emails
            $message->setFrom([$settings['fromEmail'] => $settings['fromName']]);
            $message->setTo($user->email);
            Craft::$app->mailer->send($message);
            //Send the Email
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

        //Create Variables for the template
        $fields = Craft::$app->fields->getLayoutById($email->fieldLayoutId)->getFields();
        foreach ($fields as $field){
            $variables[$field->handle] = $email[$field->handle];
        } 
        //Create Subject inc. variables
        try {
            $message->setSubject($view->renderString($email->subject, $variables));
        } catch (\Exception $e) {
            if ($email->emailType == 'commerce'){
                $error = Craft::t('commerce', 'Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}”', [
                    'email' => $email->title,
                    'order' => $variables['order']->getShortNumber(),
                    'message' => $e->getMessage()
                ]);
            } else {
                $error = Craft::t('email-editor', 'Email template parse error for email “{email}” in “Subject:”. To: “{to}”. Template error: “{message}”', [
                    'email' => $message->key,
                    'to' => $message->getTo(),
                    'message' => $event->getMessage()
				]);
				//Craft::dd($error);
            }
            Craft::error($error, __METHOD__);

            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        //Create Body inc. variables       
        try {
            if ($email->emailContent == null){
                $textBody = '';
            } else {
                $textBody = $view->renderString($email->emailContent, $variables);
            }
            $message->setTextBody($textBody);
            
            $htmlBody = $view->renderTemplate($email->template, array_merge($variables, [
                'emailContent' => Template::raw(Markdown::process($textBody)),
            ]));
            $message->setHtmlBody($htmlBody);

        } catch (\Exception $e) {
            if ($email->emailType == 'commerce'){
                $error = Craft::t('commerce', 'Email template parse error for email “{email}” in “Body:”. Order: “{order}”. Template error: “{message}”', [
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
				Craft::dd($error);
            }
            Craft::error($error, __METHOD__);

            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        //Set template mode as we found it
        $view->setTemplateMode($oldTemplateMode);

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
                'emails.name',
                'emails.subject',
                //'emails.enabled',
                'emails.handle',
                'emails.emailType',
                'emails.template',
                //'emails.fieldLayoutId'
            ])
            ->orderBy('name')
            ->from(['{{%emaileditor_email}} emails']);
    }

//     /**
//      * Returns a Query object prepped for retrieving Emails.
//      *
//      * @return Query
//      */
//     private function _createCommerceEmailQuery(): Query
//     {
//         return (new Query())
//             ->select([
//                 'emails.id',
//                 'emails.name',
//                 'emails.subject',
//                 'emails.recipientType',
//                 'emails.to',
//                 'emails.bcc',
//                 'emails.enabled',
//                 'emails.templatePath',
//                 'emails.attachPdf',
//                 'emails.pdfTemplatePath'
//             ])
//             ->orderBy('name')
//             ->from(['{{%commerce_emails}} emails']);
//     }
// }
}
