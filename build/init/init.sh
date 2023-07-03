#!/bin/bash

set -e

if [ "$JELLYFIN_WEBHOOK_ENABLED" == "0" ];then
    rm -rf /etc/service/webhook
fi

if [ "$JELLYFIN_CRON_ENABLED" == "0" ];then
    rm -rf /etc/service/cron
else
    echo "${JELLYFIN_CRON_SCHEDULE} /usr/src/emby_pinyin/run.php --server=${JELLYFIN_HOST} --key=${JELLYFIN_KEY} --type=${JELLYFIN_SORT_TYPE} --all=y" > /etc/cron.d/sort
fi