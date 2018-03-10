#!/bin/sh
while true;
do
  count=$(ps -ef | grep -c Wheel) #查找当前的进程中，计算server程序的数量
  if [ $count -lt 4 ]; then        #判断服务器进程的数量是否小于3（根据实际填上你的服务器进程数量）
    cd /www/shanpiao.tao3w.com
	php index.php Api Shanpiao timeWheel &                   #这里填入需要重启的服务器进程
  fi
  sleep 2                          #睡眠2s，周期性地检测服务器程序是不是崩溃了
done
