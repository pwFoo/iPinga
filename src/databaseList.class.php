<?php
/*
    Vern Six MVC Framework version 3.0

    Copyright (c) 2007-2018 by Vernon E. Six, Jr.
    Author's websites: http://www.ipinga.com and http://www.VernSix.com

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to use
    the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice, author's websites and this permission notice
    shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
    IN THE SOFTWARE.
*/
namespace ipinga;


Class databaseList
{

    /**
     * @var string tableName we are working with. Set in the constructor call
     */
    public $tableName;

    /**
     * @var array Array of table objects in the list.  Each table object will contain precisely one row of data from the table
     */
    public $records = array();

    /**
     * @var string
     */
    public $lastSql = '';



    /**
     * @param string $tableName
     */
    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }


    /**
     * perform the actual read from the database and populate this instance of the list. select statement MUST have a
     * field called "id" as a column name in the result set!
     *
     * @param $sql select statement to grab rows from the database
     */
    function read_from_db($sql)
    {
        $ipinga = \ipinga\ipinga::getInstance();

        $this->records = array(); // start fresh with an empty list
        try {
            foreach ($ipinga->pdo()->query($sql) as $row) {
                $tbl = new \ipinga\table($this->tableName);
                $tbl->loadById($row['id']);
                $this->records[] = $tbl;
            }
        } catch (\PDOException $e) {
            echo $e->getMessage() . '<br>' . $sql . '<br><hr>';
        }
    }

    /**
     * @param string $orderBy optional: orderby field for sql select statement
     */
    public function load($orderBy = "id")
    {
        $sql = sprintf('select id from %s order by %s', $this->tableName, $orderBy);
        $this->read_from_db($sql);
    }


    /**
     * @param string $where
     * @param string $orderBy
     */
    public function loadWithWhere($where, $orderBy = "id")
    {
        $sql = sprintf('select id from %s where %s order by %s', $this->tableName, $where, $orderBy);
        $this->read_from_db($sql);
    }



    public function loadByFieldsMatching($fields = array(),$orderBy = 'id')
    {
        $w = '';
        foreach($fields as $fieldName => $desiredValue) {
            if (empty($w)==false) {
                $w .= ' AND ';
            }
            $w .= $fieldName . ' = :' . $fieldName;
        }

        if (empty($w) == true) {
            $sql = sprintf('select id from %s order by %s', $this->tableName, $orderBy);
        } else {
            $sql = sprintf('select id from %s where %s order by %s', $this->tableName, $w, $orderBy);
        }
        $this->lastSql = $sql;

        try {

            $stmt = \ipinga\ipinga::getInstance()->pdo()->prepare($sql);
            foreach($fields as $fieldName => $desiredValue) {
                $stmt->bindValue(':'. $fieldName, $desiredValue);
            }
            $stmt->execute();

            while ($r=$stmt->fetch(\PDO::FETCH_ASSOC)) {
                $tbl = new \ipinga\table($this->tableName);
                $tbl->loadById($r['id']);
                $this->records[] = $tbl;
            }

        } catch (\PDOException $e) {
            echo $e->getMessage() . '<br>' . $sql . '<br><hr>';
            $this->saved = false;
        }




    }

    // I am not proud of this function.  :)
    public function filter($filter = array())
    {

        $filterRecords = array();

        foreach ($this->records as $r) {

            $includeThisRecord = true;
            foreach ($filter as $fieldName => $data) {
                if ($r->field[$fieldName] <> $data) {
                    $includeThisRecord = false;
                    break;
                }
            }

            if ($includeThisRecord) {
                $filterRecords[] = $r;
            }

        }
        $this->records = $filterRecords;

    }










    /*
 * Builds html for a <select> form element. Walks through the array of table object to build html for a <select> form element
 * @param string $fieldName name of the column in the database table object(s) in $the_list
 * @param integer $selectedId the database id to make the actively selected element
 * @param boolean $addFirst (default: false) ad the option 'select one...' to the start of the list
 * @param string $class css class name
 */

    /**
     * @param string     $selectName
     * @param string     $fieldName
     * @param int        $selectedId
     * @param bool|false $addFirst
     * @param string     $class
     *
     * @return string
     */
    public function asHtmlSelect($selectName, $fieldName, $selectedId = 0, $addFirst = false, $class = '')
    {
        $h = "<select name='$selectName' id='$selectName'";
        $h .= (empty($class) == false) ? " class=$class" : "";
        $h .= ">\r\n";

        if ($addFirst) {
            $h .= "<option value=0>Select one...</option>\r\n";
        }

        foreach ($this->records as $t) {
            $h .= '<option value="' . $t->field['id'] . '"';
            if ($t->field['id'] == $selectedId) {
                $h .= ' selected="selected"';
            }
            $h .= '>' . $t->field[$fieldName] . '</option>' . "\r\n";
        }

        $h .= "</select>\r\n";

        return $h;
    }

    /*
     * Return json representation of the array of v6_table object's current field values
     */
    public function asJson()
    {
        $j = array();
        foreach ($this->records as $t) {
            $j[] = $t->field;
        }
        return json_encode($j);
    }


    /*
     * locate a table object within a array of table objects by looking at the id column
     */

    /**
     * @param string $fieldName
     * @param mixed  $value
     *
     * @return bool|mixed
     */
    public function recordByField($fieldName, $value)
    {
        foreach ($this->records as $r) {
            if ($r->$fieldName == $value) return $r;
        }
        return false;
    }


    /*
     * look for a table object field_name=value pair and return record number
     * @returns int
     */
    public function recordNumberByField($fieldName, $value)
    {
        $recordNumber = 0;
        foreach ($this->records as $t) {
            if ($t->$fieldName == $value) {
                return $recordNumber;
            }
            $recordNumber++;
        }
        return 0;
    }


    // returns the records as a nested array suitable for passing to defaultHtmlGenerator as settings['choices']
    public function asChoices($valueFieldName,$descriptionFieldName) {

        $choices = array();
        foreach ($this->records as $t) {
            $choices[$t->field[$valueFieldName]] = $t->field[$descriptionFieldName];
        }
        return $choices;

    }



}

