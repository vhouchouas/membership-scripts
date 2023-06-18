<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'lib/doctrine/DoctrineConnector.php');

$loggerInstance = new NoopLogger(); // to avoid having technical log sent to the browser

// Find out the value of $since
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

// Find out the value of $keepTests
$keepTests = isset($_GET["keepTests"]);


// Retrieve the data
$doctrineConnector = new DoctrineConnector();
$members = $doctrineConnector->getOrderedListOfLastRegistrations($since);
if(!$keepTests){
  $members = keepOnlyActualMembers($members);
}

if(isset($_GET["json"])) {
  echo json_encode(array_values($members));
  die();
}
?>
<html>
<head>
  <title>Date d'adhésion</title>
  <meta charset="UTF-8">
</head>
<body>
  <form action="<?php echo $_SERVER["PHP_SELF"];?>" method="get" >
    Remonter jusqu'à <input name="since" type="date" value="<?php echo $since->format("Y-m-d"); ?>" /><br />
    Afficher les inscriptions de test <input type="checkbox" name="keepTests" <?php echo ($keepTests ? "checked" : ""); ?> /></br>
    <input type="submit" value="Rafraichir" />
  </form>

  <table>
    <tr><th>Dernière date d'adhésion</th><th>Nom</th><th>Mail</th><th>Code Postal</th></tr>
<?php
  foreach($members as $member){
    echo "<tr><td>" . dateToStr($member->lastRegistrationDate) . "</td><td>" . $member->firstName . " " . $member->lastName . "</td><td>" . $member->email . '</td><td>' . $member->postalCode . '</td></tr>';
  }
?>
  </table>
</body>
</html>
