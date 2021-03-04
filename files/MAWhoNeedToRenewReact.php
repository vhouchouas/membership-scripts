<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');

$since = null;
if (isset($_GET["since"])){
  try {
    $since = new DateTime($_GET["since"]);
  } catch(Exception $e){
    // Nothing to do. If we reach this point it means the parameter was badly formatted.
    // We leave `$since` to `null`, it will be handle afterwards
  }
}
if (is_null($since)){
  $registrationDateUtil = new RegistrationDateUtil(new DateTime());
  $since = $registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();
}

$mysqlConnector = new MysqlConnector();
$simplifiedRegistrationEvents = $mysqlConnector->getOrderedListOfLastRegistrations($since);
$json = json_encode($simplifiedRegistrationEvents);
?>
<html>
<head>
  <title>Date d'adhésion</title>
  <meta charset="UTF-8">
</head>
<body>
 <div id="ma_table"></div>

 <script>
window.ma_items = '<?php echo $json; ?>';
 </script>

<div id="root"></div>

 <!-- Load our React component. -->
 <script src="bundle.js"></script>
</body>
</html>
