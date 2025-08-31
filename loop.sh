#!/bin/bash
while [ 1 ]; do
	/usr/local/bin/php /home/parkuser/public_html/public_html/crons/SensorInterpletion.php &
    sleep 8
done
