<?php
/**
 * EmailEditor plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\emaileditor\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * EmailEditor Install Migration
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Install extends Migration
{
    public $driver;

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    public function safeDown()
    {
        // $emailQuery = Email::find();
        // $emails = $emailQuery->all();
        // foreach ($emails as $email){
        //     Craft::$app->getElements()->deleteElementById($email->id, Email::class);
        // }
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
 
        return true;
    }

    // Protected Methods
    // =========================================================================

    protected function createTables()
    {
        $tablesCreated = false;
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%emaileditor_email}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%emaileditor_email}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'systemMessageKey' => $this->string(255),
                    'subject' => $this->string(255),
                    'testVariables' => $this->longText()
                ]
            );
        }
        return $tablesCreated;
    }

    protected function createIndexes()
    {
        $this->createIndex(null, '{{%emaileditor_email}}', 'id', false);
        $this->createIndex(null, '{{%emaileditor_email}}', 'systemMessageKey', false);

        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%emaileditor_email}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
    }
    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%emaileditor_email}}');
    }
}
