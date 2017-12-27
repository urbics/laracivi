<?php
namespace Urbics\Laracivi\Console\Migrations;

class SchemaParser
{
    /**
     * The parsed schema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * Parse the XML DOM schema
     * Returned array:
     * [create => [
     *     name => 'name',
     *     options => [],
     *     fields => [
     *         name => 'name',
     *         type => 'type',
     *         arguments => '(100)' | '(2, 3)',
     *         options -> [],
     *     ],
     * [add => [...]]);
     *
     * @param  SimpleXMLElement $schema
     * @return array
     */
    public function parse($xmlSchema)
    {
        $this->schema = ['create' => [], 'add' => []];
        foreach ($xmlSchema->tables as $tableGroup) {
            foreach ($tableGroup as $table) {
                $name = $this->value('name', $table);
                $this->schema['create'][$name] = [
                    'name' => $name,
                    'type' => 'table',
                    'drop' => $this->value('drop', $table),
                    'process_order' => $this->processOrder($name),
                    'arguments' => null,
                    'options' => ["comment('" . addcslashes(($this->value('comment', $table) ?: ''), "\\'") . "')"],
                    ''
                ];
                $this->schema['update'][$name] = $this->schema['create'][$name];
                $fields = $this->getFields($table);
                $indices = $this->getIndices($table);
                $this->schema['create'][$name]['fields'] = array_merge($fields, $indices);
                $this->schema['update'][$name]['fields'] = $this->getForeignKeys($table);
            }
        }
        return $this->schema;
    }

    protected function getFields($tableXml)
    {
        $fields = array();
        foreach ($tableXml->field as $values) {
            if (!empty($this->getField('drop', $values))) {
                continue;
            }
            $name = $this->getField('name', $values);
            $type = $this->getField('type', $values);
            $length = $this->getField('length', $values);
            $default = $this->getField('default', $values);
            if (is_bool($default)) {
                $default = $default === true ? 1 : 0;
            }
            $nullable = $this->getField('nullable', $values);
            if (empty($nullable) and ($this->getField('required', $values) == 'false')) {
                $nullable = 'nullable';
            }
            if (!empty($nullable) and $name == 'deleted_at') {
                $nullable = false;
                $type = 'softDeletes';
            }

            $comment = $this->getField('comment', $values);
            $unsigned = $this->getField('unsigned', $values);
            if (empty($unsigned) and $type == 'int unsigned') {
                $unsigned = 'unsigned';
            }
            $precision = $this->getField('precision', $values);
            $scale = $this->getField('scale', $values);
            $decorators = null;
            $args = null;

            $typeConverter = [
                'tinyInteger'  => 'integer',
                'int'          => 'integer',
                'int unsigned' => 'integer',
                'varchar'      => 'string',
                'blob'         => 'binary',
                'mediumblob'   => 'binary',
                'tinyblob'     => 'binary',
                'longblob'     => 'binary',
            ];

            $type = isset($typeConverter[$type]) ?  $typeConverter[$type] : $type;

            if ($tableXml->primaryKey->name == $name and isset($tableXml->primaryKey->autoincrement)) {
                $type = 'increments';
            }
            if ($tableXml->primaryKey->name == $name and !isset($tableXml->primaryKey->autoincrement)) {
                $decorators['primary'] = "'" . $name . "'";
            }

            if (in_array($type, ['decimal', 'float', 'double'])) {
                // Precision based numbers
                $args = $this->getPrecision($precision, $scale);
            }
            if (in_array($type, ['string', 'char'])) {
                $args = $this->getLength($length);
            }

            if ($nullable) {
                $decorators['nullable'] = true;
            }
            if ($unsigned) {
                $decorators['unsigned'] = true;
            }
            if ($default == 'CURRENT_TIMESTAMP') {
                $decorators['useCurrent'] = true;
                $default = null;
            }
            if ($default == 'NULL') {
                $decorators['nullable'] = true;
                $default = null;
            }
            if ($default !== null) {
                $decorators['default'] = $default;
                // $decorators['default'] = "'" . addcslashes($default, "\\'") . "'";
            }
            if ($comment) {
                $decorators['comment'] = "'" . addcslashes($comment, "\\'") . "'";
            }
            $field = ['name' => trim($name), 'type' => $type];

            $field['options'] = $decorators ?: null;

            $field['arguments'] = $args ?: null;

            $fields[$name] = $field;
        }
        /* Timestamps are added to all migrations in the template. */
        unset($fields['created_date']);
        unset($fields['modified_date']);

        return $fields;
    }

