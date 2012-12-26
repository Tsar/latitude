<?php

$db_server = 'localhost';
$db_user   = 'latitude_palevo';
$db_passwd = 'uQVyav38Wz9nmysz';
$db_name   = 'latitude_palevo';

$m = new mysqli($db_server, $db_user, $db_passwd, $db_name);

function makePathsFromQueryResult($result) {
    $paths = array();
    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $lat = $row['coord1'] / 1E6;
        $lon = $row['coord2'] / 1E6;
        if (array_key_exists($userId, $paths)) {
            $paths[$userId] .= ",$lat,$lon";
        } else {
            $paths[$userId] = "$lat,$lon";
        }
    }
    return $paths;
}

if (isset($_GET['get_paths']) && ($_GET['get_paths'] === "1") && isset($_GET['start_date']) && isset($_GET['end_date']) && isset($_GET['user_ids_string'])) {

    $startDate = DateTime::createFromFormat('Y-m-d|', $_GET['start_date'])->getTimestamp();
    $endDate   = DateTime::createFromFormat('Y-m-d|', $_GET['end_date'])->getTimestamp() + 86400;

    $result = $m->query('SELECT user_id, coord1, coord2 FROM pos_history WHERE valid = 1 AND timestamp >= ' . ($startDate * 1000) . ' AND timestamp < ' . ($endDate * 1000) . ' ORDER BY id');
    $paths = makePathsFromQueryResult($result);

    $userIds = explode('_', $_GET['user_ids_string']);
    $resPaths = array();
    foreach ($userIds as $userId) {
        if (array_key_exists($userId, $paths)) {
            $resPaths[$userId] = $paths[$userId];
        } else {
            $resPaths[$userId] = '';
        }
    }
    echo implode('#', $resPaths);

} else {

?>
<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <style type="text/css">
      html { height: 100% }
      body { height: 100%; margin: 0; padding: 0 }
      #map_canvas { height: 100% }
    </style>
    <link rel="stylesheet" type="text/css" href="epoch_styles.css" />
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>
    <script type="text/javascript" src="xmlhttp.js"></script>
    <script type="text/javascript" src="epoch_classes.js"></script>
<?php

    $result = $m->query('SELECT u.user_id, u.fullname, u.last_update_time, u.profile_image, u.googleplus, p.coord1, p.coord2 FROM users AS u, pos_history AS p WHERE u.user_id = p.user_id AND u.last_update_time = p.timestamp');
    $fullnames = array();
    $lastUpdateTime = array();
    $profileImages = array();
    $googleplus = array();
    $currentPositions = array();
    while ($row = $result->fetch_assoc()) {
        $fullnames[$row['user_id']] = $row['fullname'];
        $lastUpdateTime[$row['user_id']] = $row['last_update_time'];
        $profileImages[$row['user_id']] = $row['profile_image'];
        $googleplus[$row['user_id']] = $row['googleplus'];
        $currentPositions[$row['user_id']] = 'new google.maps.LatLng(' . ($row['coord1'] / 1E6) . ', ' . ($row['coord2'] / 1E6) . ')';
    }

    $userIds = array_keys($lastUpdateTime);
    $userIdsString = implode('_', $userIds);

?>
    <script type="text/javascript">
      var XMLHttp = getXMLHttp();

<?php
    foreach ($userIds as $userId) {
        echo "      var path$userId = [];\n";
        echo "      var polyline$userId;\n";
    }
?>

      function initialize() {
          var mapOptions = {
              center: new google.maps.LatLng(59.940568, 30.121078),
              zoom: 11,
              mapTypeId: google.maps.MapTypeId.ROADMAP
          };
          var map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);

          var markerImageShadow = new google.maps.MarkerImage('my_friend_placard.png',
                                                              new google.maps.Size(64, 78),
                                                              new google.maps.Point(0, 0),
                                                              new google.maps.Point(32, 72));
          var infoWindow = new google.maps.InfoWindow({content: 'empty'});
          var infoWindowFixed = false;
<?php

    $colors = array("#FF0000", "#009900", "#0000FF", "#FF00FF", "#000000");
    $colorsNum = count($colors);

    foreach ($userIds as $userId) {
        echo "        polyline$userId = new google.maps.Polyline({\n";
        echo "            map: map,\n";
        echo "            strokeColor: '" . $colors[($userId - 1) % $colorsNum] . "',\n";
        echo "            strokeWeight: 2,\n";
        echo "            strokeOpacity: 0.8,\n";
        echo "            path: path$userId,\n";
        echo "            geodesic: true\n";
        echo "        });\n";

        if (!is_null($profileImages[$userId])) {
            echo "        var markerImage$userId = new google.maps.MarkerImage(\n";
            echo "            '" . $profileImages[$userId] . "',\n";
            echo "            new google.maps.Size(50, 50),\n";
            echo "            new google.maps.Point(0, 0),\n";
            echo "            new google.maps.Point(25, 67),\n";
            echo "            new google.maps.Size(50, 50)\n";
            echo "        );\n";
        }
        echo "        var marker$userId = new google.maps.Marker({\n";
        echo "            map: map,\n";
        echo "            title: '" . $fullnames[$userId] . "',\n";
        echo "            position: " . $currentPositions[$userId] . ",\n";
        if (!is_null($profileImages[$userId])) {
            echo "            shadow: markerImageShadow,\n";
            echo "            icon: markerImage$userId\n";
        } else {
            echo "            icon: markerImageShadow\n";
        }
        echo "        });\n";

        if (!is_null($profileImages[$userId])) {
            $infoWindowContent = '><tr><td><b><a href="' . $googleplus[$userId] . '" target=_blank>' . $fullnames[$userId] . '</a></b></td>' .
                                      '<td rowspan="2"><a href="' . $googleplus[$userId] . '" target=_blank><img src="' . $profileImages[$userId] . '" alt="' . $fullnames[$userId] . '" /></a></td></tr>' .
                                  '<tr><td><i>Последнее обновление:</i><br/>' . date('d.m.Y H:i:s', $lastUpdateTime[$userId] / 1000) . '</td></tr></table>';
        } else {
            $infoWindowContent = '><tr><td><b><a href="' . $googleplus[$userId] . '" target=_blank>' . $fullnames[$userId] . '</a></b></td></tr>' .
                                  '<tr><td><i>Последнее обновление:</i><br/>' . date('d.m.Y H:i:s', $lastUpdateTime[$userId] / 1000) . '</td></tr></table>';
        }

        echo "    google.maps.event.addListener(marker$userId, 'mouseover', function(event) {\n";
        echo "        if (!infoWindowFixed) {\n";
        echo "            infoWindow.setContent('<table$infoWindowContent');\n";
        echo "            infoWindow.open(map, this);\n";
        echo "        }\n";
        echo "    });\n";
        echo "    google.maps.event.addListener(marker$userId, 'mouseout', function(event) {\n";
        echo "        if (!infoWindowFixed)\n";
        echo "            infoWindow.close();\n";
        echo "    });\n";
        echo "    google.maps.event.addListener(marker$userId, 'click', function(event) {\n";
        echo "        infoWindowFixed = !infoWindowFixed;\n";
        echo "        if (infoWindowFixed) {\n";
        echo "            infoWindow.setContent('<table bgcolor=\"#CCFFCC\"$infoWindowContent');\n";
        echo "        } else {\n";
        echo "            infoWindow.setContent('<table$infoWindowContent');\n";
        echo "        }\n";
        echo "    });\n";
    }

?>
          singleDate = new Epoch('calSD', 'flat', document.getElementById('singleDate'), false);
          singleDate.clicked = function() {
              document.getElementById('applyStatus').innerHTML = '<i>Обновление...</i>';
              var dt = singleDate.selectedDates[0];
              var dtStr = dt.getFullYear() + "-" + (dt.getMonth() + 1) + "-" + dt.getDate();
              XMLHttp.open("GET", "?get_paths=1&user_ids_string=<?php echo $userIdsString; ?>&start_date=" + dtStr + "&end_date=" + dtStr);
              XMLHttp.onreadystatechange = handlePaths;
              XMLHttp.send(null);
          };
          applyDateRange();
      }

      function applyDateRange() {
          document.getElementById('applyStatus').innerHTML = '<i>Обновление...</i>';
          XMLHttp.open("GET", "?get_paths=1&user_ids_string=<?php echo $userIdsString; ?>&start_date=" + document.getElementById('startDate').value + "&end_date=" + document.getElementById('endDate').value);
          XMLHttp.onreadystatechange = handlePaths;
          XMLHttp.send(null);
      }

      function handlePaths() {
        if (XMLHttp.readyState == 4) {
            var paths = XMLHttp.responseText.split("#");
<?php
    $ii = 0;
    foreach ($userIds as $userId) {
        echo "            if (paths[$ii] == \"\") {\n";
        echo "                polyline$userId.setPath([]);\n";
        echo "            } else {\n";
        echo "                var pathCoords$userId = paths[$ii].split(\",\");\n";
        echo "                path$userId = [];\n";
        echo "                for (var i = 0, i_end = pathCoords$userId.length / 2; i < i_end; ++i) {\n";
        echo "                    path$userId.push(new google.maps.LatLng(parseFloat(pathCoords$userId" . "[i * 2]), parseFloat(pathCoords$userId" . "[i * 2 + 1])));\n";
        echo "                }\n";
        echo "                polyline$userId.setPath(path$userId);\n";
        echo "            }\n";
        ++$ii;
    }
?>
            document.getElementById('applyStatus').innerHTML = '<i>Готово</i>';
        }
    }
    </script>
  </head>
  <body onload="initialize()">
    <table width="100%" height="100%">
      <tr>
        <td width="80%" height="100%"><div id="map_canvas"></div></td>
        <td valign="top">
          <table align="center">
            <tr><td colspan="2"><h2>Отображать день</h2></td></tr>
            <tr><td colspan="2"><div id="singleDate"></div></td></tr>
            <tr><td colspan="2"><h2>Отображать диапазон</h2></td></tr>
            <tr>
              <td>С:</td>
              <td><input type="date" id="startDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" /></td>
            </tr>
            <tr>
              <td>До:</td>
              <td><input type="date" id="endDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" /></td>
            </tr>
            <tr>
              <td colspan="2" align="center"><input type="submit" value="Применить" onclick="applyDateRange()" /></td>
            </tr>
            <tr>
              <td id="applyStatus" colspan="2" align="center"></td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
<?php
}
?>
