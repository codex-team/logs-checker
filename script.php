<?php

/** Max logs length in message */
define('MAX_CONTENT_SIZE', 1024 * 4);

/**
* Functions for working with log journals
*/
class Journal
{
    public $domain          = '';
    public $logFilePath     = '';
    public $codexBotLink    = '';

    private $message        = '';
    private $archiveName    = '';
    private $newLog         = '';

    function __construct($journal = array())
    {
        foreach ($journal as $key => $value) {
            $this->$key = $value;
        }

        if (!$this->isLogFileExist()) return false;
        $this->setArchiveName();
    }

    /**
     * Check if script has enough access to log file
     */
    private function isLogFileExist()
    {

        if (!file_exists($this->logFilePath)) {
            /** create empty log file */
            file_put_contents($this->logFilePath, '');
            return false;
        } else if (!is_readable($this->logFilePath)) {
            $this->sendMessage(sprintf("The file '%s' is not readable. Error log monitor cannot continue.", $this->logFilePath));
            return false;
        } else if (!is_writable($this->logFilePath)) {
            $this->sendMessage(sprintf("The file '%s' is not writable. Error log monitor cannot continue.", $this->logFilePath));
            return false;
        }

        return true;
    }

    /**
     * Create filename for logs archive
     */
    private function setArchiveName()
    {
        $this->archiveName = sprintf('archives/%s__%s', date('Y-m'), $this->logFilePath);
    }

    /**
     * Send request to the CodeX Bot for creating notify message
     */
    private function sendMessage($message = '')
    {
        if (!$message) { return; }

        if (!$this->codexBotLink) {
            echo $message;
            return;
        }

        $data = array('message' => $message);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,  $this->codexBotLink);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Create report message with logs
     *
     * @return string
     */
    private function composeReport($logFileContent = '')
    {
        $content = $this->newLog = $logFileContent;

        $content = substr($content, 0, MAX_CONTENT_SIZE);

        $isContentBiggerThanMaxSize = strlen($this->newLog) > MAX_CONTENT_SIZE;

        if ($isContentBiggerThanMaxSize) {
            $content = substr($content, 0, strrpos($content, PHP_EOL));
        }

        $message = str_replace(PHP_EOL, PHP_EOL . PHP_EOL, $content);

        $message = '🆘 ' . $this->domain . ' errors from ' . $this->logFilePath . PHP_EOL
            . PHP_EOL
            . $message . PHP_EOL;

        $message = trim($message);

        if ($isContentBiggerThanMaxSize) {
            $message .= "\n\n... Consult the error log archive file for the full list of errors:\n" . $this->archiveName;
        } else {
            $message .= "\n\nArchive file has been saved:\n" . $this->archiveName;
        }

        return $message;
    }

    /**
     * Append new logs to archive file
     */
    public function createArchive()
    {
        $archiveContent = sprintf("----- Full content of error log @ %s -----\n%s\n\n", date('d/m/Y H:i:s'), $this->newLog);

        file_put_contents($this->archiveName, $archiveContent, FILE_APPEND);
    }

    /**
     * Check for if new logs exist
     *
     * @return bool
     */
    public function checkUpdates()
    {
        /** Check error_log filesize. No need carrying anything if it is empty */
        if (filesize($this->logFilePath) == 0) { return false; }

        $this->messageToReport = $this->composeReport(file_get_contents($this->logFilePath));

        return (bool) $this->messageToReport;
    }

    /**
     * Send created report message
     */
    public function sendReport()
    {
        $this->sendMessage($this->messageToReport);
    }

    /**
     * Clear this journal
     */
    public function clearLog()
    {
        file_put_contents($this->logFilePath, null);
    }
}


/** Get path to config file in this script's dirictory */
$pathToConfig = substr($_SERVER["SCRIPT_FILENAME"], 0, strrpos($_SERVER["SCRIPT_FILENAME"], '/')) . '/config.php';

/** If no config.php file exists */
if (!file_exists($pathToConfig)) {
    exit;
}

/** Load config file */
$configs = include($pathToConfig);

/** Check journals */
foreach ($configs as $journalConfig) {

    $journal = new Journal($journalConfig);

    if (!$journal) { continue; }

    /** Check for new logs */
    $logs = $journal->checkUpdates();

    if ($logs) {
        $journal->sendReport();
        $journal->createArchive();
        $journal->clearLog();
    }

    unset($journal);
}

exit;

?>
