<?php
namespace kuriousagency\emaileditor\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use kuriousagency\emaileditor\elements\Email;

class EmailQuery extends ElementQuery
{
    public $subject;
    public $template;
    public $handle;
    public $emailType;

    public function subject($value)
    {
        $this->subject = $value;

        return $this;
    }

    public function template($value)
    {
        $this->template = $value;

        return $this;
    }

    public function handle($value)
    {
        $this->handle = $value;

        return $this;
    }

    public function emailType($value)
    {
        $this->emailType = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        // join in the emaileditor table
        $this->joinElementTable('emaileditor_email');

        // select the columns
        $this->query->select([
            'emaileditor_email.subject',
            'emaileditor_email.template',
            'emaileditor_email.handle',
            'emaileditor_email.emailType',
        ]);

        if ($this->subject) {
            $this->subQuery->andWhere(Db::parseParam('emaileditor_email.subject', $this->subject));
        }

        if ($this->template) {
            $this->subQuery->andWhere(Db::parseParam('emaileditor_email.template', $this->template));
        }

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('emaileditor_email.handle', $this->handle));
        }

        if ($this->emailType) {
            $this->subQuery->andWhere(Db::parseParam('emaileditor_email.emailType', $this->emailType));
        }

        return parent::beforePrepare();
    }
}