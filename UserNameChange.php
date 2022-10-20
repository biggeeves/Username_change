<?php
/**
 * REDCap External Module: Username Change
 * Change a username
 * @author Greg Neils, Center for Disesase Control
 */

namespace CDC\UserNameChange;

use ExternalModules\ExternalModules;
use ExternalModules\AbstractExternalModule;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
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
     * @var
     */
    private $users;
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
    private bool $include_logs;

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
        $this->action = 'page_load';
        $form_action = $this->sanitize($_REQUEST['form_action']);
        if ($_REQUEST['action'] === 'auth_methods_preview') {
            $this->action = 'auth_methods_preview';
        } else if ($_REQUEST['action'] === 'collation') {
            $this->action = 'collation';
        } else if ($_REQUEST['action'] === 'tables') {
            $this->action = 'tables';
        } else if ($_SERVER["REQUEST_METHOD"] === "GET") {
            if ($_REQUEST['action'] === 'passwords') {
                $this->action = 'passwords';
            } else {
                $this->action = 'page_load';
            }
        } else if ($_SERVER["REQUEST_METHOD"] === "POST") {
            if (in_array($form_action, $validPostActions, true)) {
                $this->action = $form_action;
            }
        }

        $this->include_logs = $this->set_include_logs();
    }


    /**
     * @return array
     */
    private function getTablesFromSchema()
    {
        global $db;
        $tableSQL = "SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES" .
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
    public function makePage(): void
    {
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

        if ($this->action === 'page_load') {
            $this->makeHomePage();
        } else if ($this->action === 'auth_methods_preview') {
            echo $this->makeAuthenticationMethodsPage();
        } else if ($this->action === 'bulk_preview') {
            $this->bulkUserPreview();
        } else if ($this->action === 'bulk_update') {
            $this->bulkUserUpdate();
        } else if ($this->action === 'collation') {
            $this->showCollations();
        } else if ($this->action === 'passwords') {
            $this->showPasswords();
        } else if ($this->action === 'tables') {
            $this->showTables();
        } else if ($this->action === 'single_user_preview') {
            $this->singleUserPreview();
        } else if ($this->action === 'single_user_change') {
            $this->singleUserChange();
        } else {
            echo "Sorry, " . htmlspecialchars($this->sanitize($this->action)) . " that is not an available action.";
        }
    }


    /**
     *
     */
    private function singleUserPreview()
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if ($this->validateUserNameChanges($oldUser, $newUser)) {
            $results = $this->previewUserChanges($oldUser, $newUser);
            $htmlResult = "Total rows found: " . $results['count'] .
                $results['resultTable'] .
                "<h5>Select SQL</h5>" .
                "<pre>" . $results['selectSQL'] . "</pre>" .
                "<h5>Update SQL</h5><pre style='font-size: 0.75em;'>" . $results['updateSQL'] . "</pre>" .
                $this->makeSingleUserChangeFinalizeForm($oldUser, $newUser);
        } else {
            $htmlResult = $this->getUserNameChangeErrors($oldUser, $newUser);
        }
        echo $htmlResult;
    }


    /**
     *
     */
    private function singleUserChange()
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
                    echo $this->getUserNameChangeErrors($oldUser, $newUser);
                }
            } else {
                echo "There is an error around line " . $counter . ".<br>";
                $allUserNamesValid = false;
            }
        }
        if ($allUserNamesValid) {
            echo '<p>Validated. Able to proceed.</p>' .
                $resultsTables .
                "<h5>Select SQL</h5><pre>" . $selectSQL . "</pre>" .
                "<h5>Update SQL</h5><pre style='font-size: 0.75em;'>" . $updateSQL . "</pre>" .
                $this->bulkUserForm($bulkCSV);
        } else {
            echo '<p style="color:red;">Input must be corrected before proceeding</p>';
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
            echo "No CSV of username received";
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
                echo "There is an error around line " . $counter . ".<br>";
                $allUserNamesValid = false;
            }
        }
        if ($allUserNamesValid) {
            echo '<p>Results of bulk upload.</p>';
// todo this is done in twice here, is there a way to reduce duplicate code?
            foreach ($ids as $id) {
                $names = explode(',', $id, 5);
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                if ($this->singleUserUpdate($oldUser, $newUser)) {
                    echo '<div class="alert alert-secondary"><h4>Reult: Changed User</h4>' .
                        '<p>Old: ' . $oldUser . '</p>' .
                        '<p>New: ' . $newUser . '</p>' .
                        '</div>';
                } else {
                    echo $this->getUserNameChangeErrors($oldUser, $newUser);
                }
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
    function showCollations(): void
    {
        global $db_collation;
        global $db;

        $columnSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME " .
            "FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE `COLUMN_NAME` LIKE '%USER%'" .
            "AND `TABLE_SCHEMA` = '" . $db . "'";

        $tableSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE `TABLE_SCHEMA` = '" . $db . "'";

        $columnResult = $this->query($columnSQL, []);
        $tableResult = $this->query($tableSQL, []);


        $pageData = "<p>The REDCap system level db_collation is set to " . $db_collation . "</p>" .
            "<pre>" . htmlentities($columnSQL) . "</pre>" .
            "<pre>" . htmlentities($tableSQL) . "</pre>" . "</br>" .
            "<div class='alert alert-success'><p class='text-center'>Column Collations</p><p><strong>Rows in bold</strong>".
            " contain a table and column that reference user and will be included in the SQL update.</p></div>";

        if ($columnResult->num_rows > 0) {
            $resultTable = '<table  class="table table-striped table-bordered table-hover"><tr>' .
                '<th>Schema</th><th>Table</th><th>Column</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($columnResult)) {
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (strtolower($update['table']) === strtolower($collation['TABLE_NAME']) &&
                        strtolower($update['column']) === strtolower($collation['COLUMN_NAME'])) {
                        $resultTable .= ' style="font-weight:bold;"';
                        break;
                    }
                }
                $resultTable .= '>' .
                    '<td>' . $collation['TABLE_SCHEMA'] . '</td>' .
                    '<td>' . $collation['TABLE_NAME'] . '</td>' .
                    '<td>' . $collation['COLUMN_NAME'] . '</td>' .
                    '<td>' . $collation['COLLATION_NAME'] . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for column collations.  This result is strange and should never occur</p>';
        }

        if ($tableResult->num_rows > 0) {
            $pageData .= "<div class='alert alert-success'>Table Collations</div>";
            $resultTable = '<table  class="table table-striped table-bordered table-hover"><tr>' .
                '<th>TABLE_SCHEMA</th><th>Table</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($tableResult)) {
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (strtolower($update['table']) === strtolower($collation['TABLE_NAME'])) {
                        $resultTable .= ' style="font-weight:bold;"';
                        break;
                    }
                }
                $resultTable .= '>' .
                    '<td>' . $collation['TABLE_SCHEMA'] . '</td>' .
                    '<td>' . $collation['TABLE_NAME'] . '</td>' .
                    '<td>' . $collation['TABLE_COLLATION'] . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for table collations.  This result is strange and should never occur.';
        }
        echo $pageData;
    }

    /**
     *
     */
    private function showPasswords()
    {
        echo '<p>When table based authentication is used passwords are individually salted, hashed and stored in the REDCap database.' .
            ' When removing table based authentication, <em>(example: switching from Table Based to OAuth)</em>,' .
            ' it may be good idea or even required to remove passwords from REDCap.' .
            ' The SQL query below will set all passwords to NULL. Once the SQL script is run, there is no way to recover passwords.' .
            ' Please use the SQL query as your starting point.  If you do not understand whaT the script will do, do not run it. There is another column, ' .
            ' password_reset_key, which you may want to set to null as well.  This script will work as long as REDCap keeps the password column NULLABLE.</p>' .
            '<p style="color:red;"> Running this script will remove ALL passwords, even yours. You may be locked out of the system.</p>' .
            '<pre>UPDATE `redcap_auth` set `password` = NULL</pre>';
        '<pre>UPDATE `redcap_auth` set `password_salt` = NULL</pre>';
        $logEvent = 'Viewed how to remove all passwords via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
    }

    /**
     *
     */
    private function showTables()
    {
        echo $this->makeTableList();
    }


    /**
     * @param $data
     * @return mixed
     */
    private
    function sanitize($data)
    {
        // Note label_decode is a base REDCap function for cleaning data in a specific way.
        $data = strtolower(trim(stripslashes(label_decode($data))));
        return filter_var($data, FILTER_SANITIZE_STRING);
    }

    /**
     *
     */
    private
    function makeHomePage(): void
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
        return "<div>" .
            $this->makeReloadLink() .
            $this->makeAuthMethodLink() .
            $this->makeCollationLink() .
            $this->makePasswordsLink() .
            $this->makeTablesLink() .
            "</div>" .
            $this->makeDisclaimer() .
            $this->makeSunflower();
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
            '<h5>Bulk User Change</h5><p>Process multiple users.  Each row represents one user.' .
            ' Each row must be in the format of <br><br>old_user_name,new_user_name</p>' .
            '<form  action="' . $this->pageUrl . '" method="post" enctype="multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">Paste in the csv data below</label>' .
            '<textarea name="csvUserNames" id="csvUserNames" class="form-control" rows="5"></textarea>' .
            '</div>' .
            '<button class="btn btn-success" type="submit" name="form_action" value="bulk_preview">Preview</button>' .
            '</form></div>';
        return $form;
    }

    /**
     * @return string
     */
    private function makeTableList()
    {
        $table = '<p>The table below contains every table that could be affected when the user name changes.' .
            ' Your version of REDCap may or may not have one of the tables below.' .
            ' If "Yes" is in the In DB column it means your database has that table.' .
            ' If "No" is in the In DB column it means your database does not have that table.</p>' .
            ' NOTE: Column names are not checked!.  If the module crashes during a preview do NOT use it.</p>' .
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
        if ($this->action === 'single_user_change') {
            $state = 'single_user_change';
        } else {
            $state = 'single_user_preview';
        }
        $form = '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Single Username Change</h5>' .
            '<form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name">Old Username:</label>' .
            '<select name="old_name" id="old_name" class="form-control" >';
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
        if ($this->action === 'page_load') {
            $form .= '<button class="btn btn-success" style="margin-right: 30px;" type="submit" name="form_action" value="single_user_preview">Review' . '</button>';
        } else if ($this->action === 'single_user_preview') {
            $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="single_user_change">Commit Username Change</button>';
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
    private function makeSingleUserChangeFinalizeForm($oldUser, $newUser): string
    {

        if ($this->include_logs) {
            $form_include_logs = true;
        } else {
            $form_include_logs = false;
        }


        $form = '<h4>Please review the information above and below for accuracy.' .
            'You agree to take all responsibility for running this code. Pressing the button below can not be undone.</h4>' .
            '<div class="card p-3"><form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name">Old Username: ' . $oldUser . '</label>' .
            '<input name="old_name" id="old_name" class="form-control" value="' . $oldUser . '" readonly hidden>' .
            '</div>' .
            '<div class="form-group">' .
            '<label for="new_name">New Username: ' . $newUser . '</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control" readonly hidden value="' . $newUser . '">' .
            '<br>' .
            '</div>' .
            '<div class="form-group form-check">' .
            '<input type="checkbox" id="include_logs" name="include_logs" class="form-check-input" readonly hidden value="' . $form_include_logs . '"';

        if ($this->include_logs) {
            $form .= ' checked';
        }
        $form .= '>' .
            '<label for="include_logs" class="form-check-label">Include logs:';
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
    private function set_include_logs()
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
    function makeReloadLink(): string
    {
        $parameters = "";
        if (isset($_REQUEST['old_name'])) {
            $parameters .= '&old_name=' . $this->sanitize($_REQUEST['old_name']);
        }
        if (isset($_REQUEST['new_name'])) {
            $parameters .= '&new_name=' . $this->sanitize($_REQUEST['new_name']);
        }
        $url = $this->pageUrl;
        if ($parameters !== '') {
            $url .= $parameters;
        }
        return '<a class="btn btn-primary" style="margin:15px;" href="' .
            $url . '">Change User</a>';
    }

    /**
     * @return string
     */
    private
    function makeAuthMethodLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=auth_methods_preview">Auth Methods</a>';
    }

    /**
     * @return string
     */
    private
    function makeCollationLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=collation">DB Collations</a>';
    }

    /**
     * @return string
     */
    private
    function makePasswordsLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=passwords">Password Info</a>';
    }

    /**
     * @return string
     */
    private function makeTablesLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=tables">Tables</a>';
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
                $authAvailable .= '<tr><td>' . $method['auth_meth'] . '</td>' .
                    '<td>' . $method['count'] . '</td></tr>';
            }
            $authAvailable .= '</table>';
        } else {
            $authAvailable = '<p>There are no results for auth methods.  This result is strange and should probably never occur';
        }

        $pageData .= '<div style="padding:20px;margin:20px; border: 2px solid pink;">' .
            '<form><div class="form-group"  >' .
            '<label for="old_auth">Authentications in use in projects:</label>' .
            '<select name="old_auth" id="old_auth" class="form-control" onchange="generateSQL();">';
        $authFrom = "";
        foreach ($authMethodsInUse as $singleMeth) {
            $authFrom .= '<option value="' . $singleMeth . '">' . $singleMeth . '</option>';
        }
        $pageData .= $authFrom . '</select></div>';
        $filenameAuthTo = __DIR__ . '/html/authentication_available_methods.html';
        $pageData .= file_get_contents($filenameAuthTo);

        $pageData .= "<div class='alert alert-success'>Details</div>";
        $pageData .= $authAvailable;

        $projects = $this->getAuthenticationMethodDetails();
        if ($projects->num_rows > 0) {
            $authInProjects = '<table  class="table table-striped table-hover table-bordered"><tr>' .
                '<th>Project ID</th><th>Name</th><th>Auth Method</th></tr>';
            foreach ($projects as $project) {
                $authInProjects .= '<tr><th>' . $project['project_id'] . '</th>' .
                    '<th>' . $project['project_name'] . '</th>' .
                    '<th>' . $project['auth_meth'] . '</th>' .
                    '</tr>';
            }
            $authInProjects .= '</table>';
        } else {
            $authInProjects = '<p>There are no results for projects.  This result is strange and should never occur';
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
        echo "<div class='alert alert-success'>The following tables were updated</div>";
        $sql = '';
        $resultTable = "<table class='table table-striped'>" .
            "<tr><th>Table</th><th>Column</th><th>Count</th><th>Error #</th></tr>";
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            if (!$tableAndColumn['has_table']) {
                continue;
            }
            if ($tableAndColumn['is_log'] && !$this->include_logs) {
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
                '<th>' . db_affected_rows() . '</th>' .
                '<th>' . $result->error . '</th>' .
                '</tr>';

        }

        $resultTable .= "</table>";
        echo $resultTable;
        echo "<div class='alert alert-success'>Using the UPDATE following SQL:</div><pre>" . $sql . '</pre>';

        $logEvent = 'Changed user name.  Old: ' . $oldUser . ' New: ' . $newUser . ' via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
        $logId = $this->log(
            "Username Changed",
            [
                "old User" => $newUser,
                "New User" => $oldUser
            ]
        );
    }

    /**
     * @param $oldUser
     * @param $newUser
     * @return string
     */
    private function getUserNameChangeErrors($oldUser, $newUser): string
    {
        $errorMessage = "";
        if (!$this->validateUserName($oldUser)) {
            $errorMessage .= "The old user name is not valid.<br>";
        }
        if (!$this->validateUserName($newUser)) {
            $errorMessage .= "The new user name is not valid.<br>";
        }

        if (!$this->findUser($oldUser)) {
            $errorMessage .= "The user, $oldUser, was not found<br>";
        }
        if ($this->findUser($newUser)) {
            $errorMessage = $newUser . " is already in use.  An old username can not be changed to an existing username.<br>";
        }
        return $errorMessage;
    }

    /**
     * @param $oldUser
     * @param $newUser
     * @return array
     */
    #[ArrayShape(['count' => "int", 'resultTable' => "string", 'selectSQL' => "string", 'updateSQL' => "string"])]
    private function previewUserChanges($oldUser, $newUser): array
    {
// todo get this in a method and call from here as well as change user.
        global $db_collation;
        $allSelectSQL = '';
        $allUpdateSQL = '';
        $resultTable = "<table class='table table-striped'>" .
            "<tr><th>Table</th><th>Column</th><th>Count</th></tr>";
        $rowCountTotal = 0;
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            if (!$tableAndColumn['has_table']) {
                continue;
            }

            if ($tableAndColumn['is_log'] && !$this->include_logs) {
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
        $resultTable .= "</table>";
        $resultArray = [
            'count' => $rowCountTotal,
            'resultTable' => $resultTable,
            'selectSQL' => $allSelectSQL,
            'updateSQL' => $allUpdateSQL
        ];

        return $resultArray;
    }


    /**
     * @param mixed $oldUser
     * @return bool
     */
    private function findUser($oldUser): bool
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
