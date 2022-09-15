<?php
/**
 * REDCap External Module: Username Change
 * Change a username
 * @author Greg Neils, Center for Disesase Control
 */

namespace CDC\UserNameChange;

use DateTime;
use ExternalModules\ExternalModules;
use ExternalModules\AbstractExternalModule;
use JetBrains\PhpStorm\Pure;
use Logging;
use Project;
use REDCap;

/**
 * REDCap External Module: Username Change
 */
class UserNameChange extends AbstractExternalModule
{
    private $lang;
    private $page;
    private $user_rights;
    private $get;
    private $post;
    private $userName;
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
    private $user;
    private string $action;


    public function __construct()
    {
        parent::__construct();
        $this->user = $this->getUser();

        $isSuperUser = $this->user->isSuperUser();
        if ($isSuperUser !== true) {
            echo('This page is unavailable.');
        }

        $this->tablesAndColumns = [
            ['table' => 'redcap_log_view', 'column' => 'user'],
            ['table' => 'redcap_log_event', 'column' => 'user'],
            ['table' => 'redcap_log_event2', 'column' => 'user'],
            ['table' => 'redcap_log_event3', 'column' => 'user'],
            ['table' => 'redcap_log_event4', 'column' => 'user'],
            ['table' => 'redcap_log_event5', 'column' => 'user'],
            ['table' => 'redcap_log_event6', 'column' => 'user'],
            ['table' => 'redcap_log_event7', 'column' => 'user'],
            ['table' => 'redcap_log_event8', 'column' => 'user'],
            ['table' => 'redcap_log_event9', 'column' => 'user'],
            ['table' => 'redcap_log_view_old ', 'column' => 'user'],
            ['table' => 'redcap_user_allowlist', 'column' => 'username'],
            ['table' => 'redcap_auth', 'column' => 'username'],
            ['table' => 'redcap_auth_history', 'column' => 'username'],
            ['table' => 'redcap_data_access_groups_users', 'column' => 'username'],
            ['table' => 'redcap_esignatures', 'column' => 'username'],
            ['table' => 'redcap_external_links_users', 'column' => 'username'],
            ['table' => 'redcap_locking_data', 'column' => 'username'],
            ['table' => 'redcap_locking_records', 'column' => 'username'],
            ['table' => 'redcap_sendit_docs', 'column' => 'username'],
            ['table' => 'redcap_project_dashboards_access_users', 'column' => 'username'],
            ['table' => 'redcap_reports_access_users', 'column' => 'username'],
            ['table' => 'redcap_user_information', 'column' => 'username'],
            ['table' => 'redcap_user_rights', 'column' => 'username'],
            ['table' => 'redcap_reports_edit_access_users', 'column' => 'username'],
            ['table' => 'redcap_user_information', 'column' => 'user_sponsor'],
            ['table' => 'redcap_projects', 'column' => 'project_pi_username']
        ];

        $this->pageUrl = $this->getUrl('change-usernames.php');
        $this->users = $this->query('select * from redcap_user_information', []);

        $this->setAction();
    }

    public function makePage(): void
    {
        echo $this->makeNavBar();

        if ($this->action === 'auth_methods') {
            echo $this->makeAuthenticationMethodsPage();
        } else if ($this->action === 'page_load') {
            $this->makeGetPage();
        } else if ($this->action === 'preview') {
            $oldUser = $this->sanitize($_REQUEST['old_name']);
            $newUser = $this->sanitize($_REQUEST['new_name']);
            if ($this->validateUserNameChanges($oldUser, $newUser)) {
                $results = $this->previewChanges($oldUser, $newUser);
                echo "Total Affected Rows: " . $results['count'];
                echo $results['resultTable'];
                echo "<h5>Select SQL</h5><pre>";
                echo $results['selectSQL'];
                echo "</pre>";
                echo "<h5>Update SQL</h5><pre style='font-size: 0.75em;'>";
                echo $results['updateSQL'];
                echo "</pre>";
                $this->createFinalSingleUserChangeForm($oldUser, $newUser);
            } else {
                echo $this->getUserNameChangeErrors($oldUser, $newUser);
            }
        } else if ($this->action === 'change_user') {
            $this->changeUser();
        } else if ($this->action === 'bulk') {
            $this->bulkProcess();
        } else if ($this->action === 'collation') {
            $this->showCollations();
        } else {
            echo "Sorry, that is not an available action.";
        }
    }

