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
use craft\mail\Message;
use craft\commerce\Plugin as Commerce;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Email Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class EmailController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something'];

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

        //Import Commerce Emails
        $commerce = Craft::$app->plugins->getPlugin('commerce');
        if ($commerce) {
            $commerceEmails = Commerce::getInstance()->getEmails()->getAllEmails();
            //Craft::dd($commerceEmails);
            foreach ($commerceEmails as $commerceEmail) {
                $check = EmailEditor::$plugin->emails->getAllEmailByHandle('commerceEmail'.$commerceEmail->id);
                if ($check == null){
                    $email = new Email;
                    $email->subject = $commerceEmail->subject;
                    $email->handle = 'commerceEmail'.$commerceEmail->id;
                    $email->enabled = $commerceEmail->enabled;
                    $email->emailType = 'commerce';
                    $email->title = $commerceEmail->name;
                    $email->template = $commerceEmail->templatePath;
                    $email->emailContent = '';
                    Craft::$app->elements->saveElement($email);
                }
            }
        }
        return $this->renderTemplate('email-editor/index');
    }

    public function actionSettings()
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
        $plugin = EmailEditor::getInstance();

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

    public function actionSaveLayout(){
        $this->requirePostRequest();
        //$request = Craft::$app->getRequest();
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Email::class;
        //Craft::dd($fieldLayout);
        if (!($fieldLayout->id == null)) {
            if (Craft::$app->getFields()->saveLayout($fieldLayout)){
                Craft::$app->getSession()->setNotice('Layout Saved.');
                return $this->redirectToPostedUrl();
            } else {
                Craft::$app->getSession()->setError('Layout Not Saved');
            }
            //$email->fieldLayoutId = $fieldLayout->id;
        }
        
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
        }

        // Shared attributes
        // $fields = $request->getBodyParam('fields');

        $email->subject = $request->getBodyParam('subject', $email->subject);
        $email->emailType = $request->getBodyParam('emailType', $email->emailType);
        if (!($email->emailType == 'commerce')) {
            $email->handle = lcfirst(str_replace(' ','',ucwords($request->getBodyParam('title',$email->handle))));
        }
        $email->enabled = $request->getBodyParam('enabled', $email->enabled);
        $email->template = $request->getBodyParam('template', $email->template);
        $email->title = $request->getBodyParam('title', $email->title);
        $email->setFieldValuesFromRequest('fields');
        
        // Set the email field layout
        // $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        // $fieldLayout->type = Email::class;
        // //Craft::dd($fieldLayout);
        // if (!($fieldLayout->id == null)) {
        //     Craft::$app->getFields()->saveLayout($fieldLayout);
        //     $email->fieldLayoutId = $fieldLayout->id;
        // } else {
            
        // }
        
        // $check = EmailEditor::$plugin->emails->getAllEmailByHandle($email->handle);
        // if (!($check == null)) {
        //     Craft::$app->getSession()->setError('Couldn’t save email. Duplicate Handle');
        // } else {
        
            // Save it
            if (Craft::$app->elements->saveElement($email)) {
                Craft::$app->getSession()->setNotice('Email saved.');
                return $this->redirectToPostedUrl($email);
            } else {
                Craft::$app->getSession()->setError('Couldn’t save email.');
            }
        // }

        // Send the model back to the template
        //Craft::$app->getUrlManager()->setRouteParams(['email' => $email]);
    }
    /**
     * @throws HttpException
     */
    public function actionDelete($id = null): Response
    {
        // $this->requirePostRequest();
        // $this->requireAcceptsJson();
        $this->requireLogin();

        if ($id == null){
            $this->requirePostRequest();
            $this->requireAcceptsJson();
            $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        }
        if (EmailEditor::$plugin->emails->deleteEmailById($id)) {
            Craft::$app->getSession()->setNotice('Email Deleted.');
            //return $this->redirectToPostedUrl($email);
        } else {
            Craft::$app->getSession()->setError('Couldn’t delete email.');
        }
        return $this->asJson(['success' => true]);
    }

    /**
    * Send test email
     */
    public function actionSend($id)
    {
        $this->requireLogin();
        // $id = Craft::$app->getRequest()->getBodyParam('id');
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
