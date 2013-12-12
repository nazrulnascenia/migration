<?php

class migration {

    private $conn;

    private $schemaPath;

    private $arrTables;


    public function __construct() {
        $this->conn = $this->getConnection();
        $this->schemaPath = $_SERVER['DOCUMENT_ROOT'] . "/schema/migration.xml";
        $this->arrTables = array();
        $this->getTables();
    }

    public function getConnection() {
        $pdo = new PDO('mysql:host=localhost;dbname=test_application', 'test_application', 'test_application');
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
            $arrFieldAttributes = $fields->attributes();
            $arrMysqlField[$index++] = $this->generateSQLColumn($arrFieldAttributes);
            if (isset($arrFieldAttributes->index) && $arrFieldAttributes->index == 'pk') {
                $strPrimaryKey = 'PRIMARY KEY (' . $arrFieldAttributes->key . ') ';
            }
        }
        $strField = implode(',', $arrMysqlField);
        if (!empty($strPrimaryKey)) {
            $strField .= ',' . $strPrimaryKey;
        }
        $strTamplete = "CREATE TABLE IF NOT EXISTS `" . $strTableName . "` ([[+fields]])";
        $queryString = str_replace('[[+fields]]', $strField, $strTamplete);
        echo "<br/>" . $queryString;
        $result = $this->conn->query($queryString);
        if ($result)
            echo "<br/> >> Successfully created table $strTableName";
        else
            echo "<br/> >> Failed to created table $strTableName";
    }

    public function generateSQLColumn($arrFieldAttributes) {
        $strField = '';
        if (isset($arrFieldAttributes->name))
            $strField .= "`" . $arrFieldAttributes->name . "`";
        
        if (isset($arrFieldAttributes->key))
            $strField .= "`" . $arrFieldAttributes->key . "`";

        if (isset($arrFieldAttributes->dbtype) && isset($arrFieldAttributes->precision)) {
            $strField .= " " . $arrFieldAttributes->dbtype . "(" . $arrFieldAttributes->precision . ")";
        } else if (isset($arrFieldAttributes->dbtype)) {
            $strField .= ' ' . $arrFieldAttributes->dbtype;
        }

        if (isset($arrFieldAttributes->null) && $arrFieldAttributes->null == 'false')
            $strField .= " NOT NULL";
        else
            $strField .= " DEFAULT NULL";
        return $strField;
    }

    public function updateTable($strTableName, $arrFields) {
        $arrTableColumn = $this->getColumns($strTableName);
        foreach ($arrFields as $objField) {
            $arrFieldAttributes = $objField->attributes();
            $strFieldName = ($arrFieldAttributes->name == '') ? (string)$arrFieldAttributes->key : (string)$arrFieldAttributes->name;
            if(!array_key_exists($strFieldName, $arrTableColumn)){
                $strSQLColumn = $this->generateSQLColumn($arrFieldAttributes);
                $this->addColumn($strTableName, $strSQLColumn);
            }
        }die;
    }

    public function addColumn($strTableName, $strField) {
      $strSqlQuery = "ALTER TABLE `".$strTableName."` ADD ".$strField;
      echo " ".$strSqlQuery;
      $result = $this->conn->query($strSqlQuery);
      if($result) {
         echo ">> Successfully added 1 columns into $strTableName";
         echo '<br>';
      }
      else {
          echo ">> Failed to added 1 columns into $strTableName";
          echo '<br>';
      }
    }

    public function removeColumn() {
        
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