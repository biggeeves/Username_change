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
            ['table' => 'redcap_user_information', 'column' => 'user_sponsor']
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
            $this->previewChanges();
        } else if ($this->action === 'change_user') {
            $this->changeUser();
        } else {
            echo "Sorry, that is not an available action.";
        }
    }

    private function changeUser(): void
    {
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if (!$this->validateUserName($oldUser)) {
            echo ("The old user name must be at least two characters in length.<br>");
            return;
        }
        if (!$this->validateUserName($newUser)) {
            echo ("The new user name must be at least two characters in length.<br>");
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
        echo $this->makeUsersDisplay();
        echo $this->makeForm();

    }

    private function makeNavBar(): string
    {
        return "<div>" . $this->makeReloadLink() . $this->makeAuthMethodLink() . "</div>";
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

    private function makeForm(): string
    {
        $oldUserName = "";
        $newUserName = "";
        if(isset($_REQUEST['old_name'])) {
            $oldUserName = $this->sanitize($_REQUEST['old_name']);
        }
        if(isset($_REQUEST['new_name'])) {
            $newUserName = $this->sanitize($_REQUEST['new_name']);
        }
        $form = '<div class="card p-3"><form action="' . $this->pageUrl . '" method = "POST">' .
            '<div class="form-group">'.
            '<label for="old_name">Old Username:</label>' .
            '<input type="text" id="old_name" name="old_name" class="form-control" value="'.$oldUserName.'">' . '<br>' .
            '</div>'.
            '<div class="form-group">'.
            '<label for="new_name">New Username:</label>' .
            '<input type="text" id="new_name" name="new_name" class="form-control"  value="'.$newUserName.'">' . '<br>' .
            '</div>'.
            '<div class="form-group">'.
            '<button class="btn btn-success" style="margin-right: 30px;" type="submit" name="form_action" value="preview">Preview' . '</button>' .
            '<button class="btn btn-warning" type="submit" name="form_action" value="change_user">Change User' . '</button>' .
            '</div>'.
            '</form></div>';
        return $form;
    }

    private function makeReloadLink(): string
    {
        $parameters = "";
        if(isset($_REQUEST['old_name'])) {
            $parameters .= '&old_name=' . $this->sanitize($_REQUEST['old_name']);
        }
        if(isset($_REQUEST['new_name'])) {
            $parameters .= '&new_name='  . $this->sanitize($_REQUEST['new_name']);
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

    private function getAuthenticationMethodSummary() {
        return $this->query('SELECT auth_meth, count(*) as count FROM redcap_projects group by auth_meth;', []);
    }

    private function getAuthenticationMethodDetails() {
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
            $resultTable = '<table  class="table table-striped table-hover table-bordered"><tr>'.
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
        } else if ($_SERVER["REQUEST_METHOD"] === "GET") {
            $this->action = 'page_load';
        } else if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->action = $this->sanitize($_REQUEST['form_action']);
        }
    }

    private function changeUser2($oldUser, $newUser): void
    {
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
                ' COLLATE utf8mb4_unicode_ci;';
            $sql .= $sql_embedded_parameters . "<br>";
            $result = $this->query($sql_embedded_parameters, []);
            $resultTable .= '<tr><th>' . $update['table'] . '</th>' .
                '<th>' . $update['column'] . '</th>' .
                '<th>' . db_affected_rows() . '</th>' .
                '<th>' . $result->error. '</th>' .
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
        echo "here";
        print_r($logId);
        echo "there";
    }

    private function previewChanges(): void
    {
        // todo get this in a method and call from here as well as change user.
        $oldUser = $this->sanitize($_REQUEST['old_name']);
        $newUser = $this->sanitize($_REQUEST['new_name']);
        if (!$this->validateUserName($oldUser)) {
            echo ("The old user name is not valid.<br>");
        }
        if (!$this->validateUserName($newUser)) {
            echo ("The new user name is not valid.<br>");
        }
        if($this->findUser($newUser)) {
            echo ($newUser . " is already in use.  An old username can not be changed to an existing username");
            return;
        }


        $allSql = '';
        $resultTable = "<table class='table table-striped'>" .
            "<tr><th>Table</th><th>Column</th><th>Count</th></tr>";
        $rowCountTotal = 0;
        foreach ($this->tablesAndColumns as $update) {
            $sql_embedded_parameters = 'SELECT ' . $update['column'] .
                ' FROM ' . $update['table'] .
                ' WHERE `' . $update['column'] . '` = ' .
                '"' . $oldUser . '"';
            $allSql .= $sql_embedded_parameters . "<br>";
            $result = $this->query($sql_embedded_parameters, []);
            // todo check the $result to make sure there was not an error.

            $resultTable .= '<tr><th>' . $update['table'] . '</th>' .
                '<th>' . $update['column'] . '</th>' .
                '<th>' . db_affected_rows() .'</th>' .
                '</tr>';
            $rowCountTotal += db_affected_rows();

        }
        $resultTable .= "</table>";
        echo "<div class='alert alert-success'>" . $oldUser . " was found $rowCountTotal times in the following tables:</div>";
        echo $resultTable;
        echo "<div class='alert alert-success'>Using the following SELECT SQL:</div><pre>" . $allSql . '</pre>';
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

}
