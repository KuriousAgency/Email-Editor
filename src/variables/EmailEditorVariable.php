<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\variables;

use kuriousagency\emaileditor\EmailEditor;

use Craft;
use yii\base\Behavior;
use yii\di\ServiceLocator;

/**
 * EmailEditor Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.emailEditor }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class EmailEditorVariable
{
    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
		$components = EmailEditor::$plugin->components;
		unset($components['migrator']);
        $config['components'] = $components;
        parent::__construct($config);
	}
}
