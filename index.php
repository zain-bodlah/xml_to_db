<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'xml_to_db');
define('LOG_FILE', 'xml_to_db.log');
define('XML_FILE', 'feed.xml');
define('OUTPUT_FILE', 'processed_data.txt'); // Output file for saved values

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

function writeLog($message)
{
    $currentTime = date('Y-m-d H:i:s');
    $logMessage = "[{$currentTime}] {$message}\n";
    error_log($logMessage, 3, LOG_FILE);
}

function connectToDatabase()
{
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        writeLog("Error connecting to database: " . $e->getMessage());
        die("Failed to connect: " . $e->getMessage());
    }
}

function processXml($conn)
{
    try {
        $xml = simplexml_load_file(XML_FILE);

        $tableName = $xml->getName();
        $columns = [];
        foreach ($xml->children()[0]->children() as $child) {
            $columns[] = $child->getName();
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (";
        $sql .= implode(',', array_map(function ($col) {
            return "`{$col}` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        }, $columns));
        $sql .= ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $conn->exec($sql);

        $sql = "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES ";
        $placeholders = array_fill(0, count($columns), '?');
        $sql .= implode(',', $placeholders);

        $stmt = $conn->prepare($sql);
        $outputFile = fopen(OUTPUT_FILE, 'w');

        foreach ($xml->children() as $row) {
            $data = [];
            $rowData = ""; // String for output file
            foreach ($row->children() as $child) {
                $data[] = $child->__toString();
                $rowData .= $child->__toString() . "\t"; // Append data with tabs for separation
            }
            $stmt->execute($data);
            fwrite($outputFile, rtrim($rowData) . PHP_EOL); // Write data to file
        }

        fclose($outputFile);
        writeLog("XML data successfully processed and inserted into table '{$tableName}'. Saved to file: " . OUTPUT_FILE);
    } catch (Exception $e) {
        writeLog("Error processing XML: " . $e->getMessage());
    }
}

function test()
{
    $data = ['test_key' => 'test_value'];
    if (is_array($data) && !empty($data)) {
        writeLog("Test data is valid.");
    } else {
        writeLog("Test data is invalid.");
    }
}

$conn = connectToDatabase();
processXml($conn);

$conn = null;

echo "XML data processing complete. Check log file (" . LOG_FILE . ") for details.";

// test();
