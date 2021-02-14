<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');
require_once(ZWP_TOOLS . 'lib/util.php');

$dateUtil = new RegistrationDateUtil(new DateTime());
$mysql = new MysqlConnector();

// Get the data
$simplifiedRegistrationEvents = keepOnlyActualRegistrations($mysql->getOrderedListOfLastRegistrations($dateUtil->getDateAfterWhichMembershipIsConsideredValid()));

// Aggregate
$postalPerMembers = array();
foreach($simplifiedRegistrationEvents as $sre){
  $postal = $sre->postal_code;
  if (!array_key_exists($postal, $postalPerMembers)){
    $postalPerMembers[$postal] = 0;
  }
  $postalPerMembers[$postal] += 1;
}

// Order
arsort($postalPerMembers);

?>
<html>
<head>
  <title>Membres par code postal</title>
  <meta charset="UTF-8">
</head>
<body>
<div>(Note : ces comptes ne prennent en compte que les vraies adhésions : les adhésions de test sont exclues)</div>
  <table>
    <tr><th>Nombre de membres</th><th>Code postal</th></tr>
<?php
  foreach($postalPerMembers as $postal => $count){
    echo "<tr><td>" . $count . "</td><td>" . $postal . "</td></tr>";
  }
?>
  </table>
</body>
</html>
