<?php
/**
 * Will look for semantic characters (like '*', or '-action') in an array
 * and try to explode it to a full blown array.
 *
 * The following code block can be utilized by PEAR's Testing_DocTest
 * <code>
 * // Input //
 * $data = array(
 *     '*' => array(
 *         '*' => 1
 *     ),
 *     'add' => array(
 *         'employee_id,modified' => 0,
 *         '-is_update' => 1
 *     ),
 *     'edit,list' => 1
 * );
 *
 * $allOptions[0] = array('index', 'list', 'add', 'edit', 'view');
 * $allOptions[1] = array('employee_id', 'is_update', 'task_id', 'created', 'modified');
 * 
 * // Execute //
 * expandKeys($data, $allOptions, true);
 * 
 * // Show //
 * print_r($data);
 * 
 * // expects:
 * // Array
 * // (
 * //     [0] => Kevin and Max go for walk in the park.
 * // )
 * </code>
 * 
 * @param array $data
 * @param array $allOptions list to use when '*' is encountered
 *
 * @return array
 */


function expandKeys(&$data = null, $allOptionsList = null, $recurse = false)
{
    if (empty($data)) {
        return array();
    }

    $operators = array(
        '-' => true,
        '+' => true,
        '=' => true
    );

    // Determine level of recursion
    // and set active allOptions
    if ($recurse !== false) {
        if ($recurse === true) {
            $recurse = 0;
        }
        $myOptionsList = &$allOptionsList[$recurse];
    } else {
        $myOptionsList = &$allOptionsList;
    }

    foreach($data as $key=>$val) {
        $origKey = $key;

        // Determine mutation: add, delete, replace
        $operator   = substr($key, 0, 1);
        if (isset($operators[$operator])) {
            $key = substr($key, 1, strlen($key));
        } else {
            // No mutation character defaults to: add
            $operator = '+';
        }

        // Determine selection
        $keys = array();
        if ($key == '*') {
            $keys = $myOptionsList;
        } else if (false !== strpos($key, ',')) {
            $keys = explode(',', $key);
        } else {
            $keys[] = $key;
        }

        // Recurse
        if (is_array($val) && $recurse !== false) {
            echo 'recursing: '.($recurse+1)." for $key: ".print_r($val, true);
            expandKeys($val, $allOptionsList, ($recurse + 1));
            echo 'done: '.print_r($val, true);
        }

        // Mutate data according to selection
        foreach($keys as $doKey) {
            switch($operator){
                case '-':
                    // Save unsets for later
                    if (is_array($val)) {
                        foreach($val as $k=>$v) {
                            echo 'Munsetting:'.$k."\n";
                            unset($data[$doKey][$k]);
                        }
                    } else {
                        echo 'unsetting: '.$doKey." in: ".print_r($data, true);
                        unset($data[$doKey]);
                        echo 'done: '.print_r($data, true);
                    }
                    break;
                case '=':
                    $data = array();
                case '+':
                    if (is_array($val)) {
                        foreach($val as $k=>$v) {
                            $data[$doKey][$k] = $v;
                        }
                    } else {
                        $data[$doKey] = $val;
                    }
                    
                    break;
            }
        }
        
        // Clean up Symbol keys
        if ($origKey !== $key) {
            echo 'REMOVING OLD KEY: '.$origKey." in: ".print_r($data, true);
            if (isset($data[$origKey])) unset($data[$origKey]);
            echo 'done: '.print_r($data, true);
        }
    }
}
?>