<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');

$until = null;
if (isset($_GET["until"])){
  try {
    $until = new DateTime($_GET["until"]);
  } catch(Exception $e){
    // Nothing to do. If we reach this point it means the parameter was badly formatted.
    // We leave `$until` to `null`, it will be handle afterwards
  }
}
if (is_null($until)){
  $registrationDateUtil = new RegistrationDateUtil(new DateTime());
  $until = $registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();
}

$debug = true; // This page isn't suppose to write anything in db, so let's pretend we're in debug mode
$mysqlConnector = new MysqlConnector($debug);
$simplifiedRegistrationEvents = $mysqlConnector->getOrderedListOfLastRegistrations($until);

?>
<html>
<head>
  <title>Date d'adhésion</title>
  <meta charset="UTF-8">
</head>
<body>
  <form action="<?php echo $_SERVER["PHP_SELF"];?>" method="get" >
    Remonter jusqu'à <input name="until" type="date" value="<?php echo $until->format("Y-m-d"); ?>" />
    <input type="submit" value="Rafraichir" />
  </form>

  <table>
    <tr><th>Dernière date d'adhésion</th><th>Nom</th><th>Mail</th><th>Code Postal</th></tr>
<?php
  foreach($simplifiedRegistrationEvents as $event){
    echo "<tr><td>" . $event->event_date . "</td><td>" . $event->first_name . " " . $event->last_name . "</td><td>" . $event->email . '</td><td>' . $event->postal_code . '</td></tr>';
  }
?>
  </table>
</body>
</html>
