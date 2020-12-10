<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\models;

use Craft;
use craft\base\Model;

/**
 * Email Model
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     2.0.0
 */
class Email extends Model
{
    public $id = null;
    public $systemMessageKey;
    public $subject;
    public $testVariables;

    public function rules()
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['systemMessageKey','subject','testVariables'],'string']
        ];
    }
}