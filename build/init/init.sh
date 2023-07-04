#!/bin/bash

set -e

if [ "$WEBHOOK_ENABLED" == "0" ];then
    rm -rf /etc/service/webhook
fi

if [ "$CRON_ENABLED" == "0" ];then
    rm -rf /etc/service/cron
else
    echo "${CRON_SCHEDULE} root php /usr/src/emby_pinyin/run.php --host=${HOST} --key=${API_KEY} --type=${SORT_TYPE} --all=y >> /var/log/cron" > /etc/cron.d/sort
fi