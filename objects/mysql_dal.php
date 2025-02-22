<?php
/*
  tester-execution-code
  $sql = "SELECT * FROM users WHERE id=?;";
  $result = sqlDAL::readSql($sql,"i",array(1));
  while($row = sqlDAL::fetchArray($result)){
  echo $row[2]."<br />";
  }

  OR

  while($row = sqlDAL::fetchAssoc($result)){
  echo $row['user']."<br />";
  }
 */

/*
 * Internal used class
 */

class iimysqli_result
{
    public $stmt;
    public $nCols;
    public $fields;
}

global $disableMysqlNdMethods;
// this is only to test both methods more easy.
$disableMysqlNdMethods = false;

/*
 * This class exists for making servers avaible, which have no mysqlnd, withouth cause a performance-issue for those who have the driver.
 * It wouldn't be possible without Daan on https://stackoverflow.com/questions/31562359/workaround-for-mysqlnd-missing-driver
 */

class sqlDAL
{
    public static function executeFile($filename)
    {
        global $global;
        $templine = '';
        // Read in entire file
        $lines = file($filename);
        // Loop through each line
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }

            // Add this line to the current segment
            $templine .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                // Perform the query
                if (!$global['mysqli']->query($templine)) {
                    _error_log('sqlDAL::executeFile ' . $filename . ' Error performing query \'<strong>' . $templine . '\': ' . $global['mysqli']->error . '<br /><br />', AVideoLog::$ERROR);
                }
                // Reset temp variable to empty
                $templine = '';
            }
        }
    }

    /*
     * For Sql like INSERT and UPDATE. The special point about this method: You do not need to close it (more direct).
     * @param string $preparedStatement  The Sql-command
     * @param string $formats            i=int,d=doube,s=string,b=blob (http://www.php.net/manual/en/mysqli-stmt.bind-param.php)
     * @param array  $values             A array, containing the values for the prepared statement.
     * @return boolean                   true on success, false on fail
     */

    public static function writeSql($preparedStatement, $formats = "", $values = [])
    {
        global $global, $disableMysqlNdMethods;
        if (empty($preparedStatement)) {
            return false;
        }
        // make sure it does not store autid transactions
        $debug = debug_backtrace();
        if (empty($debug[2]['class']) || $debug[2]['class'] !== "AuditTable") {
            $audit = AVideoPlugin::loadPluginIfEnabled('Audit');
            if (!empty($audit)) {
                try {
                    $audit->exec(@$debug[1]['function'], @$debug[1]['class'], $preparedStatement, $formats, json_encode($values), User::getId());
                } catch (Exception $exc) {
                    log_error($exc->getTraceAsString());
                }
            }
        }

        if (!is_object($global['mysqli'])) {
            _mysql_connect();
        }

        if (!($stmt = $global['mysqli']->prepare($preparedStatement))) {
            log_error("[sqlDAL::writeSql] Prepare failed: (" . $global['mysqli']->errno . ") " . $global['mysqli']->error . " ({$preparedStatement})");
            return false;
        }
        if (!sqlDAL::eval_mysql_bind($stmt, $formats, $values)) {
            log_error("[sqlDAL::writeSql]  eval_mysql_bind failed: values and params in stmt don't match ({$preparedStatement}) with formats ({$formats})");
            return false;
        }
        try {
            $stmt->execute();
        } catch (Exception $exc) {
            log_error($exc->getTraceAsString());
            log_error('Error in writeSql stmt->execute: '.$preparedStatement);
        }

        if ($stmt->errno !== 0) {
            log_error('Error in writeSql : (' . $stmt->errno . ') ' . $stmt->error . ", SQL-CMD:" . $preparedStatement);
            $stmt->close();
            return false;
        }
        $iid = @$global['mysqli']->insert_id;
        //$global['mysqli']->affected_rows = $stmt->affected_rows;
        //$stmt->commit();
        $stmt->close();
        if (!empty($iid)) {
            return $iid;
        } else {
            return true;
        }
    }

    /*
     * For Sql like SELECT. This method needs to be closed anyway. If you start another readSql, while the old is open, it will fail.
     * @param string $preparedStatement  The Sql-command
     * @param string $formats            i=int,d=doube,s=string,b=blob (http://www.php.net/manual/en/mysqli-stmt.bind-param.php)
     * @param array  $values             A array, containing the values for the prepared statement.
     * @return Object                    Depend if mysqlnd is active or not, a object, but always false on fail
     */

    public static function readSql($preparedStatement, $formats = "", $values = [], $refreshCache = false)
    {
        // $refreshCache = true;
        global $global, $disableMysqlNdMethods, $readSqlCached, $crc;
        // need to add dechex because some times it return an negative value and make it fails on javascript playlists
        $crc = (md5($preparedStatement . implode($values)));

        if (!isset($readSqlCached)) {
            $readSqlCached = [];
        }
        if ((function_exists('mysqli_fetch_all')) && ($disableMysqlNdMethods == false)) {

            // Mysqlnd enabled

            if ((!isset($readSqlCached[$crc])) || ($refreshCache)) {

                // When not cached

                $readSqlCached[$crc] = "false";
                _mysql_connect();

                if (!($stmt = $global['mysqli']->prepare($preparedStatement))) {
                    log_error("[sqlDAL::readSql] (mysqlnd) Prepare failed: (" . $global['mysqli']->errno . ") " . $global['mysqli']->error . " ({$preparedStatement}) - format=({$formats}) values=" . json_encode($values));
                    //log_error("[sqlDAL::readSql] trying close and reconnect");
                    _mysql_close();
                    _mysql_connect();
                    if (!($stmt = $global['mysqli']->prepare($preparedStatement))) {
                        log_error("[sqlDAL::readSql] (mysqlnd) Prepare failed again return false");
                        return false;
                    } else {
                        log_error("[sqlDAL::readSql] SUCCESS close and reconnect works!");
                    }
                }
                if (!sqlDAL::eval_mysql_bind($stmt, $formats, $values)) {
                    log_error("[sqlDAL::readSql] (mysqlnd) eval_mysql_bind failed: values and params in stmt don't match {$preparedStatement} with formats {$formats}");
                    return false;
                }
                $TimeLog = "[$preparedStatement], $formats, " . json_encode($values) . ", $refreshCache";
                TimeLogStart($TimeLog);
                $stmt->execute();
                $readSqlCached[$crc] = $stmt->get_result();
                if ($stmt->errno !== 0) {
                    log_error('Error in readSql (mysqlnd): (' . $stmt->errno . ') ' . $stmt->error . ", SQL-CMD:" . $preparedStatement);
                    $stmt->close();
                    $disableMysqlNdMethods = true;
                    // try again with noMysqlND
                    $read = self::readSql($preparedStatement, $formats, $values, $refreshCache);
                    TimeLogEnd($TimeLog, "mysql_dal", 0.5);
                    return $read;
                }
                TimeLogEnd($TimeLog, "mysql_dal", 0.5);
                $stmt->close();
            } elseif (is_object($readSqlCached[$crc])) {

                // When cached
                // reset the stmt for fetch. this solves objects/video.php line 550
                $readSqlCached[$crc]->data_seek(0);
                //log_error("set dataseek to 0");
                // increase a counter for the saved queries.
                if (isset($_SESSION['savedQuerys'])) {
                    $_SESSION['savedQuerys']++;
                }
            } else {
                $readSqlCached[$crc] = "false";
            }

            //
            // if ($readSqlCached[$crc] == "false") {
            // add this in case the cache fail
            // ->lengths seems to be always NULL.. fix: $readSqlCached[$crc]->data_seek(0); above
            //if("SELECT * FROM configurations WHERE id = 1 LIMIT 1"==$preparedStatement){
            //  var_dump($readSqlCached[$crc]);
            //}
            if ($readSqlCached[$crc] != "false") {
                if (is_null($readSqlCached[$crc]->lengths) && !$refreshCache && $readSqlCached[$crc]->num_rows == 0 && $readSqlCached[$crc]->field_count == 0) {
                    log_error("[sqlDAL::readSql] (mysqlnd) Something was going wrong, re-get the query. {$preparedStatement} {$readSqlCached[$crc]->num_rows}");
                    return self::readSql($preparedStatement, $formats, $values, true);
                }
            } else {
                $readSqlCached[$crc] = false;
            }
            // }
        } else {

            // Mysqlnd-fallback

            if (!($stmt = $global['mysqli']->prepare($preparedStatement))) {
                log_error("[sqlDAL::readSql] (no mysqlnd) Prepare failed: (" . $global['mysqli']->errno . ") " . $global['mysqli']->error . " ({$preparedStatement})");
                return false;
            }

            if (!sqlDAL::eval_mysql_bind($stmt, $formats, $values)) {
                log_error("[sqlDAL::readSql] (no mysqlnd) eval_mysql_bind failed: values and params in stmt don't match {$preparedStatement} with formats {$formats}");
                return false;
            }

            $stmt->execute();
            $result = self::iimysqli_stmt_get_result($stmt);
            if ($stmt->errno !== 0) {
                log_error('Error in readSql (no mysqlnd): (' . $stmt->errno . ') ' . $stmt->error . ", SQL-CMD:" . $preparedStatement);
                $stmt->close();
                $readSqlCached[$crc] = false;
            } else {
                $readSqlCached[$crc] = $result;
            }
        }
        return $readSqlCached[$crc];
    }

    /*
     * This closes the readSql
     * @param Object $result A object from sqlDAL::readSql
     */

    public static function close($result)
    {
        global $disableMysqlNdMethods, $global;
        if ((!function_exists('mysqli_fetch_all')) || ($disableMysqlNdMethods !== false)) {
            if (!empty($result->stmt)) {
                $result->stmt->close();
            }
        }
    }

    /*
     * Get the nr of rows
     * @param Object $result A object from sqlDAL::readSql
     * @return int           The nr of rows
     */

    public static function num_rows($res)
    {
        global $global, $disableMysqlNdMethods, $crc, $num_row_cache;
        if (!isset($num_row_cache)) {
            $num_row_cache = [];
        }
        // cache is working - but disable for proper test-results
        if (!isset($num_row_cache[$crc])) {
            if ((function_exists('mysqli_fetch_all')) && ($disableMysqlNdMethods == false)) {
                // Mysqlnd
                $num_row_cache[$crc] = 0;
                if (!empty($res->num_rows)) {
                    $num_row_cache[$crc] = $res->num_rows;
                }
                return $num_row_cache[$crc];
            } else {
                // Mysqlnd-fallback - use fetchAllAssoc because this can be cached.
                $num_row_cache[$crc] = sizeof(self::fetchAllAssoc($res));
            }
        }
        return $num_row_cache[$crc];
    }

    // unused
    public static function cached_num_rows($data)
    {
        return sizeof($data);
    }

    /*
     * Make a fetch assoc on every row avaible
     * @param Object $result A object from sqlDAL::readSql
     * @return array           A array filled with all rows as a assoc array
     */

    public static function fetchAllAssoc($result)
    {
        global $crc, $fetchAllAssoc_cache;
        if (!isset($fetchAllAssoc_cache)) {
            $fetchAllAssoc_cache = [];
        }
        if (!isset($fetchAllAssoc_cache[$crc])) {
            $ret = [];
            while ($row = self::fetchAssoc($result)) {
                $ret[] = $row;
            }
            $fetchAllAssoc_cache[$crc] = $ret;
        }
        return $fetchAllAssoc_cache[$crc];
    }

    /*
     * Make a single assoc fetch
     * @param Object $result A object from sqlDAL::readSql
     * @return int           A single row in a assoc array
     */

    public static function fetchAssoc($result)
    {
        global $global, $disableMysqlNdMethods;
        ini_set('memory_limit', '-1');
        // here, a cache is more/too difficult, because fetch gives always a next. with this kind of cache, we would give always the same.
        if ((function_exists('mysqli_fetch_all')) && ($disableMysqlNdMethods == false)) {
            if ($result !== false) {
                return $result->fetch_assoc();
            }
        } else {
            return self::iimysqli_result_fetch_assoc($result);
        }
        return false;
    }

    /*
     * Make a fetchArray on every row avaible
     * @param Object $result A object from sqlDAL::readSql
     * @return array           A array filled with all rows
     */

    public static function fetchAllArray($result)
    {
        global $crc, $fetchAllArray_cache;
        if (!isset($fetchAllArray_cache)) {
            $fetchAllArray_cache = [];
        }
        // cache is working - but disable for proper test-results
        if (!isset($fetchAllArray_cache[$crc])) {
            $ret = [];
            while ($row = self::fetchArray($result)) {
                $ret[] = $row;
            }
            $fetchAllArray_cache[$crc] = $ret;
        } else {
            log_error("array-cache");
        }
        return $fetchAllArray_cache[$crc];
    }

    /*
     * Make a single fetch
     * @param Object $result A object from sqlDAL::readSql
     * @return int           A single row in a array
     */

    public static function fetchArray($result)
    {
        global $global, $disableMysqlNdMethods;
        if ((function_exists('mysqli_fetch_all')) && ($disableMysqlNdMethods == false)) {
            return $result->fetch_array();
        } else {
            return self::iimysqli_result_fetch_array($result);
        }
        return false;
    }

    private static function eval_mysql_bind($stmt, $formats, $values)
    {
        if (($stmt->param_count != sizeof($values)) || ($stmt->param_count != strlen($formats))) {
            return false;
        }
        if ((!empty($formats)) && (!empty($values))) {
            $code = "return \$stmt->bind_param(\"" . $formats . "\"";
            $i = 0;
            foreach ($values as $val) {
                $code .= ", \$values[" . $i . "]";
                $i++;
            };
            $code .= ");";
            // echo $code. " : ".$preparedStatement;
            eval($code);
        }
        return true;
    }

    private static function iimysqli_stmt_get_result($stmt)
    {
        global $global;
        $metadata = mysqli_stmt_result_metadata($stmt);
        $ret = new iimysqli_result();
        $field_array = [];
        if (!$metadata) {
            die("Execute query error, because: {$stmt->error}");
        }
        $tmpFields = $metadata->fetch_fields();
        $i = 0;
        foreach ($tmpFields as $f) {
            $field_array[$i] = $f->name;
            $i++;
        }
        $ret->fields = $field_array;
        if (!$ret) {
            return null;
        }

        $ret->nCols = mysqli_num_fields($metadata);

        $ret->stmt = $stmt;

        mysqli_free_result($metadata);
        return $ret;
    }

    private static function iimysqli_result_fetch_assoc(&$result)
    {
        global $global;
        $ret = [];
        $code = "return mysqli_stmt_bind_result(\$result->stmt ";
        for ($i = 0; $i < $result->nCols; $i++) {
            $ret[$result->fields[$i]] = null;
            $code .= ", \$ret['" . $result->fields[$i] . "']";
        };

        $code .= ");";
        if (!eval($code)) {
            return false;
        };
        if (!mysqli_stmt_fetch($result->stmt)) {
            return false;
        };
        return $ret;
    }

    private static function iimysqli_result_fetch_array(&$result)
    {
        $ret = [];
        $code = "return mysqli_stmt_bind_result(\$result->stmt ";

        for ($i = 0; $i < $result->nCols; $i++) {
            $ret[$i] = null;
            $code .= ", \$ret['" . $i . "']";
        };
        $code .= ");";
        if (!eval($code)) {
            return false;
        };
        if (!mysqli_stmt_fetch($result->stmt)) {
            return false;
        };
        return $ret;
    }
}

function log_error($err)
{
    if (!empty($global['debug'])) {
        echo $err;
    }
    _error_log("MySQL ERROR: ".json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)), AVideoLog::$ERROR);
    _error_log($err, AVideoLog::$ERROR);
}
