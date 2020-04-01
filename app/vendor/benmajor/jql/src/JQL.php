<?php

namespace BenMajor\JQL;

class JQL
{
    private $fields    = '*';
    private $where     = [ ];
    private $order     = [ ];
    private $updates   = [ ];
    private $limitAmt  = null;
    private $offsetAmt = 0;
    
    # The locale (used for translating day and month names):
    private $locale    = 'en_US';
    private $timezone;
    
    private $data;
    private $assoc;
    private $result;
    
    # Regular expression to identify functions in the SELECT clause:
    private $functionRegEx          = '/([A-Z_]+)\s*([(](.*)[)])\s*([AS|as]*+)\s*([a-zA-z0-9]*+)/';
    private $whereFuncRegExIn       = '/([a-zA-z0-9]+)\s*([NOT\sIN]+)\s*([(])(...+)([)])/';
    
    # Regular expression to identify field aliasing:
    private $aliasRegEx             = '/([a-zA-Z0-9_-]+)\s*([AS]+)\s*([a-zA-Z0-9_]+)/';
    
    # Holds the current working mode:
    private $mode;
    
    # Definition of permitted function names:
    private $allowedFunctions = [
        # String functions:
        'APPEND', 'CHAR_LENGTH', 'CHARACTER_LENGTH', 'CONCAT', 'CONCAT_WS', 'DISTINCT', 'FORMAT', 'LCASE', 'LEFT', 'LOWER', 'LPAD', 'LTRIM', 'PREPEND', 'REPLACE', 'REVERSE', 'RIGHT', 'RPAD', 'RTRIM', 'SUBSTR', 'SUBSTRING', 'TRIM', 'UCASE', 'UPPER', 
        
        # Numeric functions:
        'ABS', 'ACOS', 'ASIN', 'ATAN', 'CEIL', 'CEILING', 'COS', 'COT', 'FLOOR', 'RAND', 'RANDOM', 'ROUND', 'SIN', 'SQRT', 'TAN',
        
        # Date functions:
        'CURDATE', 'CURRENT_DATE', 'CURTIME', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'DATE_FORMAT', 'DAY', 'DAYNAME', 'DAYOFMONTH', 'DAYOFWEEK', 'DAYOFYEAR', 'HOUR', 'LAST_DAY', 'MINUTE', 'MONTH', 'MONTHNAME', 'NOW', 'SECOND', 'TIMESTAMP', 'WEEK', 'YEAR',
        
        # Aggregate functions:
        'AVG', 'MAX', 'MIN', 'SUM'
    ];
    
    # Definition of permitted UPDATE function names:
    private $allowedUpdateFunctions = [
        'APPEND', 'CONCAT_WS', 'LCASE', 'LEFT', 'LOWER', 'LPAD', 'LTRIM', 'PREPEND', 'REPLACE', 'REVERSE', 'RIGHT', 'RPAD', 'RTRIM', 'SUBSTR', 'SUBSTRING', 'TRIM', 'UCASE', 'UPPER'
    ];
    
    # Definition of permitted WHERE function names:
    private $allowedWhereFunctions = [ 'CONTAINS', 'IN', 'NOT IN' ];
    
    # Definition of aggregate functions:
    private $aggregateFunctions    = [ 'AVG', 'MAX', 'MIN', 'SUM' ];
    
    function __construct( $json )
    {
        if( is_string($json) )
        {
            $decoded = json_decode($json, true);
            $error   = json_last_error();
            
            # There was an error parsing the JSON string -- it might be a file:
            if( $error !== JSON_ERROR_NONE )
            {
                if( file_exists($json) )
                {
                    $decoded = json_decode( file_get_contents($json), true );
                    $error   = json_last_error();
                    
                    if( $error !== JSON_ERROR_NONE )
                    {
                        throw new QueryException('Error parsing JSON file: '.$error);
                    }
                }
                else
                {
                    throw new QueryException('Error parsing JSON string: '.$error);
                }
            }
            
            $this->data = $decoded;
        }
        elseif( is_object($json) || is_array($json) )
        {
            $this->data = $json;
        }
        else
        {
            throw new QueryException('Parameter passed to JQL must be a valid JSON string or object / array.');
        }
        
        $this->assoc = json_decode( json_encode($this->data), true );
        
        $this->setLocale();
        $this->setTimezone( date_default_timezone_get() );
    }
    
