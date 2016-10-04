#!/usr/bin/env bash

# This script looks for any editorial content that is older than ${NUMDAYS} and moves it into the file's relative
# 'archive' directory.

NUMDAYS=1

find /var/storage/editorial/scripts/xml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/scripts/xml/archive/ \;
find /var/storage/editorial/scripts/2nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/scripts/2nsml/archive/ \;
find /var/storage/editorial/scripts/3nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/scripts/3nsml/archive/ \;


find /var/storage/editorial/outlooks/xml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/outlooks/xml/archive/ \;
find /var/storage/editorial/outlooks/2nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/outlooks/2nsml/archive/ \;
find /var/storage/editorial/outlooks/3nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/outlooks/3nsml/archive/ \;

find /var/storage/editorial/advisories/xml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/advisories/xml/archive/ \;
find /var/storage/editorial/advisories/2nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/advisories/2nsml/archive/ \;
find /var/storage/editorial/advisories/3nsml/ -maxdepth 1 -type f -mtime +${NUMDAYS} -exec mv '{}' /var/storage/editorial/advisories/3nsml/archive/ \;

