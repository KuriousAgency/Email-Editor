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

use kuriousagency\emaileditor\EmailEditor;
use kuriousagency\emaileditor\elements\Email;
use kuriousagency\emaileditor\records\Email as EmailRecord;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\db\Query;
use craft\records\FieldLayout;
use craft\records\Element;
use craft\records\Site;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;



/**
 * EmailEditor Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Kurious Agency
 * @package   EmailEditor
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    private $_emailFieldLayoutId;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            //$this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $emailQuery = Email::find();
        $emails = $emailQuery->all();
        foreach ($emails as $email){
            Craft::$app->getElements()->deleteElementById($email->id, Email::class);
        }
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
 
        $this->delete('{{%elements}}', ['type' => [Email::class]]);

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

    // emaileditor_email table
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
                // Custom columns in the table
                    'subject' => $this->string(255)->notNull()->defaultValue(''),
                    'template' => $this->string(255)->notNull()->defaultValue(''),
                    //'name' => $this->string(255)->notNull()->defaultValue(''),
                    'handle' => $this->string(255)->notNull()->defaultValue(''),
                    'emailType' => $this->enum('type',['system','commerce','custom']),
                    //'fieldLayoutId' => $this->integer(),
                    //'enabled' => $this->boolean()->defaultValue(false),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {
    // emaileditor_email table
        $this->createIndex(
            $this->db->getIndexName(
                '{{%emaileditor_email}}',
                'handle',
                true
            ),
            '{{%emaileditor_email}}',
            'handle',
            true
        );
        $this->createIndex(null, '{{%emaileditor_email}}', 'id', false);
        
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
    // emaileditor_email table
        
        //$this->addForeignKey(null, '{{%emaileditor_email}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], null);
        $this->addForeignKey(null, '{{%emaileditor_email}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        //$this->addForeignKey(null, '{{%emaileditor_email}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');

        
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    // protected function insertDefaultData()
    // {
    //     $emails = [
    //         ['subject' => 'Test Email 1 Subject',
    //             'template' => '_email/custom',
    //             'name' => 'Test Email 1',
    //             'handle' => 'testEmail1',
    //             'emailType' => 'custom',
    //             'content' => 'Test Email Content 1',
    //             'promo' => 0,
    //             'siteId' => 1],
    //         ['subject' => 'Test Email 2 Subject',
    //             'template' => '_email/custom',
    //             'name' => 'Test Email 2',
    //             'handle' => 'testEmail2',
    //             'emailType' => 'custom',
    //             'content' => 'Test Email Content 2',
    //             'promo' => 0,
    //             'siteId' => 1],
            
    //     ];

    //     foreach ($emails as $email) {

    //         $todaysDateTime = DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s');
    //         // Create an element for product
    //         $emailElementData = [
    //             'type' => Email::class,
    //             'enabled' => 1,
    //             'archived' => 0,
    //             'dateCreated' => $todaysDateTime,
    //             'dateUpdated' => $todaysDateTime,
    //             //'fieldLayoutId' => $this->db->getLastInsertID(FieldLayout::tableName()),
    //         ];
    //         $this->insert(Element::tableName(), $emailElementData);
    //         $emailId = $this->db->getLastInsertID(Element::tableName());
    //         //This doesn't work
    //         $siteId = (new Query())
    //             ->select('id')
    //             ->from(Site::tableName())
    //             ->column();

    //         $contentData = [
    //             'elementId' => $emailId,
    //             'siteId' => $siteId,
    //             'title' => StringHelper::toTitleCase($email['name']),
    //             'field_emailContent' => $email['content'],
    //             'field_emailPromo' => $email['promo'],
    //         ];
    //         $this->insert('{{%content}}', $contentData);
    //         //This is a fudge
    //         $elementSitesData =[
    //             'elementId' => $emailId,
    //             'siteId' => $email['siteId'],
    //             'dateCreated' => $todaysDateTime,
    //             'dateUpdated' => $todaysDateTime,
    //         ];

    //         $this->insert('{{%elements_sites}}', $elementSitesData);


    //         $emailData = [
    //             'id' => $emailId,
    //             'subject' => $email['subject'],
    //             'template' => $email['template'],
    //             'handle' => $email['handle'],
    //             'emailType' => $email['emailType'],
    //             //Make this dynamic
    //             //'siteId' => $email['siteId'],
    //         ];

    //         // Insert the actual email
    //         $this->insert(EmailRecord::tableName(), $emailData);
    //     }
    // }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
    // emaileditor_email table
        $this->dropTableIfExists('{{%emaileditor_email}}');
    }
}
