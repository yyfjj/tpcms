#/bin/sh
redis-server /etc/redis.conf &
cd /resque
resque-web &
cd /www/shanpiao.tao3w.com/Resque/shanpiao
./resque.sh &
cd /www/shanpiao.tao3w.com
php index.php Api Shanpiao timeWheel &
