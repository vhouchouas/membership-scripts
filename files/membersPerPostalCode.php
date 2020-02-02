<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'mysql.php');
require_once(ZWP_TOOLS . 'registrationDateUtil.php');

$unused = array();
$dateUtil = new RegistrationDateUtil(new DateTime());
$mysql = new MysqlConnector();

$postalPerMembers = $mysql->countMembersPerPostal($dateUtil->getDateAfterWhichMembershipIsConsideredValid());

?>
<html>
<head>
  <title>Membres par code postal</title>
  <meta charset="UTF-8">
</head>
<body>
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
