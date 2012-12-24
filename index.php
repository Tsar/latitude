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
    <script type="text/javascript"
      src="https://maps.googleapis.com/maps/api/js?sensor=false">
    </script>
<?php

    $db_server = 'localhost';
    $db_user   = 'latitude_palevo';
    $db_passwd = 'uQVyav38Wz9nmysz';
    $db_name   = 'latitude_palevo';

    $m = new mysqli($db_server, $db_user, $db_passwd, $db_name);

    $result = $m->query('SELECT user_id, coord1, coord2 FROM pos_history ORDER BY id');
    $paths = array();
    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $lat = $row['coord1'] / 1E6;
        $lon = $row['coord2'] / 1E6;
        if (array_key_exists($userId, $paths)) {
            $paths[$userId] .= ", $lat, $lon";
        } else {
            $paths[$userId] = "$lat, $lon";
        }
    }

    $result = $m->query('SELECT user_id, fullname, last_update_time, profile_image, googleplus FROM users');
    $fullnames = array();
    $lastUpdateTime = array();
    $profileImages = array();
    $googleplus = array();
    while ($row = $result->fetch_assoc()) {
        $fullnames[$row['user_id']] = $row['fullname'];
        $lastUpdateTime[$row['user_id']] = $row['last_update_time'];
        $profileImages[$row['user_id']] = $row['profile_image'];
        $googleplus[$row['user_id']] = $row['googleplus'];
    }

?>
    <script type="text/javascript">
      function initialize() {
<?php

    foreach ($paths as $userId => $path) {
        echo "        var pathCoords$userId = [$path];\n";
        echo "        var path$userId = [];\n";
        echo "        for (var i = 0, i_end = pathCoords$userId.length / 2; i < i_end; ++i) {\n";
        echo "            path$userId.push(new google.maps.LatLng(parseFloat(pathCoords$userId" . "[i * 2]), parseFloat(pathCoords$userId" . "[i * 2 + 1])));\n";
        echo "        }\n";
    }

?>
        var mapOptions = {
            center: new google.maps.LatLng(59.940568, 30.121078),
            zoom: 11,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };
        var map = new google.maps.Map(document.getElementById("map_canvas"),
            mapOptions);

        var markerImageShadow = new google.maps.MarkerImage('my_friend_placard.png',
                                                            new google.maps.Size(64, 78),
                                                            new google.maps.Point(0, 0),
                                                            new google.maps.Point(32, 72));
        var infoWindow = new google.maps.InfoWindow({content: 'empty'});
        var infoWindowFixed = false;
<?php

    $colors = array("#FF0000", "#009900", "#0000FF", "#FF00FF", "#000000");
    $colorsNum = count($colors);

    foreach ($paths as $userId => $path) {
        echo "        var polyline$userId = new google.maps.Polyline({\n";
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
        echo "            position: path$userId" . "[path$userId.length - 1],\n";
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
        echo "            infoWindow.setContent('<table$infoWindowContent');";
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
        echo "            infoWindow.setContent('<table bgcolor=\"#EEFFEE\"$infoWindowContent');\n";
        echo "        } else {\n";
        echo "            infoWindow.setContent('<table$infoWindowContent');\n";
        echo "        }\n";
        echo "    });\n";
    }

?>
      }
    </script>
  </head>
  <body onload="initialize()">
    <div id="map_canvas" style="width:100%; height:100%"></div>
  </body>
</html>
