<?php

include_once 'config.inc.php';
class migration {

    private $conn;
    private $schemaPath;
    private $arrTables;

    public function __construct() {
        $this->conn = $this->getConnection();
        $this->schemaPath = $_SERVER['DOCUMENT_ROOT'] . "/migration/schema/migration.xml";
        $this->arrTables = array();
        $this->getTables();
    }

    public function getConnection() {
        global $username;
        global $password;
        global $database_sdn;
        $pdo = new PDO($database_sdn, $username, $password);
        if ($pdo) {
            return $pdo;
        } else {
            die();
        }
    }

    public function ParseXml() {
        $xmlParse = simplexml_load_file($this->schemaPath);
        foreach ($xmlParse->object as $object) {
            $arrAttributes = $object->attributes();
            $strTableName = (string) $arrAttributes->table;
            if (!empty($this->arrTables[$strTableName])) {
                $this->updateTable($strTableName, $object->field);
            } else {
                $this->createTable($strTableName, $object->field);
            }
        }
    }

    public function runMigration() {
        $this->ParseXml();
    }

    public function getTables() {
        $result = $this->conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND
        TABLE_SCHEMA='test_application'");
        foreach ($result as $row) {
            $this->arrTables[$row['TABLE_NAME']] = $row['TABLE_NAME'];
        }
    }

    public function getColumns($strTableName) {
        //TODO: have use configured database
        $arrColumn = array();
        $result = $this->conn->query("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` 
                                     WHERE `TABLE_SCHEMA`='test_application' AND `TABLE_NAME`='" . $strTableName . "'");
        foreach ($result as $row) {
            $arrColumn[$row['COLUMN_NAME']] = $row['COLUMN_NAME'];
        }
        return $arrColumn;
    }

    public function createTable($strTableName, $arrFields) {
        $arrMysqlField = array();
        $index = 0;
        $strPrimaryKey = '';
        foreach ($arrFields as $fields) {
            $objFieldAttributes = $fields->attributes();
            $arrMysqlField[$index++] = $this->generateSQLColumn($objFieldAttributes);
            if (isset($objFieldAttributes->index) && $objFieldAttributes->index == 'pk') {
                $strPrimaryKey = 'PRIMARY KEY (' . $objFieldAttributes->key . ') ';
            }
        }
        $strField = implode(',', $arrMysqlField);
        if (!empty($strPrimaryKey)) {
            $strField .= ',' . $strPrimaryKey;
        }
        $strTamplete = "CREATE TABLE IF NOT EXISTS `" . $strTableName . "` ([[+fields]])";
        $queryString = str_replace('[[+fields]]', $strField, $strTamplete);
        $result = $this->conn->query($queryString);
        if ($result)
            echo "<br/> >> Successfully created table $strTableName";
        else
            echo "<br/> >> Failed to created table $strTableName";
    }

    public function generateSQLColumn($objFieldAttributes) {
        $strField = '';
        if (isset($objFieldAttributes->name))
            $strField .= "`" . $objFieldAttributes->name . "`";

        if (isset($objFieldAttributes->key))
            $strField .= "`" . $objFieldAttributes->key . "`";

        if (isset($objFieldAttributes->dbtype) && isset($objFieldAttributes->precision)) {
            $strField .= " " . $objFieldAttributes->dbtype . "(" . $objFieldAttributes->precision . ")";
        } else if (isset($objFieldAttributes->dbtype)) {
            $strField .= ' ' . $objFieldAttributes->dbtype;
        }

        if (isset($objFieldAttributes->null) && $objFieldAttributes->null == 'false')
            $strField .= " NOT NULL";
        else
            $strField .= " DEFAULT NULL";
        return $strField;
    }

    public function updateTable($strTableName, $arrFields) {
        $arrTableColumn = $this->getColumns($strTableName);
        $arrSchemaColumns = array();
        foreach ($arrFields as $objField) {
            $objFieldAttributes = $objField->attributes();
            $strFieldName = ($objFieldAttributes->name == '') ? (string) $objFieldAttributes->key : (string) $objFieldAttributes->name;
            $arrSchemaColumns[$strFieldName] = $strFieldName;
            if (!array_key_exists($strFieldName, $arrTableColumn)) {
                $strSQLColumn = $this->generateSQLColumn($objFieldAttributes);
                $this->addColumn($strTableName, $strSQLColumn);
            } else {

                $this->updateColumn($strTableName, $objFieldAttributes);
            }
        }

        $arrDeleteColums = array_diff($arrTableColumn, $arrSchemaColumns);

        if (is_array($arrDeleteColums) && count($arrDeleteColums) > 0) {
            foreach ($arrDeleteColums as $strColumnName) {
                $this->removeColumn($strTableName, $strColumnName);
            }
        }
    }

    public function addColumn($strTableName, $strField) {
        $strSqlQuery = "ALTER TABLE `" . $strTableName . "` ADD " . $strField;
        $result = $this->conn->query($strSqlQuery);
        if ($result) {
            echo ">> Successfully added 1 columns into $strTableName";
            echo '<br>';
        } else {
            echo ">> Failed to added 1 columns into $strTableName";
            echo '<br>';
        }
    }

    public function removeColumn($strTableName, $strField) {

        $strSqlQuery = "ALTER TABLE `" . $strTableName . "` DROP COLUMN " . $strField;
        $result = $this->conn->query($strSqlQuery);
        if ($result) {
            echo ">> Successfully removed column " . $strField . " from $strTableName";
            echo '<br>';
        } else {
            echo ">> Failed to remove column " . $strField . " from $strTableName";
            echo '<br>';
        }
    }

    private function updateColumn($strTableName, $objFieldAttributes) {

        $blnCheckUpdate = false;

        $strFieldName = ($objFieldAttributes->name == '') ? (string) $objFieldAttributes->key : (string) $objFieldAttributes->name;

        $strSqlQuery = ('SELECT * FROM INFORMATION_SCHEMA.COLUMNS'
                . ' WHERE TABLE_NAME = "' . $strTableName . '" AND COLUMN_NAME = "' . $strFieldName . '"' );
        $result = $this->conn->prepare($strSqlQuery);
        $result->execute();
        $row = $result->fetchAll(PDO::FETCH_ASSOC);
        //die;
    }

    public function removeTable() {
        
    }

    public function inspect($arrData) {
        echo "<pre>";
        print_r($arrData);
        echo "</pre>";
    }

}

$objMigration = new migration();
$objMigration->runMigration();
