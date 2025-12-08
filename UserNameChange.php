<?php
/**
 * REDCap External Module: Username Change
 * Adds the ability to modify a username
 * @author Greg Neils
 */

namespace MGB\UserNameChange;

use Exception;
use ExternalModules\AbstractExternalModule;
use Logging;
use mysqli_result;

/**
 * REDCap External Module: Username Change
 */
class UserNameChange extends AbstractExternalModule
{
    /**
     * all tables and columns in the Database that this EM knows about.
     * @var string[][]
     */
    private array $tablesAndColumns;

    /**
     * @var string Page URL
     */
    private string $pageUrl;

    /**
     * @var object mysql_result of user_information table.
     */
    private object $userInformation;

    /**
     * @var Object REDCap User Object.
     */
    private object $user;

    /**
     * @var string the action that the end user requested the module do.  Example: preview user change, process bulk user change.
     */
    private string $action;

    /**
     * @var bool include log tables
     */
    private bool $includeLogs;

    /**
     * @var string the inline styles for main navigation links.
     */
    private string $topLinkStyle = 'color:white; text-decoration:none; font-size:1.5em; font-weight:bold;';
    /**
     * @var string the inline styles for drop down links.
     */
    private string $subLinkStyle = 'text-decoration:none; letter-spacing:1px; font-weight:bold; font-size:1.5em;';

    /**
     * @var string the HTML style for an active link.
     */
    private string $actionStyle = ' font-style: italic;';

    /**
     * @var bool true=Verbose Feedback, false=Minimal Feedback.
     */
    private bool $feedbackVerbose;
    /**
     * SQL to turn OFF foreign key checks.
     */
    const FOREIGN_KEY_CHECKS_OFF = 'SET FOREIGN_KEY_CHECKS = 0;';
    /**
     * SQL to turn on foreign key checks
     */
    const FOREIGN_KEY_CHECKS_ON = 'SET FOREIGN_KEY_CHECKS = 1;';
    /**
     * SQL to turn OFF SQL Safe Updates
     */
    const SQL_SAFE_UPDATES_OFF = 'SET SQL_SAFE_UPDATES = 0;';
    /**
     * SQL to turn on Safe Updates
     */
    const SQL_SAFE_UPDATES_ON = 'SET SQL_SAFE_UPDATES = 1;';
    /**
     * @var string display the username field as either an open text field or a dropdown list. System Level Setting.
     */
    private string $oldUsernameFieldType;
    /**
     * @var array containing lowercased usernames from the user information table.
     */
    private array $userInformationArrayLowerCase;
    /**
     * @var array usernames from the user rights table, all lowercased.
     */
    private array $userRightsLowerCase;
    /**
     * True, all usernames should be lowercased.  This is a system level option.
     * @var bool
     */
    private bool $newUsernameLowerCase;
    private bool $showFlower;

    /**
     *
     */
    private
    function initialize(): void
    {

        $this->user = $this->getUser();

        $this->feedbackVerbose = true;

        if ($this->getSystemSetting('feedback') == '0') {
            $this->feedbackVerbose = false;
        }

        if ($this->getSystemSetting('old_username_field_type') == 'dropdown') {
            $this->oldUsernameFieldType = 'dropdown';
        } else {
            $this->oldUsernameFieldType = 'text';
        }

        if (strtolower($this->getSystemSetting('new_username_case')) == 'lower') {
            $this->newUsernameLowerCase = true;
        } else {
            $this->newUsernameLowerCase = false;
        }

        if (strtolower($this->getSystemSetting('show_flower')) == 'hide') {
            $this->showFlower = false;
        } else {
            $this->showFlower = true;
        }

        $this->tablesAndColumns = $this->getDatabaseTables();

        $this->pageUrl = $this->getUrl('change_usernames.php');
        $selectUserInformationSQL = 'SELECT `username`' .
            ' FROM redcap_user_information ORDER BY `username`';
        $this->userInformation = $this->query($selectUserInformationSQL, []);


        $resultInformation = $this->query($selectUserInformationSQL, []);
        $userInformationArray = [];

        if ($resultInformation instanceof mysqli_result) {
            while ($row = $resultInformation->fetch_assoc()) {
                $userInformationArray[] = $row['username'];
            }
        }

        $this->userInformationArrayLowerCase = array_map('strtolower', $userInformationArray);

        $selectUserRightsSQL = 'SELECT `username` FROM redcap_user_rights ORDER BY `username`';
        $userRightsResult = $this->query($selectUserRightsSQL, []);
        $userRights = [];
        if ($userRightsResult instanceof mysqli_result) {
            while ($row = $userRightsResult->fetch_assoc()) {
                $userRights[] = $row['username'];
            }
        }

        $this->userRightsLowerCase = array_map('strtolower', $userRights);


        $validPostActions = [
            'single_username_preview',
            'single_username_change',
            'bulk_username_preview',
            'bulk_username_update',
            'bulk_auth_delete_preview'
        ];
        if (isset($_REQUEST['form_action'])) {
            $form_action = $this->sanitize($_REQUEST['form_action']);
        } else {
            $form_action = '';
        }
        $validActions = [
            'read_me',
            'auth_methods_preview',
            'db_info',
            'dictionaries',
            'external_modules',
            'project_users',
            'change_username_start',
            'single_username_change',
        ];
        if (!isset($_REQUEST['action'])) {
            $this->action = 'read_me';
        } else if (in_array($_REQUEST['action'], $validActions)) {
            $this->action = $_REQUEST['action'];
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
        return $tables;
    }

    /**
     *
     */
    public
    function makePage(): void
    {
        $this->initialize();

        if (!isset($this->action)) {
            echo 'Unknown Action.';
            return;
        }

        $isSuperUser = $this->user->isSuperUser();
        if ($isSuperUser !== true) {
            echo('This page is unavailable.');
            return;
        }

        // todo, this isn't the right place for this.  The method should update the property anyway.
        //  Since it is not necessary on every page is it worth refactoring and specifying, or calling it good?

        echo $this->makeNavBar();

        if ($this->action === 'read_me') {
            $this->echoReadMePage();
        } else if ($this->action === 'auth_methods_preview') {
            echo $this->makeAuthenticationMethodsPage();
        } else if ($this->action === 'bulk_username_preview') {
            $this->bulkUserNamePreview();
        } else if ($this->action === 'bulk_username_update') {
            $this->bulkUserNameUpdate();
        } else if ($this->action === 'db_info') {
            $this->showDBInfo();
        } else if ($this->action === 'external_modules') {
            $this->showExternalModules();
        } else if ($this->action === 'project_users') {
            $this->showProjectUsers();
        } else if ($this->action === 'dictionaries') {
            $this->showDictionariesInfoPage();
        } else if ($this->action === 'passwords') {
            $this->showPasswordInfoPage();
        } else if ($this->action === 'change_username_start') {
            $this->makeChangeUserNamePage();
        } else if ($this->action === 'single_username_preview') {
            $this->singleUserNamePreview();
        } else if ($this->action === 'single_username_change') {
            $this->singleUserNameChange();
        } else if ($this->action === 'bulk_auth_delete_preview') {
            $this->bulkAuthDeletePreview();
        } else {
            echo "Sorry, " . htmlspecialchars($this->sanitize($this->action)) . " that is not an available action.";
        }
        $this->echoDisclaimer();
        $this->echoSunflowerHTML();
        echo $this->getButtonCopyJS();
    }


    /**
     *
     */
    private
    function singleUserNamePreview(): void
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if ($this->validateUserNameChanges($oldUser, $newUser)) {

            $results = $this->previewUserChanges($oldUser, $newUser);

            $html = "<h4>Number of rows that will be updated in the database: " . $results['count'] . "</h4>";
            if ($this->feedbackVerbose) {
                $html .= $results['resultTable'] .
                    '<h5>Select SQL</h5>' .
                    '<pre>' .
                    $results['selectSQL'] .
                    '</pre>';

            }
            if ($this->newUsernameLowerCase) {
                $html .= '<p>The new username will be lowercase</p>';
            }
            $html .= '<h5>Update SQL</h5><pre id="single_id_update">' .
                self::SQL_SAFE_UPDATES_OFF . '<br>' .
                self::FOREIGN_KEY_CHECKS_OFF . '<br>' .
                $results['updateSQL'] . '<br>' .
                self::SQL_SAFE_UPDATES_ON . '<br>' .
                self::FOREIGN_KEY_CHECKS_ON .
                '</pre>' .
                $this->makeSingleUserChangeFinalizeForm($oldUser, $newUser);
            $html .= '<button type="button" class="btn btn-success btn-sm" ' .
                'onclick="unc_copy_pre_text(\'single_id_update\', this);">' .
                'Copy the Update SQL to the Clipboard </button>';
        } else {
            $html = $this->getUserNameValidationErrors($oldUser, $newUser);
        }
        echo $html;
    }


