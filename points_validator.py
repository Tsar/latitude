#!/usr/bin/env python3

import math

# On Ubuntu 12.04:
#  * sudo -i
#  * curl http://python-distribute.org/distribute_setup.py | python3
#  * easy_install-3.2 pymysql3
import pymysql

DB_HOST   = 'localhost'
DB_USER   = 'latitude_palevo'
DB_PASSWD = 'uQVyav38Wz9nmysz'
DB_NAME   = 'latitude_palevo'

# Result in km
def distance(origin, destination):
    lat1, lon1 = origin
    lat2, lon2 = destination
    radius = 6371  # km

    dlat = math.radians(lat2-lat1)
    dlon = math.radians(lon2-lon1)
    a = math.sin(dlat/2) * math.sin(dlat/2) + math.cos(math.radians(lat1)) \
        * math.cos(math.radians(lat2)) * math.sin(dlon/2) * math.sin(dlon/2)
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1-a))
    d = radius * c

    return d

if __name__ == "__main__":
    conn = pymysql.connect(host = DB_HOST, user = DB_USER, passwd = DB_PASSWD, db = DB_NAME, charset = 'utf8')
    cur = conn.cursor()
    cur2 = conn.cursor()

    # Last Valid Points
    lvp = {}

    cur.execute('SELECT id, user_id, coord1, coord2, timestamp FROM pos_history WHERE valid = 1 ORDER BY id')
    for row in cur:
        id, u, coord1, coord2, timestamp = row
        timestamp /= 3600000.0
        coord1 /= 1000000.0
        coord2 /= 1000000.0

        if not u in lvp:
            lvp[u] = (coord1, coord2, timestamp)
        else:
            if distance((lvp[u][0], lvp[u][1]), (coord1, coord2)) / (timestamp - lvp[u][2]) > 2000:
                # Current point invalid
                cur2.execute('UPDATE pos_history SET valid = 0 WHERE id = %d' % id)
                print("point is considered to be invalid: (%f, %f), id = %d" % (coord1, coord2, id))
            else:
                lvp[u] = (coord1, coord2, timestamp)
