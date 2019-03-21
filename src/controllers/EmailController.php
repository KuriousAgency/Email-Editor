<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\controllers;

use kuriousagency\emaileditor\EmailEditor;
use kuriousagency\emaileditor\elements\Email;

use Craft;
use craft\web\Controller;
use craft\helpers\StringHelper;
use craft\commerce\Plugin as Commerce;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Email Controller
 *
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class EmailController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/email-editor/email
     *
     * @return mixed
     */
    public function actionIndex(): Response
    {
        $layout = Craft::$app->fields->getLayoutByType(Email::class);

		//Import Commerce Emails created since last index load
		if (Craft::$app->plugins->isPluginInstalled('commerce')) {
			$commerceEmails = Commerce::getInstance()->getEmails()->getAllEmails();
            EmailEditor::$plugin->emails->importCommerceEmails($commerceEmails);
		}
        return $this->renderTemplate('email-editor/index');
    }
    /**
     * Handle a request going to our plugin's settings URL,
     *
     * @return mixed
     */
    public function actionSettings(): Response
    {
        $layout = Craft::$app->fields->getLayoutByType(Email::class);
        $emails = EmailEditor::$plugin->emails->getAllEmails();
        $variables['layout'] = $layout;
        $variables['emails'] = $emails;

        return $this->renderTemplate('email-editor/settings', $variables);
    }  
    /**
     * Render edit settings page for specific email by Id if Id provided else create
     * new email
     * 
     *  @return Response
     *  @param int|null $id
     *  @throws HttpsException
     */
    public function actionEditSettings(int $id = null, Email $email = null): Response
    {
        $variables = [
            'email' => $email,
            'id' => $id
        ];
        if (!$variables['email']) {
            if ($variables['id']) {
                $variables['email'] = EmailEditor::$plugin->emails->getEmailById($variables['id']);
                if (!$variables['email']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['email'] = new Email();
            }
        }
        return $this->renderTemplate('email-editor/_edit-settings',$variables);
    }
    /**
     * Render edit content page for specific email by Id 
     * 
     *  @return Response
     *  @param int|null $id
     *  @throws HttpsException
     */
    public function actionEditContent(int $id = null, Email $email = null): Response
    {
        $variables['id'] = $id;
        $variables['email'] = EmailEditor::$plugin->emails->getEmailById($variables['id']);
        if (!$variables['email']) {
            throw new HttpException(404);
        }
        $variables['title'] = $variables['email']->title;
        return $this->renderTemplate('email-editor/_edit-content', $variables);
    }
    
    /**
     * Save edited or new email
     * 
     *  @return null|Response
     *  @throws HttpsException
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('emailId');
        
        if ($id) {
            $email = EmailEditor::$plugin->emails->getEmailById($id);
        } else {
            $email = new Email();
            $handle = StringHelper::toCamelCase($request->getBodyParam('handle',$email->handle));
            if (EmailEditor::$plugin->emails->getEmailByHandle($handle)){
                $response = Craft::t('email-editor', 'An email already exists with the handle: “{handle}”', ['handle' => $handle]);
                return Craft::$app->getSession()->setError($response);
            }
        }
        $email->subject = $request->getBodyParam('subject', $email->subject);
        $email->emailType = $request->getBodyParam('emailType', $email->emailType);
        if (!($email->emailType == 'commerce')) {
            $email->handle = StringHelper::toCamelCase($request->getBodyParam('handle',$email->handle));
        }
        $email->enabled = $request->getBodyParam('enabled', $email->enabled);
        $email->template = $request->getBodyParam('template', $email->template);
        $email->title = $request->getBodyParam('title', $email->title);
        $email->setFieldValuesFromRequest('fields');
        // Save it
        if (Craft::$app->elements->saveElement($email)) {
            $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
            $fieldLayout->type = Email::class . '\\'.$email->handle;
            Craft::$app->getFields()->saveLayout($fieldLayout);
            // $email->fieldLayoutId = $fieldLayout->id;
            if ($email->emailType == 'commerce') {
                EmailEditor::$plugin->emails->updateCommerce($email);
            }
            Craft::$app->getSession()->setNotice('Email saved.');
            return $this->redirectToPostedUrl($email);
        } else {
            Craft::$app->getSession()->setError('Couldn’t save email.');
        }
        // Update commerce emails in commerce

    }
    /**
     * Delete custom emails from the settings page
     * 
     * @throws HttpException
     */
    public function actionDelete($id = null): Response
    {
        $this->requireLogin();
        if ($id == null){
            $this->requirePostRequest();
            $this->requireAcceptsJson();
            $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        }
        if (EmailEditor::$plugin->emails->deleteEmailById($id)) {
            Craft::$app->getSession()->setNotice('Email Deleted.');
        } else {
            Craft::$app->getSession()->setError('Couldn’t delete email.');
        }
        return $this->asJson(['success' => true]);
    }

    /**
    * Send test email from the email listing page
     */
    public function actionSend($id)
    {
        $this->requireLogin();
        $user = Craft::$app->getUser()->getIdentity(); 
        $email = EmailEditor::$plugin->emails->getEmailById($id); 
        $sent = EmailEditor::$plugin->emails->sendTestEmail($user,$email);
        if ($sent) {
            Craft::$app->getSession()->setNotice($email->title . " sent successfully");
        } else {
            Craft::$app->getSession()->setNotice("Unable to send email");
        };
        return $this->redirect('email-editor');
    }

}
