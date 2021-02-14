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
  <title>Date d'adhÃ©sion</title>
  <meta charset="UTF-8">
 <!-- Load React. -->
 <!-- Note: when deploying, replace "development.js" with "production.min.js". -->
 <script src="https://unpkg.com/react@17/umd/react.development.js" crossorigin></script>
 <script src="https://unpkg.com/react-dom@17/umd/react-dom.development.js" crossorigin></script>
 <script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>
</head>
<body>
 <div id="ma_table"></div>

 <script>
window.ma_items = '<?php echo $json; ?>';
window.self_page = '<?php echo $_SERVER["PHP_SELF"]; ?>';
 </script>

 <!-- Load our React component. -->
 <script type="text/babel" src="ma_table.js"></script>
</body>
</html>
