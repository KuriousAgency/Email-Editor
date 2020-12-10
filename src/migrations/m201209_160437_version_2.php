<?php

namespace kuriousagency\emaileditor\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m201209_160437_version_2 migration.
 */
class m201209_160437_version_2 extends Migration
{
    public function safeUp()
    {
        $this->dropColumn('{{%emaileditor_email}}','template');
        $this->dropColumn('{{%emaileditor_email}}','emailType');
        $this->dropColumn('{{%emaileditor_email}}','handle');
        MigrationHelper::dropForeignKeyIfExists('{{%emaileditor_email}}', ['id'], $this);
        $this->addColumn('{{%emaileditor_email}}','systemMessageKey',$this->string(255));
        $this->addColumn('{{%emaileditor_email}}','testVariables',$this->longText());
        $this->addForeignKey(null, '{{%emaileditor_email}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);

        return true;
    }

    public function safeDown()
    {
        echo "m201209_160437_version_2 cannot be reverted.\n";
        return false;
    }
}