    # Set the locale:
    public function setLocale( string $locale = 'en_US' )
    {
        $this->locale = $locale;
        setlocale(LC_TIME, $this->locale);
        
        return $this;
    }
    
    # Set the timezone:
    public function setTimezone( string $timezone = 'Europe/London' )
    {
        $this->timezone = $timezone;
        
        date_default_timezone_set( $this->timezone );
        
        return $this;
    }

    # Reset everything (so we can use the same object):
    public function reset()
    {
        $this->fields    = '*';
        $this->where     = [ ];
        $this->order     = [ ];
        $this->updates   = [ ];
        $this->limitAmt  = null;
        $this->offsetAmt = 0;

        # Return the object to preserve method-chaining:
        return $this;
    }
    
    # Start by adding which fields to select:
    public function select( $fields )
    {
        # Clear:
        $this->reset();
        
        $this->mode = 'select';
        
        if( is_array($fields) )
        {
            foreach( $fields as $field )
            {
                if( ! is_string($field) )
                {
                    throw new QueryException('Parameter passed to select() must be an array of strings or a string of *.');
                }
            }
            
            # If we already have some fields, merge them:
            if( is_array($this->fields) )
            {
                $this->fields = array_merge($this->fields, $fields);
            }
            
            # It's a * string, overwrite:
            else
            {
                $this->fields = $fields;
            }
        }
        elseif( $fields == '*' )
        {
            $this->fields = '*';
        }
        else
        {
            throw new QueryException('Parameter passed to select() must be an array of strings or a string of *.');
        }
        
        # Return the object to preserve method-chaining:
        return $this;
    }
    
    #ÊHandle update:
    public function update( $updates )
    {
        $this->mode    = 'update';
        $this->updates = $updates;
        
        return $this;
    }
    