    /**
     * receive an old and new username and change the tables accordingly.
     */
    private
    function singleUserNameChange(): void
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if ($this->singleUserNameUpdate($oldUser, $newUser)) {
            echo '<div class="alert alert-secondary">' .
                "<h4>Outcome: Changed User $oldUser to $newUser </h4>" .
                '</div>';
        } else {
            echo $this->getUserNameValidationErrors($oldUser, $newUser);
        }
    }


    /**
     * @param $oldUser
     * @param $newUser
     * @return bool
     */
    private
    function singleUserNameUpdate($oldUser, $newUser): bool
    {
        if ($this->validateUserNameChanges($oldUser, $newUser)) {
            $this->commitUserNameChange($oldUser, $newUser);
            return true;
        }

        return false;
    }


    /**
     *
     */
    private
    function bulkUserNamePreview(): void
    {
        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            echo '<h4>Please provide a CSV list of old usernames and new usernames. One row per change.</h4>';
            exit;
        }

        $html = '';
        $allUserNamesValid = true;
        $ids = explode("\n", str_replace("\r", "", $bulkCSV));

        // check for duplicate entries.
        $uniqueIds = array_unique($ids);
        if (count($ids) !== count($uniqueIds)) {
            $allUserNamesValid = false;
        }

        $justOldUserNames = array_map(function ($item) {
            return explode(',', $item)[0];
        }, $ids);

        $justNewUserNames = array_map(function ($item) {
            return explode(',', $item)[1];
        }, $ids);

        if (count(array_unique($justOldUserNames)) !== count($uniqueIds)) {
            $allUserNamesValid = false;
        }
        if (count(array_unique($justNewUserNames)) !== count($uniqueIds)) {
            $allUserNamesValid = false;
        }


        $counter = 0;
        $resultsTables = "";
        $selectSQL = "";
        $updateSQL = "";
        foreach ($ids as $id) {
            $counter++;
            $names = explode(',', $id, 5);
            if (count($names) === 2) {
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                $isValid = $this->validateUserNameChanges($oldUser, $newUser);
                if ($isValid) {
                    $results = $this->previewUserChanges($oldUser, $newUser);
                    $resultsTables .= $results['resultTable'];
                    $selectSQL .= $results['selectSQL'];
                    $updateSQL .= $results['updateSQL'];
                } else {
                    $allUserNamesValid = false;
                    $html .= '<div class="alert alert-warning"><h4>Check line ' . $counter . '.<br>' .
                        "Old: $oldUser | New: $newUser" .
                        $this->getUserNameValidationErrors($oldUser, $newUser) .
                        '</h4></div>';
                }
            } else {
                $html .= '<div class="alert alert-danger"><h4>Check line ' . $counter . ' for an extra comma or lack of one.</h4></div>';
                $allUserNamesValid = false;
            }
        }
        if ($allUserNamesValid) {
            $html .= '<div class="alert alert-secondary">' .
                '<h4>Validated. Please verify the data before proceeding.';
            if ($this->newUsernameLowerCase) {
                $html .= '<br>The new username will be lowercase';
            }
            $html .= '</h4></div>';
            if ($this->feedbackVerbose) {
                $html .= $resultsTables .
                    '<h5>Select SQL</h5><pre>' . $selectSQL . '</pre>';
            }
            $html .= '<h5>Update SQL</h5><pre id="bulk_username_sql_update">' .
                '-- Created ' . date('Y-m-d H:i:s') . '<br><br>' .
                self::SQL_SAFE_UPDATES_OFF . '<br>' .
                self::FOREIGN_KEY_CHECKS_OFF . '<br><br>' .
                $updateSQL . '<br>' .
                self::SQL_SAFE_UPDATES_ON . '<br>' .
                self::FOREIGN_KEY_CHECKS_ON . '<br>' .
                '</pre>' .
                $this->bulkUserNameForm($bulkCSV) .
                '<button type="button" class="btn btn-success btn-sm" ' .
                'onclick="unc_copy_pre_text(\'bulk_username_sql_update\', this);">' .
                'Copy the Update SQL to the Clipboard</button>';

        } else {
            $html .= '<div class="alert alert-danger"><h4>Input must be corrected before proceeding</h4></div>';
            if (count($ids) !== count($uniqueIds)) {
                $html .= '<div class="alert alert-danger"><h4>There are duplicate rows that need to be cleaned.</h4></div>';
            }
            if (count(array_unique($justOldUserNames)) !== count($uniqueIds)) {
                $html .= '<div class="alert alert-danger"><h4>There are duplicate old usernames that need to be cleaned.</h4></div>';
            }

            if (count(array_unique($justNewUserNames)) !== count($uniqueIds)) {
                $html .= '<div class="alert alert-danger"><h4>There are duplicate new usernames that need to be cleaned.</h4></div>';
            }
        }
        echo $html;
    }

    /**
     *
     */
    private
    function bulkUserNameUpdate(): void
    {
        $html = '';

        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            $allUserNamesValid = false;
            $html .= 'No CSV of username received';
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
                    $html .= $this->getUserNameValidationErrors($oldUser, $newUser);
                }
            } else {
                $html .= 'There is an error around line ' . $counter . '.<br>';
                $allUserNamesValid = false;
            }
        }

        // if ALL usernames are valid, proceed with the change.
        if ($allUserNamesValid) {
            echo '<div class="alert alert-secondary"><h4>Results of bulk upload.</h4></div>';
            foreach ($ids as $id) {
                $names = explode(',', $id, 5);
                $oldUser = $this->sanitize($names[0]);
                $newUser = $this->sanitize($names[1]);
                if ($this->singleUserNameUpdate($oldUser, $newUser)) {
                    $html .= '<div class="alert alert-secondary">' .
                        "<h4>Changed $oldUser to $newUser </h4>" .
                        '</div>';
                } else {
                    $html .= $this->getUserNameValidationErrors($oldUser, $newUser);
                }
                $html .= '<hr>';
            }
        } else {
            $html .= '<h3 class="alert alert-danger">Input must be corrected before proceeding.</h3>';
        }
        echo $html;
    }

    /**
     * @param $bulkCSV
     * @return string
     */
    private
    function bulkUserNameForm($bulkCSV): string
    {
        $logLabel = '<p><strong>Include logs';
        $logLabel .= $this->includeLogs ? ' Yes' : ' No';
        $logLabel .= '</strong></p>';

        $logCheck = $this->includeLogs ? ' checked' : '';

        $log = "<input type=\"checkbox\" name=\"include_logs\" hidden $logCheck>";

        return '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Bulk Username Change</h5><p>Click submit to finalize the username change. Proceed with caution.</p>' .
            '<form  action="' . $this->pageUrl . '" method="post" enctype="multipart/form-data">' .
            '<p>' .
            $logLabel .
            '</p>' .
            '<div class="form-group">' .
            '<label for="csvUserNames">The following usernames will change:</label>' .
            '<textarea name="csvUserNames" id="csvUserNames" class="form-control" rows="5" readonly>' .
            trim($bulkCSV) . '</textarea>' .
            '</div>' .
            $log .
            '<button class="btn btn-success" type="submit" name="form_action" value="bulk_username_update">Submit</button>' .
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
        $boldStyle = ' style="font-weight:bold;"';

        echo $this->makeTableList();

        $columnSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME, DATA_TYPE " .
            "FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE `COLUMN_NAME` LIKE '%USER%'" .
            " AND `TABLE_SCHEMA` = '" . $db . "'" .
            " AND DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext') " .
            ' ORDER BY TABLE_NAME, COLUMN_NAME;';

        $tableSQL = 'SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION ' .
            'FROM INFORMATION_SCHEMA.TABLES' .
            " WHERE `TABLE_SCHEMA` = '" . $db . "'";


        $columnResult = $this->query($columnSQL, []);
        $tableResult = $this->query($tableSQL, []);

        $tableCollations = [];
        while ($row = $tableResult->fetch_assoc()) {
            $tableCollations[$row['TABLE_NAME']] = $row['TABLE_COLLATION'];
        }


        $pageData = '<p>The underlying database tables used by REDCap at your institution may be slightly different from the tables listed below.</p>' .
            '<p>To change a username, the database must be queried and references to the old username located and updated</p>' .
            '<p>Tables may be added to REDCap at anytime in the future. This External Module only updates a fixed set of tables and columns.  At some point, this fixed list may become outdated by the addition of a new table that includes a username.</p>' .
            '<p>The list below includes all tables with at least one column containing the word user.</p>' .
            '<ol>' .
            '<li>Tables in bold may be included in the update.</li>' .
            '<li>Regular entries = Detected but not updated by the module.</li>' .
            '</ol>' .
            '<h4>Helpful SQL Snippet to find columns with the word "user" in them:</h4>' .
            '<code>SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME <br>'.
            ' FROM INFORMATION_SCHEMA.COLUMNS <br> ' .
            ' WHERE `COLUMN_NAME` LIKE "%USER%"' .
            ' AND `TABLE_SCHEMA` = "' . htmlspecialchars($db) . '"' .
            " AND DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext') " .
            ' ORDER BY TABLE_NAME, COLUMN_NAME;' .
            '</code>' .
            '<br><br>' .
            '<h4>The REDCap system level db_collation is set to ' . htmlspecialchars($db_collation) . '</h4>' .
            '<p>Helpful SQL Snippets to view colations</p>' .
            '<p><strong>Query 1</strong><p>' .
            '<code>' . htmlentities($columnSQL) . '</code>' .
            '<p><strong>Query 2</strong><p>' .
            '<code>' . htmlentities($tableSQL) . '</code>' . '</br>' .
            '<div class="alert alert-success">' .
            '<p class="text-center"><strong>YOUR Database Info</strong></p>' .
            '<p><strong>Rows in bold</strong>' .
            ' contain a table and column that reference user and will be included in the SQL update.</p></div>';


        if ($columnResult->num_rows > 0) {
            $order = 0;
            $resultTable = '<table class="table table-striped table-bordered table-hover"><tr>' .
                '<th>Order</th>'.
                '<th>Table</th>'.
                '<th>Table<br>Collation</th>'.
                '<th>Column<br>Name</th>'.
                '<th>Column<br>Collation</th>'.
                '<th>Included</th></tr>';
            while ($mySqlResult = mysqli_fetch_array($columnResult)) {
                $tableIncludedInUpdate = false;
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (strtolower($update['table']) === strtolower($mySqlResult['TABLE_NAME']) &&
                        strtolower($update['column']) === strtolower($mySqlResult['COLUMN_NAME'])) {
                        $tableIncludedInUpdate = true;
                        break;
                    }
                }
                $order++;
                if ($tableIncludedInUpdate) {
                    $resultTable .= $boldStyle;
                }
                $resultTable .= '>' .
                    "<td>$order</td>" .
                    '<td>' . htmlspecialchars($mySqlResult['TABLE_NAME'] ?? '', ENT_QUOTES) . '</td>';
                if (key_exists($mySqlResult['TABLE_NAME'], $tableCollations)) {
                    $resultTable .= '<td>' . $this->escape($tableCollations[$mySqlResult['TABLE_NAME']]) . '</td>';
                } else {
                    $resultTable .= '<td>Excluded table</td>';
                }
                $resultTable .= '<td>' . $this->escape($mySqlResult['COLUMN_NAME'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . $this->escape($mySqlResult['COLLATION_NAME'] ?? '', ENT_QUOTES) . '</td>';
                if ($tableIncludedInUpdate) {
                    $resultTable .= '<td>Yes</td>';
                } else {
                    $resultTable .= '<td>No</td>';
                }
                $resultTable .= '</tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<h3 class="alert-success alert">There are no results for column collations. This result is strange and should never occur.</h3>';
        }
        $pageData .= '<h4 class="alert alert-info">A collation defines how character strings are compared and sorted.</h4>' .
            '<p>For username matching to work correctly, collations must be compatible.</p>' .
            '<p>If collation mismatches exist between tables/columns, the update may fail silently or behave unexpectedly.</p>';
        echo $pageData;
    }

    /**
     *
     */
    private
    function echoReadMePage(): void
    {
        include(__DIR__ . '/html/readme.html');
    }

    /**
     * Display information about how changing a username could affect External Modules
     * @return void
     */
    private function showExternalModules(): void
    {
        include(__DIR__ . '/html/external_modules.html');
        $emMeta = $this->getEMsWithUser();
        if ($emMeta->num_rows > 0) {
            $html = '<table  class="table table-striped table-bordered table-hover">' .
                '<tr>' .
                '<th>PID or system.</th>' .
                '<th>EM ID</th>' .
                '<th>EM setting variable</th>' .
                '</tr>';
            while ($row = mysqli_fetch_array($emMeta)) {
                $html .= '<tr>' .
                    '<td>' . htmlspecialchars($row['project_id'] ?? 'System Level', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($row['external_module_id'] ?? 'Unknown', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($row['key'] ?? '', ENT_QUOTES) . '</td>' .
                    '</tr>';
            }
            $html .= '</table>';
        } else {
            $html = '<h4 class="alert alert-success">There are no results for settings with "user" in them.</h4>';
        }
        echo $html;
    }

    /**
     * Display a page with how the data access group can have an effect when changing a username.
     * @return void
     */
    private function showProjectUsers(): void
    {
        echo '<h3 class="alert">Project Users</h3>';

        // DAGs
        $dagSQL = 'SELECT `project_id`, `group_id`, `username` FROM redcap_data_access_groups_users a ' .
            'WHERE a.username NOT IN (SELECT `username` FROM redcap_user_information)';
        $dagData = $this->query($dagSQL, []);
        $dagHtml = '<h4>Dag information.</h4>' .
            '<p>User can be assigned to a DAG but NOT have an entry in the user_information table. ' .
            'When that happens, the SQL username update query will fail because duplicate usernames are not allowed. ' .
            'The table below is everyone that is assigned to a DAG but that does NOT belong to the user_information table.</p>';

        if ($dagData->num_rows > 0) {
            $dagHtml .= '<table class="table table-striped table-bordered table-hover">' .
                '<tr><th>Project Id</th><th>Group Id</th><th>Username</th></tr>';
            while ($dags = mysqli_fetch_array($dagData)) {
                $dagHtml .= '<tr>' .
                    '<td>' . htmlspecialchars($dags['project_id'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($dags['group_id'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($dags['username'] ?? '', ENT_QUOTES) . '</td>' .
                    '</tr>';
            }
            $dagHtml .= '</table>';
        } else {
            $dagHtml .= '<h4 class="alert alert-success">Good news. There are no users in the redcap_data_access_groups_users table that do NOT have an entry in the user_information table.</h4>';
        }


        // User Rights
        $rightsSQL = 'SELECT `project_id`, `group_id`, `username`, `role_id` FROM redcap_user_rights a ' .
            'WHERE a.username NOT IN (SELECT `username` FROM redcap_user_information)';

        $rightsData = $this->query($rightsSQL, []);


        $rightsHtml = '<h4>Project User Rights information.</h4>' .
            '<p>User can be assigned to a User Rights Role but NOT have an entry in the user_information table. ' .
            'When that happens, the SQL username update query will fail because duplicate usernames are not allowed. ' .
            'The table below is everyone that is assigned to a Project but that does NOT belong to the user_information table.</p>';


        if ($rightsData->num_rows > 0) {
            $rightsHtml .= '<h4 class="alert alert-warning">Look into these users as they do NOT have an account in REDCap.</h4>' .
                '<table class="table table-striped table-bordered table-hover">' .
                '<tr><th>Project Id</th><th>Group Id</th><th>Username</th><th>Role Id</th></tr>';
            while ($rights = mysqli_fetch_array($rightsData)) {
                $rightsHtml .= '<tr>' .
                    '<td>' . htmlspecialchars($rights['project_id'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($rights['group_id'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($rights['username'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($rights['role_id'] ?? '', ENT_QUOTES) . '</td>' .
                    '</tr>';
            }
            $rightsHtml .= '</table>';
        } else {
            $rightsHtml = '<h4 class="alert alert-danger">Good news. There are no users in the redcap_user_rights table that do NOT have an entry in the user_information table.</h4>';
        }


        echo $dagHtml;
        echo $rightsHtml;

    }

    /**
     * Display data dictionary information related to changing a user.
     * @return void
     */
    private function showDictionariesInfoPage(): void
    {
        include(__DIR__ . '/html/dictionaries.html');
        $dictionaryMeta = $this->getDictionariesWithUser();
        if ($dictionaryMeta->num_rows > 0) {
            $html = '<table  class="table table-striped table-bordered table-hover">' .
                '<tr>' .
                '<th>PID</th>' .
                '<th>Field Name</th>' .
                '<th>Form Name</th>' .
                '<th>Branching Logic</th>' .
                '<th>Calculations</th>' .
                '<th>misc</th>' .
                '</tr>';
            while ($row = mysqli_fetch_assoc($dictionaryMeta)) {
                $html .= '<tr>' .
                    '<td>' . htmlspecialchars($row['project_id'] ?? 'Unknown Project', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($row['field_name'] ?? 'Unknown Field', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($row['form_name'] ?? 'Unknown Form', ENT_QUOTES) . '</td>';

                // Branching Logic
                $html .= '<td>';
                if (str_contains(strtolower($row['branching_logic']), 'user')) {
                    $html .= '<strong>' .
                        htmlspecialchars($row['branching_logic'] ?? '', ENT_QUOTES) .
                        '</strong>';
                }
                $html .= '</td>';

                // Calculations
                $html .= '<td>';
                if (str_contains(strtolower(($row['element_enum'])), 'user')) {
                    $html .= '<strong>' .
                        htmlspecialchars($row['element_enum'] ?? '', ENT_QUOTES) .
                        '</strong>';
                }
                $html .= '</td>';

                // Misc includes action tags.
                $html .= '<td>';
                if (str_contains(strtolower(($row['misc'])), 'user')) {
                    $html .= '<strong>' .
                        htmlspecialchars($row['misc'] ?? '', ENT_QUOTES) . '</td>' .
                        '</strong>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        } else {
            $html = '<h4 class="alert alert-success">Nothing was found.</h4>';
        }
        echo $html;
    }

    /**
     *
     */
    private
    function showPasswordInfoPage(): void
    {
        include(__DIR__ . '/html/passwords.html');

        echo $this->makeBulkUserNameAuthDeleteForm();
    }

    /**
     * return MySQLi result with external module settings with "user" in their configuration.
     * @return mysqli_result
     */
    private function getEMsWithUser(): mysqli_result
    {
        $sql = "SELECT external_module_id, project_id, `key` " .
            "FROM redcap_external_module_settings " .
            "WHERE `key` LIKE '%user%' " .
            "ORDER BY project_id, external_module_id;";
        return $this->query($sql, []);

    }

    /**
     * Get all Project Data Dictionaries with anything related to user-name or username.
     * @return mysqli_result
     */
    private function getDictionariesWithUser(): mysqli_result
    {
        $limitPid = intval($_REQUEST['limit_pid']);
        $sql = 'SELECT `project_id`, `field_name`, `form_name`, `branching_logic`, `element_enum`, `misc` ' .
            'FROM `redcap_metadata` ' .
            "WHERE (`misc` LIKE '%USERNAME%' " .
            "OR `misc` LIKE '%[user-name]%' " .
            "OR `misc` LIKE '%@APPUSERNAME-APP%' " .
            "OR `element_enum` LIKE '%[user-name]%'" .
            "OR `branching_logic` LIKE '%[user-name]%')";
        if ($limitPid > 0) {
            $sql .= ' AND project_id = ' . $limitPid;
        }

        $sql .= " LIMIT 1000; ";
        echo "<pre>$sql</pre>";
        return $this->query($sql, []);

    }


    /**
     * accepts a string of data and cleans it using a Base REDCap function.
     * @param string|null $data the data to be sanitized.
     * @return string
     */
    private
    function sanitize(?string $data): string
    {
        if (is_null($data)) {
            return '';
        }
        // Note label_decode is a base REDCap function for cleaning data in a specific way.
        $data = trim(stripslashes(label_decode($data)));
        if ($this->newUsernameLowerCase) {
            $data = strtolower($data);
        }
        return htmlspecialchars($data);
    }

    /**
     * display the Single User upload form and the bulk upload form.
     */
    private
    function makeChangeUserNamePage(): void
    {
        echo $this->makeSingleUserForm();
        echo $this->makeBulkUserNameUploadForm();
    }

    /**
     * Create the navbar.
     * @return string
     */
    private
    function makeNavBar(): string
    {
        return '<div style="display: flex;justify-content: space-around;align-items: center;min-height: 45px;background-color: #43699a; padding-top:20px;">' .
            '<ul class="user_name_change_nav_bar"' .
            ' style="display:flex; justify-content:space-around; width:100%;">' .
            $this->makeReadMeLink() .
            $this->makeChangeUserLink() .
            '<li class="nav-item dropdown" style="list-style:none; ' . $this->topLinkStyle . '">' .
            '<a class="nav-link dropdown-toggle" style="color:white;" href="#" id="uncNavbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' .
            'Critical Information' .
            '</a>' .
            '<div class="dropdown-menu" aria-labelledby="uncNavbarDropdown">' .
            $this->makeDBInfoLink() .
            $this->makeAuthMethodLink() .
            $this->makeEMInfoLink() .
            $this->makeDictionaryInfoLink() .
            $this->makePasswordsLink() .
            $this->makeProjectUsersInfoLink() .
            '</div>' .
            '</li>' .
            '</ul></div>';
    }

    /**
     * echo disclaimer HTML.
     */
    private
    function echoDisclaimer(): void
    {
        include(__DIR__ . '/html/disclaimer.html');
    }

    /**
     * get the Bulk Upload Form.
     * @return string
     */
    private
    function makeBulkUserNameUploadForm(): string
    {
        return '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Bulk UserName Change </h5><p>Process multiple users. Each row represents one user. No headers.' .
            ' Each row must be in the format of<br><br> old_user_name,new_user_name</p>' .
            '<form action = "' . $this->pageUrl . '" method = "post" enctype = "multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">Paste in the csv data below</label>' .
            '<textarea name = "csvUserNames" id = "csvUserNames" class="form-control" rows="5"></textarea>' .
            '</div>' .
            '<div class="form-group form-check">' .
            '<input type="checkbox" id="include_logs" name="include_logs" class="form-check-input">' .
            '<label for="include_logs" class="form-check-label">Include logs:</label>' .
            '</div>' .
            '<button class="btn btn-success" type = "submit" name = "form_action" value="bulk_username_preview">Preview</button>' .
            '</form></div>';
    }

    /**
     * Create a list of all tables that are known and related metadata.
     * @return string
     */
    private
    function makeTableList(): string
    {
        $table = '<div class="alert alert-success">' .
            '<h4 class="text-center"><strong>Known database columns.</strong></h4></div>' .
            '<p>This is a list of tables that may have usernames that may be updated by this External Module.' .
            ' Your version of REDCap may not have one of the tables below.' .
            ' "Yes" is in the "In DB" column means the table exists and may be updated.' .
            ' If "No" is in the "In DB" column, it means your database does not exist in your instance.' .
            ' NOTE: Column names are not verified!. <strong>If the module crashes during preview, do not continue. Manually inspect the database first.</strong></p>' .
            '<table class="table table-striped table-condensed">' .
            '<tr><th>Order</th><th>Table</th><th>Column</th><th>Table in DB</th><th>Is log Table</th></tr>';
        $order = 0;
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            $order++;
            $table .= '<tr>' .
                '<td>' . $order . '</td>' .
                '<td>' . $tableAndColumn['table'] . '</td>' .
                '<td>' . $tableAndColumn['column'] . '</td>' .
                '<td>' . (($tableAndColumn['has_table']) ? "Yes" : "No") . '</td>' .
                '<td>' . (($tableAndColumn['is_log']) ? "Yes" : "No") . '</td>' . '</tr>';
        }
        $table .= '</table>';
        return $table;
    }

    /**
     * Get the Single User Update form.
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
            '<form action="' . $this->pageUrl . '&action=single_username_preview" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name">Old Username </label>';
        if ($this->oldUsernameFieldType == 'dropdown') {
            $form .= '<select name="old_name" id="old_name" class="form-control">';
            foreach ($this->userInformation as $user) {
                $form .= '<option value="' . $user['username'] . '"';
                if ($oldUserName === $user['username']) {
                    $form .= ' selected ';
                }

                $form .= '>' . $user['username'] . '</option>';
            }
            $form .= '</select>';
        } else {
            $form .= '<input type="text" name="old_name" id="old_name" class="form-control" value="' . $oldUserName . '">';
        }
        $form .= '</div>' .
            '<div class="form-group">' .
            '<label for="new_name">New Username </label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control"  value="' . $newUserName . '">' . '<br>' .
            '</div>' .
            '<div class="form-group form-check">' .
            '<input type="checkbox" id="include_logs" name="include_logs" class="form-check-input">' .
            '<label for="include_logs" class="form-check-label">Include logs:</label>' .
            '</div>' .
            '<div class="form-group">';
        if ($this->action === 'change_username_start') {
            $form .= '<button class="btn btn-success" ' .
                'style="margin-right: 30px;" type="submit" name="form_action"' .
                ' value="single_username_preview">Review</button>';
        } else if ($this->action === 'single_username_preview') {
            $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="single_username_change">' .
                'Commit Username Change</button>';
        } else {
            $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="whoops">Whoops</button>';
        }
        $form .= '</div>' .
            '</form></div>';
        return $form;
    }


    /**
     * Gets a single user form to send to REDCap to update the username.
     * After the user submitted the single user change form, show another one to verify the data!
     * @param $oldUser
     * @param $newUser
     * @return string
     */
    private
    function makeSingleUserChangeFinalizeForm($oldUser, $newUser): string
    {

        $form = '<h4 class="alert alert-danger">Please review the information above and below for accuracy. ' .
            'You agree to take full responsibility for running this code. Pressing the button below cannot be undone.</h4>' .
            '<div class="card p-3"><form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name"><strong>Old Username </strong> ' . $oldUser . '</label>' .
            '<input name="old_name" id="old_name" class="form-control" value="' . $oldUser . '" readonly hidden>' .
            '</div>' .
            '<div class="form-group">' .
            '<label for="new_name"><strong>New Username </strong> ' . $newUser . '</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control" readonly hidden value="' . $newUser . '">' .
            '<br>' .
            '</div>';

        // Log Tables
        $logElement = $this->getLogElement("readonly hidden");
        $form .= $logElement .
            '<div class="form-group">' .
            '<button class="btn btn-warning" type="submit" name="form_action" value="single_username_change">Change User</button>' .
            '</div>' .
            '</form>';
        return $form;
    }

    /**
     * @param string $classes Example "readonly hidden"
     * @return string
     */
    private function getLogElement(string $classes = ''): string
    {
        // logs
        $checked = $this->includeLogs ? ' checked' : '';
        $label = $this->includeLogs ? 'Yes' : 'No';

        return '<div class="form-group form-check">' .
            '<input type="checkbox" name="include_logs" class="form-check-input' .
            ($classes ? " $classes" : '') . '"' .
            $checked .
            '>' .
            '<label class="form-check-label"><strong>Include logs:</strong> ' .
            $label .
            '</label>' .
            '</div>';
    }

    /**
     * @return bool return true if the log tables are included in the update.
     */
    private
    function set_include_logs(): bool
    {
        if (isset($_REQUEST['include_logs']) && $_REQUEST['include_logs'] === "on") {
            return true;
        }
        return false;
    }

    /**
     * @return string a link to the readme file.
     */
    private
    function makeReadMeLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'read_me') {
            $actionStyle = $this->actionStyle;
        }
        return '<li style="list-style:none;"><a href="' .
            $this->pageUrl . '&action=read_me" style="' . $this->topLinkStyle . $actionStyle .
            '">Read Me</a></li>';
    }

    /**
     * @return string A link to the username change page.
     */
    private
    function makeChangeUserLink(): string
    {
        $url = $this->pageUrl;
        $parameters = "";
        $actionStyle = '';
        if ($this->action === 'change_username_start' ||
            $this->action === 'single_username_preview' ||
            $this->action === 'single_username_change') {
            $actionStyle = $this->actionStyle;
        }
        $parameters .= '&action=change_username_start';
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
            $url . '" style="' . $this->topLinkStyle . $actionStyle . '">' .
            'Change User</a></li>';
    }

    /**
     * @return string a link to the authentication method page.
     */
    private
    function makeAuthMethodLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'auth_methods_preview') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=auth_methods_preview" style="' . $this->subLinkStyle . $actionStyle .
            '">Authentication</a>';
    }

    /**
     * @return string a link to the DB information page.
     */
    private
    function makeDBInfoLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'db_info') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=db_info" style="' . $this->subLinkStyle . $actionStyle .
            '">DB Info</a>';
    }

    /**
     * @return string a link to the EM information page.
     */
    private
    function makeEMInfoLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'external_modules') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=external_modules" style="' . $this->subLinkStyle . $actionStyle .
            '">External Models</a>';
    }

    /**
     * @return string a link to the EM information page.
     */
    private
    function makeProjectUsersInfoLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'project_users') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=project_users" style="' . $this->subLinkStyle . $actionStyle .
            '">Project Users</a>';
    }

    /**
     * @return string a link to the EM information page.
     */
    private
    function makeDictionaryInfoLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'dictionaries') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=dictionaries" style="' . $this->subLinkStyle . $actionStyle .
            '">Dictionaries</a>';
    }

    /**
     * @return string a link to the password information page.
     */
    private
    function makePasswordsLink(): string
    {
        $actionStyle = '';
        if ($this->action === 'passwords') {
            $actionStyle = $this->actionStyle;
        }
        return '<a class="dropdown-item" href="' .
            $this->pageUrl . '&action=passwords" style="' . $this->subLinkStyle . $actionStyle .
            '">Passwords</a>';
    }

    /**
     * @return string retrieve the authentication method for each project and display.
     * REDCap no longer allows different authentication methods in a project.
     */
    private
    function makeAuthenticationMethodsPage(): string
    {
        $authMethods = $this->getAuthenticationMethodSummary();
        $authMethodsInUse = [];

        // Trusted static HTML
        $filename = __DIR__ . '/html/auth_methods_summary.html';
        $pageData = file_get_contents($filename);

        if ($authMethods->num_rows > 0) {
            $authAvailable = '<table  class="table table-striped table-bordered table-hover"><tr><th>Authentication</th><th>Count</th></tr>';
            while ($method = mysqli_fetch_array($authMethods)) {
                $authMethodsInUse[] = $method['auth_meth'];
                $authAvailable .= '<tr><td>' . htmlspecialchars($method['auth_meth'] ?? '', ENT_QUOTES) . '</td>' .
                    '<td>' . htmlspecialchars($method['count'] ?? '', ENT_QUOTES) . '</td></tr>';
            }
            $authAvailable .= '</table>';
        } else {
            $authAvailable = '<h3 class="alert alert-success">There are no results for authentication methods. This result is strange and should probably never occur</h3>';
        }

        $pageData .= '<div style="padding:20px;margin:20px; border: 2px solid pink;">' .
            '<form><div class="form-group">' .
            '<label for="old_auth">Authentications in use in projects:</label>' .
            '<select name="old_auth" id="old_auth" class="form-control" onchange="generateSQL();">';
        $authFrom = "";
        foreach ($authMethodsInUse as $singleMethod) {
            $authFrom .= '<option value="' .
                htmlspecialchars($singleMethod ?? '', ENT_QUOTES) . '">' .
                htmlspecialchars($singleMethod ?? 'Choose one', ENT_QUOTES) .
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
            $authInProjects = '<h3 class="alert alert-success">There are no results for projects. This result is strange and should never occur.</h3>';
        }
        $pageData .= $authInProjects;

        return $pageData;
    }


    /**
     * echo the HTML contents to make the CSS Flower.
     */
    private
    function echoSunflowerHTML(): void
    {
        if ($this->showFlower) {
            $cssFlowerPath = $this->getUrl('css/flower.css');
            echo '<link rel="stylesheet" type="text/css" href="' . $cssFlowerPath . '">';
            include(__DIR__ . '/html/flower.html');
        }
    }


    /**
     * @return mysqli_result return the various authentication methods used by projects.
     */
    private
    function getAuthenticationMethodSummary(): mysqli_result
    {
        return $this->query('SELECT `auth_meth`, count(auth_meth) as count FROM redcap_projects group by auth_meth;', []);
    }

    /**
     * @return mysqli_result an array of all authentication methods.
     */
    private
    function getAuthenticationMethodDetails(): mysqli_result
    {
        return $this->query('SELECT project_id, project_name, auth_meth FROM redcap_projects;', []);
    }

    /**
     * Perform the SQL Update of all tables changing the old username to the new username.
     * @param $oldUser
     * @param $newUser
     */
    private
    function commitUserNameChange($oldUser, $newUser): void
    {
        $html = '';
        $resultTable = "<div class='alert alert-success'><h4>The following tables were updated</h4></div>" .
            '<table class="table table-striped">' .
            '<tr><th>Table</th><th>Column</th><th>Count</th><th>Error #</th></tr>';

        $allSqlReadable = self::SQL_SAFE_UPDATES_OFF . '<br>' .
            self::FOREIGN_KEY_CHECKS_OFF . '<br>' .
            "-- Begin $oldUser to $newUser<br>";

        $this->query(self::SQL_SAFE_UPDATES_OFF, []);
        $this->query(self::FOREIGN_KEY_CHECKS_OFF, []);

        foreach ($this->tablesAndColumns as $entry) {
            $tableName = $entry['table'];
            $columnName = $entry['column'];
            if (!$this->shouldIncludeInUsernameUpdate($entry)) {
                $resultTable .= '<tr><th>' . $tableName . '</th>' .
                    '<th>' . $columnName . '</th>' .
                    '<th>Excluded</th>' .
                    '</tr>';
                continue;
            }

            $sqlUpdateQuery = "UPDATE $tableName SET `$columnName` = CAST(CONVERT(? USING LATIN1) AS CHAR CHARACTER SET UTF8MB4) WHERE `$columnName` = ?";

            $sqlUpdateReadable = $this->createReadableSqlUpdate($tableName, $columnName, $oldUser, $newUser);

            if ($entry['sql_append'] !== '') {
                $sqlUpdateQuery .= " " . $entry['sql_append'];
                $sqlUpdateReadable .= " " . $entry['sql_append'];
            }

            $sqlUpdateQuery .= ';';
            $sqlUpdateReadable .= ';';
            $allSqlReadable .= $sqlUpdateReadable . "<br>";

            $result = $this->query($sqlUpdateQuery, [$newUser, $oldUser]);

            $resultTable .= '<tr><th>' . $tableName . '</th>' .
                '<th>' . $columnName . '</th>' .
                '<th>' . db_affected_rows() . '</th>';
            if (isset($result->error)) {
                $resultTable .= '<th>' . $result->error . '</th>';
            } else {
                $resultTable .= '<th>0</th>';
            }
            $resultTable .= '</tr>';
        }

        $this->query(self::SQL_SAFE_UPDATES_ON, []);
        $this->query(self::FOREIGN_KEY_CHECKS_ON, []);


        $sqlUpdateUserCommentsReadable = $this->getSqlUpdateUserCommentsReadable($oldUser, $newUser);
        $commentResult = $this->submitSqlUpdateUserComments($oldUser, $newUser);

        $allSqlReadable .= $sqlUpdateUserCommentsReadable . '<br>' .
            self::SQL_SAFE_UPDATES_ON . '<br>' .
            self::FOREIGN_KEY_CHECKS_ON . '<br>' .
            "-- End $oldUser to $newUser<br>";

        $resultTable .= "</table>";
        if ($this->feedbackVerbose) {
            $html .= $resultTable;
            $html .= '<div class="alert alert-success">Using the following UPDATE SQL:</div>' .
                '<pre>' .
                $allSqlReadable .
                '</pre>';
        }

        $logEvent = 'Changed user name. Old: ' . $oldUser . ' New: ' . $newUser . ' via External Module.';
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
        $this->log(
            "Username Changed",
            [
                "Old User" => $newUser,
                "New User" => $oldUser
            ]
        );
        echo $html;

    }

    /**
     * The User Comments field is a different query from the rest.
     * @param string $oldUser Old Username
     * @param string $newUser New Username
     * @return string the SQL statement to append the username changes to the user comments field.
     */
    private function getSqlUpdateUserCommentsReadable(string $oldUser, string $newUser): string
    {
        $now = strtotime("now");
        $logTime = date("Y-m-d H:i:s", $now);
        return 'UPDATE redcap_user_information SET ' .
            "`user_comments` = CONCAT(COALESCE(`user_comments`, \"\"), '$logTime Old username=$oldUser New username=$newUser') " .
            " WHERE `username` = \"$newUser\" LIMIT 1;";
    }

    /**
     * The User Comments field is a different query from the rest.
     * @param string $oldUser Old Username
     * @param string $newUser New Username
     *
     * NOTE, PHP STORM SAYS THIS RETURNS A MYSQLI_RESULT.  IT ACTUALLY RETURNS A BOOLEAN.
     */
    private function submitSqlUpdateUserComments(string $oldUser, string $newUser)
    {
        $now = strtotime("now");
        $logTime = date("Y-m-d H:i:s", $now);
        $sql = 'UPDATE redcap_user_information SET ' .
            "`user_comments` = CONCAT(COALESCE(`user_comments`, \"\"), \"$logTime Old username=\", ?, \" New username= \", ?) " .
            " WHERE `username` = ? LIMIT 1;";

        return $this->query($sql, [$oldUser, $newUser, $newUser]);
    }

    /**
     * The User Comments field is a different query from the rest.
     * @param string $oldUser Old Username
     * @param bool $parameterized true = Parameterized, False = Inline.
     * @return string the SQL statement to append the username changes to the user comments field.
     */
    private function getSqlSelectUserComments(string $oldUser, bool $parameterized): string
    {
        if ($parameterized) {
            return "SELECT `user_comments` FROM redcap_user_information WHERE `username` = ? LIMIT 1";
        } else {
            return "SELECT `user_comments` FROM redcap_user_information WHERE `username` = \"$oldUser\" LIMIT 1";
        }
    }

    /**
     * @param string $oldUser Old Username
     * @param string $newUser New Username
     * @return string an HTML string of validation errors.
     */
    private
    function getUserNameValidationErrors(string $oldUser, string $newUser): string
    {
        $errorBegin = '<h4 class="alert alert-danger">';
        $errorMessage = '';
        $errorEnd = '</h4>';
        if (!$this->isValidUsername($oldUser)) {
            $errorMessage .= '<br>The old username is not valid.';
        }
        if (!$this->isValidUsername($newUser)) {
            $errorMessage .= '<br>The new username is not valid.';
        }

        if (!$this->isUserInTableInformation($oldUser)) {
            $errorMessage .= "<br>The user, $oldUser, was not found in the user_information table.";
        }

        // Check if the new username already exists in either the user_information table or the user_rights table.
        if ($this->countOccurrencesInTables($newUser) > 0) {
            $errorMessage .= "<br>The username, $oldUser, cannot be changed to a username, $newUser, that name already exists in one or more tables.";
        } elseif ($this->isUserInTableRights($newUser)) {
            $errorMessage = "<br>The username, $oldUser, cannot be changed because $newUser has User Rights to a project.</h4>";
        }
        if (strlen($errorMessage) === 0) {
            $errorMessage = 'Unknown Error.';
        }
        return $errorBegin . $errorMessage . $errorEnd;
    }

    /**
     * @param string $oldUser Old Username
     * @param string $newUser New Username
     * @return array [
     * 'count' => $rowCountTotal,
     * 'resultTable' => $resultTable,
     * 'selectSQL' => $allSelectSQL,
     * 'updateSQL' => $allUpdateSQL
     * ];
     */
    private function previewUserChanges(string $oldUser, string $newUser): array
    {
        // todo get this in a method and call from here as well as change user.
        $sqlCommentBegin = "<br>-- Start $oldUser to $newUser <br>";
        $sqlCommentEnd = "<br>-- End $oldUser to $newUser <br>";

        $allSelectSQL = $sqlCommentBegin;
        $allUpdateSQLReadable = $sqlCommentBegin;
        $resultTable = "<h4>Change username from $oldUser to $newUser </h4>" .
            '<table class="table table-striped caption-top">' .
            '<tr><th>Table</th><th>Column</th><th>Count</th></tr>';
        $rowCountTotal = 0;
        foreach ($this->tablesAndColumns as $tableAndColumn) {
            $tableName = $tableAndColumn['table'];
            $columnName = $tableAndColumn['column'];

            if (!$this->shouldIncludeInUsernameUpdate($tableAndColumn)) {
                $resultTable .= '<tr><th>' . $tableName . '</th>' .
                    '<th>' . $columnName . '</th>' .
                    '<th>Excluded</th>' .
                    '</tr>';
                continue;
            }

            $sqlSelectQuery = "SELECT `$columnName` FROM $tableName WHERE `$columnName` = ?";
            $sqlSelectReadable = "SELECT `$columnName` FROM $tableName WHERE `$columnName` = \"$oldUser\";";

            $allSelectSQL .= $sqlSelectReadable . "<br>";

            $allUpdateSQLReadable .= $this->createReadableSqlUpdate($tableName, $columnName, $oldUser, $newUser);

            if ($tableAndColumn['sql_append'] !== '') {
                $allUpdateSQLReadable .= " " . $tableAndColumn['sql_append'];
            }

            $allUpdateSQLReadable .= ';<br>';

            try {
                $result = $this->query($sqlSelectQuery, [$oldUser]);
                $affectedRows = db_affected_rows();

                $resultTable .= '<tr><th>' . $tableName . '</th>' .
                    '<th>' . $columnName . '</th>' .
                    '<th>' . $affectedRows . '</th>' .
                    '</tr>';
                $rowCountTotal += $affectedRows;
            } catch (Exception $e) {
                // the end user is required to see the feedback verbose.
                $this->feedbackVerbose = true;

                $resultTable .= '<tr><td colspan="3" class="text-danger display-5">Error in table '
                    . htmlspecialchars($tableName, ENT_QUOTES) . ': '
                    . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</td></tr>';
            }
        }

        $sqlSelectUserCommentsReadable = $this->getSqlSelectUserComments($oldUser, false);
        $sqlUpdateUserCommentsReadable = $this->getSqlUpdateUserCommentsReadable($oldUser, $newUser);


        $allSelectSQL .= $sqlSelectUserCommentsReadable . $sqlCommentEnd;
        $allUpdateSQLReadable .= $sqlUpdateUserCommentsReadable . $sqlCommentEnd;

        $resultTable .= '</table>';
        return [
            'count' => $rowCountTotal,
            'resultTable' => $resultTable,
            'selectSQL' => $allSelectSQL,
            'updateSQL' => $allUpdateSQLReadable
        ];
    }


    /**
     * @param string $username Old Username
     * @return bool true if found, false if not found
     */
    private function isUserInTableInformation(string $username): bool
    {
        return in_array(strtolower($username), $this->userInformationArrayLowerCase);
    }

    /**
     * @param string $username
     * @return bool true if found, false if not found
     */
    private function isUserInTableRights(string $username): bool
    {
        return in_array(strtolower($username), $this->userRightsLowerCase);
    }


    /**
     * @param string $username
     * @return bool true if the length is allowable in REDCap.
     */
    private function isValidUsername(string $username): bool
    {
        $valid = false;
        if (preg_match('/^[a-zA-Z0-9_\-.\s\'@]+$/D', $username) === 1 and strlen($username) >= 3) {
            $valid = true;
        }
        return $valid;
    }

    /**
     * @param string $oldUser Old Username
     * @param string $newUser New Username
     * @return bool true if it is a valid username, otherwise false.
     */
    private function validateUserNameChanges(string $oldUser, string $newUser): bool
    {
        if (!$this->isValidUsername($oldUser)) {
            return false;
        }

        if (!$this->isValidUsername($newUser)) {
            return false;
        }

        if (!$this->isUserInTableInformation($oldUser)) {
            return false;
        }

//        if ($this->isUserInTableInformation($newUser)) {
//            return false;
//        }

        if ($this->countOccurrencesInTables($newUser)) {
            return false;
        }
        return true;
    }

    /**
     * include the javascript file via <script src ="url_to_source"><script>
     * @return string
     */
    private function getButtonCopyJS(): string
    {
        $path = $this->getUrl('js/button_copy.js');
        return '<script src="' . $path . '"></script>';
    }


    /**
     * get the Bulk Upload Form.
     * @return string
     */
    private
    function makeBulkUserNameAuthDeleteForm(): string
    {
        return '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h4 class="alert alert-danger">Generate SQL to remove username(s) from the redcap_auth and redcap_auth_history tables.</h4>' .
            '<h5>Bulk UserName Authentication Removal </h5><p>Process multiple users. Each row represents one user. No headers.' .
            ' Each row must be in the format of<br><br> user_name</p>' .
            '<form action = "' . $this->pageUrl . '" method = "post" enctype = "multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">Paste in the IDs below</label>' .
            '<textarea name = "csvUserNames" id = "csvUserNames" class="form-control" rows="5"></textarea>' .
            '</div>' .
            '<button class="btn btn-success" type = "submit" name = "form_action" value="bulk_auth_delete_preview">Preview</button>' .
            '</form></div>';
    }

    /**
     * Display information to the end user about Deleting users from the redcap_auth and redcap_auth_history tables.
     * @return void
     */
    private function bulkAuthDeletePreview(): void
    {

        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            echo '<h4>Please use provide a CSV list of old usernames and new usernames. One row per change.</h4>';
            exit;
        }

        $html = '';
        $errors = '';
        $removeSQL = '';
        $allUserNamesValid = true;
        $ids = explode("\n", str_replace("\r", "", $bulkCSV));

        // check for duplicate entries.
        $uniqueIds = array_unique($ids);
        if (count($ids) !== count($uniqueIds)) {
            $allUserNamesValid = false;
        }

        $justOldUserNames = array_map(function ($item) {
            return explode(',', $item)[0];
        }, $ids);

        if (count(array_unique($justOldUserNames)) !== count($uniqueIds)) {
            $allUserNamesValid = false;
        }

        $counter = 0;
        $selectAuthUsersSQL = 'SELECT `username` FROM redcap_auth ORDER BY `username`';
        $authUsers = $this->query($selectAuthUsersSQL, []);
        $rows = $authUsers->fetch_all();
        $dbUsernames = array_column($rows, 0);

        foreach ($ids as $id) {
            $counter++;
            $thisUser = $this->sanitize($id);
            if (in_array($thisUser, $dbUsernames)) {
                $removeSQL .= "DELETE FROM `redcap_auth` WHERE `username` = '$id';<br>" .
                    "DELETE FROM `redcap_auth_history` WHERE `username` = '$id';<br><br>";
            } else {
                $allUserNamesValid = false;
                $errors .= '<div class="alert alert-warning">' .
                    "<h4>Check line $counter <br> $thisUser is not in the auth table" .
                    '</h4></div>';
            }
        }
        if ($allUserNamesValid) {
            $html .= '<div class="alert alert-secondary"><h4>Validated. Please verify the data before proceeding.</h4></div>' .
                '<h5>DELETE SQL</h5>' .
                '<pre>' .
                '-- Created ' . date('Y-m-d H:i:s') . '<br><br>' .
                self::SQL_SAFE_UPDATES_OFF . '<br>' .
                self::FOREIGN_KEY_CHECKS_OFF . '<br>' .
                $removeSQL .
                self::SQL_SAFE_UPDATES_ON . '<br>' .
                self::FOREIGN_KEY_CHECKS_ON . '<br>' .
                '</pre>' .
                '<h4>Verify the SQL. You must run this script on your database.</h4>';

            echo $html;
        } else {
            $errors = '<div class="alert alert-danger"><h4>Input must be corrected before proceeding</h4></div>' . $errors;
            echo $errors;
        }
    }

    /**
     * Should a table/column be excluded from the update for various reasons?
     * @param $tableAndColumn
     * @return bool true when the table should be included. Otherwise, false.
     */
    private function shouldIncludeInUsernameUpdate($tableAndColumn): bool
    {
//  Checks
        $tableName = $tableAndColumn['table'];
        $columnName = $tableAndColumn['column'];

        if (empty($tableName) || empty($columnName)) {
            return false;
        }

        if (!$tableAndColumn['has_table']) {
            return false;
        }
        if ($tableAndColumn['is_log'] && !$this->includeLogs) {
            return false;
        }

        return true;
    }

    /**
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $oldUser Old UserName
     * @param string $newUser New Username
     * @return string SQL that the end user reads on the webpage
     */
    function createReadableSqlUpdate(string $tableName, string $columnName, string $oldUser, string $newUser): string
    {
        return 'UPDATE ' . htmlspecialchars($tableName) .
            ' SET `' . htmlspecialchars($columnName) . '`' .
            ' = CAST(CONVERT("' . htmlspecialchars($newUser) . '" USING LATIN1) AS CHAR CHARACTER SET UTF8MB4)' .
            ' WHERE `' . htmlspecialchars($columnName) . '` = "' . htmlspecialchars($oldUser) . '"';
    }

    public function countOccurrencesInTables(string $value): int|string
    {
        $queries = [];
        $values = [];
        foreach ($this->tablesAndColumns as $entry) {
            $table = $entry['table'];
            $column = $entry['column'];
            if (!$this->shouldIncludeInUsernameUpdate($entry)) {
                continue;
            }
            $queries[] = "SELECT 1 FROM `$table` WHERE `$column` = ?";
            $values[] = $value;
        }

        $unionQuery = implode(" UNION ALL ", $queries) . " LIMIT 1";

        try {
            $result = $this->query($unionQuery, $values);
        } catch (Exception $e) {
            // Log the error, rethrow, or handle gracefully
            error_log("Database query failed: " . $e->getMessage());
            return 1; // a number greater than 0 to indicate an error.
        }

        return $result->num_rows;
    }

    private function getDatabaseTables() {
        $tables = [
            ['table' => 'redcap_auth', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_auth_history', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_data_access_groups_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_esignatures', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_external_links_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_locking_data', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_locking_records', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_projects', 'column' => 'project_pi_username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_project_dashboards_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_reports_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_reports_edit_access_users', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => 'AND `report_id` IN (SELECT `report_id` FROM redcap_reports )'],
            ['table' => 'redcap_sendit_docs', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_allowlist', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_information', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_information', 'column' => 'user_sponsor', 'has_table' => false, 'is_log' => false, 'sql_append' => ''],
            ['table' => 'redcap_user_rights', 'column' => 'username', 'has_table' => false, 'is_log' => false, 'sql_append' => ' AND `project_id` IN (SELECT `project_id` FROM redcap_projects)'],
            ['table' => 'redcap_log_api_allowlist', 'column' => 'username', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_view', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_log_view_old', 'column' => 'user', 'has_table' => false, 'is_log' => true, 'sql_append' => ''],
            ['table' => 'redcap_rewards_logs', 'column' => 'username', 'has_table' => false, 'is_log' => true, 'sql_append' => '']
        ];

        global $db;
        $logTableSQL = 'SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES' .
            " WHERE `TABLE_SCHEMA` = ? " .
            " AND `TABLE_NAME` LIKE 'redcap_log_event%'";
        $logTableResult = $this->query($logTableSQL, [$db ]);
        $logTables = [];
        while ($logRow = $logTableResult->fetch_assoc()) {
            $logTables[] = [
                'table' => $logRow['TABLE_NAME'],
                'column' => 'user',
                'has_table' => false,
                'is_log' => true,
                'sql_append' => ''
                ];
        }

        $allTables = array_merge($tables, $logTables);

        $dbTables = $this->getTablesFromSchema();

        foreach ($allTables as $rowId => $tablesAndColumns) {
            if (in_array($tablesAndColumns['table'], $dbTables, true)) {
                $allTables[$rowId]['has_table'] = true;
            }
        }
        return $allTables;
    }

}