    protected function getIndices($tableXml)
    {
        $indices = array();
        foreach ($tableXml->index as $values) {
            if (!empty($this->getField('drop', $values))) {
                continue;
            }
            $name = $this->getField('name', $values);
            $type = $this->getField('unique', $values) ? 'unique' : 'index';
            $fields = '';
            foreach ($values->fieldName as $key => $fieldName) {
                $fields .= ($fields ? ', ' : '[');
                $fields .= "'" . (string) $fieldName . "'";
            }
            if ($fields) {
                $fields .= ']';
            }
            if (!$name) {
                $name = 'index_' . implode('_', $fields);
            }
            if ($name == $this->getField('fieldName', $values)) {
                $name = "index_$name";
            }
            $indices[$name] = [
                'name' => $name,
                'type' => $type,
                'arguments' => $fields,
                'options' => null,
            ];
        }

        return $indices;
    }

    protected function getForeignKeys($tableXml)
    {
        $constraints = array();
        foreach ($tableXml->foreignKey as $values) {
            if (!empty($this->getField('drop', $values))) {
                continue;
            }
            $name = $this->getField('name', $values);
            $fields = "'" . $name . "'";
            $type = 'foreign';
            $table = $this->getField('table', $values);
            $key = $this->getField('key', $values);
            $onDelete = $this->getField('onDelete', $values);
            $onUpdate = ($this->getField('onUpdate', $values) ?: 'restrict');
            $name = 'fk_'
                . crc32($tableXml->name)
                . '_' . camel_case(str_replace('civicrm_', '', $table))
                . '_' . $name;
            $constraints[$name] = [
                'name' => $name,
                'type' => 'foreign',
                'arguments' => $fields,
                'options' => [
                    'references' => "'" . $key ."'",
                    'on' => "'" . $table . "'",
                ],
            ];
            if ($onUpdate) {
                $constraints[$name]['options']['onUpdate'] = "'" . $onUpdate . "'";
            }
            if ($onDelete) {
                $constraints[$name]['options']['onDelete'] = "'" . $onDelete . "'";
            }
        }

        return $constraints;
    }