    # Add a where clause:
    public function where( string $where )
    {
        #  Give preference to OR queries:
        if( strstr($where, ' OR ') )
        {
            $operand = 'OR';
            $parts = explode(' OR ', $where);
        }
        else
        {
            $operand = 'AND';
            $parts = explode(' AND ', $where);
        }
        
        $clauses = [
            'type' => $operand,
            'ops'  => [ ]
        ];

        foreach( $parts as $clause )
        {
            # Handle NULL:
            if( strstr($clause, ' IS NULL')  )
            {
                $clauseParts = explode('IS NULL', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => '=',
                    'compare' => null
                ];
            }
            
            # Handle NOT NUll:
            elseif( strstr($clause, ' IS NOT NULL') )
            {
                $clauseParts = explode('IS NOT NULL', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => '!=',
                    'compare' => null
                ];
            }
            
            # Handle EMPTY:
            elseif( strstr($clause, ' IS EMPTY') )
            {
                $clauseParts = explode(' IS EMPTY', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'empty',
                    'compare' => null
                ];
            }
            
            # Handle NOT EMPTY:
            elseif( strstr($clause, ' IS NOT EMPTY') )
            {
                $clauseParts = explode(' IS NOT EMPTY', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => '!empty',
                    'compare' => null
                ];
            }
            
            # Handle LIKE:
            elseif( strstr($clause, ' LIKE ') )
            {
                $clauseParts = explode(' LIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'LIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle SLIKE:
            elseif( strstr($clause, ' SLIKE ') )
            {
                $clauseParts = explode(' SLIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'SLIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle LIKE:
            elseif( strstr($clause, ' NOT LIKE ') )
            {
                $clauseParts = explode(' LIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'NOT LIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle SLIKE:
            elseif( strstr($clause, ' NOT SLIKE ') )
            {
                $clauseParts = explode(' SLIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'NOT SLIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # CONTAINS:
            elseif( strstr($clause, ' CONTAINS ') )
            {
                $clauseParts = explode(' CONTAINS ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'CONTAINS',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # IN / NOT IN:
            elseif( preg_match($this->whereFuncRegExIn, $clause, $clauseParts) )
            {
                # Convert the parameter to an array:
                $array = explode(',', $clauseParts[4]);
                
                # Trim the array values:
                array_walk( $array, function(&$item) { $item = trim($item); $item = trim($item, "'"); });
            
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[1]),
                    'operand' => $clauseParts[2],
                    'compare' => $array
                ];
            }
            
            # It's a normal operator:
            else
            {
                $operands = [ '==', '>=', '<=', '<>', '!=', '=', '<', '>' ];
                
                foreach( $operands as $find )
                {
                    if( strstr($clause, $find) )
                    {
                        $clauseParts = explode($find, $clause);
                        
                        $clauses['ops'][] = [
                            'column'  => trim($clauseParts[0]),
                            'operand' => $find,
                            'compare' => trim(end($clauseParts))
                        ];

                        break;
                    }
                }
            }
        }
        
        $this->where = $clauses;
        
        # Return the object to preserve method-chaining:
        return $this;
    }
    
    # Set the order:
    public function order( string $field, string $dir = 'ASC' )
    {
        $dir = strtoupper($dir);
        
        if( ! in_array($dir, [ 'ASC', 'DESC' ]) )
        {
            throw new QueryException('Invalid sort order specified. Must be one of: ASC, DESC.');
        }
        
        $this->order[$field] = $dir;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Set the limit and optionally the offset:
    public function limit( int $limit, int $offset = null )
    {
        $this->limitAmt = $limit;
        
        if( ! is_null($offset) )
        {
            $this->offset($offset);
        }
        
        return $this;
    }
    
    # Set the offset explicitly:
    public function offset( int $offset = 0 )
    {
        $this->offsetAmt = $offset;
        
        return $this;
    }
    
    # Return the first example:
    public function fetchOne()
    {
        $this->execute();
        
        return (count($this->result) > 0) ? $this->result[0] : null;
    }
    
    # Return all matches:
    public function fetch()
    {
        $this->execute();
        
        if( $this->limitAmt != null && count($this->result) > 0 )
        {
            return array_slice($this->result, $this->offsetAmt, $this->limitAmt);
        }
        
        return (count($this->result) > 0 ) ? $this->result : null;
    }
    
    # Fetch all matches as JSON:
    public function fetchAsJSON( bool $pretty = false)
    {
        return ($pretty) ? json_encode($this->fetch(), JSON_PRETTY_PRINT)
                         : json_encode($this->fetch());
    }
    
    # Fetch one result as JSON:
    public function fetchOneAsJSON( bool $pretty = false )
    {
        return json_encode(
            $this->fetchOne
        );
    }
    
    # Save the output to a JSON file:
    public function saveAsFile( $filename )
    {
        # Open a file pointer:
        $fh = fopen( $filename, 'w+' );
        
        # Write the JSOn:
        $result = fwrite( $fh, $this->fetchAsJSON( true ) );
        
        # Close the file handler:
        fclose($fh);
        
        return ($result > 0);
    }
    
    # Count the number of matches:
    public function count()
    {
        $this->execute();
        
        return count($this->result);
    }
    
    # Execute the query:
    private function execute()
    {
        $matches  = [ ];
        $return   = [ ];
        
        # Is there a WHERE clause?
        if( ! empty($this->where) && $this->mode == 'select' )
        {
            $matches = $this->execute_where();
        }
        
        # No where clause, so just assign all entries:
        elseif( (empty($this->where) && $this->mode == 'select') || $this->mode == 'update' )
        {
            if( is_array($this->data) )
            {
                $matches = $this->assoc;
            }
            else
            {
                $matches[] = $this->assoc;
            }
        }
        
        # Now we need to sort the matches:
        if( ! empty($this->order) )
        {
            uasort($matches, function($a, $b) {
                
                $c = 0;
                
                foreach( $this->order as $field => $direction )
                {
                    if( is_numeric($a[$field]) )
                    {
                        $c.= ($direction == 'ASC') ? $a[$field] - $b[$field]
                                                   : $b[$field] - $a[$field];
                    }
                    else
                    {
                        $c.= ($direction == 'ASC') ? $a[$field] > $b[$field]
                                                   : $b[$field] > $a[$field];
                    }
                }
                
                return $c;
            });
        }
        
        
        # If it's a select, we only want to return the specified rows:
        if( $this->mode == 'select' )
        {
            # Handle the select:
            if( $this->fields != '*' && count($this->fields) )
            {
                # Loop over the matches and handle the fields:
                foreach( $matches as $match )
                {
                    $tmp = [ ];
                    
                    # Loop over the fields and handle them:
                    foreach( $this->fields as $field )
                    {
                        # If it's a function, execute it:
                        if( preg_match($this->functionRegEx, $field, $functionParts) )
                        {
                            # Map the matches:
                            $functionName = $functionParts[1];
                            $alias        = $functionParts[5];
                            $parameters   = explode(',', $functionParts[3]);
                            
                            if( ! in_array($functionName, $this->allowedFunctions) )
                            {
                                throw new SyntaxException('Unknown function: '.$functionName);
                            }
                            
                            # The name of the key:
                            $key = (empty($alias)) ? $field : $alias;
                            
                            # Is it an aggregate function?
                            if( in_array($functionName, $this->aggregateFunctions) )
                            {
                                $value = $this->execute_aggregate( $functionName, (empty($parameters) ? [ ] : $parameters), $matches );
                            }
                            else
                            {
                                $value = $this->execute_function( $functionName, (empty($parameters) ? [ ] : $parameters), $match );
                            }
                            
                            $tmp[ $key ] = $value;
                        }
                        else
                        {
                            # Are we casting the field to an alias?
                            if( preg_match($this->aliasRegEx, $field, $aliasParts) )
                            {
                                $tmp[ $aliasParts[3] ] = $match[ $aliasParts[1] ];
                            }
                            else
                            {
                                $tmp[$field] = $match[$field];
                            }
                        }
                    }
                    
                    $return[] = $tmp;
                }
            }
            else
            {
                $return = $matches;
            }
        }
        else
        {
            #ÊHandle the updates:
            if( ! empty($this->updates) )
            {
                # Loop over the matches:
                foreach( $matches as &$match )
                {
                    # Loop over the updates and execute them:
                    foreach( $this->updates as $field => $update )
                    {
                        # Does it match the WHERE clause?
                        if( !empty($this->where) )
                        {
                            $type        = $this->where['type'];
                            $clauseCount = count($this->where['ops']);
                            
                            $matchCount  = 0;
                                
                            foreach( $this->where['ops'] as $clause )
                            {
                                if( $this->check_clause( $clause, $match ) )
                                {
                                    $matchCount++;
                                }
                            }
                                
                            if( ($type == 'AND' && $matchCount == $clauseCount) || ($type == 'OR' && $matchCount > 0) )
                            {
                                # Is it a function?
                                if( preg_match($this->functionRegEx, $update, $functionParts) )
                                {
                                    $functionName = $functionParts[1];
                                    $args         = explode(',', $functionParts[3]);
                                    
                                    if( ! in_array($functionName, $this->allowedUpdateFunctions) )
                                    {
                                        throw new SyntaxException('Unknown function: '.$function);
                                    }
                                    
                                    $match[$field] = $this->execute_function( $functionName, $args, $match);
                                }
                                else
                                {
                                    # Is it a forced-string replacement?
                                    if( preg_match('/[\'](.*)[\']/', $update, $updateParts) || ! array_key_exists($update, $match) )
                                    {
                                        $match[$field] = (isset($updateParts) && count($updateParts)) ? $updateParts[1] : $update;
                                    }
                                    
                                    # It's another field:
                                    else
                                    {
                                        $match[$field] = $match[$update];
                                    }
                                }
                            }
                            
                        }
                        else
                        {
                            # Is it a function?
                            if( preg_match($this->functionRegEx, $update, $functionParts) )
                            {
                                $functionName = $functionParts[1];
                                $args         = explode(',', $functionParts[3]);
                                
                                if( ! in_array($functionName, $this->allowedUpdateFunctions) )
                                {
                                    throw new SyntaxException('Unknown function: '.$function);
                                }
                                
                                $match[$field] = $this->execute_function( $functionName, $args, $match);
                            }
                            else
                            {
                                # Is it a forced-string replacement?
                                if( preg_match('/[\'](.*)[\']/', $update, $updateParts) || ! array_key_exists($update, $match) )
                                {
                                    $match[$field] = (isset($updateParts) && count($updateParts)) ? $updateParts[1] : $update;
                                }
                                
                                # It's another field:
                                else
                                {
                                    $match[$field] = $match[$update];
                                }
                            }
                        }
                    }
                }
            }
            
            $return = $matches;
        }
        
        $this->result = $return;
    }
    
    # Handle the WHERE clause and return the matched subset of rows:
    private function execute_where()
    {
        $type        = $this->where['type'];
        $clauseCount = count($this->where['ops']);
        $matches     = [ ];
        
        foreach( $this->assoc as $row )
        {
            $matchCount  = 0;
            
            foreach( $this->where['ops'] as $clause )
            {
                if( $this->check_clause( $clause, $row ) )
                {
                    $matchCount++;
                }
            }
            
            if( ($type == 'AND' && $matchCount == $clauseCount) || ($type == 'OR' && $matchCount > 0) )
            {
                $matches[] = $row;
            }
        }
        
        return $matches;
    }
    
    # Actually run a function:
    private function execute_function( $function, $args = [ ], $row )
    {   
        # Clean up the arguments:
        $args = array_filter($args);
        array_walk( $args, function(&$item) { $item = trim($item); $item = trim($item, "'"); });
        
        $value = null;
        
        /****************************/
        /*                          */
        /*     STRING FUNCTIONS     */
        /*                          */
        /****************************/
        
        # Add the specified string to the end of another string
        if( $function == 'APPEND' )
        {
            if( count($args) < 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            $string = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            $append = (array_key_exists($args[1], $row)) ? $row[$args[1]] : $args[1];
            
            $value = $string.$append;
        }
        
        # Add the specified string to the start of another string
        if( $function == 'PREPEND' )
        {
            if( count($args) < 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            $string  = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            $prepend = (array_key_exists($args[1], $row)) ? $row[$args[1]] : $args[1];
            
            $value = $prepend.$string;
        }
        
        # Returns the length of a string (in characters)
        elseif( $function == 'CHAR_LENGTH' || $function == 'CHARACTER_LENGTH' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $value = (array_key_exists($args[0], $row)) ? strlen( $row[$args[0]] ) : strlen($args[0]);
        }
        
        # Adds two or more expressions together
        elseif( $function == 'CONCAT' )
        {
            if( count($args) < 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $tmp = [ ];
            
            foreach( $args as $field )
            {
                $tmp[] = (array_key_exists($field, $row)) ? $row[$field] : $field;
            }
            
            $value = implode($tmp);
        }
        
        # Adds two or more expressions together with a separator
        elseif( $function == 'CONCAT_WS' )
        {
            if( count($args) < 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            $tmp = [ ];
            $sep = $args[0];
            
            foreach( array_slice($args, 1) as $field )
            {
                $tmp[] = (array_key_exists($field, $row)) ? $row[$field] : $field;
            }
            
            $value = implode($sep, $tmp);
        }
        
        # Selects all unique values for the given field (assumes fields are arrays):
        elseif( $function == 'DISTINCT' )
        {
            if( count($args) < 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }

            $values = [ ];

            foreach( $args as $field )
            {
                if( array_key_exists($field, $row) )
                {
                    $values = array_merge($values, (is_array($row[$field]) ? $row[$field] : [ $row[$field] ]));
                }
            }

            $value = array_unique($values);
        }

        # Formats a number to a format like "#,###,###.##", rounded to a specified number of decimal places
        elseif( $function == 'FORMAT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( !ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            if( ! is_numeric($operator) )
            {
                $operator = 0;
            }
            
            $value = number_format( $operator, $args[1], '.', ',' );
        }
        
        # Converts a string to lower-case
        elseif( $function == 'LCASE' || $function == 'LOWER' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strtolower($operator);
        }
        
        # Extracts a number of characters from a string (starting from left)
        elseif( $function == 'LEFT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, 0, $args[1]);
        }
        
        # Left-pads a string with another string, to a certain length
        elseif( $function == 'LPAD' || $function == 'RPAD' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            # Is the second parameter a number?
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = str_pad($operator, $args[1], $args[2], (($function == 'LPAD') ? STR_PAD_LEFT : STR_PAD_RIGHT));
        }
        
        # Removes leading spaces from a string
        elseif( $function == 'LTRIM' || $function == 'RTRIM' || $function == 'TRIM' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            if( $function == 'LTRIM' )
            {
                $value = ltrim($operator);
            }
            elseif( $function == 'RTRIM' )
            {
                $value = rtrim($operator);
            }
            else
            {
                $value = trim($operator);
            }
        }
        
        # Replaces all occurrences of a substring within a string, with a new substring
        elseif( $function == 'REPLACE' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = str_replace($args[1], $args[2], $operator);
        }
        
        # Reverses a string and returns the result
        elseif( $function == 'REVERSE' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strrev($operator);
        }
        
        # Extracts a number of characters from a string (starting from right)
        elseif( $function == 'RIGHT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, (0 - $args[1]));
        }
        
        # Extracts a substring from a string (starting at any position)
        elseif( $function == 'SUBSTR' || $function == 'SUBSTRING' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            if( ! ctype_digit($args[2]) )
            {
                throw new SyntaxException('Third parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, $args[1], $args[2]);
        }
        
        # Converts a string to upper-case
        elseif( $function == 'UCASE' || $function == 'UPPER' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strtoupper($operator);
        }
        
        /****************************/
        /*                          */
        /*    NUMERIC  FUNCTIONS    */
        /*                          */
        /****************************/
        
        # Returns the absolute value of a number
        elseif( $function == 'ABS' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = abs($operator);
        }
        
        # Returns the arc cosine of a number
        elseif( $function == 'ACOS' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = acos($operator);
        }
        
        # Returns the arc sine of a number
        elseif( $function == 'ASIN' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = asin($operator);
        }
        
        # Returns the arc tangent of a number
        elseif( $function == 'ATAN' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = atan($operator);
        }
        
        # Returns the smallest integer value that is >= to a number
        elseif( $function == 'CEIL' || $function == 'CEILING' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = ceil($operator);
        }
        
        # Returns the cosine of a number
        elseif( $function == 'COS' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = cos($operator);
        }
        
        # Returns the cotangent of a number
        elseif( $function == 'COT' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = cot($operator);
        }
        
        # Returns the largest integer value that is <= to a number
        elseif( $function == 'FLOOR' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = floor($operator);
        }
        
        # Returns a random number
        elseif( $function == 'RAND' || $function == 'RANDOM' )
        {
            $cleaned = array_filter($args);
            
            if( count($cleaned) > 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 0, 1 or 2 parameters.');
            }
            
            # Seed the Mersene Twister RNG:
            if( count($cleaned) == 1 )
            {
                mt_srand( $cleaned[0] );
                $value = mt_rand();
            }
            elseif( count($cleaned) == 2 )
            {
                $value = mt_rand($cleaned[0], $cleaned[1]);
            }
            else
            {
                $value = mt_rand();
            }
        }
        
        # Rounds a number to a specified number of decimal places
        elseif( $function == 'ROUND' )
        {
            if( count($args) > 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects 1 or 2 parameters.');
            }
            
            # A precision has been specified:
            if( count($args) > 1 )
            {
                if( ! ctype_digit($args[1]) )
                {
                    throw new SyntaxException('Parameter 1 for function '.$function.' must be an integer.');
                }
                
                $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
                $value = round($operator, $args[1]);
            }
            else
            {
                $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
                $value = round($operator);
            }
        }
        
        # Returns the sine of a number
        elseif( $function == 'SIN' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = sin($operator);
        }
        
        # Returns the square root of a number
        elseif( $function == 'SQRT' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = sqrt($operator);
        }
        
        # Returns the tangent of a number
        elseif( $function == 'TAN' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (float) (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = tan($operator);
        }
        
        
        /****************************/
        /*                          */
        /*      DATE  FUNCTIONS     */
        /*                          */
        /****************************/
        
        # Returns the current date
        elseif( $function == 'CURDATE' || $function == 'CURRENT_DATE' )
        {
            if( count($args) )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 0 parameters.');
            }
            
            $value = date('Y-m-d');
        }
        
        # Returns the current time:
        elseif( $function == 'CURTIME' || $function == 'CURRENT_TIME' )
        {
            if( count($args) )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 0 parameters.');
            }
            
            $value = date('H:i:s');
        }
        
        # Returns the number of seconds since the Unix epoch:
        elseif( $function == 'CURRENT_TIMESTAMP' )
        {
            if( count($args) )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 0 parameters.');
            }
            
            $value = time();
        }
        
        # Formats a date
        elseif( $function == 'DATE_FORMAT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = strftime($args[1], $operator);
        }
        
        # Returns the day of the month for a given date
        elseif( $function == 'DAY' || $function == 'DAYOFMONTH' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('j', $operator);
        }
        
        # Returns the weekday name for a given date
        elseif( $function == 'DAYNAME' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = strftime('%A', $operator);
        }
        
        # Returns the weekday index for a given date
        elseif( $function == 'DAYOFWEEK' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('N', $operator);
        }
        
        # Returns the day of the year for a given date
        elseif( $function == 'DAYOFYEAR' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('z', $operator);
        }
        
        # Returns the hour part for a given date
        elseif( $function == 'HOUR' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('H', $operator);
        }
        
        # Extracts the last day of the month for a given date
        elseif( $function == 'LAST_DAY' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('t', $operator);
        }
        
        # Returns the minute part of a time/datetime
        elseif( $function == 'MINUTE' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('i', $operator);
        }
        
        # Returns the month part for a given date
        elseif( $function == 'MONTH' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('m', $operator);
        }
        
        # Returns the name of the month for a given date
        elseif( $function == 'MONTHNAME' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = strftime('%B', $operator);
        }
        
        # Returns the current date and time
        elseif( $function == 'NOW' )
        {
            if( count($args) )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 0 parameters.');
            }
            
            $value = date('Y-m-d H:i:s');
        }

        # Returns the seconds part of a time/datetime
        elseif( $function == 'SECOND' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('s', $operator);
        }
        
        # Returns a datetime value based on a date or datetime value
        elseif( $function == 'TIMESTAMP' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('U', $operator);
        }
        
        # Returns the week number for a given date
        elseif( $function == 'WEEK' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('W', $operator);
        }
        
        # Returns the year part for a given date
        elseif( $function == 'YEAR' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = strtotime( (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0] );
            
            $value = date('Y', $operator);
        }
        
        return $value;
    }
    
    # Aggregate functions have to be handled differently, because we need to pass the whole
    # result set in, not just a single row:
    private function execute_aggregate( $function, $args = [ ], $rows )
    {
        # Clean up the arguments:
        array_walk( $args, function(&$item) { $item = trim($item); $item = trim($item, "'"); });
        
        $value = null;
        
        # Returns the average value of an expression
        if( $function == 'AVG' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $total   = 0;
            $numRows = count($rows);
            $found   = 0;
            
            foreach( $rows as $row )
            {
                if( array_key_exists($args[0], $row) )
                {
                    $total+= $row[ $args[0] ];
                    $found++;
                }
            }
            
            if( ! $found )
            {
                throw new SyntaxException('Specified column \''.$args[0].'\' does not exist in specified dataset.' );
            }
            
            return $total / $numRows;
        }

        # Return the maximum value for a field:
        elseif( $function == 'MAX' || $function == 'MIN' )
        {
            $values = [ ];

            foreach( $rows as $row )
            {
                if( array_key_exists($args[0], $row) )
                {
                    $values[] = $row[ $args[0] ];
                }
            }

            return ($function == 'MAX') ? max($values) : min($values);
        }
        
        # Calculates the sum of a set of values
        elseif( $function == 'SUM' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $total   = 0;
            $found   = 0;
            
            foreach( $rows as $row )
            {
                if( array_key_exists($args[0], $row) )
                {
                    $total+= $row[ $args[0] ];
                    $found++;
                }
            }
            
            if( ! $found )
            {
                throw new SyntaxException('Specified column \''.$args[0].'\' does not exist in specified dataset.' );
            }
            
            return $total;
        }
    }
    
    # Run a where clause:
    private function check_clause( $clause, $row )
    {
        # This is the map of comparrison keywords:
        $keywordMap = [
            'null'  => null,
            'true'  => true,
            'false' => false,
        ];
        
        $value = (array_key_exists($clause['column'], $row)) ? $row[ $clause['column'] ] : null;
        
        if( ! is_array($clause['compare']) )
        {
            $compare = (array_key_exists(strtolower($clause['compare']), $keywordMap)) ? $keywordMap[strtolower($clause['compare'])] : $clause['compare'];
        }
        else
        {
            $compare = $clause['compare'];
        }
        
        # LIKE clauses a bit different:
        if( in_array($clause['operand'], [ 'LIKE', 'SLIKE', 'NOT LIKE', 'NOT SLIKE' ]) )
        {
            $first = $compare[0];
            $last  = $compare[( strlen($compare) - 1)];
            
            $clean        = strtolower($value);
            $cleanCompare = strtolower($compare);
            
            # Is it a wildcard?
            if( $first == '%' || $last == '%')
            {
                # Start AND end are wildcards:
                if( $first == $last )
                {
                    $compareClean = trim($compare, '%');
                    
                    switch( $clause['operand'] )
                    {
                        case 'LIKE':
                            return strstr(strtolower($value), strtolower($compareClean));
                            break;
                        
                        case 'NOT LIKE':
                            return ! strstr(strtolower($value), strtolower($compareClean));
                            break;
                        
                        case 'SLIKE':
                            return strstr($value, $compareClean);
                            break;
                        
                        case 'NOT SLIKE':
                            return ! strstr($value, $compareClean);
                            break;
                    }
                }
                
                # Only beginning:
                elseif( $first == '%' || $last == '%' )
                {
                    if( $first == '%' )
                    {
                        $finder = substr($compare, 1);
                        $substr = substr($value, (0 - strlen($finder)));
                    }
                    else
                    {
                        $finder = substr($compare, -1);
                        $substr = substr($value, 0, (strlen($finder)));
                    }                    
                    
                    switch( $clause['operand'] )
                    {
                        case 'LIKE':
                            return strtolower($finder) == strtolower($substr);
                            break;
                        
                        case 'NOT LIKE':
                            return strtolower($finder) != strtolower($substr);
                            break;
                        
                        case 'SLIKE':
                            return $finder == $substr;
                            break;
                        
                        case 'NOT SLIKE':
                            return $finder != $substr;
                            break;
                    }
                    
                }
            }
            else
            {
                switch( $clause['operand'] )
                {
                    case 'LIKE':
                        return $clean == $cleanCompare;
                        break;
                    
                    case 'NOT LIKE':
                        return $clean != $cleanCompare;
                        break;
                    
                    case 'SLIKE':
                        return $value == $compare;
                        break;
                    
                    case 'NOT SLIKE':
                        return $value != $compare;
                        break;
                }
            }
        }
        
        # NULL:
        
        # IN() function:
        elseif( $clause['operand'] == 'IN' )
        {
            return in_array(
                $row[ $clause['column'] ],
                $clause['compare']
            );
        }
        
        # NOT IN() function:
        elseif( $clause['operand'] == 'NOT IN' )
        {
            return ! in_array(
                (array_key_exists($clause['column'], $row)) ? $row[$clause['column']] : [ ],
                $clause['compare']
            );
        }
        
        # CONTAINS function:
        elseif( $clause['operand'] == 'CONTAINS' )
        {
            if( isset($row[$clause['column']]) && ! is_array($row[$clause['column']]) )
            {
                throw new QueryException('CONTAINS must be called on a column containing an array.');
            }
            
            if( ! isset($row[$clause['column']]) )
            {
                return false;
            }
            
            return in_array(
                $clause['compare'],
                $row[ $clause['column'] ]
            );
        }
        
        # EMPTY function:
        elseif( $clause['operand'] == 'empty' )
        {
            if( isset($row[$clause['column']]) && ! is_array($row[$clause['column']]) )
            {
                throw new QueryException('CONTAINS must be called on a column containing an array.');
            }
            
            if( ! isset($row[$clause['column']]) )
            {
                return true;
            }
            
            return empty($row[ $clause['column'] ]);
        }
        
        # EMPTY function:
        elseif( $clause['operand'] == '!empty' )
        {
            if( isset($row[$clause['column']]) && ! is_array($row[$clause['column']]) )
            {
                throw new QueryException('CONTAINS must be called on a column containing an array.');
            }
            
            if( ! isset($row[$clause['column']]) )
            {
                return false;
            }
            
            return !empty($row[ $clause['column'] ]);
        }
        
        # Basic operations:
        else
        {
            switch( $clause['operand'] )
            {
                case '=':
                    if( is_scalar($value) && is_scalar($compare) )
                    {
                        return strtolower($value) == strtolower($compare);
                    }
                    else
                    {
                        return false;
                    }
                    break;
                
                case '==':
                    return $value == $compare;
                    break;
                
                case '>':
                    return $value > $compare;
                    break;
                
                case '<':
                    return $value < $compare;
                    break;
                
                case '!=':
                    return $value != $compare;
                    break;
                
                case '<=':
                    return $value <= $compare;
                    break;
                
                case '>=':
                    return $value >= $compare;
                    break;
                
                case '<>':
                    return $value <> $compare;
                    break;
            }
        }
        
        return false;
    }
}