    private function bulkProcess()
    {
        $bulkCSV = $this->sanitize($_REQUEST['csvUserNames']);
        if ($bulkCSV === '') {
            echo 'no input';
            exit;
        }
        $allUserNamesValid = 1;
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
                $allUserNamesValid = $this->validateUserNameChanges($oldUser, $newUser);
                if ($this->validateUserNameChanges($oldUser, $newUser)) {
                    $results = $this->previewChanges($oldUser, $newUser);
                    $totalAffectedRows = sum($totalAffectedRows, $results['count']);
                    $resultsTables .= $results['resultTable'];
                    $selectSQL .= $results['selectSQL'];
                    $updateSQL .= $results['updateSQL'];
                } else {
                    echo $this->getUserNameChangeErrors($oldUser, $newUser);
                }
            } else {
                echo "There is an error around line " . $counter . ".<br>";
                $allUserNamesValid = 0;
            }
        }
        if ($allUserNamesValid) {
            echo '<p>Able to proceed</p>';
            echo $resultsTables;
            echo "<h5>Select SQL</h5><pre>" . $selectSQL . "</pre>";
            echo "<h5>Update SQL</h5><pre style='font-size: 0.75em;'>" . $updateSQL . "</pre>";
        } else {
            echo '<p style="color:red;">Input must be corrected before proceeding</p>';
        }
    }

    private function showCollations()
    {
        global $db_collation;
        global $db;
        $columnSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLLATION_NAME " .
            "FROM INFORMATION_SCHEMA.COLUMNS " .
            " WHERE `COLUMN_NAME` LIKE '%USER%'" .
            "AND `TABLE_SCHEMA` = '" . $db . "'";
        $tableSQL = "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES" .
            "AND `TABLE_SCHEMA` = '" . $db . "'";
        $columnResult = $this->query($columnSQL, []);
        $pageData = "";
        echo "<p>The db_collation is set to " . $db_collation . "</p>";
        echo "<p>Rows in bold contain a table and column that reference username.</p>";
        if ($columnResult->num_rows > 0) {
            $pageData .= "<div class='alert alert-success'>Column Collations</div>";
            $resultTable = '<table  class="table table-striped table-bordered table-hover"><tr>' .
                '<th>Schema</th><th>Table</th><th>Column</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($columnResult)) {
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (lower($update['table']) === lower($collation['TABLE_NAME']) &&
                        lower($update['column']) === lower($collation['COLUMN_NAME'])) {
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
            $pageData .= '<p>There are no results for column collations.  This result is strange and should never occur';
        }
        $tableResult = $this->query($columnSQL, []);

        if ($tableResult->num_rows > 0) {
            $pageData .= "<div class='alert alert-success'>Table Collations</div>";
            $resultTable = '<table  class="table table-striped table-bordered table-hover"><tr>' .
                '<th>TABLE_SCHEMA</th><th>Table</th><th>Collation</th></tr>';
            while ($collation = mysqli_fetch_array($tableResult)) {
                $resultTable .= '<tr';
                foreach ($this->tablesAndColumns as $update) {
                    if (lower($update['table']) === lower($collation['TABLE_NAME'])) {
                        $resultTable .= ' style="font-weight:bold;"';
                        break;
                    }
                }
                $resultTable .= '>' .
                    '<td>' . $collation['TABLE_SCHEMA'] . '</td>' .
                    '<td>' . $collation['TABLE_NAME'] . '</td>' .
                    '<td>' . $collation['COLLATION_NAME'] . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for table collations.  This result is strange and should never occur';
        }
        echo $pageData;
    }

    private function changeUser(): void
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if (!$this->validateUserName($oldUser)) {
            echo("The old user name must be at least two characters in length.<br>");
            return;
        }
        if (!$this->validateUserName($newUser)) {
            echo("The new user name must be at least two characters in length.<br>");
            return;
        }

        if (!$this->findUser($oldUser)) {
            echo "The user, $oldUser, was not found<br>";
        } else if ($this->findUser($newUser)) {
            echo "That user name, $newUser, is already taken<br>";
        } else {
            $banner = '<div class="alert alert-secondary"><h4>Changed User</h4>' .
                '<p>Old: ' . $oldUser . '</p>' .
                '<p>New: ' . $newUser . '</p>' .
                '</div>';
            echo $banner;
            $this->changeUser2($oldUser, $newUser);
        }

    }

    #[Pure] private function sanitize($data)
    {
        $data = lower(trim(stripslashes($data)));
        return filter_var($data, FILTER_SANITIZE_STRING);
    }

    private function makeGetPage(): void
    {
//        echo $this->makeUsersDisplay();
        echo $this->makeForm();
        echo $this->makeUploadForm();

    }

    private function makeNavBar(): string
    {
        return "<div>" . $this->makeReloadLink() .
            $this->makeAuthMethodLink() .
            $this->makeCollationLink() .
            "</div>" .
            $this->makeDisclaimer() .
            $this->makeSunflower();
    }

    private function makeSunflower()
    {
        return '<div id="position" class="sunflower">
	<div class="head">
	    <div id="eye-1" class="eye"></div>
	    <div id="eye-2" class="eye"></div>
	    <div class="mouth"></div>
	</div>
	<div class="petals"></div>
	<div class="trunk">
		<div class="left-branch"></div>
		<div class="right-branch"></div>
	</div>
	<div class="vase"></div>
</div>' .
            "<style>#position{
	position:fixed;
	bottom:180px;
	left:25px;
}
.sunflower{
  position:relative;
  height:30px;
  width:30px;
}
.head {
  animation: hmove 2s infinite linear;
  height: 50px;
  width: 62px;
  position: relative;
  left:8px;
  top:39px;
  transform-origin: 50% -7px;
  user-select: none;
}
.head .eye {
  background: #43699a;
  border-radius: 10px;
  height: 5px;
  position: absolute;
  top: 30px;
  width: 5px;
}
.head .eye#eye-1 {
  left: 17px;
   animation: eye 4s linear infinite normal 0.5s;
}
.head .eye#eye-2 {
  right: 17px;
  animation: eye 4s linear infinite normal 0.5s;
}
.head .mouth {
  background: #ecf0f1;
  border-radius: 30px;
  bottom: 2px;
  clip: rect(8px, 15px, 16px, 0);
  height: 16px;
  margin-left: -7.5px;
  position: absolute;
  left: 50%;
  width: 15px;
}

