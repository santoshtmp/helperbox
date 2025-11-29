<?php

namespace Drupal\helperbox\Helper;

use Drupal\node\Entity\NodeType;

/**
 * Util Helper class
 *
 * @package Drupal\helperbox\Helper
 */
class UtilHelper {

    /**
     * Logs exceptions with backtrace to a secure file.
     *
     * @param \Throwable $throwable
     *   The exception or error to log.
     *
     * @return void
     */
    public static function helperbox_error_log($th) {
        // Define the log file path
        $log_file = \Drupal::root() . '/sites/default/files/helperbox_error_log.txt';
        // Get the backtrace to find the original file where the error occurred
        $backtrace = debug_backtrace();
        $initial_error_file = isset($backtrace[1]['file']) ? $backtrace[1]['file'] : '';
        $initial_error_line = isset($backtrace[1]['line']) ? $backtrace[1]['line'] : '';
        // Format the log message
        $log_message = "[" . date("Y-m-d H:i:s") . "] ERROR: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine();
        if ($initial_error_file && $initial_error_line) {
            $log_message .= " | Initial Error File: " . $initial_error_file . " on line " . $initial_error_line . PHP_EOL;
        } else {
            $log_message .=  PHP_EOL;
        }
        // Ensure the log file is writable
        if (is_writable(dirname($log_file))) {
            error_log($log_message, 3, $log_file);
        }
        \Drupal::messenger()->addMessage(json_encode($log_message), 'yi_error_message');
    }

    /**
     * Gell all content type list 
     */
    public static function get_all_node_content_type() {
        // Get all content types
        $contentTypeOptions = [];
        $node_types = NodeType::loadMultiple();
        foreach ($node_types as $machine_name => $type) {
            $contentTypeOptions[$machine_name] = $type->label();
        }
        return $contentTypeOptions;
    }

    // END
}
