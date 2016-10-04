#!/usr/bin/env bash


DIRS=(
    /var/storage/temp/scripts/2nsml
    /var/storage/temp/scripts/3nsml
    /var/storage/temp/outlooks/3nsml
    /var/storage/temp/advisories/3nsml
)

while true
    do clear
    for DIRECTORY in "${DIRS[@]}"
        do
            echo "${DIRECTORY}";
            ls -la ${DIRECTORY};
            echo ""
        done
        sleep 1
    done