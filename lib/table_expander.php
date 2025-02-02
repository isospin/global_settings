<?php

class rex_global_settings_table_expander extends rex_form
{
    private $metaPrefix;
    private $tableManager;

    public function __construct($metaPrefix, $metaTable, $tableName, $whereCondition, $method = 'post', $debug = false)
    {
        $this->metaPrefix = $metaPrefix;
        $this->tableManager = new rex_global_settings_table_manager($metaTable);

        parent::__construct($tableName, rex_i18n::msg('global_settings_field_fieldset'), $whereCondition, $method, $debug);
    }

    public function init()
    {
        // ----- EXTENSION POINT
        // IDs aller Feldtypen bei denen das Parameter-Feld eingeblendet werden soll
        $typeFields = rex_extension::registerPoint(new rex_extension_point('GLOBAL_SETTINGS_TYPE_FIELDS', [REX_GLOBAL_SETTINGS_FIELD_SELECT, REX_GLOBAL_SETTINGS_FIELD_RADIO, REX_GLOBAL_SETTINGS_FIELD_CHECKBOX, REX_GLOBAL_SETTINGS_FIELD_REX_MEDIA_WIDGET, REX_GLOBAL_SETTINGS_FIELD_REX_MEDIALIST_WIDGET, REX_GLOBAL_SETTINGS_FIELD_REX_LINK_WIDGET, REX_GLOBAL_SETTINGS_FIELD_REX_LINKLIST_WIDGET]));

        $field = $this->addTextField('name');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_name'));
		$field->setAttribute('id', 'global-settings-name-field');

        $field = $this->addSelectField('priority');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_priority'));
        $select = $field->getSelect();
        $select->setSize(1);
        $select->addOption(rex_i18n::msg('global_settings_field_first_priority'), 1);
        // Im Edit Mode das Feld selbst nicht als Position einf�gen
        $qry = 'SELECT name,priority FROM ' . $this->tableName . ' WHERE `name` LIKE "' . $this->metaPrefix . '%"';
        if ($this->isEditMode()) {
            $qry .= ' AND id != ' . $this->getParam('field_id');
        }
        $qry .= ' ORDER BY priority';
        $sql = rex_sql::factory();
        $sql->setQuery($qry);
        $value = 1;
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $value = $sql->getValue('priority') + 1;
            $select->addOption(
                rex_i18n::rawMsg('global_settings_field_after_priority', rex_global_settings::getStrippedField($sql->getValue('name'))),
                $value
            );
            $sql->next();
        }
        if (!$this->isEditMode()) {
            $select->setSelected($value);
        }

        $field = $this->addTextField('title');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_title'));
        $field->setNotice(rex_i18n::msg('global_settings_field_notice_title'));

        $field = $this->addTextField('notice');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_note'));

        $gq = rex_sql::factory();
        $gq->setQuery('SELECT dbtype,id FROM ' . rex::getTablePrefix() . 'global_settings_type');
        $textFields = [];
        foreach ($gq->getArray() as $f) {
            if ($f['dbtype'] == 'text') {
                $textFields[$f['id']] = $f['id'];
            }
        }

        $field = $this->addSelectField('type_id');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_type'));
        $field->setAttribute('onchange', 'gs_checkConditionalFields(this, new Array(' . implode(',', $typeFields) . '), new Array(' . implode(',', $textFields) . '));');
        $select = $field->getSelect();
        $select->setSize(1);

        $qry = 'SELECT label,id FROM ' . rex::getTablePrefix() . 'global_settings_type';
        $select->addSqlOptions($qry);

        $notices = '';
        for ($i = 1; $i < REX_GLOBAL_SETTINGS_FIELD_COUNT; ++$i) {
            if (rex_i18n::hasMsg('global_settings_field_params_notice_' . $i)) {
                $notices .= '<span id="global-settings-field-params-notice-' . $i . '" style="display:none">' . rex_i18n::msg('global_settings_field_params_notice_' . $i) . '</span>' . "\n";
            }
        }
        $notices .= '
        <script type="text/javascript">
            var needle = new getObj("' . $field->getAttribute('id') . '");
            gs_checkConditionalFields(needle.obj, new Array(' . implode(',', $typeFields) . '), new Array(' . implode(',', $textFields) . '));
        </script>';

        $field = $this->addTextAreaField('params');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_params'));
        $field->setNotice($notices);

        $field = $this->addTextAreaField('attributes');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_attributes'));
        $notice = rex_i18n::msg('global_settings_field_attributes_notice') . "\n";
        $field->setNotice($notice);

        $field = $this->addTextAreaField('callback');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_callback'));
        $notice = rex_i18n::msg('global_settings_field_label_notice') . "\n";
        $field->setNotice($notice);

        $field = $this->addTextField('default');
        $field->setLabel(rex_i18n::msg('global_settings_field_label_default'));

        /*if ('clang_' !== $this->metaPrefix) {
            $attributes = [];
            $attributes['internal::fieldClass'] = 'rex_form_restrictons_element';
            $field = $this->addField('', 'restrictions', null, $attributes);
            $field->setLabel(rex_i18n::msg('global_settings_field_label_restrictions'));
            $field->setAttribute('size', 10);
            $field->setAttribute('class', 'form-control');
        }*/

