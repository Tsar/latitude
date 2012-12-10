<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
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
        if (array_key_exists($userId, $paths)) {
            $paths[$userId] .= ', ' . $row['coord1'] . ', ' . $row['coord2'];
        } else {
            $paths[$userId] = $row['coord1'] . ', ' . $row['coord2'];
        }
    }

?>
    <script type="text/javascript">
      function initialize() {
<?php

    foreach ($paths as $userId => $path) {
        echo "        var pathCoords$userId = [$path];\n";
        echo "        var path$userId = [];\n";
        echo "        for (var i = 0, i_end = pathCoords$userId.length / 2; i < i_end; ++i) {\n";
        echo "          path$userId.push(new google.maps.LatLng(parseFloat(pathCoords$userId" . "[i * 2]), parseFloat(pathCoords$userId" . "[i * 2 + 1])));\n";
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
<?php

    foreach ($paths as $userId => $path) {
        echo "        var polyline$userId = new google.maps.Polyline({\n";
        echo "            map: map,\n";
        echo "            strokeColor: '#FF0000',\n";
        echo "            strokeWeight: 2,\n";
        echo "            strokeOpacity: 0.8,\n";
        echo "            path: path$userId\n";
        echo "        });\n";
    }

?>
      }
    </script>
  </head>
  <body onload="initialize()">
    <div id="map_canvas" style="width:100%; height:100%"></div>
  </body>
</html>
