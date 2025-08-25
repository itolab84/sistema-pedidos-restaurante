<?php
// Find all references to payment_status in the database
require_once 'config/database.php';

$conn = getDBConnection();

echo "Searching for all references to 'payment_status' in the database...\n\n";

try {
    // Check all triggers again
    echo "=== CHECKING ALL TRIGGERS ===\n";
    $result = $conn->query("SHOW TRIGGERS");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (stripos($row['Statement'], 'payment_status') !== false) {
                echo "❌ FOUND payment_status in trigger: " . $row['Trigger'] . " on table: " . $row['Table'] . "\n";
                echo "Statement: " . $row['Statement'] . "\n\n";
            }
        }
    }
    
    // Check stored procedures
    echo "=== CHECKING STORED PROCEDURES ===\n";
    $result = $conn->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $proc_name = $row['Name'];
            $proc_result = $conn->query("SHOW CREATE PROCEDURE `$proc_name`");
            if ($proc_result) {
                $proc_row = $proc_result->fetch_assoc();
                if (stripos($proc_row['Create Procedure'], 'payment_status') !== false) {
                    echo "❌ FOUND payment_status in procedure: $proc_name\n";
                    echo "Definition: " . $proc_row['Create Procedure'] . "\n\n";
                }
            }
        }
    } else {
        echo "No stored procedures found.\n";
    }
    
    // Check functions
    echo "=== CHECKING FUNCTIONS ===\n";
    $result = $conn->query("SHOW FUNCTION STATUS WHERE Db = DATABASE()");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $func_name = $row['Name'];
            $func_result = $conn->query("SHOW CREATE FUNCTION `$func_name`");
            if ($func_result) {
                $func_row = $func_result->fetch_assoc();
                if (stripos($func_row['Create Function'], 'payment_status') !== false) {
                    echo "❌ FOUND payment_status in function: $func_name\n";
                    echo "Definition: " . $func_row['Create Function'] . "\n\n";
                }
            }
        }
    } else {
        echo "No functions found.\n";
    }
    
    // Check views
    echo "=== CHECKING VIEWS ===\n";
    $result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $view_name = $row['Tables_in_' . $conn->query("SELECT DATABASE()")->fetch_row()[0]];
            $view_result = $conn->query("SHOW CREATE VIEW `$view_name`");
            if ($view_result) {
                $view_row = $view_result->fetch_assoc();
                if (stripos($view_row['Create View'], 'payment_status') !== false) {
                    echo "❌ FOUND payment_status in view: $view_name\n";
                    echo "Definition: " . $view_row['Create View'] . "\n\n";
                }
            }
        }
    } else {
        echo "No views found.\n";
    }
    
    // Check foreign key constraints
    echo "=== CHECKING FOREIGN KEY CONSTRAINTS ===\n";
    $result = $conn->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND (COLUMN_NAME LIKE '%payment_status%' OR REFERENCED_COLUMN_NAME LIKE '%payment_status%')
    ");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "❌ FOUND payment_status in constraint: " . $row['CONSTRAINT_NAME'] . "\n";
            echo "Table: " . $row['TABLE_NAME'] . ", Column: " . $row['COLUMN_NAME'] . "\n";
            echo "References: " . $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . "\n\n";
        }
    } else {
        echo "No foreign key constraints with payment_status found.\n";
    }
    
    // Let's also check if there are any remaining triggers with payment_status
    echo "=== DOUBLE-CHECKING TRIGGERS FOR payment_status ===\n";
    $result = $conn->query("
        SELECT 
            TRIGGER_NAME,
            EVENT_MANIPULATION,
            EVENT_OBJECT_TABLE,
            ACTION_STATEMENT
        FROM information_schema.TRIGGERS 
        WHERE TRIGGER_SCHEMA = DATABASE()
        AND ACTION_STATEMENT LIKE '%payment_status%'
    ");
    
    if ($result && $result->num_rows > 0) {
        echo "❌ STILL FOUND TRIGGERS WITH payment_status:\n";
        while ($row = $result->fetch_assoc()) {
            echo "Trigger: " . $row['TRIGGER_NAME'] . " on table: " . $row['EVENT_OBJECT_TABLE'] . "\n";
            echo "Statement: " . $row['ACTION_STATEMENT'] . "\n\n";
        }
    } else {
        echo "✅ No triggers with payment_status found.\n";
    }
    
    echo "\n=== SEARCH COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