        parent::init();
    }

    protected function delete()
    {
        // Infos zuerst selektieren, da nach parent::delete() nicht mehr in der db
        $sql = rex_sql::factory();
        $sql->setDebug($this->debug);
        $sql->setTable($this->tableName);
        $sql->setWhere($this->whereCondition);
        $sql->select('name');
        $columnName = $sql->getValue('name');

        if (($result = parent::delete()) === true) {
            // Prios neu setzen, damit keine lücken entstehen
            $this->organizePriorities(1, 2);
            return $this->tableManager->deleteColumn($columnName);
        }

        return $result;
    }

    protected function preSave($fieldsetName, $fieldName, $fieldValue, rex_sql $saveSql)
    {
        if ($fieldsetName == $this->getFieldsetName() && $fieldName == 'name') {
            // Den Namen mit Prefix speichern
            return $this->addPrefix($fieldValue);
        }

        return parent::preSave($fieldsetName, $fieldName, $fieldValue, $saveSql);
    }

    protected function preView($fieldsetName, $fieldName, $fieldValue)
    {
        if ($fieldsetName == $this->getFieldsetName() && $fieldName == 'name') {
            // Den Namen ohne Prefix anzeigen
            return $this->stripPrefix($fieldValue);
        }
        return parent::preView($fieldsetName, $fieldName, $fieldValue);
    }

    public function addPrefix($string)
    {
        $lowerString = strtolower($string);
        if (substr($lowerString, 0, strlen($this->metaPrefix)) !== $this->metaPrefix) {
            return $this->metaPrefix . $string;
        }
        return $string;
    }

    public function stripPrefix($string)
    {
        $lowerString = strtolower($string);
        if (substr($lowerString, 0, strlen($this->metaPrefix)) === $this->metaPrefix) {
            return substr($string, strlen($this->metaPrefix));
        }
        return $string;
    }

    protected function validate()
    {
        $fieldName = $this->elementPostValue($this->getFieldsetName(), 'name');
        if ($fieldName == '') {
            return rex_i18n::msg('global_settings_field_error_name');
        }

        if (preg_match('/[^a-zA-Z0-9\_]/', $fieldName)) {
            return rex_i18n::msg('global_settings_field_error_chars_name');
        }

        // Pruefen ob schon eine Spalte mit dem Namen existiert (nur beim add noetig)
        if (!$this->isEditMode()) {
            // die tabelle selbst checken
            if ($this->tableManager->hasColumn($this->addPrefix($fieldName))) {
                return rex_i18n::msg('global_settings_field_error_unique_name');
            }

            // das meta-schema checken
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT * FROM ' . $this->tableName . ' WHERE name="' . $this->addPrefix($fieldName) . '" LIMIT 1');
            if ($sql->getRows() == 1) {
                return rex_i18n::msg('global_settings_field_error_unique_name');
            }
        }

        return parent::validate();
    }

    protected function save()
    {
        $fieldName = $this->elementPostValue($this->getFieldsetName(), 'name');

        // Den alten Wert aus der DB holen
        // Dies muss hier geschehen, da in parent::save() die Werte fuer die DB mit den
        // POST werten ueberschrieben werden!
        $fieldOldName = '';
        $fieldOldPriority = 9999999999999; // dirty, damit die prio richtig l�uft...
        $fieldOldDefault = '';
        if ($this->sql->getRows() == 1) {
            $fieldOldName = $this->sql->getValue('name');
            $fieldOldPriority = $this->sql->getValue('priority');
            $fieldOldDefault = $this->sql->getValue('default');
        }

        if (parent::save()) {
            $this->organizePriorities($this->elementPostValue($this->getFieldsetName(), 'priority'), $fieldOldPriority);

            $fieldName = $this->addPrefix($fieldName);
            $fieldType = $this->elementPostValue($this->getFieldsetName(), 'type_id');
            $fieldDefault = $this->elementPostValue($this->getFieldsetName(), 'default');

            $sql = rex_sql::factory();
            $sql->setDebug($this->debug);
            $result = $sql->getArray('SELECT `dbtype`, `dblength` FROM `' . rex::getTablePrefix() . 'global_settings_type` WHERE id=' . $fieldType);
            $fieldDbType = $result[0]['dbtype'];
            $fieldDbLength = $result[0]['dblength'];

            // TEXT Spalten duerfen in MySQL keine Defaultwerte haben
            if ($fieldDbType == 'text') {
                $fieldDefault = null;
            }

            if ($this->isEditMode()) {
                // Spalte in der Tabelle ver�ndern
                $tmRes = $this->tableManager->editColumn($fieldOldName, $fieldName, $fieldDbType, $fieldDbLength, $fieldDefault);
            } else {
                // Spalte in der Tabelle anlegen
                $tmRes = $this->tableManager->addColumn($fieldName, $fieldDbType, $fieldDbLength, $fieldDefault);
            }
            rex_delete_cache();

            if ($tmRes) {
                // DefaultWerte setzen
                if ($fieldDefault != $fieldOldDefault) {
                    try {
                        $upd = rex_sql::factory();
                        $upd->setDebug($this->debug);
                        $upd->setTable($this->tableManager->getTableName());
                        $upd->setWhere([$fieldName => $fieldOldDefault]);
                        $upd->setValue($fieldName, $fieldDefault);
                        $upd->update();
                        return true;
                    } catch (rex_sql_exception $e) {
                        return false;
                    }
                }

                // Default werte haben schon zuvor gepasst, daher true zur�ckgeben
                return true;
            }
        }

        return false;
    }

    public function getPrefix()
    {
        return $this->metaPrefix;
    }

    protected function organizePriorities($newPrio, $oldPrio)
    {
        if ($newPrio == $oldPrio) {
            return;
        }

        // replace LIKE wildcards
        $metaPrefix = str_replace(['_', '%'], ['\_', '\%'], $this->metaPrefix);

        rex_sql_util::organizePriorities(
            $this->tableName,
            'priority',
            'name LIKE "' . $metaPrefix . '%"',
            'priority, updatedate desc'
        );
    }
}
