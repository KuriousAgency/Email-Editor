<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\records;

use kuriousagency\emaileditor\EmailEditor;

use Craft;
use craft\db\ActiveRecord;

/**
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Email extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%emaileditor_email}}';
    }
}