.petals {
  z-index:-1;
  border-radius:100%;
  display:inline-block;
  background-color:#faaa18;
  height:50px;
  width:50px;
  position:absolute;
  animation:petals 2s infinite linear;
  box-shadow:15px 17px #ffe000,
    -15px 17px #ffe000,
    -22px -7px #ffe000,
    0px -22px #ffe000,
    22px -7px #ffe000;
}
.trunk{
	height: 65px; width: 5px;
	background:#77b039;
	left: 37px;top:100px;
	position:absolute;
	z-index:-2;
	animation:trunk 2s infinite linear;
}
.left-branch{
	background: #77b039;
	height: 35px; width: 9px;
	position: absolute; left: -12px; top: 24px;
	border-radius:100% 0% 0% 0%;
	-webkit-border-radius:100% 0% 0% 0%;
	-moz-border-radius:100% 0% 0% 0%;
	-ms-border-radius:100% 0% 0% 0%;
	-o-border-radius:100% 0% 0% 0%;
	
	transform: rotate(-50deg);
	-webkit-transform: rotate(-50deg);
	-moz-transform: rotate(-50deg);
	-ms-transform: rotate(-50deg);
	-o-transform: rotate(-50deg);
}

.right-branch{
	background: #77b039;
	height: 35px; width: 9px;
	position: absolute; top: 24px; left: 10px;
	border-radius:0% 100% 0% 0%;
	-webkit-border-radius:0% 100% 0% 0%;
	-moz-border-radius:0% 100% 0% 0%;
	-ms-border-radius:0% 100% 0% 0%;
	-o-border-radius:0% 100% 0% 0%;
	
	transform: rotate(50deg);
	-webkit-transform: rotate(50deg);
	-moz-transform: rotate(50deg);
	-ms-transform: rotate(50deg);
	-o-transform: rotate(50deg);
}
.vase{
position:absolute;
   top:165px;
   left:13px;
   height: 0; 
   width: 53px;
   border-top: 45px solid #faaa18;
   border-left: 8px solid transparent;
   border-right: 8px solid transparent;
}
.vase:before,.vase:after {
	content: '';
	position: absolute;
	background: #faa118;
}
.vase:before{
	background: #f9a018;
	width: 58px; height: 20px;
	top: -50px; left: -10px;
	position:absolute;
	box-shadow: 0 5px 10px -9px black;
	-moz-transform: 0 5px 10px -9px black;
	-ms-transform: 0 5px 10px -9px black;
	-o-transform: 0 5px 10px -9px black;
}

