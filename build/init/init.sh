#!/bin/bash

set -e

if [ "$JELLYFIN_WEBHOOK_ENABLED" == "0" ];then
    rm -rf /etc/service/webhook
fi

if [ "$JELLYFIN_CRON_ENABLED" == "0" ];then
    rm -rf /etc/service/cron
else
    echo "${JELLYFIN_CRON_SCHEDULE} root php /usr/src/emby_pinyin/run.php --host=${JELLYFIN_HOST} --key=${JELLYFIN_KEY} --type=${JELLYFIN_SORT_TYPE} --all=y >> /var/log/cron" > /etc/cron.d/sort
fi