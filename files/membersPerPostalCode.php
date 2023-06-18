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
require_once(ZWP_TOOLS . 'lib/doctrine/DoctrineConnector.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');
require_once(ZWP_TOOLS . 'lib/util.php');

$loggerInstance = new NoopLogger(); // to avoid having technical log sent to the browser

$dateUtil = new RegistrationDateUtil(new DateTime());
$doctrine = new DoctrineConnector();

// Get the data
$members = keepOnlyActualMembers($doctrine->getOrderedListOfLastRegistrations($dateUtil->getDateAfterWhichMembershipIsConsideredValid()));

// Aggregate
$postalPerMembers = array();
foreach($members as $member){
  $postal = $member->postalCode;
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
