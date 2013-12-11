<?php

class migration {

    public $conn, $schemaPath, $arrTables;

    public function __construct() {
        $this->conn = $this->getConnection();
        $this->schemaPath = $_SERVER['DOCUMENT_ROOT'] . "/Migration/schema/migration.xml";
        $this->arrTables = array();
        $this->getTables();
    }

    public function getConnection() {
        $pdo = new PDO('mysql:host=localhost;dbname=test_application', 'root', '');
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
            $strTableName = (string) $arrAttributes['table_name'];
            if (!empty($this->arrTables[$strTableName])) {
                $this->updateTable();
            } else {
                $this->createTable($strTableName, $object->field);
            }
        }
    }

    public function runMigration() {
        $this->ParseXml();
    }

    public function getTables() {
        $result = $this->conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='test_application'");
        foreach ($result as $row) {
            $this->arrTables[$row['TABLE_NAME']] = $row['TABLE_NAME'];
        }
    }

    public function getColumns() {
        
    }

    public function createTable($strTableName, $arrFields) {
        $arrMysqlField = array();
        $index = 0;
        foreach ($arrFields as $fields)
        {
          $strField = '';
          $arrFieldAttributes = $fields->attributes();   
          if(isset($arrFieldAttributes['name']))
             $strField .= "`".$arrFieldAttributes['name']."`";
          if(isset($arrFieldAttributes['dbtype']) && isset($arrFieldAttributes['precision']))
            $strField .= " ".$arrFieldAttributes['dbtype']."(".$arrFieldAttributes['precision'].")";
          if(isset($arrFieldAttributes['null']) && $arrFieldAttributes['null'] == 'false')
            $strField .= " NOT NULL";
          else
            $strField .= " DEFAULT NULL";  
          $arrMysqlField[$index++] = $strField;
        }
        $strField = implode(',', $arrMysqlField);
        $strTamplete = "CREATE TABLE IF NOT EXISTS `" . $strTableName . "` (
          [[+fields]]
        )";
        $queryString = str_replace('[[+fields]]', $strField, $strTamplete);
        $result = $this->conn->query($queryString);
        if($result)
          echo "<br/> >> Successfully created table $strTableName";
        else
          echo "<br/> >> Failed to created table $strTableName";  
    }
    
    public function updateTable()
    {
        
    }
    
    public function updateTableColumn()
    {
        
    }

    public function inspect($arrData) {
        echo "<pre>";
        print_r($arrData);
        echo "</pre>";
    }

}

$objMigration = new migration();
$objMigration->runMigration();