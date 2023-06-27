<?php
/**
 * REDCap External Module: Username Change
 * Adds the ability to modify a username
 * @author Greg Neils, Center for Disesase Control
 */

namespace CDC\UserNameChange;

use ExternalModules\ExternalModules;
use ExternalModules\AbstractExternalModule;
use Logging;
use Project;
use REDCap;

/**
 * REDCap External Module: Username Change
 */
class UserNameChange extends AbstractExternalModule
{
    /**
     * @var string[][]
     */
    private array $tablesAndColumns;

    /**
     * @var string Page URL
     */
    private string $pageUrl;

    /**
     * @var array
     */
    private array $users;
    /**
     * @var
     */
    private $user;

    /**
     * @var string
     */
    private string $action;

    /**
     * @var bool
     */
    private bool $includeLogs;

    /**
     * @var string
     */
    private string $linkStyle = 'color:white; text-decoration:none; letter-spacing:1px; font-weight:bold;';


    /**
     * @var string
     */
    private string $actionStyle = ' font-style: italic;';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->user = $this->getUser();

        $this->initialize();
    }


    /**
     *
     */
    private
    function initialize()
    {
        $this->tablesAndColumns = [
            ['table' => 'redcap_log_event', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event2', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event3', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event4', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event5', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event6', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event7', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event8', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_event9', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_view', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_view_old ', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_user_allowlist', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_auth', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_auth_history', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_data_access_groups_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_esignatures', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_external_links_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_locking_data', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_locking_records', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_sendit_docs', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_project_dashboards_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_reports_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_information', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_rights', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ' and project_id in (select project_id from redcap_projects)'],
            ['table' => 'redcap_reports_edit_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => 'and report_id in (select report_id from redcap_reports )'],
            ['table' => 'redcap_user_information', 'column' => 'user_sponsor', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_projects', 'column' => 'project_pi_username', 'has_table' => false, 'is_log' => false, 'sql_append' => '']
        ];

        $this->pageUrl = $this->getUrl('change-usernames.php');
        $selectUserSQL = 'select `username`, `user_firstname`, `user_lastname`, `user_email`' .
            ' from redcap_user_information ORDER BY `username`';
        $this->users = $this->query($selectUserSQL, []);
        $validPostActions = [
            'single_user_preview',
            'single_user_change',
            'bulk_preview',
            'bulk_update'
        ];
        if (isset($_REQUEST['form_action'])) {
            $form_action = $this->sanitize($_REQUEST['form_action']);
        } else {
            $form_action = '';
        }
        if (!isset($_REQUEST['action'])) {
            $this->action = 'read_me';
        } else if ($_REQUEST['action'] === 'read_me') {
            $this->action = 'read_me';
        } else if ($_REQUEST['action'] === 'auth_methods_preview') {
            $this->action = 'auth_methods_preview';
        } else if ($_REQUEST['action'] === 'db_info') {
            $this->action = 'db_info';
        } else if ($_REQUEST['action'] === 'tables') {
            $this->action = 'tables';
        } else if ($_REQUEST['action'] === 'change_user_start') {
            $this->action = 'change_user_start';
        } else if ($_REQUEST['action'] === 'single_user_change') {
            $this->action = 'single_user_change';
        } else if ($_SERVER["REQUEST_METHOD"] === "GET") {
            if ($_REQUEST['action'] === 'passwords') {
                $this->action = 'passwords';
            }
        } else {
            $this->action = 'read_me';
        }
        if ($_SERVER["REQUEST_METHOD"] === 'POST') {
            if (in_array($form_action, $validPostActions, true)) {
                $this->action = $form_action;
            } else {
                $this->action = 'read_me';
            }
        }
        $this->includeLogs = $this->set_include_logs();
    }


    /**
     * @return array
     */
    private
    function getTablesFromSchema(): array
    {
        global $db;
        $tableSQL = 'SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES' .
            " WHERE `TABLE_SCHEMA` = '" . $db . "'";
        $tableResult = $this->query($tableSQL, []);
        $tables = [];
        foreach ($tableResult as $row) {
            $tables[] = $row['TABLE_NAME'];
        }
        return ($tables);
    }

    /**
     *
     */
    public
    function makePage(): void
    {
//        echo "<h1>Debug: This Action = $this->action </h1>";
        $isSuperUser = $this->user->isSuperUser();
        if ($isSuperUser !== true) {
            die('This page is unavailable.');
        }

        // todo, this isn't the right place for this.  The method should update the property anyway.
        //  Since it is not necessary on every page is it worth refactoring and specifying, or calling it good?
        $dbTables = $this->getTablesFromSchema();
        foreach ($this->tablesAndColumns as $rowId => $tablesAndColumns) {
            if (in_array($tablesAndColumns['table'], $dbTables, true)) {
                $this->tablesAndColumns[$rowId]['has_table'] = true;
            }
        }
        echo $this->makeNavBar();

        if ($this->action === 'read_me') {
            $this->showReadMePage();
        } else if ($this->action === 'auth_methods_preview') {
            echo $this->makeAuthenticationMethodsPage();
        } else if ($this->action === 'bulk_preview') {
            $this->bulkUserPreview();
        } else if ($this->action === 'bulk_update') {
            $this->bulkUserUpdate();
        } else if ($this->action === 'db_info') {
            $this->showDBInfo();
        } else if ($this->action === 'passwords') {
            $this->showPasswordInfoPage();
        } else if ($this->action === 'tables') {
            $this->showTables();
        } else if ($this->action === 'change_user_start') {
            $this->makeChangeUserPage();
        } else if ($this->action === 'single_user_preview') {
            $this->singleUserPreview();
        } else if ($this->action === 'single_user_change') {
            $this->singleUserChange();
        } else {
            echo "Sorry, " . htmlspecialchars($this->sanitize($this->action)) . " that is not an available action.";
        }
        echo $this->makeDisclaimer();
        echo $this->makeSunflower();
    }


    /**
     *
     */
    private
    function singleUserPreview()
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if ($this->validateUserNameChanges($oldUser, $newUser)) {
            $results = $this->previewUserChanges($oldUser, $newUser);
            $htmlResult = "<h4>Number of rows that will be updated in the database: " . $results['count'] . "</h4>" .
                $results['resultTable'] .
                '<h5>Select SQL</h5>' .
                '<pre>' . $results['selectSQL'] . '</pre>' .
                '<h5>Update SQL</h5><pre style="font-size: 0.75em;">' . $results['updateSQL'] . '</pre>' .
                $this->makeSingleUserChangeFinalizeForm($oldUser, $newUser);
        } else {
            $htmlResult = $this->getUserNameChangeErrors($oldUser, $newUser);
        }
        echo $htmlResult;
    }


    /**
     *
     */
    private
    function singleUserChange()
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if ($this->singleUserUpdate($oldUser, $newUser)) {
            echo '<div class="alert alert-secondary"><h4>Outcome: Changed User</h4>' .
                '<p>Old: ' . $oldUser . '</p>' .
                '<p>New: ' . $newUser . '</p>' .
                '</div>';
        } else {
            echo $this->getUserNameChangeErrors($oldUser, $newUser);
        }
    }


    /**
     * @param $oldUser
     * @param $newUser
     * @return bool
     */
    private
    function singleUserUpdate($oldUser, $newUser): bool
    {
        if ($this->validateUserNameChanges($oldUser, $newUser)) {
            $this->commitUserChange($oldUser, $newUser);
            return true;
        }

        return false;
    }


    /**
     *
     */
    private
    function bulkUserPreview()
    {
        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            echo 'no input';
            exit;
        }
        $allUserNamesValid = true;
        $ids = explode("\n", str_replace("\r", "", $bulkCSV));
        $counter = 0;
        $totalAffectedRows = 0;
        $resultsTables = "";
        $selectSQL = "";
        $updateSQL = "";
        foreach ($ids as $id) {
            $counter++;
            $names = explode(',', $id, 5);
            if (count($names) === 2) {
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                $userNamesValid = $this->validateUserNameChanges($oldUser, $newUser);
                if ($userNamesValid) {
                    $results = $this->previewUserChanges($oldUser, $newUser);
                    $totalAffectedRows += $results['count'];
                    $resultsTables .= $results['resultTable'];
                    $selectSQL .= $results['selectSQL'];
                    $updateSQL .= $results['updateSQL'];
                } else {
                    $allUserNamesValid = false;
                    echo '<div class="alert alert-warning"><h4>Check line ' . $counter . '. ' .
                        $this->getUserNameChangeErrors($oldUser, $newUser) .
                        '</h4></div>';
                }
            } else {
                echo '<div class="alert alert-danger"><h4>Check line ' . $counter . ' for an extra comma or lack of one.</h4></div>';
                $allUserNamesValid = false;
            }
        }
        if ($allUserNamesValid) {
            echo '<div class="alert alert-secondary"><h4>Validated. Please verify the data before proceeding.</h4></div>' .
                $resultsTables .
                '<h5>Select SQL</h5><pre>' . $selectSQL . '</pre>' .
                '<h5>Update SQL</h5><pre style="font-size: 0.75em;">' . $updateSQL . '</pre>' .
                $this->bulkUserForm($bulkCSV);
        } else {
            echo '<div class="alert alert-danger"><h4>Input must be corrected before proceeding</h4></div>';
        }
    }

    /**
     *
     */
    private
    function bulkUserUpdate()
    {
        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            $allUserNamesValid = false;
            echo 'No CSV of username received';
        } else {
            $allUserNamesValid = true;
        }
        $ids = explode("\n", str_replace("\r", "", $bulkCSV));
        $counter = 0;
        foreach ($ids as $id) {
            $counter++;
            $names = explode(',', $id, 5);
            if (count($names) === 2) {
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                $userNamesValid = $this->validateUserNameChanges($oldUser, $newUser);
                if (!$userNamesValid) {
                    $allUserNamesValid = false;
                    echo $this->getUserNameChangeErrors($oldUser, $newUser);
                }
            } else {
                echo 'There is an error around line ' . $counter . '.<br>';
                $allUserNamesValid = false;
            }
        }
        if ($allUserNamesValid) {
            echo '<div class="alert alert-secondary"><h4>Results of bulk upload.</h4></div>';
// todo this is done in twice here, is there a way to reduce duplicate code?
            foreach ($ids as $id) {
                $names = explode(',', $id, 5);
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                if ($this->singleUserUpdate($oldUser, $newUser)) {
                    echo '<div class="alert alert-secondary"><h4>Changed User</h4>' .
                        '<p>Old Username: ' . $oldUser . '</p>' .
                        '<p>New Username: ' . $newUser . '</p>' .
                        '</div>';
                } else {
                    echo $this->getUserNameChangeErrors($oldUser, $newUser);
                }
                echo '<hr>';
            }
        } else {
            echo '<p style="color:red;">Input must be corrected before proceeding.</p>';
        }
    }

    /**
     * @param $bulkCSV
     * @return string
     */
    private
    function bulkUserForm($bulkCSV): string
    {
        return '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Bulk User Change</h5><p>The usernames have passed basic validation.  Clicking submit will finalize the username change.  Proceed with caution.</p>' .
            '<form  action="' . $this->pageUrl . '" method="post" enctype="multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">These usernames will change:</label>' .
            '<textarea name="csvUserNames" id="csvUserNames" class="form-control" rows="5" readonly>' .
            trim($bulkCSV) . '</textarea>' .
            '</div>' .
            '<button class="btn btn-success" type="submit" name="form_action" value="bulk_update">Submit</button>' .
            '</form></div>';
    }

    /**
     *
     */
    private
    function showDBInfo(): void
    {
        global $db_collation;
        global $db;

        $columnSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME " .
            "FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE `COLUMN_NAME` LIKE '%USER%'" .
            "AND `TABLE_SCHEMA` = '" . $db . "'";

        $tableSQL = 'SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES' .
            " WHERE `TABLE_SCHEMA` = '" . $db . "'";

        $columnResult = $this->query($columnSQL, []);
        $tableResult = $this->query($tableSQL, []);
        $boldStyle = ' style="font-weight:bold;"';


        $pageData = '<p>The underlying database tables used by REDCap at your institution may be slightly different from the tables listed below.</p>' .
            '<p>In order to change a username the database must be queried and references to the old username located and updated</p>' .
            '<p>Tables may be added REDCap at anytime in the future. This External Module only updates a fixed set of tables and columns.  At some point this fixed list may become outdated by the addition of a new table that includes a username.</p>' .
            '<p>Below is a list of all tables with a column that looks like user in it. Tables in bold are in the fixed list and will be updated.' .
            '<p>Tables that are not in bold will not be updated</p>' .
            '<p>SQL Snippet to help locate potential tables that may reference username:</p>' .
            '<code>SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME <br> FROM INFORMATION_SCHEMA.COLUMNS <br> WHERE `COLUMN_NAME` LIKE "%USER%"' .
            'and `TABLE_SCHEMA` = "' . $db . '";</code><br><br>';
        '<div class="alert alert-success">' . '<p class="text-center"><strong>Database Info</strong></p>' .
        '<p><strong>Rows in bold</strong>' .
        ' contain a table and column that reference user and will be included in the SQL update.</p></div>';

        if ($columnResult->num_rows > 0) {
            $resultTable = '<table class="table table-striped table-bordered table-hover"><tr>' .
                '<th>Table</th><th>Will be <br>modified</th><th>Column</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($columnResult)) {
                $tableIncludedInUpdate = false;
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (strtolower($update['table']) === strtolower($collation['TABLE_NAME']) &&
                        strtolower($update['column']) === strtolower($collation['COLUMN_NAME'])) {
                        $tableIncludedInUpdate = true;
                        break;
                    }
                }
                if ($tableIncludedInUpdate) {
                    $resultTable .= $boldStyle;
                }
                $resultTable .= '>' .
                    '<td>' . htmlspecialchars($collation['TABLE_NAME'] ?? '', ENT_QUOTES) . '</td>';
                if ($tableIncludedInUpdate) {
                    $resultTable .= '<td>Yes</td>';
                } else {
                    $resultTable .= '<td>No</td>';
                }
                $resultTable .= '<td>' . htmlspecialchars($collation['COLUMN_NAME'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($collation['COLLATION_NAME'] ?? '', ENT_QUOTES) . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for column collations . This result is strange and should never occur.</p>';
        }
        $pageData .= '<p>A collation is a set of rules that tell the database how to compare and sort the character data. In other words defines how the match is made on username.</p>' .
            '<p>The REDCap system level db_collation is set to ' . $db_collation . '</p>' .
            '<p>Run two SQL statements below to view the database and table collations.</p>' .
            '<p><strong>Query 1</strong><p>' .
            '<code>' . htmlentities($columnSQL) . '</code>' .
            '<p><strong>Query 2</strong><p>' .
            '<code>' . htmlentities($tableSQL) . '</code>' . '</br>';

        if ($tableResult->num_rows > 0) {
            $pageData .= '<div class="alert alert-success">Table Collations</div>';
            $resultTable = '<table class="table table-striped table-bordered table-hover"><tr>' .
                '<th>Table</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($tableResult)) {
                $tableIncludedInUpdate = false;
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (strtolower($update['table']) === strtolower($collation['TABLE_NAME'])) {
                        $tableIncludedInUpdate = true;
                        break;
                    }
                }
                if ($tableIncludedInUpdate) {
                    $resultTable .= $boldStyle;
                }
                $resultTable .= '>' .
                    '<td>' . htmlspecialchars($collation['TABLE_NAME'], ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($collation['TABLE_COLLATION'], ENT_QUOTES) . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for table collations. This result is strange and should never occur.</p>';
        }
        echo $pageData;
    }

    /**
     *
     */
    private
    function showReadMePage(): void
    {
        echo file_get_contents(__DIR__ . '/html/readme.html');

        $logEvent = 'Viewed readme via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
    }

    /**
     *
     */
    private
    function showPasswordInfoPage(): void
    {
        echo file_get_contents(__DIR__ . '/html/passwords.html');

        $logEvent = 'Viewed how to remove all passwords via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
    }

    /**
     *
     */
    private
    function showTables(): void
    {
        echo $this->makeTableList();
    }


    /**
     * @param string|null $data
     * @return string
     */
    private
    function sanitize(?string $data): string
    {
        if (is_null($data)) {
            return '';
        }
        // Note label_decode is a base REDCap function for cleaning data in a specific way.
        $data = strtolower(trim(stripslashes(label_decode($data))));
        return htmlspecialchars($data);
    }

    /**
     *
     */
    private
    function makeChangeUserPage(): void
    {
        echo $this->makeSingleUserForm();
        echo $this->makeBulkUploadForm();
    }

    /**
     * @return string
     */
    private
    function makeNavBar(): string
    {
        return '<div style="display: flex;justify-content: space-around;align-items: center;min-height: 45px;background-color: #43699a"' .
            '<ul class="user_name_change_nav_bar"' .
            ' style="display: flex;justify-content: space-around;width: 30%;">' .
            $this->makeReadMeLink() .
            $this->makeTablesLink() .
            $this->makeChangeUserLink() .
            $this->makeAuthMethodLink() .
            $this->makeDBInfoLink() .
            $this->makePasswordsLink() .
            '</ul></div>';
    }

    /**
     * @return string
     */
    private
    function makeDisclaimer(): string
    {
        return file_get_contents(__DIR__ . '/html/disclaimer.html');
    }

    /**
     * @return string
     */
    private
    function makeBulkUploadForm(): string
    {
        $form = '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Bulk User Change </h5><p>Process multiple users. Each row represents one user.' .
            ' Each row must be in the format of<br><br> old_user_name,new_user_name</p>' .
            '<form action = "' . $this->pageUrl . '" method = "post" enctype = "multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">Paste in the csv data below</label>' .
            '<textarea name = "csvUserNames" id = "csvUserNames" class="form-control" rows="5"></textarea>' .
            '</div>' .
            '<button class="btn btn-success" type = "submit" name = "form_action" value="bulk_preview">Preview</button>' .
            '</form></div>';
        return $form;
    }

    /**
     * @return string
     */
    private
    function makeTableList(): string
    {
        $table = '<p>This is a list tables that may have usernames that will be updated by this External Module.' .
            ' Your version of REDCap may or may not have one of the tables below.' .
            ' If "Yes" is in the In DB column it means your database has that table.' .
            ' If "No" is in the In DB column it means your database does not have that table.  You version of REDCap may not have them.' .
            ' NOTE: Column names are not checked!. <strong>If the module crashes during a preview do NOT use it.</strong></p>' .
            '<table class="table table-striped table-condensed">' .
            '<tr><th>Table</th><th>Column</th><th>in DB</th><th>Is log Table</th></tr>';
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            $table .= '<tr><td>' . $tableAndColumn['table'] . '</td>' .
                '<td>' . $tableAndColumn['column'] . '</td>' .
                '<td>' . (($tableAndColumn['has_table']) ? "Yes" : "No") . '</td>' .
                '<td>' . (($tableAndColumn['is_log']) ? "Yes" : "No") . '</td>' . '</tr>';
        }
        $table .= '</table>';
        return $table;
    }

    /**
     * @return string
     */
    private
    function makeSingleUserForm(): string
    {
        $oldUserName = "";
        $newUserName = "";
        if (isset($_REQUEST['old_name'])) {
            $oldUserName = $this->sanitize($_REQUEST['old_name']);
        }
        if (isset($_REQUEST['new_name'])) {
            $newUserName = $this->sanitize($_REQUEST['new_name']);
        }

        $form = '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Single Username Change</h5>' .
            '<form action="' . $this->pageUrl . '&action=single_user_preview" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name">Old Username:</label>' .
            '<select name="old_name" id="old_name" class="form-control">';
        foreach ($this->users as $user) {
            $form .= '<option value="' . $user['username'] . '"';
            if ($oldUserName === $user['username']) {
                $form .= ' selected ';
            }

            $form .= '>' . $user['username'] . '</option>';
        }
        $form .= '</select>' .
            '</div>' .
            '<div class="form-group">' .
            '<label for="new_name">New Username:</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control"  value="' . $newUserName . '">' . '<br>' .
            '</div>' .
            '<div class="form-group form-check">' .
            '<input type="checkbox" id="include_logs" name="include_logs" class="form-check-input" value="1">' .
            '<label for="include_logs" class="form-check-label">Include logs:</label>' .
            '</div>' .
            '<div class="form-group">';
        if ($this->action === 'change_user_start') {
            $form .= '<button class="btn btn-success" ' .
                'style="margin-right: 30px;" type="submit" name="form_action"' .
                ' value="single_user_preview">Review</button>';
        } else if ($this->action === 'single_user_preview') {
            $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="single_user_change">' .
                'Commit Username Change</button>';
        } else {
            $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="whoops">Whoops</button>';
        }
        $form .= '</div>' .
            '</form></div>';
        return $form;
    }


    /**
     * @param $oldUser
     * @param $newUser
     * @return string
     */
    private
    function makeSingleUserChangeFinalizeForm($oldUser, $newUser): string
    {

        if ($this->includeLogs) {
            $form_include_logs = true;
        } else {
            $form_include_logs = false;
        }


        $form = '<h4>Please review the information above and below for accuracy. ' .
            'You agree to take full responsibility for running this code. Pressing the button below can not be undone.</h4>' .
            '<div class="card p-3"><form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name"><strong>Old Username:</strong> ' . $oldUser . '</label>' .
            '<input name="old_name" id="old_name" class="form-control" value="' . $oldUser . '" readonly hidden>' .
            '</div>' .
            '<div class="form-group">' .
            '<label for="new_name"><strong>New Username:</strong> ' . $newUser . '</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control" readonly hidden value="' . $newUser . '">' .
            '<br>' .
            '</div>' .
            '<div class="form-group form-check">' .
            '<input type="checkbox" id="include_logs" name="include_logs" class="form-check-input" readonly hidden value="' .
            $form_include_logs . '"';

        if ($this->includeLogs) {
            $form .= ' checked';
        }
        $form .= '>' .
            '<label for="include_logs" class="form-check-label"><strong>Include logs:</strong>';
        if ($form_include_logs) {
            $form .= " Yes";
        } else {
            $form .= " No";
        }
        $form .= '</label>' .
            '</div>' .
            '<div class="form-group">' .
            '<button class="btn btn-warning" type="submit" name="form_action" value="single_user_change">Change User' . '</button>' .
            '</div>' .
            '</form>';
        return $form;
    }


    /**
     * @return bool
     */
    private
    function set_include_logs(): bool
    {
        if (isset($_REQUEST['include_logs']) && $_REQUEST['include_logs'] = 1) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    private
    function makeReadMeLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'read_me') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=read_me" style="' . $this->linkStyle . $actionStyle .
            '">Read Me</a></li>';
    }

    /**
     * @return string
     */
    private
    function makeChangeUserLink(): string
    {
        $url = $this->pageUrl;
        $parameters = "";
        $actionStyle = '';
        if ($this->action === 'change_user_start' ||
            $this->action === 'single_user_preview' ||
            $this->action === 'single_user_change') {
            $actionStyle = $this->actionStyle;
        }
        $parameters .= '&action=change_user_start';
        if (isset($_REQUEST['old_name'])) {
            $parameters .= '&old_name=' . $this->sanitize($_REQUEST['old_name']);
        }
        if (isset($_REQUEST['new_name'])) {
            $parameters .= '&new_name=' . $this->sanitize($_REQUEST['new_name']);
        }
        if ($parameters !== '') {
            $url .= $parameters;
        }
        return '<li style="list-style:none;"><a href="' .
            $url . '" style="' . $this->linkStyle . $actionStyle . '">' .
            'Change User</a></li>';
    }

    /**
     * @return string
     */
    private
    function makeAuthMethodLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'auth_methods_preview') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=auth_methods_preview" style="' . $this->linkStyle . $actionStyle .
            '">Auth Methods</a></li>';
    }

    /**
     * @return string
     */
    private
    function makeDBInfoLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'db_info') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=db_info" style="' . $this->linkStyle . $actionStyle .
            '">DB Info</a></li>';
    }

    /**
     * @return string
     */
    private
    function makePasswordsLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'passwords') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=passwords" style="' . $this->linkStyle . $actionStyle .
            '">Password Info</a></li>';
    }

    /**
     * @return string
     */
    private
    function makeTablesLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'tables') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=tables" style="' . $this->linkStyle . $actionStyle .
            '">Tables</a></li>';
    }


    /**
     * @return string
     */
    private
    function makeAuthenticationMethodsPage(): string
    {
        $authMethods = $this->getAuthenticationMethodSummary();
        $authMethodsInUse = [];
        $filename = __DIR__ . '/html/auth_methods_summary.html';
        $pageData = file_get_contents($filename);

        if ($authMethods->num_rows > 0) {
            $authAvailable = '<table  class="table table-striped table-bordered table-hover"><tr><th>Auth Methods</th><th>Count</th></tr>';
            while ($method = mysqli_fetch_array($authMethods)) {
                $authMethodsInUse[] = $method['auth_meth'];
                $authAvailable .= '<tr><td>' . htmlspecialchars($method['auth_meth'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($method['count'] ?? '', ENT_QUOTES) . '</td></tr>';
            }
            $authAvailable .= '</table>';
        } else {
            $authAvailable = '<p>There are no results for auth methods. This result is strange and should probably never occur</p>';
        }

        $pageData .= '<div style="padding:20px;margin:20px; border: 2px solid pink;">' .
            '<form><div class="form-group">' .
            '<label for="old_auth">Authentications in use in projects:</label>' .
            '<select name="old_auth" id="old_auth" class="form-control" onchange="generateSQL();">';
        $authFrom = "";
        foreach ($authMethodsInUse as $singleMethod) {
            $authFrom .= '<option value="' .
                htmlspecialchars($singleMethod ?? '', ENT_QUOTES) . '">' .
                htmlspecialchars($singleMethod ?? '', ENT_QUOTES) .
                '</option>';
        }
        $pageData .= $authFrom . '</select></div>';
        $filenameAuthTo = __DIR__ . '/html/authentication_available_methods.html';
        $pageData .= file_get_contents($filenameAuthTo);

        $pageData .= "<div class='alert alert-success'>Details</div>";
        $pageData .= $authAvailable;

        $projects = $this->getAuthenticationMethodDetails();
        if ($projects->num_rows > 0) {
            $authInProjects = '<table class="table table-striped table-hover table-bordered"><tr>' .
                '<th>Project ID</th><th>Name</th><th>Auth Method</th></tr>';
            foreach ($projects as $project) {
                $authInProjects .= '<tr><th>' . $project['project_id'] . '</th>' .
                    '<th>' . $project['project_name'] . '</th>' .
                    '<th>' . $project['auth_meth'] . '</th>' .
                    '</tr>';
            }
            $authInProjects .= '</table>';
        } else {
            $authInProjects = '<p>There are no results for projects. This result is strange and should never occur.</p>';
        }
        $pageData .= $authInProjects;

        return $pageData;
    }


    /**
     * @return string
     */
    private
    function makeSunflower(): string
    {
        return file_get_contents(__DIR__ . '/html/flower.html');
    }


    /**
     * @return mixed
     */
    private
    function getAuthenticationMethodSummary()
    {
        return $this->query('SELECT `auth_meth`, count(auth_meth) as count FROM redcap_projects group by auth_meth;', []);
    }

    /**
     * @return mixed
     */
    private
    function getAuthenticationMethodDetails()
    {
        return $this->query('SELECT project_id, project_name, auth_meth FROM redcap_projects;', []);
    }

    /**
     * @param $oldUser
     * @param $newUser
     */
    private
    function commitUserChange($oldUser, $newUser): void
    {
        global $db_collation;
        echo "<div class='alert alert-success'><h4>The following tables were updated</h4></div>";
        $sql = '';
        $resultTable = '<table class="table table-striped">' .
            '<tr><th>Table</th><th>Column</th><th>Count</th><th>Error #</th></tr>';
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            if (!$tableAndColumn['has_table']) {
                continue;
            }
            if ($tableAndColumn['is_log'] && !$this->includeLogs) {
                continue;
            }

            // todo a specific table has the need for a where clause.
            $sql_embedded_parameters = 'UPDATE ' . $tableAndColumn['table'] .
                ' SET `' . $tableAndColumn['column'] . '` = ' .
                '"' . $newUser . '"' .
                ' WHERE `' . $tableAndColumn['column'] . '` = ' .
                '"' . $oldUser . '"';
            if ($tableAndColumn['sql_append'] !== '') {
                $sql_embedded_parameters .= " " . $tableAndColumn['sql_append'];
            } else {
                $sql_embedded_parameters .= ' COLLATE ' . $db_collation;
            }
            $sql_embedded_parameters .= ';';
            $sql .= $sql_embedded_parameters . "<br>";

//            $sql_with_parameters = 'UPDATE ' . $update['table'] .
//                ' SET `' . $update['column'] . '` = ?' .
//                ' WHERE `' . $update['column'] . '` = ?' .
//                ' COLLATE ' . $db_collation . ';';
//            echo $sql_with_parameters;
//
//            $sql_parameters = [$newUser, $oldUser, $db_collation];
//            $x = $this->query($sql_with_parameters, $sql_parameters);

            $result = $this->query($sql_embedded_parameters, []);
            $resultTable .= '<tr><th>' . $tableAndColumn['table'] . '</th>' .
                '<th>' . $tableAndColumn['column'] . '</th>' .
                '<th>' . db_affected_rows() . '</th>';
            if (isset($result->error)) {
                $resultTable .= '<th>' . $result->error . '</th>';
            } else {
                $resultTable .= '<th>0</th>';
            }
            $resultTable .= '</tr>';

        }

        $resultTable .= "</table>";
        echo $resultTable;
        echo '<div class="alert alert-success">Using the following UPDATE SQL:</div><pre>' . $sql . '</pre>';

        $logEvent = 'Changed user name. Old: ' . $oldUser . ' New: ' . $newUser . ' via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
        $logId = $this->log(
            "Username Changed",
            [
                "Old User" => $newUser,
                "New User" => $oldUser
            ]
        );
    }

    /**
     * @param $oldUser
     * @param $newUser
     * @return string
     */
    private
    function getUserNameChangeErrors($oldUser, $newUser): string
    {
        $errorMessage = "";
        if (!$this->validateUserName($oldUser)) {
            $errorMessage .= 'The old username is not valid.<br>';
        }
        if (!$this->validateUserName($newUser)) {
            $errorMessage .= 'The new username is not valid.<br>';
        }

        if (!$this->findUser($oldUser)) {
            $errorMessage .= "The user, $oldUser, was not found<br>";
        }
        if ($this->findUser($newUser)) {
            $errorMessage = "<h2>" . $newUser . ' is already in use. <br>An old username can not be changed to an existing username.</h2>';
        }
        return $errorMessage;
    }

    /**
     * @param $oldUser
     * @param $newUser
     * @return array
     */
    #[
        ArrayShape(['count' => "int", 'resultTable' => "string", 'selectSQL' => "string", 'updateSQL' => "string"])]
    private function previewUserChanges($oldUser, $newUser): array
    {
// todo get this in a method and call from here as well as change user.
        global $db_collation;
        $allSelectSQL = '';
        $allUpdateSQL = '';
        $resultTable = '<h4>Old Username: ' . $oldUser . ' --> New Username:' . $newUser . '</h4>' .
            '<table class="table table-striped caption-top">' .
            '<tr><th>Table</th><th>Column</th><th>Count</th></tr>';
        $rowCountTotal = 0;
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            if (!$tableAndColumn['has_table']) {
                continue;
            }

            if ($tableAndColumn['is_log'] && !$this->includeLogs) {
                continue;
            }

            $sql_embedded_parameters = 'SELECT ' . $tableAndColumn['column'] .
                ' FROM ' . $tableAndColumn['table'] .
                ' WHERE `' . $tableAndColumn['column'] . '` = ' .
                '"' . $oldUser . '"';
            $allSelectSQL .= $sql_embedded_parameters . "<br>";
            $allUpdateSQL .= 'UPDATE ' . $tableAndColumn['table'] .
                ' SET `' . $tableAndColumn['column'] . '` = ' .
                '"' . $newUser . '"' .
                ' WHERE `' . $tableAndColumn['column'] . '` = ' .
                '"' . $oldUser . '"';
            if ($tableAndColumn['sql_append'] !== '') {
                $allUpdateSQL .= " " . $tableAndColumn['sql_append'];
            } else {
                $allUpdateSQL .= ' COLLATE ' . $db_collation;
            }
            $allUpdateSQL .= ';<br>';
            $result = $this->query($sql_embedded_parameters, []);
            // todo check the $result to make sure there was not an error.

            $resultTable .= '<tr><th>' . $tableAndColumn['table'] . '</th>' .
                '<th>' . $tableAndColumn['column'] . '</th>' .
                '<th>' . db_affected_rows() . '</th>' .
                '</tr>';
            $rowCountTotal += db_affected_rows();

        }
        $resultTable .= '</table>';
        return [
            'count' => $rowCountTotal,
            'resultTable' => $resultTable,
            'selectSQL' => $allSelectSQL,
            'updateSQL' => $allUpdateSQL
        ];
    }


    /**
     * @param string $oldUser
     * @return bool
     */
    private function findUser(string $oldUser): bool
    {
        foreach ($this->users as $user) {
            if (strtolower($user['username']) === strtolower($oldUser)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @param $username
     * @return bool
     */
    private function validateUserName($username): bool
    {
        return strlen($username) > 2;
    }

    /**
     * @param $oldUser
     * @param $newUser
     * @return bool
     */
    private function validateUserNameChanges($oldUser, $newUser): bool
    {
        if (!$this->validateUserName($oldUser)) {
            return false;
        }

        if (!$this->validateUserName($newUser)) {
            return false;
        }

        if (!$this->findUser($oldUser)) {
            return false;
        }

        if ($this->findUser($newUser)) {
            return false;
        }
        return true;
    }

}
