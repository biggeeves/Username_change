***

# Username Change

Author: Greg Neils

***

## Summary

This REDCap External Module is designed to support administrators in safely renaming usernames across the platform.
Given the complexity of REDCap’s underlying database and the critical role usernames play in access control, logging,
and project settings, this tool offers a structured and cautious approach to ensure data integrity. It provides a tabbed
interface to guide users through the process, including previews of SQL changes, validation of authentication methods,
and updates to related configurations like External Modules, dictionaries, and password entries. The module is intended
for experienced REDCap admins and requires a strong understanding of SQL and REDCap’s architecture.

### Read Me Tab

The Read Me tab provides essential warnings, prerequisites, and best practices for using the module. It emphasizes the
importance of reviewing all SQL queries before execution, validating table and column existence, ensuring new usernames
meet REDCap’s naming standards, and making full database backups before any changes. It also discusses potential issues
like collation mismatches, orphaned records, and REDCap version differences that may affect module behavior.

### Change User Tab

This tab offers both single and bulk username change options through a user-friendly interface. Users can input the old
and new usernames manually or paste a CSV-formatted list for batch processing. Options to include authentication tables
and logging details allow for more thorough tracking and updates across the system. This is the main operational hub for
executing username changes, with safeguards to preview and confirm actions before any changes are made to the database.

### DB Info Tab

The DB Info tab lists all database tables that may contain usernames, indicating whether they exist in your REDCap
instance. It helps admins understand where usernames are stored and what tables could be affected. It also highlights
the importance of character collation compatibility, which can impact whether updates work correctly. Helpful SQL
snippets are provided to explore the database schema, making this tab a valuable resource for verifying
environment-specific table configurations.

### Auth Methods Tab

This tab provides critical information and guidance on how authentication methods relate to usernames within REDCap. It
explains the potential risks of changing global authentication settings and how those changes might affect access,
especially for superusers. It includes SQL examples for updating project-level authentication settings but does not
execute these changes directly. This section is particularly useful when transitioning between authentication systems (
e.g., from Table-based to LDAP or OAuth).

### EMs Tab

The External Modules (EMs) tab identifies potential dependencies where usernames may be hardcoded into module settings.
It runs a query to detect relevant entries in the REDCap external_module_settings table, but results require human
interpretation. This helps prevent disruptions in functionality due to outdated username references within EM
configurations.

### Dictionaries Tab

This tab scans REDCap projects for smart variables (like [user-name]) or action tags (like @USERNAME) that may depend on
usernames. These are commonly used in branching logic or calculated fields. It includes a query to identify up to 1000
such instances, helping admins locate where updates to usernames might silently break project functionality. Results can
be filtered by project ID for more targeted inspection.

### Passwords Tab

The Passwords tab is designed for environments using Table-based authentication. It provides SQL scripts to nullify
passwords and related data, which is essential when transitioning to external authentication systems (e.g., OAuth). It
also supports bulk removal of username records from authentication tables. The tab emphasizes caution, as these
operations are irreversible without backups and may lock users out of the system if not handled properly.

### REDCap Pages that utilize user or username that may be involved when changing a username:

- Project DAG Switcher
- Project eSign an instrument
- Project Lock an instrument
- Project Lock an entire record
- Project Logs
- Project Dashboard with selected users.
- Project Reports with selected VIEW users
- Project Dashboard with selected EDIT users
- Project User Rights Page
- Send it with attachment
- System User Allowlist
- System Custom Application Links for Projects
- System Edit User Page
- System Edit User Page, PI Username

### Tips

Username names may already appear in the redcap_user_rights because end users can assign someone that is NOT a redcap
user to have rights in that table, but it does create an "orphaned" entry in the table. Renaming a user to the same name
will cause a duplicate and that will stop the SQL script from running.
Everyone should have a redcap_user_information table that has an entry in the redcap_user_rights.
select * from redcap_user_rights a where username not in (select username from redcap_user_information)

### Todo, check the redcap_user_rights table.

--- Add this script to the information:
select * from redcap_user_rights a where
a.username like '%@%'
and
a.username not in (select username from redcap_user_information)

delete from
redcap_user_rights a
where
a.username like '%@%'
and
a.username not in (select username from redcap_user_information)

### Another issue:

select * from redcap_data_access_groups_users a where
a.username like '%@%'
and
a.username not in (select username from redcap_user_information)

delete from
redcap_data_access_groups_users a
where
a.username like '%@%'
and
a.username not in (select username from redcap_user_information)

## Minimum REDCap Version is 12.0.8.