    /**
     * Generates appropriate value for a given field attribute.
     *
     * @param  string $key
     * @param  SimpleXMLElement $value
     * @return string
     */
    protected function getField($key, $value)
    {
        $result = $this->value($key, $value);
        if ($key == 'nullable' and empty($this->value('required', $value))) {
            return 'nullable';
        }
        if ($key == 'unsigned' and !empty($this->value('type', $value))) {
            if ($this->value('type', $value) == 'int unsigned') {
                return 'unsigned';
            }
        }

        return $result;
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return string|void
     */
    protected function getPrecision($precision, $scale)
    {
        if (!$precision) {
            return '';
        }
        if ($precision != 8 or $scale != 2) {
            $result = $precision;
            if ($scale != 2) {
                $result .= ', ' . $scale;
            }
            return $result;
        }
    }
    /**
     * @param int $length
     * @return int|void
     */
    protected function getLength($length)
    {
        if ($length and $length !== 255) {
            return $length;
        }
    }

    /**
     * @param string $default
     * @param string $type
     * @return string
     */
    protected function getDefault($default, &$type)
    {
        if (in_array($default, ['CURRENT_TIMESTAMP'], true)) {
            if ($type == 'dateTime') {
                $type = 'timestamp';
            }
            $default = $this->decorate('DB::raw', $default);
        } elseif (in_array($type, ['string', 'text']) or !is_numeric($default)) {
            $default = $this->argsToString($default);
        }
        return $this->decorate('default', $default, '');
    }

    /**
     * @param string|array $args
     * @param string       $quotes
     * @return string
     */
    protected function argsToString($args, $quotes = '\'')
    {
        if (is_array($args)) {
            $separator = $quotes .', '. $quotes;
            $args = implode($separator, str_replace($quotes, '\\'.$quotes, $args));
        } else {
            $args = str_replace($quotes, '\\'.$quotes, $args);
        }

        return $quotes . $args . $quotes;
    }

    /**
     * Get Decorator
     * @param string       $function
     * @param string|array $args
     * @param string       $quotes
     * @return string
     */
    protected function decorate($function, $args, $quotes = '\'')
    {
        if (! is_null($args)) {
            $args = $this->argsToString($args, $quotes);
            return $function . '(' . $args . ')';
        } else {
            return $function;
        }
    }
    /**
    * @param $key
    * @param $object
    * @param null $default
    *
    * @return null|string
    */
    protected function value($key, &$object, $default = null)
    {
        if (isset($object->$key)) {
            return (string ) $object->$key;
        }
        return $default;
    }

    /**
     * Add a field to the schema array.
     *
     * @param  array $field
     * @return $this
     */
    private function addField($field)
    {
        $this->schema[] = $field;

        return $this;
    }

    /**
     * Get an array of fields from the given schema.
     *
     * @param  string $schema
     * @return array
     */
    private function splitIntoFields($schema)
    {
        return preg_split('/,\s?(?![^()]*\))/', $schema);
    }

    /**
     * Get the segments of the schema field.
     *
     * @param  string $field
     * @return array
     */
    private function parseSegments($field)
    {
        $segments = explode(':', $field);

        $name = array_shift($segments);
        $type = array_shift($segments);
        $arguments = [];
        $options = $this->parseOptions($segments);

        // Do we have arguments being used here?
        // Like: string(100)
        if (preg_match('/(.+?)\(([^)]+)\)/', $type, $matches)) {
            $type = $matches[1];
            $arguments = explode(',', $matches[2]);
        }

        return compact('name', 'type', 'arguments', 'options');
    }

    /**
     * Parse any given options into something usable.
     *
     * @param  array $options
     * @return array
     */
    private function parseOptions($options)
    {
        if (empty($options)) return [];

        foreach ($options as $option) {
            if (str_contains($option, '(')) {
                preg_match('/([a-z]+)\(([^\)]+)\)/i', $option, $matches);

                $results[$matches[1]] = $matches[2];
            } else {
                $results[$option] = true;
            }
        }

        return $results;
    }

    /**
     * Add a foreign constraint field to the schema.
     *
     * @param array $segments
     */
    private function addForeignConstraint($segments)
    {
        $string = sprintf(
            "%s:foreign:references('id'):on('%s')",
            $segments['name'],
            $this->getTableNameFromForeignKey($segments['name'])
        );

        $this->addField($this->parseSegments($string));
    }

    /**
     * Try to figure out the name of a table from a foreign key.
     * Ex: user_id => users
     *
     * @param  string $key
     * @return string
     */
    private function getTableNameFromForeignKey($key)
    {
        return str_plural(str_replace('_id', '', $key));
    }

    /**
     * Determine if the user wants a foreign constraint for the field.
     *
     * @param  array $segments
     * @return bool
     */
    private function fieldNeedsForeignConstraint($segments)
    {
        return array_key_exists('foreign', $segments['options']);
    }

    protected function processOrder($name)
    {
        $tables = [
            'civicrm_acl',
            'civicrm_contact',
            'civicrm_action_schedule',
            'civicrm_group',
            'civicrm_msg_template',
            'civicrm_sms_provider',
            'civicrm_campaign',
            'civicrm_phone',
            'civicrm_relationship',
            'civicrm_activity',
            'civicrm_country',
            'civicrm_county',
            'civicrm_state_province',
            'civicrm_saved_search',
            'civicrm_component',
            'civicrm_case_type',
            'civicrm_case',
            'civicrm_address',
            'civicrm_contribution_page',
            'civicrm_contribution_recur',
            'civicrm_financial_type',
            'civicrm_contribution',
            'civicrm_payment_processor',
            'civicrm_payment_token',
            'civicrm_pcp',
            'civicrm_address_format',
            'civicrm_worldregion',
            'civicrm_custom_group',
            'civicrm_domain',
            'civicrm_dashboard',
            'civicrm_dedupe_rule_group',
            'civicrm_price_set',
            'civicrm_batch',
            'civicrm_file',
            'civicrm_financial_account',
            'civicrm_financial_trxn',
            'civicrm_tag',
            'civicrm_loc_block',
            'civicrm_event_carts',
            'civicrm_event',
            'civicrm_email',
            'civicrm_price_field',
            'civicrm_price_field_value',
            'civicrm_im',
            'civicrm_mailing_component',
            'civicrm_location_type',
            'civicrm_mailing_bounce_type',
            'civicrm_mailing_event_queue',
            'civicrm_mailing_event_subscribe',
            'civicrm_mailing_job',
            'civicrm_mailing_trackable_url',
            'civicrm_mailing',
            'civicrm_mapping',
            'civicrm_relationship_type',
            'civicrm_membership_type',
            'civicrm_membership_status',
            'civicrm_membership',
            'civicrm_option_group',
            'civicrm_discount',
            'civicrm_participant_status_type',
            'civicrm_participant',
            'civicrm_payment_processor_type',
            'civicrm_uf_group',
            'civicrm_pledge',
            'civicrm_premiums',
            'civicrm_product',
            'civicrm_navigation',
            'civicrm_acl_cache',
            'civicrm_acl_contact_cache',
            'civicrm_acl_entity_role',
            'civicrm_action_log',
            'civicrm_action_mapping',
            'civicrm_activity_contact',
            'civicrm_cache',
            'civicrm_campaign_group',
            'civicrm_case_activity',
            'civicrm_case_contact',
            'civicrm_contact_type',
            'civicrm_contribution_product',
            'civicrm_contribution_soft',
            'civicrm_contribution_widget',
            'civicrm_currency',
            'civicrm_custom_field',
            'civicrm_cxn',
            'civicrm_dashboard_contact',
            'civicrm_dedupe_exception',
            'civicrm_dedupe_rule',
            'civicrm_entity_batch',
            'civicrm_entity_file',
            'civicrm_entity_financial_account',
            'civicrm_entity_financial_trxn',
            'civicrm_entity_tag',
            'civicrm_events_in_carts',
            'civicrm_extension',
            'civicrm_financial_item',
            'civicrm_grant',
            'civicrm_group_contact',
            'civicrm_group_contact_cache',
            'civicrm_group_nesting',
            'civicrm_group_organization',
            'civicrm_job',
            'civicrm_job_log',
            'civicrm_line_item',
            'civicrm_log',
            'civicrm_mail_settings',
            'civicrm_mailing_abtest',
            'civicrm_mailing_bounce_pattern',
            'civicrm_mailing_event_bounce',
            'civicrm_mailing_event_confirm',
            'civicrm_mailing_event_delivered',
            'civicrm_mailing_event_forward',
            'civicrm_mailing_event_opened',
            'civicrm_mailing_event_reply',
            'civicrm_mailing_event_trackable_url_open',
            'civicrm_mailing_event_unsubscribe',
            'civicrm_mailing_group',
            'civicrm_mailing_recipients',
            'civicrm_mailing_spool',
            'civicrm_managed',
            'civicrm_mapping_field',
            'civicrm_membership_block',
            'civicrm_membership_log',
            'civicrm_membership_payment',
            'civicrm_menu',
            'civicrm_note',
            'civicrm_openid',
            'civicrm_option_value',
            'civicrm_participant_payment',
            'civicrm_pcp_block',
            'civicrm_persistent',
            'civicrm_pledge_block',
            'civicrm_pledge_payment',
            'civicrm_preferences_date',
            'civicrm_premiums_product',
            'civicrm_prevnext_cache',
            'civicrm_price_set_entity',
            'civicrm_print_label',
            'civicrm_queue_item',
            'civicrm_recurring_entity',
            'civicrm_report_instance',
            'civicrm_setting',
            'civicrm_status_pref',
            'civicrm_subscription_history',
            'civicrm_survey',
            'civicrm_system_log',
            'civicrm_tell_friend',
            'civicrm_timezone',
            'civicrm_uf_field',
            'civicrm_uf_join',
            'civicrm_uf_match',
            'civicrm_website',
            'civicrm_word_replacement',
        ];

        return (array_search($name, $tables) ?: 200);
    }
}

