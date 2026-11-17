# My PHP / Node Starter App

This starter project is kept separate from the Laravel AgriConnect application.

The Dockerfile runs the Express API on port 5000. The PHP pages require a PHP
web server with the PDO MySQL extension. Configure `DB_HOST`, `DB_NAME`,
`DB_USER`, and `DB_PASSWORD` in the PHP runtime environment before using
`db-test.php`; do not commit real credentials.
