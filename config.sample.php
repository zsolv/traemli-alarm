<?PHP

define ("DB_HOST", "<host>"); // set database host
define ("DB_USER", "<user>"); // set database user
define ("DB_PASS", "<pass>"); // set database password
define ("DB_NAME", "<name>"); // set database name

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME) or die("Couldn't connect to DB");
mysqli_set_charset($db, "utf8");
?>