@keyframes petals {
	0% {transform: rotate(0);left:10px;}
	25% {left:20px;}
	50% {left:10px;}
	75% {left:20px;}
	100% {transform: rotate(360deg);left:10px;}
}
@keyframes hmove {
	0% {left:5px;}
	25% {left:15px;}
	50% {left:5px;}
	75% {left:15px;}
	100% {left:5px;}
}
@keyframes eye{
	from { }
    79% {height:5px;}
	80% {height:0px;}
	85%{height:5px;}
	to {height:5px;}
}
@keyframes trunk {
	0% {left:34px;transform:rotate(-5deg);}
	25% {left:40px;transform:rotate(5deg);}
	50% {left:34px;transform:rotate(-5deg);}
	75% {left:40px;transform:rotate(5deg);}
	100% {left:34px;transform:rotate(-5deg);}
}</style>";
    }


    private function makeDisclaimer()
    {
        return "<p>Please be very careful when using this external module</p>";
    }

    private function makeUsersDisplay(): string
    {
        $usersDisplay = "<div class='alert alert-success'>The following usernames are available to change.</div>" .
            "<ul>";
        foreach ($this->users as $user) {
            $usersDisplay .= '<li>' . $user['username'] . '</li>';
        }
        $usersDisplay .= "</ul>";
        return $usersDisplay;
    }

    private function makeUploadForm(): string
    {
        $form = '<div style="margin:20px; border: 2px solid pink; border-radius: 5px; padding:25px;">' .
            '<h5>Bulk User Change</h5><p>Process multiple users.  Each row represents one user.' .
            ' Each row should be in the format <br>old_user_name,new_user_name</p>' .
            '<form  action="' . $this->pageUrl . '" method="post" enctype="multipart/form-data">' .
            '<div class="form-group">' .
            '<label for="csvUserNames">Paste in the csv data below</label>' .
            '<textarea name="csvUserNames" id="csvUserNames" class="form-control" rows="5"></textarea>' .
            '</div>' .
            '<button class="btn btn-success" type="submit" name="form_action" value="bulk">Preview</button>' .
            '</form></div>';
        return $form;
    }

    private function makeForm(): string
    {
        $oldUserName = "";
        $newUserName = "";
        if (isset($_REQUEST['old_name'])) {
            $oldUserName = $this->sanitize($_REQUEST['old_name']);
        }
        if (isset($_REQUEST['new_name'])) {
            $newUserName = $this->sanitize($_REQUEST['new_name']);
        }
        $state = 'load';
        if (isset($_REQUEST['form_action'])) {
            if ($_REQUEST['form_action'] === 'change_user') {
                $state = 'change_user';
            } else {
                $state = 'preview';
            }
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
            '<div class="form-group">';
        if ($state === 'load') {
            $form .= '<button class="btn btn-success" style="margin-right: 30px;" type="submit" name="form_action" value="preview">Preview' . '</button>';
        }
        $form .= '<button class="btn btn-warning" type="submit" name="form_action" value="change_user">Change User' . '</button>' .
            '</div>' .
            '</form></div>';
        return $form;
    }

    private function makeReloadLink(): string
    {
        $parameters = "";
        if (isset($_REQUEST['old_name'])) {
            $parameters .= '&old_name=' . $this->sanitize($_REQUEST['old_name']);
        }
        if (isset($_REQUEST['new_name'])) {
            $parameters .= '&new_name=' . $this->sanitize($_REQUEST['new_name']);
        }
        $url = $this->pageUrl;
        if (length($parameters) > 0) {
            $url .= $parameters;
        }
        return '<a class="btn btn-primary" style="margin:15px;" href="' .
            $url . '">Change User Home Page</a>';
    }

    private function makeAuthMethodLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=auth_methods">Show Auth Methods</a>';
    }

    private function makeCollationLink(): string
    {
        return '<a class="btn btn-primary"  style="margin:15px;" href="' .
            $this->pageUrl . '&action=collation">Show DB Collations</a>';
    }

    private function getAuthenticationMethodSummary()
    {
        return $this->query('SELECT auth_meth, count(*) as count FROM redcap_projects group by auth_meth;', []);
    }

    private function getAuthenticationMethodDetails()
    {
        return $this->query('SELECT project_id, project_name, auth_meth FROM redcap_projects;', []);
    }

    private function makeAuthenticationMethodsPage(): string
    {
        $authMethods = $this->getAuthenticationMethodSummary();
        $pageData = '';
        if ($authMethods->num_rows > 0) {
            $pageData .= "<div class='alert alert-success'>Authentication Methods Summary</div>";
            $resultTable = '<table  class="table table-striped table-bordered table-hover"><tr><th>Auth Methods</th><th>Count</th></tr>';
            while ($method = mysqli_fetch_array($authMethods)) {
                $resultTable .= '<tr><td>' . $method['auth_meth'] . '</td>' .
                    '<td>' . $method['count'] . '</td></tr>';
            }

            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for auth methods.  This result is strange and should never occur';
        }
        $projects = $this->getAuthenticationMethodDetails();
        $pageData .= "<div class='alert alert-success'>Details</div>";
        if ($projects->num_rows > 0) {
            $resultTable = '<table  class="table table-striped table-hover table-bordered"><tr>' .
                '<th>Project ID</th><th>Name</th><th>Auth Method</th></tr>';
            foreach ($projects as $project) {
                $resultTable .= '<tr><th>' . $project['project_id'] . '</th>' .
                    '<th>' . $project['project_name'] . '</th>' .
                    '<th>' . $project['auth_meth'] . '</th>' .
                    '</tr>';
            }
            $pageData .= $resultTable . '</table>';
        } else {
            $pageData .= '<p>There are no results for projects.  This result is strange and should never occur';
        }
        return $pageData;
    }

    private function setAction(): void
    {
        $this->action = 'page_load';
        if ($_REQUEST['action'] === 'auth_methods') {
            $this->action = 'auth_methods';
        } else if ($_REQUEST['action'] === 'collation') {
            $this->action = 'collation';
        } else if ($_SERVER["REQUEST_METHOD"] === "GET") {
            $this->action = 'page_load';
        } else if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->action = $this->sanitize($_REQUEST['form_action']);
        }
    }

    private function changeUser2($oldUser, $newUser): void
    {
        global $db_collation;
        echo "<div class='alert alert-success'>The following tables were updated</div>";
        $sql = '';
        $resultTable = "<table class='table table-striped'>" .
            "<tr><th>Table</th><th>Column</th><th>Count</th><th>Error #</th></tr>";
        foreach ($this->tablesAndColumns as $update) {
            $sql_embedded_parameters = 'UPDATE ' . $update['table'] .
                ' SET `' . $update['column'] . '` = ' .
                '"' . $newUser . '"' .
                ' WHERE `' . $update['column'] . '` = ' .
                '"' . $oldUser . '"' .
                ' COLLATE ' . $db_collation . ';';
            $sql .= $sql_embedded_parameters . "<br>";
            $result = $this->query($sql_embedded_parameters, []);
            $resultTable .= '<tr><th>' . $update['table'] . '</th>' .
                '<th>' . $update['column'] . '</th>' .
                '<th>' . db_affected_rows() . '</th>' .
                '<th>' . $result->error . '</th>' .
                '</tr>';

        }
        $resultTable .= "</table>";
        echo $resultTable;
        echo "<div class='alert alert-success'>Using the UPDATE following SQL:</div><pre>" . $sql . '</pre>';

        $logEvent = 'Changed User Name.  Old: ' . $oldUser . ' New: ' . $newUser;
        Logging::logEvent("", "redcap_auth", $logEvent, "Record", "display", $logEvent);
        $logId = $this->log(
            "Username Changed",
            [
                "old User" => $newUser,
                "New User" => $oldUser
            ]
        );
    }

    private function getUserNameChangeErrors($oldUser, $newUser): string
    {
        $errorMessage = "";
        if (!$this->validateUserName($oldUser)) {
            $errorMessage = "The old user name is not valid.<br>";
        }
        if (!$this->validateUserName($newUser)) {
            $errorMessage .= "The new user name is not valid.<br>";
        }

        if (!$this->findUser($oldUser)) {
            $errorMessage .= "The user, $oldUser, was not found<br>";
        } else if ($this->findUser($newUser)) {
            $errorMessage = $newUser . " is already in use.  An old username can not be changed to an existing username.<br>";
        }
        return $errorMessage;
    }

    private function previewChanges($oldUser, $newUser): array
    {
// todo get this in a method and call from here as well as change user.
        $allSelectSQL = '';
        $allUpdateSQL = '';
        $resultTable = "<table class='table table-striped'>" .
            "<tr><th>Table</th><th>Column</th><th>Count</th></tr>";
        $rowCountTotal = 0;
        foreach ($this->tablesAndColumns as $update) {
            $sql_embedded_parameters = 'SELECT ' . $update['column'] .
                ' FROM ' . $update['table'] .
                ' WHERE `' . $update['column'] . '` = ' .
                '"' . $oldUser . '"';
            $allSelectSQL .= $sql_embedded_parameters . "<br>";
            $allUpdateSQL .= 'UPDATE ' . $update['table'] .
                ' SET `' . $update['column'] . '` = ' .
                '"' . $newUser . '"' .
                ' WHERE `' . $update['column'] . '` = ' .
                '"' . $oldUser . '"' .
                ' COLLATE utf8mb4_unicode_ci;' .
                '<br>';

            $result = $this->query($sql_embedded_parameters, []);
            // todo check the $result to make sure there was not an error.

            $resultTable .= '<tr><th>' . $update['table'] . '</th>' .
                '<th>' . $update['column'] . '</th>' .
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
        $results = "<div class='alert alert-success'>" . $oldUser .
            " was found $rowCountTotal times in the following tables:</div>" .
            $resultTable .
            "<div class='alert alert-success'>Using the following SELECT SQL:</div><pre>" . $allSelectSQL . '</pre>' .
            "<div class='alert alert-success'>" .
            "This is the UPDATE SQL:</div><pre style='font-size: .75em;'>" . $allUpdateSQL . '</pre>';

        return $resultArray;
    }

    private function createFinalSingleUserChangeForm($oldUser, $newUser)
    {
        $form = '<div class="card p-3"><form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">' .
            '<label for="old_name">Old Username: ' . $oldUser . '</label>' .
            '<input name="old_name" id="old_name" class="form-control" value="' . $oldUser . '" readonly hidden>' .
            '</div>' .
            '<div class="form-group">' .
            '<label for="new_name">New Username: ' . $newUser . '</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control" readonly hidden value="' . $newUser . '">' .
            '<br>' .
            '</div>' .
            '<div class="form-group">' .
            '<button class="btn btn-warning" type="submit" name="form_action" value="change_user">Change User' . '</button>' .
            '</div>' .
            '</form>';
        return $form;
    }

    private function validateUserName($username): bool
    {
        if (length($username) < 2) {
            return false;
        }
        return true;
    }

    private function findUser(mixed $oldUser): bool
    {
        foreach ($this->users as $user) {
            if (lower($user['username']) === lower($oldUser)) {
                return true;
            }
        }
        return false;
    }

    private function validateUserNameChanges($oldUser, $newUser): bool
    {
        if (!$this->validateUserName($oldUser)) {
            return false;
        } else if (!$this->validateUserName($newUser)) {
            return false;
        } else if (!$this->findUser($oldUser)) {
            return false;
        } else if ($this->findUser($newUser)) {
            return false;
        }
        return true;
    }

}
