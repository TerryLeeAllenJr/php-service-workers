#!/usr/bin/env bash


# Remove log files older than 30 days.
find /var/bin/services/log/ -type f -mtime +30 -exec rm {} \;

# Remove intake files older than 1 days. These files are likely corrupted and left behind by the parser.
find /var/storage/temp/scripts/2nsml/ -type f -mtime +1 -exec rm {} \;
find /var/storage/temp/scripts/3nsml/ -type f -mtime +1 -exec rm {} \;
find /var/storage/temp/outlooks/3nsml/ -type f -mtime +1 -exec rm {} \;
find /var/storage/temp/advisories/3nsml/ -type f -mtime +1 -exec rm {} \;