<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'mysql.php');

$nbDays = isset($_GET["nbDays"]) ? (int) $_GET["nbDays"] : 366;
$nbDays = ($nbDays >= 1) ? $nbDays : 366;
$until = new DateTime(date("Y-m-d\T00:00:00", time() - $nbDays*24*3600));
$mysqlConnector = new MysqlConnector();
$simplifiedRegistrationEvents = $mysqlConnector->getOrderedListOfLastRegistrations($until);

?>
<html>
<head>
  <title>Date d'adhésion</title>
  <meta charset="UTF-8">
</head>
<body>
  <form action="<?php echo $_SERVER["PHP_SELF"];?>" method="get" >
    Remonter jusqu'à <input type="number" step="1" name="nbDays"  value="<?php echo $nbDays; ?>" /> jours.
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
