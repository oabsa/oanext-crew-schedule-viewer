# oanext-crew-schedule-viewer
WordPress plugin for displaying crew schedules for NEXT conference.  Fetches schedule data from a Google Spreadsheet.

## .htaccess
This plugin requires the addition of the following lines to the `.htaccess` file:

```
Options +FollowSymLinks
RewriteRule ^crew\/([0-9]+)\/?$ crew?crew_id=$1 [R=301,L]
```
