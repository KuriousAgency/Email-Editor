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

use Craft;
use craft\web\Controller;
use craft\elements\Entry;

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

    public function actionSend($id)
    {
        $this->requireLogin();
        $user = Craft::$app->getUser()->getIdentity(); 
        $sent = EmailEditor::$plugin->emails->sendTestEmail($user,$id);
        if ($sent) {
            Craft::$app->getSession()->setNotice("Email sent successfully");
        } else {
            Craft::$app->getSession()->setNotice("Unable to send email");
        };
        $returnUrl = Entry::find()->id($id)->one()->cpEditUrl;
        return $this->redirect($returnUrl);
    }

}
